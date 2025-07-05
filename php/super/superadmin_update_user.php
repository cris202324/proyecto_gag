<?php
// Inicia la sesión de PHP. Es crucial para acceder a las variables de sesión
// y verificar los permisos del usuario que realiza la petición.
session_start();

// Establece la cabecera de la respuesta HTTP para indicar que el contenido será JSON.
// Esto le dice al navegador (o al cliente que hizo la petición) cómo interpretar la respuesta.
header('Content-Type: application/json');

// --- PREPARACIÓN DE LA RESPUESTA JSON ---
// Se inicializa un array asociativo que contendrá la respuesta.
// Por defecto, se asume que la petición no es exitosa.
$response = ['success' => false, 'message' => 'Petición inválida.'];

// 1. --- VERIFICACIÓN DE PERMISOS DE SUPERADMINISTRADOR ---
// Medida de seguridad crítica. Comprueba si el usuario ha iniciado sesión,
// si tiene un rol asignado y si ese rol es el de Super Admin (ID 3).
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['rol']) || $_SESSION['rol'] != 3) {
    // Si no cumple los requisitos, se actualiza el mensaje de error y se detiene el script,
    // enviando la respuesta JSON de acceso denegado.
    $response['message'] = 'Acceso denegado. No tienes permisos de superadministrador.';
    echo json_encode($response);
    exit();
}

// --- INCLUSIÓN DE LA CONEXIÓN A LA BASE DE DATOS ---
// Incluye el archivo que establece la conexión a la base de datos y define la variable $pdo.
require_once '../conexion.php';

// --- PROCESAMIENTO DE LA PETICIÓN POST ---
// Se verifica que la petición HTTP sea de tipo POST, que es como el formulario AJAX envía los datos.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se recogen los datos enviados desde el formulario del modal.
    // El operador de fusión de null (??) se usa como atajo para asignar un valor por defecto (null) si la variable no existe.
    $user_id_to_edit = $_POST['user_id'] ?? null;
    $new_name = isset($_POST['user_name']) ? trim($_POST['user_name']) : null;
    $new_email = isset($_POST['user_email']) ? trim($_POST['user_email']) : null;
    $new_role_id = $_POST['role_id'] ?? null;
    $new_status_id = $_POST['status_id'] ?? null;
    $new_password = $_POST['new_password'] ?? null;

    // --- VALIDACIONES DE LOS DATOS RECIBIDOS ---
    
    // Validación de datos esenciales: se asegura de que los campos no opcionales no estén vacíos.
    if (empty($user_id_to_edit) || empty($new_name) || empty($new_email) || empty($new_role_id) || empty($new_status_id)) {
        $response['message'] = 'Faltan datos requeridos (ID, nombre, email, rol o estado).';
        echo json_encode($response);
        exit();
    }

    // Validación del nombre: usa una expresión regular para asegurarse de que no contenga números.
    if (preg_match('/[0-9]/', $new_name)) {
        $response['message'] = 'El nombre no puede contener números.';
        echo json_encode($response);
        exit();
    }

    // Validación del email: usa una función de filtro de PHP para verificar que el formato del email sea válido.
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'El formato del correo electrónico no es válido.';
        echo json_encode($response);
        exit();
    }

    // Medida de seguridad para evitar que el Super Admin se bloquee a sí mismo.
    // Si el ID del usuario a editar es el mismo que el del usuario logueado...
    if ($user_id_to_edit === $_SESSION['id_usuario']) {
        // ...se eliminan las variables de rol y estado. Esto previene que se incluyan
        // en la consulta SQL de actualización, evitando que el superadmin cambie su propio rol o estado accidentalmente.
        unset($new_role_id);
        unset($new_status_id);
    }
    
    // Bloque try-catch para manejar errores de la base de datos durante la ejecución de las consultas.
    try {
        // Se comprueba si el nuevo email ya existe en la base de datos para OTRO usuario.
        // Esto previene emails duplicados, que deben ser únicos.
        $stmt_check_email = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :user_id");
        $stmt_check_email->execute([':email' => $new_email, ':user_id' => $user_id_to_edit]);
        if ($stmt_check_email->fetch()) {
            // Si la consulta devuelve un resultado, significa que el email ya está en uso.
            $response['message'] = 'El nuevo email ya está en uso por otro usuario.';
            echo json_encode($response);
            exit();
        }

        // --- CONSTRUCCIÓN DINÁMICA DE LA CONSULTA SQL DE ACTUALIZACIÓN ---
        // Se crea un array con las partes de la consulta que siempre estarán presentes (nombre y email).
        $sql_parts = ["nombre = :name", "email = :email"];
        // Se crea un array con los parámetros correspondientes para la consulta preparada.
        $params = [
            ':user_id' => $user_id_to_edit,
            ':name' => $new_name,
            ':email' => $new_email
        ];

        // Se añaden las partes de rol y estado a la consulta solo si no fueron eliminadas previamente (es decir, si no se está auto-editando).
        if (isset($new_role_id) && isset($new_status_id)) {
            $sql_parts[] = "id_rol = :role_id";
            $sql_parts[] = "id_estado = :status_id";
            $params[':role_id'] = $new_role_id;
            $params[':status_id'] = $new_status_id;
        }

        // Si se proporcionó una nueva contraseña...
        if (!empty($new_password)) {
            // ...primero se valida su longitud.
            if (strlen($new_password) < 8) {
                $response['message'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
                echo json_encode($response);
                exit();
            }
            // ...luego se hashea de forma segura usando el algoritmo por defecto de PHP.
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            // ...y finalmente se añade la parte de la contraseña a la consulta y a los parámetros.
            $sql_parts[] = "contrasena = :password";
            $params[':password'] = $hashed_password;
        }

        // Se unen todas las partes de la consulta SQL con comas para formar la sentencia UPDATE final.
        $sql = "UPDATE usuarios SET " . implode(', ', $sql_parts) . " WHERE id_usuario = :user_id";
        
        // Se prepara y ejecuta la consulta con todos los parámetros.
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            // Si la ejecución es exitosa, se comprueba si alguna fila fue afectada.
            if ($stmt->rowCount() > 0) {
                // Si se afectó al menos una fila, la actualización fue exitosa.
                $response['success'] = true;
                $response['message'] = '¡Usuario actualizado exitosamente!';
            } else {
                // Si no se afectó ninguna fila, significa que los datos enviados eran los mismos que ya estaban en la BD.
                // Se considera un éxito porque no hubo error.
                $response['success'] = true;
                $response['message'] = 'No se realizaron cambios (los datos son los mismos).';
            }
        } else {
            // Si `execute()` devuelve false, hubo un error en la ejecución.
            $response['message'] = 'Error al ejecutar la actualización en la base de datos.';
        }

    } catch (PDOException $e) {
        // Si ocurre un error de base de datos, se captura y se envía un mensaje genérico.
        // Es una buena práctica registrar el error real en un log del servidor para depuración.
        // error_log('Error en superadmin_update_user.php: ' . $e->getMessage());
        $response['message'] = 'Error de base de datos. Por favor, inténtelo de nuevo.';
    }
}

// --- RESPUESTA FINAL ---
// Se codifica el array $response a formato JSON y se envía de vuelta al cliente (el JavaScript que hizo la petición).
echo json_encode($response);
?>