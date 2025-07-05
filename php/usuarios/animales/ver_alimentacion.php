<?php
// Inicia la sesión de PHP. Es el primer paso y es crucial para poder usar variables de sesión
// como $_SESSION['id_usuario'] para la autenticación y autorización.
session_start();

// 1. --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Esta es la primera barrera de seguridad. Asegura que solo los usuarios que han iniciado sesión
// puedan acceder a esta página. Si no, los redirige a la página de login.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../pages/auth/login.html");
    exit(); // Detiene la ejecución del script para proteger la página.
}

// 2. --- INCLUSIÓN DE ARCHIVOS Y DEFINICIÓN DE VARIABLES INICIALES ---
include '../../conexion.php'; // Incluye el archivo de conexión a la base de datos, que define la variable $pdo.
$id_usuario_actual = $_SESSION['id_usuario']; // ID del usuario actual, obtenido de la sesión.
$historial_alimentacion = []; // Array que almacenará el historial de alimentación del animal.
$animal_info = null; // Almacenará los datos del animal seleccionado.
$mensaje_pagina = ''; // Variable para mostrar mensajes de error o éxito en la página.

// 3. --- VALIDACIÓN DEL PARÁMETRO GET ---
// Se comprueba si se ha pasado un parámetro 'id_animal' en la URL (ej. ...?id_animal=123) y si es numérico.
// Esto es esencial para saber de qué animal se debe mostrar el historial.
if (!isset($_GET['id_animal']) || !is_numeric($_GET['id_animal'])) {
    // Si el ID no es válido, se guarda un mensaje de error en la sesión y se redirige al usuario.
    $_SESSION['mensaje_error_animal'] = "ID de animal no válido.";
    header("Location: mis_animales.php");
    exit();
}
$id_animal_seleccionado = (int)$_GET['id_animal']; // Se convierte a entero para seguridad.

