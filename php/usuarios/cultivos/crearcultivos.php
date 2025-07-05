<?php
// Inicia la sesión de PHP. Es un paso crucial que debe ir al principio para poder
// usar variables de sesión como $_SESSION['id_usuario'].
session_start();

// --- CABECERAS HTTP PARA EVITAR EL CACHÉ DEL NAVEGADOR ---
// Estas líneas le indican al navegador que no guarde una copia local (caché) de esta página,
// asegurando que siempre se muestre la versión más reciente del script.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT"); // Una fecha en el pasado.

// --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Comprueba si el usuario ha iniciado sesión. Si la variable de sesión 'id_usuario' no existe,
// se le redirige a la página de login y el script se detiene.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../../pages/auth/login.html"); // Ajusta esta ruta según tu estructura.
    exit(); // Detiene la ejecución para proteger la página.
}

// Opcional: Bloque de autorización comentado. Podrías descomentarlo si solo los usuarios
// con un rol específico (ej. rol 2) pueden crear cultivos.
/*
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 2) {
    header("Location: index.php"); // O a una página de no autorizado.
    exit();
}
*/

// --- INCLUSIÓN DE LA CONEXIÓN Y VERIFICACIÓN ---
// Se incluye el archivo que establece la conexión a la base de datos y define la variable $pdo.
// Se usa __DIR__ para construir una ruta robusta que funcione sin importar la ubicación del script.
$ruta_conexion = __DIR__ . '/../../conexion.php';
if (!file_exists($ruta_conexion)) {
    die("Error crítico: No se encontró el archivo de configuración de la base de datos.");
}
include $ruta_conexion;

// Se comprueba si la conexión fue exitosa. Si $pdo no está definido, se detiene la ejecución.
if (!isset($pdo)) {
    die("Error crítico: No se pudo establecer la conexión con la base de datos (\$pdo no está definido).");
}

// --- DECLARACIÓN DE VARIABLES INICIALES ---
$mensaje = ''; // Para mostrar mensajes de éxito o error al usuario.
$error_formulario = false; // Un 'flag' para controlar si hubo un error y se deben repopular los campos.
$tipos_cultivo_con_tiempo = []; // Almacenará los tipos de cultivo para el menú desplegable.
$municipios = []; // Almacenará los municipios para el menú desplegable.
$id_estado_cultivo_en_progreso = 1; // ID fijo para el estado "En Progreso" al crear un nuevo cultivo.

// --- CARGA DE DATOS PARA LOS MENÚS DESPLEGABLES ---
// Se obtienen los datos necesarios para los <select> del formulario desde la base de datos.
// Esto se hace fuera del bloque POST para que los menús siempre estén disponibles.
try {
    // Obtiene todos los tipos de cultivo, incluyendo su tiempo estimado de cosecha para el cálculo automático.
    $stmt_tipos = $pdo->query("SELECT `id_tipo_cultivo`, `nombre_cultivo`, `tiempo_estimado_frutos` FROM `tipos_cultivo` ORDER BY `nombre_cultivo`");
    $tipos_cultivo_con_tiempo = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

    // Obtiene todos los municipios disponibles para el selector.
    $stmt_municipios = $pdo->query("SELECT `id_municipio`, `nombre` FROM `municipio` ORDER BY `nombre`");
    $municipios = $stmt_municipios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si hay un error al cargar estos datos, se muestra un mensaje y se deshabilita el formulario.
    $mensaje = "Error al cargar datos iniciales para el formulario: " . $e->getMessage();
    $error_formulario = true; 
}


