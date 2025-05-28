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

$animales = [];
$mensaje_error = '';

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        $sql = "SELECT 
                    a.id_animal,
                    a.nombre_animal,
                    a.tipo_animal,
                    a.raza,
                    a.fecha_nacimiento,
                    a.sexo,
                    a.identificador_unico,
                    a.fecha_registro,
                    u.nombre AS nombre_usuario
                FROM animales a
                JOIN usuarios u ON a.id_usuario = u.id_usuario
                ORDER BY a.fecha_registro DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $animales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los animales: " . $e->getMessage();
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
    <title>Ver Animales</title>
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
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="logo" />
        </div>
        <div class="menu">
            <a href="admin_dashboard.php">Inicio</a>
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="view_all_animals.php" class="active">Ver Animales</a>
            <a href="manage_users.php">Gestionar Usuarios</a>
            <a href="manage_animals.php">Gestionar Animales</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </div>
    </div>

    <div class="content">
        <h2>Todos los Animales Registrados</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($animales)): ?>
            <div class="no-animales">
                <p>No hay animales registrados.</p>
            </div>
        <?php elseif (!empty($animales)): ?>
            <?php foreach ($animales as $animal): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($animal['nombre_animal']); ?> (<?php echo htmlspecialchars($animal['nombre_usuario']); ?>)</h3>
                    <p>
                        <strong>Tipo:</strong> <?php echo htmlspecialchars($animal['tipo_animal']); ?><br>
                        <strong>Raza:</strong> <?php echo htmlspecialchars($animal['raza'] ?? 'No especificada'); ?><br>
                        <strong>Sexo:</strong> <?php echo htmlspecialchars($animal['sexo']); ?><br>
                        <strong>Fecha Nacimiento:</strong> <?php echo $animal['fecha_nacimiento'] ? htmlspecialchars(date("d/m/Y", strtotime($animal['fecha_nacimiento']))) : 'No especificada'; ?>
                    </p>
                    <small>
                        ID Animal: <?php echo htmlspecialchars($animal['id_animal']); ?><br>
                        Registrado: <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($animal['fecha_registro']))); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>