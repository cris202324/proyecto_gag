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
    <title>Ver Usuarios</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .content {
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 20px;
            width: 300px;
            min-height: 150px;
            display: flex;
            flex-direction: column;
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
        .card small {
            font-size: 0.8em;
            color: #777;
            display: block;
            margin-top: auto;
        }
        .no-usuarios {
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
        <h2>Lista de Usuarios</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($usuarios)): ?>
            <div class="no-usuarios">
                <p>No hay usuarios registrados.</p>
            </div>
        <?php elseif (!empty($usuarios)): ?>
            <?php foreach ($usuarios as $usuario): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($usuario['nombre']); ?></h3>
                    <p>
                        <strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?><br>
                        <strong>Rol:</strong> <?php echo $usuario['id_rol'] == 2 ? 'Usuario' : 'Admin'; ?>
                    </p>
                    <small>
                        ID: <?php echo htmlspecialchars($usuario['id_usuario']); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>