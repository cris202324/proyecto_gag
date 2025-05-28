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

$cultivos = [];
$mensaje_error = '';

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        $sql = "SELECT
                    c.id_cultivo,
                    c.fecha_inicio,
                    c.fecha_fin AS fecha_fin_registrada,
                    c.area_hectarea,
                    tc.nombre_cultivo,
                    tc.tiempo_estimado_frutos,
                    m.nombre AS nombre_municipio,
                    u.nombre AS nombre_usuario
                FROM cultivos c
                JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                JOIN municipio m ON c.id_municipio = m.id_municipio
                JOIN usuarios u ON c.id_usuario = u.id_usuario
                ORDER BY c.fecha_inicio DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $cultivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los cultivos: " . $e->getMessage();
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
    <title>Ver Cultivos</title>
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
        .no-cultivos {
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
        <h2>Todos los Cultivos Registrados</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($cultivos)): ?>
            <div class="no-cultivos">
                <p>No hay cultivos registrados.</p>
            </div>
        <?php elseif (!empty($cultivos)): ?>
            <?php foreach ($cultivos as $cultivo): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($cultivo['nombre_cultivo']); ?> (<?php echo htmlspecialchars($cultivo['nombre_usuario']); ?>)</h3>
                    <p>
                        <strong>Inicio:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_inicio']))); ?><br>
                        <strong>Área:</strong> <?php echo htmlspecialchars($cultivo['area_hectarea']); ?> ha<br>
                        <strong>Municipio:</strong> <?php echo htmlspecialchars($cultivo['nombre_municipio']); ?>
                    </p>
                    <small>
                        ID Cultivo: <?php echo htmlspecialchars($cultivo['id_cultivo']); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>