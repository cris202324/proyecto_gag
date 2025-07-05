<?php
// Inicia la sesión de PHP. Es necesario para usar variables de sesión como $_SESSION['id_usuario']
// y para pasar mensajes (feedback) entre páginas (ej. después de marcar un cultivo como terminado).
session_start();

// --- CABECERAS HTTP PARA EVITAR CACHÉ DEL NAVEGADOR ---
// Estas líneas le indican al navegador que no guarde una copia en caché de esta página.
// Esto asegura que el usuario siempre vea la información más actualizada.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Comprueba si el usuario ha iniciado sesión. Si no, lo redirige a la página de login.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../../pages/auth/login.html");
    exit(); // Detiene la ejecución del script.
}

// --- INCLUSIÓN DE ARCHIVOS Y DECLARACIÓN DE VARIABLES ---
include '../../conexion.php'; // Incluye el archivo de conexión a la base de datos, que define la variable $pdo.
$id_usuario_actual = $_SESSION['id_usuario']; // ID del usuario actual.

$cultivos_usuario = []; // Array para almacenar los cultivos del usuario que se mostrarán.
$mensaje_error = ''; // Para mostrar errores de base de datos o lógica.
$mensaje_exito = ''; // Para mostrar mensajes de éxito de acciones previas.

// --- DEFINICIÓN DE CONSTANTES DE ESTADO ---
// Se definen los IDs de los estados para hacer el código más legible y fácil de mantener.
$id_estado_en_progreso = 1;
$id_estado_terminado = 2;

// --- LÓGICA DE VISUALIZACIÓN (FILTRADO POR ESTADO) ---
// Determina si se deben mostrar los cultivos "En Progreso" o "Terminados" basándose en el parámetro GET 'estado'.
// Si no se especifica, por defecto muestra los cultivos "En Progreso".
$estado_a_mostrar_get = isset($_GET['estado']) && $_GET['estado'] === 'terminado' ? 'terminado' : 'en_progreso';
$estado_a_mostrar_db = ($estado_a_mostrar_get === 'terminado') ? $id_estado_terminado : $id_estado_en_progreso;

// Se definen las variables para los textos y enlaces de la página de forma dinámica,
// dependiendo del estado que se está mostrando.
$titulo_pagina = ($estado_a_mostrar_db == $id_estado_en_progreso) ? "Mis Cultivos en Progreso" : "Mis Cultivos Terminados";
$link_ver_otros_cultivos_href = ($estado_a_mostrar_db == $id_estado_en_progreso) ? "miscultivos.php?estado=terminado" : "miscultivos.php";
$link_ver_otros_cultivos_texto = ($estado_a_mostrar_db == $id_estado_en_progreso) ? "Ver Cultivos Terminados" : "Ver Cultivos en Progreso";

// Variables para el botón de reporte, también dinámicas según la vista actual.
$texto_boton_reporte = ($estado_a_mostrar_db == $id_estado_en_progreso) ? "Reporte Cultivos en Progreso" : "Reporte Cultivos Terminados";
$param_estado_reporte = ($estado_a_mostrar_db == $id_estado_en_progreso) ? "en_progreso" : "terminado";

// --- MANEJO DE MENSAJES DE SESIÓN ---
// Recupera mensajes de feedback de acciones previas y los elimina de la sesión.
if (isset($_SESSION['mensaje_accion_cultivo'])) {
    $mensaje_exito = $_SESSION['mensaje_accion_cultivo'];
    unset($_SESSION['mensaje_accion_cultivo']);
}
if (isset($_SESSION['error_accion_cultivo'])) {
    $mensaje_error = $_SESSION['error_accion_cultivo'];
    unset($_SESSION['error_accion_cultivo']);
}

