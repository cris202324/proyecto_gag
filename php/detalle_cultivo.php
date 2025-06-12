<?php
session_start();
require_once 'conexion.php'; // $pdo

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.html"); 
    exit();
}
$id_usuario_actual = $_SESSION['id_usuario'];

$cultivo_detalle = null;
$tratamientos_cultivo = [];
// Eliminamos las variables para las secciones que no se mostrarán:
// $riegos_cultivo = [];
// $analisis_suelo_cultivo = [];
// $produccion_cultivo = [];
$mensaje_error_pagina = ''; 

if (!isset($_GET['id_cultivo']) || !is_numeric($_GET['id_cultivo'])) {
    $_SESSION['error_accion_cultivo'] = "ID de cultivo no válido o no proporcionado.";
    header("Location: miscultivos.php"); 
    exit();
}
$id_cultivo_seleccionado = (int)$_GET['id_cultivo'];

if (!isset($pdo)) {
    $mensaje_error_pagina = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // 1. Obtener datos principales del cultivo
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
                        WHERE c.id_cultivo = :id_cultivo AND c.id_usuario = :id_usuario_actual_param";
        
        $stmt_cultivo = $pdo->prepare($sql_cultivo);
        $stmt_cultivo->bindParam(':id_cultivo', $id_cultivo_seleccionado, PDO::PARAM_INT);
        $stmt_cultivo->bindParam(':id_usuario_actual_param', $id_usuario_actual, PDO::PARAM_STR);
        $stmt_cultivo->execute();
        $cultivo_detalle = $stmt_cultivo->fetch(PDO::FETCH_ASSOC);

        if (!$cultivo_detalle) {
            $_SESSION['error_accion_cultivo'] = "Cultivo no encontrado o no tienes permiso para verlo (ID: ".htmlspecialchars($id_cultivo_seleccionado).").";
            header("Location: miscultivos.php");
            exit();
        } else {
            // 2. Obtener tratamientos del cultivo
            $sql_tratamientos = "SELECT id_tratamiento, tipo_tratamiento, producto_usado, etapas, dosis, observaciones, 
                                        DATE_FORMAT(fecha_aplicacion_estimada, '%d/%m/%Y') as fecha_aplicacion_f,
                                        DATE_FORMAT(fecha_realizacion_real, '%d/%m/%Y') as fecha_realizacion_f,
                                        estado_tratamiento, observaciones_realizacion
                                 FROM tratamiento_cultivo 
                                 WHERE id_cultivo = :id_cultivo ORDER BY fecha_aplicacion_estimada ASC, id_tratamiento ASC";
            $stmt_tratamientos = $pdo->prepare($sql_tratamientos);
            $stmt_tratamientos->bindParam(':id_cultivo', $id_cultivo_seleccionado, PDO::PARAM_INT);
            $stmt_tratamientos->execute();
            $tratamientos_cultivo = $stmt_tratamientos->fetchAll(PDO::FETCH_ASSOC);

            // Las consultas para riego, suelo y producción han sido eliminadas
        }

    } catch (PDOException $e) {
        $mensaje_error_pagina = "Error al obtener los detalles del cultivo: " . $e->getMessage();
        $cultivo_detalle = null; 
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Cultivo: <?php echo $cultivo_detalle ? htmlspecialchars($cultivo_detalle['nombre_cultivo']) : 'No encontrado'; ?> - GAG</title>
    <style>
        /* Estilos generales y de header/menú (asumir que vienen de tu CSS global o copiarlos aquí) */
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px; color: #333;}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;} 
        .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;white-space:nowrap;font-size:.9em; transition: background-color .3s, color .3s;}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}

        .page-container-detalle {max-width:800px;margin:25px auto;padding:20px;} /* Ajustado max-width */
        .page-title-detalle {text-align:center;color:#4caf50;margin-bottom:25px;font-size:2em;font-weight:600;}
        .detail-section{background-color:#fff;padding:20px 25px;margin-bottom:25px;border-radius:8px;box-shadow:0 3px 10px rgba(0,0,0,0.08);border-left:5px solid #88c057;}
        .detail-section h3{color:#0056b3;font-size:1.3em;margin-top:0;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f0f0f0;}
        .detail-section p, .detail-section li {font-size:0.95em;line-height:1.7;color:#444;margin-bottom:10px;}
        .detail-section strong{color:#111;margin-right:5px;display:inline-block;min-width:150px;}
        .detail-section ul {list-style:none; padding-left:0;}
        .detail-section ul li {background-color:#f9f9f9;padding:12px 15px;border-radius:5px;margin-bottom:10px;border:1px solid #efefef;}
        .detail-section ul li strong {min-width:120px;}
        .tratamiento-item .estado-pendiente {color:#f39c12;font-style:italic;}
        .tratamiento-item .estado-completado {color:#27ae60;font-weight:bold;}
        .tratamiento-item .observaciones-realizacion {margin-top:5px;padding-left:15px;font-size:0.9em;color:#555;border-left:2px solid #ccc;}
        .no-datos {text-align:center;padding:20px;font-size:1em;color:#777;font-style:italic;background-color:#fdfdfd; border:1px dashed #e0e0e0; border-radius:5px;}
        .error-message {color:#d8000c;text-align:center;padding:15px;background-color:#ffdddd;border:1px solid #ffcccc;border-radius:5px;margin-bottom:20px;}
        .back-button-container {text-align:center; margin-top:30px;}
        .back-button {padding:10px 25px; background-color:#6c757d;color:white; text-decoration:none; border-radius:5px; font-size:1em; font-weight:bold; transition:background-color 0.3s ease;}
        .back-button:hover { background-color:#5a6268; }

        /* Media Queries */
        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}}
        @media (max-width:768px){.logo img{height:60px} .page-title-detalle{font-size:1.6em} .detail-section h3{font-size:1.2em} .detail-section strong {min-width:120px;}}
        @media (max-width:480px){.logo img{height:50px} .menu-toggle{font-size:1.6rem} .page-title-detalle{font-size:1.4em} .detail-section h3{font-size:1.1em} .detail-section strong {min-width:100px;} .detail-section p, .detail-section li { font-size:0.9em;}}
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
            <a href="calendario_general.php">Calendario</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container-detalle">
        <?php if (!empty($mensaje_error_pagina)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error_pagina); ?></p>
            <div class="back-button-container"><a href="miscultivos.php" class="back-button">Volver a Mis Cultivos</a></div>
        <?php elseif ($cultivo_detalle): ?>
            <h2 class="page-title-detalle">Detalle del Cultivo: <?php echo htmlspecialchars($cultivo_detalle['nombre_cultivo']); ?></h2>
            
            <section class="detail-section">
                <h3>Información General</h3>
                <p><strong>Propietario:</strong> <?php echo htmlspecialchars($cultivo_detalle['nombre_creador_cultivo']); ?> (ID: <?php echo htmlspecialchars($cultivo_detalle['id_creador_cultivo']); ?>)</p>
                <p><strong>Fecha de Inicio:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo_detalle['fecha_inicio']))); ?></p>
                <p><strong>Fecha Fin (Estimada/Real):</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo_detalle['fecha_fin_registrada']))); ?></p>
                <p><strong>Área:</strong> <?php echo htmlspecialchars($cultivo_detalle['area_hectarea']); ?> ha</p>
                <p><strong>Municipio:</strong> <?php echo htmlspecialchars($cultivo_detalle['nombre_municipio']); ?></p>
                <p><strong>Estado Actual:</strong> <?php echo htmlspecialchars($cultivo_detalle['estado_actual_cultivo'] ?: 'No definido'); ?></p>
            </section>

            <section class="detail-section">
                <h3>Plan de Tratamientos</h3>
                <?php if (!empty($tratamientos_cultivo)): ?>
                    <ul>
                        <?php foreach ($tratamientos_cultivo as $trat): ?>
                            <li class="tratamiento-item">
                                <strong>Tipo:</strong> <?php echo htmlspecialchars($trat['tipo_tratamiento']); ?><br>
                                <strong>Producto:</strong> <?php echo htmlspecialchars($trat['producto_usado']); ?><br>
                                <strong>Etapas:</strong> <?php echo htmlspecialchars($trat['etapas']); ?><br>
                                <strong>Dosis:</strong> <?php echo htmlspecialchars($trat['dosis']); ?><br>
                                <strong>Fecha Estimada:</strong> <?php echo htmlspecialchars($trat['fecha_aplicacion_f'] ?: 'No definida'); ?><br>
                                <strong>Estado:</strong> 
                                <span class="estado-<?php echo strtolower(htmlspecialchars($trat['estado_tratamiento'])); ?>">
                                    <?php echo htmlspecialchars($trat['estado_tratamiento']); ?>
                                </span><br>
                                <?php if(!empty($trat['fecha_realizacion_f'])): ?>
                                    <strong>Fecha Realización:</strong> <?php echo htmlspecialchars($trat['fecha_realizacion_f']); ?><br>
                                <?php endif; ?>
                                <?php if(!empty($trat['observaciones'])): ?>
                                    <strong>Observaciones (Plan):</strong> <?php echo htmlspecialchars($trat['observaciones']); ?><br>
                                <?php endif; ?>
                                <?php if(!empty($trat['observaciones_realizacion'])): ?>
                                    <div class="observaciones-realizacion"><strong>Observaciones (Realización):</strong> <?php echo nl2br(htmlspecialchars($trat['observaciones_realizacion'])); ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-datos">No hay tratamientos registrados/programados para este cultivo.</p>
                <?php endif; ?>
            </section>

            <!-- SECCIONES DE RIEGO, SUELO Y PRODUCCIÓN ELIMINADAS -->

            <div class="back-button-container">
                <a href="miscultivos.php" class="back-button">Volver a Mis Cultivos</a>
            </div>

        <?php else: ?>
            <p class="error-message">No se pudo cargar la información del cultivo.</p>
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