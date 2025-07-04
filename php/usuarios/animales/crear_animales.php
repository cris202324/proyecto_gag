<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../conexion.php';

// --- LISTA PREDEFINIDA DE TIPOS DE ANIMALES ---
// Puedes gestionar esta lista aquí o, en el futuro, desde una tabla en la base de datos.
$tipos_de_animales_comunes = [
    "caballo", "cerdo", "Ovino", "Caprino", "Abeja", "pollos", "vaca", "Otro"
];


$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = $_SESSION['id_usuario'];
    $nombre_animal = trim($_POST['nombre_animal']);
    $tipo_animal_seleccionado = $_POST['tipo_animal']; // Valor del select
    $tipo_animal_otro = trim($_POST['tipo_animal_otro']); // Valor del campo de texto para "Otro"
    
    // Determinar el tipo de animal final a registrar
    $tipo_animal_final = $tipo_animal_seleccionado;
    if ($tipo_animal_seleccionado === 'Otro' && !empty($tipo_animal_otro)) {
        $tipo_animal_final = $tipo_animal_otro;
    }
    
    $raza = !empty(trim($_POST['raza'])) ? trim($_POST['raza']) : null;
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $sexo = $_POST['sexo'];
    $identificador_unico = !empty(trim($_POST['identificador_unico'])) ? trim($_POST['identificador_unico']) : null;
    $cantidad = isset($_POST['cantidad']) && is_numeric($_POST['cantidad']) && $_POST['cantidad'] > 0 ? (int)$_POST['cantidad'] : 1;

    // Validación básica
    if (empty($tipo_animal_final) || ($tipo_animal_seleccionado === 'Otro' && empty($tipo_animal_otro))) {
        $mensaje = "El tipo de animal es obligatorio. Si seleccionas 'Otro', debes especificar cuál.";
    } elseif ($cantidad <= 0) {
        $mensaje = "La cantidad debe ser un número positivo.";
    } else {
        try {
            $sql = "INSERT INTO animales (id_usuario, nombre_animal, tipo_animal, raza, fecha_nacimiento, sexo, identificador_unico, cantidad)
                    VALUES (:id_usuario, :nombre_animal, :tipo_animal, :raza, :fecha_nacimiento, :sexo, :identificador_unico, :cantidad)";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':nombre_animal', $nombre_animal);
            $stmt->bindParam(':tipo_animal', $tipo_animal_final); // Usamos la variable final
            $stmt->bindParam(':raza', $raza);
            $stmt->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $stmt->bindParam(':sexo', $sexo);
            $stmt->bindParam(':identificador_unico', $identificador_unico);
            $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($cantidad == 1) {
                    $mensaje = "¡Animal registrado exitosamente!";
                } else {
                    $mensaje = "¡Lote de " . htmlspecialchars($cantidad) . " animales registrado exitosamente!";
                }
            } else {
                $mensaje = "Error al registrar el animal/lote.";
            }
        } catch (PDOException $e) {
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
        /* (Tus estilos existentes... sin cambios aquí) */
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
        
        /* Estilo para ocultar el campo de "Otro" por defecto */
        #otro_tipo_animal_container {
            display: none;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Registrar Nuevo Animal</h1>

        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo (stripos($mensaje, 'exitosamente') !== false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div class="form-group">
                <label for="nombre_animal">Nombre / Identificador del Lote (Opcional si Cantidad > 1):</label>
                <input type="text" name="nombre_animal" id="nombre_animal" maxlength="100">
            </div>

            <!-- ===== CAMBIO: de input a select ===== -->
            <div class="form-group">
                <label for="tipo_animal">Tipo de Animal:</label>
                <select name="tipo_animal" id="tipo_animal" required>
                    <option value="">-- Seleccione un tipo --</option>
                    <?php foreach ($tipos_de_animales_comunes as $tipo): ?>
                        <option value="<?php echo htmlspecialchars($tipo); ?>"><?php echo htmlspecialchars($tipo); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Contenedor para el campo de texto "Otro", oculto por defecto -->
                <div id="otro_tipo_animal_container">
                    <label for="tipo_animal_otro" style="margin-top:10px;">Por favor, especifique el tipo:</label>
                    <input type="text" name="tipo_animal_otro" id="tipo_animal_otro" maxlength="50">
                </div>
            </div>
            <!-- ==================================== -->

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

    <!-- Script de JavaScript para mostrar el campo "Otro" -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tipoAnimalSelect = document.getElementById('tipo_animal');
            const otroTipoContainer = document.getElementById('otro_tipo_animal_container');
            const otroTipoInput = document.getElementById('tipo_animal_otro');

            tipoAnimalSelect.addEventListener('change', function() {
                // Si el valor seleccionado es "Otro", muestra el contenedor. Si no, lo oculta.
                if (this.value === 'Otro') {
                    otroTipoContainer.style.display = 'block';
                    otroTipoInput.required = true; // Hacer el campo de texto requerido
                } else {
                    otroTipoContainer.style.display = 'none';
                    otroTipoInput.required = false; // Quitar el requerimiento
                    otroTipoInput.value = ''; // Limpiar el campo por si acaso
                }
            });
        });
    </script>
</body>
</html>