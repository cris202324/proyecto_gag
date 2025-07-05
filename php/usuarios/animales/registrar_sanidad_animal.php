<?php
// Inicia la sesión de PHP. Es el primer paso y es crucial para poder usar
// variables de sesión como $_SESSION['id_usuario'] para la autenticación y autorización.
session_start();

// 1. --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Esta es una barrera de seguridad fundamental.
// Se comprueba si la variable de sesión 'id_usuario' existe. Si no, significa que el usuario
// no ha iniciado sesión, por lo que se le redirige a la página de login y se detiene el script.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../pages/auth/login.html");
    exit(); // Detiene la ejecución del script para proteger la página.
}

// 2. --- INCLUSIÓN DE ARCHIVOS Y DEFINICIÓN DE VARIABLES INICIALES ---
// Incluye el archivo de conexión a la base de datos, que se espera que defina la variable $pdo.
include '../../conexion.php'; 
// Guarda el ID del usuario actual de la sesión en una variable para un uso más fácil y claro.
$id_usuario_actual = $_SESSION['id_usuario'];
// Inicializa las variables que se usarán a lo largo del script para evitar errores.
$mensaje = ''; // Para mostrar mensajes de error o éxito al usuario.
$animal_seleccionado_info = null; // Almacenará los datos del animal si se pasa por la URL.
$id_animal_get = null; // Almacenará el ID del animal si viene por la URL.
$lista_tipos_mv = []; // Array para guardar la lista de productos (medicamentos/vacunas) predefinidos.

// 3. --- LÓGICA DE PRE-CARGA DE DATOS (MÉTODO GET) ---
// Esta sección se ejecuta cuando se accede a la página con un ID de animal en la URL (ej. ...?id_animal=123).
// Esto permite que el formulario se muestre ya pre-configurado para un animal específico.
if (isset($_GET['id_animal']) && is_numeric($_GET['id_animal'])) {
    $id_animal_get = (int)$_GET['id_animal']; // Se convierte a entero para seguridad.
    
    // Se valida que el animal especificado en la URL realmente pertenezca al usuario logueado.
    // Es una medida de seguridad clave para evitar que un usuario manipule la URL para ver o registrar datos de otros.
    $stmt_val_animal = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal, cantidad FROM animales WHERE id_animal = :id_animal AND id_usuario = :id_usuario");
    $stmt_val_animal->bindParam(':id_animal', $id_animal_get, PDO::PARAM_INT);
    $stmt_val_animal->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_val_animal->execute();
    $animal_seleccionado_info = $stmt_val_animal->fetch(PDO::FETCH_ASSOC);

    // Si la consulta no devuelve ningún resultado, el animal no existe o no pertenece al usuario.
    // En este caso, se redirige al usuario de vuelta a su lista de animales con un mensaje de error.
    if (!$animal_seleccionado_info) {
        $_SESSION['mensaje_error_animal'] = "Error: El animal especificado no es válido o no te pertenece.";
        header("Location: mis_animales.php");
        exit();
    }
}
// También se comprueba si se ha pre-seleccionado un producto (id_tipo_mv). Esto sucede cuando
// el usuario hace clic en "Aplicar esto" desde una sugerencia en la página de historial.
$id_tipo_mv_get = isset($_GET['id_tipo_mv']) && is_numeric($_GET['id_tipo_mv']) ? (int)$_GET['id_tipo_mv'] : null;

// 4. --- CARGAR LISTA DE PRODUCTOS PREDEFINIDOS ---
// Esta consulta obtiene todos los medicamentos/vacunas de la tabla `tipos_medicamento_vacuna`
// para llenar el menú desplegable en el formulario, facilitando al usuario la selección de un producto conocido.
try {
    $stmt_tipos_mv = $pdo->query("SELECT id_tipo_mv, nombre_producto, tipo_aplicacion FROM tipos_medicamento_vacuna ORDER BY tipo_aplicacion, nombre_producto");
    $lista_tipos_mv = $stmt_tipos_mv->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si la consulta falla, se guarda un mensaje de error.
    $mensaje = "Error al cargar productos predefinidos: " . $e->getMessage();
}

