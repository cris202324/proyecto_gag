<?php
session_start();
require_once 'conexion.php'; // $pdo

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php"); // Ajusta esta ruta
    exit();
}

$id_usuario_actual = $_SESSION['id_usuario'];
$mensaje_general = '';
$mensaje_datos = '';
$mensaje_pass = '';
$error_general = false;
$error_datos = false;
$error_pass = false;

// Obtener datos actuales del usuario para el formulario
try {
    $stmt_user = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id_usuario = :id_usuario");
    $stmt_user->bindParam(':id_usuario', $id_usuario_actual);
    $stmt_user->execute();
    $usuario_actual = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$usuario_actual) {
        // Esto no debería pasar si la sesión es válida, pero por si acaso
        $mensaje_general = "Error: No se pudieron cargar los datos del usuario.";
        $error_general = true;
        // Considera redirigir o manejar este error de forma más robusta
    }
} catch (PDOException $e) {
    $mensaje_general = "Error de base de datos al cargar datos: " . $e->getMessage();
    $error_general = true;
}


// Manejar envío del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ---- MANEJO DE ACTUALIZACIÓN DE DATOS PERSONALES ----
    if (isset($_POST['actualizar_datos'])) {
        $nuevo_nombre = trim($_POST['nombre']);
        $nuevo_email = trim($_POST['email']);

        if (empty($nuevo_nombre) || empty($nuevo_email)) {
            $mensaje_datos = "El nombre y el correo electrónico no pueden estar vacíos.";
            $error_datos = true;
        } elseif (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
            $mensaje_datos = "El formato del correo electrónico no es válido.";
            $error_datos = true;
        } else {
            // Verificar si el nuevo email ya existe para OTRO usuario
            try {
                $stmt_check_email = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :id_usuario_actual");
                $stmt_check_email->bindParam(':email', $nuevo_email);
                $stmt_check_email->bindParam(':id_usuario_actual', $id_usuario_actual);
                $stmt_check_email->execute();

                if ($stmt_check_email->fetch()) {
                    $mensaje_datos = "El correo electrónico ingresado ya está en uso por otro usuario.";
                    $error_datos = true;
                } else {
                    // Actualizar datos
                    $sql_update_datos = "UPDATE usuarios SET nombre = :nombre, email = :email WHERE id_usuario = :id_usuario";
                    $stmt_update = $pdo->prepare($sql_update_datos);
                    $stmt_update->bindParam(':nombre', $nuevo_nombre);
                    $stmt_update->bindParam(':email', $nuevo_email);
                    $stmt_update->bindParam(':id_usuario', $id_usuario_actual);

                    if ($stmt_update->execute()) {
                        $mensaje_datos = "¡Datos personales actualizados correctamente!";
                        // Actualizar nombre en sesión si cambió
                        $_SESSION['usuario'] = $nuevo_nombre;
                        // Recargar datos para el formulario
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

    // ---- MANEJO DE CAMBIO DE CONTRASEÑA ----
    elseif (isset($_POST['cambiar_contrasena'])) {
        $pass_actual = trim($_POST['contrasena_actual']);
        $pass_nueva = trim($_POST['contrasena_nueva']);
        $pass_confirmar = trim($_POST['contrasena_confirmar']);

        if (empty($pass_actual) || empty($pass_nueva) || empty($pass_confirmar)) {
            $mensaje_pass = "Todos los campos de contraseña son obligatorios.";
            $error_pass = true;
        } elseif (strlen($pass_nueva) < 6) { // Ejemplo de validación de longitud mínima
            $mensaje_pass = "La nueva contraseña debe tener al menos 6 caracteres.";
            $error_pass = true;
        } elseif ($pass_nueva !== $pass_confirmar) {
            $mensaje_pass = "La nueva contraseña y su confirmación no coinciden.";
            $error_pass = true;
        } else {
            try {
                // Obtener hash de contraseña actual de la BD
                $stmt_get_pass = $pdo->prepare("SELECT contrasena FROM usuarios WHERE id_usuario = :id_usuario");
                $stmt_get_pass->bindParam(':id_usuario', $id_usuario_actual);
                $stmt_get_pass->execute();
                $user_data_pass = $stmt_get_pass->fetch(PDO::FETCH_ASSOC);

                if ($user_data_pass && password_verify($pass_actual, $user_data_pass['contrasena'])) {
                    // Contraseña actual correcta, proceder a actualizar
                    $hash_nueva_contrasena = password_hash($pass_nueva, PASSWORD_DEFAULT);
                    
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
    <title>Configuración de Usuario</title>
    <link rel="stylesheet" href="../css/estilos.css"> <!-- Ajusta ruta -->
    <style>
        /* Estilos específicos para la página de configuración, puedes moverlos a estilos.css */
        .config-container {
            max-width: 700px;
            margin: 30px auto;
            padding: 25px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .config-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .config-section:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        .config-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #4caf50; /* Verde principal */
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #555;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .form-group input:focus {
            border-color: #4caf50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
            outline: none;
        }
        .btn-submit { /* Estilo para botones de submit de formularios */
            padding: 10px 20px;
            background-color: #4CAF50; /* Verde GAG */
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-submit:hover {
            background-color: #45a049; /* Verde más oscuro */
        }
        .mensaje {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-size: 0.9em;
            text-align: center;
        }
        .mensaje.exito {
            background-color: #e8f5e9; /* Verde muy claro */
            color: #387002; /* Verde oscuro */
            border: 1px solid #c8e6c9;
        }
        .mensaje.error {
            background-color: #ffebee; /* Rojo muy claro */
            color: #c62828; /* Rojo oscuro */
            border: 1px solid #ffcdd2;
        }
    </style>
</head>
<body>
    <div class="header"> <!-- Asumiendo que usas las mismas clases de tu 'estilos.css' general -->
        <div class="logo">
            <img src="../img/logo.png" alt="Logo" /> <!-- Ajusta ruta -->
        </div>
        <div class="menu">
            <a href="index.php">Inicio</a>
            <a href="miscultivos.php">Mis cultivos</a>
            <a href="animales/mis_animales.php">Mis animales</a>
            <a href="calendario.php">Calendario y Horarios</a>
            <a href="configuracion.php" class="active">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php">Cerrar sesión</a>
        </div>
    </div>

    <div class="config-container">
        <h2 style="text-align:center; color:#4caf50; margin-bottom:30px;">Configuración de Usuario</h2>

        <?php if (!empty($mensaje_general)): ?>
            <p class="mensaje <?php echo $error_general ? 'error' : 'exito'; ?>"><?php echo htmlspecialchars($mensaje_general); ?></p>
        <?php endif; ?>

        <?php if ($usuario_actual): // Solo mostrar formularios si los datos del usuario se cargaron ?>
        <!-- Sección para Datos Personales -->
        <div class="config-section">
            <h3>Datos Personales</h3>
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
        </div>

        <!-- Sección para Cambiar Contraseña -->
        <div class="config-section">
            <h3>Cambiar Contraseña</h3>
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
        </div>
        <?php endif; // Fin de if ($usuario_actual) ?>
    </div>

</body>
</html>