<?php
// Inicia la sesión de PHP para poder usar variables de sesión como $_SESSION['id_usuario'].
session_start();

// --- CABECERAS HTTP PARA EVITAR CACHÉ ---
// Estas cabeceras le dicen al navegador que no guarde una copia local (caché) de esta página.
// Es útil para páginas con contenido dinámico para asegurar que siempre se muestra la información más reciente.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT"); // Una fecha en el pasado.

// --- VERIFICACIÓN DE PERMISOS DE ADMINISTRADOR ---
// Comprueba si el usuario ha iniciado sesión (existe 'id_usuario') y si su rol es de administrador (rol ID 1).
// Si alguna de estas condiciones no se cumple, se le redirige a la página de login y el script se detiene.
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    // La ruta de redirección debe ser correcta desde la ubicación de este archivo.
    header("Location: ../../pages/auth/login.html");
    exit(); // Detiene la ejecución del script.
}

// --- INCLUSIÓN DE ARCHIVOS Y DECLARACIÓN DE VARIABLES ---
// Incluye el archivo de conexión a la base de datos, que debería definir la variable $pdo.
require_once '../conexion.php'; 

// Inicializa las variables que se usarán en el script para evitar errores de "variable no definida".
$todos_animales = []; // Array para almacenar los animales de la página actual.
$tipos_de_animales_distintos = []; // Array para el selector de filtro de reportes.
$mensaje_error = ''; // Para mostrar errores de base de datos o de lógica.
$mensaje_exito = ''; // Para mostrar mensajes de éxito (ej. "Animal eliminado correctamente").

// Variables para la paginación.
$total_animales = 0; // Total de animales en la base de datos.
$animales_por_pagina = 7; // Cuántos animales mostrar por página.
$pagina_actual = 1; // La página que se está viendo, por defecto la primera.
$total_paginas = 1; // Total de páginas, calculado más adelante.

// --- MANEJO DE MENSAJES DE SESIÓN (FEEDBACK AL USUARIO) ---
// Comprueba si hay mensajes de éxito o error guardados en la sesión desde otra página (ej. después de borrar un animal).
// Si los hay, los guarda en una variable local y los elimina de la sesión para que no se muestren de nuevo al recargar.
if (isset($_SESSION['mensaje_accion_animal'])) {
    $mensaje_exito = $_SESSION['mensaje_accion_animal'];
    unset($_SESSION['mensaje_accion_animal']);
}
if (isset($_SESSION['error_accion_animal'])) {
    $mensaje_error = $_SESSION['error_accion_animal'];
    unset($_SESSION['error_accion_animal']);
}

