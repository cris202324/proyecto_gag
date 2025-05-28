<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    header("Location: ../login.html");
    exit();
}

include 'conexion.php';

$usuarios = [];
$mensaje_error = '';
$mensaje_success = '';

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        $sql = "SELECT id_usuario, nombre, email, id_rol FROM usuarios WHERE id_rol != 1 ORDER BY id_usuario";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los usuarios: " . $e->getMessage();
    }

    // Procesar edición o eliminación
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['edit_id'])) {
            $id = $_POST['edit_id'];
            $nombre = $_POST['nombre'];
            $email = $_POST['email'];
            $rol = $_POST['rol'];

            $update = $pdo->prepare("UPDATE usuarios SET nombre = :nombre, email = :email, id_rol = :rol WHERE id_usuario = :id");
            $update->bindParam(':id', $id, PDO::PARAM_INT);
            $update->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $update->bindParam(':email', $email, PDO::PARAM_STR);
            $update->bindParam(':rol', $rol, PDO::PARAM_INT);

            if ($update->execute()) {
                $mensaje_success = "Usuario actualizado con éxito.";
            } else {
                $mensaje_error = "Error al actualizar el usuario.";
            }
        } elseif (isset($_POST['delete_id'])) {
            $id = $_POST['delete_id'];
            $delete = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
            $delete->bindParam(':id', $id, PDO::PARAM_INT);

            if ($delete->execute()) {
                $mensaje_success = "Usuario eliminado con éxito.";
            } else {
                $mensaje_error = "Error al eliminar el usuario.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Gestionar Usuarios</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .content {
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 20px;
            width: 100%;
            max-width: 300px;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        .card h3 {
            margin-top: 0;
            color: #333;
        }
        .card p {
            font-size: 0.9em;
            color: #555;
            margin-bottom: 8px;
            flex-grow: 1;
        }
        .card form {
            margin-top: 10px;
        }
        .card input, .card select {
            width: 100%;
            padding: 5px;
            margin-bottom: 5px;
            box-sizing: border-box;
        }
        .card button, .card input[type="submit"] {
            width: 100%;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 5px;
        }
        .card button:hover {
            background-color: #c9302c;
        }
        .card input[type="submit"]:hover {
            background-color: #4cae4c;
        }
        .no-animales {
            text-align: center;
            width: 100%;
            padding: 30px;
            font-size: 1.2em;
            color: #777;
        }
        .error-message {
            color: red;
            text-align: center;
            width: 100%;
            padding: 15px;
            background-color: #fdd;
            border: 1px solid #fbb;
        }
        .success-message {
            color: green;
            text-align: center;
            width: 100%;
            padding: 15px;
            background-color: #dff0d8;
            border: 1px solid #d6e9c6;
        }
        .form-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        .form-section h4 {
            margin-top: 0;
            color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="logo" />
        </div>
        <div class="menu">
            <a href="admin_dashboard.php" class="active">Inicio</a>
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="view_all_animals.php">Ver Animales</a>
            <a href="manage_users.php">Gestionar Usuarios</a>
            <a href="manage_animals.php">Gestionar Animales</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </div>
    </div>

    <div class="content">
        <h2>Gestionar Usuarios</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_success)): ?>
            <p class="success-message"><?php echo htmlspecialchars($mensaje_success); ?></p>
        <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($usuarios)): ?>
            <div class="no-usuarios">
                <p>No hay usuarios para gestionar.</p>
            </div>
        <?php elseif (!empty($usuarios)): ?>
            <?php foreach ($usuarios as $usuario): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($usuario['nombre']); ?></h3>
                    <p>
                        <strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?><br>
                        <strong>Rol:</strong> <?php echo $usuario['id_rol'] == 2 ? 'Usuario' : 'Admin'; ?>
                    </p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="edit_id" value="<?php echo $usuario['id_usuario']; ?>">
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                        <select name="rol">
                            <option value="2" <?php echo $usuario['id_rol'] == 2 ? 'selected' : ''; ?>>Usuario</option>
                            <option value="1" <?php echo $usuario['id_rol'] == 1 ? 'selected' : ''; ?>>Admin</option>
                        </select>
                        <input type="submit" value="Guardar">
                    </form>
                    <form method="POST" style="display: inline; margin-left: 10px;">
                        <input type="hidden" name="delete_id" value="<?php echo $usuario['id_usuario']; ?>">
                        <button type="submit" onclick="return confirm('¿Seguro que quieres eliminar a <?php echo addslashes($usuario['nombre']); ?>?');">Eliminar</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>