<?php
// Inicia la sesión de PHP. Es necesario para usar variables de sesión como $_SESSION['id_usuario'] y $_SESSION['rol'].
session_start();

// --- CABECERAS HTTP PARA EVITAR EL CACHÉ DEL NAVEGADOR ---
// Estas líneas le dicen al navegador que no guarde una copia local (caché) de esta página.
// Esto es útil para asegurar que siempre se muestre la información más actualizada,
// especialmente en paneles de administración donde los datos cambian constantemente.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT"); // Establece una fecha de expiración en el pasado.

// --- VERIFICACIÓN DE PERMISOS DE ADMINISTRADOR ---
// Se comprueba si la variable de sesión 'id_usuario' existe (lo que indica que el usuario ha iniciado sesión).
// También se comprueba si el rol del usuario es de administrador (ID de rol 1).
// Si alguna de estas condiciones no se cumple, se redirige al usuario a la página de inicio de sesión y se detiene la ejecución del script.
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    // La ruta de redirección debe ajustarse según la estructura de tu proyecto.
    header("Location: ../../pages/auth/login.html"); 
    exit(); // Detiene la ejecución para evitar que se cargue el resto de la página.
}

// --- INCLUSIÓN DE LA CONEXIÓN A LA BASE DE DATOS Y DECLARACIÓN DE VARIABLES ---
// Incluye el archivo que establece la conexión con la base de datos y define la variable $pdo.
require_once '../conexion.php'; 

// Inicializa las variables que se usarán a lo largo del script.
// Hacer esto previene errores de "variable no definida" y hace el código más claro.
$todos_cultivos = []; // Array que almacenará los datos de los cultivos a mostrar.
$mensaje_error = ''; // Variable para almacenar mensajes de error.
$total_cultivos = 0; // Contador para el número total de cultivos en la BD.
$cultivos_por_pagina = 7; // Define cuántos registros se mostrarán por página.
$pagina_actual = 1; // La página que se está viendo, por defecto es la primera.
$total_paginas = 1; // El número total de páginas, se calculará más adelante.

