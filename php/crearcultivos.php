<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado y tiene rol de usuario (id_rol = 2)
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 2) {
    header("Location: http://localhost/proyecto_gag/login.html");
    exit();
}

// Incluir el archivo de conexión
$ruta_conexion = __DIR__ . '/conexion.php';
if (!file_exists($ruta_conexion)) {
    die("Error crítico: No se encontró el archivo de configuración de la base de datos.");
}
include $ruta_conexion;

if (!isset($pdo)) {
    die("Error crítico: No se pudo establecer la conexión con la base de datos (\$pdo no está definido).");
}

$mensaje = '';
$tipos_cultivo_con_tiempo = [];
$municipios = [];

// Obtener tipos de cultivo para el dropdown
try {
    $stmt_tipos = $pdo->query("SELECT `id_tipo_cultivo`, `nombre_cultivo`, `tiempo_estimado_frutos` FROM `tipos_cultivo` ORDER BY `nombre_cultivo`");
    $tipos_cultivo_con_tiempo = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar tipos de cultivo: " . $e->getMessage();
}

// Obtener municipios para el dropdown
if (empty($mensaje)) {
    try {
        $stmt_municipios = $pdo->query("SELECT `id_municipio`, `nombre` FROM `municipio` ORDER BY `nombre`");
        $municipios = $stmt_municipios->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje = "Error al cargar municipios: " . $e->getMessage();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = $_SESSION['id_usuario'];
    $id_tipo_cultivo_seleccionado = $_POST['id_tipo_cultivo'];
    $fecha_inicio_cultivo = $_POST['fecha_inicio'];
    $fecha_fin_estimada = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
    $area_hectarea = $_POST['area_hectarea'];
    $id_municipio = $_POST['id_municipio'];

    if (empty($id_tipo_cultivo_seleccionado) || empty($fecha_inicio_cultivo) || empty($area_hectarea) || empty($id_municipio)) {
        $mensaje = "Por favor, complete todos los campos obligatorios.";
    } elseif ($fecha_fin_estimada && strtotime($fecha_fin_estimada) < strtotime($fecha_inicio_cultivo)) {
        $mensaje = "La fecha de fin no puede ser anterior a la fecha de inicio.";
    } else {
        $pdo->beginTransaction();

        try {
            $sql_cultivo = "INSERT INTO `cultivos` (`id_usuario`, `id_tipo_cultivo`, `fecha_inicio`, `fecha_fin`, `area_hectarea`, `id_municipio`) 
                            VALUES (:id_usuario, :id_tipo_cultivo, :fecha_inicio, :fecha_fin, :area_hectarea, :id_municipio)";
            $stmt_cultivo = $pdo->prepare($sql_cultivo);
            $stmt_cultivo->bindParam(':id_usuario', $id_usuario, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':id_tipo_cultivo', $id_tipo_cultivo_seleccionado, PDO::PARAM_INT);
            $stmt_cultivo->bindParam(':fecha_inicio', $fecha_inicio_cultivo, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':fecha_fin', $fecha_fin_estimada, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':area_hectarea', $area_hectarea, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':id_municipio', $id_municipio, PDO::PARAM_INT);

            if ($stmt_cultivo->execute()) {
                $id_cultivo_creado = $pdo->lastInsertId();

                $sql_trat_pred = "SELECT * FROM `tratamientos_predeterminados` WHERE `id_tipo_cultivo` = :id_tipo_cultivo";
                $stmt_trat_pred = $pdo->prepare($sql_trat_pred);
                $stmt_trat_pred->bindParam(':id_tipo_cultivo', $id_tipo_cultivo_seleccionado, PDO::PARAM_INT);
                $stmt_trat_pred->execute();
                $tratamientos_a_aplicar = $stmt_trat_pred->fetchAll(PDO::FETCH_ASSOC);

                if ($tratamientos_a_aplicar) {
                    $sql_insert_tratamiento = "INSERT INTO `tratamiento_cultivo` 
                                               (`id_cultivo`, `id_tipo_cultivo`, `tipo_tratamiento`, `producto_usado`, `etapas`, `dosis`, `observaciones`, `fecha_aplicacion_estimada`) 
                                               VALUES (:id_cultivo, :id_tipo_cultivo, :tipo_tratamiento, :producto_usado, :etapas, :dosis, :observaciones, :fecha_aplicacion_estimada)";
                    $stmt_insert_tratamiento = $pdo->prepare($sql_insert_tratamiento);

                    foreach ($tratamientos_a_aplicar as $trat_pred) {
                        $fecha_aplicacion_calc = null;
                        if (isset($trat_pred['dias_despues_inicio_aplicacion'])) {
                            try {
                                $fechaInicioObj = new DateTime($fecha_inicio_cultivo);
                                $diasOffset = (int)$trat_pred['dias_despues_inicio_aplicacion'];
                                if ($diasOffset >= 0) {
                                    $fechaInicioObj->add(new DateInterval('P' . $diasOffset . 'D'));
                                } else {
                                    $fechaInicioObj->sub(new DateInterval('P' . abs($diasOffset) . 'D'));
                                }
                                $fecha_aplicacion_calc = $fechaInicioObj->format('Y-m-d');
                            } catch (Exception $dateEx) {
                                $fecha_aplicacion_calc = null;
                            }
                        }

                        $stmt_insert_tratamiento->bindParam(':id_cultivo', $id_cultivo_creado, PDO::PARAM_INT);
                        $stmt_insert_tratamiento->bindParam(':id_tipo_cultivo', $trat_pred['id_tipo_cultivo'], PDO::PARAM_INT);
                        $stmt_insert_tratamiento->bindParam(':tipo_tratamiento', $trat_pred['tipo_tratamiento'], PDO::PARAM_STR);
                        $stmt_insert_tratamiento->bindParam(':producto_usado', $trat_pred['producto_usado'], PDO::PARAM_STR);
                        $stmt_insert_tratamiento->bindParam(':etapas', $trat_pred['etapas'], PDO::PARAM_STR);
                        $stmt_insert_tratamiento->bindParam(':dosis', $trat_pred['dosis'], PDO::PARAM_STR);
                        $stmt_insert_tratamiento->bindParam(':observaciones', $trat_pred['observaciones'], PDO::PARAM_STR);
                        $stmt_insert_tratamiento->bindParam(':fecha_aplicacion_estimada', $fecha_aplicacion_calc, PDO::PARAM_STR);

                        $stmt_insert_tratamiento->execute();
                    }
                }
                $pdo->commit();
                $mensaje = "¡Cultivo y tratamientos predeterminados registrados exitosamente!";
            } else {
                $pdo->rollBack();
                $mensaje = "Error al crear el cultivo.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje = "Error en la base de datos: " . $e->getMessage();
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error general del sistema: " . $e->getMessage();
        }
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
    <title>Crear Nuevo Cultivo</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { width: 60%; margin: 50px auto; background: #fff; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; }
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group input[type="submit"] {
            background-color: #5cb85c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .form-group input[type="submit"]:hover { background-color: #4cae4c; }
        .mensaje { padding: 10px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #337ab7; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crear Nuevo Cultivo</h1>

        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo (stripos($mensaje, 'exitosamente') !== false || stripos($mensaje, 'error') === false) ? 'exito' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div class="form-group">
                <label for="id_tipo_cultivo">Tipo de Cultivo:</label>
                <select name="id_tipo_cultivo" id="id_tipo_cultivo" required>
                    <option value="">Seleccione un tipo</option>
                    <?php foreach ($tipos_cultivo_con_tiempo as $tipo): ?>
                        <option value="<?php echo htmlspecialchars($tipo['id_tipo_cultivo']); ?>"
                                data-tiempo_estimado="<?php echo htmlspecialchars($tipo['tiempo_estimado_frutos']); ?>">
                            <?php echo htmlspecialchars($tipo['nombre_cultivo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="fecha_inicio">Fecha de Inicio:</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio" required>
            </div>

            <div class="form-group">
                <label for="fecha_fin">Fecha de Fin (Estimada):</label>
                <input type="date" name="fecha_fin" id="fecha_fin">
            </div>

            <div class="form-group">
                <label for="area_hectarea">Área (Hectáreas):</label>
                <input type="number" name="area_hectarea" id="area_hectarea" step="0.01" min="0.01" required>
            </div>

            <div class="form-group">
                <label for="id_municipio">Municipio:</label>
                <select name="id_municipio" id="id_municipio" required>
                    <option value="">Seleccione un municipio</option>
                    <?php foreach ($municipios as $municipio): ?>
                        <option value="<?php echo htmlspecialchars($municipio['id_municipio']); ?>">
                            <?php echo htmlspecialchars($municipio['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <input type="submit" value="Crear Cultivo">
            </div>
        </form>
        <a href="index.php" class="back-link">Volver</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tipoCultivoSelect = document.getElementById('id_tipo_cultivo');
            const fechaInicioInput = document.getElementById('fecha_inicio');
            const fechaFinInput = document.getElementById('fecha_fin');

            function calcularFechaFin() {
                const tipoSeleccionado = tipoCultivoSelect.options[tipoCultivoSelect.selectedIndex];
                if (!tipoSeleccionado || !tipoSeleccionado.value) {
                    fechaFinInput.value = '';
                    return;
                }

                const tiempoEstimadoDias = parseInt(tipoSeleccionado.getAttribute('data-tiempo_estimado'), 10);
                const fechaInicioValor = fechaInicioInput.value;

                if (fechaInicioValor && !isNaN(tiempoEstimadoDias) && tiempoEstimadoDias > 0) {
                    try {
                        const partesFecha = fechaInicioValor.split('-');
                        const anioInicio = parseInt(partesFecha[0], 10);
                        const mesInicio = parseInt(partesFecha[1], 10) - 1;
                        const diaInicio = parseInt(partesFecha[2], 10);
                        
                        const fechaInicioDate = new Date(anioInicio, mesInicio, diaInicio);

                        if (isNaN(fechaInicioDate.getTime())) {
                            fechaFinInput.value = '';
                            return;
                        }
                        
                        const fechaFinDate = new Date(fechaInicioDate);
                        fechaFinDate.setDate(fechaInicioDate.getDate() + tiempoEstimadoDias);

                        const anio = fechaFinDate.getFullYear();
                        const mes = String(fechaFinDate.getMonth() + 1).padStart(2, '0');
                        const dia = String(fechaFinDate.getDate()).padStart(2, '0');
                        
                        fechaFinInput.value = `${anio}-${mes}-${dia}`;
                    } catch (error) {
                        console.error("Error al calcular la fecha de fin:", error);
                        fechaFinInput.value = '';
                    }
                } else {
                    fechaFinInput.value = '';
                }
            }

            tipoCultivoSelect.addEventListener('change', calcularFechaFin);
            fechaInicioInput.addEventListener('change', calcularFechaFin);
            fechaInicioInput.addEventListener('input', calcularFechaFin);
        });
    </script>
</body>
</html>