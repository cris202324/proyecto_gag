<?php
// Inicia la sesión de PHP para poder usar variables de sesión como $_SESSION['id_usuario'].
session_start();

// --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Comprueba si el usuario ha iniciado sesión. Si no, lo redirige a la página de login.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit(); // Detiene la ejecución del script.
}

// --- INCLUSIÓN Y VARIABLES ---
// Incluye el archivo de conexión a la base de datos que define la variable $pdo.
include '../../conexion.php';

// --- LISTA PREDEFINIDA DE TIPOS DE ANIMALES ---
// Esta lista se usa para poblar el menú desplegable.
// Es una forma sencilla de estandarizar las entradas y mejorar la experiencia del usuario.
// En una versión más avanzada, esta lista podría venir de una tabla en la base de datos.
$tipos_de_animales_comunes = [
    "Caballo", "Cerdo", "Ovino", "Caprino", "Abeja", "Pollos", "Vaca", "Otro"
];

// Inicializa la variable de mensaje para dar feedback al usuario.
$mensaje = '';

// --- PROCESAMIENTO DEL FORMULARIO ---
// Se comprueba si la petición HTTP es de tipo POST, lo que indica que el formulario ha sido enviado.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Se recogen los datos del formulario. `trim()` se usa para eliminar espacios en blanco al inicio y al final.
    $id_usuario = $_SESSION['id_usuario'];
    $nombre_animal = trim($_POST['nombre_animal']);
    $tipo_animal_seleccionado = $_POST['tipo_animal']; // El valor del menú desplegable (<select>).
    $tipo_animal_otro = trim($_POST['tipo_animal_otro']); // El valor del campo de texto que aparece si se selecciona "Otro".
    
    // Se determina cuál es el tipo de animal final que se guardará en la base de datos.
    $tipo_animal_final = $tipo_animal_seleccionado;
    // Si el usuario seleccionó "Otro" y escribió algo en el campo de texto, el tipo final será lo que escribió.
    if ($tipo_animal_seleccionado === 'Otro' && !empty($tipo_animal_otro)) {
        $tipo_animal_final = $tipo_animal_otro;
    }
    
    // Se recogen los demás datos del formulario. Se usa el operador ternario para asignar `null` si el campo está vacío.
    $raza = !empty(trim($_POST['raza'])) ? trim($_POST['raza']) : null;
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $sexo = $_POST['sexo'];
    $identificador_unico = !empty(trim($_POST['identificador_unico'])) ? trim($_POST['identificador_unico']) : null;
    // Se valida que la cantidad sea un número positivo, por defecto es 1.
    $cantidad = isset($_POST['cantidad']) && is_numeric($_POST['cantidad']) && $_POST['cantidad'] > 0 ? (int)$_POST['cantidad'] : 1;

    // --- VALIDACIÓN DE DATOS ---
    // Se comprueba que el tipo de animal no esté vacío.
    if (empty($tipo_animal_final) || ($tipo_animal_seleccionado === 'Otro' && empty($tipo_animal_otro))) {
        $mensaje = "El tipo de animal es obligatorio. Si seleccionas 'Otro', debes especificar cuál.";
    } elseif ($cantidad <= 0) {
        $mensaje = "La cantidad debe ser un número positivo.";
    } else {
        // --- INSERCIÓN EN LA BASE DE DATOS ---
        // El bloque try-catch maneja errores de base de datos de forma segura.
        try {
            // Se prepara la consulta SQL de inserción usando placeholders (ej. :id_usuario) para prevenir inyección SQL.
            $sql = "INSERT INTO animales (id_usuario, nombre_animal, tipo_animal, raza, fecha_nacimiento, sexo, identificador_unico, cantidad)
                    VALUES (:id_usuario, :nombre_animal, :tipo_animal, :raza, :fecha_nacimiento, :sexo, :identificador_unico, :cantidad)";
            $stmt = $pdo->prepare($sql);

            // Se asocian los valores de las variables a los placeholders de la consulta.
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':nombre_animal', $nombre_animal);
            $stmt->bindParam(':tipo_animal', $tipo_animal_final); // Se usa la variable final calculada.
            $stmt->bindParam(':raza', $raza);
            $stmt->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $stmt->bindParam(':sexo', $sexo);
            $stmt->bindParam(':identificador_unico', $identificador_unico);
            $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT); // Se especifica que es un entero.

            // Se ejecuta la consulta.
            if ($stmt->execute()) {
                // Si la ejecución es exitosa, se crea un mensaje de éxito personalizado.
                if ($cantidad == 1) {
                    $mensaje = "¡Animal registrado exitosamente!";
                } else {
                    $mensaje = "¡Lote de " . htmlspecialchars($cantidad) . " animales registrado exitosamente!";
                }
            } else {
                // Si la ejecución falla por alguna razón.
                $mensaje = "Error al registrar el animal/lote.";
            }
        } catch (PDOException $e) {
            // Si ocurre una excepción de base de datos, se muestra un mensaje de error detallado.
            $mensaje = "Error en la base de datos: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Nuevo Animal</title>
    <style>
        /* --- ESTILOS CSS --- */
        /* Estilos visuales para el formulario y la página. */
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f4f4; color: #333; }
        .container { width: 90%; max-width: 600px; margin: 30px auto; background: #fff; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { text-align: center; color: #4CAF50; margin-bottom: 25px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
        .form-group input[type="submit"] { background-color: #5cb85c; color: white; border: none; cursor: pointer; transition: background-color 0.3s; }
        .mensaje { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; }
        .mensaje.error { background-color: #f2dede; color: #a94442; }
        .back-link { display: block; text-align: center; margin-top: 25px; }
        
        /* Estilo para ocultar el campo de texto "Otro" por defecto. */
        #otro_tipo_animal_container {
            display: none;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML DEL FORMULARIO --- -->
    <div class="container">
        <h1>Registrar Nuevo Animal</h1>

        <!-- Muestra un mensaje de éxito o error si la variable $mensaje no está vacía. -->
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo (stripos($mensaje, 'exitosamente') !== false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- El formulario envía los datos a la misma página (PHP_SELF) usando el método POST. -->
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div class="form-group">
                <label for="nombre_animal">Nombre / Identificador del Lote (Opcional si Cantidad > 1):</label>
                <input type="text" name="nombre_animal" id="nombre_animal" maxlength="100">
            </div>

            <!-- Campo para seleccionar el tipo de animal. Ha sido cambiado de un input de texto a un select. -->
            <div class="form-group">
                <label for="tipo_animal">Tipo de Animal:</label>
                <!-- Menú desplegable (<select>) que se llena con la lista predefinida en PHP. -->
                <select name="tipo_animal" id="tipo_animal" required>
                    <option value="">-- Seleccione un tipo --</option>
                    <?php foreach ($tipos_de_animales_comunes as $tipo): ?>
                        <option value="<?php echo htmlspecialchars($tipo); ?>"><?php echo htmlspecialchars($tipo); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Contenedor para el campo de texto "Otro", que está oculto por defecto gracias al CSS. -->
                <div id="otro_tipo_animal_container">
                    <label for="tipo_animal_otro" style="margin-top:10px;">Por favor, especifique el tipo:</label>
                    <input type="text" name="tipo_animal_otro" id="tipo_animal_otro" maxlength="50">
                </div>
            </div>

            <div class="form-group">
                <label for="cantidad">Cantidad:</label>
                <input type="number" name="cantidad" id="cantidad" value="1" min="1" required>
            </div>

            <div class="form-group">
                <label for="raza">Raza (Opcional):</label>
                <input type="text" name="raza" id="raza" maxlength="50">
            </div>

            <div class="form-group">
                <label for="fecha_nacimiento">Fecha de Nacimiento (Opcional):</label>
                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento">
            </div>

            <div class="form-group">
                <label for="sexo">Sexo:</label>
                <select name="sexo" id="sexo">
                    <option value="Desconocido" selected>Desconocido / Mixto</option>
                    <option value="Macho">Macho</option>
                    <option value="Hembra">Hembra</option>
                </select>
            </div>

            <div class="form-group">
                <label for="identificador_unico">Identificador Único Adicional (Opcional):</label>
                <input type="text" name="identificador_unico" id="identificador_unico" maxlength="50">
            </div>

            <div class="form-group">
                <input type="submit" value="Registrar Animal/Lote">
            </div>
        </form>
        <a href="mis_animales.php" class="back-link">Volver a Mis Animales</a>
    </div>

    <!-- --- SCRIPT JAVASCRIPT PARA LA INTERACTIVIDAD DEL FORMULARIO --- -->
    <script>
        // Se ejecuta cuando el contenido HTML de la página ha sido completamente cargado.
        document.addEventListener('DOMContentLoaded', function() {
            // Se obtienen los elementos del DOM necesarios: el select y el contenedor del campo "Otro".
            const tipoAnimalSelect = document.getElementById('tipo_animal');
            const otroTipoContainer = document.getElementById('otro_tipo_animal_container');
            const otroTipoInput = document.getElementById('tipo_animal_otro');

            // Se añade un "escuchador de eventos" que se activa cada vez que el usuario cambia la opción del select.
            tipoAnimalSelect.addEventListener('change', function() {
                // Si el valor de la opción seleccionada es "Otro"...
                if (this.value === 'Otro') {
                    // ...se muestra el contenedor del campo de texto.
                    otroTipoContainer.style.display = 'block';
                    // ...y se hace que el campo de texto sea obligatorio.
                    otroTipoInput.required = true; 
                } else {
                    // Si se selecciona cualquier otra opción...
                    // ...se oculta el contenedor del campo de texto.
                    otroTipoContainer.style.display = 'none';
                    // ...se quita el requerimiento del campo de texto.
                    otroTipoInput.required = false; 
                    // ...y se limpia su valor para evitar enviar datos incorrectos.
                    otroTipoInput.value = ''; 
                }
            });
        });
    </script>
</body>
</html>