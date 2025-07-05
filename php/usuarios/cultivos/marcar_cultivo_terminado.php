<?php
// Inicia la sesión de PHP. Es crucial para acceder a variables de sesión,
// como el ID del usuario logueado, y para guardar mensajes de feedback que se mostrarán en la siguiente página.
session_start();

// --- INCLUSIÓN DEL ARCHIVO DE CONEXIÓN A LA BASE DE DATOS ---
// Incluye el archivo que establece la conexión con la base de datos y define la variable $pdo.
// La ruta relativa debe ser correcta desde la ubicación de este script.
require_once '../../conexion.php'; 

// --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Comprueba si el usuario ha iniciado sesión. Si no, lo redirige a la página de login.
// Esta es la primera barrera de seguridad para asegurar que solo usuarios logueados puedan ejecutar esta acción.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../../pages/auth/login.html"); 
    exit(); // Detiene la ejecución del script.
}

// Guarda el ID del usuario actual de la sesión en una variable para un uso más fácil y claro.
$id_usuario_actual = $_SESSION['id_usuario'];

// --- VALIDACIÓN DEL PARÁMETRO GET 'id_cultivo' ---
// Comprueba si se ha pasado un 'id_cultivo' en la URL (ej. ...?id_cultivo=123) y si es un número.
// Este ID es esencial para saber qué cultivo se debe marcar como terminado.
if (!isset($_GET['id_cultivo']) || !is_numeric($_GET['id_cultivo'])) {
    // Si el ID no es válido, se guarda un mensaje de error en la sesión y se redirige al usuario a la lista de cultivos.
    $_SESSION['error_accion_cultivo'] = "ID de cultivo no válido para marcar como terminado.";
    header("Location: miscultivos.php"); 
    exit();
}
// Se convierte el ID a entero para seguridad y se guarda en una variable.
$id_cultivo_a_terminar = (int)$_GET['id_cultivo'];

// --- DEFINICIÓN DE CONSTANTES/VARIABLES DE ESTADO ---
// Se define el ID del estado "Terminado". Este valor debe coincidir con el que tienes en tu tabla `estado_cultivo_definiciones`.
// Definirlo aquí hace que el código sea más legible y fácil de mantener si el ID cambia en el futuro.
$id_estado_terminado = 2; 

// --- VERIFICACIÓN DE LA CONEXIÓN A LA BASE DE DATOS ---
// Se comprueba si la variable $pdo se creó correctamente en 'conexion.php'.
if (!isset($pdo)) {
    $_SESSION['error_accion_cultivo'] = "Error crítico: No hay conexión a la base de datos.";
    header("Location: miscultivos.php");
    exit();
}

// --- LÓGICA PRINCIPAL DE ACTUALIZACIÓN ---
// El bloque try-catch maneja de forma segura los errores que puedan ocurrir durante las consultas a la base de datos.
try {
    // 1. VERIFICAR PERTENENCIA Y ESTADO ACTUAL DEL CULTIVO
    // Antes de modificar, se comprueba que el cultivo realmente pertenece al usuario actual.
    // Esta es una medida de seguridad crucial para evitar que un usuario modifique los datos de otro.
    $stmt_check = $pdo->prepare("SELECT id_estado_cultivo FROM cultivos WHERE id_cultivo = :id_cultivo AND id_usuario = :id_usuario");
    $stmt_check->bindParam(':id_cultivo', $id_cultivo_a_terminar, PDO::PARAM_INT);
    $stmt_check->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_check->execute();
    $cultivo_actual = $stmt_check->fetch(PDO::FETCH_ASSOC);

    // Si la consulta no devuelve ningún resultado, el cultivo no existe o no pertenece al usuario.
    if (!$cultivo_actual) {
        $_SESSION['error_accion_cultivo'] = "No tienes permiso para modificar este cultivo o no existe.";
        header("Location: miscultivos.php");
        exit();
    }

    // Se comprueba si el cultivo ya está marcado como terminado para evitar una actualización innecesaria.
    if ($cultivo_actual['id_estado_cultivo'] == $id_estado_terminado) {
        $_SESSION['mensaje_accion_cultivo'] = "El cultivo (ID: {$id_cultivo_a_terminar}) ya estaba marcado como terminado.";
        header("Location: miscultivos.php");
        exit();
    }
    
    // 2. EJECUTAR LA ACTUALIZACIÓN
    // Si todas las verificaciones pasan, se prepara la consulta SQL para actualizar solo el estado del cultivo.
    $sql_update = "UPDATE cultivos SET id_estado_cultivo = :id_estado_terminado 
                   WHERE id_cultivo = :id_cultivo AND id_usuario = :id_usuario";
    
    $stmt_update = $pdo->prepare($sql_update);
    // Se asocian los valores a los placeholders de la consulta de forma segura.
    $stmt_update->bindParam(':id_estado_terminado', $id_estado_terminado, PDO::PARAM_INT);
    $stmt_update->bindParam(':id_cultivo', $id_cultivo_a_terminar, PDO::PARAM_INT);
    $stmt_update->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);

    // Se ejecuta la consulta de actualización.
    if ($stmt_update->execute()) {
        // `rowCount()` devuelve el número de filas afectadas por la última consulta (UPDATE, DELETE, INSERT).
        if ($stmt_update->rowCount() > 0) {
            // Si al menos una fila fue afectada, la actualización fue exitosa.
            $_SESSION['mensaje_accion_cultivo'] = "Cultivo (ID: {$id_cultivo_a_terminar}) marcado como Terminado exitosamente.";
        } else {
            // Esto es un caso raro que podría ocurrir si el ID desaparece entre la verificación y la actualización.
            $_SESSION['error_accion_cultivo'] = "No se pudo actualizar el estado del cultivo.";
        }
    } else {
        // Si `execute()` devuelve false, hubo un error en la ejecución.
        $_SESSION['error_accion_cultivo'] = "Error al ejecutar la actualización del estado del cultivo.";
    }

} catch (PDOException $e) {
    // Si ocurre un error de base de datos durante el proceso, se captura y se guarda un mensaje de error.
    $_SESSION['error_accion_cultivo'] = "Error de base de datos al marcar como terminado: " . $e->getMessage();
}

// --- REDIRECCIÓN FINAL ---
// Sin importar si la operación fue exitosa o falló, se redirige al usuario de vuelta a la lista de 'miscultivos.php'.
// La página 'miscultivos.php' se encargará de mostrar el mensaje de éxito o error que se guardó en la sesión.
header("Location: miscultivos.php"); 
exit(); // Se detiene el script para asegurar que la redirección se ejecute correctamente.
?>