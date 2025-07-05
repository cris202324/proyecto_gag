<?php
// Inicia la sesión de PHP. Es necesario para usar variables de sesión como $_SESSION['id_usuario']
// para verificar que el usuario ha iniciado sesión.
session_start();

// 1. --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Esta es la primera barrera de seguridad. Si el usuario no ha iniciado sesión,
// se le redirige a la página de login y el script se detiene.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../pages/auth/login.html");
    exit();
}

// 2. --- INCLUSIÓN DE ARCHIVOS Y DEFINICIÓN DE VARIABLES INICIALES ---
include '../../conexion.php'; // Incluye el archivo de conexión a la base de datos, que define la variable $pdo.
$id_usuario_actual = $_SESSION['id_usuario']; // ID del usuario actual, obtenido de la sesión.
$historial_sanitario = []; // Array que almacenará el historial de aplicaciones sanitarias.
$sugerencias_sanitarias = []; // Array que almacenará las sugerencias de vacunas/medicamentos.
$animal_info = null; // Almacenará los datos del animal seleccionado.
$mensaje_pagina = ''; // Variable para mostrar mensajes de error o éxito.

// 3. --- VALIDACIÓN DEL PARÁMETRO GET 'id_animal' ---
// Se comprueba si se ha pasado un 'id_animal' en la URL y si es numérico.
// Esto es crucial para saber de qué animal se debe mostrar la información.
if (!isset($_GET['id_animal']) || !is_numeric($_GET['id_animal'])) {
    // Si el ID no es válido, se guarda un mensaje de error en la sesión y se redirige al usuario.
    $_SESSION['mensaje_error_animal'] = "ID de animal no válido para ver sanidad.";
    header("Location: mis_animales.php");
    exit();
}
$id_animal_seleccionado = (int)$_GET['id_animal']; // Se convierte a entero para seguridad.

