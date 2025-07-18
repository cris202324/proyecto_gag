<?php
// Inicia la sesión de PHP. Es necesario para usar variables de sesión,
// especialmente para verificar que el usuario ha iniciado sesión.
session_start();

// --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Comprueba si la variable de sesión 'id_usuario' existe. Si no, significa que el usuario
// no ha iniciado sesión, por lo que se le redirige a la página de login y se detiene el script.
if (!isset($_SESSION['id_usuario'])) {
    // La ruta de redirección debe ser correcta desde la ubicación de este archivo.
    header("Location: ../../pages/auth/login.html");
    exit(); // Detiene la ejecución para proteger la página.
}       

// --- INCLUSIÓN DE ARCHIVOS Y DECLARACIÓN DE VARIABLES ---
// Incluye el archivo que establece la conexión a la base de datos y define la variable $pdo.
include '../../conexion.php';
// Guarda el ID del usuario actual de la sesión en una variable para un uso más fácil.
$id_usuario_actual = $_SESSION['id_usuario'];

// Inicializa las variables que se usarán a lo largo del script.
$mensaje = ''; // Para mostrar mensajes de éxito o error al usuario.
$animal_seleccionado_info = null; // Almacenará los datos del animal si se pasa por URL.
$id_animal_get = null; // Almacenará el ID del animal pasado por la URL.

//listado de animales a seleccionar
$lista_de_tipo_de_alimento= [
    "Concentrado Para Crecimiento", "Maiz Molido", "Pasto Fresco ", "Leguminosas", "Heno", "Avena", "alfalfa","Legumbres especiales"
];

$lista_de_tipo_frecuencia= [
    "2 veces al dia", "Ad libitium","Mañana","Tarde","Mañana Y Tarde"
];


// --- LÓGICA PARA PRESELECCIONAR UN ANIMAL DESDE LA URL ---
// Comprueba si se ha pasado un parámetro 'id_animal' en la URL (ej. ...?id_animal=123) y si es un número.
if (isset($_GET['id_animal']) && is_numeric($_GET['id_animal'])) {
    $id_animal_get = (int)$_GET['id_animal']; // Se convierte a entero para seguridad.
    
    // Se valida que el animal especificado en la URL realmente pertenezca al usuario que está logueado.
    // Esto es una medida de seguridad para evitar que un usuario vea o interactúe con los datos de otro.
    $stmt_val_animal = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal, cantidad FROM animales WHERE id_animal = :id_animal AND id_usuario = :id_usuario");
    $stmt_val_animal->bindParam(':id_animal', $id_animal_get, PDO::PARAM_INT);
    $stmt_val_animal->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_val_animal->execute();
    $animal_seleccionado_info = $stmt_val_animal->fetch(PDO::FETCH_ASSOC);
    
    // Si la consulta no devuelve ningún resultado, el animal no existe o no pertenece al usuario.
    if (!$animal_seleccionado_info) {
        // Se anulan las variables para que el formulario muestre el selector general en lugar de un animal preseleccionado.
        $id_animal_get = null; 
        $animal_seleccionado_info = null;
        $mensaje = "Error: El animal no te pertenece o no esta validado";
    }
}

