<?php
session_start();
require_once '../../conexion.php'; // $pdo

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../../pages/auth/login.html"); 
    exit();
}

$id_usuario_actual = $_SESSION['id_usuario'];

// Verificar si se proporcionó un ID de cultivo válido
if (!isset($_GET['id_cultivo']) || !is_numeric($_GET['id_cultivo'])) {
    $_SESSION['error_accion_cultivo'] = "ID de cultivo no válido para marcar como terminado.";
    header("Location: miscultivos.php"); 
    exit();
}

$id_cultivo_a_terminar = (int)$_GET['id_cultivo'];

// ID del estado "Terminado" (ajústalo según tu tabla estado_cultivo_definiciones)
$id_estado_terminado = 2; // ASUME que 2 es el ID para "Terminado"

if (!isset($pdo)) {
    $_SESSION['error_accion_cultivo'] = "Error crítico: No hay conexión a la base de datos.";
    header("Location: miscultivos.php");
    exit();
}

try {
    // 1. Verificar que el cultivo pertenece al usuario actual y no esté ya terminado
    $stmt_check = $pdo->prepare("SELECT id_estado_cultivo FROM cultivos WHERE id_cultivo = :id_cultivo AND id_usuario = :id_usuario");
    $stmt_check->bindParam(':id_cultivo', $id_cultivo_a_terminar, PDO::PARAM_INT);
    $stmt_check->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_check->execute();
    $cultivo_actual = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$cultivo_actual) {
        $_SESSION['error_accion_cultivo'] = "No tienes permiso para modificar este cultivo o no existe.";
        header("Location: miscultivos.php");
        exit();
    }

    if ($cultivo_actual['id_estado_cultivo'] == $id_estado_terminado) {
        $_SESSION['mensaje_accion_cultivo'] = "El cultivo (ID: {$id_cultivo_a_terminar}) ya estaba marcado como terminado.";
        header("Location: miscultivos.php");
        exit();
    }
    
    // 2. Actualizar solo el estado del cultivo
    $sql_update = "UPDATE cultivos SET id_estado_cultivo = :id_estado_terminado 
                   WHERE id_cultivo = :id_cultivo AND id_usuario = :id_usuario";
    
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->bindParam(':id_estado_terminado', $id_estado_terminado, PDO::PARAM_INT);
    $stmt_update->bindParam(':id_cultivo', $id_cultivo_a_terminar, PDO::PARAM_INT);
    $stmt_update->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);

    if ($stmt_update->execute()) {
        if ($stmt_update->rowCount() > 0) {
            $_SESSION['mensaje_accion_cultivo'] = "Cultivo (ID: {$id_cultivo_a_terminar}) marcado como Terminado exitosamente.";
        } else {
            // Esto podría pasar si el ID no existe o no pertenece al usuario, aunque ya se verificó.
            $_SESSION['error_accion_cultivo'] = "No se pudo actualizar el estado del cultivo.";
        }
    } else {
        $_SESSION['error_accion_cultivo'] = "Error al ejecutar la actualización del estado del cultivo.";
    }

} catch (PDOException $e) {
    $_SESSION['error_accion_cultivo'] = "Error de base de datos al marcar como terminado: " . $e->getMessage();
}

header("Location: miscultivos.php"); 
exit();
?>