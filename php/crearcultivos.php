<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado (asumimos que cualquier usuario logueado puede crear cultivos)
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.html"); // Ajusta esta ruta según tu estructura
    exit();
}
// Si solo los usuarios normales (rol 2) pueden crear, y no los admins:
/*
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 2) {
    // Redirigir o mostrar error si no es rol usuario
    header("Location: index.php"); // O a una página de no autorizado
    exit();
}
*/

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
$error_formulario = false; // Para controlar el estado del formulario y repopular
$tipos_cultivo_con_tiempo = [];
$municipios = [];
$id_estado_cultivo_en_progreso = 1; // ASUME que 1 es el ID para 'En Progreso' en tu tabla estado_cultivo_definiciones

// Obtener tipos de cultivo y municipios para los dropdowns
try {
    $stmt_tipos = $pdo->query("SELECT `id_tipo_cultivo`, `nombre_cultivo`, `tiempo_estimado_frutos` FROM `tipos_cultivo` ORDER BY `nombre_cultivo`");
    $tipos_cultivo_con_tiempo = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

    $stmt_municipios = $pdo->query("SELECT `id_municipio`, `nombre` FROM `municipio` ORDER BY `nombre`");
    $municipios = $stmt_municipios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar datos iniciales para el formulario: " . $e->getMessage();
    $error_formulario = true; 
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = $_SESSION['id_usuario'];
    $id_tipo_cultivo_seleccionado = $_POST['id_tipo_cultivo'];
    $fecha_inicio_cultivo_str = $_POST['fecha_inicio'];
    $fecha_fin_post = !empty($_POST['fecha_fin']) ? trim($_POST['fecha_fin']) : null;
    $area_hectarea = $_POST['area_hectarea'];
    $id_municipio = $_POST['id_municipio'];

    // Validación básica de campos
    if (empty($id_tipo_cultivo_seleccionado) || empty($fecha_inicio_cultivo_str) || empty($area_hectarea) || empty($id_municipio)) {
        $mensaje = "Por favor, complete todos los campos obligatorios.";
        $error_formulario = true;
    }

    // Determinar la fecha_fin para la base de datos
    $fecha_fin_para_db = null;
    $tiempo_estimado_db = 0;

    if (!$error_formulario) { // Solo proceder si los campos básicos están
        foreach ($tipos_cultivo_con_tiempo as $tipo_c) {
            if ($tipo_c['id_tipo_cultivo'] == $id_tipo_cultivo_seleccionado) {
                $tiempo_estimado_db = (int)($tipo_c['tiempo_estimado_frutos'] ?? 0);
                break;
            }
        }

        if (!empty($fecha_fin_post)) { // Si el usuario ingresó una fecha de fin
            $fecha_fin_obj_post = DateTime::createFromFormat('Y-m-d', $fecha_fin_post);
            if ($fecha_fin_obj_post && $fecha_fin_obj_post->format('Y-m-d') === $fecha_fin_post) {
                if (strtotime($fecha_fin_post) < strtotime($fecha_inicio_cultivo_str)) {
                    $mensaje = "La fecha de fin ingresada no puede ser anterior a la fecha de inicio.";
                    $error_formulario = true;
                } else {
                    $fecha_fin_para_db = $fecha_fin_post; // Usar la fecha del usuario si es válida
                }
            } else {
                $mensaje = "El formato de la fecha de fin ingresada no es válido.";
                $error_formulario = true;
            }
        } elseif ($tiempo_estimado_db > 0) { // Si no ingresó fecha de fin, pero hay tiempo estimado
            try {
                $fechaInicioObj = new DateTime($fecha_inicio_cultivo_str);
                $fechaInicioObj->add(new DateInterval('P' . $tiempo_estimado_db . 'D'));
                $fecha_fin_para_db = $fechaInicioObj->format('Y-m-d');
            } catch (Exception $e) {
                $mensaje = "Error al calcular la fecha de fin estimada.";
                $error_formulario = true;
            }
        } else {
            // Si no hay fecha_fin del post NI tiempo_estimado_frutos, y fecha_fin es NOT NULL
            // Puedes asignar la misma fecha de inicio o manejar como error.
            // Tu tabla la tiene como NOT NULL. Asignaremos la fecha de inicio.
            $fecha_fin_para_db = $fecha_inicio_cultivo_str;
            // O podrías hacer:
            // $mensaje = "No se pudo determinar la fecha de fin del cultivo.";
            // $error_formulario = true;
        }
    }


    if (!$error_formulario) {
        $pdo->beginTransaction();
        try {
            $sql_cultivo = "INSERT INTO `cultivos` 
                                (`id_usuario`, `id_tipo_cultivo`, `fecha_inicio`, `fecha_fin`, `area_hectarea`, `id_municipio`, `id_estado_cultivo`) 
                            VALUES 
                                (:id_usuario, :id_tipo_cultivo, :fecha_inicio, :fecha_fin, :area_hectarea, :id_municipio, :id_estado_cultivo)";
            $stmt_cultivo = $pdo->prepare($sql_cultivo);
            $stmt_cultivo->bindParam(':id_usuario', $id_usuario, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':id_tipo_cultivo', $id_tipo_cultivo_seleccionado, PDO::PARAM_INT);
            $stmt_cultivo->bindParam(':fecha_inicio', $fecha_inicio_cultivo_str, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':fecha_fin', $fecha_fin_para_db, PDO::PARAM_STR); // Usar la fecha determinada
            $stmt_cultivo->bindParam(':area_hectarea', $area_hectarea, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':id_municipio', $id_municipio, PDO::PARAM_INT);
            $stmt_cultivo->bindParam(':id_estado_cultivo', $id_estado_cultivo_en_progreso, PDO::PARAM_INT); // Nuevo estado

            if ($stmt_cultivo->execute()) {
                $id_cultivo_creado = $pdo->lastInsertId();

                // Lógica para insertar tratamientos predeterminados (se mantiene igual)
                $sql_trat_pred = "SELECT * FROM `tratamientos_predeterminados` WHERE `id_tipo_cultivo` = :id_tipo_cultivo_pred";
                $stmt_trat_pred = $pdo->prepare($sql_trat_pred);
                $stmt_trat_pred->bindParam(':id_tipo_cultivo_pred', $id_tipo_cultivo_seleccionado, PDO::PARAM_INT);
                $stmt_trat_pred->execute();
                $tratamientos_a_aplicar = $stmt_trat_pred->fetchAll(PDO::FETCH_ASSOC);

                if ($tratamientos_a_aplicar) {
                    $sql_insert_tratamiento = "INSERT INTO `tratamiento_cultivo` 
                                               (`id_cultivo`, `id_tipo_cultivo`, `tipo_tratamiento`, `producto_usado`, `etapas`, `dosis`, `observaciones`, `fecha_aplicacion_estimada`) 
                                               VALUES (:id_cultivo, :id_tipo_cultivo_trat, :tipo_tratamiento, :producto_usado, :etapas, :dosis, :observaciones, :fecha_aplicacion_estimada)";
                    $stmt_insert_tratamiento = $pdo->prepare($sql_insert_tratamiento);

                    foreach ($tratamientos_a_aplicar as $trat_pred) {
                        $fecha_aplicacion_calc = null;
                        if (isset($trat_pred['dias_despues_inicio_aplicacion'])) {
                            try {
                                $fechaInicioTratObj = new DateTime($fecha_inicio_cultivo_str);
                                $diasOffset = (int)$trat_pred['dias_despues_inicio_aplicacion'];
                                if ($diasOffset >= 0) {
                                    $fechaInicioTratObj->add(new DateInterval('P' . $diasOffset . 'D'));
                                } else {
                                    $fechaInicioTratObj->sub(new DateInterval('P' . abs($diasOffset) . 'D'));
                                }
                                $fecha_aplicacion_calc = $fechaInicioTratObj->format('Y-m-d');
                            } catch (Exception $dateEx) { $fecha_aplicacion_calc = null; }
                        }

                        $stmt_insert_tratamiento->bindParam(':id_cultivo', $id_cultivo_creado, PDO::PARAM_INT);
                        $stmt_insert_tratamiento->bindParam(':id_tipo_cultivo_trat', $id_tipo_cultivo_seleccionado, PDO::PARAM_INT); // Usar el del cultivo
                        $stmt_insert_tratamiento->bindParam(':tipo_tratamiento', $trat_pred['tipo_tratamiento']);
                        $stmt_insert_tratamiento->bindParam(':producto_usado', $trat_pred['producto_usado']);
                        $stmt_insert_tratamiento->bindParam(':etapas', $trat_pred['etapas']);
                        $stmt_insert_tratamiento->bindParam(':dosis', $trat_pred['dosis']);
                        $stmt_insert_tratamiento->bindParam(':observaciones', $trat_pred['observaciones']);
                        $stmt_insert_tratamiento->bindParam(':fecha_aplicacion_estimada', $fecha_aplicacion_calc);
                        $stmt_insert_tratamiento->execute();
                    }
                }
                $pdo->commit();
                $mensaje = "¡Cultivo y plan inicial registrados exitosamente!";
                $_POST = array(); // Limpiar campos del formulario
            } else {
                $pdo->rollBack();
                $mensaje = "Error al crear el cultivo.";
                $error_formulario = true;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensaje = "Error en la base de datos: " . $e->getMessage();
            $error_formulario = true;
        } catch (Exception $e) { // Para errores de DateTime, etc.
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensaje = "Error general del sistema: " . $e->getMessage();
            $error_formulario = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nuevo Cultivo - GAG</title>
    <link rel="stylesheet" href="../css/estilos.css"> <!-- Ajusta ruta -->
    <style>
        /* Estilos del formulario (como los tenías) */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .page-container { max-width: 700px; margin: 30px auto; } /* Contenedor para el título + form */
        .form-wrapper { background: #fff; padding: 25px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .page-container h1 { text-align: center; color: #4caf50; margin-bottom: 20px; font-size:1.8em;}
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight:bold; }
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%; padding: 10px; border: 1px solid #ddd;
            border-radius: 4px; box-sizing: border-box; font-size: 1em;
        }
        .form-group input[type="submit"], .btn-submit { /* Aplicar a ambos */
            background-color: #5cb85c; color: white; padding: 10px 15px;
            border: none; border-radius: 4px; cursor: pointer; font-size: 1em; font-weight:bold;
        }
        .form-group input[type="submit"]:hover, .btn-submit:hover { background-color: #4cae4c; }
        .mensaje { padding: 10px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #337ab7; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        /* Estilos del header (si no están en estilos.css) */
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;}
        .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;white-space:nowrap;font-size:.9em;transition:background-color .3s, color .3s}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}
        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}}
        @media (max-width:768px){.logo img{height:60px} .page-container{width:90%; margin:20px auto;} .form-wrapper{padding:20px;}}
        @media (max-width:480px){.logo img{height:50px} .menu-toggle{font-size:1.6rem} .page-container h1{font-size:1.5em;}}
    </style>
