<?php
session_start();
require_once 'conexion.php'; // $pdo

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php"); // Ajusta ruta
    exit();
}

$id_usuario_actual = $_SESSION['id_usuario'];
$mensaje_formulario = '';
$error_formulario = false;
$tickets_usuario = [];

// Manejar envío de nuevo ticket
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enviar_pregunta'])) {
    $asunto = trim($_POST['asunto']);
    $mensaje_pregunta = trim($_POST['mensaje_pregunta']);

    if (empty($asunto) || empty($mensaje_pregunta)) {
        $mensaje_formulario = "El asunto y el mensaje son obligatorios.";
        $error_formulario = true;
    } elseif (strlen($asunto) > 255) {
        $mensaje_formulario = "El asunto es demasiado largo (máximo 255 caracteres).";
        $error_formulario = true;
    } else {
        try {
            $sql_insert_ticket = "INSERT INTO tickets_soporte (id_usuario, asunto, mensaje_usuario) VALUES (:id_usuario, :asunto, :mensaje)";
            $stmt_insert = $pdo->prepare($sql_insert_ticket);
            $stmt_insert->bindParam(':id_usuario', $id_usuario_actual);
            $stmt_insert->bindParam(':asunto', $asunto);
            $stmt_insert->bindParam(':mensaje', $mensaje_pregunta);

            if ($stmt_insert->execute()) {
                $mensaje_formulario = "¡Tu pregunta ha sido enviada! Recibirás una respuesta pronto.";
            } else {
                $mensaje_formulario = "Error al enviar tu pregunta. Inténtalo de nuevo.";
                $error_formulario = true;
            }
        } catch (PDOException $e) {
            $mensaje_formulario = "Error de base de datos: " . $e->getMessage();
            // error_log("Error al crear ticket: " . $e->getMessage());
            $error_formulario = true;
        }
    }
}

