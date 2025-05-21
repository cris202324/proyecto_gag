<?php
// conexion.php
$host = '127.0.0.1'; // o localhost
$db   = 'gag';
$user = 'root'; // Tu usuario de MySQL/MariaDB
$pass = '';     // Tu contraseña de MySQL/MariaDB
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Es buena idea loguear el error o mostrar un mensaje genérico en producción
     // error_log("Error de conexión PDO: " . $e->getMessage());
     // die("Error al conectar con la base de datos. Por favor, intente más tarde.");
     // Por ahora, para desarrollo, relanzar la excepción puede ser útil para ver el error completo.
     throw new \PDOException("Error de conexión PDO en conexion.php: " . $e->getMessage(), (int)$e->getCode());
}
?>