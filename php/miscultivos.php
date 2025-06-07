<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.html");
    exit();
}

include 'conexion.php'; 
$id_usuario_actual = $_SESSION['id_usuario'];

$cultivos_usuario = [];
$mensaje_error = '';
$mensaje_exito = '';

if (isset($_SESSION['mensaje_borrado'])) {
    $mensaje_exito = $_SESSION['mensaje_borrado'];
    unset($_SESSION['mensaje_borrado']);
}
if (isset($_SESSION['error_borrado'])) {
    $mensaje_error = $_SESSION['error_borrado'];
    unset($_SESSION['error_borrado']);
}

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // Consulta principal de cultivos
        $sql_cultivos_main = "SELECT
                    c.id_cultivo, c.fecha_inicio, c.fecha_fin AS fecha_fin_registrada,
                    c.area_hectarea, tc.nombre_cultivo, tc.tiempo_estimado_frutos,
                    m.nombre AS nombre_municipio
                FROM cultivos c
                JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                JOIN municipio m ON c.id_municipio = m.id_municipio
                WHERE c.id_usuario = :id_usuario
                ORDER BY c.fecha_inicio DESC";
        $stmt_cultivos_main = $pdo->prepare($sql_cultivos_main);
        $stmt_cultivos_main->bindParam(':id_usuario', $id_usuario_actual);
        $stmt_cultivos_main->execute();
        $cultivos_usuario = $stmt_cultivos_main->fetchAll(PDO::FETCH_ASSOC);

        // Para cada cultivo, obtener sus tareas pendientes/próximas
        for ($i = 0; $i < count($cultivos_usuario); $i++) {
            $id_cultivo_actual = $cultivos_usuario[$i]['id_cultivo'];
            $hoy_str = date('Y-m-d');

            // Obtener tratamientos programados desde hoy en adelante para este cultivo
            $sql_tareas = "SELECT tipo_tratamiento, producto_usado, etapas, fecha_aplicacion_estimada
                           FROM tratamiento_cultivo
                           WHERE id_cultivo = :id_cultivo 
                             AND fecha_aplicacion_estimada >= :hoy
                           ORDER BY fecha_aplicacion_estimada ASC";
            $stmt_tareas = $pdo->prepare($sql_tareas);
            $stmt_tareas->bindParam(':id_cultivo', $id_cultivo_actual, PDO::PARAM_INT);
            $stmt_tareas->bindParam(':hoy', $hoy_str, PDO::PARAM_STR);
            $stmt_tareas->execute();
            $tareas_pendientes = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);
            
            $cultivos_usuario[$i]['tarea_hoy'] = null;
            $cultivos_usuario[$i]['proxima_tarea'] = null;

            if ($tareas_pendientes) {
                // Verificar si la primera tarea es para hoy
                if ($tareas_pendientes[0]['fecha_aplicacion_estimada'] == $hoy_str) {
                    $cultivos_usuario[$i]['tarea_hoy'] = $tareas_pendientes[0];
                    // Si hay más tareas, la siguiente sería la próxima
                    if (isset($tareas_pendientes[1])) {
                        $cultivos_usuario[$i]['proxima_tarea'] = $tareas_pendientes[1];
                    }
                } else {
                    // Si la primera tarea no es para hoy, es la próxima tarea
                    $cultivos_usuario[$i]['proxima_tarea'] = $tareas_pendientes[0];
                }
            }
        }

    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los cultivos o sus tareas: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Cultivos - GAG</title>
    <style>
        /* Estilos generales (copiados de tu versión anterior de miscultivos.php) */
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;transition:height .3s ease}
        .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;transition:background-color .3s,color .3s,padding .3s ease;white-space:nowrap;font-size:.9em}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}
        .page-container{max-width:1200px;margin:20px auto;padding:20px}
        .page-container > h2.page-title{text-align:center;color:#4caf50;margin-bottom:25px;font-size:1.8em}
        .cultivos-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:25px} /* Aumentado minmax para más espacio */
        .cultivo-card{background-color:#ffffff;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);padding:20px;display:flex;flex-direction:column;transition:transform .3s ease,box-shadow .3s ease}
        .cultivo-card:hover{transform:translateY(-5px);box-shadow:0 6px 16px rgba(0,0,0,0.15)}
        .cultivo-card h3{margin-top:0;margin-bottom:12px;color:#0056b3;font-size:1.3em;border-bottom:1px solid #f0f0f0;padding-bottom:10px}
        .cultivo-card .info-section p{font-size:.95em;color:#444;margin-bottom:8px;line-height:1.6}
        .cultivo-card .info-section strong{color:#111}
        .cultivo-card .status-section{font-size:.85em;color:#555;display:block;margin-top:15px;padding-top:10px;border-top:1px solid #f0f0f0}
        .cultivo-card .status-section strong{color:#333}
        
        /* NUEVOS ESTILOS PARA TAREAS */
        .tareas-section {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #e0e0e0; /* Separador diferente */
            font-size: 0.85em;
        }
        .tareas-section p { margin: 5px 0; }
        .tarea-hoy { color: #d9534f; font-weight: bold; } /* Rojo para destacar tarea de hoy */
        .proxima-tarea { color: #5bc0de; } /* Azul claro para próxima tarea */
        .no-tareas { color: #777; font-style: italic;}

        .cultivo-actions{margin-top:15px;text-align:right}
        .btn-delete-cultivo{background-color:#e74c3c;color:#fff;border:none;padding:8px 15px;border-radius:5px;text-decoration:none;font-size:.85em;cursor:pointer;transition:background-color .3s ease}
        .btn-delete-cultivo:hover{background-color:#c0392b}
        .no-cultivos{text-align:center;width:100%;padding:40px 20px;font-size:1.2em;color:#666;background-color:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.05)}
        .no-cultivos p{margin-bottom:15px}
        .no-cultivos a{color:#4caf50;font-weight:700;text-decoration:none}
        .no-cultivos a:hover{text-decoration:underline}
        .error-message{color:#D8000C;text-align:center;width:100%;padding:15px;background-color:#FFD2D2;border:1px solid #D8000C;border-radius:5px;margin-bottom:20px}
        .success-message{color:#270;background-color:#DFF2BF;border:1px solid #4F8A10;padding:15px;margin-bottom:20px;text-align:center;border-radius:5px}
        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}.page-container{padding:15px}.page-container > h2.page-title{font-size:1.6em;margin-bottom:20px}.cultivos-grid{grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:20px}.cultivo-card{padding:15px}.cultivo-card h3{font-size:1.2em}}
        @media (max-width:767px){.logo img{height:60px}.menu-toggle{font-size:1.6rem}.page-container > h2.page-title{font-size:1.5em}.cultivos-grid{grid-template-columns:1fr}}
        @media (max-width:480px){.logo img{height:50px}.page-container > h2.page-title{font-size:1.4em}.cultivo-card h3{font-size:1.1em}.cultivo-card .info-section p,.cultivo-card .status-section,.tareas-section{font-size:.9em}}
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="index.php">Inicio</a>
            <a href="miscultivos.php" class="active">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario.php">Calendario</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h2 class="page-title">Mis Cultivos Registrados</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_exito)): ?>
            <p class="success-message"><?php echo htmlspecialchars($mensaje_exito); ?></p>
        <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($cultivos_usuario)): ?>
            <div class="no-cultivos">
                <p>Aún no has registrado ningún cultivo.</p>
                <p><a href="crearcultivos.php">¡Registra tu primer cultivo aquí!</a></p>
            </div>
        <?php elseif (!empty($cultivos_usuario)): ?>
            <div class="cultivos-grid">
                <?php foreach ($cultivos_usuario as $cultivo): ?>
                    <div class="cultivo-card">
                        <h3><?php echo htmlspecialchars($cultivo['nombre_cultivo']); ?> en <?php echo htmlspecialchars($cultivo['nombre_municipio']); ?></h3>
                        <div class="info-section">
                            <p>
                                <strong>Inicio:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_inicio']))); ?><br>
                                <strong>Área:</strong> <?php echo htmlspecialchars($cultivo['area_hectarea']); ?> ha<br>
                            </p>
                        </div>
                        <div class="status-section">
                            <?php
                            // --- LÓGICA COSECHA (EXISTENTE) ---
                            $hoy_obj = new DateTime(); // Objeto DateTime para hoy
                            $hoy_obj->setTime(0,0,0); // Comparar solo fechas
                            $fechaInicioObj = new DateTime($cultivo['fecha_inicio']);
                            $mensajeCosecha = "Fecha de cosecha no determinada.";

                            if (!empty($cultivo['fecha_fin_registrada'])) {
                                $fechaFinEstimadaObj = new DateTime($cultivo['fecha_fin_registrada']);
                            } elseif (!empty($cultivo['tiempo_estimado_frutos'])) {
                                $fechaFinEstimadaObj = clone $fechaInicioObj;
                                $fechaFinEstimadaObj->add(new DateInterval('P' . (int)$cultivo['tiempo_estimado_frutos'] . 'D'));
                            } else {
                                $fechaFinEstimadaObj = null;
                            }

                            if ($fechaFinEstimadaObj) {
                                $fechaCompararCosecha = clone $fechaFinEstimadaObj;
                                $fechaCompararCosecha->setTime(0,0,0);
                                if ($fechaCompararCosecha < $hoy_obj) {
                                    $mensajeCosecha = "Finalizado el " . $fechaFinEstimadaObj->format('d/m/Y');
                                } else {
                                    $diferencia = $hoy_obj->diff($fechaCompararCosecha);
                                    if ($diferencia->days == 0) { $mensajeCosecha = "Cosecha estimada: ¡Hoy!"; }
                                    elseif ($diferencia->days == 1 && !$diferencia->invert) { $mensajeCosecha = "Cosecha estimada: Mañana (1 día)."; }
                                    elseif (!$diferencia->invert) { $mensajeCosecha = "Cosecha estimada: En {$diferencia->days} días (" . $fechaFinEstimadaObj->format('d/m/Y') . ")."; }
                                    // else: si invert es 1, ya pasó, cubierto por la primera condición.
                                }
                            }
                            echo "<strong>Cosecha:</strong> " . htmlspecialchars($mensajeCosecha) . "<br>";

                            // --- LÓGICA ABONO (EXISTENTE) ---
                            // (Tu código para $progresoAbono) ...
                            $progresoAbono = "Abono: No hay datos."; // Placeholder
                            if (isset($pdo)) { 
                                try {
                                    $sql_abono = "SELECT tipo_tratamiento, producto_usado, etapas, id_tratamiento
                                                  FROM tratamiento_cultivo
                                                  WHERE id_cultivo = :id_cultivo
                                                    AND (LOWER(tipo_tratamiento) LIKE '%abono%' OR LOWER(tipo_tratamiento) LIKE '%fertilizante%')
                                                  ORDER BY fecha_aplicacion_estimada DESC LIMIT 1"; // Ordenar por fecha si la tienes
                                    $stmt_abono = $pdo->prepare($sql_abono);
                                    $stmt_abono->bindParam(':id_cultivo', $cultivo['id_cultivo']);
                                    $stmt_abono->execute();
                                    $ultimo_abono = $stmt_abono->fetch(PDO::FETCH_ASSOC);

                                    if ($ultimo_abono) {
                                        $progresoAbono = "Último abono: " . htmlspecialchars($ultimo_abono['tipo_tratamiento']);
                                    } else {
                                        $progresoAbono = "Abono: Ningún tratamiento de abono registrado.";
                                    }
                                } catch (PDOException $e) {
                                    $progresoAbono = "Abono: Error consulta.";
                                }
                            }
                            echo "<strong>" . htmlspecialchars($progresoAbono) . "</strong>";
                            ?>
                        </div>

                        <!-- NUEVA SECCIÓN DE TAREAS -->
                        <div class="tareas-section">
                            <strong>Tareas Próximas:</strong><br>
                            <?php if ($cultivo['tarea_hoy']): 
                                $tarea_h = $cultivo['tarea_hoy'];
                            ?>
                                <p class="tarea-hoy">
                                    HOY (<?php echo htmlspecialchars(date("d/m", strtotime($tarea_h['fecha_aplicacion_estimada']))); ?>):
                                    <?php echo htmlspecialchars($tarea_h['tipo_tratamiento']); ?> 
                                    (<?php echo htmlspecialchars($tarea_h['producto_usado']); ?>)
                                    - Etapa: <?php echo htmlspecialchars($tarea_h['etapas']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($cultivo['proxima_tarea']): 
                                $prox_t = $cultivo['proxima_tarea'];
                                $fecha_prox_tarea_obj = new DateTime($prox_t['fecha_aplicacion_estimada']);
                                $diff_prox = $hoy_obj->diff($fecha_prox_tarea_obj);
                                $dias_para_prox = $diff_prox->invert ? -$diff_prox->days : $diff_prox->days; // Negativo si ya pasó
                            ?>
                                <p class="proxima-tarea">
                                    Siguiente (<?php echo htmlspecialchars($fecha_prox_tarea_obj->format("d/m")); ?> - 
                                    <?php 
                                        if ($dias_para_prox == 0 && !$cultivo['tarea_hoy']) echo "¡Hoy!"; // Si es hoy y no se listó arriba
                                        elseif ($dias_para_prox == 1) echo "Mañana";
                                        elseif ($dias_para_prox > 1) echo "En {$dias_para_prox} días";
                                        else echo "Fecha pasada"; // O no mostrar si ya pasó y no es hoy
                                    ?>):
                                    <?php echo htmlspecialchars($prox_t['tipo_tratamiento']); ?>
                                    (<?php echo htmlspecialchars($prox_t['producto_usado']); ?>)
                                     - Etapa: <?php echo htmlspecialchars($prox_t['etapas']); ?>
                                </p>
                            <?php elseif (!$cultivo['tarea_hoy']): ?>
                                <p class="no-tareas">No hay próximas tareas programadas.</p>
                            <?php endif; ?>
                        </div>

                        <div class="cultivo-actions">
                            <a href="borrar_cultivo.php?id_cultivo=<?php echo $cultivo['id_cultivo']; ?>"
                               class="btn-delete-cultivo"
                               onclick="return confirm('¿Estás seguro de que deseas borrar este cultivo? Esta acción no se puede deshacer.');">
                                Borrar Cultivo
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div> 
        <?php endif; ?>
    </div> 

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
                });
            }
        });
    </script>
</body>
</html>