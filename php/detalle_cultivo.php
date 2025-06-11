<?php
session_start();
require_once 'conexion.php'; // $pdo

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.html"); 
    exit();
}
$id_usuario_actual = $_SESSION['id_usuario'];

$cultivo_detalle = null;
$tratamientos_cultivo = [];
$riegos_cultivo = [];
$analisis_suelo_cultivo = [];
$produccion_cultivo = [];
$mensaje_error = '';

if (!isset($_GET['id_cultivo']) || !is_numeric($_GET['id_cultivo'])) {
    // Redirigir si no hay ID de cultivo o no es numérico
    header("Location: miscultivos.php"); // O a una página de error
    exit();
}
$id_cultivo_seleccionado = (int)$_GET['id_cultivo'];

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // 1. Obtener datos principales del cultivo y asegurar que pertenece al usuario
        $sql_cultivo = "SELECT
                            c.id_cultivo, c.fecha_inicio, c.fecha_fin AS fecha_fin_registrada,
                            c.area_hectarea, tc.nombre_cultivo, tc.tiempo_estimado_frutos,
                            m.nombre AS nombre_municipio,
                            ecd.nombre_estado AS estado_actual_cultivo, 
                            c.id_estado_cultivo,
                            u.nombre AS nombre_creador_cultivo, u.id_usuario AS id_creador_cultivo
                        FROM cultivos c
                        JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                        JOIN municipio m ON c.id_municipio = m.id_municipio
                        JOIN usuarios u ON c.id_usuario = u.id_usuario
                        LEFT JOIN estado_cultivo_definiciones ecd ON c.id_estado_cultivo = ecd.id_estado_cultivo
                        WHERE c.id_cultivo = :id_cultivo AND c.id_usuario = :id_usuario_actual";
        
        $stmt_cultivo = $pdo->prepare($sql_cultivo);
        $stmt_cultivo->bindParam(':id_cultivo', $id_cultivo_seleccionado, PDO::PARAM_INT);
        $stmt_cultivo->bindParam(':id_usuario_actual', $id_usuario_actual, PDO::PARAM_STR);
        $stmt_cultivo->execute();
        $cultivo_detalle = $stmt_cultivo->fetch(PDO::FETCH_ASSOC);

        if (!$cultivo_detalle) {
            $mensaje_error = "Cultivo no encontrado o no tienes permiso para verlo.";
        } else {
            // 2. Obtener tratamientos del cultivo
            $sql_tratamientos = "SELECT tipo_tratamiento, producto_usado, etapas, dosis, observaciones, DATE_FORMAT(fecha_aplicacion_estimada, '%d/%m/%Y') as fecha_aplicacion_f 
                                 FROM tratamiento_cultivo 
                                 WHERE id_cultivo = :id_cultivo ORDER BY fecha_aplicacion_estimada ASC, id_tratamiento ASC";
            $stmt_tratamientos = $pdo->prepare($sql_tratamientos);
            $stmt_tratamientos->bindParam(':id_cultivo', $id_cultivo_seleccionado, PDO::PARAM_INT);
            $stmt_tratamientos->execute();
            $tratamientos_cultivo = $stmt_tratamientos->fetchAll(PDO::FETCH_ASSOC);

            // 3. Obtener registros de riego
            $sql_riegos = "SELECT frecuencia_riego, volumen_agua, metodo_riego, DATE_FORMAT(fecha_ultimo_riego, '%d/%m/%Y') as fecha_ultimo_riego_f 
                           FROM riego 
                           WHERE id_cultivo = :id_cultivo ORDER BY fecha_ultimo_riego DESC";
            $stmt_riegos = $pdo->prepare($sql_riegos);
            $stmt_riegos->bindParam(':id_cultivo', $id_cultivo_seleccionado, PDO::PARAM_INT);
            $stmt_riegos->execute();
            $riegos_cultivo = $stmt_riegos->fetchAll(PDO::FETCH_ASSOC);
            
            // 4. Obtener análisis de suelo (si existe la tabla y datos)
            // Asumiendo que tienes una tabla 'suelo' similar a tu DUMP inicial
            $sql_suelo = "SELECT tipo_suelo, ph, nivel_nutrientes, temperatura_actual, DATE_FORMAT(fecha_muestreo, '%d/%m/%Y') as fecha_muestreo_f 
                          FROM suelo 
                          WHERE id_cultivo = :id_cultivo ORDER BY fecha_muestreo DESC";
            $stmt_suelo = $pdo->prepare($sql_suelo);
            $stmt_suelo->bindParam(':id_cultivo', $id_cultivo_seleccionado, PDO::PARAM_INT);
            $stmt_suelo->execute();
            $analisis_suelo_cultivo = $stmt_suelo->fetchAll(PDO::FETCH_ASSOC);
            
            // 5. Obtener producción del cultivo (si existe la tabla y datos)
            $sql_produccion = "SELECT cantidad_producida, DATE_FORMAT(fecha_cosecha, '%d/%m/%Y') as fecha_cosecha_f, calidad_cosecha 
                               FROM produccion_cultivo 
                               WHERE id_cultivo = :id_cultivo ORDER BY fecha_cosecha DESC";
            $stmt_produccion = $pdo->prepare($sql_produccion);
            $stmt_produccion->bindParam(':id_cultivo', $id_cultivo_seleccionado, PDO::PARAM_INT);
            $stmt_produccion->execute();
            $produccion_cultivo = $stmt_produccion->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los detalles del cultivo: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Cultivo - <?php echo $cultivo_detalle ? htmlspecialchars($cultivo_detalle['nombre_cultivo']) : 'Error'; ?> - GAG</title>
    <style>
        /* Estilos generales y de header/menú (asumir que vienen de tu CSS global o copiarlos aquí) */
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px; color: #333;}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;} .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;white-space:nowrap;font-size:.9em}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}

        .page-container{max-width:900px;margin:20px auto;padding:20px;}
        .page-title{text-align:center;color:#4caf50;margin-bottom:20px;font-size:2em;}
        .detail-section{background-color:#fff;padding:20px;margin-bottom:25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
        .detail-section h3{color:#0056b3;font-size:1.4em;margin-top:0;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #eee;}
        .detail-section p, .detail-section li {font-size:0.95em;line-height:1.6;color:#444;margin-bottom:8px;}
        .detail-section strong{color:#111;}
        .detail-section ul {list-style:none; padding-left:0;}
        .detail-section ul li { background-color:#f9f9f9; padding:10px; border-radius:5px; margin-bottom:8px; border-left: 3px solid #88c057;}
        .no-datos {text-align:center;padding:15px;font-size:1em;color:#777;font-style:italic;}
        .error-message {color:#d8000c;text-align:center;padding:15px;background-color:#ffdddd;border:1px solid #ffcccc;border-radius:5px;margin-bottom:20px;}
        .back-button-container { text-align:center; margin-top:30px; }
        .back-button { padding:10px 20px; background-color:#777; color:white; text-decoration:none; border-radius:5px; font-size:1em; transition:background-color 0.3s ease; }
        .back-button:hover { background-color:#666; }

        /* Media Queries para responsividad */
        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}}
        @media (max-width:768px){.logo img{height:60px} .page-title{font-size:1.6em} .detail-section h3{font-size:1.25em}}
        @media (max-width:480px){.logo img{height:50px} .menu-toggle{font-size:1.6rem} .page-title{font-size:1.4em} .detail-section h3{font-size:1.15em}}
    </style>
</head>
<body>
    <div class="header">
        <div class="logo"><img src="../img/logo.png" alt="Logo GAG" /></div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="index.php">Inicio</a>
            <a href="miscultivos.php" class="active">Mis Cultivos</a> <!-- Marcado como activo si vienes de ahí -->
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario_general.php">Calendario</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
            <div class="back-button-container"><a href="miscultivos.php" class="back-button">Volver a Mis Cultivos</a></div>
        <?php elseif ($cultivo_detalle): ?>
            <h2 class="page-title">Detalle del Cultivo: <?php echo htmlspecialchars($cultivo_detalle['nombre_cultivo']); ?></h2>
            
            <section class="detail-section">
                <h3>Información General del Cultivo</h3>
                <p><strong>Nombre del Cultivo:</strong> <?php echo htmlspecialchars($cultivo_detalle['nombre_cultivo']); ?></p>
                <p><strong>Propietario:</strong> <?php echo htmlspecialchars($cultivo_detalle['nombre_creador_cultivo']); ?> (ID: <?php echo htmlspecialchars($cultivo_detalle['id_creador_cultivo']); ?>)</p>
                <p><strong>Fecha de Inicio:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo_detalle['fecha_inicio']))); ?></p>
                <p><strong>Fecha Fin Estimada/Registrada:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo_detalle['fecha_fin_registrada']))); ?></p>
                <p><strong>Área:</strong> <?php echo htmlspecialchars($cultivo_detalle['area_hectarea']); ?> ha</p>
                <p><strong>Municipio:</strong> <?php echo htmlspecialchars($cultivo_detalle['nombre_municipio']); ?></p>
                <p><strong>Estado Actual:</strong> <?php echo htmlspecialchars($cultivo_detalle['estado_actual_cultivo'] ?: 'No definido'); ?></p>
            </section>

            <section class="detail-section">
                <h3>Tratamientos Registrados</h3>
                <?php if (!empty($tratamientos_cultivo)): ?>
                    <ul>
                        <?php foreach ($tratamientos_cultivo as $trat): ?>
                            <li>
                                <strong>Tipo:</strong> <?php echo htmlspecialchars($trat['tipo_tratamiento']); ?><br>
                                <strong>Producto:</strong> <?php echo htmlspecialchars($trat['producto_usado']); ?><br>
                                <strong>Etapas:</strong> <?php echo htmlspecialchars($trat['etapas']); ?><br>
                                <strong>Dosis:</strong> <?php echo htmlspecialchars($trat['dosis']); ?><br>
                                <strong>Fecha Estimada:</strong> <?php echo htmlspecialchars($trat['fecha_aplicacion_f'] ?: 'No definida'); ?><br>
                                <?php if(!empty($trat['observaciones'])): ?>
                                    <strong>Observaciones:</strong> <?php echo htmlspecialchars($trat['observaciones']); ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-datos">No hay tratamientos registrados para este cultivo.</p>
                <?php endif; ?>
            </section>

            <section class="detail-section">
                <h3>Registros de Riego</h3>
                <?php if (!empty($riegos_cultivo)): ?>
                    <ul>
                        <?php foreach ($riegos_cultivo as $riego): ?>
                            <li>
                                <strong>Frecuencia:</strong> <?php echo htmlspecialchars($riego['frecuencia_riego']); ?><br>
                                <strong>Volumen:</strong> <?php echo htmlspecialchars($riego['volumen_agua']); ?> (unidad no especificada)<br>
                                <strong>Método:</strong> <?php echo htmlspecialchars($riego['metodo_riego']); ?><br>
                                <strong>Último Riego:</strong> <?php echo htmlspecialchars($riego['fecha_ultimo_riego_f']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-datos">No hay registros de riego para este cultivo.</p>
                <?php endif; ?>
            </section>
            
            <section class="detail-section">
                <h3>Análisis de Suelo</h3>
                <?php if (!empty($analisis_suelo_cultivo)): ?>
                    <ul>
                        <?php foreach ($analisis_suelo_cultivo as $suelo): ?>
                            <li>
                                <strong>Tipo de Suelo:</strong> <?php echo htmlspecialchars($suelo['tipo_suelo']); ?><br>
                                <strong>pH:</strong> <?php echo htmlspecialchars($suelo['ph']); ?><br>
                                <strong>Nivel de Nutrientes:</strong> <?php echo htmlspecialchars($suelo['nivel_nutrientes']); ?><br>
                                <strong>Temperatura Suelo:</strong> <?php echo $suelo['temperatura_actual'] ? htmlspecialchars($suelo['temperatura_actual']) . '°C' : 'N/A'; ?><br>
                                <strong>Fecha Muestreo:</strong> <?php echo htmlspecialchars($suelo['fecha_muestreo_f']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-datos">No hay análisis de suelo registrados para este cultivo.</p>
                <?php endif; ?>
            </section>

            <section class="detail-section">
                <h3>Producción Registrada</h3>
                <?php if (!empty($produccion_cultivo)): ?>
                    <ul>
                        <?php foreach ($produccion_cultivo as $prod): ?>
                            <li>
                                <strong>Cantidad Producida:</strong> <?php echo htmlspecialchars($prod['cantidad_producida']); ?> (unidad no especificada)<br>
                                <strong>Fecha Cosecha:</strong> <?php echo htmlspecialchars($prod['fecha_cosecha_f']); ?><br>
                                <strong>Calidad:</strong> <?php echo htmlspecialchars($prod['calidad_cosecha']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-datos">No hay registros de producción para este cultivo.</p>
                <?php endif; ?>
            </section>

            <div class="back-button-container">
                <a href="miscultivos.php" class="back-button">Volver a Mis Cultivos</a>
            </div>

        <?php else: // Si $cultivo_detalle está vacío y no hay $mensaje_error general (improbable si el ID es requerido) ?>
            <p class="error-message">No se encontró el cultivo especificado o no tienes permiso para verlo.</p>
            <div class="back-button-container"><a href="miscultivos.php" class="back-button">Volver a Mis Cultivos</a></div>
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