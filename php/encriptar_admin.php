<?php
// Generar un hash para la contraseña del administrador
$contraseña = "adminpassword"; // Reemplaza con la contraseña que ya tienes
$hash = password_hash($contraseña, PASSWORD_DEFAULT);
echo "Contraseña encriptada: $hash";
?>


