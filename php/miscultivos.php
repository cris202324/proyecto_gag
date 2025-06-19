<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../pages/auth/login.html");
    exit();
}

include 'conexion.php';
$id_usuario_actual = $_SESSION['id_usuario'];

$cultivos_usuario = [];
$mensaje_error = '';
$mensaje_exito = '';

// IDs de estado (ajústalos según tu tabla estado_cultivo_definiciones)
$id_estado_en_progreso = 1;
$id_estado_terminado = 2;

// Determinar qué estado de cultivo mostrar (para la vista actual)
$estado_a_mostrar_get = isset($_GET['estado']) && $_GET['estado'] === 'terminado' ? 'terminado' : 'en_progreso';
$estado_a_mostrar_db = ($estado_a_mostrar_get === 'terminado') ? $id_estado_terminado : $id_estado_en_progreso;


$titulo_pagina = ($estado_a_mostrar_db == $id_estado_en_progreso) ? "Mis Cultivos en Progreso" : "Mis Cultivos Terminados";
$link_ver_otros_cultivos_href = ($estado_a_mostrar_db == $id_estado_en_progreso) ? "miscultivos.php?estado=terminado" : "miscultivos.php";
$link_ver_otros_cultivos_texto = ($estado_a_mostrar_db == $id_estado_en_progreso) ? "Ver Cultivos Terminados" : "Ver Cultivos en Progreso";

// Texto para el botón de reporte
$texto_boton_reporte = ($estado_a_mostrar_db == $id_estado_en_progreso) ? "Reporte Cultivos en Progreso" : "Reporte Cultivos Terminados";
$param_estado_reporte = ($estado_a_mostrar_db == $id_estado_en_progreso) ? "en_progreso" : "terminado";


