<?php
session_start();
require_once 'conexion.php'; // $pdo

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    header("Location: ../login.html");
    exit();
}

$mensaje_formulario = '';
$error_formulario = false;
$animal_a_editar = null;
$id_animal_get = null;

// Obtener ID del animal de GET o POST (si se reenvía el form con error)
if (isset($_GET['id_animal']) && is_numeric($_GET['id_animal'])) {
    $id_animal_get = (int)$_GET['id_animal'];
} elseif (isset($_POST['id_animal_hidden']) && is_numeric($_POST['id_animal_hidden'])) {
    $id_animal_get = (int)$_POST['id_animal_hidden'];
}

if ($id_animal_get === null) {
    $_SESSION['error_accion_animal'] = "No se especificó un ID de animal válido para editar.";
    header("Location: view_all_animals.php");
    exit();
}

// --- LÓGICA DE ACTUALIZACIÓN (CUANDO SE ENVÍA EL FORMULARIO) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_animal'])) {
    $id_animal_post = (int)$_POST['id_animal_hidden']; // Usar el ID oculto para la actualización
    $nombre_animal = trim($_POST['nombre_animal']);
    $tipo_animal = trim($_POST['tipo_animal']);
    $raza = !empty(trim($_POST['raza'])) ? trim($_POST['raza']) : null;
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $sexo = $_POST['sexo'];
    $identificador_unico = !empty(trim($_POST['identificador_unico'])) ? trim($_POST['identificador_unico']) : null;

    if (empty($tipo_animal)) {
        $mensaje_formulario = "El tipo de animal es obligatorio.";
        $error_formulario = true;
    } else {
        try {
            $sql_update = "UPDATE animales SET 
                                nombre_animal = :nombre_animal, 
                                tipo_animal = :tipo_animal, 
                                raza = :raza, 
                                fecha_nacimiento = :fecha_nacimiento, 
                                sexo = :sexo, 
                                identificador_unico = :identificador_unico
                           WHERE id_animal = :id_animal_post";
            
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindParam(':nombre_animal', $nombre_animal);
            $stmt_update->bindParam(':tipo_animal', $tipo_animal);
            $stmt_update->bindParam(':raza', $raza);
            $stmt_update->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $stmt_update->bindParam(':sexo', $sexo);
            $stmt_update->bindParam(':identificador_unico', $identificador_unico);
            $stmt_update->bindParam(':id_animal_post', $id_animal_post, PDO::PARAM_INT);

            if ($stmt_update->execute()) {
                $_SESSION['mensaje_accion_animal'] = "¡Animal (ID: {$id_animal_post}) actualizado correctamente!";
                header("Location: view_all_animals.php?pagina=" . (isset($_POST['pagina_retorno']) ? $_POST['pagina_retorno'] : 1) ); // Volver a la página de la lista
                exit();
            } else {
                $mensaje_formulario = "Error al actualizar el animal.";
                $error_formulario = true;
            }
        } catch (PDOException $e) {
            $mensaje_formulario = "Error de base de datos al actualizar: " . $e->getMessage();
            $error_formulario = true;
        }
    }
    // Si hay error en POST, necesitamos recargar los datos del animal para el formulario
    // $id_animal_get ya tiene el ID correcto
}


