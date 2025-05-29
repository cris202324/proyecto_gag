<?php
session_start();
require_once 'conexion.php'; // $pdo - Asume que conexion.php está en el mismo directorio

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php"); // Ajusta ruta si login.php no está en el mismo directorio
    exit();
}

// Verificar si se proporcionó un ID de cultivo y si es numérico
if (!isset($_GET['id_cultivo']) || !is_numeric($_GET['id_cultivo'])) {
    $_SESSION['error_borrado'] = "ID de cultivo no válido o no proporcionado.";
    header("Location: miscultivos.php"); // Redirige de vuelta a la lista de cultivos
    exit();
}

$id_cultivo_a_borrar = (int)$_GET['id_cultivo'];
$id_usuario_actual = $_SESSION['id_usuario'];

// Verificar si la conexión a la BD está disponible (proveniente de conexion.php)
if (!isset($pdo)) {
    $_SESSION['error_borrado'] = "Error crítico: No hay conexión a la base de datos.";
    header("Location: miscultivos.php");
    exit();
}

try {
    // PASO DE SEGURIDAD: Verificar que el cultivo pertenece al usuario actual antes de borrar
    $stmt_check = $pdo->prepare("SELECT id_usuario FROM cultivos WHERE id_cultivo = :id_cultivo");
    $stmt_check->bindParam(':id_cultivo', $id_cultivo_a_borrar, PDO::PARAM_INT);
    $stmt_check->execute();
    $cultivo_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$cultivo_data) {
        $_SESSION['error_borrado'] = "El cultivo que intentas borrar no existe.";
        header("Location: miscultivos.php");
        exit();
    }

    if ($cultivo_data['id_usuario'] !== $id_usuario_actual) {
        // Intento de borrar un cultivo ajeno
        $_SESSION['error_borrado'] = "No tienes permiso para borrar este cultivo.";
        // Opcional: loguear este intento
        // error_log("ALERTA: Usuario {$id_usuario_actual} intentó borrar cultivo {$id_cultivo_a_borrar} del usuario {$cultivo_data['id_usuario']}.");
        header("Location: miscultivos.php");
        exit();
    }

    // Si todo está bien, proceder a borrar el cultivo
    // La cláusula AND id_usuario es una doble capa de seguridad.
    $stmt_delete = $pdo->prepare("DELETE FROM cultivos WHERE id_cultivo = :id_cultivo AND id_usuario = :id_usuario");
    $stmt_delete->bindParam(':id_cultivo', $id_cultivo_a_borrar, PDO::PARAM_INT);
    $stmt_delete->bindParam(':id_usuario', $id_usuario_actual); // Asegura que solo borre si también coincide el usuario

    if ($stmt_delete->execute()) {
        // rowCount() devuelve el número de filas afectadas. Debería ser 1 si se borró.
        if ($stmt_delete->rowCount() > 0) {
            $_SESSION['mensaje_borrado'] = "¡Cultivo borrado exitosamente!";
        } else {
            // Esto podría pasar si, por alguna razón muy extraña, el cultivo existía en el check pero no al momento del delete,
            // o si el id_usuario no coincidió en la cláusula WHERE del DELETE (ya cubierto por el check anterior).
            $_SESSION['error_borrado'] = "No se pudo borrar el cultivo o ya no existía con tus permisos.";
        }
    } else {
        $_SESSION['error_borrado'] = "Error al ejecutar la acción de borrado en la base de datos.";
    }

} catch (PDOException $e) {
    // Capturar errores específicos de PDO
    $_SESSION['error_borrado'] = "Error de base de datos al intentar borrar el cultivo. Detalles: " . $e->getMessage();
    // En un entorno de producción, solo loguear $e->getMessage() y mostrar un mensaje genérico al usuario.
    // error_log("Error PDO al borrar cultivo {$id_cultivo_a_borrar} por usuario {$id_usuario_actual}: " . $e->getMessage());
} catch (Exception $e) {
    // Capturar otras excepciones generales
    $_SESSION['error_borrado'] = "Ocurrió un error inesperado. Por favor, inténtalo de nuevo.";
    // error_log("Error general al borrar cultivo {$id_cultivo_a_borrar}: " . $e->getMessage());
}

// Redirigir siempre de vuelta a la lista de cultivos
header("Location: miscultivos.php");
exit();
?>