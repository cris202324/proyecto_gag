<?php
session_start();
require_once 'conexion.php'; // $pdo

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.html"); // Ajusta ruta si es necesario
    exit();
}
// Opcional: Si quieres que esta página sea solo para admins, descomenta y ajusta:
/*
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    header("Location: index.php"); // O a una página de "no autorizado"
    exit();
}
*/

$precios_mostrados = [];
$mensaje_error_precios = '';
$ultima_actualizacion_general = "No disponible"; 

if (!isset($pdo)) {
    $mensaje_error_precios = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // Obtener la fecha de la consulta más reciente
        $stmt_ultima_act = $pdo->query("SELECT MAX(fecha_consulta_local) as ultima_corrida FROM precios_cultivos_actuales");
        $fecha_ultima_corrida_db = $stmt_ultima_act->fetchColumn();
        if ($fecha_ultima_corrida_db) {
            // Crear objeto DateTime para asegurar el formato y evitar errores con strtotime(null)
            $dt = new DateTime($fecha_ultima_corrida_db);
            $ultima_actualizacion_general = $dt->format("d/m/Y H:i:s"); // Formato más completo
        } else {
            $ultima_actualizacion_general = "Aún no se han sincronizado datos.";
        }


        // Obtener los precios más recientes de tu BD local
        // Muestra el registro más reciente por cada tipo de cultivo y fuente de mercado.
        $sql_precios = "SELECT 
                            tc.nombre_cultivo, 
                            pca.precio_promedio, 
                            pca.unidad, 
                            DATE_FORMAT(pca.fecha_actualizacion_api, '%d/%m/%Y') as fecha_precio,
                            pca.fuente_mercado,
                            pca.nombre_producto_api
                        FROM precios_cultivos_actuales pca
                        JOIN tipos_cultivo tc ON pca.id_tipo_cultivo = tc.id_tipo_cultivo
                        WHERE pca.id_precio IN (
                            SELECT MAX(sub_pca.id_precio) 
                            FROM precios_cultivos_actuales sub_pca
                            GROUP BY sub_pca.id_tipo_cultivo, sub_pca.fuente_mercado 
                            -- Si solo quieres el más reciente sin importar la fuente: GROUP BY sub_pca.id_tipo_cultivo
                        )
                        ORDER BY tc.nombre_cultivo ASC, pca.fuente_mercado ASC, pca.fecha_actualizacion_api DESC";
        
        $stmt_precios = $pdo->query($sql_precios);
        $precios_mostrados = $stmt_precios->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $mensaje_error_precios = "Error al cargar los precios de los cultivos: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Precios Actuales de Cultivos - GAG</title>
    <style>
        /* Estilos generales */
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;} .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;white-space:nowrap;font-size:.9em;transition:background-color .3s, color .3s}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}
        
        .page-container{max-width:950px;margin:20px auto;padding:20px}
        .page-container > h2.page-title{text-align:center;color:#4caf50;margin-bottom:10px;font-size:2em}
        .sub-title {text-align:center;color:#555;margin-bottom:20px;font-size:0.95em;font-style:italic;}
        .info-actualizacion { text-align: right; font-size: 0.8em; color: #888; margin-bottom: 20px;}


        .tabla-datos { width:100%;border-collapse:collapse; margin-top: 15px; margin-bottom:20px;background-color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.1);border-radius:8px;overflow:hidden }
        .tabla-datos th, .tabla-datos td { border-bottom:1px solid #ddd;padding:10px 12px; text-align:left;font-size:.88em; word-break:break-word; }
        .tabla-datos th { background-color:#f2f2f2;color:#333;font-weight:700;border-top:1px solid #ddd }
        .tabla-datos tr:last-child td { border-bottom:none }
        .tabla-datos tr:nth-child(even) { background-color:#f9f9f9 }
        .tabla-datos tr:hover { background-color:#f1f1f1 }
        .tabla-datos td.precio { font-weight: bold; color: #28a745; font-size:0.95em; }
        
        .no-datos { text-align:center;padding:30px;font-size:1.2em;color:#777; background-color:#fff; border-radius:5px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
        .error-message { color:#d8000c;text-align:center;padding:15px;background-color:#ffdddd;border:1px solid #ffcccc;border-radius:5px;margin-bottom:20px; }

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
            .tabla-datos{display:block;overflow-x:auto;white-space:nowrap}
            .tabla-datos th,.tabla-datos td{font-size:.82em;padding:8px 10px}
            .page-container > h2.page-title{font-size:1.6em}
            .sub-title {font-size: 0.9em;}
        }
        @media (max-width:480px){
            .logo img{height:50px}
            .menu-toggle{font-size:1.6rem}
            .page-container > h2.page-title{font-size:1.4em}
            .sub-title {font-size: 0.85em;}
            .tabla-datos th,.tabla-datos td{font-size:.78em}
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo GAG" /> <!-- Ajusta ruta -->
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <!-- Asumiendo que esta página es accesible tanto por admin como por usuario normal -->
            <!-- Si es solo admin, el enlace a Inicio sería admin_dashboard.php -->
            <a href="index.php">Inicio</a> 
            <a href="miscultivos.php">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario_general.php">Calendario</a>
            <a href="precios_actuales.php" class="active">Precios de Cultivos</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h2 class="page-title">Precios de Referencia de Cultivos</h2>
        <p class="sub-title">Información actualizada periódicamente. Los precios pueden variar según el mercado, calidad y otros factores.</p>
        <?php if ($ultima_actualizacion_general): ?>
            <p class="info-actualizacion">Última sincronización de datos: <?php echo $ultima_actualizacion_general; ?></p>
        <?php endif; ?>

        <?php if (!empty($mensaje_error_precios)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error_precios); ?></p>
        <?php endif; ?>

        <?php if (isset($pdo) && empty($mensaje_error_precios) && empty($precios_mostrados)): ?>
            <div class="no-datos">
                <p>No hay información de precios de cultivos disponible en este momento.</p>
                <p><small>El sistema intenta actualizar estos datos periódicamente. Vuelve a consultar más tarde.</small></p>
            </div>
        <?php elseif (!empty($precios_mostrados)): ?>
            <div class="tabla-responsive-container">
                <table class="tabla-datos">
                    <thead>
                        <tr>
                            <th>Cultivo (GAG)</th>
                            <th>Producto (Fuente API)</th>
                            <th>Precio Promedio</th>
                            <th>Unidad</th>
                            <th>Fuente / Mercado</th>
                            <th>Fecha del Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($precios_mostrados as $precio_info): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($precio_info['nombre_cultivo']); ?></td>
                                <td><?php echo htmlspecialchars($precio_info['nombre_producto_api'] ?: 'N/A'); ?></td>
                                <td class="precio">$ <?php echo htmlspecialchars(number_format((float)$precio_info['precio_promedio'], 0, ',', '.')); ?></td>
                                <td><?php echo htmlspecialchars($precio_info['unidad']); ?></td>
                                <td><?php echo htmlspecialchars($precio_info['fuente_mercado']); ?></td>
                                <td><?php echo htmlspecialchars($precio_info['fecha_precio']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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