// --- LÓGICA PRINCIPAL DE BASE DE DATOS ---
// Verifica si la conexión a la base de datos ($pdo) se estableció correctamente.
if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    // El bloque try-catch maneja posibles errores de base de datos (PDOExceptions) de forma segura.
    try {
        // --- LÓGICA DE PAGINACIÓN ---
        // Primero, se cuenta el número total de animales en la tabla para saber cuántas páginas se necesitarán.
        $sql_count = "SELECT COUNT(*) FROM animales";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute();
        $total_animales = (int)$stmt_count->fetchColumn();
        
        // Se calcula el número total de páginas dividiendo el total de animales por el número de animales por página.
        // `ceil` redondea hacia arriba para asegurar que haya una página para los registros restantes.
        $total_paginas = ceil($total_animales / $animales_por_pagina);
        $total_paginas = $total_paginas < 1 ? 1 : $total_paginas; // Asegura que siempre haya al menos una página.

        // Se obtiene la página actual de la URL (ej. ?pagina=2) y se valida para que esté dentro del rango posible.
        if (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) {
            $pagina_actual = (int)$_GET['pagina'];
            if ($pagina_actual < 1) $pagina_actual = 1; // No puede ser menor que 1.
            elseif ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas; // No puede ser mayor que el total de páginas.
        }
        // Se calcula el 'offset', que es desde qué registro empezar a mostrar en la consulta SQL.
        $offset_actual = ($pagina_actual - 1) * $animales_por_pagina;

        // --- CONSULTA PRINCIPAL PARA OBTENER LOS ANIMALES DE LA PÁGINA ACTUAL ---
        // Se seleccionan los datos de los animales y se unen con la tabla de usuarios para obtener el nombre del propietario.
        // Se usa `LIMIT` y `OFFSET` para obtener solo los registros de la página actual.
        $sql = "SELECT 
                    a.id_animal, a.nombre_animal, a.tipo_animal, a.raza,
                    a.fecha_nacimiento, a.sexo, a.identificador_unico, a.cantidad,
                    DATE_FORMAT(a.fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_f,
                    u.nombre AS nombre_usuario, u.id_usuario AS id_usuario_animal
                FROM animales a
                JOIN usuarios u ON a.id_usuario = u.id_usuario
                ORDER BY a.fecha_registro DESC
                LIMIT :limit OFFSET :offset_val";
        
        $stmt = $pdo->prepare($sql);
        // `bindValue` asigna los valores a los placeholders de la consulta de forma segura para prevenir inyección SQL.
        $stmt->bindValue(':limit', (int) $animales_por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset_val', (int) $offset_actual, PDO::PARAM_INT);
        $stmt->execute();
        // `fetchAll` recupera todas las filas del resultado y las guarda en el array `$todos_animales`.
        $todos_animales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- OBTENER TIPOS DE ANIMALES PARA EL FILTRO DE REPORTE ---
        // Se hace una consulta para obtener una lista de todos los tipos de animales únicos (sin repetir).
        // Esto se usará para poblar el menú desplegable (select) del filtro de reportes.
        $sql_tipos = "SELECT DISTINCT tipo_animal FROM animales ORDER BY tipo_animal ASC";
        $stmt_tipos = $pdo->query($sql_tipos);
        // `fetchAll(PDO::FETCH_COLUMN)` crea un array simple con los valores de la primera columna.
        $tipos_de_animales_distintos = $stmt_tipos->fetchAll(PDO::FETCH_COLUMN);

    } catch (PDOException $e) {
        // Si ocurre un error de base de datos en cualquier punto del bloque 'try', se captura aquí.
        $mensaje_error = "Error al obtener los animales: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Todos los Animales - Admin GAG</title>
    <style>
        /* --- ESTILOS CSS DE LA PÁGINA --- */
        /* Aquí van todos los estilos para dar formato a la página. Incluyen estilos para el layout general,
           la cabecera, el menú, la tabla de datos, los botones, la paginación y la responsividad para móviles.
           Estos estilos están diseñados para ser consistentes con el resto del panel de administración. */

        /* Estilos generales */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f8f1; font-size: 16px; color: #333; }
        
        /* Cabecera y Logo */
        .header { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background-color: #e0e0e0; border-bottom: 2px solid #ccc; position: relative; }
        .logo img { height: 70px; }
        
        /* Menú de Navegación */
        .menu { display: flex; align-items: center; }
        .menu a { margin: 0 5px; text-decoration: none; color: black; padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; transition: background-color 0.3s, color 0.3s; white-space: nowrap; font-size: 0.9em; }
        .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: #70a845; }
        .menu a.exit { background-color: #ff4d4d; color: white !important; border: 1px solid #cc0000; }
        .menu a.exit:hover { background-color: #cc0000; }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.8rem; color: #333; cursor: pointer; padding: 5px; }
        
        /* Contenedor principal de la página */
        .page-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h2.page-title { margin: 0; flex-grow: 1; color: #4caf50; font-size: 1.8em; }
        
        /* Contenedor para botones de reporte */
        .report-actions-container { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .report-actions-container select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-size: 0.9em; }
        .btn-reporte { padding: 9px 15px; color: white !important; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 0.9em; transition: background-color 0.3s; border: none; cursor: pointer; }
        .btn-reporte.general { background-color: #17a2b8; }
        .btn-reporte.general:hover { background-color: #138496; }
        .btn-reporte.filtrado { background-color: #28a745; }
        .btn-reporte.filtrado:hover { background-color: #218838; }

        /* Estilos de la tabla de datos */
        .tabla-container-responsive { display: block; width: 100%; overflow-x: auto; }
        .tabla-datos { width: 100%; min-width: 900px; border-collapse: collapse; margin-bottom: 20px; background-color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.1); border-radius: 8px; overflow: hidden; }
        .tabla-datos th, .tabla-datos td { border-bottom: 1px solid #ddd; padding: 10px; text-align: left; font-size: .85em; word-break: break-word; }
        .tabla-datos th { background-color: #f2f2f2; color: #333; font-weight: 700; }
        .tabla-datos tr:last-child td { border-bottom: none; }
        .tabla-datos tr:hover { background-color: #f1f1f1; }
        .tabla-datos .acciones a { display: inline-block; padding: 5px 10px; margin-right: 5px; margin-bottom: 5px; font-size: 0.8em; text-decoration: none; color: white; border-radius: 4px; border: none; cursor: pointer; }
        .tabla-datos .acciones .btn-editar { background-color: #3498db; }
        .tabla-datos .acciones .btn-borrar { background-color: #e74c3c; }

        /* Estilos de paginación y mensajes */
        .paginacion { /* Estilos para los botones de paginación */ }
        .no-datos { /* Estilo para cuando no hay resultados */ }
        .error-message, .success-message { /* Estilos para mensajes de feedback */ }

        /* Media Queries para responsividad */
        @media (max-width: 991.98px) { 
            .header { padding: 10px 15px; }
            .menu-toggle { display: block; } 
            .menu { display: none; flex-direction: column; align-items: stretch; position: absolute; top: 100%; left: 0; width: 100%; background-color: #f8f9fa; padding: 10px 0; box-shadow: 0 4px 8px rgba(0,0,0,.1); z-index: 1000; border-top: 1px solid #ddd; } 
            .menu.active { display: flex; } 
            .menu a { margin: 5px 10px; padding: 12px 15px; text-align: left; } 
        }
        @media (max-width: 768px) {
            .logo img { height: 60px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .report-actions-container { width: 100%; }
        }
        @media (max-width: 480px) {
            .logo img { height: 50px; }
        }
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML PRINCIPAL --- -->
    
    <!-- Cabecera de la página con logo y menú de navegación -->
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="logo GAG" />
        </div>
        <!-- Botón de menú hamburguesa para móviles -->
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <!-- Menú de navegación principal -->
        <nav class="menu" id="mainMenu">
            <a href="admin_dashboard.php">Inicio Admin</a> 
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="admin_manage_trat_pred.php">Tratamientos Pred.</a>
            <a href="view_all_animals.php" class="active">Ver Animales</a> 
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <!-- Contenedor del contenido principal de la página -->
    <div class="page-container">
        <!-- Encabezado de la página con título y botones de acción -->
        <div class="page-header">
            <h2 class="page-title">Todos los Animales Registrados</h2>
            <!-- Solo se muestran los botones de reporte si hay animales en la base de datos -->
            <?php if (!empty($todos_animales)): ?>
            <div class="report-actions-container">
                <!-- Botón para generar un reporte con TODOS los animales -->
                <a href="admin_generar_reporte_animales_excel.php" class="btn-reporte general" target="_blank">Reporte General</a>
                <!-- Formulario para generar un reporte filtrado por tipo de animal -->
                <form action="admin_generar_reporte_animales_excel.php" method="GET" target="_blank" style="display:inline-flex; gap:10px; align-items:center;">
                    <!-- Menú desplegable con los tipos de animales disponibles -->
                    <select name="tipo_animal" required>
                        <option value="">-- Filtrar por tipo --</option>
                        <?php foreach ($tipos_de_animales_distintos as $tipo): ?>
                            <option value="<?php echo htmlspecialchars($tipo); ?>"><?php echo htmlspecialchars($tipo); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Botón para enviar el formulario y generar el reporte filtrado -->
                    <button type="submit" class="btn-reporte filtrado">Reporte por Tipo</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Muestra mensajes de error o éxito si existen -->
        <?php if (!empty($mensaje_error)): ?> <!-- ... (código para mostrar mensaje de error) ... --> <?php endif; ?>
        <?php if (!empty($mensaje_exito)): ?> <!-- ... (código para mostrar mensaje de éxito) ... --> <?php endif; ?>

        <!-- Lógica para mostrar la tabla de animales o un mensaje de "no hay datos" -->
        <?php if (empty($mensaje_error) && empty($todos_animales)): ?>
            <div class="no-datos"><p>No hay animales registrados en el sistema.</p></div>
        <?php elseif (!empty($todos_animales)): ?>
            <!-- Contenedor para hacer la tabla responsive (scroll horizontal en móviles) -->
            <div class="tabla-container-responsive">
                <table class="tabla-datos">
                    <thead>
                        <tr>
                            <th>ID</th><th>Nombre</th><th>Tipo</th><th>Cantidad</th><th>Raza</th><th>Usuario</th>
                            <th>F. Nac.</th><th>Sexo</th><th>ID Único</th><th>F. Reg.</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Bucle para recorrer y mostrar cada animal en una fila de la tabla -->
                        <?php foreach ($todos_animales as $animal): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($animal['id_animal']); ?></td>
                                <td><?php echo htmlspecialchars($animal['nombre_animal'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($animal['tipo_animal']); ?></td>
                                <td><?php echo htmlspecialchars($animal['cantidad'] ?: 1); ?></td>
                                <td><?php echo htmlspecialchars($animal['raza'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($animal['nombre_usuario']); ?> <br><small>(<?php echo htmlspecialchars($animal['id_usuario_animal']); ?>)</small></td>
                                <td><?php echo $animal['fecha_nacimiento'] ? htmlspecialchars(date("d/m/Y", strtotime($animal['fecha_nacimiento']))) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($animal['sexo']); ?></td>
                                <td><?php echo htmlspecialchars($animal['identificador_unico'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($animal['fecha_registro_f']); ?></td>
                                <td class="acciones">
                                    <!-- Enlaces para editar o borrar el registro -->
                                    <a href="admin_edit_animal.php?id_animal=<?php echo $animal['id_animal']; ?>" class="btn-editar">Editar</a>
                                    <a href="admin_delete_animal.php?id_animal=<?php echo $animal['id_animal']; ?>" class="btn-borrar" 
                                       onclick="return confirm('¿Estás seguro de que deseas eliminar este animal? Esta acción también eliminará los registros de alimentación y medicamentos asociados.');">
                                       Borrar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Muestra los controles de paginación si hay más de una página -->
            <!-- ... (Tu código HTML para la paginación iría aquí) ... -->
        <?php endif; ?>
    </div>
    
    <!-- SCRIPT JAVASCRIPT PARA EL MENÚ HAMBURGUESA -->
    <script>
        // Espera a que todo el HTML de la página esté cargado antes de ejecutar el script.
        document.addEventListener('DOMContentLoaded', function() {
            // Obtiene el botón del menú y el contenedor del menú por sus IDs.
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            // Si ambos elementos existen en la página...
            if (menuToggleBtn && mainMenu) {
                // ...añade un "escuchador de eventos" que se activa cuando se hace clic en el botón.
                menuToggleBtn.addEventListener('click', () => {
                    // Alterna (añade si no está, quita si ya está) la clase 'active' en el menú.
                    // Esta clase es la que controla su visibilidad en el CSS para móviles.
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