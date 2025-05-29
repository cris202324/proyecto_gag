<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    header("Location: ../login.html");
    exit();
}

include 'conexion.php';

$tickets = [];
$mensaje_error = '';
$mensaje_success = '';

// Procesar respuesta o cierre de ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['responder_ticket'])) {
            $id_ticket = $_POST['id_ticket'];
            $mensaje_admin = trim($_POST['mensaje_admin']);

            if (empty($mensaje_admin)) {
                $mensaje_error = "El mensaje de respuesta es obligatorio.";
            } else {
                $update_ticket = $pdo->prepare("UPDATE tickets_soporte SET estado_ticket = 'Respondido' WHERE id_ticket = :id_ticket");
                $update_ticket->bindParam(':id_ticket', $id_ticket, PDO::PARAM_INT);
                $update_ticket->execute();

                $insert_response = $pdo->prepare("INSERT INTO respuestas_soporte (id_ticket, id_admin, mensaje_admin) VALUES (:id_ticket, :id_admin, :mensaje)");
                $insert_response->bindParam(':id_ticket', $id_ticket, PDO::PARAM_INT);
                $insert_response->bindParam(':id_admin', $_SESSION['id_usuario']);
                $insert_response->bindParam(':mensaje', $mensaje_admin);
                $insert_response->execute();

                $mensaje_success = "Respuesta enviada con éxito.";
            }
        } elseif (isset($_POST['cerrar_ticket'])) {
            $id_ticket = $_POST['id_ticket'];
            $update_ticket = $pdo->prepare("UPDATE tickets_soporte SET estado_ticket = 'Cerrado' WHERE id_ticket = :id_ticket");
            $update_ticket->bindParam(':id_ticket', $id_ticket, PDO::PARAM_INT);
            $update_ticket->execute();
            $mensaje_success = "Ticket cerrado con éxito.";
        }
    } catch (PDOException $e) {
        $mensaje_error = "Error en la operación: " . $e->getMessage();
    }
}

// Obtener todos los tickets
try {
    $sql = "SELECT 
                t.id_ticket,
                t.asunto,
                t.mensaje_usuario,
                DATE_FORMAT(t.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion_f,
                t.estado_ticket,
                u.nombre AS nombre_usuario,
                r.mensaje_admin,
                DATE_FORMAT(r.fecha_respuesta, '%d/%m/%Y %H:%i') as fecha_respuesta_f
            FROM tickets_soporte t
            LEFT JOIN usuarios u ON t.id_usuario = u.id_usuario
            LEFT JOIN respuestas_soporte r ON t.id_ticket = r.id_ticket
            ORDER BY t.ultima_actualizacion DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje_error = "Error al obtener los tickets: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Gestionar Tickets de Soporte</title>
    <link rel="stylesheet" href="../css/estilos.css">
        <style>
        .content {
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            max-width: 400px;
            min-height: 0; /* Permite que crezca según el contenido */
        }
        .card textarea {
            overflow-wrap: break-word;
            word-break: break-all;
        }
        .no-tickets {
            text-align: center;
            width: 100%;
            padding: 30px;
            font-size: 1.2em;
            color: #777;
        }
        .error-message {
            color: red;
            text-align: center;
            width: 100%;
            padding: 15px;
            background-color: #fdd;
            border: 1px solid #fbb;
        }
        .success-message {
            color: green;
            text-align: center;
            width: 100%;
            padding: 15px;
            background-color: #dff0d8;
            border: 1px solid #d6e9c6;
        }
        .ticket-status {
            font-size: 0.85em;
            padding: 4px 8px;
            border-radius: 15px;
            color: white;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .estado-abierto { background-color: #ffc107; }
        .estado-respondido { background-color: #28a745; }
        .estado-cerrado { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="logo" />
        </div>
        <div class="menu">
            <a href="admin_dashboard.php">Inicio</a>
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="view_all_animals.php">Ver Animales</a>
            <a href="manage_users.php">Gestionar Usuarios</a>
            <a href="manage_animals.php">Gestionar Animales</a>
            <a href="manage_tickets.php" class="active">Gestionar Tickets</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </div>
    </div>

    <div class="content">
        <h2>Gestionar Tickets de Soporte</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_success)): ?>
            <p class="success-message"><?php echo htmlspecialchars($mensaje_success); ?></p>
        <?php endif; ?>

        <?php if (empty($tickets)): ?>
            <div class="no-tickets">
                <p>No hay tickets para gestionar.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tickets as $ticket): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($ticket['asunto']); ?> (<?php echo htmlspecialchars($ticket['nombre_usuario']); ?>)</h3>
                    <p><strong>Fecha:</strong> <?php echo htmlspecialchars($ticket['fecha_creacion_f']); ?></p>
                    <p><strong>Mensaje:</strong> <?php echo nl2br(htmlspecialchars($ticket['mensaje_usuario'])); ?></p>
                    <span class="ticket-status estado-<?php echo strtolower($ticket['estado_ticket']); ?>">
                        <?php echo htmlspecialchars($ticket['estado_ticket']); ?>
                    </span>

                    <?php if ($ticket['estado_ticket'] === 'Abierto' || $ticket['estado_ticket'] === 'Respondido'): ?>
                        <form method="POST">
                            <input type="hidden" name="id_ticket" value="<?php echo $ticket['id_ticket']; ?>">
                            <?php if ($ticket['estado_ticket'] === 'Abierto'): ?>
                                <textarea name="mensaje_admin" placeholder="Escribe tu respuesta..." required></textarea>
                                <button type="submit" name="responder_ticket" class="btn-responder">Responder</button>
                            <?php endif; ?>
                            <button type="submit" name="cerrar_ticket" class="btn-cerrar" onclick="return confirm('¿Seguro que quieres cerrar este ticket?');">Cerrar Ticket</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($ticket['mensaje_admin']): ?>
                        <p><strong>Respuesta:</strong> <?php echo nl2br(htmlspecialchars($ticket['mensaje_admin'])); ?></p>
                        <p><small>Respondido el: <?php echo htmlspecialchars($ticket['fecha_respuesta_f']); ?></small></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>