// --- PROCESAMIENTO DEL FORMULARIO CUANDO SE ENVÍA (MÉTODO POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Se recogen los datos del formulario. `filter_input` es una forma segura de obtener datos. `trim()` elimina espacios en blanco.
    $id_animal_post = filter_input(INPUT_POST, 'id_animal', FILTER_VALIDATE_INT);
    $tipo_alimento_seleccionado = trim($_POST['tipo_alimento']);
    $cantidad_diaria = filter_input(INPUT_POST, 'cantidad_diaria', FILTER_VALIDATE_FLOAT);
    $unidad_cantidad = trim($_POST['unidad_cantidad']);
    $frecuencia_alimentacion = trim($_POST['frecuencia_alimentacion']);
    $fecha_registro_alimentacion = $_POST['fecha_registro_alimentacion']; // El tipo 'date' de HTML ya envía en formato YYYY-MM-DD.
    $observaciones = !empty(trim($_POST['observaciones'])) ? trim($_POST['observaciones']) : null; // Asigna null si está vacío.

    // --- VALIDACIONES DE LOS DATOS RECIBIDOS ---
    if (!$id_animal_post) {
        $mensaje = "Error: Debes seleccionar un animal.";
    } elseif (empty($tipo_alimento)) {
        $mensaje = "El tipo de alimento es obligatorio.";
    } elseif ($cantidad_diaria === false || $cantidad_diaria <= 0) {
        $mensaje = "La cantidad diaria debe ser un número positivo.";
    } elseif (empty($unidad_cantidad)) {
        $mensaje = "La unidad para la cantidad es obligatoria.";
    } elseif (empty($frecuencia_alimentacion)) {
        $mensaje = "La frecuencia de alimentación es obligatoria.";
    } elseif (empty($fecha_registro_alimentacion)) {
        $mensaje = "La fecha de registro de esta pauta es obligatoria.";
    } else {
        // --- INSERCIÓN EN LA BASE DE DATOS ---
        // Se vuelve a validar que el animal enviado por POST pertenece al usuario.
        $stmt_val = $pdo->prepare("SELECT id_animal FROM animales WHERE id_animal = :id_animal_val AND id_usuario = :id_usuario_val");
        $stmt_val->bindParam(':id_animal_val', $id_animal_post, PDO::PARAM_INT);
        $stmt_val->bindParam(':id_usuario_val', $id_usuario_actual, PDO::PARAM_STR);
        $stmt_val->execute();
        
        if ($stmt_val->fetch()) { // Si la consulta devuelve un resultado, el animal es válido.
            try {
                // Se prepara la consulta SQL de inserción usando placeholders para prevenir inyección SQL.
                $sql = "INSERT INTO alimentacion (id_animal, tipo_alimento, cantidad_diaria, unidad_cantidad, frecuencia_alimentacion, fecha_registro_alimentacion, observaciones)
                        VALUES (:id_animal, :tipo_alimento, :cantidad_diaria, :unidad_cantidad, :frecuencia_alimentacion, :fecha_registro_alimentacion, :observaciones)";
                $stmt = $pdo->prepare($sql);
                
                // Se asocian los valores de las variables a los placeholders de la consulta.
                $stmt->bindParam(':id_animal', $id_animal_post, PDO::PARAM_INT);
                $stmt->bindParam(':tipo_alimento', $tipo_alimento);
                $stmt->bindParam(':cantidad_diaria', $cantidad_diaria);
                $stmt->bindParam(':unidad_cantidad', $unidad_cantidad);
                $stmt->bindParam(':frecuencia_alimentacion', $frecuencia_alimentacion);
                $stmt->bindParam(':fecha_registro_alimentacion', $fecha_registro_alimentacion);
                $stmt->bindParam(':observaciones', $observaciones);

                // Se ejecuta la consulta.
                if ($stmt->execute()) {
                    $mensaje = "¡Pauta de alimentación registrada exitosamente!";
                    // Si la página se cargó con un animal preseleccionado, se refresca su información para mostrarla actualizada.
                    if ($id_animal_get) {
                        $stmt_val_animal->execute();
                        $animal_seleccionado_info = $stmt_val_animal->fetch(PDO::FETCH_ASSOC);
                    }
                } else {
                    $mensaje = "Error al registrar la pauta de alimentación.";
                }
            } catch (PDOException $e) {
                // Si ocurre un error de base de datos, se muestra un mensaje detallado.
                $mensaje = "Error en la base de datos: " . $e->getMessage();
            }
        } else {
            $mensaje = "Error: El animal seleccionado no es válido o no te pertenece.";
        }
    }
}

