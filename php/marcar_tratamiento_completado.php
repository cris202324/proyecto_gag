<?php
session_start();
require_once 'conexion.php'; // $pdo

header('Content-Type: application/json'); // Siempre devolver JSON

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit();
}

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit();
}

$id_tratamiento = $_POST['id_tratamiento'] ?? null;
$fecha_realizacion = $_POST['fecha_realizacion'] ?? null;
$observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : null;
$id_usuario_actual = $_SESSION['id_usuario'];

if (empty($id_tratamiento) || !is_numeric($id_tratamiento)) {
    echo json_encode(['success' => false, 'message' => 'ID de tratamiento no válido.']);
    exit();
}
if (empty($fecha_realizacion)) {
    echo json_encode(['success' => false, 'message' => 'La fecha de realización es obligatoria.']);
    exit();
}
// Validar formato de fecha YYYY-MM-DD
$fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_realizacion);
if (!$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha_realizacion) {
    echo json_encode(['success' => false, 'message' => 'Formato de fecha de realización no válido. Use YYYY-MM-DD.']);
    exit();
}


try {
    // Verificar que el tratamiento pertenece a un cultivo del usuario (seguridad adicional)
    $sql_check = "SELECT t.id_cultivo 
                  FROM tratamiento_cultivo t
                  JOIN cultivos c ON t.id_cultivo = c.id_cultivo
                  WHERE t.id_tratamiento = :id_tratamiento AND c.id_usuario = :id_usuario";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id_tratamiento', $id_tratamiento, PDO::PARAM_INT);
    $stmt_check->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_check->execute();

    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para modificar este tratamiento o no existe.']);
        exit();
    }

    // Actualizar el tratamiento
    $sql_update = "UPDATE tratamiento_cultivo 
                   SET estado_tratamiento = 'Completado', 
                       fecha_realizacion_real = :fecha_realizacion,
                       observaciones_realizacion = :observaciones
                   WHERE id_tratamiento = :id_tratamiento";
    
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->bindParam(':fecha_realizacion', $fecha_realizacion);
    $stmt_update->bindParam(':observaciones', $observaciones);
    $stmt_update->bindParam(':id_tratamiento', $id_tratamiento, PDO::PARAM_INT);

    if ($stmt_update->execute()) {
        if ($stmt_update->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Tratamiento marcado como completado.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se realizaron cambios o el tratamiento ya estaba completado.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el tratamiento.']);
    }

} catch (PDOException $e) {
    // error_log("Error PDO en marcar_tratamiento_completado: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>