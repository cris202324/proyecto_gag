<?php
// Inicia la sesión. Es fundamental para acceder a $_SESSION y verificar la autenticación del usuario.
session_start();
// Incluye el script de conexión a la base de datos, que se espera que defina la variable $pdo.
require_once '../../conexion.php'; 

// --- CABECERAS HTTP PARA EVITAR CACHÉ DEL NAVEGADOR ---
// Estas cabeceras le indican al navegador que no almacene en caché esta página,
// asegurando que siempre se muestre la información más reciente.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Verifica si el id_usuario está en la sesión. Si no, significa que el usuario no ha iniciado sesión,
// por lo que se le redirige a la página de login.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../../pages/auth/login.html");
    exit();
}
$id_usuario_actual = $_SESSION['id_usuario'];

// --- INICIALIZACIÓN DE VARIABLES ---
// Inicializa las variables que almacenarán los datos del cultivo, sus tratamientos
// y cualquier mensaje de error que pueda ocurrir durante la ejecución.
$cultivo_detalle = null;
$tratamientos_cultivo = [];
$mensaje_error_pagina = '';

// --- VALIDACIÓN DE ENTRADA (ID DEL CULTIVO) ---
// Comprueba que se haya proporcionado un 'id_cultivo' en la URL y que sea numérico.
// Es una medida de seguridad crucial para prevenir errores y posibles ataques.
if (!isset($_GET['id_cultivo']) || !is_numeric($_GET['id_cultivo'])) {
    // Si la validación falla, se guarda un mensaje de error en la sesión y se redirige
    // al usuario a la página principal de sus cultivos.
    $_SESSION['error_accion_cultivo'] = "ID de cultivo no válido o no proporcionado.";
    header("Location: miscultivos.php");
    exit();
}
$id_cultivo_seleccionado = (int)$_GET['id_cultivo'];

