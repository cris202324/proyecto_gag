<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../pages/auth/login.html"); // Sube dos niveles para login.html
    exit();
}

include '../conexion.php'; // Sube un nivel para conexion.php
$id_usuario_actual = $_SESSION['id_usuario'];
$animales_usuario = [];
$mensaje_error = '';
// Puedes añadir aquí lógica para mensajes de éxito/error si implementas borrado o edición

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        $sql = "SELECT 
                    id_animal, 
                    nombre_animal, 
                    tipo_animal, 
                    raza, 
                    fecha_nacimiento, 
                    sexo, 
                    identificador_unico,
                    DATE_FORMAT(fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_formateada
                FROM animales
                WHERE id_usuario = :id_usuario
                ORDER BY fecha_registro DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_usuario', $id_usuario_actual);
        $stmt->execute();
        $animales_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los animales: " . $e->getMessage();
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
    <title>Mis Animales - GAG</title>
    <!-- <link rel="stylesheet" href="../../css/estilos.css"> --> <!-- Comentado, estilos inline -->
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
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .page-container > h2.page-title { 
            text-align: center;
            color: #4caf50; 
            margin-bottom: 25px;
            font-size: 1.8em;
        }

        /* Estilos para mis_animales.php */
        .animal-list-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .animal-card {
            background-color: #fff;
            border: 1px solid #ddd;
            border-left: 5px solid #88c057; /* Acento verde GAG */
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .animal-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.12);
        }

        .animal-card h3 {
            margin-top: 0;
            margin-bottom: 12px;
            color: #333; /* Título más oscuro */
            font-size: 1.3em;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .animal-card h3 span.tipo-animal {
            color: #0056b3; /* Diferenciar tipo de animal */
        }
        .animal-card p {
            margin: 6px 0;
            font-size: 0.95em;
            line-height: 1.5;
            color: #555;
        }
        .animal-card strong {
            color: #222;
            margin-right: 5px;
        }
        
        .btn-add { /* Botón para añadir nuevo animal */
            display: inline-block;
            padding: 12px 25px;
            background-color: #28a745;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            transition: background-color 0.3s;
            margin-bottom: 25px; /* Espacio debajo del botón */
        }
        .btn-add:hover {
            background-color: #218838;
        }

        .action-links {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
            text-align: right;
        }
        .action-links a {
            margin-left: 10px;
            color: #007bff;
            text-decoration: none;
            font-size: 0.9em;
        }
        .action-links a:hover { text-decoration: underline; }
        
        .no-animales { text-align: center; padding: 30px; font-size: 1.2em; color: #777; }
        .error-message { 
            color: #D8000C; text-align: center; width: 100%; padding: 15px; 
            background-color: #FFD2D2; border: 1px solid #D8000C; 
            border-radius: 5px; margin-bottom: 20px; 
        }


        /* --- INICIO DE ESTILOS RESPONSIVOS --- */
        @media (max-width: 991px) { /* Breakpoint para tablets y móviles grandes */
            .menu-toggle { display: block; }
            .menu {
                display: none; flex-direction: column; align-items: stretch; position: absolute;
                top: 100%; left: 0; width: 100%;
                background-color: #e9e9e9; padding: 0;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1000; border-top: 1px solid #ccc;
            }
            .menu.active { display: flex; }
            .menu a {
                margin: 0; padding: 15px 20px; width: 100%; text-align: left;
                border: none; border-bottom: 1px solid #d0d0d0; border-radius: 0; color: #333;
            }
            .menu a:last-child { border-bottom: none; }
            .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: transparent; }
            .menu a.exit, .menu a.exit:hover { background-color: #ff4d4d; color: white !important; }

            .page-container > h2.page-title { font-size: 1.6em; margin-bottom: 20px; }
            .animal-list-container { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
            .animal-card { padding: 15px; }
            .animal-card h3 { font-size: 1.2em; }
        }

        @media (max-width: 767px) { /* Móviles */
            .logo img { height: 60px; }
            .menu-toggle { font-size: 1.6rem; }
            .page-container > h2.page-title { font-size: 1.5em; }
            .animal-list-container { grid-template-columns: 1fr; } /* Una columna */
            .btn-add { width: 100%; box-sizing: border-box; } /* Botón de añadir ocupa todo el ancho */
        }

        @media (max-width: 480px) { /* Móviles pequeños */
            .logo img { height: 50px; }
            .page-container { padding: 10px; }
            .page-container > h2.page-title { font-size: 1.4em; }
            .animal-card h3 { font-size: 1.1em; }
            .animal-card p { font-size: 0.9em; }
        }
        /* --- FIN DE ESTILOS RESPONSIVOS --- */
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="Logo GAG" /> <!-- Sube dos niveles para img -->
        </div>
        <!-- Botón Hamburguesa -->
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">
            ☰ <!-- Icono de hamburguesa -->
        </button>
        <nav class="menu" id="mainMenu"> <!-- Contenedor del menú con ID -->
            <a href="../index.php">Inicio</a> <!-- Sube un nivel para index.php -->
            <a href="../miscultivos.php">Mis Cultivos</a> <!-- Sube un nivel -->
            <a href="mis_animales.php" class="active">Mis Animales</a> <!-- Mismo directorio (animales/) -->
            <a href="../calendario.php">Calendario</a> <!-- Sube un nivel, asumiendo que calendario_general está en php/ -->
            <a href="../configuracion.php">Configuración</a> <!-- Sube un nivel -->
            <a href="../ayuda.php">Ayuda</a> <!-- Sube un nivel -->
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a> <!-- Sube un nivel -->
        </nav>
    </div>

    <div class="page-container">
        <h2 class="page-title">Mis Animales Registrados</h2>
        <div style="text-align: center; margin-bottom: 20px;">
            <a href="crear_animales.php" class="btn-add">Registrar Nuevo Animal</a> <!-- Mismo directorio (animales/) -->
        </div>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <div class="animal-list-container">
            <?php if (empty($mensaje_error) && empty($animales_usuario)): ?>
                <div class="no-animales">
                    <p>Aún no has registrado ningún animal.</p>
                </div>
            <?php elseif (!empty($animales_usuario)): ?>
                <?php foreach ($animales_usuario as $animal): ?>
                    <div class="animal-card">
                        <h3>
                            <span class="tipo-animal"><?php echo htmlspecialchars($animal['tipo_animal']); ?></span>
                            <?php if (!empty($animal['nombre_animal'])): ?>
                                - "<?php echo htmlspecialchars($animal['nombre_animal']); ?>"
                            <?php endif; ?>
                        </h3>
                        <p><strong>Raza:</strong> <?php echo htmlspecialchars($animal['raza'] ?: 'No especificada'); ?></p>
                        <p><strong>Sexo:</strong> <?php echo htmlspecialchars($animal['sexo']); ?></p>
                        <?php if (!empty($animal['fecha_nacimiento'])): ?>
                            <p><strong>F. Nacimiento:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($animal['fecha_nacimiento']))); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($animal['identificador_unico'])): ?>
                            <p><strong>ID Adicional:</strong> <?php echo htmlspecialchars($animal['identificador_unico']); ?></p>
                        <?php endif; ?>
                        <p><small>Registrado el: <?php echo htmlspecialchars($animal['fecha_registro_formateada']); ?></small></p>
                        <div class="action-links">
                            <!-- Futuros enlaces -->
                            <!-- <a href="editar_animal.php?id=<?php echo $animal['id_animal']; ?>">Editar</a> -->
                            <!-- <a href="alimentacion_animal.php?id_animal=<?php echo $animal['id_animal']; ?>">Alimentación</a> -->
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div> <!-- Fin animal-list-container -->
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