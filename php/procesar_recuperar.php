<?php
session_start();
include 'conexion.php';

// Incluir PHPMailer
$autoloadPath = '../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('Error: No se encontró vendor/autoload.php. Asegúrate de haber instalado PHPMailer con Composer.');
}
require $autoloadPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Paso 1: Generar token de recuperación (basado en email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = $_POST['email'];

    // Validar que el email existe en la base de datos
    $query = $conexion->prepare("SELECT * FROM usuarios WHERE email = :email");
    $query->bindParam(':email', $email);
    $query->execute();

    if ($query->rowCount() > 0) {
        // Generar un token único
        $token = bin2hex(random_bytes(32));

        // Actualizar el token en la base de datos
        $updateQuery = $conexion->prepare("UPDATE usuarios SET token_recuperacion = :token WHERE email = :email");
        $updateQuery->bindParam(':token', $token);
        $updateQuery->bindParam(':email', $email);
        $updateQuery->execute();

        // Configurar PHPMailer
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 2; // Habilita depuración
        $mail->Debugoutput = 'html'; // Muestra salida en HTML

        try {
            // Configuración del servidor SMTP (Mailtrap)
            $mail->isSMTP();
            $mail->Host = 'smtp.mailtrap.io';
            $mail->SMTPAuth = true;
            $mail->Username = 'tu_usuario_mailtrap'; // Reemplaza con tu Usuario de Mailtrap
            $mail->Password = 'tu_contraseña_mailtrap'; // Reemplaza con tu Contraseña de Mailtrap
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 2525;

            // Remitente y destinatario
            $mail->setFrom('no-reply@gag-tolima.com', 'GAG Tolima');
            $mail->addAddress($email);

            // Contenido del correo
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $resetLink = "$baseUrl/proyecto/php/procesar_recuperar.php?token=" . $token;
            $asunto = "Recuperación de Contraseña - GAG Tolima";
            $mensaje = "Hola,\n\nHemos recibido una solicitud para restablecer tu contraseña. Haz clic en el enlace a continuación para restablecerla:\n\n$resetLink\n\nSi no solicitaste esto, ignora este correo.\n\nSaludos,\nEquipo GAG Tolima";

            $mail->isHTML(false);
            $mail->Subject = $asunto;
            $mail->Body = $mensaje;

            // Enviar el correo
            $mail->send();
            echo "<script>
                    alert('Se ha enviado un enlace de recuperación a tu correo.');
                    window.location.href='http://localhost/proyecto/login.html';
                  </script>";
        } catch (Exception $e) {
            echo "<script>
                    alert('Error al enviar el correo: " . addslashes($mail->ErrorInfo) . "');
                    window.location.href='http://localhost/proyecto/recuperar_contraseña.html';
                  </script>";
        }
    } else {
        echo "<script>
                alert('El correo electrónico no está registrado.');
                window.location.href='http://localhost/proyecto/recuperar_contraseña.html';
              </script>";
    }
} elseif (isset($_GET['token'])) {
    // Paso 2: Manejar la solicitud de restablecimiento con el token
    $token = $_GET['token'];

    // Verificar si el token existe en la base de datos
    $query = $conexion->prepare("SELECT * FROM usuarios WHERE token_recuperacion = :token");
    $query->bindParam(':token', $token);
    $query->execute();

    if ($query->rowCount() > 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
            // Restablecer la contraseña
            $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $updateQuery = $conexion->prepare("UPDATE usuarios SET contraseña = :password, token_recuperacion = NULL WHERE token_recuperacion = :token");
            $updateQuery->bindParam(':password', $new_password);
            $updateQuery->bindParam(':token', $token);
            $updateQuery->execute();

            if ($updateQuery->rowCount() > 0) {
                echo "<script>
                        alert('Contraseña restablecida correctamente.');
                        window.location.href='http://localhost/proyecto/login.html';
                      </script>";
            } else {
                echo "<script>
                        alert('Error al restablecer la contraseña.');
                        window.location.href='http://localhost/proyecto/recuperar_contraseña.html';
                      </script>";
            }
        } else {
            // Mostrar formulario para nueva contraseña
            ?>
            <form method="POST" action="">
                <label for="new_password">Nueva contraseña:</label>
                <input type="password" name="new_password" required>
                <input type="hidden" name="token" value="<?php echo $token; ?>">
                <button type="submit">Restablecer contraseña</button>
            </form>
            <?php
        }
    } else {
        echo "<script>
                alert('Token no válido o expirado.');
                window.location.href='http://localhost/proyecto/login.html';
              </script>";
    }
} else {
    echo "<script>
            alert('Solicitud no válida.');
            window.location.href='http://localhost/proyecto/recuperar_contraseña.html';
          </script>";
}
?>