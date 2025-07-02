<?php
session_start();
// (Incluir las cabeceras de no-cache aquí si es necesario)

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../pages/auth/login.html");
    exit();
}

include '../../conexion.php';
$id_usuario_actual = $_SESSION['id_usuario'];
$historial_alimentacion = [];
$animal_info = null;
$mensaje_pagina = '';

if (!isset($_GET['id_animal']) || !is_numeric($_GET['id_animal'])) {
    $_SESSION['mensaje_error_animal'] = "ID de animal no válido."; // Usar para mis_animales.php
    header("Location: mis_animales.php");
    exit();
}
$id_animal_seleccionado = (int)$_GET['id_animal'];

// Verificar que el animal pertenece al usuario
try {
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

    // Obtener historial de alimentación
    $sql_historial = "SELECT id_alimentacion, tipo_alimento, cantidad_diaria, unidad_cantidad, 
                             frecuencia_alimentacion, DATE_FORMAT(fecha_registro_alimentacion, '%d/%m/%Y') as fecha_registro_f, observaciones
                      FROM alimentacion
                      WHERE id_animal = :id_animal
                      ORDER BY fecha_registro_alimentacion DESC, id_alimentacion DESC";
    $stmt_historial = $pdo->prepare($sql_historial);
    $stmt_historial->bindParam(':id_animal', $id_animal_seleccionado, PDO::PARAM_INT);
    $stmt_historial->execute();
    $historial_alimentacion = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Historial de Alimentación</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
        .container { width: 90%; max-width: 900px; margin: 30px auto; background: #fff; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1, h2 { text-align: center; color: #4CAF50; margin-bottom: 20px; }
        h2 { font-size: 1.4em; color: #333; margin-top:0; }
        .animal-info-header { background-color: #e9f5e9; padding: 15px; border-radius: 5px; margin-bottom:25px; border-left: 4px solid #4CAF50;}
        .animal-info-header p { margin: 5px 0; color: #333; font-size: 1.1em; }
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.95em; }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
        th { background-color: #f2f2f2; color: #333; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        td.actions a { margin-right: 8px; color: #007bff; text-decoration: none; }
        td.actions a:hover { text-decoration: underline; }
        .no-records { text-align: center; padding: 20px; font-style: italic; color: #777; }
        .btn-add-pauta {
            display: inline-block; padding: 10px 18px; background-color: #5cb85c; color: white !important;
            text-decoration: none; border-radius: 5px; font-weight: bold;
            transition: background-color 0.3s ease; margin-bottom: 20px;
        }
        .btn-add-pauta:hover { background-color: #4cae4c; }
        .mensaje-pagina { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-size: 0.95em; }
        .mensaje-pagina.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje-pagina.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        .back-link-container { text-align: center; margin-top: 25px; }
        .back-link { color: #337ab7; text-decoration: none; font-size: 0.9em; margin: 0 10px; }
        .back-link:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            th, td { padding: 8px; font-size: 0.9em; }
            .btn-add-pauta { font-size: 0.9em; padding: 8px 15px; }
        }
    </style>
</head>
<body>
    <?php // include '../header_app_interna.php'; ?>
    <div class="container">
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

        <div style="margin-bottom: 20px;">
            <a href="registrar_alimentacion.php?id_animal=<?php echo $id_animal_seleccionado; ?>" class="btn-add-pauta">Añadir Nueva Pauta de Alimentación</a>
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
                                    <!-- <a href="editar_alimentacion.php?id_alimentacion=<?php echo $pauta['id_alimentacion']; ?>&id_animal=<?php echo $id_animal_seleccionado; ?>">Editar</a> -->
                                    <!-- <a href="eliminar_alimentacion.php?id_alimentacion=<?php echo $pauta['id_alimentacion']; ?>&id_animal=<?php echo $id_animal_seleccionado; ?>" onclick="return confirm('¿Estás seguro de eliminar esta pauta?');">Eliminar</a> -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
             <?php if (empty($mensaje_pagina) || stripos($mensaje_pagina, 'exitosamente') !== false) : // No mostrar "no hay registros" si hay un error general de carga ?>
                <p class="no-records">No hay pautas de alimentación registradas para este animal/lote.</p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="back-link-container">
            <a href="mis_animales.php" class="back-link">Volver a Mis Animales</a>
        </div>
    </div>
</body>
</html>