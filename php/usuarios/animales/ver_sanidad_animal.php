<?php
session_start();

// 1. --- VERIFICACIÓN DE AUTENTICACIÓN ---
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../pages/auth/login.html");
    exit();
}

// 2. --- INCLUSIÓN Y VARIABLES ---
include '../../conexion.php';
$id_usuario_actual = $_SESSION['id_usuario'];
$historial_sanitario = [];
$sugerencias_sanitarias = [];
$animal_info = null;
$mensaje_pagina = '';

// 3. --- VALIDACIÓN DEL PARÁMETRO GET 'id_animal' ---
if (!isset($_GET['id_animal']) || !is_numeric($_GET['id_animal'])) {
    $_SESSION['mensaje_error_animal'] = "ID de animal no válido para ver sanidad.";
    header("Location: mis_animales.php");
    exit();
}
$id_animal_seleccionado = (int)$_GET['id_animal'];

// 4. --- LÓGICA DE BASE DE DATOS ---
try {
    // 4.1. Obtener información del animal y validar pertenencia
    $stmt_animal = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal, cantidad, fecha_nacimiento FROM animales WHERE id_animal = :id_animal AND id_usuario = :id_usuario");
    $stmt_animal->bindParam(':id_animal', $id_animal_seleccionado, PDO::PARAM_INT);
    $stmt_animal->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_animal->execute();
    $animal_info = $stmt_animal->fetch(PDO::FETCH_ASSOC);

    if (!$animal_info) {
        $_SESSION['mensaje_error_animal'] = "Animal no encontrado o no te pertenece.";
        header("Location: mis_animales.php");
        exit();
    }

    // 4.2. Obtener historial sanitario del animal
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

    // 4.3. Generar sugerencias si el animal tiene fecha de nacimiento
    if ($animal_info['fecha_nacimiento']) {
        $fecha_nac = new DateTime($animal_info['fecha_nacimiento']);
        $hoy = new DateTime();
        $edad_animal_dias = $hoy->diff($fecha_nac)->days;
        $tipo_animal_like = '%' . $animal_info['tipo_animal'] . '%';

        // Consulta para encontrar productos sugeridos por especie y edad
        $sql_sugerencias = "SELECT id_tipo_mv, nombre_producto, tipo_aplicacion, descripcion_uso, 
                                   edad_aplicacion_sugerida_dias_min, edad_aplicacion_sugerida_dias_max,
                                   dosis_sugerida, via_administracion_sugerida, frecuencia_sugerida
                            FROM tipos_medicamento_vacuna
                            WHERE (especie_objetivo LIKE :tipo_animal_like OR especie_objetivo = 'General' OR especie_objetivo IS NULL)
                              AND (
                                    (:edad_actual_dias BETWEEN edad_aplicacion_sugerida_dias_min AND edad_aplicacion_sugerida_dias_max)
                                  )";
        
        $stmt_sugerencias = $pdo->prepare($sql_sugerencias);
        $stmt_sugerencias->bindParam(':tipo_animal_like', $tipo_animal_like, PDO::PARAM_STR);
        $stmt_sugerencias->bindParam(':edad_actual_dias', $edad_animal_dias, PDO::PARAM_INT);
        $stmt_sugerencias->execute();
        $sugerencias_posibles = $stmt_sugerencias->fetchAll(PDO::FETCH_ASSOC);

        // Filtrado simple para no mostrar sugerencias de productos ya aplicados
        $productos_ya_aplicados_ids = [];
        $stmt_aplicados = $pdo->prepare("SELECT DISTINCT id_tipo_mv FROM registro_sanitario_animal WHERE id_animal = :id_animal AND id_tipo_mv IS NOT NULL");
        $stmt_aplicados->bindParam(':id_animal', $id_animal_seleccionado, PDO::PARAM_INT);
        $stmt_aplicados->execute();
        $res_aplicados = $stmt_aplicados->fetchAll(PDO::FETCH_COLUMN);
        if ($res_aplicados) {
            $productos_ya_aplicados_ids = $res_aplicados;
        }

        foreach ($sugerencias_posibles as $sug) {
            // Lógica simple: si el id_tipo_mv ya está en los aplicados, no lo sugerimos.
            // Una lógica más avanzada podría considerar la frecuencia de la aplicación.
            if (!in_array($sug['id_tipo_mv'], $productos_ya_aplicados_ids)) {
                $sugerencias_sanitarias[] = $sug;
            }
        }
    }

    if(isset($_GET['mensaje_exito_registro'])){
        $mensaje_pagina = "Registro sanitario guardado exitosamente.";
    }

} catch (PDOException $e) {
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
        /* Estilos generales y layout */
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
    <div class="header">
        <div class="logo">
            <img src="../../../img/logo.png" alt="Logo GAG" />
        </div>
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

        <?php if ($animal_info): ?>
            <div class="animal-info-header">
                <p><strong>Animal/Lote:</strong> <?php echo htmlspecialchars($animal_info['tipo_animal']); ?>
                    <?php echo !empty($animal_info['nombre_animal']) ? ' "' . htmlspecialchars($animal_info['nombre_animal']) . '"' : ''; ?>
                    (ID: <?php echo htmlspecialchars($animal_info['id_animal']); ?>)
                </p>
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

        <?php if (!empty($mensaje_pagina)): ?>
            <div class="mensaje-pagina <?php echo (stripos($mensaje_pagina, 'exitosamente') !== false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje_pagina); ?>
            </div>
        <?php endif; ?>

        <div class="action-bar">
            <a href="registrar_sanidad_animal.php?id_animal=<?php echo $id_animal_seleccionado; ?>" class="btn-action btn-add-registro">Registrar Nueva Aplicación</a>
            <?php if (!empty($historial_sanitario)): ?>
                <a href="generar_reporte_sanidad.php?id_animal=<?php echo $id_animal_seleccionado; ?>" class="btn-action btn-reporte" target="_blank">
                    Generar Reporte Sanitario
                </a>
            <?php endif; ?>
        </div>

        <!-- Sección de Sugerencias -->
        <?php if ($animal_info && $animal_info['fecha_nacimiento']): ?>
            <h2>Sugerencias Sanitarias</h2>
            <?php if (!empty($sugerencias_sanitarias)): ?>
                <?php foreach ($sugerencias_sanitarias as $sug): ?>
                    <div class="sugerencia-card">
                        <h4><?php echo htmlspecialchars($sug['nombre_producto']); ?> (<?php echo htmlspecialchars($sug['tipo_aplicacion']); ?>)</h4>
                        <p><strong>Uso:</strong> <?php echo htmlspecialchars($sug['descripcion_uso'] ?: 'No especificado'); ?></p>
                        <?php if($sug['edad_aplicacion_sugerida_dias_min'] !== null): ?>
                            <p><strong>Aplicar a partir de:</strong> <?php echo $sug['edad_aplicacion_sugerida_dias_min']; ?> días de edad.</p>
                        <?php endif; ?>
                        <p><strong>Frecuencia Sugerida:</strong> <?php echo htmlspecialchars($sug['frecuencia_sugerida'] ?: 'N/A'); ?></p>
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

        <!-- Sección de Historial -->
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

        <div class="back-link-container">
            <a href="mis_animales.php" class="back-link">Volver a Mis Animales</a>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>