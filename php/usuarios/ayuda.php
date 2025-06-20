<?php
session_start();
require_once '../conexion.php'; // $pdo

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../pages/auth/login.html"); // Asumiendo login.html está en la raíz del proyecto
    exit();
}

$id_usuario_actual = $_SESSION['id_usuario'];
$mensaje_formulario = '';
$error_formulario = false;
$tickets_usuario = [];
$mensaje_general_error = ''; // Inicializar para evitar notice

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
                $_POST = array(); 
            } else {
                $mensaje_formulario = "Error al enviar tu pregunta. Inténtalo de nuevo.";
                $error_formulario = true;
            }
        } catch (PDOException $e) {
            $mensaje_formulario = "Error de base de datos: " . $e->getMessage();
            $error_formulario = true;
        }
    }
}

// Cargar tickets existentes del usuario
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
                        ORDER BY t.ultima_actualizacion DESC";
    $stmt_get = $pdo->prepare($sql_get_tickets);
    $stmt_get->bindParam(':id_usuario', $id_usuario_actual);
    $stmt_get->execute();
    $tickets_usuario = $stmt_get->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $mensaje_general_error = "Error al cargar tus preguntas anteriores: " . $e->getMessage();
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
    <title>Ayuda y Soporte - GAG</title>
    <!-- <link rel="stylesheet" href="../css/estilos.css"> --> <!-- Comentado porque los estilos se incluyen abajo -->
    <style>
        /* Estilos generales */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            font-size: 16px; 
        }

        /* Cabecera */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            background-color: #e0e0e0;
            border-bottom: 2px solid #ccc;
            position: relative; 
        }

        .logo img {
            height: 70px; 
            transition: height 0.3s ease;
        }

        .menu {
            display: flex;
            align-items: center;
        }

        .menu a {
            margin: 0 5px;
            text-decoration: none;
            color: black; 
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            transition: background-color 0.3s, color 0.3s, padding 0.3s ease;
            white-space: nowrap;
            font-size: 0.9em;
        }

        .menu a.active,
        .menu a:hover {
            background-color: #88c057; 
            color: white !important;    
            border-color: #70a845;   
        }

        .menu a.exit { 
            background-color: #ff4d4d;
            color: white !important;
            border: 1px solid #cc0000;
        }
        .menu a.exit:hover {
            background-color: #cc0000;
            color: white !important;
        }

        /* Botón del menú hamburguesa */
        .menu-toggle {
            display: none; 
            background: none;
            border: none;
            font-size: 1.8rem; 
            color: #333;     
            cursor: pointer;
            padding: 5px;
        }
        
        /* Contenedor principal de la página */
        .page-container { 
            max-width: 850px; 
            margin: 20px auto;
            padding: 20px;
        }
        .page-container > h2.page-title { 
            text-align: center;
            color: #4caf50;
            margin-bottom: 30px;
            font-size: 2em;
        }

        .ayuda-container { 
            padding: 25px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .ayuda-section {
            margin-bottom: 40px; 
            padding-bottom: 25px;
            border-bottom: 1px solid #f0f0f0; 
        }
        .ayuda-section:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        .ayuda-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333; 
            font-size: 1.4em; 
            border-bottom: 2px solid #4caf50; 
            padding-bottom: 8px;
            display: inline-block; 
        }
        .form-group { margin-bottom: 18px; } 
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            border-color: #4caf50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.20);
            outline: none;
        }
        .form-group textarea { min-height: 140px; resize: vertical; }
        
        .btn-submit { 
            padding: 12px 25px; 
            background-color: #4CAF50; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            font-size: 1em; 
            font-weight: bold;
            cursor: pointer; 
            transition: background-color 0.3s ease; 
        }
        .btn-submit:hover { background-color: #45a049; }
        
        .mensaje { padding: 12px; margin-bottom: 20px; border-radius: 5px; font-size: 0.9em; text-align: center; }
        .mensaje.exito { background-color: #e8f5e9; color: #387002; border: 1px solid #c8e6c9; }
        .mensaje.error { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        .ticket-lista { list-style: none; padding: 0; }
        .ticket-item {
            background-color: #fdfdfd; 
            border: 1px solid #e9e9e9;
            border-left: 4px solid #4caf50; 
            border-radius: 6px;
            padding: 18px;
            margin-bottom: 18px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .ticket-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            margin-bottom: 10px; 
            flex-wrap: wrap; 
            gap: 10px; 
        }
        .ticket-asunto { 
            font-weight: bold; 
            color: #333; 
            font-size: 1.15em; 
            flex-grow: 1; 
            margin-right: 10px; 
        }
        .ticket-estado {
            font-size: 0.8em; 
            padding: 5px 10px; 
            border-radius: 15px;
            color: white !important; 
            font-weight: bold;
            white-space: nowrap; 
            flex-shrink: 0; 
        }
        .estado-abierto { background-color: #ffc107 !important; }
        .estado-respondido { background-color: #28a745 !important; }
        .estado-cerrado { background-color: #6c757d !important; }
        
        .ticket-fecha { font-size: 0.8em; color: #888; width: 100%; margin-bottom: 10px;}
        .ticket-mensaje, .ticket-respuesta {
            margin-top: 12px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
            font-size: 0.95em;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .ticket-mensaje p, .ticket-respuesta p { margin: 0; }
        .ticket-mensaje strong, .ticket-respuesta strong {
            display: block;
            margin-bottom: 6px;
            color: #555;
            font-size: 0.9em;
        }
        .ticket-respuesta { 
            border-left: 4px solid #28a745; 
            background-color: #e8f5e9; 
            margin-top:18px; 
        }
        .no-tickets { text-align: center; color: #777; padding: 30px 0; font-size: 1.1em; }

        /* --- INICIO DE ESTILOS RESPONSIVOS --- */
        @media (max-width: 991px) { /* Breakpoint para tablets y móviles grandes */
            .menu-toggle {
                display: block; /* Mostrar el botón hamburguesa */
            }
            .menu {
                display: none; /* Ocultar el menú normal */
                flex-direction: column;
                align-items: stretch;
                position: absolute;
                top: 100%; 
                left: 0;
                width: 100%;
                background-color: #e9e9e9; 
                padding: 0;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                z-index: 1000;
                border-top: 1px solid #ccc;
            }
            .menu.active { display: flex; } /* Mostrar menú cuando tiene clase .active */
            .menu a {
                margin: 0; padding: 15px 20px; width: 100%; text-align: left;
                border: none; border-bottom: 1px solid #d0d0d0; border-radius: 0;
                color: #333;
            }
            .menu a:last-child { border-bottom: none; }
            .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: transparent; }
            .menu a.exit, .menu a.exit:hover { background-color: #ff4d4d; color: white !important; }
            
            /* Ajustes para el contenido de ayuda en tabletas */
            .page-container { margin: 15px; padding: 15px; }
            .page-container > h2.page-title { font-size: 1.6em; margin-bottom: 20px; }
            .ayuda-container { padding: 20px; }
            .ayuda-section h3 { font-size: 1.25em; }
            .btn-submit { width: 100%; padding: 12px; }
            .ticket-asunto { font-size: 1.05em; }
            .ticket-estado { font-size: 0.75em; padding: 4px 8px; }
        }

        @media (max-width: 480px) { /* Móviles pequeños */
             .logo img { height: 60px; }
            .menu-toggle { font-size: 1.6rem; }
            
            .page-container { margin: 10px; padding: 10px; }
            .page-container > h2.page-title { font-size: 1.4em; }
            .ayuda-container { padding: 15px; }
            .ayuda-section h3 { font-size: 1.15em; }
            .ticket-item { padding: 12px; }
            .ticket-asunto { font-size: 1em; }
            .ticket-estado { font-size: 0.7em; padding: 3px 7px; }
            .ticket-mensaje, .ticket-respuesta { font-size: 0.9em; }
            .form-group input[type="text"], .form-group textarea { padding: 10px; }
        }
        /* --- FIN DE ESTILOS RESPONSIVOS --- */
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="Logo GAG" /> <!-- Ajusta esta ruta -->
        </div>
        <!-- Botón Hamburguesa -->
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">
            ☰ <!-- Icono de hamburguesa -->
        </button>
        <nav class="menu" id="mainMenu"> <!-- Contenedor del menú con ID -->
            <a href="../index.php">Inicio</a>
            <a href="cultivos/miscultivos.php">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario.php">Calendario</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php" class="active">Ayuda</a> <!-- Asumiendo que esta es ayuda.php -->
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h2 class="page-title">Ayuda y Soporte</h2>
        <div class="ayuda-container">

            <section class="ayuda-section">
                <h3>Enviar una Nueva Pregunta</h3>
                <?php if (!empty($mensaje_formulario)): ?>
                    <p class="mensaje <?php echo $error_formulario ? 'error' : 'exito'; ?>"><?php echo htmlspecialchars($mensaje_formulario); ?></p>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="form-group">
                        <label for="asunto">Asunto:</label>
                        <input type="text" id="asunto" name="asunto" value="<?php echo isset($_POST['asunto']) && $error_formulario ? htmlspecialchars($_POST['asunto']) : ''; ?>" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label for="mensaje_pregunta">Tu Pregunta:</label>
                        <textarea id="mensaje_pregunta" name="mensaje_pregunta" rows="6" required><?php echo isset($_POST['mensaje_pregunta']) && $error_formulario ? htmlspecialchars($_POST['mensaje_pregunta']) : ''; ?></textarea>
                    </div>
                    <button type="submit" name="enviar_pregunta" class="btn-submit">Enviar Pregunta</button>
                </form>
            </section>

            <section class="ayuda-section">
                <h3>Mis Preguntas Anteriores</h3>
                <?php if (!empty($mensaje_general_error)): ?>
                     <p class="mensaje error"><?php echo htmlspecialchars($mensaje_general_error); ?></p>
                <?php endif; ?>

                <?php if (empty($tickets_usuario) && empty($mensaje_general_error)): ?>
                    <p class="no-tickets">No has enviado ninguna pregunta todavía.</p>
                <?php elseif (!empty($tickets_usuario)): ?>
                    <ul class="ticket-lista">
                        <?php foreach ($tickets_usuario as $ticket): ?>
                            <li class="ticket-item">
                                <div class="ticket-header">
                                    <span class="ticket-asunto"><?php echo htmlspecialchars($ticket['asunto']); ?></span>
                                    <span class="ticket-estado estado-<?php echo strtolower(htmlspecialchars($ticket['estado_ticket'])); ?>">
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
                                        <strong>Respuesta de <?php echo htmlspecialchars($ticket['nombre_admin'] ?: 'Soporte GAG'); ?> (<?php echo $ticket['fecha_respuesta_f']; ?>):</strong>
                                        <p><?php echo nl2br(htmlspecialchars($ticket['mensaje_admin'])); ?></p>
                                    </div>
                                <?php elseif ($ticket['estado_ticket'] == 'Abierto'): ?>
                                    <p style="font-style: italic; color: #777; margin-top:15px; font-size:0.9em;">Esperando respuesta del administrador...</p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div> <!-- Fin ayuda-container -->
    </div> <!-- Fin page-container -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- LÓGICA PARA EL MENÚ HAMBURGUESA ---
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