</head>
<body>
    <!-- Incluye tu header aquí -->
    <div class="header">
        <div class="logo"><img src="../img/logo.png" alt="Logo GAG" /></div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="index.php">Inicio</a>
            <a href="miscultivos.php" class="active">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario_general.php">Calendario</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h1>Crear Nuevo Cultivo</h1>
        <div class="form-wrapper">
            <?php if (!empty($mensaje)): ?>
                <div class="mensaje <?php echo $error_formulario ? 'error' : 'exito'; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <?php if (!$error_formulario || ($error_formulario && $_SERVER["REQUEST_METHOD"] == "POST")): // Mostrar formulario si no hay error crítico inicial o si es un error de POST ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="form-group">
                    <label for="id_tipo_cultivo">Tipo de Cultivo:</label>
                    <select name="id_tipo_cultivo" id="id_tipo_cultivo" required>
                        <option value="">Seleccione un tipo</option>
                        <?php foreach ($tipos_cultivo_con_tiempo as $tipo): ?>
                            <option value="<?php echo htmlspecialchars($tipo['id_tipo_cultivo']); ?>"
                                    data-tiempo_estimado="<?php echo htmlspecialchars($tipo['tiempo_estimado_frutos'] ?? '0'); ?>"
                                    <?php echo (isset($_POST['id_tipo_cultivo']) && $_POST['id_tipo_cultivo'] == $tipo['id_tipo_cultivo']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tipo['nombre_cultivo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fecha_inicio">Fecha de Inicio:</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo isset($_POST['fecha_inicio']) && $error_formulario ? htmlspecialchars($_POST['fecha_inicio']) : date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="fecha_fin">Fecha de Fin (Estimada por sistema o manual):</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo isset($_POST['fecha_fin']) && $error_formulario ? htmlspecialchars($_POST['fecha_fin']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="area_hectarea">Área (Hectáreas):</label>
                    <input type="number" name="area_hectarea" id="area_hectarea" step="0.01" min="0.01" value="<?php echo isset($_POST['area_hectarea']) && $error_formulario ? htmlspecialchars($_POST['area_hectarea']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="id_municipio">Municipio:</label>
                    <select name="id_municipio" id="id_municipio" required>
                        <option value="">Seleccione un municipio</option>
                        <?php foreach ($municipios as $municipio): ?>
                            <option value="<?php echo htmlspecialchars($municipio['id_municipio']); ?>"
                                    <?php echo (isset($_POST['id_municipio']) && $_POST['id_municipio'] == $municipio['id_municipio']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($municipio['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group action-buttons">
                    <button type="submit" class="btn-submit">Crear Cultivo</button>
                    <a href="index.php" class="btn-cancel" style="margin-left: 10px;">Cancelar</a>
                </div>
            </form>
            <?php endif; // Fin de if para mostrar formulario ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menú Hamburguesa
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
                });
            }

            // Calcular Fecha Fin Estimada
            const tipoCultivoSelect = document.getElementById('id_tipo_cultivo');
            const fechaInicioInput = document.getElementById('fecha_inicio');
            const fechaFinInput = document.getElementById('fecha_fin');

            function calcularFechaFin() {
                if (!tipoCultivoSelect || !fechaInicioInput || !fechaFinInput) return;

                const tipoSeleccionado = tipoCultivoSelect.options[tipoCultivoSelect.selectedIndex];
                if (!tipoSeleccionado || !tipoSeleccionado.value) {
                    // No limpiar fecha_fin si el usuario ya puso algo manualmente
                    // fechaFinInput.value = ''; 
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
                            // fechaFinInput.value = ''; // No limpiar
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
                        // fechaFinInput.value = ''; // No limpiar
                    }
                } else if (fechaInicioValor && (!tiempoEstimadoDias || tiempoEstimadoDias <= 0)) {
                    // Si hay fecha de inicio pero el cultivo no tiene tiempo estimado,
                    // se podría limpiar la fecha_fin o dejar que el usuario la ponga.
                    // Por ahora, no la limpiamos si ya tenía un valor.
                }
            }
            if(tipoCultivoSelect) tipoCultivoSelect.addEventListener('change', calcularFechaFin);
            if(fechaInicioInput) {
                 fechaInicioInput.addEventListener('change', calcularFechaFin);
                 fechaInicioInput.addEventListener('input', calcularFechaFin); // Para reactividad
                 // Calcular al cargar la página si ya hay valores
                 if (tipoCultivoSelect.value && fechaInicioInput.value) {
                    calcularFechaFin();
                 }
            }
        });
    </script>
</body>
</html>