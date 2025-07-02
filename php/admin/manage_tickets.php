<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    // Ajusta esta ruta a tu página de login
    header("Location: ../../pages/auth/login.html");
    exit();
}

// Ajusta esta ruta a tu archivo de conexión
require_once '../conexion.php'; // $pdo

$tickets = [];
$mensaje_error = '';
$mensaje_success = '';

// Procesar respuesta o cierre de ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_ticket = $_POST['id_ticket'] ?? null;
        if (!$id_ticket || !is_numeric($id_ticket)) {
            throw new Exception("ID de ticket no válido.");
        }

        if (isset($_POST['responder_ticket'])) {
            $mensaje_admin = trim($_POST['mensaje_admin']);

            if (empty($mensaje_admin)) {
                $mensaje_error = "El mensaje de respuesta es obligatorio.";
            } else {
                $pdo->beginTransaction();

                $update_ticket = $pdo->prepare("UPDATE tickets_soporte SET estado_ticket = 'Respondido', ultima_actualizacion = CURRENT_TIMESTAMP WHERE id_ticket = :id_ticket");
                $update_ticket->bindParam(':id_ticket', $id_ticket, PDO::PARAM_INT);
                $update_ticket->execute();

                $insert_response = $pdo->prepare("INSERT INTO respuestas_soporte (id_ticket, id_admin, mensaje_admin) VALUES (:id_ticket, :id_admin, :mensaje)");
                $insert_response->bindParam(':id_ticket', $id_ticket, PDO::PARAM_INT);
                $insert_response->bindParam(':id_admin', $_SESSION['id_usuario'], PDO::PARAM_STR);
                $insert_response->bindParam(':mensaje', $mensaje_admin, PDO::PARAM_STR);
                $insert_response->execute();

                $pdo->commit();
                $mensaje_success = "Respuesta enviada con éxito al ticket ID: " . htmlspecialchars($id_ticket);
            }
        } elseif (isset($_POST['cerrar_ticket'])) {
            $update_ticket = $pdo->prepare("UPDATE tickets_soporte SET estado_ticket = 'Cerrado', ultima_actualizacion = CURRENT_TIMESTAMP WHERE id_ticket = :id_ticket");
            $update_ticket->bindParam(':id_ticket', $id_ticket, PDO::PARAM_INT);
            $update_ticket->execute();
            $mensaje_success = "Ticket ID: " . htmlspecialchars($id_ticket) . " cerrado con éxito.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje_error = "Error en la operación de base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        $mensaje_error = "Error en la operación: " . $e->getMessage();
    }
}

