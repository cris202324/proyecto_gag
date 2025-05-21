<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: http://localhost/proyecto/login.html");
    exit();
}

// Determinar el rol del usuario
$is_admin = ($_SESSION['rol'] == 1);
$title = $is_admin ? "Interfaz Admin" : "Panel de Usuario";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo" />
        </div>
        <div class="menu">
            <a href="#" class="active">Inicio</a>
            <a href="miscultivos.php">Mis cultivos</a>
            <a href="animales/mis_animales.php">Mis animales</a>
            <a href="calendario.php">Calendario y horarios</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php">Cerrar sesión</a>
        </div>
    </div>
    <div class="content">
        <a href="crearcultivos.php" class="card">Nuevos cultivos</a>
        <a href="animales/crear_animales.php" class="card">Nuevos animales</a>
        <a href="calendario.php" class="card">Ver calendario</a>
        <a href="configuracion.php" class="card">Configuración</a>
        <a href="ayuda.php" class="card">Ayuda</a>
    </div>
</body>
</html>