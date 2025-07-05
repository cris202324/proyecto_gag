<?php
// Inicia la sesión de PHP, necesaria para acceder a variables como $_SESSION['id_usuario'].
session_start();
// Incluye el archivo de conexión a la base de datos, que debe definir la variable $pdo.
require_once '../conexion.php'; 

// --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Si el usuario no ha iniciado sesión, se le redirige a la página de login.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../pages/auth/login.html");
    exit(); // Detiene la ejecución del script.
}

// --- INICIALIZACIÓN DE VARIABLES ---
$id_usuario_actual = $_SESSION['id_usuario'];
// Variables para almacenar mensajes de feedback para el usuario.
$mensaje_general = ''; // Para errores generales (ej. carga de datos).
$mensaje_datos = '';   // Para el formulario de datos personales.
$mensaje_pass = '';    // Para el formulario de cambio de contraseña.
// Banderas de error para aplicar estilos CSS a los mensajes.
$error_general = false;
$error_datos = false;
$error_pass = false;
$usuario_actual = null; // Variable para almacenar los datos del usuario.

// --- OBTENCIÓN DE DATOS ACTUALES DEL USUARIO ---
// Se obtienen los datos del usuario para pre-rellenar los campos del formulario.
try {
    $stmt_user = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id_usuario = :id_usuario");
    $stmt_user->bindParam(':id_usuario', $id_usuario_actual);
    $stmt_user->execute();
    $usuario_actual = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // Si no se encuentra el usuario, se establece un mensaje de error.
    if (!$usuario_actual) {
        $mensaje_general = "Error: No se pudieron cargar los datos del usuario.";
        $error_general = true;
    }
} catch (PDOException $e) {
    // Si ocurre un error de base de datos, se captura y se muestra un mensaje.
    $mensaje_general = "Error de base de datos al cargar datos: " . $e->getMessage();
    $error_general = true;
}


