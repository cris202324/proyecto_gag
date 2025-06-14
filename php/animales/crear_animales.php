<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

include '../conexion.php'; // Asegúrate que $pdo esté definido aquí

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = $_SESSION['id_usuario'];
    $nombre_animal = trim($_POST['nombre_animal']);
    $tipo_animal = trim($_POST['tipo_animal']);
    $raza = !empty(trim($_POST['raza'])) ? trim($_POST['raza']) : null;
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $sexo = $_POST['sexo'];
    $identificador_unico = !empty(trim($_POST['identificador_unico'])) ? trim($_POST['identificador_unico']) : null;

    // Validación básica
    if (empty($tipo_animal)) {
        $mensaje = "El tipo de animal es obligatorio.";
    } else {
        try {
            $sql = "INSERT INTO animales (id_usuario, nombre_animal, tipo_animal, raza, fecha_nacimiento, sexo, identificador_unico)
                    VALUES (:id_usuario, :nombre_animal, :tipo_animal, :raza, :fecha_nacimiento, :sexo, :identificador_unico)";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':nombre_animal', $nombre_animal);
            $stmt->bindParam(':tipo_animal', $tipo_animal);
            $stmt->bindParam(':raza', $raza);
            $stmt->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $stmt->bindParam(':sexo', $sexo);
            $stmt->bindParam(':identificador_unico', $identificador_unico);

            if ($stmt->execute()) {
                $mensaje = "¡Animal registrado exitosamente!";
            } else {
                $mensaje = "Error al registrar el animal.";
            }
        } catch (PDOException $e) {
            $mensaje = "Error en la base de datos: " . $e->getMessage();
            // error_log("Error en crearanimal.php: " . $e->getMessage());
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
    <link rel="stylesheet" href="../css/estilos.css"> <!-- Ajusta la ruta si es necesario -->
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { width: 60%; margin: 50px auto; background: #fff; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group input[type="submit"] {
            background-color: #5cb85c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .form-group input[type="submit"]:hover { background-color: #4cae4c; }
        .mensaje { padding: 10px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #337ab7; text-decoration: none;}
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php // include 'header_app.php'; // Si tienes una cabecera común para la app ?>

    <div class="container">
        <h1>Registrar Nuevo Animal</h1>

        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo (stripos($mensaje, 'exitosamente') !== false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div class="form-group">
                <label for="nombre_animal">Nombre / Identificador del Animal :</label>
                <input type="text" name="nombre_animal" id="nombre_animal" maxlength="100">
            </div>

            <div class="form-group">
                <label for="tipo_animal">Tipo de Animal (Ej: Vaca, Pollo, Cerdo):</label>
                <input type="text" name="tipo_animal" id="tipo_animal" maxlength="50" required>
            </div>

            <div class="form-group">
                <label for="raza">Raza (Opcional):</label>
                <input type="text" name="raza" id="raza" maxlength="50">
            </div>

            <div class="form-group">
                <label for="fecha_nacimiento">Fecha de Nacimiento (Opcional):</label>
                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento">
            </div>

            <div class="form-group">
                <label for="sexo">Sexo:</label>
                <select name="sexo" id="sexo">
                    <option value="Desconocido">Desconocido</option>
                    <option value="Macho">Macho</option>
                    <option value="Hembra">Hembra</option>
                </select>
            </div>

            <div class="form-group">
                <label for="identificador_unico">Identificador Único Adicional (Ej: Arete, Chip) (Opcional):</label>
                <input type="text" name="identificador_unico" id="identificador_unico" maxlength="50">
            </div>

            <div class="form-group">
                <input type="submit" value="Registrar Animal">
            </div>
        </form>
        <a href="../index.php" class="back-link">Volver al Panel</a> <!-- O a mis_animales.php -->
    </div>
</body>
</html>