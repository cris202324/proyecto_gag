<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php"); // Asegúrate que esta ruta es correcta
    exit();
}

include '../../conexion.php'; // Asegúrate que $pdo esté definido aquí

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = $_SESSION['id_usuario'];
    $nombre_animal = trim($_POST['nombre_animal']);
    $tipo_animal = trim($_POST['tipo_animal']);
    $raza = !empty(trim($_POST['raza'])) ? trim($_POST['raza']) : null;
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $sexo = $_POST['sexo'];
    $identificador_unico = !empty(trim($_POST['identificador_unico'])) ? trim($_POST['identificador_unico']) : null;
    $cantidad = isset($_POST['cantidad']) && is_numeric($_POST['cantidad']) && $_POST['cantidad'] > 0 ? (int)$_POST['cantidad'] : 1; // Nuevo campo

    // Validación básica
    if (empty($tipo_animal)) {
        $mensaje = "El tipo de animal es obligatorio.";
    } elseif ($cantidad <= 0) {
        $mensaje = "La cantidad debe ser un número positivo.";
    } else {
        try {
            $sql = "INSERT INTO animales (id_usuario, nombre_animal, tipo_animal, raza, fecha_nacimiento, sexo, identificador_unico, cantidad)
                    VALUES (:id_usuario, :nombre_animal, :tipo_animal, :raza, :fecha_nacimiento, :sexo, :identificador_unico, :cantidad)"; // Añadido :cantidad
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':nombre_animal', $nombre_animal);
            $stmt->bindParam(':tipo_animal', $tipo_animal);
            $stmt->bindParam(':raza', $raza);
            $stmt->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $stmt->bindParam(':sexo', $sexo);
            $stmt->bindParam(':identificador_unico', $identificador_unico);
            $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT); // Nuevo bindParam

            if ($stmt->execute()) {
                if ($cantidad == 1) {
                    $mensaje = "¡Animal registrado exitosamente!";
                } else {
                    $mensaje = "¡Lote de " . htmlspecialchars($cantidad) . " animales registrado exitosamente!";
                }
            } else {
                $mensaje = "Error al registrar el animal/lote.";
            }
        } catch (PDOException $e) {
            $mensaje = "Error en la base de datos: " . $e->getMessage();
            // Para depuración, puedes descomentar esto en desarrollo:
            // error_log("Error en crearanimal.php al insertar: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Nuevo Animal</title>
    <!-- <link rel="stylesheet" href="../css/estilos.css"> --> <!-- Ajusta la ruta si es necesario -->
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
        .container { width: 90%; max-width: 600px; margin: 30px auto; background: #fff; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { text-align: center; color: #4CAF50; margin-bottom: 25px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; color: #555; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="number"], /* Para cantidad */
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box; /* Importante para que el padding no afecte el width */
            font-size: 16px;
        }
        .form-group input[type="submit"] {
            background-color: #5cb85c;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            width: 100%; /* Botón ocupa todo el ancho */
        }
        .form-group input[type="submit"]:hover { background-color: #4cae4c; }
        .mensaje { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-size: 0.95em; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        .back-link { display: block; text-align: center; margin-top: 25px; color: #337ab7; text-decoration: none; font-size: 0.9em;}
        .back-link:hover { text-decoration: underline; }
        
        /* Pequeñas mejoras de responsividad */
        @media (max-width: 768px) {
            .container {
                width: 95%;
                margin: 20px auto;
                padding: 20px;
            }
            h1 {
                font-size: 1.5em;
            }
        }
    </style>
</head>
<body>
    <?php // include 'header_app.php'; // Si tienes una cabecera común para la app ?>

    <div class="container">
        <h1>Registrar Nuevo Animal</h1>

        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo (stripos($mensaje, 'exitosamente') !== false || stripos($mensaje, 'Lote') !== false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div class="form-group">
                <label for="nombre_animal">Nombre / Identificador del Lote (Opcional si Cantidad > 1):</label>
                <input type="text" name="nombre_animal" id="nombre_animal" maxlength="100">
            </div>

            <div class="form-group">
                <label for="tipo_animal">Tipo de Animal (Ej: Vaca, Pollo, Cerdo):</label>
                <input type="text" name="tipo_animal" id="tipo_animal" maxlength="50" required>
            </div>

            <div class="form-group">
                <label for="cantidad">Cantidad:</label>
                <input type="number" name="cantidad" id="cantidad" value="1" min="1" required>
            </div>

            <div class="form-group">
                <label for="raza">Raza (Opcional):</label>
                <input type="text" name="raza" id="raza" maxlength="50">
            </div>

            <div class="form-group">
                <label for="fecha_nacimiento">Fecha de Nacimiento (Opcional, aplicar con cuidado si Cantidad > 1):</label>
                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento">
            </div>

            <div class="form-group">
                <label for="sexo">Sexo (Aplicar con cuidado si Cantidad > 1):</label>
                <select name="sexo" id="sexo">
                    <option value="Desconocido" selected>Desconocido / Mixto</option>
                    <option value="Macho">Macho</option>
                    <option value="Hembra">Hembra</option>
                </select>
            </div>

            <div class="form-group">
                <label for="identificador_unico">Identificador Único del Lote o Animal (Opcional):</label>
                <input type="text" name="identificador_unico" id="identificador_unico" maxlength="50">
            </div>

            <div class="form-group">
                <input type="submit" value="Registrar Animal/Lote">
            </div>
        </form>
        <a href="mis_animales.php" class="back-link">Volver a Mis Animales</a>
        <!-- O podrías cambiar el enlace de volver: <a href="../index.php" class="back-link">Volver al Panel</a> -->
    </div>
</body>
</html>