<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['nombre']) || !isset($_POST['email']) || !isset($_POST['contrasena'])) {
        echo "<script>
                alert('Por favor, complete todos los campos.');
                window.location.href='http://localhost/proyecto/registro.html';
              </script>";
        exit();
    }

    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $contrasena = password_hash(trim($_POST['contrasena']), PASSWORD_DEFAULT);
    $id_rol = 2;
    $id_estado = 1;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>
                alert('Por favor, ingrese un email válido.');
                window.location.href='http://localhost/proyecto/registro.html';
              </script>";
        exit();
    }

    try {
        $check = $pdo->prepare("SELECT `email` FROM `usuarios` WHERE `email` = :email");
        $check->bindParam(':email', $email, PDO::PARAM_STR);
        $check->execute();

        if ($check->rowCount() > 0) {
            echo "<script>
                    alert('El email ya está registrado.');
                    window.location.href='http://localhost/proyecto/registro.html';
                  </script>";
        } else {
            do {
                $id_usuario = 'USR' . rand(1000, 9999);
                $check_id = $pdo->prepare("SELECT `id_usuario` FROM `usuarios` WHERE `id_usuario` = :id");
                $check_id->bindParam(':id', $id_usuario, PDO::PARAM_STR);
                $check_id->execute();
            } while ($check_id->rowCount() > 0);

            $sql = "INSERT INTO `usuarios` (`id_usuario`, `nombre`, `email`, `contrasena`, `id_rol`, `id_estado`) VALUES (:id, :nombre, :email, :contrasena, :rol, :estado)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id_usuario, PDO::PARAM_STR);
            $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':contrasena', $contrasena, PDO::PARAM_STR);
            $stmt->bindParam(':rol', $id_rol, PDO::PARAM_INT);
            $stmt->bindParam(':estado', $id_estado, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo "<script>
                        alert('Registro exitoso. Ahora puedes iniciar sesión.');
                        window.location.href='http://localhost/proyecto/login.html';
                      </script>";
            } else {
                echo "<script>
                        alert('Error al registrar el usuario. Verifica los datos o contacta al soporte. Detalle: ' + " . json_encode($stmt->errorInfo()) . ");
                        window.location.href='http://localhost/proyecto/registro.html';
                      </script>";
            }
        }
    } catch (PDOException $e) {
        echo "<script>
                alert('Error de base de datos: " . addslashes($e->getMessage()) . "');
                window.location.href='http://localhost/proyecto/registro.html';
              </script>";
    }
} else {
    echo "<script>
            alert('Método no permitido.');
            window.location.href='http://localhost/proyecto/registro.html';
          </script>";
}
?>