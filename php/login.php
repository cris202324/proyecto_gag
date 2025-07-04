<?php
session_start();
require_once 'conexion.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

if (!isset($_POST['email']) || !isset($_POST['contrasena'])) {
    echo "<script>
            alert('Por favor, complete todos los campos.');
            window.location.href='http://localhost/proyecto_gag/pages/auth/login.html';
          </script>";
    exit();
}

$email = trim($_POST['email']);
$contrasena = trim($_POST['contrasena']);

try {
    $sql = "SELECT `id_usuario`, `nombre`, `contrasena`, `id_rol` FROM `usuarios` WHERE `email` = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        if (password_verify($contrasena, $result['contrasena'])) {
            $_SESSION['usuario'] = $result['nombre'];
            $_SESSION['id_usuario'] = $result['id_usuario'];
            $_SESSION['rol'] = $result['id_rol'];
            
            if ($result['id_rol'] == 3) {
                echo "<script>
                        alert('Inicio de sesión exitoso. Bienvenido, " . addslashes($result['nombre']) . " (super admin)!');
                        window.location.href='http://localhost/proyecto_gag/php/super/index.php';
                      </script>";
            }
            if ($result['id_rol'] == 2) {
                echo "<script>
                        alert('Inicio de sesión exitoso. Bienvenido, " . addslashes($result['nombre']) . " (usuario)!');
                        window.location.href='http://localhost/proyecto_gag/php/index.php';
                      </script>";
            } elseif ($result['id_rol'] == 1) {
                echo "<script>
                        alert('Inicio de sesión exitoso. Bienvenido, " . addslashes($result['nombre']) . "(admin)!');
                        window.location.href='http://localhost/proyecto_gag/php/index.php';
                      </script>";
            } else {
                echo "<script>
                        alert('Rol no reconocido. Contacta al administrador.');
                        window.location.href='http://localhost/proyecto_gag/pages/auth/login.html';
                      </script>";
            }
        } else {
            echo "<script>
                    alert('Contraseña incorrecta. Inténtelo de nuevo.');
                    window.location.href='http://localhost/proyecto_gag/pages/auth/login.html';
                  </script>";
        }
    } else {
        echo "<script>
                alert('No se encontró un usuario con ese correo.');
                window.location.href='http://localhost/proyecto_gag/pages/auth/login.html';
              </script>";
    }
} catch (PDOException $e) {
    echo "<script>
            alert('Error de base de datos: " . addslashes($e->getMessage()) . "');
            window.location.href='http://localhost/proyecto_gag/pages/auth/login.html';
          </script>";
}
?>