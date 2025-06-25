<?php
session_start();
// (Cabeceras de no-cache)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");


if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    // Si no es admin, no debería estar aquí.
    // Podrías redirigir a una página de "no autorizado" o al login.
    $_SESSION['mensaje_error_trat_pred'] = "Acceso no autorizado para esta acción.";
    header("Location: admin_manage_trat_pred.php"); // O a una página de error genérica
    exit();
}
include '../conexion.php'; 

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_a_eliminar = (int)$_GET['id'];

    if (isset($pdo)) {
        try {
            // Primero, verificar si el tratamiento existe (opcional, pero buena práctica)
            $stmt_check = $pdo->prepare("SELECT id_trat_pred FROM tratamientos_predeterminados WHERE id_trat_pred = :id");
            $stmt_check->bindParam(':id', $id_a_eliminar, PDO::PARAM_INT);
            $stmt_check->execute();

            if ($stmt_check->fetch()) {
                $stmt_delete = $pdo->prepare("DELETE FROM tratamientos_predeterminados WHERE id_trat_pred = :id");
                $stmt_delete->bindParam(':id', $id_a_eliminar, PDO::PARAM_INT);
                
                if ($stmt_delete->execute()) {
                    $_SESSION['mensaje_exito_trat_pred'] = "Tratamiento predeterminado (ID: $id_a_eliminar) eliminado exitosamente.";
                } else {
                    $_SESSION['mensaje_error_trat_pred'] = "Error al intentar eliminar el tratamiento predeterminado (ID: $id_a_eliminar).";
                }
            } else {
                 $_SESSION['mensaje_error_trat_pred'] = "Tratamiento predeterminado no encontrado para eliminar (ID: $id_a_eliminar).";
            }
        } catch (PDOException $e) {
            // Manejar errores de FK si, por ejemplo, este tratamiento está siendo usado en algún lugar
            // y no tienes ON DELETE CASCADE (lo cual para tratamientos predeterminados quizás no sea lo ideal que borre en cascada)
            if ($e->getCode() == '23000') {
                 $_SESSION['mensaje_error_trat_pred'] = "No se puede eliminar el tratamiento (ID: $id_a_eliminar) porque está en uso o referenciado en otra parte del sistema.";
            } else {
                $_SESSION['mensaje_error_trat_pred'] = "Error en la base de datos al eliminar: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['mensaje_error_trat_pred'] = "Error de conexión a la base de datos.";
    }
} else {
    $_SESSION['mensaje_error_trat_pred'] = "ID de tratamiento no válido para eliminar.";
}

header("Location: admin_manage_trat_pred.php");
exit();
?>