<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../../pages/auth/login.html"); // Sube dos niveles para login.html
    exit();
}

include '../../conexion.php'; // Sube un nivel para conexion.php
$id_usuario_actual = $_SESSION['id_usuario'];
$animales_usuario = [];
$mensaje_error = '';
$mensaje_exito_animal = ''; // Para mensajes de éxito/error específicos de acciones de animales

// Capturar mensajes de otras páginas si existen
if (isset($_SESSION['mensaje_exito_animal'])) {
    $mensaje_exito_animal = $_SESSION['mensaje_exito_animal'];
    unset($_SESSION['mensaje_exito_animal']);
}
if (isset($_SESSION['mensaje_error_animal'])) {
    $mensaje_error = $_SESSION['mensaje_error_animal']; // Usamos $mensaje_error para consistencia
    unset($_SESSION['mensaje_error_animal']);
}


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
                    cantidad,
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
    <style>
        /* Estilos generales */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            font-size: 16px;
            color: #333;
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
            display: flex; /* Para flexbox */
            flex-direction: column; /* Apilar contenido y acciones */
            justify-content: space-between; /* Empujar acciones al fondo */
        }
        .animal-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.12);
        }
        .animal-card-content { /* Nuevo contenedor para el contenido principal */
            flex-grow: 1; /* Permite que este div crezca y empuje las acciones */
        }

        .animal-card h3 {
            margin-top: 0;
            margin-bottom: 12px;
            color: #333;
            font-size: 1.3em;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .animal-card h3 span.tipo-animal {
            color: #0056b3;
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

        .btn-add-container { /* Contenedor para el botón de añadir */
            text-align: center;
            margin-bottom: 25px;
        }
        .btn-add {
            display: inline-block;
            padding: 12px 25px;
            background-color: #28a745;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn-add:hover {
            background-color: #218838;
        }

        .action-links {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
            text-align: right;
            display: flex; /* Para mejor alineación de botones */
            flex-wrap: wrap; /* Para que se ajusten si son muchos */
            gap: 8px; /* Espacio entre botones */
            justify-content: flex-end; /* Alinea a la derecha */
        }
        .action-links a {
            /* Estilo base para los enlaces de acción */
            padding: 6px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85em;
            color: white !important; /* Texto blanco para todos */
            transition: opacity 0.2s;
            background-color: #007bff; /* Azul por defecto */
        }
        .action-links a:hover {
            opacity: 0.85;
        }
        /* Colores específicos para botones de acción */
        .action-links a.link-alimentacion { background-color: #17a2b8; } /* Cian info */
        .action-links a.link-detalles { background-color: #6c757d; } /* Gris secondary */
        .action-links a.link-editar { background-color: #ffc107; color: #212529 !important; } /* Amarillo warning, texto oscuro */
        .action-links a.link-medicamentos { background-color: #dc3545; } /* Rojo danger */


        .no-animales { text-align: center; padding: 30px; font-size: 1.2em; color: #777; }
        .mensaje { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-size: 0.95em; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }


        /* --- INICIO DE ESTILOS RESPONSIVOS --- */
        @media (max-width: 991.98px) {
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

        @media (max-width: 767px) {
            .logo img { height: 60px; }
            .menu-toggle { font-size: 1.6rem; }
            .page-container > h2.page-title { font-size: 1.5em; }
            .animal-list-container { grid-template-columns: 1fr; }
            .btn-add { width: 100%; box-sizing: border-box; }
            .action-links { justify-content: center; } /* Centrar botones de acción en móvil */
            .action-links a { flex-grow: 1; text-align: center; margin-bottom: 5px; } /* Hacerlos ocupar más espacio */
        }

        @media (max-width: 480px) {
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
            <img src="../../../img/logo.png" alt="Logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">
            ☰
        </button>
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

    <div class="page-container">
        <h2 class="page-title">Mis Animales Registrados</h2>
        
        <?php if (!empty($mensaje_exito_animal)): ?>
            <div class="mensaje exito"><?php echo htmlspecialchars($mensaje_exito_animal); ?></div>
        <?php endif; ?>
        <?php if (!empty($mensaje_error)): ?>
            <div class="mensaje error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <div class="btn-add-container">
            <a href="crear_animales.php" class="btn-add">Registrar Nuevo Animal/Lote</a>
        </div>


        <div class="animal-list-container">
            <?php if (empty($mensaje_error) && empty($animales_usuario) && empty($mensaje_exito_animal)): ?>
                <div class="no-animales">
                    <p>Aún no has registrado ningún animal o lote.</p>
                </div>
            <?php elseif (!empty($animales_usuario)): ?>
                <?php foreach ($animales_usuario as $animal): ?>
                    <div class="animal-card">
                        <div class="animal-card-content">
                            <h3>
                                <?php
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
                            <p><strong>Cantidad:</strong> <?php echo htmlspecialchars($animal['cantidad']); ?></p>
                            <p><strong>Raza:</strong> <?php echo htmlspecialchars($animal['raza'] ?: 'No especificada'); ?></p>
                            
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
                        <div class="action-links">
                            <a href="ver_alimentacion.php?id_animal=<?php echo $animal['id_animal']; ?>" class="link-alimentacion">Alimentación</a>
                            <!-- <a href="detalle_animal.php?id_animal=<?php echo $animal['id_animal']; ?>" class="link-detalles">Detalles</a> -->
                            <!-- <a href="editar_animal.php?id_animal=<?php echo $animal['id_animal']; ?>" class="link-editar">Editar</a> -->
                            <!-- <a href="medicamentos_animal.php?id_animal=<?php echo $animal['id_animal']; ?>" class="link-medicamentos">Medicamentos</a> -->
                        </div>
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