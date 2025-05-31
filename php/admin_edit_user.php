<?php
session_start();
require_once 'conexion.php'; // $pdo

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    header("Location: ../login.html");
    exit();
}

$mensaje_formulario = '';
$error_formulario = false;
$usuario_a_editar = null;
$id_usuario_para_editar = null; 
$roles_disponibles = [];
$estados_disponibles = [];

// Capturar parámetros de retorno
$pagina_retorno = isset($_REQUEST['pagina']) ? (int)$_REQUEST['pagina'] : 1;
$busqueda_retorno = isset($_REQUEST['buscar']) ? trim($_REQUEST['buscar']) : '';
$url_retorno_lista = "view_users.php?pagina=" . $pagina_retorno . "&buscar=" . urlencode($busqueda_retorno);

// Determinar el ID del usuario a editar
if (isset($_GET['id_usuario']) && !empty(trim($_GET['id_usuario']))) {
    $id_usuario_para_editar = trim($_GET['id_usuario']);
} elseif (isset($_POST['id_usuario_hidden']) && !empty(trim($_POST['id_usuario_hidden']))) {
    $id_usuario_para_editar = trim($_POST['id_usuario_hidden']);
}

if ($id_usuario_para_editar === null) {
    $_SESSION['error_accion_usuario'] = "No se especificó un ID de usuario válido para editar.";
    header("Location: " . $url_retorno_lista);
    exit();
}

// Seguridad: NO permitir que el admin actual EDITE su PROPIO ROL o ESTADO desde esta interfaz.
// Podría editar su nombre/email, pero el rol/estado debería ser manejado por otro admin o con más cuidado.
// Por ahora, si es él mismo, no le dejaremos cambiar rol/estado aquí.
$puede_cambiar_rol_estado = ($id_usuario_para_editar != $_SESSION['id_usuario']);


// --- LÓGICA DE ACTUALIZACIÓN ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_usuario'])) {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    
    // Solo tomar rol y estado de POST si se permite cambiarlos para este usuario
    if ($puede_cambiar_rol_estado) {
        $id_rol_nuevo = (int)$_POST['id_rol'];
        $id_estado_nuevo = (int)$_POST['id_estado'];
    } else {
        // Si no se puede cambiar, mantener los valores actuales del usuario de la BD
        // (esto requiere cargar $usuario_a_editar antes o hacer otra consulta)
        // Por ahora, para simplificar, si está deshabilitado, el valor no cambiará
        // si el campo select está 'disabled'. Pero es mejor obtenerlo de la BD.
        // Vamos a obtenerlos de campos hidden si el select está disabled.
        $id_rol_nuevo = (int)$_POST['id_rol_actual_hidden']; // Necesitaremos añadir este hidden
        $id_estado_nuevo = (int)$_POST['id_estado_actual_hidden']; // Necesitaremos añadir este hidden
    }


    // Validaciones
    if (empty($nombre) || empty($email)) {
        $mensaje_formulario = "El nombre y el correo electrónico no pueden estar vacíos.";
        $error_formulario = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje_formulario = "El formato del correo electrónico no es válido.";
        $error_formulario = true;
    } elseif (!in_array($id_rol_nuevo, [1, 2])) { // Asumiendo roles válidos
        $mensaje_formulario = "Rol no válido seleccionado.";
        $error_formulario = true;
    } elseif (!in_array($id_estado_nuevo, [1, 2])) { // Asumiendo estados válidos
        $mensaje_formulario = "Estado no válido seleccionado.";
        $error_formulario = true;
    } 
    
    if (!$error_formulario) {
        try {
            $stmt_check_email = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :id_usuario_actual");
            $stmt_check_email->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt_check_email->bindParam(':id_usuario_actual', $id_usuario_para_editar, PDO::PARAM_STR);
            $stmt_check_email->execute();

            if ($stmt_check_email->fetch()) {
                $mensaje_formulario = "El correo electrónico ingresado ya está en uso por otro usuario.";
                $error_formulario = true;
            } else {
                $sql_update = "UPDATE usuarios SET nombre = :nombre, email = :email, id_rol = :id_rol_nuevo, id_estado = :id_estado_nuevo 
                               WHERE id_usuario = :id_usuario_a_actualizar";
                // Si $id_usuario_para_editar es el admin actual, NO actualizar su rol desde este POST
                // (ya prevenido por $puede_cambiar_rol_estado y el select disabled)

                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':nombre', $nombre, PDO::PARAM_STR);
                $stmt_update->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt_update->bindParam(':id_rol_nuevo', $id_rol_nuevo, PDO::PARAM_INT);
                $stmt_update->bindParam(':id_estado_nuevo', $id_estado_nuevo, PDO::PARAM_INT);
                $stmt_update->bindParam(':id_usuario_a_actualizar', $id_usuario_para_editar, PDO::PARAM_STR);

                if ($stmt_update->execute()) {
                    if ($stmt_update->rowCount() > 0) {
                        $_SESSION['mensaje_accion_usuario'] = "Usuario '" . htmlspecialchars($nombre) . "' (ID: " . htmlspecialchars($id_usuario_para_editar) . ") actualizado correctamente.";
                    } else {
                        $_SESSION['mensaje_accion_usuario'] = "No se realizaron cambios en el usuario (ID: " . htmlspecialchars($id_usuario_para_editar) . ").";
                    }
                    header("Location: " . $url_retorno_lista);
                    exit();
                } else {
                    $mensaje_formulario = "Error al actualizar el usuario.";
                    $error_formulario = true;
                }
            }
        } catch (PDOException $e) {
            $mensaje_formulario = "Error de base de datos: " . $e->getMessage();
            $error_formulario = true;
        }
    }
}


