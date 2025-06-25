<?php
session_start();
// (Cabeceras de no-cache)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    header("Location: ../pages/auth/login.html");
    exit();
}
include '../conexion.php'; 

$trat_pred = [ // Valores por defecto para un nuevo tratamiento
    'id_trat_pred' => null,
    'id_tipo_cultivo' => '',
    'tipo_tratamiento' => '',
    'producto_usado' => '',
    'etapas' => '',
    'dosis' => '',
    'unidad_dosis' => 'kg/ha',
    'observaciones' => '',
    'dias_despues_inicio_aplicacion' => 0
];
$page_title = "Crear Nuevo Tratamiento Predeterminado";
$action_url = "admin_edit_trat_pred.php";
$mensaje_form = '';
$tipos_cultivo_list = [];

// Cargar tipos de cultivo para el selector
if (isset($pdo)) {
    try {
        $stmt_tc = $pdo->query("SELECT id_tipo_cultivo, nombre_cultivo FROM tipos_cultivo ORDER BY nombre_cultivo ASC");
        $tipos_cultivo_list = $stmt_tc->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje_form = "Error al cargar tipos de cultivo: " . $e->getMessage();
    }
} else {
    $mensaje_form = "Error: Conexión a la base de datos no disponible.";
}


// Si se recibe un ID, es para editar
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_a_editar = (int)$_GET['id'];
    $page_title = "Editar Tratamiento Predeterminado (ID: $id_a_editar)";
    $action_url = "admin_edit_trat_pred.php?id=$id_a_editar";

    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM tratamientos_predeterminados WHERE id_trat_pred = :id");
            $stmt->bindParam(':id', $id_a_editar, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                $trat_pred = $data;
            } else {
                $_SESSION['mensaje_error_trat_pred'] = "Tratamiento predeterminado no encontrado (ID: $id_a_editar).";
                header("Location: admin_manage_trat_pred.php");
                exit();
            }
        } catch (PDOException $e) {
            $mensaje_form = "Error al cargar el tratamiento: " . $e->getMessage();
        }
    }
}

