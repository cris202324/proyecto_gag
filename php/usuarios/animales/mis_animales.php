<?php
// Inicia la sesión de PHP. Es el primer paso necesario para poder usar variables de sesión
// como $_SESSION['id_usuario'] para la autenticación.
session_start();

// --- CABECERAS HTTP PARA EVITAR CACHÉ DEL NAVEGADOR ---
// Estas cabeceras le indican al navegador que no debe guardar una copia en caché de esta página.
// Esto asegura que el usuario siempre vea la información más actualizada.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT"); // Una fecha en el pasado.

// --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Comprueba si la variable de sesión 'id_usuario' existe. Si no, significa que el usuario
// no ha iniciado sesión, por lo que se le redirige a la página de login y se detiene el script.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../../pages/auth/login.html");
    exit();
}

// --- INCLUSIÓN DE ARCHIVOS Y DECLARACIÓN DE VARIABLES ---
// Incluye el archivo que establece la conexión a la base de datos y define la variable $pdo.
include '../../conexion.php';
// Guarda el ID del usuario actual en una variable para un uso más fácil y claro.
$id_usuario_actual = $_SESSION['id_usuario'];

// Inicializa las variables que se usarán en el script.
$animales_usuario = []; // Array para almacenar la lista de animales del usuario.
$mensaje_error = ''; // Variable para almacenar mensajes de error.
$mensaje_exito_animal = ''; // Variable para mensajes de éxito (ej. "Animal registrado correctamente").

// --- MANEJO DE MENSAJES DE SESIÓN (FEEDBACK AL USUARIO) ---
// Comprueba si hay mensajes guardados en la sesión desde una acción anterior (como registrar o borrar un animal).
// Si existen, los guarda en una variable local y los borra de la sesión para que no se muestren de nuevo.
if (isset($_SESSION['mensaje_exito_animal'])) {
    $mensaje_exito_animal = $_SESSION['mensaje_exito_animal'];
    unset($_SESSION['mensaje_exito_animal']);
}
if (isset($_SESSION['mensaje_error_animal'])) {
    $mensaje_error = $_SESSION['mensaje_error_animal'];
    unset($_SESSION['mensaje_error_animal']);
}