if (isset($_SESSION['mensaje_accion_cultivo'])) {
    $mensaje_exito = $_SESSION['mensaje_accion_cultivo'];
    unset($_SESSION['mensaje_accion_cultivo']);
}
if (isset($_SESSION['error_accion_cultivo'])) {
    $mensaje_error = $_SESSION['error_accion_cultivo'];
    unset($_SESSION['error_accion_cultivo']);
}

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // --- INICIO: ACTUALIZAR AUTOMÁTICAMENTE CULTIVOS A "TERMINADO" ---
        $hoy_para_sql = date('Y-m-d');
        $sql_auto_terminar = "UPDATE cultivos
                              SET id_estado_cultivo = :id_estado_terminado
                              WHERE id_usuario = :id_usuario_actual_update
                                AND id_estado_cultivo = :id_estado_en_progreso_update
                                AND fecha_fin < :hoy_actual";

        $stmt_auto_terminar = $pdo->prepare($sql_auto_terminar);
        $stmt_auto_terminar->bindParam(':id_estado_terminado', $id_estado_terminado, PDO::PARAM_INT);
        $stmt_auto_terminar->bindParam(':id_usuario_actual_update', $id_usuario_actual, PDO::PARAM_STR);
        $stmt_auto_terminar->bindParam(':id_estado_en_progreso_update', $id_estado_en_progreso, PDO::PARAM_INT);
        $stmt_auto_terminar->bindParam(':hoy_actual', $hoy_para_sql, PDO::PARAM_STR);
        $stmt_auto_terminar->execute();
        // --- FIN: ACTUALIZAR AUTOMÁTICAMENTE CULTIVOS ---


        // Consulta principal de cultivos (filtrada por el estado seleccionado)
        $sql_cultivos_main = "SELECT
                    c.id_cultivo, c.fecha_inicio, c.fecha_fin AS fecha_fin_registrada,
                    c.area_hectarea, tc.nombre_cultivo, tc.tiempo_estimado_frutos,
                    m.nombre AS nombre_municipio,
                    ecd.nombre_estado AS estado_actual_cultivo,
                    c.id_estado_cultivo
                FROM cultivos c
                JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                JOIN municipio m ON c.id_municipio = m.id_municipio
                LEFT JOIN estado_cultivo_definiciones ecd ON c.id_estado_cultivo = ecd.id_estado_cultivo
                WHERE c.id_usuario = :id_usuario
                  AND c.id_estado_cultivo = :id_estado_a_mostrar_db
                ORDER BY c.fecha_inicio DESC";

        $stmt_cultivos_main = $pdo->prepare($sql_cultivos_main);
        $stmt_cultivos_main->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
        $stmt_cultivos_main->bindParam(':id_estado_a_mostrar_db', $estado_a_mostrar_db, PDO::PARAM_INT);
        $stmt_cultivos_main->execute();
        $cultivos_usuario = $stmt_cultivos_main->fetchAll(PDO::FETCH_ASSOC);

        // Para cada cultivo EN PROGRESO, obtener sus tareas pendientes/próximas
        if ($estado_a_mostrar_db == $id_estado_en_progreso) {
            for ($i = 0; $i < count($cultivos_usuario); $i++) {
                $id_cultivo_actual_loop = $cultivos_usuario[$i]['id_cultivo']; // Renombrada para evitar confusión
                $hoy_str = date('Y-m-d');
                $sql_tareas = "SELECT tipo_tratamiento, producto_usado, etapas, fecha_aplicacion_estimada
                               FROM tratamiento_cultivo
                               WHERE id_cultivo = :id_cultivo_loop_param
                                 AND fecha_aplicacion_estimada >= :hoy
                                 AND estado_tratamiento = 'Pendiente'
                               ORDER BY fecha_aplicacion_estimada ASC";
                $stmt_tareas = $pdo->prepare($sql_tareas);
                $stmt_tareas->bindParam(':id_cultivo_loop_param', $id_cultivo_actual_loop, PDO::PARAM_INT);
                $stmt_tareas->bindParam(':hoy', $hoy_str, PDO::PARAM_STR);
                $stmt_tareas->execute();
                $tareas_pendientes = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);

                $cultivos_usuario[$i]['tarea_hoy'] = null;
                $cultivos_usuario[$i]['proxima_tarea'] = null;

                if ($tareas_pendientes) {
                    if ($tareas_pendientes[0]['fecha_aplicacion_estimada'] == $hoy_str) {
                        $cultivos_usuario[$i]['tarea_hoy'] = $tareas_pendientes[0];
                        if (isset($tareas_pendientes[1])) {
                            $cultivos_usuario[$i]['proxima_tarea'] = $tareas_pendientes[1];
                        }
                    } else {
                        $cultivos_usuario[$i]['proxima_tarea'] = $tareas_pendientes[0];
                    }
                }
            }
        }

    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los cultivos: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo_pagina); ?> - GAG</title>
    <style>
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
        .page-container > h2.page-title{text-align:center;color:#4caf50;margin-bottom:10px;font-size:1.8em} /* Reducido margin-bottom */
        
        .actions-bar { /* Nuevo: Contenedor para botones de acción de página */
            display: flex;
            justify-content: space-between; /* Para separar "Ver otros" y "Reporte" */
            align-items: center;
            margin: 10px 0 20px 0; /* Espaciado */
            flex-wrap: wrap; /* Para que se ajusten en pantallas pequeñas */
            gap: 10px; /* Espacio entre elementos si se envuelven */
        }
        .view-toggle-link a, .report-page-button { 
            color: #4caf50; 
            font-weight: bold; 
            text-decoration: none; 
            padding: 8px 15px; 
            border: 1px solid #4caf50; 
            border-radius: 5px; 
            transition: background-color 0.3s, color 0.3s;
            background-color: #fff; /* Fondo blanco por defecto */
        }
        .view-toggle-link a:hover, .report-page-button:hover { 
            background-color: #e8f5e9; /* Un verde muy claro al pasar el mouse */
            color: #388e3c; /* Verde más oscuro para el texto */
        }
        .report-page-button { /* Estilo específico si es necesario diferenciarlo más */
            /* background-color: #28a745; */ /* Opcional: Si quieres que resalte más */
            /* color: white; */
            /* border-color: #28a745; */
        }


        .cultivos-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:25px} 
        .cultivo-card{background-color:#ffffff;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);padding:20px;display:flex;flex-direction:column;transition:transform .3s ease,box-shadow .3s ease}
        .cultivo-card:hover{transform:translateY(-5px);box-shadow:0 6px 16px rgba(0,0,0,0.15)}
        .cultivo-card h3{margin-top:0;margin-bottom:12px;color:#0056b3;font-size:1.3em;border-bottom:1px solid #f0f0f0;padding-bottom:10px}
        .cultivo-card .info-section p{font-size:.95em;color:#444;margin-bottom:8px;line-height:1.6}
        .cultivo-card .info-section strong{color:#111}
        .cultivo-card .status-section{font-size:.85em;color:#555;display:block;margin-top:15px;padding-top:10px;border-top:1px solid #f0f0f0}
        .cultivo-card .status-section strong{color:#333}
        .tareas-section {margin-top:15px;padding-top:10px;border-top:1px dashed #e0e0e0;font-size:0.85em;}
        .tareas-section p { margin: 5px 0; }
        .tarea-hoy { color: #d9534f; font-weight: bold; } 
        .proxima-tarea { color:rgb(0, 0, 0); } 
        .no-tareas { color: #777; font-style: italic;}
        .cultivo-actions{margin-top:15px;text-align:right;display:flex;justify-content:flex-end;gap:10px}
        .btn-cultivo-action {color:white !important;border:none;padding:8px 15px;border-radius:5px;text-decoration:none;font-size:0.85em;cursor:pointer;transition:background-color 0.3s ease;}
        .btn-terminar-cultivo { background-color:rgb(131, 20, 20); }
        .btn-terminar-cultivo:hover { background-color:rgb(175, 23, 23); }
        .btn-ver-detalles { background-color: #5cb85c; }
        .btn-ver-detalles:hover { background-color: #4cae4c; }
        .no-cultivos{text-align:center;width:100%;padding:40px 20px;font-size:1.2em;color:#666;background-color:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.05)}
        .no-cultivos p{margin-bottom:15px}
        .no-cultivos a{color:#4caf50;font-weight:700;text-decoration:none}
        .no-cultivos a:hover{text-decoration:underline}
        .error-message{color:#D8000C;text-align:center;width:100%;padding:15px;background-color:#FFD2D2;border:1px solid #D8000C;border-radius:5px;margin-bottom:20px}
        .success-message{color:#270;background-color:#DFF2BF;border:1px solid #4F8A10;padding:15px;margin-bottom:20px;text-align:center;border-radius:5px}
        
        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}.page-container{padding:15px}.page-container > h2.page-title{font-size:1.6em;margin-bottom:20px}.cultivos-grid{grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:20px}.cultivo-card{padding:15px}.cultivo-card h3{font-size:1.2em}}
        
        @media (max-width:767px){
            .logo img{height:60px}
            .menu-toggle{font-size:1.6rem}
            .page-container > h2.page-title{font-size:1.5em}
            .cultivos-grid{grid-template-columns:1fr} 
            .cultivo-actions { flex-direction: column; align-items: stretch;} 
            .cultivo-actions .btn-cultivo-action { width: 100%; box-sizing: border-box; margin-bottom: 5px; margin-right:0;} 
            .cultivo-actions .btn-cultivo-action:last-child {margin-bottom:0;}
            .actions-bar { justify-content: center; flex-direction: column; } /* Centra y apila en móvil */
            .view-toggle-link, .report-page-button { width: 90%; text-align: center; margin-bottom: 10px; }
        }
        @media (max-width:480px){.logo img{height:50px}.page-container > h2.page-title{font-size:1.4em}.cultivo-card h3{font-size:1.1em}.cultivo-card .info-section p,.cultivo-card .status-section,.tareas-section{font-size:.9em}}
    </style>
</head>
<body>
    <div class="header">
        <div class="logo"><img src="../img/logo.png" alt="Logo GAG" /></div>
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
        <h2 class="page-title"><?php echo htmlspecialchars($titulo_pagina); ?></h2>

        <div class="actions-bar">
            <div class="view-toggle-link">
                <a href="<?php echo $link_ver_otros_cultivos_href; ?>"><?php echo $link_ver_otros_cultivos_texto; ?></a>
            </div>
            <?php if (!empty($cultivos_usuario)): // Solo mostrar el botón de reporte si hay cultivos en la vista actual ?>
                <a href="generar_reporte_miscultivos_excel.php?estado=<?php echo $param_estado_reporte; ?>" class="report-page-button" target="_blank">
                    <?php echo htmlspecialchars($texto_boton_reporte); ?>
                </a>
            <?php endif; ?>
        </div>


        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_exito)): ?>
            <p class="success-message"><?php echo htmlspecialchars($mensaje_exito); ?></p>
        <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($cultivos_usuario)): ?>
            <div class="no-cultivos">
                <?php if ($estado_a_mostrar_db == $id_estado_en_progreso): ?>
                    <p>No tienes cultivos "En Progreso" registrados.</p>
                    <p><a href="crearcultivos.php">¡Registra un nuevo cultivo aquí!</a></p>
                <?php else: ?>
                    <p>No tienes cultivos "Terminados" para mostrar.</p>
                <?php endif; ?>
            </div>
        <?php elseif (!empty($cultivos_usuario)): ?>
            <div class="cultivos-grid">
                <?php foreach ($cultivos_usuario as $cultivo): ?>
                    <div class="cultivo-card">
                        <div class="cultivo-card-content">
                            <h3><?php echo htmlspecialchars($cultivo['nombre_cultivo']); ?> en <?php echo htmlspecialchars($cultivo['nombre_municipio']); ?></h3>
                            <div class="info-section">
                                <p>
                                    <strong>Inicio:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_inicio']))); ?><br>
                                    <strong>Área:</strong> <?php echo htmlspecialchars($cultivo['area_hectarea']); ?> ha<br>
                                    <?php if (isset($cultivo['estado_actual_cultivo'])): ?>
                                        <strong>Estado:</strong> <?php echo htmlspecialchars($cultivo['estado_actual_cultivo']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($cultivo['id_estado_cultivo'] == $id_estado_terminado && !empty($cultivo['fecha_fin_registrada'])): ?>
                                        <strong>Finalizado el:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_fin_registrada']))); ?><br>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="status-section">
                                <?php
                                $hoy_obj = new DateTime(); $hoy_obj->setTime(0,0,0);
                                $fechaInicioObj = new DateTime($cultivo['fecha_inicio']);
                                $mensajeCosecha = "Fecha de cosecha no determinada.";
                                if (!empty($cultivo['fecha_fin_registrada'])) {
                                    $fechaFinEstimadaObj = new DateTime($cultivo['fecha_fin_registrada']);
                                } elseif (!empty($cultivo['tiempo_estimado_frutos'])) {
                                    $fechaFinEstimadaObj = clone $fechaInicioObj;
                                    $fechaFinEstimadaObj->add(new DateInterval('P' . (int)$cultivo['tiempo_estimado_frutos'] . 'D'));
                                } else { $fechaFinEstimadaObj = null; }
                                if ($fechaFinEstimadaObj) {
                                    $fechaCompararCosecha = clone $fechaFinEstimadaObj; $fechaCompararCosecha->setTime(0,0,0);
                                    if ($fechaCompararCosecha < $hoy_obj && $cultivo['id_estado_cultivo'] == $id_estado_terminado) {
                                         $mensajeCosecha = "Finalizado el " . $fechaFinEstimadaObj->format('d/m/Y');
                                    } elseif ($fechaCompararCosecha < $hoy_obj) {
                                         $mensajeCosecha = "Cosecha debió ser el " . $fechaFinEstimadaObj->format('d/m/Y');
                                    } else { $diferencia = $hoy_obj->diff($fechaCompararCosecha);
                                        if ($diferencia->days == 0) { $mensajeCosecha = "Cosecha estimada: ¡Hoy!"; }
                                        elseif ($diferencia->days == 1 && !$diferencia->invert) { $mensajeCosecha = "Cosecha estimada: Mañana (1 día)."; }
                                        elseif (!$diferencia->invert) { $mensajeCosecha = "Cosecha estimada: En {$diferencia->days} días (" . $fechaFinEstimadaObj->format('d/m/Y') . ")."; }
                                    }
                                }
                                echo "<strong>Cosecha:</strong> " . htmlspecialchars($mensajeCosecha) . "<br>";
                                $progresoAbono = "Abono: No hay datos.";
                                if (isset($pdo)) { try { $sql_abono = "SELECT tipo_tratamiento, producto_usado, etapas, id_tratamiento FROM tratamiento_cultivo WHERE id_cultivo = :id_cultivo_abono AND (LOWER(tipo_tratamiento) LIKE '%abono%' OR LOWER(tipo_tratamiento) LIKE '%fertilizante%') ORDER BY COALESCE(fecha_aplicacion_estimada, '0000-00-00') DESC, id_tratamiento DESC LIMIT 1"; $stmt_abono = $pdo->prepare($sql_abono); $stmt_abono->bindParam(':id_cultivo_abono', $cultivo['id_cultivo']); $stmt_abono->execute(); $ultimo_abono = $stmt_abono->fetch(PDO::FETCH_ASSOC); if ($ultimo_abono) { $progresoAbono = "Último abono: " . htmlspecialchars($ultimo_abono['tipo_tratamiento']); } else { $progresoAbono = "Abono: Ningún tratamiento de abono registrado."; } } catch (PDOException $e) { $progresoAbono = "Abono: Error consulta."; } }
                                echo "<strong>" . htmlspecialchars($progresoAbono) . "</strong>";
                                ?>
                            </div>

                            <?php if ($cultivo['id_estado_cultivo'] == $id_estado_en_progreso): ?>
                            <div class="tareas-section">
                                <strong>Tareas Próximas:</strong><br>
                                <?php if (isset($cultivo['tarea_hoy']) && $cultivo['tarea_hoy']): $tarea_h = $cultivo['tarea_hoy']; ?>
                                    <p class="tarea-hoy">
                                        HOY (<?php echo htmlspecialchars(date("d/m", strtotime($tarea_h['fecha_aplicacion_estimada']))); ?>):
                                        <?php echo htmlspecialchars($tarea_h['tipo_tratamiento']); ?>
                                        (<?php echo htmlspecialchars($tarea_h['producto_usado']); ?>)
                                        - Etapa: <?php echo htmlspecialchars($tarea_h['etapas']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (isset($cultivo['proxima_tarea']) && $cultivo['proxima_tarea']): $prox_t = $cultivo['proxima_tarea']; $fecha_prox_tarea_obj = new DateTime($prox_t['fecha_aplicacion_estimada']); $diff_prox = $hoy_obj->diff($fecha_prox_tarea_obj); $dias_para_prox = $diff_prox->invert ? -$diff_prox->days : $diff_prox->days; ?>
                                    <p class="proxima-tarea">
                                        Siguiente (<?php echo htmlspecialchars($fecha_prox_tarea_obj->format("d/m")); ?> -
                                        <?php if ($dias_para_prox == 0 && !(isset($cultivo['tarea_hoy']) && $cultivo['tarea_hoy'])) echo "¡Hoy!"; elseif ($dias_para_prox == 1) echo "Mañana"; elseif ($dias_para_prox > 1) echo "En {$dias_para_prox} días"; else echo "Fecha pasada"; ?>):
                                        <?php echo htmlspecialchars($prox_t['tipo_tratamiento']); ?>
                                        (<?php echo htmlspecialchars($prox_t['producto_usado']); ?>)
                                         - Etapa: <?php echo htmlspecialchars($prox_t['etapas']); ?>
                                    </p>
                                <?php elseif (!(isset($cultivo['tarea_hoy']) && $cultivo['tarea_hoy'])): ?>
                                    <p class="no-tareas">No hay próximas tareas programadas.</p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="cultivo-actions">
                            <a href="detalle_cultivo.php?id_cultivo=<?php echo $cultivo['id_cultivo']; ?>" class="btn-cultivo-action btn-ver-detalles">Ver Detalles</a>
                            <?php if ($cultivo['id_estado_cultivo'] == $id_estado_en_progreso): ?>
                                <a href="marcar_cultivo_terminado.php?id_cultivo=<?php echo $cultivo['id_cultivo']; ?>"
                                   class="btn-cultivo-action btn-terminar-cultivo"
                                   onclick="return confirm('¿Estás seguro de que deseas marcar este cultivo como terminado?');">
                                    Marcar como Terminado
                                </a>
                            <?php endif; ?>
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