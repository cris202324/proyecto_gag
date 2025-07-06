<?php
/**
 * @file manage_tickets.php
 * @description Página de administración para gestionar tickets de soporte.
 * Permite a los administradores ver, responder y cerrar tickets enviados por los usuarios.
 * Incluye funcionalidades de seguridad, manejo de base deatos y una interfaz de usuario completa.
 */

// Iniciar la sesión para acceder a las variables de sesión del usuario.
session_start();

// --- Cabeceras de Seguridad ---
// Se envían cabeceras para evitar que el navegador guarde en caché esta página.
// Es una buena práctica para páginas con contenido dinámico y sensible para asegurar que siempre se muestra la información más actualizada.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// --- Verificación de Autenticación y Rol ---
// Se comprueba si el usuario ha iniciado sesión (existe 'id_usuario' en la sesión).
// Además, se verifica que el rol del usuario sea 1 (administrador).
// Si alguna de estas condiciones no se cumple, se redirige al usuario a la página de login.
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    // Redirige a la página de inicio de sesión. Ajusta la ruta si es necesario.
    header("Location: ../../pages/auth/login.html");
    exit(); // Detiene la ejecución del script después de la redirección.
}

// --- Conexión a la Base de Datos ---
// Se incluye el archivo que contiene la lógica para conectarse a la base de datos (objeto PDO $pdo).
require_once '../conexion.php'; 

// --- Inicialización de Variables ---
$tickets = []; // Array para almacenar los tickets obtenidos de la base de datos.
$mensaje_error = ''; // Variable para almacenar mensajes de error que se mostrarán al usuario.
$mensaje_success = ''; // Variable para almacenar mensajes de éxito que se mostrarán al usuario.

// --- Procesamiento de Formularios (Peticiones POST) ---
// Se verifica si la solicitud al servidor es de tipo POST, lo que indica que se ha enviado un formulario (responder o cerrar ticket).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Se obtiene el ID del ticket del formulario. Se usa el operador de fusión de null para evitar errores si no existe.
        $id_ticket = $_POST['id_ticket'] ?? null;
        // Se valida que el ID del ticket sea un número válido.
        if (!$id_ticket || !is_numeric($id_ticket)) {
            throw new Exception("ID de ticket no válido.");
        }

        // --- Lógica para Responder un Ticket ---
        if (isset($_POST['responder_ticket'])) {
            // Se obtiene y limpia el mensaje del administrador. trim() elimina espacios en blanco al inicio y final.
            $mensaje_admin = trim($_POST['mensaje_admin']);

            // Se valida que el mensaje de respuesta no esté vacío.
            if (empty($mensaje_admin)) {
                $mensaje_error = "El mensaje de respuesta es obligatorio.";
            } else {
                // Iniciar una transacción para asegurar la integridad de los datos.
                // O se ejecutan todas las consultas correctamente, o no se ejecuta ninguna.
                $pdo->beginTransaction();

                // 1. Actualizar el estado del ticket a 'Respondido' y la fecha de última actualización.
                $update_ticket = $pdo->prepare("UPDATE tickets_soporte SET estado_ticket = 'Respondido', ultima_actualizacion = CURRENT_TIMESTAMP WHERE id_ticket = :id_ticket");
                $update_ticket->bindParam(':id_ticket', $id_ticket, PDO::PARAM_INT);
                $update_ticket->execute();

                // 2. Insertar la respuesta del administrador en la tabla de respuestas.
                $insert_response = $pdo->prepare("INSERT INTO respuestas_soporte (id_ticket, id_admin, mensaje_admin) VALUES (:id_ticket, :id_admin, :mensaje)");
                $insert_response->bindParam(':id_ticket', $id_ticket, PDO::PARAM_INT);
                $insert_response->bindParam(':id_admin', $_SESSION['id_usuario'], PDO::PARAM_INT); // Se usa el ID del admin logueado.
                $insert_response->bindParam(':mensaje', $mensaje_admin, PDO::PARAM_STR);
                $insert_response->execute();

                // Confirmar la transacción si todo ha ido bien.
                $pdo->commit();
                $mensaje_success = "Respuesta enviada con éxito al ticket ID: " . htmlspecialchars($id_ticket);
            }
        // --- Lógica para Cerrar un Ticket ---
        } elseif (isset($_POST['cerrar_ticket'])) {
            // Actualizar el estado del ticket a 'Cerrado' y la fecha de última actualización.
            $update_ticket = $pdo->prepare("UPDATE tickets_soporte SET estado_ticket = 'Cerrado', ultima_actualizacion = CURRENT_TIMESTAMP WHERE id_ticket = :id_ticket");
            $update_ticket->bindParam(':id_ticket', $id_ticket, PDO::PARAM_INT);
            $update_ticket->execute();
            $mensaje_success = "Ticket ID: " . htmlspecialchars($id_ticket) . " cerrado con éxito.";
        }
    } catch (PDOException $e) {
        // Si ocurre un error de base de datos, revertir la transacción si estaba activa.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje_error = "Error en la operación de base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        // Capturar cualquier otra excepción general.
        $mensaje_error = "Error en la operación: " . $e->getMessage();
    }
}