// --- LÓGICA DE BASE DE DATOS ---
// Verificación de la conexión a la base de datos.
if (!isset($pdo)) {
    $mensaje_error_pagina = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    // Se utiliza un bloque try-catch para manejar posibles errores de base de datos (PDOException)
    // de forma controlada, evitando que el script se detenga y mostrando un mensaje amigable.
    try {
        // --- CONSULTA 1: OBTENER DATOS PRINCIPALES DEL CULTIVO ---
        // Se unen (JOIN) varias tablas para obtener nombres legibles en lugar de solo IDs.
        // La cláusula WHERE es crucial: asegura que solo se pueda consultar un cultivo
        // que pertenezca al usuario que ha iniciado sesión (c.id_usuario = :id_usuario_actual_param),
        // previniendo que un usuario vea los datos de otro.
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
        // Se utiliza fetch() porque se espera un único resultado (un solo cultivo).
        $cultivo_detalle = $stmt_cultivo->fetch(PDO::FETCH_ASSOC);

        // --- VERIFICACIÓN DE PERMISOS Y EXISTENCIA ---
        // Si la consulta no devuelve ninguna fila, el cultivo no existe o no pertenece al usuario.
        if (!$cultivo_detalle) {
            $_SESSION['error_accion_cultivo'] = "Cultivo no encontrado o no tienes permiso para verlo (ID: ".htmlspecialchars($id_cultivo_seleccionado).").";
            header("Location: miscultivos.php");
            exit();
        } else {
            // --- CONSULTA 2: OBTENER TRATAMIENTOS DEL CULTIVO ---
            // Si el cultivo se encontró, se obtienen todos sus tratamientos asociados.
            // Se formatea la fecha directamente en la consulta para facilitar su uso en el HTML.
            // Se ordena por fecha para mostrarlos en orden cronológico.
            $sql_tratamientos = "SELECT id_tratamiento, tipo_tratamiento, producto_usado, etapas, dosis, observaciones,
                                        DATE_FORMAT(fecha_aplicacion_estimada, '%d/%m/%Y') as fecha_aplicacion_f,
                                        DATE_FORMAT(fecha_realizacion_real, '%d/%m/%Y') as fecha_realizacion_f,
                                        estado_tratamiento, observaciones_realizacion
                                 FROM tratamiento_cultivo
                                 WHERE id_cultivo = :id_cultivo ORDER BY fecha_aplicacion_estimada ASC, id_tratamiento ASC";
            $stmt_tratamientos = $pdo->prepare($sql_tratamientos);
            $stmt_tratamientos->bindParam(':id_cultivo', $id_cultivo_seleccionado, PDO::PARAM_INT);
            $stmt_tratamientos->execute();
            // Se utiliza fetchAll() porque un cultivo puede tener múltiples tratamientos.
            $tratamientos_cultivo = $stmt_tratamientos->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        // Si ocurre una excepción PDO, se captura el error, se crea un mensaje para el usuario
        // y se asegura que $cultivo_detalle sea nulo para que la página muestre el error.
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
        .page-title-detalle {text-align:center;color:#4caf50;margin-bottom:10px;font-size:2em;font-weight:600;} /* Reducido margin-bottom */
        
        .action-buttons-container { /* Nuevo contenedor para botones */
            display: flex;
            justify-content: center; /* Centra los botones si hay espacio */
            align-items: center;
            gap: 15px; /* Espacio entre botones */
            margin-bottom: 25px; /* Espacio debajo de los botones */
        }
        .report-button, .back-button { /* Estilos comunes para botones */
            padding:10px 20px; 
            text-decoration:none; 
            border-radius:5px; 
            font-size:0.9em; 
            font-weight:bold; 
            transition:background-color 0.3s ease;
            display: inline-block; /* Para que tomen el padding correctamente */
            text-align: center;
            border: none;
            cursor: pointer;
        }
        .report-button {
            background-color:#28a745; /* Verde */
            color:white; 
        }
        .report-button:hover {
            background-color:#218838;
        }

        .detail-section{background-color:#fff;border-left:10px solid #88c057;padding:20px 25px;margin-bottom:25px;border-radius:8px;box-shadow:0 3px 10px rgba(0,0,0,0.08);border-left:5px solid #88c057;}
        .detail-section h3{color:#0056b3;font-size:1.3em;margin-top:0;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f0f0f0;}
        .detail-section p, .detail-section li {font-size:0.95em;line-height:1.7;color:#444;margin-bottom:10px;}
        .detail-section strong{color:#111;margin-right:5px;display:inline-block;min-width:150px;}
        .detail-section ul {list-style:none; padding-left:0;}
        .detail-section ul li {background-color:#f9f9f9;padding:12px 15px;border-radius:5px;margin-bottom:10px;border:1px solid #efefef;}
        .detail-section ul li strong {min-width:120px;}
        .tratamiento-item .estado-pendiente {color:#f39c12;font-style:italic;}
        .tratamiento-item .estado-completado {color:#27ae60;font-weight:bold;}
        .tratamiento-item .estado-cancelado {color:#dc3545;text-decoration: line-through;} /* Estilo para cancelado */
        .tratamiento-item .observaciones-realizacion {margin-top:5px;padding-left:15px;font-size:0.9em;color:#555;border-left:2px solid #ccc;}
        .no-datos {text-align:center;padding:20px;font-size:1em;color:#777;font-style:italic;background-color:#fdfdfd; border:1px dashed #e0e0e0; border-radius:5px;}
        .error-message {color:#d8000c;text-align:center;padding:15px;background-color:#ffdddd;border:1px solid #ffcccc;border-radius:5px;margin-bottom:20px;}
        
        .back-button-container-bottom {text-align:center; margin-top:30px;} /* Contenedor para el botón de volver inferior */
        .back-button { /* Estilos para el botón de volver */
            padding:10px 25px; 
            background-color:#6c757d;
            color:white; 
            text-decoration:none; 
            border-radius:5px; 
            font-size:1em; 
            font-weight:bold; 
            transition:background-color 0.3s ease;
        }
        .back-button:hover { 
            background-color:#5a6268; 
        }


        /* Media Queries */
        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}}
        @media (max-width:768px){.logo img{height:60px} .page-title-detalle{font-size:1.6em} .detail-section h3{font-size:1.2em} .detail-section strong {min-width:120px;} .report-button, .back-button {font-size: 0.85em; padding: 8px 15px;}}
        @media (max-width:480px){.logo img{height:50px} .menu-toggle{font-size:1.6rem} .page-title-detalle{font-size:1.4em} .detail-section h3{font-size:1.1em} .detail-section strong {min-width:100px;} .detail-section p, .detail-section li { font-size:0.9em;} .action-buttons-container {flex-direction: column; gap: 10px;} .report-button, .back-button {width: 80%;}}
    </style>
</head>
<body>
    <div class="header">
        <div class="logo"><img src="../../../img/logo.png" alt="Logo GAG" /></div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="../../index.php">Inicio</a>
            <a href="miscultivos.php" class="active">Mis Cultivos</a>
            <a href="../animales/mis_animales.php">Mis Animales</a>
            <a href="../calendario.php">Calendario</a>
            <a href="../configuracion.php">Configuración</a>
            <a href="../ayuda.php">Ayuda</a>
            <a href="../../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container-detalle">
        <?php 
        // --- RENDERIZADO CONDICIONAL DEL CONTENIDO ---
        // Si hubo un error fatal en la carga (ej. problema de conexión), se muestra un mensaje de error.
        if (!empty($mensaje_error_pagina)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error_pagina); ?></p>
            <div class="back-button-container-bottom"><a href="miscultivos.php" class="back-button">Volver a Mis Cultivos</a></div>
        <?php 
        // Si la variable $cultivo_detalle contiene datos (el cultivo se encontró), se muestra la información.
        elseif ($cultivo_detalle): ?>
            <h2 class="page-title-detalle">Detalle del Cultivo: <?php echo htmlspecialchars($cultivo_detalle['nombre_cultivo']); ?></h2>

            <!-- Contenedor para los botones de acción principales -->
            <div class="action-buttons-container">
                <a href="../../generar_reporte_cultivo_excel.php?id_cultivo=<?php echo $id_cultivo_seleccionado; ?>" class="report-button" target="_blank">Generar Reporte Excel</a>
                <!-- Aquí se podrían añadir más botones, como "Editar Cultivo" o "Añadir Tratamiento" -->
            </div>

            <!-- Sección de Información General del Cultivo -->
            <section class="detail-section">
                <h3>Información General</h3>
                <p><strong>Propietario:</strong> <?php echo htmlspecialchars($cultivo_detalle['nombre_creador_cultivo']); ?> (ID: <?php echo htmlspecialchars($cultivo_detalle['id_creador_cultivo']); ?>)</p>
                <p><strong>Fecha de Inicio:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo_detalle['fecha_inicio']))); ?></p>
                <p><strong>Fecha Fin (Estimada/Real):</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo_detalle['fecha_fin_registrada']))); ?></p>
                <p><strong>Área:</strong> <?php echo htmlspecialchars($cultivo_detalle['area_hectarea']); ?> ha</p>
                <p><strong>Municipio:</strong> <?php echo htmlspecialchars($cultivo_detalle['nombre_municipio']); ?></p>
                <p><strong>Estado Actual:</strong> <?php echo htmlspecialchars($cultivo_detalle['estado_actual_cultivo'] ?: 'No definido'); ?></p>
            </section>

            <!-- Sección de Tratamientos del Cultivo -->
            <section class="detail-section">
                <h3>Plan de Tratamientos</h3>
                <?php 
                // Comprueba si el array de tratamientos tiene elementos antes de intentar mostrar la lista.
                if (!empty($tratamientos_cultivo)): ?>
                    <ul>
                        <?php 
                        // Bucle para iterar sobre cada tratamiento y mostrar sus detalles.
                        foreach ($tratamientos_cultivo as $trat): ?>
                            <li class="tratamiento-item">
                                <strong>Tipo:</strong> <?php echo htmlspecialchars($trat['tipo_tratamiento']); ?><br>
                                <strong>Producto:</strong> <?php echo htmlspecialchars($trat['producto_usado']); ?><br>
                                <strong>Etapas:</strong> <?php echo htmlspecialchars($trat['etapas']); ?><br>
                                <strong>Dosis:</strong> <?php echo htmlspecialchars($trat['dosis']); ?><br>
                                <strong>Fecha Estimada:</strong> <?php echo htmlspecialchars($trat['fecha_aplicacion_f'] ?: 'No definida'); ?><br>
                                <strong>Estado:</strong>
                                <!-- Clase CSS dinámica para dar estilo visual según el estado del tratamiento. -->
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
                                    <!-- nl2br convierte saltos de línea en <br> para respetar el formato del texto. -->
                                    <div class="observaciones-realizacion"><strong>Observaciones (Realización):</strong> <?php echo nl2br(htmlspecialchars($trat['observaciones_realizacion'])); ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <!-- Si no se encontraron tratamientos, se muestra un mensaje informativo. -->
                    <p class="no-datos">No hay tratamientos registrados/programados para este cultivo.</p>
                <?php endif; ?>
            </section>

            <div class="back-button-container-bottom">
                <a href="miscultivos.php" class="back-button">Volver a Mis Cultivos</a>
            </div>

        <?php 
        // Caso final: si no hubo error, pero $cultivo_detalle está vacío (no debería ocurrir si la lógica es correcta).
        else: ?>
            <p class="error-message">No se pudo cargar la información del cultivo.</p>
            <div class="back-button-container-bottom"><a href="miscultivos.php" class="back-button">Volver a Mis Cultivos</a></div>
        <?php endif; ?>
    </div>

    <script>
        // Script para la funcionalidad del menú de navegación en dispositivos móviles (hamburguesa).
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