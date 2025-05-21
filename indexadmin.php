<?php
session_start(); 

if (isset($_SESSION['usuario'])) {
   
    header("Location: admin.php"); 
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <h1>Sistema de Gestión Agrícola y Ganadera</h1>
    <form method="POST" action="php/login.php">
        <label>Email:</label>
        <input type="email" name="email" required><br>
        <label>Contraseña:</label>
        <input type="password" name="password" required><br>
        <button type="submit">Iniciar Sesión</button>
    </form>

    
    <p>¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
</body>
<!-- esto tambien era como un borrador que hize en el sena, pero como usted ya lo hizo creo que da igual -->
</html>

