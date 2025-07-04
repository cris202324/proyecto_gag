<?php
session_start();
require_once '../conexion.php'; // Asegúrate que la ruta a tu conexión es correcta

// --- 1. VERIFICACIÓN DE ACCESO DE ADMIN/SUPERADMIN ---
$roles_permitidos = [1, 3]; // Admin y Superadmin pueden intentar eliminar
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $roles_permitidos)) {
    // Si no tiene permisos, se le deniega el acceso.
    // Guardamos un mensaje de error y redirigimos.
    $_SESSION['error_accion_usuario'] = "Acceso no autorizado para realizar esta acción.";
    header("Location: view_users.php"); // Redirigir a la lista de usuarios
    exit();
}

// --- 2. VALIDAR QUE SE RECIBE UN ID DE USUARIO ---
if (!isset($_GET['id_usuario']) || empty($_GET['id_usuario'])) {
    $_SESSION['error_accion_usuario'] = "No se especificó un ID de usuario para eliminar.";
    header("Location: view_users.php");
    exit();
}

$id_usuario_a_eliminar = $_GET['id_usuario'];
$id_admin_actual = $_SESSION['id_usuario'];
$rol_admin_actual = $_SESSION['rol'];

// --- 3. PREVENIR LA AUTOELIMINACIÓN ---
if ($id_usuario_a_eliminar === $id_admin_actual) {
    $_SESSION['error_accion_usuario'] = "No puedes eliminar tu propia cuenta.";
    header("Location: view_users.php");
    exit();
}

// --- 4. OBTENER DATOS DEL USUARIO A ELIMINAR PARA VALIDACIONES ADICIONALES ---
try {
    $stmt_user_info = $pdo->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = :id_usuario");
    $stmt_user_info->bindParam(':id_usuario', $id_usuario_a_eliminar, PDO::PARAM_STR);
    $stmt_user_info->execute();
    $usuario_a_eliminar = $stmt_user_info->fetch(PDO::FETCH_ASSOC);

    // Verificar si el usuario a eliminar existe
    if (!$usuario_a_eliminar) {
        $_SESSION['error_accion_usuario'] = "El usuario que intentas eliminar no existe.";
        header("Location: view_users.php");
        exit();
    }
    
    $rol_usuario_a_eliminar = $usuario_a_eliminar['id_rol'];

    // --- 5. LÓGICA DE PERMISOS DE ELIMINACIÓN ---
    // Un admin normal (rol=1) no puede eliminar a otro admin (rol=1) ni a un superadmin (rol=3)
    if ($rol_admin_actual == 1 && in_array($rol_usuario_a_eliminar, [1, 3])) {
        $_SESSION['error_accion_usuario'] = "No tienes permisos para eliminar a otros administradores o superadministradores.";
        header("Location: view_users.php");
        exit();
    }
    
    // --- 6. PROCEDER CON LA ELIMINACIÓN ---
    // En este punto, la eliminación es permitida.
    
    // Usamos una transacción para asegurar la integridad. Si algo falla, se revierte.
    // Esto es especialmente útil si tuvieras que borrar datos en múltiples tablas relacionadas (además de lo que ON DELETE CASCADE ya hace).
    $pdo->beginTransaction();

    // La consulta para eliminar
    $stmt_delete = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id_usuario");
    $stmt_delete->bindParam(':id_usuario', $id_usuario_a_eliminar, PDO::PARAM_STR);
    
    if ($stmt_delete->execute()) {
        // Confirmar la transacción
        $pdo->commit();
        $_SESSION['mensaje_accion_usuario'] = "Usuario con ID '" . htmlspecialchars($id_usuario_a_eliminar) . "' eliminado exitosamente.";
    } else {
        // Revertir la transacción si la eliminación falla
        $pdo->rollBack();
        $_SESSION['error_accion_usuario'] = "Error al intentar eliminar el usuario. Es posible que tenga datos asociados que impiden su eliminación.";
    }

} catch (PDOException $e) {
    // Si hay un error de base de datos, revertir la transacción si estaba activa
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Para depuración, puedes guardar el error en un log
    // error_log("Error en admin_delete_user.php: " . $e->getMessage());
    $_SESSION['error_accion_usuario'] = "Error de base de datos al intentar eliminar el usuario. Detalles: " . $e->getMessage();
}

// --- 7. REDIRIGIR DE VUELTA A LA LISTA DE USUARIOS ---
// Redirige manteniendo los parámetros de búsqueda y paginación si existen.
$redirect_url = "view_users.php";
$query_params = [];
if (isset($_GET['pagina'])) {
    $query_params['pagina'] = $_GET['pagina'];
}
if (isset($_GET['buscar'])) {
    $query_params['buscar'] = $_GET['buscar'];
}
if (!empty($query_params)) {
    $redirect_url .= '?' . http_build_query($query_params);
}

header("Location: " . $redirect_url);
exit();
?>