// --- CARGAR DATOS DEL USUARIO Y ROLES/ESTADOS PARA MOSTRAR EN EL FORMULARIO ---
if (($id_usuario_para_editar && $_SERVER["REQUEST_METHOD"] != "POST") || ($id_usuario_para_editar && $error_formulario && $_SERVER["REQUEST_METHOD"] == "POST")) {
    if (!isset($pdo)) {
         if(empty($mensaje_formulario)) $mensaje_formulario = "Error crítico: No hay conexión a la base de datos.";
         $error_formulario = true;
    } else {
        try {
            $stmt_usuario = $pdo->prepare("SELECT id_usuario, nombre, email, id_rol, id_estado FROM usuarios WHERE id_usuario = :id_usuario");
            $stmt_usuario->bindParam(':id_usuario', $id_usuario_para_editar, PDO::PARAM_STR);
            $stmt_usuario->execute();
            $data_from_db = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

            if (!$data_from_db) {
                $_SESSION['error_accion_usuario'] = "Usuario no encontrado con ID: " . htmlspecialchars($id_usuario_para_editar);
                header("Location: " . $url_retorno_lista);
                exit();
            }
            
            if ($error_formulario && $_SERVER["REQUEST_METHOD"] == "POST") {
                $usuario_a_editar = [
                    'id_usuario' => $id_usuario_para_editar,
                    'nombre' => $_POST['nombre'],
                    'email' => $_POST['email'],
                    'id_rol' => (int)$_POST['id_rol'], // O de id_rol_actual_hidden si estaba disabled
                    'id_estado' => (int)$_POST['id_estado'] // O de id_estado_actual_hidden
                ];
                // Si los campos estaban deshabilitados, $_POST['id_rol'] no se enviará,
                // por lo que necesitamos los campos hidden si queremos mantener el valor.
                if (!$puede_cambiar_rol_estado) {
                    $usuario_a_editar['id_rol'] = (int)$_POST['id_rol_actual_hidden'];
                    // El estado usualmente sí se puede cambiar para el admin logueado, si lo permites.
                    // Aquí asumimos que el estado del admin logueado no se cambia en este form.
                    $usuario_a_editar['id_estado'] = (int)$_POST['id_estado_actual_hidden'];
                }

            } else {
                $usuario_a_editar = $data_from_db;
            }

            // Cargar TODOS los roles disponibles para el dropdown, el admin decide.
            $stmt_roles = $pdo->query("SELECT id_rol, rol FROM rol ORDER BY id_rol");
            $roles_disponibles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

            // Cargar estados disponibles
            $stmt_estados = $pdo->query("SELECT id_estado, descripcion FROM estado ORDER BY id_estado");
            $estados_disponibles = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $mensaje_formulario = "Error al cargar datos iniciales: " . $e->getMessage();
            $error_formulario = true;
            $usuario_a_editar = null; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - Admin GAG</title>
    <style>
        /* Copia los estilos del header, .page-container, formularios, etc. de view_users.php o tu CSS global */
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;} .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;white-space:nowrap;font-size:.9em;transition:background-color .3s, color .3s}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}

        .page-container{max-width:700px;margin:20px auto;padding:20px}
        .page-container > h2.page-title{text-align:center;color:#4caf50;margin-bottom:25px;font-size:1.8em}
        
        .form-container { padding:25px; background-color:#fff; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:600; color:#444; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group select {
            width:100%; padding:12px; border:1px solid #ddd; border-radius:5px;
            box-sizing:border-box; font-size:1em; transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color:#4caf50; box-shadow:0 0 0 0.2rem rgba(76,175,80,0.20); outline:none;
        }
        .btn-submit { padding:12px 25px; background-color:#4CAF50; color:white; border:none; border-radius:5px; font-size:1em; font-weight:bold; cursor:pointer; transition:background-color 0.3s ease; }
        .btn-submit:hover { background-color:#45a049; }
        .btn-cancel { display:inline-block; margin-left:10px; padding:11px 20px; background-color:#777; color:white; text-decoration:none; border-radius:5px; font-size:1em; transition:background-color 0.3s ease; }
        .btn-cancel:hover { background-color:#666; }
        .mensaje { padding:12px; margin-bottom:20px; border-radius:5px; font-size:0.9em; text-align:center; }
        .mensaje.exito { background-color:#e8f5e9; color:#387002; border:1px solid #c8e6c9; }
        .mensaje.error { background-color:#ffebee; color:#c62828; border:1px solid #ffcdd2; }
        select[disabled] { background-color: #eee; opacity: 0.7; cursor: not-allowed; }
        .warning-note { font-size:0.8em; color:#e67e22; margin-top:5px; display:block; }


        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}}
        @media (max-width:768px){.logo img{height:60px} .page-container > h2.page-title{font-size:1.6em} }
        @media (max-width:480px){.logo img{height:50px} .menu-toggle{font-size:1.6rem} .page-container > h2.page-title{font-size:1.4em} }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo"><img src="../img/logo.png" alt="Logo GAG" /></div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="admin_dashboard.php">Inicio</a> 
            <a href="view_users.php" class="active">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="view_all_animals.php">Ver Animales</a>
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h2 class="page-title">Editar Usuario: <?php echo $usuario_a_editar ? htmlspecialchars($usuario_a_editar['nombre']) : 'ID No Válido'; ?></h2>

        <?php if (!empty($mensaje_formulario)): ?>
            <p class="mensaje <?php echo $error_formulario ? 'error' : 'exito'; ?>"><?php echo htmlspecialchars($mensaje_formulario); ?></p>
        <?php endif; ?>

        <?php if ($usuario_a_editar): ?>
            <div class="form-container">
                <form action="admin_edit_user.php" method="POST">
                    <input type="hidden" name="id_usuario_hidden" value="<?php echo htmlspecialchars($usuario_a_editar['id_usuario']); ?>">
                    <input type="hidden" name="pagina" value="<?php echo htmlspecialchars($pagina_retorno); ?>">
                    <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($busqueda_retorno); ?>">
                    
                    <!-- Campos ocultos para rol y estado si el select está deshabilitado -->
                    <?php if (!$puede_cambiar_rol_estado): ?>
                        <input type="hidden" name="id_rol_actual_hidden" value="<?php echo htmlspecialchars($usuario_a_editar['id_rol']); ?>">
                        <input type="hidden" name="id_estado_actual_hidden" value="<?php echo htmlspecialchars($usuario_a_editar['id_estado']); ?>">
                    <?php endif; ?>


                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario_a_editar['nombre']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Correo Electrónico:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario_a_editar['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="id_rol">Rol:</label>
                        <select id="id_rol" name="id_rol" required <?php echo (!$puede_cambiar_rol_estado) ? 'disabled' : ''; ?> >
                            <?php if (!empty($roles_disponibles)): ?>
                                <?php foreach($roles_disponibles as $rol_opt): ?>
                                    <option value="<?php echo $rol_opt['id_rol']; ?>" <?php echo ($rol_opt['id_rol'] == $usuario_a_editar['id_rol']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rol_opt['rol']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php elseif ($usuario_a_editar['id_rol'] == 1): // Si es admin y no hay otras opciones (porque solo se cargó él mismo) ?>
                                <option value="1" selected>Administrador</option>
                            <?php else: ?>
                                <option value="">Error al cargar roles</option>
                            <?php endif; ?>
                        </select>
                        <?php if (!$puede_cambiar_rol_estado): ?>
                            <small class="warning-note">No puedes cambiar el rol de tu propia cuenta de administrador aquí.</small>
                        <?php endif; ?>
                    </div>
                     <div class="form-group">
                        <label for="id_estado">Estado:</label>
                        <select id="id_estado" name="id_estado" required <?php echo (!$puede_cambiar_rol_estado && $usuario_a_editar['id_rol'] == 1) ? 'disabled' : ''; // Deshabilitar estado solo si es el admin logueado ?> >
                             <?php if (!empty($estados_disponibles)): ?>
                                <?php foreach($estados_disponibles as $estado_opt): ?>
                                    <option value="<?php echo $estado_opt['id_estado']; ?>" <?php echo ($estado_opt['id_estado'] == $usuario_a_editar['id_estado']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($estado_opt['descripcion']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                 <option value="">Error al cargar estados</option>
                            <?php endif; ?>
                        </select>
                         <?php if (!$puede_cambiar_rol_estado && $usuario_a_editar['id_rol'] == 1): ?>
                            <small class="warning-note">No puedes cambiar el estado de tu propia cuenta de administrador aquí.</small>
                        <?php endif; ?>
                    </div>
                    <p style="font-size:0.8em; color:#777; margin-top: -10px; margin-bottom: 20px;">Nota: La contraseña se gestiona por separado (ej. "Olvidé mi contraseña" o una sección específica de cambio de contraseña para el usuario).</p>
                    
                    <button type="submit" name="actualizar_usuario" class="btn-submit">Actualizar Usuario</button>
                    <a href="<?php echo htmlspecialchars($url_retorno_lista); ?>" class="btn-cancel">Cancelar</a>
                </form>
            </div>
        <?php elseif(!$error_formulario && $id_usuario_para_editar): ?>
            <p class="mensaje error">No se pudo cargar la información del usuario (ID: <?php echo htmlspecialchars($id_usuario_para_editar);?>) o no tienes permiso para editarlo.</p>
            <a href="<?php echo htmlspecialchars($url_retorno_lista); ?>" class="btn-cancel" style="display:block; text-align:center; width:150px; margin: 20px auto;">Volver</a>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
                });
            }
        });
    </script>
</body>
</html>