// --- LÓGICA PRINCIPAL DE BASE DE DATOS ---
if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // --- ACTUALIZACIÓN AUTOMÁTICA DE ESTADO ---
        // Esta consulta busca todos los cultivos "En Progreso" del usuario cuya fecha de fin ya ha pasado
        // y los actualiza automáticamente al estado "Terminado". Es una tarea de mantenimiento útil.
        $hoy_para_sql = date('Y-m-d'); // Obtiene la fecha actual en formato YYYY-MM-DD.
        $sql_auto_terminar = "UPDATE cultivos
                              SET id_estado_cultivo = :id_estado_terminado
                              WHERE id_usuario = :id_usuario_actual_update
                                AND id_estado_cultivo = :id_estado_en_progreso_update
                                AND fecha_fin < :hoy_actual"; // Condición para cultivos cuya fecha_fin ya pasó.
        $stmt_auto_terminar = $pdo->prepare($sql_auto_terminar);
        $stmt_auto_terminar->bindParam(':id_estado_terminado', $id_estado_terminado, PDO::PARAM_INT);
        $stmt_auto_terminar->bindParam(':id_usuario_actual_update', $id_usuario_actual, PDO::PARAM_STR);
        $stmt_auto_terminar->bindParam(':id_estado_en_progreso_update', $id_estado_en_progreso, PDO::PARAM_INT);
        $stmt_auto_terminar->bindParam(':hoy_actual', $hoy_para_sql, PDO::PARAM_STR);
        $stmt_auto_terminar->execute();

        // --- CONSULTA PRINCIPAL PARA MOSTRAR CULTIVOS ---
        // Se seleccionan los datos de los cultivos del usuario, filtrados por el estado ("En Progreso" o "Terminados").
        // Se unen (JOIN) varias tablas para obtener información legible como el nombre del cultivo, municipio y estado.
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
                ORDER BY c.fecha_inicio DESC"; // Ordena los cultivos por fecha de inicio descendente.
        $stmt_cultivos_main = $pdo->prepare($sql_cultivos_main);
        $stmt_cultivos_main->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
        $stmt_cultivos_main->bindParam(':id_estado_a_mostrar_db', $estado_a_mostrar_db, PDO::PARAM_INT);
        $stmt_cultivos_main->execute();
        $cultivos_usuario = $stmt_cultivos_main->fetchAll(PDO::FETCH_ASSOC);

        // --- OBTENER TAREAS PRÓXIMAS PARA CULTIVOS EN PROGRESO ---
        // Si se están mostrando los cultivos "En Progreso", se realiza una consulta adicional por cada cultivo
        // para encontrar sus tareas pendientes más próximas y mostrarlas en la tarjeta.
        if ($estado_a_mostrar_db == $id_estado_en_progreso) {
            // Itera sobre cada cultivo para buscar sus tareas asociadas.
            for ($i = 0; $i < count($cultivos_usuario); $i++) {
                $id_cultivo_actual_loop = $cultivos_usuario[$i]['id_cultivo'];
                $hoy_str = date('Y-m-d'); // Fecha actual para filtrar tareas futuras.
                // Consulta para obtener tareas pendientes desde hoy en adelante para el cultivo actual.
                $sql_tareas = "SELECT tipo_tratamiento, producto_usado, etapas, fecha_aplicacion_estimada
                               FROM tratamiento_cultivo
                               WHERE id_cultivo = :id_cultivo_loop_param
                                 AND fecha_aplicacion_estimada >= :hoy
                                 AND estado_tratamiento = 'Pendiente'
                               ORDER BY fecha_aplicacion_estimada ASC"; // Ordena por fecha para obtener la más próxima.
                $stmt_tareas = $pdo->prepare($sql_tareas);
                $stmt_tareas->bindParam(':id_cultivo_loop_param', $id_cultivo_actual_loop, PDO::PARAM_INT);
                $stmt_tareas->bindParam(':hoy', $hoy_str, PDO::PARAM_STR);
                $stmt_tareas->execute();
                $tareas_pendientes = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);

                // Se procesan las tareas para determinar si hay una para "hoy" y cuál es la "próxima".
                $cultivos_usuario[$i]['tarea_hoy'] = null;
                $cultivos_usuario[$i]['proxima_tarea'] = null;
                if ($tareas_pendientes) {
                    // Si la primera tarea pendiente es para la fecha actual, se considera la tarea de hoy.
                    if ($tareas_pendientes[0]['fecha_aplicacion_estimada'] == $hoy_str) {
                        $cultivos_usuario[$i]['tarea_hoy'] = $tareas_pendientes[0];
                        // Si hay más de una tarea, la siguiente es la próxima tarea.
                        if (isset($tareas_pendientes[1])) {
                            $cultivos_usuario[$i]['proxima_tarea'] = $tareas_pendientes[1];
                        }
                    } else {
                        // Si la primera tarea pendiente no es hoy, entonces esa es la próxima tarea.
                        $cultivos_usuario[$i]['proxima_tarea'] = $tareas_pendientes[0];
                    }
                }
            }
        }

    } catch (PDOException $e) {
        // Si ocurre un error de base de datos, se captura y se guarda un mensaje de error.
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
        /*
         * Estilos CSS para la página "Mis Cultivos".
         * Define el diseño general, la cabecera, la cuadrícula de cultivos (cards)
         * y la adaptabilidad para diferentes tamaños de pantalla (responsive design).
         */
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
        .page-container > h2.page-title{text-align:center;color:#4caf50;margin-bottom:10px;font-size:1.8em}
        
        .actions-bar {display: flex; justify-content: space-between; align-items: center; margin: 10px 0 20px 0; flex-wrap: wrap; gap: 10px;}
        .view-toggle-link a, .report-page-button {color: #4caf50; font-weight: bold; text-decoration: none; padding: 8px 15px; border: 1px solid #4caf50; border-radius: 5px; transition: background-color 0.3s, color 0.3s; background-color: #fff;}
        .view-toggle-link a:hover, .report-page-button:hover {background-color: #e8f5e9; color: #388e3c;}

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
        .tarea-hoy { color: #d9534f; font-weight: bold; } /* Estilo para tareas programadas para hoy */
        .proxima-tarea { color:rgb(0, 0, 0); } /* Estilo para la próxima tarea */
        .no-tareas { color: #777; font-style: italic;} /* Estilo cuando no hay tareas próximas */
        .cultivo-actions{margin-top:auto;padding-top:15px;text-align:right;display:flex;justify-content:flex-end;gap:10px}
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
        
        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}}
        @media (max-width:767px){.cultivos-grid{grid-template-columns:1fr}.actions-bar{flex-direction:column;align-items:stretch;}.view-toggle-link, .report-page-button{text-align:center;}}
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

    <div class="page-container">
        <h2 class="page-title"><?php echo htmlspecialchars($titulo_pagina); ?></h2>

        <div class="actions-bar">
            <div class="view-toggle-link">
                <a href="<?php echo $link_ver_otros_cultivos_href; ?>"><?php echo $link_ver_otros_cultivos_texto; ?></a>
            </div>
            <?php if (!empty($cultivos_usuario)): ?>
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

        <?php 
        // Bloque condicional para mostrar un mensaje si no hay cultivos.
        if (empty($mensaje_error) && empty($cultivos_usuario)): 
        ?>
            <div class="no-cultivos">
                <?php 
                // Muestra un mensaje específico si se están viendo cultivos "En Progreso" y no hay.
                if ($estado_a_mostrar_db == $id_estado_en_progreso): 
                ?>
                    <p>No tienes cultivos "En Progreso" registrados.</p>
                    <p><a href="crearcultivos.php">¡Registra un nuevo cultivo aquí!</a></p>
                <?php 
                // Muestra un mensaje específico si se están viendo cultivos "Terminados" y no hay.
                else: 
                ?>
                    <p>No tienes cultivos "Terminados" para mostrar.</p>
                <?php endif; ?>
            </div>
        <?php 
        // Bloque condicional para mostrar la cuadrícula de cultivos si hay datos.
        elseif (!empty($cultivos_usuario)): 
        ?>
            <div class="cultivos-grid">
                <?php 
                // Itera sobre cada cultivo para mostrarlo en una tarjeta individual.
                foreach ($cultivos_usuario as $cultivo): 
                ?>
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
                                    <?php 
                                    // Muestra la fecha de finalización real si el cultivo está terminado.
                                    if ($cultivo['id_estado_cultivo'] == $id_estado_terminado && !empty($cultivo['fecha_fin_registrada'])): 
                                    ?>
                                        <strong>Finalizado el:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_fin_registrada']))); ?><br>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="status-section">
                                <?php
                                // Lógica para calcular y mostrar la fecha de cosecha estimada o real.
                                $hoy_obj = new DateTime(); // Obtiene la fecha actual.
                                $hoy_obj->setTime(0,0,0); // Normaliza la hora a medianoche para comparación de fechas.

                                $fechaInicioObj = new DateTime($cultivo['fecha_inicio']); // Fecha de inicio del cultivo.
                                $mensajeCosecha = "Fecha de cosecha no determinada."; // Mensaje por defecto.
                                $fechaFinEstimadaObj = null;

                                // Determina la fecha de fin estimada/registrada:
                                // 1. Si hay una fecha de fin registrada, úsala.
                                if (!empty($cultivo['fecha_fin_registrada'])) {
                                    $fechaFinEstimadaObj = new DateTime($cultivo['fecha_fin_registrada']);
                                }
                                // 2. Si no hay fecha de fin registrada pero hay tiempo estimado de frutos, calcula la fecha de fin.
                                elseif (!empty($cultivo['tiempo_estimado_frutos'])) {
                                    $fechaFinEstimadaObj = clone $fechaInicioObj; // Clona la fecha de inicio.
                                    // Añade los días estimados para la maduración/cosecha basados en el tipo de cultivo.
                                    $fechaFinEstimadaObj->add(new DateInterval('P' . (int)$cultivo['tiempo_estimado_frutos'] . 'D'));
                                }

                                if ($fechaFinEstimadaObj) {
                                    $fechaCompararCosecha = clone $fechaFinEstimadaObj;
                                    $fechaCompararCosecha->setTime(0,0,0); // Normaliza la hora para comparación.

                                    // Compara la fecha de cosecha con la fecha actual para generar el mensaje adecuado.
                                    if ($fechaCompararCosecha < $hoy_obj && $cultivo['id_estado_cultivo'] == $id_estado_terminado) {
                                         // Si la cosecha ya pasó y el cultivo está marcado como terminado.
                                         $mensajeCosecha = "Finalizado el " . $fechaFinEstimadaObj->format('d/m/Y');
                                    } elseif ($fechaCompararCosecha < $hoy_obj) {
                                         // Si la cosecha ya pasó pero el cultivo aún no está marcado como terminado.
                                         $mensajeCosecha = "Cosecha debió ser el " . $fechaFinEstimadaObj->format('d/m/Y');
                                    } else { 
                                        // Si la cosecha es hoy o en el futuro.
                                        $diferencia = $hoy_obj->diff($fechaCompararCosecha); // Calcula la diferencia en días.
                                        if ($diferencia->days == 0) {
                                            $mensajeCosecha = "Cosecha estimada: ¡Hoy!";
                                        } elseif ($diferencia->days == 1 && !$diferencia->invert) { // !$diferencia->invert asegura que la fecha futura.
                                            $mensajeCosecha = "Cosecha estimada: Mañana (1 día).";
                                        } elseif (!$diferencia->invert) {
                                            // Muestra los días restantes y la fecha exacta de cosecha estimada.
                                            $mensajeCosecha = "Cosecha estimada: En {$diferencia->days} días (" . $fechaFinEstimadaObj->format('d/m/Y') . ").";
                                        }
                                    }
                                }
                                echo "<strong>Cosecha:</strong> " . htmlspecialchars($mensajeCosecha) . "<br>";

                                // Lógica para obtener el último tipo de tratamiento de "Abono" o "Fertilizante" para este cultivo.
                                $progresoAbono = "Abono: No hay datos."; // Mensaje por defecto si no se encuentra.
                                if (isset($pdo)) { 
                                    try { 
                                        $sql_abono = "SELECT tipo_tratamiento FROM tratamiento_cultivo WHERE id_cultivo = :id_cultivo_abono AND (LOWER(tipo_tratamiento) LIKE '%abono%' OR LOWER(tipo_tratamiento) LIKE '%fertilizante%') ORDER BY COALESCE(fecha_realizacion_real, fecha_aplicacion_estimada) DESC LIMIT 1"; 
                                        $stmt_abono = $pdo->prepare($sql_abono); 
                                        $stmt_abono->bindParam(':id_cultivo_abono', $cultivo['id_cultivo']); 
                                        $stmt_abono->execute(); 
                                        $ultimo_abono = $stmt_abono->fetch(PDO::FETCH_ASSOC); 
                                        if ($ultimo_abono) { 
                                            $progresoAbono = "Último abono: " . htmlspecialchars($ultimo_abono['tipo_tratamiento']); 
                                        } else { 
                                            $progresoAbono = "Abono: Ningún tratamiento registrado."; 
                                        } 
                                    } catch (PDOException $e) { 
                                        $progresoAbono = "Abono: Error consulta."; // Mensaje de error en caso de fallo de la consulta.
                                    } 
                                }
                                echo "<strong>" . htmlspecialchars($progresoAbono) . "</strong>";
                                ?>
                            </div>
                            <?php 
                            // Muestra la sección de tareas próximas solo si el cultivo está "En Progreso".
                            if ($cultivo['id_estado_cultivo'] == $id_estado_en_progreso): 
                            ?>
                            <div class="tareas-section">
                                <strong>Tareas Próximas:</strong><br>
                                <?php 
                                // Muestra la tarea para "hoy" si existe en los datos del cultivo.
                                if (isset($cultivo['tarea_hoy']) && $cultivo['tarea_hoy']): 
                                    $tarea_h = $cultivo['tarea_hoy']; 
                                ?>
                                    <p class="tarea-hoy">HOY: <?php echo htmlspecialchars($tarea_h['tipo_tratamiento']); ?></p>
                                <?php 
                                endif; 
                                // Muestra la próxima tarea (no hoy) si existe.
                                if (isset($cultivo['proxima_tarea']) && $cultivo['proxima_tarea']): 
                                    $prox_t = $cultivo['proxima_tarea']; 
                                ?>
                                    <p class="proxima-tarea">Siguiente (<?php echo htmlspecialchars(date("d/m", strtotime($prox_t['fecha_aplicacion_estimada']))); ?>): <?php echo htmlspecialchars($prox_t['tipo_tratamiento']); ?></p>
                                <?php 
                                // Si no hay tareas para hoy ni próximas tareas.
                                elseif (!(isset($cultivo['tarea_hoy']) && $cultivo['tarea_hoy'])): 
                                ?>
                                    <p class="no-tareas">No hay próximas tareas programadas.</p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="cultivo-actions">
                            <a href="detalle_cultivo.php?id_cultivo=<?php echo $cultivo['id_cultivo']; ?>" class="btn-cultivo-action btn-ver-detalles">Ver Detalles</a>
                            <?php 
                            // Muestra el botón "Marcar como Terminado" solo si el cultivo está "En Progreso".
                            if ($cultivo['id_estado_cultivo'] == $id_estado_en_progreso): 
                            ?>
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
                // Añade un evento click al botón de alternar menú.
                menuToggleBtn.addEventListener('click', () => {
                    // Alterna la clase 'active' en el menú para mostrarlo/ocultarlo.
                    mainMenu.classList.toggle('active');
                    // Actualiza el atributo 'aria-expanded' para accesibilidad.
                    menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
                });
            }
        });
    </script>
</body>
</html>