// --- OBTENCIÓN DE DATOS PARA EL FORMULARIO ---
// Se obtiene la lista de todos los animales del usuario para poblar el menú desplegable.
// Esta consulta solo se ejecuta si la página se cargó sin un 'id_animal' específico en la URL.
$lista_animales_usuario = [];
if (!$id_animal_get) {
    $stmt_animales = $pdo->prepare("SELECT id_animal, nombre_animal, tipo_animal, cantidad FROM animales WHERE id_usuario = :id_usuario ORDER BY tipo_animal, nombre_animal");
    $stmt_animales->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_animales->execute();
    $lista_animales_usuario = $stmt_animales->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Alimentación</title>
    <style>
        /* --- ESTILOS CSS --- */
        /* Estilos visuales para el formulario y la página. */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
        .container { width: 90%; max-width: 700px; margin: 30px auto; background: #fff; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1, h2 { text-align: center; color: #4CAF50; margin-bottom: 15px; }
        h2 { font-size: 1.3em; color: #333; margin-top:0;}
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; color: #555; font-weight: bold; }
        .form-group input[type="text"], .form-group input[type="date"], .form-group input[type="number"], .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
        .form-group textarea { min-height: 70px; resize: vertical; }
        .form-group input[type="submit"] { background-color: #5cb85c; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 18px; transition: background-color 0.3s ease; width: 100%; }
        .form-group input[type="submit"]:hover { background-color: #4cae4c; }
        .mensaje { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-size: 0.95em; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        .back-link-container { text-align: center; margin-top: 25px; background-color: #5cb85c; color:white; padding:12px 0px ; border:none ; border-radius: 4px; cursor:pointer; font-size: 20px; transition: background-color 0.3s ease;width: 100% }
        .back-link { color: white ; text-decoration: none; font-size: 0.9em; margin: 25px; }
        .back-link:hover { text-decoration: underline; }
        .animal-info { background-color: #e9f5e9; padding: 15px; border-radius: 5px; margin-bottom:20px; border-left: 4px solid #4CAF50;}
        .animal-info p { margin: 5px 0; color: #333; }
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML DEL FORMULARIO --- -->
    <div class="container">
        <h1>Registrar Pauta de Alimentación</h1>

        <!-- Si un animal fue preseleccionado desde la URL, se muestra su información aquí. -->
        <?php if ($animal_seleccionado_info): ?>
            <div class="animal-info">
                <h2>Para: <?php echo htmlspecialchars($animal_seleccionado_info['tipo_animal']); ?>
                    <?php echo !empty($animal_seleccionado_info['nombre_animal']) ? ' "' . htmlspecialchars($animal_seleccionado_info['nombre_animal']) . '"' : ''; ?>
                    (ID: <?php echo htmlspecialchars($animal_seleccionado_info['id_animal']); ?>
                    <?php if($animal_seleccionado_info['cantidad'] > 1) echo ", Lote de: ".htmlspecialchars($animal_seleccionado_info['cantidad']); ?>)
                </h2>
            </div>
        <?php endif; ?>

        <!-- Muestra un mensaje de éxito o error si la variable $mensaje no está vacía. -->
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo (stripos($mensaje, 'exitosamente') !== false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- El formulario envía los datos a la misma página (PHP_SELF). La URL incluye el id_animal si fue preseleccionado. -->
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . ($id_animal_get ? '?id_animal='.$id_animal_get : ''); ?>" method="POST">
            
            <!-- Lógica para mostrar el selector de animales o un campo oculto. -->
            <?php if (!$id_animal_get && empty($animal_seleccionado_info)): // Si no hay animal de URL, muestra el selector. ?>
                <div class="form-group">
                    <label for="id_animal">Seleccionar Animal/Lote:</label>
                    <select name="id_animal" id="id_animal" required>
                        <option value="">-- Elija un animal/lote --</option>
                        <!-- Bucle para llenar el selector con los animales del usuario. -->
                        <?php foreach ($lista_animales_usuario as $animal_opt): ?>
                            <option value="<?php echo $animal_opt['id_animal']; ?>">
                                <?php 
                                echo htmlspecialchars($animal_opt['tipo_animal']);
                                if (!empty($animal_opt['nombre_animal'])) echo ' "' . htmlspecialchars($animal_opt['nombre_animal']) . '"';
                                echo " (ID: " . $animal_opt['id_animal'];
                                if ($animal_opt['cantidad'] > 1) echo ", Lote de: " . $animal_opt['cantidad'];
                                echo ")";
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif($id_animal_get && $animal_seleccionado_info): // Si hay animal de URL, usa un campo oculto para enviar su ID. ?>
                <input type="hidden" name="id_animal" value="<?php echo $id_animal_get; ?>">
            <?php endif; ?>

            <!-- Campos del formulario para registrar la pauta de alimentación. -->
            <div class="form-group">
                <label for="tipo_alimento">Tipo de Alimento (Ej: Concentrado Crecimiento, Pasto Fresco, Maíz Molido):</label>
                <select name="tipo_alimento" id="tipo_alimento" required>
                    elecc<option value="">---Seleccione una opcion---</option>
                    <?php 
                        foreach ($lista_de_tipo_de_alimento as $tipo_alimento){
                            $selected = (trim($_POST['tipo_alimento'] && $_POST['tipo_alimento'])?'selected':'');
                            echo "<option value='" . htmlspecialchars($tipo_alimento) ."'$selected>" . htmlspecialchars($tipo_alimento)."</option>";                      
                        }                      
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="cantidad_diaria">Cantidad Diaria:</label>
                <input type="number" name="cantidad_diaria" id="cantidad_diaria" step="0.01" min="0.01" required>
            </div>

            <div class="form-group">
                <label for="unidad_cantidad">Unidad (Ej: kg, g, lb, raciones, litros):</label>
                <input type="text" name="unidad_cantidad" id="unidad_cantidad" value="kg" maxlength="20" required>
            </div>

            <div class="form-group">
                <label for="frecuencia_alimentacion">Frecuencia (Ej: 2 veces al día, Ad libitum, Mañana y Tarde):</label>
                <select for ="frecuencia_alimentacion" id= "frecuencia_alimentacion" required>
                    elecc<option value="">---Seleccion una opcion---</option>
                    <?php
                        foreach($lista_de_tipo_frecuencia as $frecuencia_alimentacion){
                            $selected= (trim($_POST['frecuencia_alimentacion'] && $_POST['frecuencia_alimentacion'])?'selected':'');
                            echo "<option value='" . htmlspecialchars($frecuencia_alimentacion) . "'$selected>" .htmlspecialchars($frecuencia_alimentacion) ."</option>";
                        }
                    ?>
            </div>
            
            <div class="form-group">
                <label for="fecha_registro_alimentacion">Fecha de Inicio de esta Pauta:</label>
                <input type="date" name="fecha_registro_alimentacion" id="fecha_registro_alimentacion" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label for="observaciones">Observaciones (Opcional):</label>
                <textarea name="observaciones" id="observaciones" maxlength="500"></textarea>
            </div>

            <div class="form-group">
                <input type="submit" value="Registrar Pauta de Alimentación">
            </div>
        </form>

        <!-- Enlaces de navegación para volver a otras páginas. -->
        <div class="back-link-container">
            <?php if ($id_animal_get): ?>
                <a href="ver_alimentacion.php?id_animal=<?php echo $id_animal_get; ?>" class="back-link">Ver Historial de Alimentación</a>
            <?php endif; ?>
        </div>
        <div class ="back-link-container">
          <a href="mis_animales.php" class="back-link">Volver a Mis Animales</a>
        </div>    
    </div>
</body>
</html>