// 4. --- LÓGICA DE BASE DE DATOS ---
// El bloque try-catch maneja de forma segura los errores que puedan ocurrir durante las consultas.
try {
    // 4.1. Obtener información del animal y validar que pertenece al usuario logueado.
    // Esta es una medida de seguridad para evitar que un usuario vea datos de otro manipulando la URL.
    $stmt_animal = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal, cantidad, fecha_nacimiento FROM animales WHERE id_animal = :id_animal AND id_usuario = :id_usuario");
    $stmt_animal->bindParam(':id_animal', $id_animal_seleccionado, PDO::PARAM_INT);
    $stmt_animal->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_animal->execute();
    $animal_info = $stmt_animal->fetch(PDO::FETCH_ASSOC);

    // Si la consulta no devuelve un resultado, el animal no existe o no pertenece al usuario.
    if (!$animal_info) {
        $_SESSION['mensaje_error_animal'] = "Animal no encontrado o no te pertenece.";
        header("Location: mis_animales.php");
        exit();
    }

    // 4.2. Obtener el historial sanitario completo del animal.
    // La consulta selecciona todos los registros de la tabla 'registro_sanitario_animal' para este animal.
    // Se formatea la fecha para una mejor visualización y se ordena por la fecha de aplicación más reciente.
    $sql_historial = "SELECT id_registro_sanitario, nombre_producto_aplicado, tipo_aplicacion_registrada, 
                             DATE_FORMAT(fecha_aplicacion, '%d/%m/%Y') as fecha_aplicacion_f, 
                             dosis_aplicada, via_administracion, responsable_aplicacion, observaciones,
                             DATE_FORMAT(fecha_proxima_dosis_sugerida, '%d/%m/%Y') as fecha_proxima_f
                      FROM registro_sanitario_animal
                      WHERE id_animal = :id_animal
                      ORDER BY fecha_aplicacion DESC, id_registro_sanitario DESC";
    $stmt_historial = $pdo->prepare($sql_historial);
    $stmt_historial->bindParam(':id_animal', $id_animal_seleccionado, PDO::PARAM_INT);
    $stmt_historial->execute();
    $historial_sanitario = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

    // 4.3. Generar sugerencias sanitarias si el animal tiene una fecha de nacimiento registrada.
    // Esta es la parte más "inteligente" del script.
    if ($animal_info['fecha_nacimiento']) {
        // Se calcula la edad actual del animal en días.
        $fecha_nac = new DateTime($animal_info['fecha_nacimiento']);
        $hoy = new DateTime();
        $edad_animal_dias = $hoy->diff($fecha_nac)->days;
        // Se prepara el tipo de animal para una búsqueda flexible (ej. "Bovinos" coincidirá con "Bovino").
        $tipo_animal_like = '%' . $animal_info['tipo_animal'] . '%';

        // Se buscan en la tabla de productos predefinidos aquellos que coincidan con:
        // - La especie del animal (o que sean de uso "General").
        // - El rango de edad sugerido para la aplicación.
        $sql_sugerencias = "SELECT id_tipo_mv, nombre_producto, tipo_aplicacion, descripcion_uso, 
                                   edad_aplicacion_sugerida_dias_min, edad_aplicacion_sugerida_dias_max,
                                   dosis_sugerida, via_administracion_sugerida, frecuencia_sugerida
                            FROM tipos_medicamento_vacuna
                            WHERE (especie_objetivo LIKE :tipo_animal_like OR especie_objetivo = 'General' OR especie_objetivo IS NULL)
                              AND (:edad_actual_dias BETWEEN edad_aplicacion_sugerida_dias_min AND edad_aplicacion_sugerida_dias_max)";
        
        $stmt_sugerencias = $pdo->prepare($sql_sugerencias);
        $stmt_sugerencias->bindParam(':tipo_animal_like', $tipo_animal_like, PDO::PARAM_STR);
        $stmt_sugerencias->bindParam(':edad_actual_dias', $edad_animal_dias, PDO::PARAM_INT);
        $stmt_sugerencias->execute();
        $sugerencias_posibles = $stmt_sugerencias->fetchAll(PDO::FETCH_ASSOC);

        // Se filtran las sugerencias para no mostrar productos que ya han sido aplicados.
        // Primero, se obtiene una lista de los IDs de productos ya aplicados a este animal.
        $productos_ya_aplicados_ids = [];
        $stmt_aplicados = $pdo->prepare("SELECT DISTINCT id_tipo_mv FROM registro_sanitario_animal WHERE id_animal = :id_animal AND id_tipo_mv IS NOT NULL");
        $stmt_aplicados->bindParam(':id_animal', $id_animal_seleccionado, PDO::PARAM_INT);
        $stmt_aplicados->execute();
        $res_aplicados = $stmt_aplicados->fetchAll(PDO::FETCH_COLUMN); // Devuelve un array simple con los IDs.
        if ($res_aplicados) {
            $productos_ya_aplicados_ids = $res_aplicados;
        }

        // Se recorren las sugerencias posibles y se añaden al array final solo si no han sido aplicadas aún.
        // NOTA: Esta es una lógica simple. Una versión más avanzada debería considerar la frecuencia (ej. si una vacuna es anual).
        foreach ($sugerencias_posibles as $sug) {
            if (!in_array($sug['id_tipo_mv'], $productos_ya_aplicados_ids)) {
                $sugerencias_sanitarias[] = $sug;
            }
        }
    }

    // Se comprueba si hay un mensaje de éxito en la URL (ej. después de registrar una aplicación).
    if(isset($_GET['mensaje_exito_registro'])){
        $mensaje_pagina = "Registro sanitario guardado exitosamente.";
    }

} catch (PDOException $e) {
    // Si alguna de las consultas falla, se captura el error y se guarda un mensaje.
    $mensaje_pagina = "Error al obtener datos sanitarios: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan Sanitario del Animal - GAG</title>
    <style>
        /* --- ESTILOS CSS --- */
        /* Estilos visuales para la página, incluyendo el layout, cabecera, menú, tabla,
           tarjetas de sugerencia y responsividad. */
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f9f9f9; color: #333; }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background-color: #e0e0e0; border-bottom: 2px solid #ccc; position: relative; }
        .logo img { height: 70px; }
        .menu { display: flex; align-items: center; }
        .menu a { margin: 0 5px; text-decoration: none; color: black; padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; white-space: nowrap; font-size: 0.9em; }
        .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: #70a845; }
        .menu a.exit { background-color: #ff4d4d; color: white !important; }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.8rem; color: #333; cursor: pointer; }
        
        .page-container { width: 95%; max-width: 1000px; margin: 20px auto; background: #fff; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { text-align: center; color: #dc3545; margin-bottom: 20px; }
        h2 { font-size: 1.5em; color: #333; margin-top: 30px; border-bottom: 2px solid #eee; padding-bottom: 8px; }
        
        .animal-info-header { background-color: #fbeae5; padding: 15px; border-radius: 5px; margin-bottom:25px; border-left: 4px solid #dc3545;}
        .animal-info-header p { margin: 5px 0; color: #333; font-size: 1.1em; }
        
        .action-bar { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .btn-action { display: inline-block; padding: 10px 18px; color: white !important; text-decoration: none; border-radius: 5px; font-weight: bold; text-align: center; transition: background-color 0.3s; }
        .btn-add-registro { background-color: #dc3545; }
        .btn-add-registro:hover { background-color: #c82333; }
        .btn-reporte { background-color: #6c757d; }
        .btn-reporte:hover { background-color: #5a6268; }
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        th { background-color: #343a40; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        
        .sugerencia-card { border: 1px solid #bce8f1; background-color: #d9edf7; color: #31708f; padding: 15px; margin-bottom: 10px; border-radius: 4px; }
        .sugerencia-card h4 { margin: 0 0 10px 0; color: #285e8e; font-size: 1.1em; border-bottom: 1px solid #a6d8e8; padding-bottom: 5px;}
        .sugerencia-card p { margin: 4px 0; font-size: 0.9em; }
        .sugerencia-card p strong { color: #31708f; }
        .sugerencia-card .apply-link { display: block; text-align: right; margin-top:10px; }
        .sugerencia-card .apply-link a { font-size:0.9em; color:#285e8e; font-weight:bold; text-decoration: none; padding: 5px 10px; background-color: rgba(255,255,255,0.5); border-radius: 4px;}
        .sugerencia-card .apply-link a:hover { background-color: rgba(255,255,255,0.8); }

        .no-records { text-align: center; padding: 20px; font-style: italic; color: #777; }
        .mensaje-pagina { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .mensaje-pagina.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje-pagina.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }

        .back-link-container { text-align: center; margin-top: 25px; }
        .back-link { color: #337ab7; text-decoration: none; }
        
        @media (max-width: 991.98px) { .menu-toggle { display: block; } .menu { display: none; flex-direction: column; align-items: stretch; position: absolute; top: 100%; left: 0; width: 100%; background-color: #e9e9e9; padding: 0; box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1000; border-top: 1px solid #ccc; } .menu.active{display:flex} .menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333} .menu a:last-child{border-bottom:none} .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent} .menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}}
        @media (max-width: 768px) { .btn-action { flex-grow: 1; } }
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML DE LA PÁGINA --- -->
    <div class="header">
        <div class="logo"> <img src="../../../img/logo.png" alt="Logo GAG" /> </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="../../index.php">Inicio</a>
            <a href="../cultivos/miscultivos.php">Mis Cultivos</a>
            <a href="mis_animales.php" class="active">Mis Animales</a>
            <a href="../calendario.php">Calendario</a>
            <a href="../configuracion.php">Configuración</a>
            <a href="../ayuda.php">Ayuda</a>
            <a href="../../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h1>Plan y Registro Sanitario</h1>

        <!-- Muestra un resumen del animal/lote seleccionado y su edad calculada. -->
        <?php if ($animal_info): ?>
            <div class="animal-info-header">
                <p><strong>Animal/Lote:</strong> <?php echo htmlspecialchars($animal_info['tipo_animal'] . ' "' . $animal_info['nombre_animal'] . '"'); ?> (ID: <?php echo htmlspecialchars($animal_info['id_animal']); ?>)</p>
                <?php if ($animal_info['fecha_nacimiento']): 
                    $fecha_nac_info = new DateTime($animal_info['fecha_nacimiento']);
                    $hoy_info = new DateTime();
                    $edad_info = $hoy_info->diff($fecha_nac_info);
                ?>
                <p><strong>Edad Actual:</strong> 
                    <?php echo $edad_info->y . " años, " . $edad_info->m . " meses, " . $edad_info->d . " días"; ?>
                    (Total: <?php echo $edad_info->days; ?> días)
                </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Muestra mensajes de éxito o error. -->
        <?php if (!empty($mensaje_pagina)): ?>
            <div class="mensaje-pagina <?php echo (stripos($mensaje_pagina, 'exitosamente') !== false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje_pagina); ?>
            </div>
        <?php endif; ?>

        <!-- Barra de acciones: añadir nuevo registro y generar reporte. -->
        <div class="action-bar">
            <a href="registrar_sanidad_animal.php?id_animal=<?php echo $id_animal_seleccionado; ?>" class="btn-action btn-add-registro">Registrar Nueva Aplicación</a>
            <?php if (!empty($historial_sanitario)): ?>
                <a href="generar_reporte_sanidad.php?id_animal=<?php echo $id_animal_seleccionado; ?>" class="btn-action btn-reporte" target="_blank">Generar Reporte Sanitario</a>
            <?php endif; ?>
        </div>

        <!-- Sección de Sugerencias: se muestra solo si el animal tiene fecha de nacimiento. -->
        <?php if ($animal_info && $animal_info['fecha_nacimiento']): ?>
            <h2>Sugerencias Sanitarias</h2>
            <?php if (!empty($sugerencias_sanitarias)): ?>
                <!-- Bucle para mostrar cada sugerencia en una tarjeta. -->
                <?php foreach ($sugerencias_sanitarias as $sug): ?>
                    <div class="sugerencia-card">
                        <h4><?php echo htmlspecialchars($sug['nombre_producto']); ?> (<?php echo htmlspecialchars($sug['tipo_aplicacion']); ?>)</h4>
                        <p><strong>Uso:</strong> <?php echo htmlspecialchars($sug['descripcion_uso'] ?: 'No especificado'); ?></p>
                        <?php if($sug['edad_aplicacion_sugerida_dias_min'] !== null): ?>
                            <p><strong>Aplicar a partir de:</strong> <?php echo $sug['edad_aplicacion_sugerida_dias_min']; ?> días de edad.</p>
                        <?php endif; ?>
                        <p><strong>Frecuencia Sugerida:</strong> <?php echo htmlspecialchars($sug['frecuencia_sugerida'] ?: 'N/A'); ?></p>
                        <!-- Enlace que lleva al formulario de registro con el producto ya preseleccionado. -->
                        <div class="apply-link">
                            <a href="registrar_sanidad_animal.php?id_animal=<?php echo $id_animal_seleccionado; ?>&id_tipo_mv=<?php echo $sug['id_tipo_mv']; ?>">Aplicar esto</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-records">No hay sugerencias sanitarias activas para la edad actual o tipo de este animal/lote.</p>
            <?php endif; ?>
        <?php elseif ($animal_info && !$animal_info['fecha_nacimiento']): ?>
            <h2>Sugerencias Sanitarias</h2>
            <p class="no-records">No se pueden generar sugerencias de edad porque la fecha de nacimiento no está registrada.</p>
        <?php endif; ?>

        <!-- Sección de Historial: muestra la tabla de aplicaciones ya registradas. -->
        <h2>Historial de Aplicaciones Sanitarias</h2>
        <?php if (!empty($historial_sanitario)): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha Aplic.</th>
                            <th>Producto Aplicado</th>
                            <th>Tipo</th>
                            <th>Dosis</th>
                            <th>Vía</th>
                            <th>Próx. Dosis</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Bucle para mostrar cada registro en una fila de la tabla. -->
                        <?php foreach ($historial_sanitario as $reg): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reg['fecha_aplicacion_f']); ?></td>
                                <td><?php echo htmlspecialchars($reg['nombre_producto_aplicado']); ?></td>
                                <td><?php echo htmlspecialchars($reg['tipo_aplicacion_registrada']); ?></td>
                                <td><?php echo htmlspecialchars($reg['dosis_aplicada'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($reg['via_administracion'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($reg['fecha_proxima_f'] ?: '-'); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($reg['observaciones'] ?: '-')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-records">No hay registros sanitarios para este animal/lote.</p>
        <?php endif; ?>

        <!-- Enlace para volver a la lista principal de animales. -->
        <div class="back-link-container">
            <a href="mis_animales.php" class="back-link">Volver a Mis Animales</a>
        </div>
    </div>
    
    <!-- SCRIPT JAVASCRIPT PARA EL MENÚ RESPONSIVE (HAMBURGUESA) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                // Añade un evento de clic al botón del menú.
                menuToggleBtn.addEventListener('click', () => {
                    // Alterna la clase 'active' para mostrar u ocultar el menú.
                    mainMenu.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>