// --- PROCESAMIENTO DEL FORMULARIO (CUANDO EL USUARIO ENVÍA DATOS - MÉTODO POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoge los datos del formulario. `trim()` elimina espacios en blanco.
    $id_usuario = $_SESSION['id_usuario'];
    $id_tipo_cultivo_seleccionado = $_POST['id_tipo_cultivo'];
    $fecha_inicio_cultivo_str = $_POST['fecha_inicio'];
    $fecha_fin_post = !empty($_POST['fecha_fin']) ? trim($_POST['fecha_fin']) : null;
    $area_hectarea = $_POST['area_hectarea'];
    $id_municipio = $_POST['id_municipio'];

    // Validación básica para asegurarse de que los campos obligatorios no estén vacíos.
    if (empty($id_tipo_cultivo_seleccionado) || empty($fecha_inicio_cultivo_str) || empty($area_hectarea) || empty($id_municipio)) {
        $mensaje = "Por favor, complete todos los campos obligatorios.";
        $error_formulario = true;
    }

    // --- LÓGICA PARA DETERMINAR LA FECHA DE FIN DEL CULTIVO ---
    $fecha_fin_para_db = null;
    $tiempo_estimado_db = 0;

    if (!$error_formulario) { // Solo se procede si las validaciones básicas pasaron.
        // Se busca el tiempo estimado de cosecha para el tipo de cultivo seleccionado.
        foreach ($tipos_cultivo_con_tiempo as $tipo_c) {
            if ($tipo_c['id_tipo_cultivo'] == $id_tipo_cultivo_seleccionado) {
                $tiempo_estimado_db = (int)($tipo_c['tiempo_estimado_frutos'] ?? 0);
                break;
            }
        }

        if (!empty($fecha_fin_post)) { // Prioridad 1: Si el usuario ingresó una fecha de fin manualmente.
            $fecha_fin_obj_post = DateTime::createFromFormat('Y-m-d', $fecha_fin_post);
            if ($fecha_fin_obj_post && $fecha_fin_obj_post->format('Y-m-d') === $fecha_fin_post) {
                if (strtotime($fecha_fin_post) < strtotime($fecha_inicio_cultivo_str)) {
                    $mensaje = "La fecha de fin ingresada no puede ser anterior a la fecha de inicio.";
                    $error_formulario = true;
                } else {
                    $fecha_fin_para_db = $fecha_fin_post; // Se usa la fecha del usuario si es válida.
                }
            } else {
                $mensaje = "El formato de la fecha de fin ingresada no es válido.";
                $error_formulario = true;
            }
        } elseif ($tiempo_estimado_db > 0) { // Prioridad 2: Si no hay fecha manual, se calcula a partir del tiempo estimado.
            try {
                $fechaInicioObj = new DateTime($fecha_inicio_cultivo_str);
                $fechaInicioObj->add(new DateInterval('P' . $tiempo_estimado_db . 'D'));
                $fecha_fin_para_db = $fechaInicioObj->format('Y-m-d');
            } catch (Exception $e) {
                $mensaje = "Error al calcular la fecha de fin estimada.";
                $error_formulario = true;
            }
        } else {
            // Prioridad 3: Si no hay fecha manual ni tiempo estimado, se usa la fecha de inicio para cumplir con la BD.
            $fecha_fin_para_db = $fecha_inicio_cultivo_str;
        }
    }


    // --- INSERCIÓN EN LA BASE DE DATOS (SI NO HAY ERRORES) ---
    if (!$error_formulario) {
        // Se inicia una transacción. Esto agrupa todas las consultas siguientes en una sola operación.
        $pdo->beginTransaction();
        try {
            // Se prepara la consulta para insertar el nuevo cultivo.
            $sql_cultivo = "INSERT INTO `cultivos` 
                                (`id_usuario`, `id_tipo_cultivo`, `fecha_inicio`, `fecha_fin`, `area_hectarea`, `id_municipio`, `id_estado_cultivo`) 
                            VALUES 
                                (:id_usuario, :id_tipo_cultivo, :fecha_inicio, :fecha_fin, :area_hectarea, :id_municipio, :id_estado_cultivo)";
            $stmt_cultivo = $pdo->prepare($sql_cultivo);
            $stmt_cultivo->bindParam(':id_usuario', $id_usuario, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':id_tipo_cultivo', $id_tipo_cultivo_seleccionado, PDO::PARAM_INT);
            $stmt_cultivo->bindParam(':fecha_inicio', $fecha_inicio_cultivo_str, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':fecha_fin', $fecha_fin_para_db, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':area_hectarea', $area_hectarea, PDO::PARAM_STR);
            $stmt_cultivo->bindParam(':id_municipio', $id_municipio, PDO::PARAM_INT);
            $stmt_cultivo->bindParam(':id_estado_cultivo', $id_estado_cultivo_en_progreso, PDO::PARAM_INT);

            if ($stmt_cultivo->execute()) {
                // Si la inserción del cultivo es exitosa, se obtiene su ID.
                $id_cultivo_creado = $pdo->lastInsertId();

                // Se buscan los tratamientos predeterminados asociados a este tipo de cultivo.
                $sql_trat_pred = "SELECT * FROM `tratamientos_predeterminados` WHERE `id_tipo_cultivo` = :id_tipo_cultivo_pred";
                $stmt_trat_pred = $pdo->prepare($sql_trat_pred);
                $stmt_trat_pred->bindParam(':id_tipo_cultivo_pred', $id_tipo_cultivo_seleccionado, PDO::PARAM_INT);
                $stmt_trat_pred->execute();
                $tratamientos_a_aplicar = $stmt_trat_pred->fetchAll(PDO::FETCH_ASSOC);

                // Si se encontraron tratamientos, se insertan en la tabla `tratamiento_cultivo`.
                if ($tratamientos_a_aplicar) {
                    $sql_insert_tratamiento = "INSERT INTO `tratamiento_cultivo` 
                                               (`id_cultivo`, `id_tipo_cultivo`, `tipo_tratamiento`, `producto_usado`, `etapas`, `dosis`, `observaciones`, `fecha_aplicacion_estimada`) 
                                               VALUES (:id_cultivo, :id_tipo_cultivo_trat, :tipo_tratamiento, :producto_usado, :etapas, :dosis, :observaciones, :fecha_aplicacion_estimada)";
                    $stmt_insert_tratamiento = $pdo->prepare($sql_insert_tratamiento);

                    foreach ($tratamientos_a_aplicar as $trat_pred) {
                        // Se calcula la fecha estimada de aplicación para cada tratamiento.
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
                        
                        // Se bindean y ejecutan las inserciones para cada tratamiento.
                        $stmt_insert_tratamiento->bindParam(':id_cultivo', $id_cultivo_creado, PDO::PARAM_INT);
                        $stmt_insert_tratamiento->bindParam(':id_tipo_cultivo_trat', $id_tipo_cultivo_seleccionado, PDO::PARAM_INT);
                        $stmt_insert_tratamiento->bindParam(':tipo_tratamiento', $trat_pred['tipo_tratamiento']);
                        $stmt_insert_tratamiento->bindParam(':producto_usado', $trat_pred['producto_usado']);
                        $stmt_insert_tratamiento->bindParam(':etapas', $trat_pred['etapas']);
                        $stmt_insert_tratamiento->bindParam(':dosis', $trat_pred['dosis']);
                        $stmt_insert_tratamiento->bindParam(':observaciones', $trat_pred['observaciones']);
                        $stmt_insert_tratamiento->bindParam(':fecha_aplicacion_estimada', $fecha_aplicacion_calc);
                        $stmt_insert_tratamiento->execute();
                    }
                }
                // Si todas las inserciones fueron exitosas, se confirma la transacción.
                $pdo->commit();
                $mensaje = "¡Cultivo y plan inicial registrados exitosamente!";
                $_POST = array(); // Se limpian los datos del formulario.
            } else {
                $pdo->rollBack(); // Si falla la inserción, se revierte la transacción.
                $mensaje = "Error al crear el cultivo.";
                $error_formulario = true;
            }
        } catch (PDOException | Exception $e) { // Captura errores de BD y de lógica.
            if ($pdo->inTransaction()) $pdo->rollBack(); // Se asegura de revertir si hay error.
            $mensaje = "Error en el proceso: " . $e->getMessage();
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
    <style>
        /* --- ESTILOS CSS --- */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .page-container { max-width: 700px; margin: 30px auto; }
        .form-wrapper { background: #fff; padding: 25px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .page-container h1 { text-align: center; color: #4caf50; margin-bottom: 20px; font-size:1.8em;}
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight:bold; }
        .form-group input[type="date"], .form-group input[type="number"], .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
        .btn-submit { background-color: #5cb85c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; font-weight:bold; }
        .btn-submit:hover { background-color: #4cae4c; }
        .btn-cancel { text-decoration: none; color: #777; padding: 10px 15px; }
        .mensaje { padding: 10px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;}
        .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;white-space:nowrap;font-size:.9em;transition:background-color .3s, color .3s}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}
        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}}
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML DE LA PÁGINA --- -->
    <div class="header">
        <div class="logo"><img src="../../../img/logo.png" alt="Logo GAG" /></div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="../../index.php">Inicio</a>
            <a href="miscultivos.php" class="active">Mis Cultivos</a>
            <a href="../animales/mis_animales.php">Mis Animales</a>
            <a href="../calendario.php">Calendario</a>
            <a href="../configuracion.php">Configuración</a>
            <a href="../ayuda.php">Ayuda</a>
            <a href="../../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h1>Crear Nuevo Cultivo</h1>
        <div class="form-wrapper">
            <!-- Muestra el mensaje de éxito o error. -->
            <?php if (!empty($mensaje)): ?>
                <div class="mensaje <?php echo $error_formulario ? 'error' : 'exito'; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <!-- Muestra el formulario si no hubo un error crítico al cargar la página. -->
            <?php if (!$error_formulario || ($error_formulario && $_SERVER["REQUEST_METHOD"] == "POST")): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <!-- Campos del formulario -->
                <div class="form-group">
                    <label for="id_tipo_cultivo">Tipo de Cultivo:</label>
                    <select name="id_tipo_cultivo" id="id_tipo_cultivo" required>
                        <option value="">Seleccione un tipo</option>
                        <?php foreach ($tipos_cultivo_con_tiempo as $tipo): ?>
                            <!-- El atributo `data-tiempo_estimado` es leído por JavaScript para el cálculo automático. -->
                            <!-- La lógica ternaria en 'selected' asegura que el campo se repopule si hubo un error. -->
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
                    <label for="fecha_fin">Fecha de Fin (opcional, se calcula si se deja vacío):</label>
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
                    <a href="miscultivos.php" class="btn-cancel" style="margin-left: 10px;">Cancelar</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- --- SCRIPT JAVASCRIPT PARA LA INTERACTIVIDAD --- -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- LÓGICA DEL MENÚ HAMBURGUESA ---
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
                });
            }

            // --- LÓGICA PARA CALCULAR LA FECHA DE FIN AUTOMÁTICAMENTE ---
            const tipoCultivoSelect = document.getElementById('id_tipo_cultivo');
            const fechaInicioInput = document.getElementById('fecha_inicio');
            const fechaFinInput = document.getElementById('fecha_fin');

            function calcularFechaFin() {
                if (!tipoCultivoSelect || !fechaInicioInput || !fechaFinInput) return;

                // Se obtiene la opción seleccionada y su atributo `data-tiempo_estimado`.
                const tipoSeleccionado = tipoCultivoSelect.options[tipoCultivoSelect.selectedIndex];
                if (!tipoSeleccionado || !tipoSeleccionado.value) { return; }

                const tiempoEstimadoDias = parseInt(tipoSeleccionado.getAttribute('data-tiempo_estimado'), 10);
                const fechaInicioValor = fechaInicioInput.value;

                // Si hay una fecha de inicio y un tiempo estimado válido, se calcula la fecha de fin.
                if (fechaInicioValor && !isNaN(tiempoEstimadoDias) && tiempoEstimadoDias > 0) {
                    try {
                        const fechaInicioDate = new Date(fechaInicioValor + 'T00:00:00'); // Añadir T00:00:00 para evitar problemas de zona horaria
                        if (isNaN(fechaInicioDate.getTime())) { return; }
                        
                        // Se suman los días estimados a la fecha de inicio.
                        const fechaFinDate = new Date(fechaInicioDate);
                        fechaFinDate.setDate(fechaInicioDate.getDate() + tiempoEstimadoDias);

                        // Se formatea la fecha calculada al formato YYYY-MM-DD.
                        const anio = fechaFinDate.getFullYear();
                        const mes = String(fechaFinDate.getMonth() + 1).padStart(2, '0');
                        const dia = String(fechaFinDate.getDate()).padStart(2, '0');
                        
                        // Se asigna el valor al campo de fecha de fin.
                        fechaFinInput.value = `${anio}-${mes}-${dia}`;
                    } catch (error) {
                        console.error("Error al calcular la fecha de fin:", error);
                    }
                }
            }
            // Se añaden los "escuchadores de eventos" para que la función se ejecute cuando el usuario cambie los valores.
            if(tipoCultivoSelect) tipoCultivoSelect.addEventListener('change', calcularFechaFin);
            if(fechaInicioInput) {
                 fechaInicioInput.addEventListener('change', calcularFechaFin);
                 fechaInicioInput.addEventListener('input', calcularFechaFin); // Para mayor reactividad.
                 // Se ejecuta la función al cargar la página por si hay valores pre-cargados (ej. en caso de error).
                 if (tipoCultivoSelect.value && fechaInicioInput.value) {
                    calcularFechaFin();
                 }
            }
        });
    </script>
</body>
</html>