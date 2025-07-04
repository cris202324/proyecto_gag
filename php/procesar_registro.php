<?php
// Iniciar sesión para poder manejar mensajes de error/éxito
session_start();

// Incluir la conexión a la base de datos
require_once 'conexion.php';

// Verificar que la solicitud sea por el método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Si no es POST, redirigir al formulario de registro
    header('Location: ../pages/auth/registro.php'); // Asegúrate que la ruta sea correcta
    exit();
}

// 1. Recoger y limpiar los datos del formulario
// Se usa trim() para eliminar espacios en blanco al inicio y al final
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$contrasena = isset($_POST['contrasena']) ? $_POST['contrasena'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

// 2. Realizar validaciones en el servidor
// Aunque JavaScript ya valida, la validación del servidor es la capa de seguridad principal.

// Validación 2.1: Verificar que no haya números en el nombre
if (preg_match('/[0-9]/', $nombre)) {
    $_SESSION['error_registro'] = "El nombre no puede contener números.";
    header('Location: ../pages/auth/registro.php');
    exit();
}

// Validación 2.2: Verificar campos obligatorios
if (empty($nombre) || empty($email) || empty($contrasena)) {
    $_SESSION['error_registro'] = "Por favor, complete todos los campos obligatorios.";
    header('Location: ../pages/auth/registro.php');
    exit();
}

// Validación 2.3: Validar formato de email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_registro'] = "El formato del correo electrónico no es válido.";
    header('Location: ../pages/auth/registro.php');
    exit();
}

// Validación 2.4: Validar longitud de la contraseña
if (strlen($contrasena) < 8) {
    $_SESSION['error_registro'] = "La contraseña debe tener al menos 8 caracteres.";
    header('Location: ../pages/auth/registro.php');
    exit();
}

// Validación 2.5: Validar que las contraseñas coincidan
if ($contrasena !== $confirm_password) {
    $_SESSION['error_registro'] = "Las contraseñas no coinciden.";
    header('Location: ../pages/auth/registro.php');
    exit();
}


// 3. Procesar el registro si todas las validaciones pasan
try {
    // 3.1: Verificar si el email ya está registrado
    $stmt_check_email = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email");
    $stmt_check_email->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt_check_email->execute();

    if ($stmt_check_email->rowCount() > 0) {
        $_SESSION['error_registro'] = "Este correo electrónico ya está en uso.";
        header('Location: ../pages/auth/registro.php');
        exit();
    }

    // 3.2: Generar un ID de usuario único (tu lógica actual es buena)
    do {
        $id_usuario = 'USR' . rand(1000, 9999);
        $stmt_check_id = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = :id_usuario");
        $stmt_check_id->bindParam(':id_usuario', $id_usuario, PDO::PARAM_STR);
        $stmt_check_id->execute();
    } while ($stmt_check_id->rowCount() > 0);

    // 3.3: Hashear la contraseña para almacenamiento seguro
    $contrasena_hashed = password_hash($contrasena, PASSWORD_DEFAULT);

    // 3.4: Preparar la inserción en la base de datos
    $id_rol = 2; // Rol de 'usuario' por defecto
    $id_estado = 1; // Estado 'activo' por defecto

    $sql = "INSERT INTO usuarios (id_usuario, nombre, email, contrasena, id_rol, id_estado) 
            VALUES (:id_usuario, :nombre, :email, :contrasena, :id_rol, :id_estado)";
    
    $stmt_insert = $pdo->prepare($sql);
    $stmt_insert->bindParam(':id_usuario', $id_usuario, PDO::PARAM_STR);
    $stmt_insert->bindParam(':nombre', $nombre, PDO::PARAM_STR);
    $stmt_insert->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt_insert->bindParam(':contrasena', $contrasena_hashed, PDO::PARAM_STR);
    $stmt_insert->bindParam(':id_rol', $id_rol, PDO::PARAM_INT);
    $stmt_insert->bindParam(':id_estado', $id_estado, PDO::PARAM_INT);

    // 3.5: Ejecutar la inserción y redirigir
    if ($stmt_insert->execute()) {
        // Registro exitoso
        $_SESSION['registro_exitoso'] = "¡Cuenta creada con éxito! Ya puedes iniciar sesión.";
        header('Location: ../pages/auth/login.html'); // Redirigir a la página de login
        exit();
    } else {
        // Error inesperado durante la inserción
        $_SESSION['error_registro'] = "No se pudo crear la cuenta. Por favor, inténtelo de nuevo.";
        header('Location: ../pages/auth/registro.php');
        exit();
    }

} catch (PDOException $e) {
    // Manejo de errores de base de datos
    $_SESSION['error_registro'] = "Error de conexión con la base de datos. Inténtelo más tarde.";
    // Para depuración, puedes guardar el error real en un archivo de log
    // error_log("Error en procesar_registro.php: " . $e->getMessage());
    header('Location: ../pages/auth/registro.php');
    exit();
}
?>