// Procesar el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($pdo)) {
    // Recoger datos del POST
    $id_trat_pred_post = filter_input(INPUT_POST, 'id_trat_pred', FILTER_VALIDATE_INT); // Para saber si es update
    $id_tipo_cultivo = filter_input(INPUT_POST, 'id_tipo_cultivo', FILTER_VALIDATE_INT);
    $tipo_tratamiento = trim($_POST['tipo_tratamiento']);
    $producto_usado = trim($_POST['producto_usado']);
    $etapas = trim($_POST['etapas']);
    $dosis = filter_input(INPUT_POST, 'dosis', FILTER_VALIDATE_FLOAT);
    $unidad_dosis = trim($_POST['unidad_dosis']);
    $observaciones = !empty(trim($_POST['observaciones'])) ? trim($_POST['observaciones']) : null;
    $dias_despues_inicio_aplicacion = filter_input(INPUT_POST, 'dias_despues_inicio_aplicacion', FILTER_VALIDATE_INT);
    if ($dias_despues_inicio_aplicacion === false) $dias_despues_inicio_aplicacion = 0; // Default si no es un int válido


    // Actualizar el array $trat_pred con los datos del POST para repoblar el form en caso de error
    $trat_pred = $_POST;
    $trat_pred['id_trat_pred'] = $id_trat_pred_post;


    // Validaciones básicas
    if (empty($id_tipo_cultivo)) $mensaje_form = "Debe seleccionar un tipo de cultivo.";
    elseif (empty($tipo_tratamiento)) $mensaje_form = "El tipo de tratamiento es obligatorio.";
    elseif (empty($producto_usado)) $mensaje_form = "El producto usado es obligatorio.";
    elseif (empty($etapas)) $mensaje_form = "Las etapas son obligatorias.";
    elseif ($dosis === false || $dosis <= 0) $mensaje_form = "La dosis debe ser un número positivo.";
    elseif (empty($unidad_dosis)) $mensaje_form = "La unidad de dosis es obligatoria.";
    // $dias_despues_inicio_aplicacion puede ser 0 o negativo (pre-siembra), así que no validamos > 0
    
    if (empty($mensaje_form)) {
        try {
            if ($id_trat_pred_post) { // Actualizar
                $sql = "UPDATE tratamientos_predeterminados SET 
                            id_tipo_cultivo = :id_tipo_cultivo, tipo_tratamiento = :tipo_tratamiento,
                            producto_usado = :producto_usado, etapas = :etapas, dosis = :dosis,
                            unidad_dosis = :unidad_dosis, observaciones = :observaciones,
                            dias_despues_inicio_aplicacion = :dias_despues_inicio_aplicacion
                        WHERE id_trat_pred = :id_trat_pred";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id_trat_pred', $id_trat_pred_post, PDO::PARAM_INT);
            } else { // Crear nuevo
                $sql = "INSERT INTO tratamientos_predeterminados 
                            (id_tipo_cultivo, tipo_tratamiento, producto_usado, etapas, dosis, unidad_dosis, observaciones, dias_despues_inicio_aplicacion)
                        VALUES 
                            (:id_tipo_cultivo, :tipo_tratamiento, :producto_usado, :etapas, :dosis, :unidad_dosis, :observaciones, :dias_despues_inicio_aplicacion)";
                $stmt = $pdo->prepare($sql);
            }

            $stmt->bindParam(':id_tipo_cultivo', $id_tipo_cultivo, PDO::PARAM_INT);
            $stmt->bindParam(':tipo_tratamiento', $tipo_tratamiento);
            $stmt->bindParam(':producto_usado', $producto_usado);
            $stmt->bindParam(':etapas', $etapas);
            $stmt->bindParam(':dosis', $dosis);
            $stmt->bindParam(':unidad_dosis', $unidad_dosis);
            $stmt->bindParam(':observaciones', $observaciones);
            $stmt->bindParam(':dias_despues_inicio_aplicacion', $dias_despues_inicio_aplicacion, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['mensaje_exito_trat_pred'] = $id_trat_pred_post ? "Tratamiento predeterminado actualizado exitosamente." : "Tratamiento predeterminado creado exitosamente.";
                header("Location: admin_manage_trat_pred.php");
                exit();
            } else {
                $mensaje_form = "Error al guardar el tratamiento predeterminado.";
            }
        } catch (PDOException $e) {
            // Verificar error de constraint (ej. nombre_producto duplicado si lo tuvieras UNIQUE)
            if ($e->getCode() == '23000') { // Código de error SQLSTATE para violación de integridad
                 $mensaje_form = "Error: Podría haber un conflicto con datos existentes (ej. un nombre de producto duplicado si es único). Detalles: " . $e->getMessage();
            } else {
                $mensaje_form = "Error en la base de datos: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
        .container { width: 90%; max-width: 700px; margin: 30px auto; background: #fff; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { text-align: center; color: #4CAF50; margin-bottom: 25px; font-size: 1.5em;}
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; color: #555; font-weight: bold; font-size: 0.9em; }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;
            box-sizing: border-box; font-size: 0.95em;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group input[type="submit"], .btn-cancel {
            padding: 10px 18px; border: none; border-radius: 4px; cursor: pointer;
            font-size: 0.95em; font-weight: bold; transition: background-color 0.3s ease;
            text-decoration: none; display: inline-block; text-align: center;
        }
        .form-group input[type="submit"] { background-color: #5cb85c; color: white; margin-right: 10px; }
        .form-group input[type="submit"]:hover { background-color: #4cae4c; }
        .btn-cancel { background-color: #f0f0f0; color: #333; border: 1px solid #ccc; }
        .btn-cancel:hover { background-color: #e0e0e0; }
        .mensaje-form { padding: 10px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-size: 0.9em; }
        .mensaje-form.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>

        <?php if (!empty($mensaje_form)): ?>
            <p class="mensaje-form error"><?php echo htmlspecialchars($mensaje_form); ?></p>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($action_url); ?>" method="POST">
            <?php if ($trat_pred['id_trat_pred']): ?>
                <input type="hidden" name="id_trat_pred" value="<?php echo htmlspecialchars($trat_pred['id_trat_pred']); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="id_tipo_cultivo">Cultivo Asociado:</label>
                <select name="id_tipo_cultivo" id="id_tipo_cultivo" required>
                    <option value="">-- Seleccione un Tipo de Cultivo --</option>
                    <?php foreach ($tipos_cultivo_list as $tc): ?>
                        <option value="<?php echo $tc['id_tipo_cultivo']; ?>" <?php echo ($trat_pred['id_tipo_cultivo'] == $tc['id_tipo_cultivo']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tc['nombre_cultivo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="tipo_tratamiento">Tipo de Tratamiento (Ej: Fertilización, Control Herbicida):</label>
                <input type="text" name="tipo_tratamiento" id="tipo_tratamiento" value="<?php echo htmlspecialchars($trat_pred['tipo_tratamiento']); ?>" maxlength="50" required>
            </div>

            <div class="form-group">
                <label for="producto_usado">Producto Usado (Ej: NPK 15-15-15, Glifosato):</label>
                <input type="text" name="producto_usado" id="producto_usado" value="<?php echo htmlspecialchars($trat_pred['producto_usado']); ?>" maxlength="50" required>
            </div>

            <div class="form-group">
                <label for="etapas">Etapas de Aplicación (Ej: Siembra, Macollamiento, Pre-emergencia):</label>
                <input type="text" name="etapas" id="etapas" value="<?php echo htmlspecialchars($trat_pred['etapas']); ?>" maxlength="100" required>
            </div>

            <div class="form-group">
                <label for="dosis">Dosis:</label>
                <input type="number" name="dosis" id="dosis" value="<?php echo htmlspecialchars($trat_pred['dosis']); ?>" step="0.01" min="0.01" required>
            </div>

            <div class="form-group">
                <label for="unidad_dosis">Unidad de Dosis (Ej: kg/ha, L/ha, g/planta):</label>
                <input type="text" name="unidad_dosis" id="unidad_dosis" value="<?php echo htmlspecialchars($trat_pred['unidad_dosis']); ?>" maxlength="20" required>
            </div>
            
            <div class="form-group">
                <label for="dias_despues_inicio_aplicacion">Días para Aplicación (desde inicio de cultivo, 0=al inicio, negativo=antes):</label>
                <input type="number" name="dias_despues_inicio_aplicacion" id="dias_despues_inicio_aplicacion" value="<?php echo htmlspecialchars($trat_pred['dias_despues_inicio_aplicacion']); ?>" step="1" required>
            </div>

            <div class="form-group">
                <label for="observaciones">Observaciones (Opcional):</label>
                <textarea name="observaciones" id="observaciones" maxlength="100"><?php echo htmlspecialchars($trat_pred['observaciones']); ?></textarea>
            </div>

            <div class="form-group">
                <input type="submit" value="<?php echo $trat_pred['id_trat_pred'] ? 'Actualizar Tratamiento' : 'Crear Tratamiento'; ?>">
                <a href="admin_manage_trat_pred.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>