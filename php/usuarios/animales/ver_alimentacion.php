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
$historial_alimentacion = [];
$animal_info = null;
$mensaje_pagina = '';

// 3. --- VALIDACIÓN DEL PARÁMETRO GET ---
if (!isset($_GET['id_animal']) || !is_numeric($_GET['id_animal'])) {
    $_SESSION['mensaje_error_animal'] = "ID de animal no válido.";
    header("Location: mis_animales.php");
    exit();
}
$id_animal_seleccionado = (int)$_GET['id_animal'];

// 4. --- LÓGICA DE BASE DE DATOS ---
try {
    // 4.1. Validar pertenencia del animal
    $stmt_animal = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal, cantidad FROM animales WHERE id_animal = :id_animal AND id_usuario = :id_usuario");
    $stmt_animal->bindParam(':id_animal', $id_animal_seleccionado, PDO::PARAM_INT);
    $stmt_animal->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_animal->execute();
    $animal_info = $stmt_animal->fetch(PDO::FETCH_ASSOC);

    if (!$animal_info) {
        $_SESSION['mensaje_error_animal'] = "Animal no encontrado o no te pertenece.";
        header("Location: mis_animales.php");
        exit();
    }

    // 4.2. Obtener el historial
    $sql_historial = "SELECT id_alimentacion, tipo_alimento, cantidad_diaria, unidad_cantidad, 
                             frecuencia_alimentacion, DATE_FORMAT(fecha_registro_alimentacion, '%d/%m/%Y') as fecha_registro_f, observaciones
                      FROM alimentacion
                      WHERE id_animal = :id_animal
                      ORDER BY fecha_registro_alimentacion DESC, id_alimentacion DESC";
    $stmt_historial = $pdo->prepare($sql_historial);
    $stmt_historial->bindParam(':id_animal', $id_animal_seleccionado, PDO::PARAM_INT);
    $stmt_historial->execute();
    $historial_alimentacion = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

    // 4.3. Mensaje de éxito
    if(isset($_GET['mensaje_exito'])){
        $mensaje_pagina = "Pauta de alimentación registrada exitosamente.";
    }

} catch (PDOException $e) {
    $mensaje_pagina = "Error al obtener datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Alimentación - GAG</title>
    <!-- Incluir tu hoja de estilos principal si la tienes -->
    <!-- <link rel="stylesheet" href="../../css/styles.css"> -->
    <style>
        /* Estilos generales (Copiar de tus otras páginas para consistencia) */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9; font-size: 16px; color: #333; }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background-color: #e0e0e0; border-bottom: 2px solid #ccc; position: relative; }
        .logo img { height: 70px; }
        .menu { display: flex; align-items: center; }
        .menu a { margin: 0 5px; text-decoration: none; color: black; padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; white-space: nowrap; font-size: 0.9em; }
        .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: #70a845; }
        .menu a.exit { background-color: #ff4d4d; color: white !important; border: 1px solid #cc0000; }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.8rem; color: #333; cursor: pointer; }
        
        .page-container { width: 90%; max-width: 900px; margin: 30px auto; background: #fff; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .page-container h1 { text-align: center; color: #4CAF50; margin-bottom: 20px; }
        
        .animal-info-header { background-color: #e9f5e9; padding: 15px; border-radius: 5px; margin-bottom:25px; border-left: 4px solid #4CAF50;}
        .animal-info-header p { margin: 5px 0; color: #333; font-size: 1.1em; }
        
        .action-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .btn-action { display: inline-block; padding: 10px 18px; color: white !important; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background-color 0.3s ease; text-align: center; }
        .btn-add-pauta { background-color: #5cb85c; }
        .btn-add-pauta:hover { background-color: #4cae4c; }
        .btn-reporte { background-color: #17a2b8; }
        .btn-reporte:hover { background-color: #138496; }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.95em; }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        td.actions a { margin-right: 8px; color: #007bff; text-decoration: none; }
        .no-records { text-align: center; padding: 20px; font-style: italic; color: #777; }
        
        .mensaje-pagina { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .mensaje-pagina.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje-pagina.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        
        .back-link-container { text-align: center; margin-top: 25px; }
        .back-link { color: #337ab7; text-decoration: none; font-size: 0.9em; }

        /* Estilos responsivos (Copiar de tus otras páginas) */
        @media (max-width: 991.98px) { .menu-toggle { display: block; } .menu { display: none; /* ... */ } }
        @media (max-width: 768px) { .btn-action { flex-grow: 1; } }
    </style>
</head>
<body>
    <!-- ===== INICIO DE LA ESTRUCTURA HTML COMPLETA ===== -->
    
    <div class="header">
        <div class="logo">
            <img src="../../../img/logo.png" alt="Logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">
            ☰
        </button>
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
        <h1>Historial de Alimentación</h1>

        <?php if ($animal_info): ?>
            <div class="animal-info-header">
                <p><strong>Animal/Lote:</strong> <?php echo htmlspecialchars($animal_info['tipo_animal']); ?>
                    <?php echo !empty($animal_info['nombre_animal']) ? ' "' . htmlspecialchars($animal_info['nombre_animal']) . '"' : ''; ?>
                    (ID: <?php echo htmlspecialchars($animal_info['id_animal']); ?>
                    <?php if($animal_info['cantidad'] > 1) echo ", Lote de: ".htmlspecialchars($animal_info['cantidad']); ?>)
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje_pagina)): ?>
            <div class="mensaje-pagina <?php echo (stripos($mensaje_pagina, 'exitosamente') !== false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje_pagina); ?>
            </div>
        <?php endif; ?>

        <div class="action-bar">
            <a href="registrar_alimentacion.php?id_animal=<?php echo $id_animal_seleccionado; ?>" class="btn-action btn-add-pauta">Añadir Nueva Pauta</a>
            <?php if (!empty($historial_alimentacion)): ?>
                <a href="generar_reporte_alimentacion.php?id_animal=<?php echo $id_animal_seleccionado; ?>" class="btn-action btn-reporte" target="_blank">
                    Generar Reporte Excel
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($historial_alimentacion)): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha Pauta</th>
                            <th>Tipo Alimento</th>
                            <th>Cantidad Diaria</th>
                            <th>Unidad</th>
                            <th>Frecuencia</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial_alimentacion as $pauta): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pauta['fecha_registro_f']); ?></td>
                                <td><?php echo htmlspecialchars($pauta['tipo_alimento']); ?></td>
                                <td><?php echo htmlspecialchars(rtrim(rtrim(number_format($pauta['cantidad_diaria'], 2, '.', ''), '0'), '.')); ?></td>
                                <td><?php echo htmlspecialchars($pauta['unidad_cantidad']); ?></td>
                                <td><?php echo htmlspecialchars($pauta['frecuencia_alimentacion']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($pauta['observaciones'] ?: '-')); ?></td>
                                <td class="actions">
                                    <!-- Espacio para futuros botones de Editar/Eliminar -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
             <?php if (empty($mensaje_pagina) || stripos($mensaje_pagina, 'exitosamente') !== false): ?>
                <p class="no-records">No hay pautas de alimentación registradas para este animal/lote.</p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="back-link-container">
            <a href="mis_animales.php" class="back-link">Volver a Mis Animales</a>
        </div>
    </div>
    
    <!-- Pie de página si lo usas en las páginas internas -->
    <!--
    <footer class="footer">
        ... tu contenido de footer ...
    </footer>
    -->

    <script>
        // Script para el menú hamburguesa (si lo necesitas)
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');

            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    const isExpanded = mainMenu.classList.contains('active');
                    menuToggleBtn.setAttribute('aria-expanded', isExpanded);
                });
            }
        });
    </script>
</body>
</html>