// 4. --- LÓGICA DE BASE DE DATOS ---
// El bloque try-catch maneja de forma segura los errores que puedan ocurrir durante las consultas a la base de datos.
try {
    // 4.1. Validar que el animal especificado en la URL realmente pertenece al usuario logueado.
    // Esta es una medida de seguridad clave para evitar que un usuario vea datos de otro manipulando la URL.
    $stmt_animal = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal, cantidad FROM animales WHERE id_animal = :id_animal AND id_usuario = :id_usuario");
    $stmt_animal->bindParam(':id_animal', $id_animal_seleccionado, PDO::PARAM_INT);
    $stmt_animal->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_animal->execute();
    $animal_info = $stmt_animal->fetch(PDO::FETCH_ASSOC);

    // Si la consulta no devuelve ningún resultado, el animal no existe o no pertenece al usuario.
    if (!$animal_info) {
        $_SESSION['mensaje_error_animal'] = "Animal no encontrado o no te pertenece.";
        header("Location: mis_animales.php");
        exit();
    }

    // 4.2. Obtener el historial de alimentación para el animal seleccionado.
    // La consulta selecciona todos los registros de la tabla 'alimentacion' que coincidan con el id_animal.
    // Se formatea la fecha para una mejor visualización y se ordena por la fecha más reciente primero.
    $sql_historial = "SELECT id_alimentacion, tipo_alimento, cantidad_diaria, unidad_cantidad, 
                             frecuencia_alimentacion, DATE_FORMAT(fecha_registro_alimentacion, '%d/%m/%Y') as fecha_registro_f, observaciones
                      FROM alimentacion
                      WHERE id_animal = :id_animal
                      ORDER BY fecha_registro_alimentacion DESC, id_alimentacion DESC";
    $stmt_historial = $pdo->prepare($sql_historial);
    $stmt_historial->bindParam(':id_animal', $id_animal_seleccionado, PDO::PARAM_INT);
    $stmt_historial->execute();
    // `fetchAll` recupera todas las filas del resultado y las guarda en el array.
    $historial_alimentacion = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

    // 4.3. Comprobar si hay un mensaje de éxito en la URL.
    // Esto se usa para mostrar una confirmación después de que el usuario registra una nueva pauta.
    if(isset($_GET['mensaje_exito'])){
        $mensaje_pagina = "Pauta de alimentación registrada exitosamente.";
    }

} catch (PDOException $e) {
    // Si alguna de las consultas falla, se captura el error y se guarda un mensaje para mostrarlo al usuario.
    $mensaje_pagina = "Error al obtener datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Alimentación - GAG</title>
    <style>
        /* --- ESTILOS CSS --- */
        /* Aquí se definen todos los estilos visuales de la página, incluyendo el layout,
           la cabecera, el menú, la tabla, los botones y la responsividad. */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9; font-size: 16px; color: #333; }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background-color: #e0e0e0; border-bottom: 2px solid #ccc; position: relative; }
        .logo img { height: 70px; }
        .menu { display: flex; align-items: center; }
        .menu a { margin: 0 5px; text-decoration: none; color: black; padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; white-space: nowrap; font-size: 0.9em; }
        .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: #70a845; }
        .menu a.exit { background-color: #ff4d4d; color: white !important; border: 1px solid #cc0000; }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.8rem; color: #333; cursor: pointer; }
        
        .page-container { width: 90%; max-width: 900px; margin: 30px auto; background: #fff; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .page-container h1 { text-align: center; color: #4CAF50; margin-bottom: 20px; }
        
        .animal-info-header { background-color: #e9f5e9; padding: 15px; border-radius: 5px; margin-bottom:25px; border-left: 4px solid #4CAF50;}
        .animal-info-header p { margin: 5px 0; color: #333; font-size: 1.1em; }
        
        .action-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .btn-action { display: inline-block; padding: 10px 18px; color: white !important; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background-color 0.3s ease; text-align: center; }
        .btn-add-pauta { background-color: #5cb85c; }
        .btn-add-pauta:hover { background-color: #4cae4c; }
        .btn-reporte { background-color: #17a2b8; }
        .btn-reporte:hover { background-color: #138496; }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.95em; }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        td.actions a { margin-right: 8px; color: #007bff; text-decoration: none; }
        .no-records { text-align: center; padding: 20px; font-style: italic; color: #777; }
        
        .mensaje-pagina { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .mensaje-pagina.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje-pagina.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        
        .back-link-container { text-align: center; margin-top: 25px; }
        .back-link { color: #337ab7; text-decoration: none; font-size: 0.9em; }

        @media (max-width: 991.98px) { .menu-toggle { display: block; } .menu { display: none; flex-direction: column; align-items: stretch; position: absolute; top: 100%; left: 0; width: 100%; background-color: #e9e9e9; padding: 0; box-shadow: 0 4px 8px rgba(0,0,0,.1); z-index: 1000; border-top: 1px solid #ccc; } .menu.active { display: flex; } .menu a { margin:0; padding:15px 20px; width:100%; text-align:left; border:none; border-bottom:1px solid #d0d0d0; border-radius:0; color:#333; } .menu a:last-child { border-bottom: none; } .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; } }
        @media (max-width: 768px) { .btn-action { flex-grow: 1; } }
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML DE LA PÁGINA --- -->
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

    <div class="page-container">
        <h1>Historial de Alimentación</h1>

        <!-- Muestra un resumen del animal/lote seleccionado. -->
        <?php if ($animal_info): ?>
            <div class="animal-info-header">
                <p><strong>Animal/Lote:</strong> <?php echo htmlspecialchars($animal_info['tipo_animal']); ?>
                    <?php echo !empty($animal_info['nombre_animal']) ? ' "' . htmlspecialchars($animal_info['nombre_animal']) . '"' : ''; ?>
                    (ID: <?php echo htmlspecialchars($animal_info['id_animal']); ?>
                    <?php if($animal_info['cantidad'] > 1) echo ", Lote de: ".htmlspecialchars($animal_info['cantidad']); ?>)
                </p>
            </div>
        <?php endif; ?>

        <!-- Muestra mensajes de éxito o error si existen. -->
        <?php if (!empty($mensaje_pagina)): ?>
            <div class="mensaje-pagina <?php echo (stripos($mensaje_pagina, 'exitosamente') !== false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje_pagina); ?>
            </div>
        <?php endif; ?>

        <!-- Barra de acciones principales: añadir nueva pauta y generar reporte. -->
        <div class="action-bar">
            <a href="registrar_alimentacion.php?id_animal=<?php echo $id_animal_seleccionado; ?>" class="btn-action btn-add-pauta">Añadir Nueva Pauta</a>
            <!-- El botón de reporte solo se muestra si hay registros en el historial. -->
            <?php if (!empty($historial_alimentacion)): ?>
                <a href="generar_reporte_alimentacion.php?id_animal=<?php echo $id_animal_seleccionado; ?>" class="btn-action btn-reporte" target="_blank">
                    Generar Reporte Excel
                </a>
            <?php endif; ?>
        </div>

        <!-- Lógica para mostrar la tabla con el historial o un mensaje si no hay registros. -->
        <?php if (!empty($historial_alimentacion)): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha Pauta</th>
                            <th>Tipo Alimento</th>
                            <th>Cantidad Diaria</th>
                            <th>Unidad</th>
                            <th>Frecuencia</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Bucle que recorre el array del historial y crea una fila <tr> por cada registro. -->
                        <?php foreach ($historial_alimentacion as $pauta): ?>
                            <tr>
                                <!-- Se imprimen los datos de cada pauta. `htmlspecialchars` previene ataques XSS. -->
                                <td><?php echo htmlspecialchars($pauta['fecha_registro_f']); ?></td>
                                <td><?php echo htmlspecialchars($pauta['tipo_alimento']); ?></td>
                                <!-- La función rtrim se usa para limpiar ceros innecesarios de los decimales (ej. 5.00 -> 5). -->
                                <td><?php echo htmlspecialchars(rtrim(rtrim(number_format($pauta['cantidad_diaria'], 2, '.', ''), '0'), '.')); ?></td>
                                <td><?php echo htmlspecialchars($pauta['unidad_cantidad']); ?></td>
                                <td><?php echo htmlspecialchars($pauta['frecuencia_alimentacion']); ?></td>
                                <!-- nl2br() convierte los saltos de línea (\n) en etiquetas <br> para que se muestren en HTML. -->
                                <td><?php echo nl2br(htmlspecialchars($pauta['observaciones'] ?: '-')); ?></td>
                                <td class="actions">
                                    <!-- Espacio reservado para futuros botones como "Editar" o "Eliminar". -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- Este mensaje se muestra solo si la carga de datos fue exitosa pero no se encontraron registros. -->
             <?php if (empty($mensaje_pagina) || stripos($mensaje_pagina, 'exitosamente') !== false): ?>
                <p class="no-records">No hay pautas de alimentación registradas para este animal/lote.</p>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Enlace para volver a la lista principal de animales. -->
        <div class="back-link-container">
            <a href="mis_animales.php" class="back-link">Volver a Mis Animales</a>
        </div>
    </div>
    
    <!-- --- SCRIPT JAVASCRIPT PARA EL MENÚ RESPONSIVE (HAMBURGUESA) --- -->
    <script>
        // Se ejecuta cuando el contenido HTML de la página ha sido completamente cargado.
        document.addEventListener('DOMContentLoaded', function() {
            // Se obtienen los elementos del DOM del botón y del menú.
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');

            // Se comprueba que ambos elementos existan para evitar errores.
            if (menuToggleBtn && mainMenu) {
                // Se añade un "escuchador de eventos" que reacciona al clic en el botón.
                menuToggleBtn.addEventListener('click', () => {
                    // Alterna la clase 'active' en el menú, lo que controla su visibilidad en el CSS.
                    mainMenu.classList.toggle('active');
                    // Actualiza el atributo 'aria-expanded' para mejorar la accesibilidad.
                    const isExpanded = mainMenu.classList.contains('active');
                    menuToggleBtn.setAttribute('aria-expanded', isExpanded);
                });
            }
        });
    </script>
</body>
</html>