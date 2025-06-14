<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
header('Content-Type: text/html; charset=UTF-8');
require_once 'conexion.php';
require '../vendor/autoload.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

if (!isset($_POST['email']) || empty(trim($_POST['email']))) {
    echo '<script>alert("Por favor, ingrese un correo electrónico.");</script>';
    echo '<script>window.location = "../pages/auth/login.html";</script>';
    exit();
}

$email = trim($_POST['email']);

try {
    $check = $pdo->prepare("SELECT `id_usuario`, `email` FROM `usuarios` WHERE `email` = :email");
    $check->bindParam(':email', $email, PDO::PARAM_STR);
    $check->execute();

    if ($check->rowCount() > 0) {
        $user = $check->fetch(PDO::FETCH_ASSOC);

        // Generar token único
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Limpiar tokens anteriores
        $clear = $pdo->prepare("UPDATE `usuarios` SET `token_recuperacion` = NULL, `token_expiracion` = NULL, `token_usado` = 0 WHERE `email` = :email");
        $clear->bindParam(':email', $email, PDO::PARAM_STR);
        $clear->execute();

        // Actualizar con nuevo token
        $update = $pdo->prepare("UPDATE `usuarios` SET `token_recuperacion` = :token, `token_expiracion` = :expiracion, `token_usado` = 0 WHERE `email` = :email");
        $update->bindParam(':token', $token, PDO::PARAM_STR);
        $update->bindParam(':expiracion', $expiracion, PDO::PARAM_STR);
        $update->bindParam(':email', $email, PDO::PARAM_STR);

        if ($update->execute()) {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'davidfernandoballares@gmail.com';
            $mail->Password = 'rtanshtovelxpcoo';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('davidfernandoballares@gmail.com', 'Soporte GAG');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Recuperación de Contraseña';
            $resetLink = "http://localhost/proyecto_gag/php/change_password.php?token=" . urlencode($token);
            $mail->Body = "Hola, has solicitado recuperar tu contraseña. Haz clic aquí para restablecerla: <a href='$resetLink'>Restablecer Contraseña</a>";
            $mail->AltBody = "Has solicitado recuperar tu contraseña. Visita este enlace para restablecerla: $resetLink";

            $mail->send();
            echo '<script>alert("Correo de recuperación enviado con éxito.");</script>';
            echo '<script>window.location = "../pages/auth/login.html?message=ok";</script>';
        } else {
            echo '<script>alert("Error al generar el token.");</script>';
            echo '<script>window.location = "../pages/auth/login.html";</script>';
        }
    } else {
        echo '<script>alert("El correo no está registrado.");</script>';
        echo '<script>window.location = "../pages/auth/login.html";</script>';
    }
} catch (PDOException $e) {
    echo '<script>alert("Error de base de datos: ' . addslashes($e->getMessage()) . '");</script>';
    echo '<script>window.location = "../pages/auth/login.html";</script>';
}
?>