// --- MANEJO DE ENVÍO DE FORMULARIOS (MÉTODO POST) ---
// Este bloque se ejecuta solo si se ha enviado un formulario en la página.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ---- LÓGICA PARA ACTUALIZAR DATOS PERSONALES ----
    // Se verifica si el botón 'actualizar_datos' fue el que envió el formulario.
    if (isset($_POST['actualizar_datos'])) {
        $nuevo_nombre = trim($_POST['nombre']);
        $nuevo_email = trim($_POST['email']);

        // Validaciones de los campos.
        if (empty($nuevo_nombre) || empty($nuevo_email)) {
            $mensaje_datos = "El nombre y el correo electrónico no pueden estar vacíos.";
            $error_datos = true;
        } elseif (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
            $mensaje_datos = "El formato del correo electrónico no es válido.";
            $error_datos = true;
        } else {
            // Si las validaciones pasan, se procede con la lógica de base de datos.
            try {
                // Paso de seguridad: Verificar si el nuevo email ya está en uso por OTRO usuario.
                $stmt_check_email = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :id_usuario_actual");
                $stmt_check_email->bindParam(':email', $nuevo_email);
                $stmt_check_email->bindParam(':id_usuario_actual', $id_usuario_actual);
                $stmt_check_email->execute();

                if ($stmt_check_email->fetch()) {
                    // Si la consulta devuelve un resultado, el email ya está en uso.
                    $mensaje_datos = "El correo electrónico ingresado ya está en uso por otro usuario.";
                    $error_datos = true;
                } else {
                    // Si el email está disponible, se actualizan los datos.
                    $sql_update_datos = "UPDATE usuarios SET nombre = :nombre, email = :email WHERE id_usuario = :id_usuario";
                    $stmt_update = $pdo->prepare($sql_update_datos);
                    $stmt_update->bindParam(':nombre', $nuevo_nombre);
                    $stmt_update->bindParam(':email', $nuevo_email);
                    $stmt_update->bindParam(':id_usuario', $id_usuario_actual);

                    if ($stmt_update->execute()) {
                        $mensaje_datos = "¡Datos personales actualizados correctamente!";
                        $_SESSION['usuario'] = $nuevo_nombre; // Actualiza el nombre en la sesión.
                        // Actualiza los datos en la variable local para que el formulario los muestre inmediatamente.
                        $usuario_actual['nombre'] = $nuevo_nombre;
                        $usuario_actual['email'] = $nuevo_email;
                    } else {
                        $mensaje_datos = "Error al actualizar los datos personales.";
                        $error_datos = true;
                    }
                }
            } catch (PDOException $e) {
                $mensaje_datos = "Error de base de datos: " . $e->getMessage();
                $error_datos = true;
            }
        }
    }
    // ---- LÓGICA PARA CAMBIAR LA CONTRASEÑA ----
    // Se verifica si el botón 'cambiar_contrasena' fue el que envió el formulario.
    elseif (isset($_POST['cambiar_contrasena'])) {
        $pass_actual = trim($_POST['contrasena_actual']);
        $pass_nueva = trim($_POST['contrasena_nueva']);
        $pass_confirmar = trim($_POST['contrasena_confirmar']);

        // Validaciones de los campos de contraseña.
        if (empty($pass_actual) || empty($pass_nueva) || empty($pass_confirmar)) {
            $mensaje_pass = "Todos los campos de contraseña son obligatorios.";
            $error_pass = true;
        } elseif (strlen($pass_nueva) < 6) {
            $mensaje_pass = "La nueva contraseña debe tener al menos 6 caracteres.";
            $error_pass = true;
        } elseif ($pass_nueva !== $pass_confirmar) {
            $mensaje_pass = "La nueva contraseña y su confirmación no coinciden.";
            $error_pass = true;
        } else {
            // Si las validaciones pasan, se procede con la lógica de base de datos.
            try {
                // 1. Obtener la contraseña actual (hasheada) de la base de datos.
                $stmt_get_pass = $pdo->prepare("SELECT contrasena FROM usuarios WHERE id_usuario = :id_usuario");
                $stmt_get_pass->bindParam(':id_usuario', $id_usuario_actual);
                $stmt_get_pass->execute();
                $user_data_pass = $stmt_get_pass->fetch(PDO::FETCH_ASSOC);

                // 2. Verificar si la contraseña actual proporcionada por el usuario coincide con la de la BD.
                if ($user_data_pass && password_verify($pass_actual, $user_data_pass['contrasena'])) {
                    // 3. Si coincide, hashear la nueva contraseña.
                    $hash_nueva_contrasena = password_hash($pass_nueva, PASSWORD_DEFAULT);
                    
                    // 4. Actualizar la contraseña en la base de datos con el nuevo hash.
                    $sql_update_pass = "UPDATE usuarios SET contrasena = :contrasena WHERE id_usuario = :id_usuario";
                    $stmt_update_p = $pdo->prepare($sql_update_pass);
                    $stmt_update_p->bindParam(':contrasena', $hash_nueva_contrasena);
                    $stmt_update_p->bindParam(':id_usuario', $id_usuario_actual);

                    if ($stmt_update_p->execute()) {
                        $mensaje_pass = "¡Contraseña actualizada correctamente!";
                    } else {
                        $mensaje_pass = "Error al actualizar la contraseña.";
                        $error_pass = true;
                    }
                } else {
                    // Si la contraseña actual no coincide.
                    $mensaje_pass = "La contraseña actual ingresada es incorrecta.";
                    $error_pass = true;
                }
            } catch (PDOException $e) {
                $mensaje_pass = "Error de base de datos: " . $e->getMessage();
                $error_pass = true;
            }
        }
    }
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
    <title>Configuración de Usuario - GAG</title>
    <style>
        /* Estilos generales, de cabecera, menú, contenedor y formularios */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9; font-size: 16px; }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background-color: #e0e0e0; border-bottom: 2px solid #ccc; position: relative; }
        .logo img { height: 70px; transition: height 0.3s ease; }
        .menu { display: flex; align-items: center; }
        .menu a { margin: 0 5px; text-decoration: none; color: black; padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; transition: background-color 0.3s, color 0.3s, padding 0.3s ease; white-space: nowrap; font-size: 0.9em; }
        .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: #70a845; }
        .menu a.exit { background-color: #ff4d4d; color: white !important; border: 1px solid #cc0000; }
        .menu a.exit:hover { background-color: #cc0000; color: white !important; }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.8rem; color: #333; cursor: pointer; padding: 5px; }
        .page-container { max-width: 750px; margin: 20px auto; padding: 20px; }
        .page-container > h2.page-title { text-align: center; color: #4caf50; margin-bottom: 30px; font-size: 2em; }
        .config-container { padding: 25px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .config-section { margin-bottom: 35px; padding-bottom: 25px; border-bottom: 1px solid #f0f0f0; }
        .config-section:last-child { margin-bottom: 0; border-bottom: none; }
        .config-section h3 { margin-top: 0; margin-bottom: 20px; color: #333; font-size: 1.3em; border-bottom: 2px solid #4caf50; padding-bottom: 8px; display: inline-block; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 1em; transition: border-color 0.3s, box-shadow 0.3s; }
        .form-group input:focus { border-color: #4caf50; box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.20); outline: none; }
        .btn-submit { padding: 12px 25px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; font-size: 1em; font-weight: bold; cursor: pointer; transition: background-color 0.3s ease; }
        .btn-submit:hover { background-color: #45a049; }
        .mensaje { padding: 12px; margin-bottom: 20px; border-radius: 5px; font-size: 0.9em; text-align: center; }
        .mensaje.exito { background-color: #e8f5e9; color: #387002; border: 1px solid #c8e6c9; }
        .mensaje.error { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        /* Estilos responsivos para tablets y móviles */
        @media (max-width: 991px) {
            .menu-toggle { display: block; }
            .menu { display: none; flex-direction: column; align-items: stretch; position: absolute; top: 100%; left: 0; width: 100%; background-color: #e9e9e9; padding: 0; box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1000; border-top: 1px solid #ccc; }
            .menu.active { display: flex; }
            .menu a { margin: 0; padding: 15px 20px; width: 100%; text-align: left; border: none; border-bottom: 1px solid #d0d0d0; border-radius: 0; color: #333; }
            .menu a:last-child { border-bottom: none; }
            .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: transparent; }
            .menu a.exit, .menu a.exit:hover { background-color: #ff4d4d; color: white !important; }
            .page-container { margin: 15px; padding: 15px; max-width: 100%;}
            .page-container > h2.page-title { font-size: 1.6em; margin-bottom: 20px; }
            .config-container { padding: 20px; }
            .config-section h3 { font-size: 1.2em; }
        }
        @media (max-width: 480px) {
            .logo img { height: 60px; }
            .menu-toggle { font-size: 1.6rem; }
            .page-container { margin: 10px; padding: 10px; }
            .page-container > h2.page-title { font-size: 1.4em; }
            .config-container { padding: 15px; }
            .config-section h3 { font-size: 1.1em; }
            .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"] { padding: 10px; }
            .btn-submit { width: 100%; font-size: 0.95em; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="Logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="../index.php">Inicio</a>
            <a href="cultivos/miscultivos.php">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario.php">Calendario</a>
            <a href="configuracion.php" class="active">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h2 class="page-title">Configuración de Usuario</h2>
        <div class="config-container">

            <!-- Muestra un mensaje de error general si ocurrió un problema al cargar los datos -->
            <?php if (!empty($mensaje_general)): ?>
                <p class="mensaje <?php echo $error_general ? 'error' : 'exito'; ?>"><?php echo htmlspecialchars($mensaje_general); ?></p>
            <?php endif; ?>

            <!-- Solo muestra los formularios si los datos del usuario se cargaron correctamente -->
            <?php if ($usuario_actual): ?>
            <section class="config-section">
                <h3>Datos Personales</h3>
                <!-- Muestra el mensaje de feedback específico para el formulario de datos -->
                <?php if (!empty($mensaje_datos)): ?>
                    <p class="mensaje <?php echo $error_datos ? 'error' : 'exito'; ?>"><?php echo htmlspecialchars($mensaje_datos); ?></p>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario_actual['nombre'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Correo Electrónico:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario_actual['email'] ?? ''); ?>" required>
                    </div>
                    <button type="submit" name="actualizar_datos" class="btn-submit">Actualizar Datos</button>
                </form>
            </section>

            <section class="config-section">
                <h3>Cambiar Contraseña</h3>
                <!-- Muestra el mensaje de feedback específico para el formulario de contraseña -->
                <?php if (!empty($mensaje_pass)): ?>
                    <p class="mensaje <?php echo $error_pass ? 'error' : 'exito'; ?>"><?php echo htmlspecialchars($mensaje_pass); ?></p>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="form-group">
                        <label for="contrasena_actual">Contraseña Actual:</label>
                        <input type="password" id="contrasena_actual" name="contrasena_actual" required>
                    </div>
                    <div class="form-group">
                        <label for="contrasena_nueva">Nueva Contraseña:</label>
                        <input type="password" id="contrasena_nueva" name="contrasena_nueva" required>
                    </div>
                    <div class="form-group">
                        <label for="contrasena_confirmar">Confirmar Nueva Contraseña:</label>
                        <input type="password" id="contrasena_confirmar" name="contrasena_confirmar" required>
                    </div>
                    <button type="submit" name="cambiar_contrasena" class="btn-submit">Cambiar Contraseña</button>
                </form>
            </section>
            <?php else: ?>
                <!-- Mensaje alternativo si los datos del usuario no se pudieron cargar -->
                <?php if (empty($mensaje_general)): ?>
                    <p class="mensaje error">No se pudieron cargar los datos del usuario para configurar.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Script para manejar la funcionalidad del menú hamburguesa en dispositivos móviles.
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