// Obtener todos los tickets
try {
    $sql = "SELECT 
                t.id_ticket, t.asunto, t.mensaje_usuario, 
                DATE_FORMAT(t.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion_f,
                t.estado_ticket, t.ultima_actualizacion,
                u.nombre AS nombre_usuario, u.email AS email_usuario,
                GROUP_CONCAT(DISTINCT CONCAT(DATE_FORMAT(r.fecha_respuesta, '%d/%m/%Y %H:%i'), ' (Admin: ', ua.nombre, '):<br>', r.mensaje_admin) ORDER BY r.fecha_respuesta ASC SEPARATOR '<hr style=\"margin:5px 0; border-color: #eee;\">') as respuestas_concatenadas
            FROM tickets_soporte t
            JOIN usuarios u ON t.id_usuario = u.id_usuario
            LEFT JOIN respuestas_soporte r ON t.id_ticket = r.id_ticket
            LEFT JOIN usuarios ua ON r.id_admin = ua.id_usuario
            GROUP BY t.id_ticket, t.asunto, t.mensaje_usuario, t.fecha_creacion, t.estado_ticket, t.ultima_actualizacion, u.nombre, u.email
            ORDER BY FIELD(t.estado_ticket, 'Abierto', 'Respondido', 'Cerrado'), t.ultima_actualizacion DESC";
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
    <title>Gestionar Tickets de Soporte - Admin GAG</title>
    <style>
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;} .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;white-space:nowrap;font-size:.9em;transition:background-color .3s, color .3s}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}
        
        .page-container{max-width:900px;margin:20px auto;padding:20px}
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h2.page-title {
            margin: 0;
            flex-grow: 1;
            text-align: left;
            color:#4caf50;
            font-size:2em;
        }
        .btn-reporte {
            padding: 10px 18px;
            background-color: #17a2b8;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 0.9em;
            transition: background-color 0.3s;
            white-space: nowrap;
        }
        .btn-reporte:hover {
            background-color: #138496;
        }
        
        .tickets-wrapper {display: flex;flex-direction: column;gap: 25px;}
        .ticket-card {background-color: #fff;border-radius: 8px;box-shadow: 0 3px 10px rgba(0,0,0,0.08);padding: 20px;border-left: 5px solid;}
        .ticket-card.estado-abierto { border-left-color: #ffc107; }
        .ticket-card.estado-respondido { border-left-color: #28a745; }
        .ticket-card.estado-cerrado { border-left-color: #6c757d; background-color: #f8f9fa; opacity: 0.8; }

        .ticket-card h3 {margin-top: 0;margin-bottom: 10px;color: #333;font-size: 1.3em;}
        .ticket-card .ticket-meta {font-size: 0.8em;color: #777;margin-bottom: 15px;display: flex;justify-content: space-between;flex-wrap: wrap;gap: 10px;}
        .ticket-card .ticket-meta .status-badge {font-size: 0.9em;padding: 5px 12px;border-radius: 15px;color: white !important;font-weight: bold;text-transform: capitalize;}
        /* Colores para badges de estado */
        .status-badge.estado-abierto { background-color: #ffc107; color: #212529 !important; }
        .status-badge.estado-respondido { background-color: #28a745; }
        .status-badge.estado-cerrado { background-color: #6c757d; }

        .ticket-card .ticket-content p {font-size: 0.95em;color: #555;line-height: 1.6;margin-bottom: 8px;white-space: pre-wrap;}
        .ticket-card .ticket-content strong { color: #333; }

        .ticket-responses {margin-top: 15px;padding-top: 15px;border-top: 1px dashed #e0e0e0;}
        .ticket-responses h4 {margin-top: 0;margin-bottom: 10px;font-size: 1em;color: #555;}
        .ticket-responses .response-item {font-size: 0.9em;color: #444;background-color: #f9f9f9;padding: 10px;border-radius: 5px;margin-bottom: 8px;white-space: pre-wrap;}
        
        .ticket-card form {margin-top: 20px;padding-top: 15px;border-top: 1px dashed #e0e0e0;}
        .ticket-card textarea {width: 100%;min-height: 100px;padding: 10px;border: 1px solid #ccc;border-radius: 5px;box-sizing: border-box;font-size: 0.95em;margin-bottom: 10px;resize: vertical;}
        .ticket-card .form-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .ticket-card .btn-action {padding: 8px 18px; border: none; border-radius: 5px;font-size: 0.9em; font-weight:bold; cursor: pointer; transition: background-color 0.3s ease;}
        .btn-responder { background-color: #5cb85c; color: white; }
        .btn-responder:hover { background-color: #4cae4c; }
        .btn-cerrar { background-color: #d9534f; color: white; }
        .btn-cerrar:hover { background-color: #c9302c; }
        
        .no-datos { text-align:center;padding:30px;font-size:1.2em;color:#777 }
        .error-message, .success-message { text-align:center;padding:15px;border-radius:5px;margin-bottom:20px;font-size:0.9em;}
        .error-message { color:#d8000c; background-color:#ffdddd; border:1px solid #ffcccc; }
        .success-message { color:#270; background-color:#DFF2BF; border:1px solid #4F8A10; }

        @media (max-width:991.98px){
            .menu-toggle{display:block}
            .menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}
            .menu.active{display:flex}
            .menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}
            .menu a:last-child{border-bottom:none}
            .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}
            .menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}
        }
        @media (max-width:768px){
            .logo img{height:60px}
            .page-header { flex-direction: column; align-items: flex-start; }
            .page-container > .page-header > .page-title{font-size:1.6em}
            .ticket-card h3 {font-size: 1.2em;}
            .ticket-card .ticket-meta { font-size: 0.75em;}
            .ticket-card .ticket-content p { font-size: 0.9em;}
            .ticket-card .form-actions { flex-direction:column; }
            .ticket-card .btn-action { width:100%; margin-bottom: 8px; }
            .ticket-card .btn-action:last-child { margin-bottom:0; }
        }
        @media (max-width:480px){
            .logo img{height:50px}
            .menu-toggle{font-size:1.6rem}
            .page-container > .page-header > .page-title{font-size:1.4em}
            .ticket-card h3 {font-size: 1.1em;}
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <!-- Ajusta la ruta a tu logo -->
            <img src="../../img/logo.png" alt="logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <!-- Ajusta las rutas del menú -->
            <a href="admin_dashboard.php">Inicio Admin</a> 
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="admin_manage_trat_pred.php">Tratamientos Pred.</a>
            <a href="view_all_animals.php">Ver Animales</a> 
            <a href="manage_tickets.php" class="active">Gestionar Tickets</a>
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <div class="page-header">
            <h2 class="page-title">Gestionar Tickets de Soporte</h2>
            <?php if (!empty($tickets)): ?>
                <a href="admin_generar_reporte_tickets.php" class="btn-reporte" target="_blank">Generar Reporte de Tickets</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_success)): ?>
            <p class="success-message"><?php echo htmlspecialchars($mensaje_success); ?></p>
        <?php endif; ?>

        <div class="tickets-wrapper">
            <?php if (empty($tickets) && empty($mensaje_error)): ?>
                <div class="no-datos">
                    <p>No hay tickets para gestionar en este momento.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-card estado-<?php echo strtolower(htmlspecialchars($ticket['estado_ticket'])); ?>">
                        <h3><?php echo htmlspecialchars($ticket['asunto']); ?></h3>
                        <div class="ticket-meta">
                            <span>De: <?php echo htmlspecialchars($ticket['nombre_usuario']); ?> (<?php echo htmlspecialchars($ticket['email_usuario']); ?>)</span>
                            <span>Fecha: <?php echo htmlspecialchars($ticket['fecha_creacion_f']); ?></span>
                            <span class="status-badge estado-<?php echo strtolower(htmlspecialchars($ticket['estado_ticket'])); ?>">
                                <?php echo htmlspecialchars($ticket['estado_ticket']); ?>
                            </span>
                        </div>
                        <div class="ticket-content">
                            <strong>Mensaje del Usuario:</strong>
                            <p><?php echo nl2br(htmlspecialchars($ticket['mensaje_usuario'])); ?></p>
                        </div>

                        <?php if ($ticket['respuestas_concatenadas']): ?>
                            <div class="ticket-responses">
                                <h4>Historial de Respuestas:</h4>
                                <div class="response-item">
                                    <?php echo $ticket['respuestas_concatenadas']; // Ya viene con formato HTML, no escapar ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($ticket['estado_ticket'] === 'Abierto' || $ticket['estado_ticket'] === 'Respondido'): ?>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <input type="hidden" name="id_ticket" value="<?php echo $ticket['id_ticket']; ?>">
                                <div class="form-group">
                                    <label for="mensaje_admin_<?php echo $ticket['id_ticket']; ?>">Tu Respuesta:</label>
                                    <textarea name="mensaje_admin" id="mensaje_admin_<?php echo $ticket['id_ticket']; ?>" placeholder="Escribe tu respuesta aquí..." required rows="4"></textarea>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="responder_ticket" class="btn-action btn-responder">Enviar Respuesta</button>
                                    <button type="submit" name="cerrar_ticket" class="btn-action btn-cerrar" onclick="return confirm('¿Estás seguro de que quieres cerrar este ticket? Un ticket cerrado no puede ser reabierto por el usuario.');">Cerrar Ticket</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    const isExpanded = mainMenu.classList.contains('active');
                    menuToggleBtn.setAttribute('aria-expanded', isExpanded);
                });
            }
        });
    </script>
</body>
</html>