// 5. --- PROCESAMIENTO DEL FORMULARIO (CUANDO EL USUARIO ENVÍA DATOS - MÉTODO POST) ---
// Se verifica que la petición HTTP sea de tipo POST, lo que indica que el formulario ha sido enviado.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 5.1. Recoger y limpiar los datos del formulario de forma segura.
    // `filter_input` es una forma recomendada para obtener datos de $_POST.
    // El operador de fusión de null (??) se usa para asignar un valor por defecto si la clave no existe, evitando warnings.
    $id_animal_post = filter_input(INPUT_POST, 'id_animal', FILTER_VALIDATE_INT);
    $id_tipo_mv_seleccionado = filter_input(INPUT_POST, 'id_tipo_mv', FILTER_VALIDATE_INT) ?: null; // Si no es un entero válido, será null.
    $nombre_producto_manual = trim($_POST['nombre_producto_manual'] ?? '');
    $tipo_aplicacion_registrada = $_POST['tipo_aplicacion_registrada'] ?? '';
    $fecha_aplicacion = $_POST['fecha_aplicacion'] ?? '';
    $dosis_aplicada = !empty(trim($_POST['dosis_aplicada'] ?? '')) ? trim($_POST['dosis_aplicada']) : null;
    $via_administracion = !empty(trim($_POST['via_administracion'] ?? '')) ? trim($_POST['via_administracion']) : null;
    $lote_producto = !empty(trim($_POST['lote_producto'] ?? '')) ? trim($_POST['lote_producto']) : null;
    $fecha_vencimiento_producto = !empty($_POST['fecha_vencimiento_producto'] ?? '') ? $_POST['fecha_vencimiento_producto'] : null;
    $responsable_aplicacion = !empty(trim($_POST['responsable_aplicacion'] ?? '')) ? trim($_POST['responsable_aplicacion']) : null;
    $observaciones = !empty(trim($_POST['observaciones'] ?? '')) ? trim($_POST['observaciones']) : null;
    $fecha_proxima_dosis_sugerida = !empty($_POST['fecha_proxima_dosis_sugerida'] ?? '') ? $_POST['fecha_proxima_dosis_sugerida'] : null;

    // 5.2. Lógica para determinar el nombre final del producto a registrar.
    // Si el usuario eligió un producto de la lista y no escribió nada en el campo manual,
    // se busca el nombre del producto en la base de datos para registrarlo.
    $nombre_producto_final_a_registrar = $nombre_producto_manual;
    if ($id_tipo_mv_seleccionado && empty($nombre_producto_manual)) {
        $stmt_nombre = $pdo->prepare("SELECT nombre_producto FROM tipos_medicamento_vacuna WHERE id_tipo_mv = :id_tipo_mv");
        $stmt_nombre->bindParam(':id_tipo_mv', $id_tipo_mv_seleccionado, PDO::PARAM_INT);
        $stmt_nombre->execute();
        $res_nombre = $stmt_nombre->fetch(PDO::FETCH_ASSOC);
        if ($res_nombre) {
            $nombre_producto_final_a_registrar = $res_nombre['nombre_producto'];
        }
    }
    
    // 5.3. Validaciones del lado del servidor. Esta es la capa de seguridad principal de los datos.
    if (!$id_animal_post) { $mensaje = "Error: Debes seleccionar un animal."; }
    elseif (empty($nombre_producto_final_a_registrar)) { $mensaje = "Debes seleccionar un producto de la lista o ingresar un nombre manualmente."; }
    elseif (empty($tipo_aplicacion_registrada)) { $mensaje = "El tipo de aplicación es obligatorio."; }
    elseif (empty($fecha_aplicacion)) { $mensaje = "La fecha de aplicación es obligatoria."; }
    else {
        // 5.4. Si las validaciones pasan, se procede a insertar en la base de datos.
        // Se utiliza un bloque try-catch para manejar cualquier error durante la inserción.
        try {
            $sql = "INSERT INTO registro_sanitario_animal (id_animal, id_tipo_mv, nombre_producto_aplicado, tipo_aplicacion_registrada, fecha_aplicacion, dosis_aplicada, via_administracion, lote_producto, fecha_vencimiento_producto, responsable_aplicacion, observaciones, fecha_proxima_dosis_sugerida)
                    VALUES (:id_animal, :id_tipo_mv, :nombre_prod_app, :tipo_app_reg, :fecha_app, :dosis_app, :via_admin, :lote_prod, :fecha_venc_prod, :responsable, :obs, :fecha_prox_dosis)";
            $stmt = $pdo->prepare($sql);
            
            // Se asocian las variables a los placeholders de la consulta para prevenir inyección SQL.
            $stmt->bindParam(':id_animal', $id_animal_post, PDO::PARAM_INT);
            $stmt->bindParam(':id_tipo_mv', $id_tipo_mv_seleccionado, $id_tipo_mv_seleccionado ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindParam(':nombre_prod_app', $nombre_producto_final_a_registrar);
            $stmt->bindParam(':tipo_app_reg', $tipo_aplicacion_registrada);
            $stmt->bindParam(':fecha_app', $fecha_aplicacion);
            $stmt->bindParam(':dosis_app', $dosis_aplicada);
            $stmt->bindParam(':via_admin', $via_administracion);
            $stmt->bindParam(':lote_prod', $lote_producto);
            $stmt->bindParam(':fecha_venc_prod', $fecha_vencimiento_producto, $fecha_vencimiento_producto ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindParam(':responsable', $responsable_aplicacion);
            $stmt->bindParam(':obs', $observaciones);
            $stmt->bindParam(':fecha_prox_dosis', $fecha_proxima_dosis_sugerida, $fecha_proxima_dosis_sugerida ? PDO::PARAM_STR : PDO::PARAM_NULL);

            if ($stmt->execute()) {
                // Si la inserción es exitosa, se redirige al usuario a la página del historial con un mensaje de éxito.
                header("Location: ver_sanidad_animal.php?id_animal=" . $id_animal_post . "&mensaje_exito_registro=1");
                exit();
            } else {
                $mensaje = "Error al guardar el registro sanitario.";
            }
        } catch (PDOException $e) {
            $mensaje = "Error en la base de datos: " . $e->getMessage();
        }
    }
}

// 6. --- CARGA DE ANIMALES PARA EL SELECTOR (si es necesario) ---
// Si la página se cargó sin un animal preseleccionado, esta consulta carga la lista de todos
// los animales del usuario para que pueda elegir uno del menú desplegable.
$lista_animales_usuario = [];
if (!$id_animal_get) { 
    $stmt_animales_sel = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal, cantidad FROM animales WHERE id_usuario = :id_usuario ORDER BY tipo_animal, nombre_animal");
    $stmt_animales_sel->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_animales_sel->execute();
    $lista_animales_usuario = $stmt_animales_sel->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Sanidad Animal - GAG</title>
    <style>
        /* --- ESTILOS CSS --- */
        /* Estilos visuales para el formulario y la página. */
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f9f9f9; }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background-color: #e0e0e0; border-bottom: 2px solid #ccc; }
        .logo img { height: 70px; }
        .menu { display: flex; align-items: center; }
        .menu a { margin: 0 5px; text-decoration: none; color: black; padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; white-space: nowrap; }
        .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; }
        .menu a.exit { background-color: #ff4d4d; color: white !important; }
        
        .container { width: 90%; max-width: 750px; margin: 30px auto; background: #fff; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { text-align: center; color: #dc3545; margin-bottom: 15px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group .btn-save { background-color: #dc3545; color: white; padding: 12px 20px; border: none; cursor: pointer; width: 100%; transition: background-color 0.3s; border-radius: 4px; font-size: 16px; }
        .form-group .btn-save:hover { background-color: #c82333; }
        
        .mensaje { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .mensaje.error { background-color: #f2dede; color: #a94442; }

        .animal-info { background-color: #fbeae5; padding: 15px; border-radius: 5px; margin-bottom:20px; border-left: 4px solid #dc3545;}
        .animal-info h2 { color: #333; font-size: 1.2em; text-align: left; margin: 0; }
        .manual-entry-note { font-size: 0.85em; color: #666; margin-top: 5px; }

        .back-link-container { text-align: center; margin-top: 25px; }
        .back-link { color: #337ab7; text-decoration: none; margin: 0 10px; }
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML DE LA PÁGINA --- -->
    <div class="header">
        <div class="logo"> <img src="../../../img/logo.png" alt="Logo GAG" /> </div>
        <nav class="menu" id="mainMenu">
            <!-- Menú de navegación principal -->
            <a href="../../index.php">Inicio</a>
            <a href="../cultivos/miscultivos.php">Mis Cultivos</a>
            <a href="mis_animales.php" class="active">Mis Animales</a>
            <a href="../calendario.php">Calendario</a>
            <a href="../configuracion.php">Configuración</a>
            <a href="../ayuda.php">Ayuda</a>
            <a href="../../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="container">
        <h1>Registrar Aplicación Sanitaria</h1>

        <!-- Si un animal fue preseleccionado, se muestra su información aquí. -->
        <?php if ($animal_seleccionado_info): ?>
            <div class="animal-info">
                <h2>Para: <?php echo htmlspecialchars($animal_seleccionado_info['tipo_animal']); ?>
                    <?php echo !empty($animal_seleccionado_info['nombre_animal']) ? ' "' . htmlspecialchars($animal_seleccionado_info['nombre_animal']) . '"' : ''; ?>
                </h2>
            </div>
        <?php endif; ?>

        <!-- Muestra un mensaje de error si ocurrió alguno durante el procesamiento del formulario. -->
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje error"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <!-- Formulario para registrar la aplicación sanitaria. -->
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . ($id_animal_get ? '?id_animal='.$id_animal_get : ''); ?>" method="POST">
            
            <!-- Si no se preseleccionó un animal, muestra un menú desplegable para elegir uno. -->
            <?php if (!$id_animal_get): ?>
                <div class="form-group">
                    <label for="id_animal">Seleccionar Animal/Lote:</label>
                    <select name="id_animal" id="id_animal" required>
                        <option value="">-- Elija un animal/lote --</option>
                        <?php foreach ($lista_animales_usuario as $animal_opt): ?>
                            <option value="<?php echo $animal_opt['id_animal']; ?>">
                                <?php echo htmlspecialchars($animal_opt['tipo_animal'] . ' "' . $animal_opt['nombre_animal'] . '"'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: // Si sí se preseleccionó, envía el ID en un campo oculto. ?>
                <input type="hidden" name="id_animal" value="<?php echo $id_animal_get; ?>">
            <?php endif; ?>

            <!-- Menú desplegable para seleccionar un producto predefinido. -->
            <div class="form-group">
                <label for="id_tipo_mv">Producto Predefinido (Opcional):</label>
                <select name="id_tipo_mv" id="id_tipo_mv">
                    <option value="">-- Seleccione un producto de la lista --</option>
                    <?php foreach ($lista_tipos_mv as $tipo_mv): ?>
                        <option value="<?php echo $tipo_mv['id_tipo_mv']; ?>" <?php if ($id_tipo_mv_get == $tipo_mv['id_tipo_mv']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($tipo_mv['tipo_aplicacion']) . " - " . htmlspecialchars($tipo_mv['nombre_producto']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Campos del formulario para los detalles de la aplicación. -->
            <div class="form-group">
                <label for="nombre_producto_manual">Nombre del Producto Aplicado:</label>
                <input type="text" name="nombre_producto_manual" id="nombre_producto_manual" maxlength="150">
                <p class="manual-entry-note">Si seleccionó un producto, puede dejarlo vacío o especificar una marca. Si no, escriba aquí el nombre del producto.</p>
            </div>
            
            <div class="form-group">
                <label for="tipo_aplicacion_registrada">Tipo de Aplicación:</label>
                <select name="tipo_aplicacion_registrada" id="tipo_aplicacion_registrada" required>
                    <option value="Vacuna">Vacuna</option>
                    <option value="Medicamento">Medicamento</option>
                    <option value="Desparasitante">Desparasitante</option>
                    <option value="Vitamina">Vitamina</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>

            <div class="form-group">
                <label for="fecha_aplicacion">Fecha de Aplicación:</label>
                <input type="date" name="fecha_aplicacion" id="fecha_aplicacion" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <!-- ... (resto de los campos del formulario: dosis, vía, lote, etc.) ... -->

            <div class="form-group">
                <button type="submit" class="btn-save">Guardar Registro</button>
            </div>
        </form>

        <!-- Enlaces de navegación para volver a otras páginas. -->
        <div class="back-link-container">
            <?php if ($id_animal_get): ?>
                <a href="ver_sanidad_animal.php?id_animal=<?php echo $id_animal_get; ?>" class="back-link">Ver Historial Sanitario</a>
            <?php endif; ?>
            <a href="mis_animales.php" class="back-link">Volver a Mis Animales</a>
        </div>
    </div>
    
    <!-- --- SCRIPT JAVASCRIPT PARA LA INTERACTIVIDAD DEL FORMULARIO --- -->
    <script>
        // Se ejecuta cuando el contenido HTML de la página ha sido completamente cargado.
        document.addEventListener('DOMContentLoaded', function() {
            // Se obtienen los elementos del DOM necesarios para la interactividad.
            const selectProductoPredefinido = document.getElementById('id_tipo_mv');
            const inputTipoAplicacion = document.getElementById('tipo_aplicacion_registrada');
            const mainMenuToggle = document.getElementById('menuToggleBtn'); // Para el menú hamburguesa

            // Función para sincronizar el campo "Tipo de Aplicación" cuando se elige un producto predefinido.
            function syncTipoAplicacion() {
                const selectedOption = selectProductoPredefinido.options[selectProductoPredefinido.selectedIndex];
                if (!selectedOption || selectedOption.value === "") return;
                
                // Extrae el tipo (ej. "Vacuna") del texto de la opción seleccionada.
                const tipoAppFromOption = selectedOption.textContent.trim().split(' - ')[0].trim();
                
                // Busca y selecciona la opción coincidente en el menú desplegable de "Tipo de Aplicación".
                for (let i = 0; i < inputTipoAplicacion.options.length; i++) {
                    if (inputTipoAplicacion.options[i].value.toLowerCase() === tipoAppFromOption.toLowerCase()) {
                        inputTipoAplicacion.value = inputTipoAplicacion.options[i].value;
                        break;
                    }
                }
            }
            
            // Se añade el "escuchador de eventos" al selector de productos.
            if (selectProductoPredefinido) {
                selectProductoPredefinido.addEventListener('change', syncTipoAplicacion);
                // Si la página ya cargó con un producto preseleccionado, se llama a la función para sincronizarlo.
                if (selectProductoPredefinido.value) {
                    syncTipoAplicacion();
                }
            }
            
            // Lógica para el menú hamburguesa (si existe en esta página).
            if (mainMenuToggle) {
                const mainMenu = document.getElementById('mainMenu');
                mainMenuToggle.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>