// --- LÓGICA DE CONEXIÓN Y CONSULTAS A LA BASE DE DATOS ---
// Se comprueba si la variable $pdo (de 'conexion.php') existe y es válida.
if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    // Se utiliza un bloque try-catch para manejar de forma segura cualquier error que pueda ocurrir durante las consultas a la base de datos.
    try {
        // --- LÓGICA DE PAGINACIÓN ---
        
        // 1. Contar el número total de cultivos en la base de datos.
        $sql_count = "SELECT COUNT(*) FROM cultivos";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute();
        $total_cultivos = (int)$stmt_count->fetchColumn(); // fetchColumn() devuelve el valor de la primera columna del resultado.
        
        // 2. Calcular el número total de páginas necesarias.
        // `ceil()` redondea hacia arriba para asegurarse de que todos los registros tengan una página.
        $total_paginas = ceil($total_cultivos / $cultivos_por_pagina);
        $total_paginas = $total_paginas < 1 ? 1 : $total_paginas; // Asegura que siempre haya al menos una página, incluso si no hay registros.

        // 3. Obtener la página actual desde la URL (ej. ?pagina=2) y validarla.
        if (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) {
            $pagina_actual = (int)$_GET['pagina'];
            // Se asegura de que el número de página esté dentro de los límites válidos.
            if ($pagina_actual < 1) {
                $pagina_actual = 1;
            } elseif ($pagina_actual > $total_paginas) {
                $pagina_actual = $total_paginas;
            }
        }
        // 4. Calcular el 'offset', que es desde qué registro se debe empezar a contar para la consulta SQL.
        $offset_actual = ($pagina_actual - 1) * $cultivos_por_pagina;

        // --- CONSULTA PRINCIPAL PARA OBTENER LOS CULTIVOS DE LA PÁGINA ACTUAL ---
        // Se seleccionan los datos de los cultivos y se unen (JOIN) con otras tablas para obtener información legible (nombres en lugar de IDs).
        // LEFT JOIN se usa para 'estado_cultivo_definiciones' por si un cultivo no tuviera un estado asignado, para que aun así aparezca.
        // `LIMIT` y `OFFSET` se usan para la paginación, mostrando solo un subconjunto de resultados.
        $sql = "SELECT
                    c.id_cultivo, c.fecha_inicio, c.fecha_fin AS fecha_fin_registrada,
                    c.area_hectarea, tc.nombre_cultivo, m.nombre AS nombre_municipio,
                    u.nombre AS nombre_usuario, u.id_usuario AS id_usuario_cultivo,
                    ecd.nombre_estado AS estado_actual_cultivo
                FROM cultivos c
                JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                JOIN municipio m ON c.id_municipio = m.id_municipio
                JOIN usuarios u ON c.id_usuario = u.id_usuario
                LEFT JOIN estado_cultivo_definiciones ecd ON c.id_estado_cultivo = ecd.id_estado_cultivo
                ORDER BY c.fecha_inicio DESC
                LIMIT :limit OFFSET :offset_val";
        
        // Se prepara y ejecuta la consulta de forma segura usando prepared statements para prevenir inyección SQL.
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int) $cultivos_por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset_val', (int) $offset_actual, PDO::PARAM_INT);
        $stmt->execute();
        // `fetchAll(PDO::FETCH_ASSOC)` recupera todas las filas del resultado como un array asociativo.
        $todos_cultivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Si ocurre un error de base de datos dentro del bloque 'try', se captura aquí y se guarda un mensaje de error.
        $mensaje_error = "Error al obtener los cultivos: " . $e->getMessage();
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
    <title>Ver Todos los Cultivos - Admin GAG</title>
    <style>
        /* --- ESTILOS CSS --- */
        /* Aquí se definen todos los estilos visuales de la página.
           Esto incluye el diseño general, la cabecera, el menú, la tabla,
           los botones y las reglas de responsividad para que la página
           se vea bien en diferentes tamaños de pantalla (móviles, tablets, escritorio). */
        
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9; font-size: 16px; color: #333; }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background-color: #e0e0e0; border-bottom: 2px solid #ccc; position: relative; }
        .logo img { height: 70px; }
        .menu { display: flex; align-items: center; }
        .menu a { margin: 0 5px; text-decoration: none; color: black; padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; transition: background-color 0.3s, color 0.3s; white-space: nowrap; font-size: 0.9em; }
        .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: #70a845; }
        .menu a.exit { background-color: #ff4d4d; color: white !important; border: 1px solid #cc0000; }
        .menu a.exit:hover { background-color: #cc0000; }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.8rem; color: #333; cursor: pointer; }
        
        .page-container { max-width: 1100px; margin: 20px auto; padding: 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 10px; }
        .page-header h2.page-title { color: #4caf50; font-size: 1.8em; margin: 0; flex-grow: 1; }
        .report-buttons-container { display: flex; gap: 10px; flex-wrap: nowrap; }
        .btn-reporte { padding: 10px 15px; color: white !important; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 0.85em; transition: background-color 0.3s; white-space: nowrap; border: none; cursor: pointer; text-align: center; }
        .btn-reporte.general { background-color: #17a2b8; }
        .btn-reporte.general:hover { background-color: #138496; }
        .btn-reporte.mes-actual { background-color: #28a745; }
        .btn-reporte.mes-actual:hover { background-color: #218838; }
        
        .tabla-container-responsive { display: block; width: 100%; overflow-x: auto; }
        .tabla-cultivos { width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.1); border-radius: 8px; overflow: hidden; }
        .tabla-cultivos th, .tabla-cultivos td { border-bottom: 1px solid #ddd; padding: 10px 12px; text-align: left; font-size: .85em; }
        .tabla-cultivos th { background-color: #f2f2f2; color: #333; font-weight: 700; }
        .tabla-cultivos tr:last-child td { border-bottom: none; }
        .tabla-cultivos tr:hover { background-color: #f1f1f1; }
        
        .paginacion { text-align: center; margin-top: 30px; padding-bottom: 20px; }
        .paginacion a, .paginacion span { display: inline-block; padding: 8px 14px; margin: 0 4px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #4caf50; font-size: .9em; }
        .paginacion a:hover { background-color: #e8f5e9; }
        .paginacion span.actual { background-color: #4caf50; color: #fff; border-color: #43a047; font-weight: 700; }
        .paginacion span.disabled { color: #aaa; border-color: #ddd; cursor: default; }
        .paginacion span.ellipsis { border: none; padding: 8px 5px; }

        .no-datos { text-align: center; padding: 30px; font-size: 1.2em; color: #777; }
        .error-message { color: #d8000c; padding: 15px; background-color: #ffdddd; border: 1px solid #ffcccc; border-radius: 5px; margin-bottom: 20px; font-size: 0.9em; }

        @media (max-width: 991.98px) {
            .menu-toggle { display: block; }
            .menu { display: none; flex-direction: column; align-items: stretch; position: absolute; top: 100%; left: 0; width: 100%; background-color: #e9e9e9; padding: 0; box-shadow: 0 4px 8px rgba(0,0,0,.1); z-index: 1000; border-top: 1px solid #ccc; }
            .menu.active { display: flex; }
            .menu a { margin: 0; padding: 15px 20px; width: 100%; text-align: left; border: none; border-bottom: 1px solid #d0d0d0; border-radius: 0; color: #333; }
            .menu a:last-child { border-bottom: none; }
            .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; }
        }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .report-buttons-container { width: 100%; }
        }
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML DE LA PÁGINA --- -->

    <!-- Cabecera con logo y menú de navegación -->
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="Logo GAG" />
        </div>
        <!-- Botón de menú para móviles -->
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <!-- Contenedor del menú -->
        <nav class="menu" id="mainMenu">
            <a href="admin_dashboard.php">Inicio Admin</a> 
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php" class="active">Ver Cultivos</a>
            <a href="admin_manage_trat_pred.php">Tratamientos Pred.</a>
            <a href="view_all_animals.php">Ver Animales</a> 
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <!-- Contenido principal de la página -->
    <div class="page-container">
        <!-- Encabezado de la página con título y botones de reporte -->
        <div class="page-header">
            <h2 class="page-title">Todos los Cultivos Registrados</h2>
            <!-- Solo muestra los botones si hay cultivos para reportar -->
            <?php if (!empty($todos_cultivos) && empty($mensaje_error)): ?>
                <div class="report-buttons-container">
                    <a href="admin_generar_reporte_cultivos_excel.php?rango_reporte=general" class="btn-reporte general" target="_blank">Reporte General</a>
                    <a href="admin_generar_reporte_cultivos_excel.php?rango_reporte=mes_actual" class="btn-reporte mes-actual" target="_blank">Reporte Mes Actual</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Muestra un mensaje de error si ocurrió alguno -->
        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <!-- Lógica para mostrar la tabla o un mensaje de "no hay datos" -->
        <?php if (empty($mensaje_error) && empty($todos_cultivos)): ?>
            <div class="no-datos">
                <p>No hay cultivos registrados en el sistema para mostrar.</p>
            </div>
        <?php elseif (!empty($todos_cultivos)): ?>
            <!-- Contenedor para hacer la tabla responsive (scroll horizontal en móviles) -->
            <div class="tabla-container-responsive">
                <table class="tabla-cultivos">
                    <thead>
                        <tr>
                            <th>ID Cultivo</th>
                            <th>Nombre Cultivo</th>
                            <th>Usuario (ID)</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin Estimada</th>
                            <th>Área (ha)</th>
                            <th>Municipio</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Bucle que recorre el array de cultivos y crea una fila <tr> por cada uno -->
                        <?php foreach ($todos_cultivos as $cultivo): ?>
                            <tr>
                                <!-- Se imprime cada dato del cultivo en su celda <td> correspondiente. -->
                                <!-- `htmlspecialchars()` se usa para prevenir ataques XSS, asegurando que cualquier HTML en los datos se muestre como texto. -->
                                <td><?php echo htmlspecialchars($cultivo['id_cultivo']); ?></td>
                                <td><?php echo htmlspecialchars($cultivo['nombre_cultivo']); ?></td>
                                <td><?php echo htmlspecialchars($cultivo['nombre_usuario']); ?> (<?php echo htmlspecialchars($cultivo['id_usuario_cultivo']); ?>)</td>
                                <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_inicio']))); ?></td>
                                <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_fin_registrada']))); ?></td>
                                <td><?php echo htmlspecialchars($cultivo['area_hectarea']); ?></td>
                                <td><?php echo htmlspecialchars($cultivo['nombre_municipio']); ?></td>
                                <td><?php echo htmlspecialchars($cultivo['estado_actual_cultivo'] ?: 'No definido'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Muestra los controles de paginación solo si hay más de una página -->
            <?php if ($total_paginas > 1): ?>
                <div class="paginacion">
                    <!-- Botón "Anterior" -->
                    <?php if ($pagina_actual > 1): ?>
                        <a href="?pagina=<?php echo $pagina_actual - 1; ?>">« Anterior</a>
                    <?php else: ?>
                        <span class="disabled">« Anterior</span>
                    <?php endif; ?>

                    <!-- Números de Página (con lógica para mostrar puntos suspensivos) -->
                    <?php 
                    $rango = 2; 
                    for ($i = 1; $i <= $total_paginas; $i++):
                        if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - $rango && $i <= $pagina_actual + $rango)):
                            if ($i == $pagina_actual): echo '<span class="actual">' . $i . '</span>';
                            else: echo '<a href="?pagina=' . $i . '">' . $i . '</a>'; endif;
                        elseif ($i == $pagina_actual - $rango - 1 || $i == $pagina_actual + $rango + 1):
                            echo '<span class="ellipsis">...</span>';
                        endif;
                    endfor;
                    ?>
                    
                    <!-- Botón "Siguiente" -->
                    <?php if ($pagina_actual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_actual + 1; ?>">Siguiente »</a>
                    <?php else: ?>
                        <span class="disabled">Siguiente »</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
    
    <!-- SCRIPT JAVASCRIPT PARA EL MENÚ RESPONSIVE (HAMBURGUESA) -->
    <script>
        // Se ejecuta cuando el contenido HTML de la página ha sido completamente cargado y parseado.
        document.addEventListener('DOMContentLoaded', function() {
            // Se obtienen los elementos del botón del menú y del contenedor del menú por sus IDs.
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            
            // Se comprueba que ambos elementos existan para evitar errores.
            if (menuToggleBtn && mainMenu) {
                // Se añade un "escuchador de eventos" que se activa cuando el usuario hace clic en el botón.
                menuToggleBtn.addEventListener('click', () => {
                    // La función `toggle` añade la clase 'active' si no está presente, y la quita si ya lo está.
                    // Esta clase es la que controla la visibilidad del menú en el CSS para pantallas pequeñas.
                    mainMenu.classList.toggle('active');
                    
                    // Se actualiza el atributo 'aria-expanded' para mejorar la accesibilidad,
                    // informando a los lectores de pantalla si el menú está desplegado o no.
                    const isExpanded = mainMenu.classList.contains('active');
                    menuToggleBtn.setAttribute('aria-expanded', isExpanded);
                });
            }
        });
    </script>
</body>
</html>