// --- Obtención de Datos (Peticiones GET o carga inicial de la página) ---
// Se obtienen todos los tickets de la base de datos para mostrarlos.
try {
    // Consulta SQL para obtener los tickets con información del usuario y las respuestas concatenadas.
    $sql = "SELECT 
                t.id_ticket, t.asunto, t.mensaje_usuario, 
                DATE_FORMAT(t.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion_f, -- Formatea la fecha para mejor legibilidad
                t.estado_ticket, t.ultima_actualizacion,
                u.nombre AS nombre_usuario, u.email AS email_usuario,
                -- Concatena todas las respuestas de un ticket en una sola cadena, formateada con HTML.
                GROUP_CONCAT(DISTINCT CONCAT(DATE_FORMAT(r.fecha_respuesta, '%d/%m/%Y %H:%i'), ' (Admin: ', ua.nombre, '):<br>', r.mensaje_admin) ORDER BY r.fecha_respuesta ASC SEPARATOR '<hr style=\"margin:5px 0; border-color: #eee;\">') as respuestas_concatenadas
            FROM tickets_soporte t
            JOIN usuarios u ON t.id_usuario = u.id_usuario -- Une con la tabla de usuarios para obtener el nombre del creador del ticket.
            LEFT JOIN respuestas_soporte r ON t.id_ticket = r.id_ticket -- Une con las respuestas (LEFT JOIN para incluir tickets sin respuesta).
            LEFT JOIN usuarios ua ON r.id_admin = ua.id_usuario -- Une de nuevo con usuarios para obtener el nombre del admin que respondió.
            GROUP BY t.id_ticket, t.asunto, t.mensaje_usuario, t.fecha_creacion, t.estado_ticket, t.ultima_actualizacion, u.nombre, u.email
            -- Ordena los tickets: primero los 'Abiertos', luego 'Respondidos', y finalmente 'Cerrados'.
            -- Dentro de cada estado, se ordenan por la fecha de última actualización descendente.
            ORDER BY FIELD(t.estado_ticket, 'Abierto', 'Respondido', 'Cerrado'), t.ultima_actualizacion DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC); // Obtiene todos los resultados en un array asociativo.
} catch (PDOException $e) {
    $mensaje_error = "Error al obtener los tickets: " . $e->getMessage();
    $tickets = []; // Asegurarse de que $tickets es un array vacío en caso de error.
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Tickets de Soporte - Admin GAG</title>
    <!-- Estilos CSS integrados en la página -->
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

        /* Estilos para responsividad (menu hamburguesa en móvil) */
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
    <!-- Encabezado de la página con logo y menú de navegación -->
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <!-- Enlaces del menú de navegación del administrador -->
            <a href="admin_dashboard.php">Inicio Admin</a> 
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="admin_manage_trat_pred.php">Tratamientos Pred.</a>
            <a href="view_all_animals.php">Ver Animales</a> 
            <a href="manage_tickets.php" class="active">Gestionar Tickets</a> <!-- Enlace activo para la página actual -->
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <!-- Contenedor principal de la página -->
    <div class="page-container">
        <!-- Cabecera de la sección de contenido -->
        <div class="page-header">
            <h2 class="page-title">Gestionar Tickets de Soporte</h2>
            <?php if (!empty($tickets)): // Muestra el botón de generar reporte solo si hay tickets ?>
                <a href="admin_generar_reporte_tickets.php" class="btn-reporte" target="_blank">Generar Reporte de Tickets</a>
            <?php endif; ?>
        </div>

        <?php // Muestra mensajes de error o éxito si existen ?>
        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_success)): ?>
            <p class="success-message"><?php echo htmlspecialchars($mensaje_success); ?></p>
        <?php endif; ?>

        <!-- Contenedor de la lista de tickets -->
        <div class="tickets-wrapper">
            <?php // Si no hay tickets y no hay mensajes de error, muestra un mensaje informativo. ?>
            <?php if (empty($tickets) && empty($mensaje_error)): ?>
                <div class="no-datos">
                    <p>No hay tickets para gestionar en este momento.</p>
                </div>
            <?php else: ?>
                <?php // Itera sobre el array de tickets y muestra cada uno en una "tarjeta". ?>
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-card estado-<?php echo strtolower(htmlspecialchars($ticket['estado_ticket'])); // Clase CSS dinámica según el estado ?>">
                        <h3><?php echo htmlspecialchars($ticket['asunto']); ?></h3>
                        <div class="ticket-meta">
                            <span>De: <?php echo htmlspecialchars($ticket['nombre_usuario']); ?> (<?php echo htmlspecialchars($ticket['email_usuario']); ?>)</span>
                            <span>Fecha: <?php echo htmlspecialchars($ticket['fecha_creacion_f']); ?></span>
                            <span class="status-badge estado-<?php echo strtolower(htmlspecialchars($ticket['estado_ticket'])); // Clase CSS dinámica para la "badge" de estado ?>">
                                <?php echo htmlspecialchars($ticket['estado_ticket']); ?>
                            </span>
                        </div>
                        <div class="ticket-content">
                            <strong>Mensaje del Usuario:</strong>
                            <p><?php echo nl2br(htmlspecialchars($ticket['mensaje_usuario'])); // nl2br convierte saltos de línea en <br> ?></p>
                        </div>

                        <?php // Si existen respuestas, las muestra en una sección de historial. ?>
                        <?php if ($ticket['respuestas_concatenadas']): ?>
                            <div class="ticket-responses">
                                <h4>Historial de Respuestas:</h4>
                                <div class="response-item">
                                    <?php 
                                        // Se imprime directamente porque la consulta SQL ya formateó el contenido con HTML (<br>, <hr>).
                                        // Los datos individuales (nombre, mensaje) ya fueron escapados o son seguros.
                                        echo $ticket['respuestas_concatenadas']; 
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php // Muestra el formulario de respuesta solo si el ticket está 'Abierto' o 'Respondido'. ?>
                        <?php if ($ticket['estado_ticket'] === 'Abierto' || $ticket['estado_ticket'] === 'Respondido'): ?>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); // Envía el formulario a la misma página ?>">
                                <input type="hidden" name="id_ticket" value="<?php echo $ticket['id_ticket']; // Campo oculto con el ID del ticket ?>">
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
    <!-- Script JavaScript para la funcionalidad del menú móvil -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                // Añade un evento de click al botón del menú hamburguesa
                menuToggleBtn.addEventListener('click', () => {
                    // Alterna la clase 'active' en el menú para mostrarlo u ocultarlo
                    mainMenu.classList.toggle('active');
                    // Actualiza el atributo aria-expanded para accesibilidad
                    const isExpanded = mainMenu.classList.contains('active');
                    menuToggleBtn.setAttribute('aria-expanded', isExpanded);
                });
            }
        });
    </script>
</body>
</html>