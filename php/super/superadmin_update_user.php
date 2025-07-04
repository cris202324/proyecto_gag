<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Petición inválida.'];

if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['rol']) || $_SESSION['rol'] != 3) {
    $response['message'] = 'Acceso denegado. No tienes permisos de superadministrador.';
    echo json_encode($response);
    exit();
}

require_once '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id_to_edit = $_POST['user_id'] ?? null;
    $new_name = isset($_POST['user_name']) ? trim($_POST['user_name']) : null; // NUEVO
    $new_email = isset($_POST['user_email']) ? trim($_POST['user_email']) : null; // NUEVO
    $new_role_id = $_POST['role_id'] ?? null;
    $new_status_id = $_POST['status_id'] ?? null;
    $new_password = $_POST['new_password'] ?? null;

    // --- VALIDACIONES ---
    if (empty($user_id_to_edit) || empty($new_name) || empty($new_email) || empty($new_role_id) || empty($new_status_id)) {
        $response['message'] = 'Faltan datos requeridos (ID, nombre, email, rol o estado).';
        echo json_encode($response);
        exit();
    }

    // Validación anti-números para el nombre
    if (preg_match('/[0-9]/', $new_name)) {
        $response['message'] = 'El nombre no puede contener números.';
        echo json_encode($response);
        exit();
    }

    // Validación de formato de email
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'El formato del correo electrónico no es válido.';
        echo json_encode($response);
        exit();
    }

    // Medida de seguridad: Si se está editando la propia cuenta, no permitir cambiar rol o estado.
    if ($user_id_to_edit === $_SESSION['id_usuario']) {
        // La consulta solo incluirá nombre, email y contraseña para la propia cuenta.
        // Forzamos que el rol y estado no se envíen para ser actualizados.
        unset($new_role_id);
        unset($new_status_id);
    }
    
    try {
        // Verificar si el nuevo email ya está en uso por OTRO usuario
        $stmt_check_email = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :user_id");
        $stmt_check_email->execute([':email' => $new_email, ':user_id' => $user_id_to_edit]);
        if ($stmt_check_email->fetch()) {
            $response['message'] = 'El nuevo email ya está en uso por otro usuario.';
            echo json_encode($response);
            exit();
        }

        // --- CONSTRUCCIÓN DE LA CONSULTA SQL ---
        $sql_parts = ["nombre = :name", "email = :email"];
        $params = [
            ':user_id' => $user_id_to_edit,
            ':name' => $new_name,
            ':email' => $new_email
        ];

        // Añadir rol y estado si no se está auto-editando
        if (isset($new_role_id) && isset($new_status_id)) {
            $sql_parts[] = "id_rol = :role_id";
            $sql_parts[] = "id_estado = :status_id";
            $params[':role_id'] = $new_role_id;
            $params[':status_id'] = $new_status_id;
        }

        // Si se proporcionó una nueva contraseña, la añadimos a la consulta.
        if (!empty($new_password)) {
            if (strlen($new_password) < 8) {
                $response['message'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
                echo json_encode($response);
                exit();
            }
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_parts[] = "contrasena = :password";
            $params[':password'] = $hashed_password;
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $sql_parts) . " WHERE id_usuario = :user_id";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = '¡Usuario actualizado exitosamente!';
            } else {
                $response['success'] = true;
                $response['message'] = 'No se realizaron cambios (los datos son los mismos).';
            }
        } else {
            $response['message'] = 'Error al ejecutar la actualización en la base de datos.';
        }

    } catch (PDOException $e) {
        $response['message'] = 'Error de base de datos. Por favor, inténtelo de nuevo.';
    }
}

echo json_encode($response);
?>