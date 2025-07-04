<?php 
// Iniciar sesión al principio de todo para poder acceder a las variables de sesión
session_start(); 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - GAG</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Estilos para los mensajes de error del servidor (opcional, para que destaquen) */
        .server-feedback {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            border: 1px solid transparent;
        }
        .server-feedback.error {
            background-color: #f2dede;
            color: #a94442;
            border-color: #ebccd1;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-logo">
            <img src="../../img/logo.png" alt="Logo GAG" class="logo-navbar">
            <span class="navbar-title">Gestión de Agricultura y Ganadería en el Tolima</span>
        </div>
        <button class="navbar-toggle" aria-label="Abrir menú">
            ☰
        </button>
        <div class="navbar-links" id="navbarLinks">
            <a href="../home/index.html">Inicio</a>
            <a href="../home/misionvision.html">Misión y Visión</a>
            <a href="../info/quienessomos.html">¿Quiénes Somos?</a>
            <a href="../home/contacto.html">Contáctanos</a>
            <a href="login.html">Iniciar Sesión</a> <!-- Cambiado a .php por consistencia -->
        </div>
    </nav>

    <!-- Botón de Volver -->
    <button class="back-button" onclick="history.back()">
        <i class="fas fa-arrow-left"></i> Volver
    </button>

    <!-- Contenedor principal con carrusel de fondo -->
    <div class="register-container">
        <!-- Carrusel de fondo -->
        <div class="background-carousel">
            <img src="../../img/imagen1.png" alt="Imagen 1" class="visible">
            <img src="../../img/imagen2.png" alt="Imagen 2">
            <img src="../../img/imagen3.jpg" alt="Imagen 3">
        </div>

        <!-- Contenido Principal -->
        <div class="main-content centered-content">
            <h2>Crear Cuenta</h2>

            <!-- ===== NUEVO BLOQUE PHP PARA MOSTRAR MENSAJES DEL SERVIDOR ===== -->
            <?php
            if (isset($_SESSION['error_registro'])) {
                // Muestra el mensaje de error si existe en la sesión
                echo '<div class="server-feedback error">' . htmlspecialchars($_SESSION['error_registro']) . '</div>';
                // Limpia la variable de sesión para que no se muestre de nuevo al recargar
                unset($_SESSION['error_registro']);
            }
            ?>
            <!-- ============================================================= -->

            <form action="../../php/procesar_registro.php" method="POST" class="login-form">
                <div class="form-content">
                    <label for="nombre">Nombre:</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ingrese su nombre" required>

                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email" placeholder="Ingrese su correo electrónico" required>

                    <label for="contrasena">Contraseña:</label>
                    <input type="password" id="contrasena" name="contrasena" placeholder="Ingrese su contraseña" required>

                    <label for="confirm_password">Confirmar Contraseña:</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirme su contraseña" required>
                </div>
                <!-- Este contenedor es para los mensajes de validación de JavaScript en tiempo real -->
                <div class="error-container">
                    <div class="error-message validation-feedback"></div>
                </div>
                <button type="submit">Registrarse</button>
            </form>
        </div>
    </div>

    <!-- Pie de Página -->
    <footer class="footer">
        <div class="footer-social">
            <a href="https://www.facebook.com" target="_blank"><img src="../../icons/facebook.webp" alt="Facebook"></a>
            <a href="https://www.instagram.com" target="_blank"><img src="../../icons/instagram.png" alt="Instagram"></a>
            <a href="https://www.twitter.com" target="_blank"><img src="../../icons/twitter.png" alt="Twitter"></a>
        </div>
        <p>© 2025 GAG - Gestión de Agricultura y Ganadería. Todos los derechos reservados.</p>
    </footer>

    <!-- Scripts de JavaScript (sin cambios) -->
    <script src="../../js/validation.js"></script>
    <script src="../../js/scripts.js"></script>
</body>
</html>