// Cargar tickets existentes del usuario (y sus respuestas si las hay)
try {
    $sql_get_tickets = "SELECT 
                            t.id_ticket, 
                            t.asunto, 
                            t.mensaje_usuario, 
                            DATE_FORMAT(t.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion_f,
                            t.estado_ticket,
                            r.mensaje_admin,
                            DATE_FORMAT(r.fecha_respuesta, '%d/%m/%Y %H:%i') as fecha_respuesta_f,
                            ua.nombre as nombre_admin
                        FROM tickets_soporte t
                        LEFT JOIN respuestas_soporte r ON t.id_ticket = r.id_ticket
                        LEFT JOIN usuarios ua ON r.id_admin = ua.id_usuario 
                        WHERE t.id_usuario = :id_usuario
                        ORDER BY t.ultima_actualizacion DESC"; // O t.fecha_creacion DESC
    $stmt_get = $pdo->prepare($sql_get_tickets);
    $stmt_get->bindParam(':id_usuario', $id_usuario_actual);
    $stmt_get->execute();
    $tickets_usuario = $stmt_get->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $mensaje_general_error = "Error al cargar tus preguntas anteriores: " . $e->getMessage();
    // error_log("Error al cargar tickets: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayuda y Soporte</title>
    <link rel="stylesheet" href="../css/estilos.css"> <!-- Ajusta ruta -->
    <style>
        .ayuda-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 25px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .ayuda-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .ayuda-section:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        .ayuda-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #4caf50;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .btn-submit { padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; font-size: 1em; cursor: pointer; transition: background-color 0.3s ease; }
        .btn-submit:hover { background-color: #45a049; }
        .mensaje { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 0.9em; text-align: center; }
        .mensaje.exito { background-color: #e8f5e9; color: #387002; border: 1px solid #c8e6c9; }
        .mensaje.error { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        .ticket-lista { list-style: none; padding: 0; }
        .ticket-item {
            background-color: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .ticket-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .ticket-asunto { font-weight: bold; color: #333; font-size: 1.1em; }
        .ticket-estado {
            font-size: 0.85em;
            padding: 4px 8px;
            border-radius: 15px;
            color: white;
            font-weight: bold;
        }
        .estado-abierto { background-color: #ffc107; /* Amarillo */ }
        .estado-respondido { background-color: #28a745; /* Verde */ }
        .estado-cerrado { background-color: #6c757d; /* Gris */ }
        .ticket-fecha { font-size: 0.8em; color: #777; }
        .ticket-mensaje, .ticket-respuesta {
            margin-top: 10px;
            padding-left: 10px;
            border-left: 3px solid #e0e0e0;
            font-size: 0.95em;
            line-height: 1.5;
            white-space: pre-wrap; /* Para conservar saltos de línea */
        }
        .ticket-respuesta { border-left-color: #4caf50; background-color: #f0fff0; padding:10px; margin-top:15px; }
        .ticket-respuesta strong { display: block; margin-bottom: 5px; }
        .no-tickets { text-align: center; color: #777; padding: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo" /> <!-- Ajusta ruta -->
        </div>
        <div class="menu">
            <a href="index.php">Inicio</a>
            <a href="miscultivos.php">Mis cultivos</a>
            <a href="animales/mis_animales.php">Mis animales</a>
            <a href="calendario_general.php">Calendario</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php" class="active">Ayuda</a>
            <a href="cerrar_sesion.php">Cerrar sesión</a>
        </div>
    </div>

    <div class="ayuda-container">
        <h2 style="text-align:center; color:#4caf50; margin-bottom:30px;">Ayuda y Soporte</h2>

        <!-- Sección para Enviar Nueva Pregunta -->
        <div class="ayuda-section">
            <h3>Enviar una Nueva Pregunta</h3>
            <?php if (!empty($mensaje_formulario)): ?>
                <p class="mensaje <?php echo $error_formulario ? 'error' : 'exito'; ?>"><?php echo htmlspecialchars($mensaje_formulario); ?></p>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="form-group">
                    <label for="asunto">Asunto:</label>
                    <input type="text" id="asunto" name="asunto" maxlength="255" required>
                </div>
                <div class="form-group">
                    <label for="mensaje_pregunta">Tu Pregunta:</label>
                    <textarea id="mensaje_pregunta" name="mensaje_pregunta" rows="6" required></textarea>
                </div>
                <button type="submit" name="enviar_pregunta" class="btn-submit">Enviar Pregunta</button>
            </form>
        </div>

        <!-- Sección para Ver Preguntas Anteriores -->
        <div class="ayuda-section">
            <h3>Mis Preguntas Anteriores</h3>
            <?php if (isset($mensaje_general_error)): ?>
                 <p class="mensaje error"><?php echo htmlspecialchars($mensaje_general_error); ?></p>
            <?php endif; ?>

            <?php if (empty($tickets_usuario) && !isset($mensaje_general_error)): ?>
                <p class="no-tickets">No has enviado ninguna pregunta todavía.</p>
            <?php else: ?>
                <ul class="ticket-lista">
                    <?php foreach ($tickets_usuario as $ticket): ?>
                        <li class="ticket-item">
                            <div class="ticket-header">
                                <span class="ticket-asunto"><?php echo htmlspecialchars($ticket['asunto']); ?></span>
                                <span class="ticket-estado estado-<?php echo strtolower($ticket['estado_ticket']); ?>">
                                    <?php echo htmlspecialchars($ticket['estado_ticket']); ?>
                                </span>
                            </div>
                            <div class="ticket-fecha">Enviado el: <?php echo $ticket['fecha_creacion_f']; ?></div>
                            <div class="ticket-mensaje">
                                <strong>Tu pregunta:</strong>
                                <p><?php echo nl2br(htmlspecialchars($ticket['mensaje_usuario'])); ?></p>
                            </div>
                            <?php if ($ticket['mensaje_admin']): ?>
                                <div class="ticket-respuesta">
                                    <strong>Respuesta de <?php echo htmlspecialchars($ticket['nombre_admin'] ?: 'Soporte'); ?> (<?php echo $ticket['fecha_respuesta_f']; ?>):</strong>
                                    <p><?php echo nl2br(htmlspecialchars($ticket['mensaje_admin'])); ?></p>
                                </div>
                            <?php elseif ($ticket['estado_ticket'] == 'Abierto'): ?>
                                <p style="font-style: italic; color: #777; margin-top:10px;">Esperando respuesta del administrador...</p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>