<?php
session_start();
// (Incluir las cabeceras de no-cache aquí si es necesario)

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../pages/auth/login.html");
    exit();
}

include '../conexion.php';
$id_usuario_actual = $_SESSION['id_usuario'];
$mensaje = '';
$animal_seleccionado_info = null;
$id_animal_get = null;

// Obtener id_animal de GET si está presente (para preseleccionar)
if (isset($_GET['id_animal']) && is_numeric($_GET['id_animal'])) {
    $id_animal_get = (int)$_GET['id_animal'];
    
    // Validar que el animal pertenece al usuario y obtener su nombre/tipo
    $stmt_val_animal = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal, cantidad FROM animales WHERE id_animal = :id_animal AND id_usuario = :id_usuario");
    $stmt_val_animal->bindParam(':id_animal', $id_animal_get, PDO::PARAM_INT);
    $stmt_val_animal->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_val_animal->execute();
    $animal_seleccionado_info = $stmt_val_animal->fetch(PDO::FETCH_ASSOC);
    if (!$animal_seleccionado_info) {
        // El animal no pertenece al usuario o no existe, anular para que se muestre el selector general
        $id_animal_get = null; 
        $animal_seleccionado_info = null;
        $mensaje = "Error: El animal especificado no es válido o no te pertenece.";
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_animal_post = filter_input(INPUT_POST, 'id_animal', FILTER_VALIDATE_INT);
    $tipo_alimento = trim($_POST['tipo_alimento']);
    $cantidad_diaria = filter_input(INPUT_POST, 'cantidad_diaria', FILTER_VALIDATE_FLOAT);
    $unidad_cantidad = trim($_POST['unidad_cantidad']);
    $frecuencia_alimentacion = trim($_POST['frecuencia_alimentacion']);
    $fecha_registro_alimentacion = $_POST['fecha_registro_alimentacion']; // Ya es YYYY-MM-DD
    $observaciones = !empty(trim($_POST['observaciones'])) ? trim($_POST['observaciones']) : null;

    if (!$id_animal_post) {
        $mensaje = "Error: Debes seleccionar un animal.";
    } elseif (empty($tipo_alimento)) {
        $mensaje = "El tipo de alimento es obligatorio.";
    } elseif ($cantidad_diaria === false || $cantidad_diaria <= 0) {
        $mensaje = "La cantidad diaria debe ser un número positivo.";
    } elseif (empty($unidad_cantidad)) {
        $mensaje = "La unidad para la cantidad es obligatoria.";
    } elseif (empty($frecuencia_alimentacion)) {
        $mensaje = "La frecuencia de alimentación es obligatoria.";
    } elseif (empty($fecha_registro_alimentacion)) {
        $mensaje = "La fecha de registro de esta pauta es obligatoria.";
    } else {
        // Validar que el animal seleccionado pertenece al usuario (importante si no vino por GET)
        $stmt_val = $pdo->prepare("SELECT id_animal FROM animales WHERE id_animal = :id_animal_val AND id_usuario = :id_usuario_val");
        $stmt_val->bindParam(':id_animal_val', $id_animal_post, PDO::PARAM_INT);
        $stmt_val->bindParam(':id_usuario_val', $id_usuario_actual, PDO::PARAM_STR);
        $stmt_val->execute();
        if ($stmt_val->fetch()) {
            try {
                $sql = "INSERT INTO alimentacion (id_animal, tipo_alimento, cantidad_diaria, unidad_cantidad, frecuencia_alimentacion, fecha_registro_alimentacion, observaciones)
                        VALUES (:id_animal, :tipo_alimento, :cantidad_diaria, :unidad_cantidad, :frecuencia_alimentacion, :fecha_registro_alimentacion, :observaciones)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id_animal', $id_animal_post, PDO::PARAM_INT);
                $stmt->bindParam(':tipo_alimento', $tipo_alimento);
                $stmt->bindParam(':cantidad_diaria', $cantidad_diaria);
                $stmt->bindParam(':unidad_cantidad', $unidad_cantidad);
                $stmt->bindParam(':frecuencia_alimentacion', $frecuencia_alimentacion);
                $stmt->bindParam(':fecha_registro_alimentacion', $fecha_registro_alimentacion);
                $stmt->bindParam(':observaciones', $observaciones);

                if ($stmt->execute()) {
                    $mensaje = "¡Pauta de alimentación registrada exitosamente!";
                    // Limpiar id_animal_get para que el formulario no siga preseleccionado después de un POST exitoso
                    // y permitir registrar para otro animal si se desea, o volver a cargar la info del animal actual si se quedó en la pág.
                    if ($id_animal_get) {
                         //header("Location: ver_alimentacion.php?id_animal=" . $id_animal_get . "&mensaje_exito=1"); // Redirigir a ver historial
                         //exit();
                         // O recargar la info del animal si se queda en la misma página
                        $stmt_val_animal->execute(); // Volver a ejecutar para refrescar
                        $animal_seleccionado_info = $stmt_val_animal->fetch(PDO::FETCH_ASSOC);
                    }
                   
                } else {
                    $mensaje = "Error al registrar la pauta de alimentación.";
                }
            } catch (PDOException $e) {
                $mensaje = "Error en la base de datos: " . $e->getMessage();
            }
        } else {
            $mensaje = "Error: El animal seleccionado no es válido o no te pertenece.";
        }
    }
}

// Obtener lista de animales del usuario para el selector (si no se pasó un id_animal específico o si fue inválido)
$lista_animales_usuario = [];
if (!$id_animal_get) { // Solo cargar si no hay un animal válido de GET
    $stmt_animales = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal, cantidad FROM animales WHERE id_usuario = :id_usuario ORDER BY tipo_animal, nombre_animal");
    $stmt_animales->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_animales->execute();
    $lista_animales_usuario = $stmt_animales->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Alimentación</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
        .container { width: 90%; max-width: 700px; margin: 30px auto; background: #fff; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1, h2 { text-align: center; color: #4CAF50; margin-bottom: 15px; }
        h2 { font-size: 1.3em; color: #333; margin-top:0;}
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; color: #555; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box; 
            font-size: 16px;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group input[type="submit"] {
            background-color: #5cb85c; color: white; padding: 12px 20px; border: none;
            border-radius: 4px; cursor: pointer; font-size: 16px;
            transition: background-color 0.3s ease; width: 100%;
        }
        .form-group input[type="submit"]:hover { background-color: #4cae4c; }
        .mensaje { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-size: 0.95em; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        .back-link-container { text-align: center; margin-top: 25px; }
        .back-link { color: #337ab7; text-decoration: none; font-size: 0.9em; margin: 0 10px; }
        .back-link:hover { text-decoration: underline; }
        .animal-info { background-color: #e9f5e9; padding: 15px; border-radius: 5px; margin-bottom:20px; border-left: 4px solid #4CAF50;}
        .animal-info p { margin: 5px 0; color: #333; }
    </style>
</head>
<body>
    <?php // include '../header_app_interna.php'; // Cabecera si la tienes ?>
    <div class="container">
        <h1>Registrar Pauta de Alimentación</h1>

        <?php if ($animal_seleccionado_info): ?>
            <div class="animal-info">
                <h2>Para: <?php echo htmlspecialchars($animal_seleccionado_info['tipo_animal']); ?>
                    <?php echo !empty($animal_seleccionado_info['nombre_animal']) ? ' "' . htmlspecialchars($animal_seleccionado_info['nombre_animal']) . '"' : ''; ?>
                    (ID: <?php echo htmlspecialchars($animal_seleccionado_info['id_animal']); ?>
                    <?php if($animal_seleccionado_info['cantidad'] > 1) echo ", Lote de: ".htmlspecialchars($animal_seleccionado_info['cantidad']); ?>)
                </h2>
            </div>
        <?php endif; ?>


        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo (stripos($mensaje, 'exitosamente') !== false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . ($id_animal_get ? '?id_animal='.$id_animal_get : ''); ?>" method="POST">
            
            <?php if (!$id_animal_get && empty($animal_seleccionado_info)): // Mostrar selector si no hay animal por GET ?>
                <div class="form-group">
                    <label for="id_animal">Seleccionar Animal/Lote:</label>
                    <select name="id_animal" id="id_animal" required>
                        <option value="">-- Elija un animal/lote --</option>
                        <?php foreach ($lista_animales_usuario as $animal_opt): ?>
                            <option value="<?php echo $animal_opt['id_animal']; ?>">
                                <?php 
                                echo htmlspecialchars($animal_opt['tipo_animal']);
                                if (!empty($animal_opt['nombre_animal'])) echo ' "' . htmlspecialchars($animal_opt['nombre_animal']) . '"';
                                echo " (ID: " . $animal_opt['id_animal'];
                                if ($animal_opt['cantidad'] > 1) echo ", Lote de: " . $animal_opt['cantidad'];
                                echo ")";
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif($id_animal_get && $animal_seleccionado_info): // Si hay animal por GET, usar un campo oculto ?>
                <input type="hidden" name="id_animal" value="<?php echo $id_animal_get; ?>">
            <?php endif; ?>


            <div class="form-group">
                <label for="tipo_alimento">Tipo de Alimento (Ej: Concentrado Crecimiento, Pasto Fresco, Maíz Molido):</label>
                <input type="text" name="tipo_alimento" id="tipo_alimento" maxlength="100" required>
            </div>

            <div class="form-group">
                <label for="cantidad_diaria">Cantidad Diaria:</label>
                <input type="number" name="cantidad_diaria" id="cantidad_diaria" step="0.01" min="0.01" required>
            </div>

            <div class="form-group">
                <label for="unidad_cantidad">Unidad (Ej: kg, g, lb, raciones, litros):</label>
                <input type="text" name="unidad_cantidad" id="unidad_cantidad" value="kg" maxlength="20" required>
            </div>

            <div class="form-group">
                <label for="frecuencia_alimentacion">Frecuencia (Ej: 2 veces al día, Ad libitum, Mañana y Tarde):</label>
                <input type="text" name="frecuencia_alimentacion" id="frecuencia_alimentacion" maxlength="70" required>
            </div>
            
            <div class="form-group">
                <label for="fecha_registro_alimentacion">Fecha de Inicio de esta Pauta:</label>
                <input type="date" name="fecha_registro_alimentacion" id="fecha_registro_alimentacion" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label for="observaciones">Observaciones (Opcional):</label>
                <textarea name="observaciones" id="observaciones" maxlength="500"></textarea>
            </div>

            <div class="form-group">
                <input type="submit" value="Registrar Pauta de Alimentación">
            </div>
        </form>

        <div class="back-link-container">
            <?php if ($id_animal_get): ?>
                <a href="ver_alimentacion.php?id_animal=<?php echo $id_animal_get; ?>" class="back-link">Ver Historial de Alimentación</a>
            <?php endif; ?>
            <a href="mis_animales.php" class="back-link">Volver a Mis Animales</a>
        </div>
    </div>
</body>
</html>