// --- CARGAR DATOS DEL ANIMAL PARA MOSTRAR EN EL FORMULARIO (SI NO HUBO ERROR POST O ES LA PRIMERA CARGA) ---
if (!$error_formulario || $_SERVER["REQUEST_METHOD"] != "POST") { // Solo cargar si no es un reenvío de form con error, o si es GET
    if (!isset($pdo)) {
        $mensaje_formulario = "Error crítico: La conexión a la base de datos no está disponible.";
        $error_formulario = true;
    } else {
        try {
            $stmt_animal = $pdo->prepare("SELECT * FROM animales WHERE id_animal = :id_animal");
            $stmt_animal->bindParam(':id_animal', $id_animal_get, PDO::PARAM_INT);
            $stmt_animal->execute();
            $animal_a_editar = $stmt_animal->fetch(PDO::FETCH_ASSOC);

            if (!$animal_a_editar) {
                $_SESSION['error_accion_animal'] = "No se encontró el animal con ID: " . htmlspecialchars($id_animal_get);
                header("Location: view_all_animals.php");
                exit();
            }
        } catch (PDOException $e) {
            $mensaje_formulario = "Error al cargar datos del animal: " . $e->getMessage();
            $error_formulario = true;
        }
    }
}
// Guardar la página actual para el retorno
$pagina_retorno = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Animal - Admin GAG</title>
    <style>
        /* Estilos generales y de header (copiar de view_all_animals.php o de tu CSS global) */
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;} .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;white-space:nowrap;font-size:.9em}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}

        .page-container{max-width:700px;margin:20px auto;padding:20px}
        .page-container > h2.page-title{text-align:center;color:#4caf50;margin-bottom:25px;font-size:1.8em}
        
        .form-container { padding:25px; background-color:#fff; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:600; color:#444; }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group select {
            width:100%; padding:12px; border:1px solid #ddd; border-radius:5px;
            box-sizing:border-box; font-size:1em; transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color:#4caf50; box-shadow:0 0 0 0.2rem rgba(76,175,80,0.20); outline:none;
        }
        .btn-submit { padding:12px 25px; background-color:#4CAF50; color:white; border:none; border-radius:5px; font-size:1em; font-weight:bold; cursor:pointer; transition:background-color 0.3s ease; }
        .btn-submit:hover { background-color:#45a049; }
        .btn-cancel { display:inline-block; margin-left:10px; padding:11px 20px; background-color:#777; color:white; text-decoration:none; border-radius:5px; font-size:1em; transition:background-color 0.3s ease; }
        .btn-cancel:hover { background-color:#666; }
        .mensaje { padding:12px; margin-bottom:20px; border-radius:5px; font-size:0.9em; text-align:center; }
        .mensaje.exito { background-color:#e8f5e9; color:#387002; border:1px solid #c8e6c9; }
        .mensaje.error { background-color:#ffebee; color:#c62828; border:1px solid #ffcdd2; }

        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}}
        @media (max-width:768px){.logo img{height:60px} .page-container > h2.page-title{font-size:1.6em} }
        @media (max-width:480px){.logo img{height:50px} .menu-toggle{font-size:1.6rem} .page-container > h2.page-title{font-size:1.4em} }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo"><img src="../img/logo.png" alt="Logo GAG" /></div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="admin_dashboard.php">Inicio</a> 
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="view_all_animals.php" class="active">Ver Animales</a>
            <a href="manage_users.php">Gestionar Roles</a> 
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h2 class="page-title">Editar Animal</h2>

        <?php if (!empty($mensaje_formulario)): ?>
            <p class="mensaje <?php echo $error_formulario ? 'error' : 'exito'; ?>"><?php echo htmlspecialchars($mensaje_formulario); ?></p>
        <?php endif; ?>

        <?php if ($animal_a_editar): ?>
            <div class="form-container">
                <form action="admin_edit_animal.php" method="POST">
                    <input type="hidden" name="id_animal_hidden" value="<?php echo htmlspecialchars($animal_a_editar['id_animal']); ?>">
                    <input type="hidden" name="pagina_retorno" value="<?php echo htmlspecialchars($pagina_retorno); ?>">

                    <div class="form-group">
                        <label for="nombre_animal">Nombre / Identificador del Animal (Opcional):</label>
                        <input type="text" id="nombre_animal" name="nombre_animal" value="<?php echo htmlspecialchars($animal_a_editar['nombre_animal'] ?? ''); ?>" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="tipo_animal">Tipo de Animal (Ej: Vaca, Pollo):</label>
                        <input type="text" id="tipo_animal" name="tipo_animal" value="<?php echo htmlspecialchars($animal_a_editar['tipo_animal'] ?? ''); ?>" maxlength="50" required>
                    </div>
                    <div class="form-group">
                        <label for="raza">Raza (Opcional):</label>
                        <input type="text" id="raza" name="raza" value="<?php echo htmlspecialchars($animal_a_editar['raza'] ?? ''); ?>" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="fecha_nacimiento">Fecha de Nacimiento (Opcional):</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo htmlspecialchars($animal_a_editar['fecha_nacimiento'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sexo">Sexo:</label>
                        <select id="sexo" name="sexo">
                            <option value="Desconocido" <?php echo (($animal_a_editar['sexo'] ?? '') == 'Desconocido') ? 'selected' : ''; ?>>Desconocido</option>
                            <option value="Macho" <?php echo (($animal_a_editar['sexo'] ?? '') == 'Macho') ? 'selected' : ''; ?>>Macho</option>
                            <option value="Hembra" <?php echo (($animal_a_editar['sexo'] ?? '') == 'Hembra') ? 'selected' : ''; ?>>Hembra</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="identificador_unico">ID Único Adicional (Ej: Arete) (Opcional):</label>
                        <input type="text" id="identificador_unico" name="identificador_unico" value="<?php echo htmlspecialchars($animal_a_editar['identificador_unico'] ?? ''); ?>" maxlength="50">
                    </div>
                    <button type="submit" name="actualizar_animal" class="btn-submit">Actualizar Animal</button>
                    <a href="view_all_animals.php?pagina=<?php echo htmlspecialchars($pagina_retorno); ?>" class="btn-cancel">Cancelar</a>
                </form>
            </div>
        <?php elseif(!$error_formulario): /* Si no se encontró el animal y no es por error de POST */?>
            <p class="mensaje error">No se pudo cargar el animal para editar o el ID no es válido.</p>
            <a href="view_all_animals.php" class="btn-cancel" style="display:block; text-align:center; width:150px; margin: 20px auto;">Volver a la lista</a>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
                });
            }
        });
    </script>
</body>
</html>