// --- LÓGICA PRINCIPAL DE BASE DE DATOS ---
// Comprueba si la conexión a la base de datos se estableció correctamente.
if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    // El bloque try-catch maneja de forma segura los posibles errores de la base de datos.
    try {
        // Prepara la consulta SQL para seleccionar todos los animales que pertenecen al usuario actual.
        // Se seleccionan todos los campos relevantes y se formatea la fecha de registro para una mejor visualización.
        $sql = "SELECT
                    id_animal, nombre_animal, tipo_animal, raza, fecha_nacimiento,
                    sexo, identificador_unico, cantidad,
                    DATE_FORMAT(fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_formateada
                FROM animales
                WHERE id_usuario = :id_usuario
                ORDER BY fecha_registro DESC"; // Se ordenan por fecha de registro descendente.
        
        $stmt = $pdo->prepare($sql);
        // Se asocia el ID del usuario actual al placeholder :id_usuario para una consulta segura.
        $stmt->bindParam(':id_usuario', $id_usuario_actual);
        // Se ejecuta la consulta.
        $stmt->execute();
        // `fetchAll(PDO::FETCH_ASSOC)` recupera todas las filas del resultado como un array asociativo.
        $animales_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Si ocurre un error durante la consulta, se captura y se guarda un mensaje de error.
        $mensaje_error = "Error al obtener los animales: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Animales - GAG</title>
    <style>
        /* --- ESTILOS CSS --- */
        /* Aquí se definen todos los estilos para la página, incluyendo el layout,
           la cabecera, el menú, las tarjetas de animales, los botones y la responsividad. */
        
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9; font-size: 16px; color: #333; }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background-color: #e0e0e0; border-bottom: 2px solid #ccc; position: relative; }
        .logo img { height: 70px; }
        .menu { display: flex; align-items: center; }
        .menu a { margin: 0 5px; text-decoration: none; color: black; padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; white-space: nowrap; font-size: 0.9em; transition: background-color 0.3s; }
        .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: #70a845; }
        .menu a.exit { background-color: #ff4d4d; color: white !important; border: 1px solid #cc0000; }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.8rem; color: #333; cursor: pointer; }
        
        .page-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .page-container > h2.page-title { text-align: center; color: #4caf50; margin-bottom: 25px; font-size: 1.8em; }
        
        .animal-list-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .animal-card { background-color: #fff; border: 1px solid #ddd; border-left: 10px solid #88c057; border-radius: 8px; padding: 20px; box-shadow: 0 3px 10px rgba(0,0,0,0.08); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s; }
        .animal-card:hover { transform: translateY(-4px); box-shadow: 0 5px 15px rgba(0,0,0,0.12); }
        .animal-card-content { flex-grow: 1; }
        .animal-card h3 { margin-top: 0; margin-bottom: 12px; color: #333; font-size: 1.3em; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .animal-card h3 span.tipo-animal { color: #0056b3; }
        .animal-card p { margin: 6px 0; font-size: 0.95em; line-height: 1.5; color: #555; }
        .animal-card strong { color: #222; }
        
        .action-bar { text-align: center; margin-bottom: 25px; display: flex; flex-direction: column; gap: 15px; align-items: center; }
        .btn-page-action { display: inline-block; padding: 12px 25px; color: white !important; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background-color 0.3s; min-width: 250px; }
        .btn-add { background-color: #28a745; }
        .btn-add:hover { background-color: #218838; }
        .btn-reporte { background-color: #17a2b8; }
        .btn-reporte:hover { background-color: #138496; }
        
        .action-links { margin-top: 15px; padding-top: 10px; border-top: 1px solid #f0f0f0; text-align: right; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
        .action-links a { padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 0.85em; color: white !important; transition: opacity 0.2s; }
        .action-links a:hover { opacity: 0.85; }
        .action-links a.link-alimentacion { background-color: #17a2b8; }
        .action-links a.link-medicamentos { background-color: #dc3545; }
        
        .no-animales { text-align: center; padding: 30px; font-size: 1.2em; color: #777; }
        .mensaje { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-size: 0.95em; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }

        @media (max-width: 991.98px) {
            .menu-toggle { display: block; }
            .menu { display: none; flex-direction: column; align-items: stretch; position: absolute; top: 100%; left: 0; width: 100%; background-color: #e9e9e9; padding: 0; box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1000; border-top: 1px solid #ccc; }
            .menu.active { display: flex; }
            .menu a { margin: 0; padding: 15px 20px; width: 100%; text-align: left; border: none; border-bottom: 1px solid #d0d0d0; border-radius: 0; color: #333; }
            .menu a:last-child { border-bottom: none; }
            .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; }
        }
        @media (max-width: 767px) { .animal-list-container { grid-template-columns: 1fr; } .btn-page-action { width: 100%; box-sizing: border-box; } }
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML DE LA PÁGINA --- -->

    <!-- Cabecera con logo y menú de navegación -->
    <div class="header">
        <div class="logo">
            <img src="../../../img/logo.png" alt="Logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="../../index.php">Inicio</a>
            <a href="../cultivos/miscultivos.php">Mis Cultivos</a>
            <a href="mis_animales.php" class="active">Mis Animales</a>
            <a href="../calendario.php">Calendario</a>
            <a href="../configuracion.php">Configuración</a>
            <a href="../ayuda.php">Ayuda</a>
            <a href="../../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <!-- Contenido principal de la página -->
    <div class="page-container">
        <h2 class="page-title">Mis Animales Registrados</h2>
        
        <!-- Muestra mensajes de éxito o error si existen. -->
        <?php if (!empty($mensaje_exito_animal)): ?>
            <div class="mensaje exito"><?php echo htmlspecialchars($mensaje_exito_animal); ?></div>
        <?php endif; ?>
        <?php if (!empty($mensaje_error)): ?>
            <div class="mensaje error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <!-- Barra de acciones principales de la página. -->
        <div class="action-bar">
            <a href="crear_animales.php" class="btn-page-action btn-add">Registrar Nuevo Animal/Lote</a>
            <!-- El botón de reporte solo se muestra si el usuario tiene al menos un animal. -->
            <?php if (!empty($animales_usuario)): ?>
                <a href="generar_reporte_mis_animales.php" class="btn-page-action btn-reporte" target="_blank">
                    Generar Reporte Excel
                </a>
            <?php endif; ?>
        </div>

        <!-- Contenedor de las tarjetas de animales. -->
        <div class="animal-list-container">
            <!-- Lógica para mostrar las tarjetas o un mensaje si no hay animales. -->
            <?php if (empty($mensaje_error) && empty($animales_usuario) && empty($mensaje_exito_animal)): ?>
                <div class="no-animales">
                    <p>Aún no has registrado ningún animal o lote.</p>
                </div>
            <?php elseif (!empty($animales_usuario)): ?>
                <!-- Bucle que recorre el array de animales y crea una tarjeta para cada uno. -->
                <?php foreach ($animales_usuario as $animal): ?>
                    <div class="animal-card">
                        <div class="animal-card-content">
                            <h3>
                                <?php
                                // Lógica para mostrar un título diferente si es un lote o un animal individual.
                                $titulo_principal = '';
                                if ($animal['cantidad'] > 1) {
                                    $titulo_principal = "Lote de " . htmlspecialchars($animal['tipo_animal']);
                                    if (!empty($animal['nombre_animal'])) {
                                        $titulo_principal .= ' "' . htmlspecialchars($animal['nombre_animal']) . '"';
                                    }
                                } else {
                                    $titulo_principal = '<span class="tipo-animal">' . htmlspecialchars($animal['tipo_animal']) . '</span>';
                                    if (!empty($animal['nombre_animal'])) {
                                        $titulo_principal .= ' - "' . htmlspecialchars($animal['nombre_animal']) . '"';
                                    }
                                }
                                echo $titulo_principal;
                                ?>
                            </h3>
                            <!-- Se imprimen los datos del animal. `htmlspecialchars` previene ataques XSS. -->
                            <!-- El operador ternario se usa para mostrar "No especificada" si un campo opcional está vacío. -->
                            <p><strong>Cantidad:</strong> <?php echo htmlspecialchars($animal['cantidad']); ?></p>
                            <p><strong>Raza:</strong> <?php echo htmlspecialchars($animal['raza'] ?: 'No especificada'); ?></p>
                            
                            <!-- Lógica para mostrar sexo y fecha de nacimiento de forma condicional. -->
                            <?php if ($animal['cantidad'] == 1 || !empty($animal['fecha_nacimiento'])): ?>
                                <p><strong>Sexo:</strong> <?php echo htmlspecialchars($animal['sexo']); ?></p>
                                <?php if (!empty($animal['fecha_nacimiento'])): ?>
                                    <p><strong>F. Nacimiento:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($animal['fecha_nacimiento']))); ?></p>
                                <?php endif; ?>
                            <?php elseif ($animal['cantidad'] > 1 && $animal['sexo'] !== 'Desconocido'): ?>
                                 <p><strong>Sexo (Predominante/Lote):</strong> <?php echo htmlspecialchars($animal['sexo']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($animal['identificador_unico'])): ?>
                                <p><strong>ID Adicional/Lote:</strong> <?php echo htmlspecialchars($animal['identificador_unico']); ?></p>
                            <?php endif; ?>
                            <p><small>Registrado el: <?php echo htmlspecialchars($animal['fecha_registro_formateada']); ?></small></p>
                        </div>
                        <!-- Enlaces de acción para cada animal. -->
                        <div class="action-links">
                            <a href="ver_alimentacion.php?id_animal=<?php echo $animal['id_animal']; ?>" class="link-alimentacion">Alimentación</a>
                            <a href="ver_sanidad_animal.php?id_animal=<?php echo $animal['id_animal']; ?>" class="link-medicamentos">Sanidad</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- --- SCRIPT JAVASCRIPT PARA EL MENÚ RESPONSIVE (HAMBURGUESA) --- -->
    <script>
        // Se ejecuta cuando el contenido HTML de la página ha sido completamente cargado.
        document.addEventListener('DOMContentLoaded', function() {
            // Se obtienen los elementos del botón del menú y del contenedor del menú por sus IDs.
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');

            // Se comprueba que ambos elementos existan para evitar errores.
            if (menuToggleBtn && mainMenu) {
                // Se añade un "escuchador de eventos" que se activa cuando el usuario hace clic en el botón.
                menuToggleBtn.addEventListener('click', () => {
                    // Alterna (añade o quita) la clase 'active' en el menú, lo que controla su visibilidad en el CSS.
                    mainMenu.classList.toggle('active');
                    // Actualiza el atributo 'aria-expanded' para mejorar la accesibilidad para lectores de pantalla.
                    const isExpanded = mainMenu.classList.contains('active');
                    menuToggleBtn.setAttribute('aria-expanded', isExpanded);
                });
            }
        });
    </script>
</body>
</html>