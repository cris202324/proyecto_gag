<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    header("Location: ../../pages/auth/login.html");
    exit();
}

include '../conexion.php';

$animales = [];
$mensaje_error = '';
$mensaje_success = '';

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        $sql = "SELECT 
                    a.id_animal,
                    a.nombre_animal,
                    a.tipo_animal,
                    a.raza,
                    a.fecha_nacimiento,
                    a.sexo,
                    a.identificador_unico,
                    a.fecha_registro,
                    u.nombre AS nombre_usuario,
                    u.id_usuario AS usuario_id
                FROM animales a
                JOIN usuarios u ON a.id_usuario = u.id_usuario
                ORDER BY a.fecha_registro DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $animales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener lista de usuarios para el selector
        $stmt_users = $pdo->prepare("SELECT id_usuario, nombre FROM usuarios WHERE id_rol != 1 ORDER BY nombre");
        $stmt_users->execute();
        $usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los animales: " . $e->getMessage();
    }

    // Procesar edición, eliminación, alimentación o medicamentos
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (isset($_POST['edit_id'])) {
                $id = $_POST['edit_id'];
                $nombre = $_POST['nombre_animal'];
                $tipo = $_POST['tipo_animal'];
                $raza = $_POST['raza'] ?: null;
                $fecha_nacimiento = $_POST['fecha_nacimiento'] ?: null;
                $sexo = $_POST['sexo'];
                $id_usuario = $_POST['id_usuario'];

                $update = $pdo->prepare("UPDATE animales SET nombre_animal = :nombre, tipo_animal = :tipo, raza = :raza, fecha_nacimiento = :fecha_nacimiento, sexo = :sexo, id_usuario = :id_usuario WHERE id_animal = :id");
                $update->bindParam(':id', $id, PDO::PARAM_INT);
                $update->bindParam(':nombre', $nombre, PDO::PARAM_STR);
                $update->bindParam(':tipo', $tipo, PDO::PARAM_STR);
                $update->bindParam(':raza', $raza, PDO::PARAM_STR);
                $update->bindParam(':fecha_nacimiento', $fecha_nacimiento, PDO::PARAM_STR);
                $update->bindParam(':sexo', $sexo, PDO::PARAM_STR);
                $update->bindParam(':id_usuario', $id_usuario, PDO::PARAM_STR);

                if ($update->execute()) {
                    $mensaje_success = "Animal actualizado con éxito.";
                } else {
                    $mensaje_error = "Error al actualizar el animal.";
                }
            } elseif (isset($_POST['delete_id'])) {
                $id = $_POST['delete_id'];
                $delete = $pdo->prepare("DELETE FROM animales WHERE id_animal = :id");
                $delete->bindParam(':id', $id, PDO::PARAM_INT);

                if ($delete->execute()) {
                    $mensaje_success = "Animal eliminado con éxito.";
                } else {
                    $mensaje_error = "Error al eliminar el animal.";
                }
            } elseif (isset($_POST['add_alimentacion'])) {
                $id_animal = $_POST['id_animal'];
                $tipo_alimento = $_POST['tipo_alimento'];
                $cantidad_diaria = $_POST['cantidad_diaria'];
                $frecuencia_alimentacion = $_POST['frecuencia_alimentacion'];

                $insert = $pdo->prepare("INSERT INTO alimentacion (id_animal, tipo_alimento, cantidad_diaria, frecuencia_alimentacion) VALUES (:id_animal, :tipo_alimento, :cantidad_diaria, :frecuencia_alimentacion)");
                $insert->bindParam(':id_animal', $id_animal, PDO::PARAM_INT);
                $insert->bindParam(':tipo_alimento', $tipo_alimento, PDO::PARAM_STR);
                $insert->bindParam(':cantidad_diaria', $cantidad_diaria, PDO::PARAM_STR);
                $insert->bindParam(':frecuencia_alimentacion', $frecuencia_alimentacion, PDO::PARAM_STR);

                if ($insert->execute()) {
                    $mensaje_success = "Alimentación registrada con éxito.";
                } else {
                    $mensaje_error = "Error al registrar la alimentación.";
                }
            } elseif (isset($_POST['add_medicamento'])) {
                $id_animal = $_POST['id_animal'];
                $tipo_medicamento = $_POST['tipo_medicamento'];
                $nombre = $_POST['nombre_medicamento'];
                $fecha_administracion = $_POST['fecha_administracion'];
                $dosis = $_POST['dosis'];

                $insert = $pdo->prepare("INSERT INTO medicamentos (id_animal, tipo_medicamento, nombre, fecha_de_administracion, dosis) VALUES (:id_animal, :tipo_medicamento, :nombre, :fecha_administracion, :dosis)");
                $insert->bindParam(':id_animal', $id_animal, PDO::PARAM_INT);
                $insert->bindParam(':tipo_medicamento', $tipo_medicamento, PDO::PARAM_STR);
                $insert->bindParam(':nombre', $nombre, PDO::PARAM_STR);
                $insert->bindParam(':fecha_administracion', $fecha_administracion, PDO::PARAM_STR);
                $insert->bindParam(':dosis', $dosis, PDO::PARAM_STR);

                if ($insert->execute()) {
                    $mensaje_success = "Medicamento registrado con éxito.";
                } else {
                    $mensaje_error = "Error al registrar el medicamento.";
                }
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error en la operación: " . $e->getMessage();
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
    <title>Gestionar Animales</title>
    <link rel="stylesheet" href="../../css/estilos.css">
        <style>
        .content {
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            min-height: 0; /* Permite que crezca según el contenido */
        }
        .form-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        .form-section h4 {
            margin-top: 0;
            color: #0056b3;
        }
        .no-animales {
            text-align: center;
            width: 100%;
            padding: 30px;
            font-size: 1.2em;
            color: #777;
        }
        .error-message {
            color: red;
            text-align: center;
            width: 100%;
            padding: 15px;
            background-color: #fdd;
            border: 1px solid #fbb;
        }
        .success-message {
            color: green;
            text-align: center;
            width: 100%;
            padding: 15px;
            background-color: #dff0d8;
            border: 1px solid #d6e9c6;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="logo" />
        </div>
        <nav class="menu" id="mainMenu">
            <!-- Ajusta las rutas del menú según la ubicación de este archivo -->
            <a href="admin_dashboard.php" class="active">Inicio Admin</a> 
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="admin_manage_trat_pred.php">Tratamientos Pred.</a> <!-- Enlace al nuevo gestor -->
            <a href="view_all_animals.php">Ver Animales</a> 
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a> <!-- Asume que cerrar_sesion está un nivel arriba -->
        </nav>
    </div>

    <div class="content">
        <h2>Gestionar Animales</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_success)): ?>
            <p class="success-message"><?php echo htmlspecialchars($mensaje_success); ?></p>
        <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($animales)): ?>
            <div class="no-animales">
                <p>No hay animales para gestionar.</p>
            </div>
        <?php elseif (!empty($animales)): ?>
            <?php foreach ($animales as $animal): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($animal['nombre_animal']); ?> (<?php echo htmlspecialchars($animal['nombre_usuario']); ?>)</h3>
                    <form method="POST">
                        <input type="hidden" name="edit_id" value="<?php echo $animal['id_animal']; ?>">
                        <label>Nombre:</label>
                        <input type="text" name="nombre_animal" value="<?php echo htmlspecialchars($animal['nombre_animal']); ?>" required>
                        <label>Tipo:</label>
                        <input type="text" name="tipo_animal" value="<?php echo htmlspecialchars($animal['tipo_animal']); ?>" required>
                        <label>Raza:</label>
                        <input type="text" name="raza" value="<?php echo htmlspecialchars($animal['raza'] ?? ''); ?>">
                        <label>Fecha de Nacimiento:</label>
                        <input type="date" name="fecha_nacimiento" value="<?php echo htmlspecialchars($animal['fecha_nacimiento'] ?? ''); ?>">
                        <label>Sexo:</label>
                        <select name="sexo">
                            <option value="Macho" <?php echo $animal['sexo'] == 'Macho' ? 'selected' : ''; ?>>Macho</option>
                            <option value="Hembra" <?php echo $animal['sexo'] == 'Hembra' ? 'selected' : ''; ?>>Hembra</option>
                            <option value="Desconocido" <?php echo $animal['sexo'] == 'Desconocido' ? 'selected' : ''; ?>>Desconocido</option>
                        </select>
                        <label>Usuario:</label>
                        <select name="id_usuario">
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo htmlspecialchars($usuario['id_usuario']); ?>" <?php echo $animal['usuario_id'] == $usuario['id_usuario'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" value="Guardar">
                    </form>
                    <form method="POST" style="display: inline; margin-top: 10px;">
                        <input type="hidden" name="delete_id" value="<?php echo $animal['id_animal']; ?>">
                        <button type="submit" onclick="return confirm('¿Seguro que quieres eliminar a <?php echo addslashes($animal['nombre_animal']); ?>?');">Eliminar</button>
                    </form>

                    <!-- Formulario para añadir alimentación -->
                    <div class="form-section">
                        <h4>Añadir Alimentación</h4>
                        <form method="POST">
                            <input type="hidden" name="id_animal" value="<?php echo $animal['id_animal']; ?>">
                            <label>Tipo de Alimento:</label>
                            <input type="text" name="tipo_alimento" required>
                            <label>Cantidad Diaria (kg):</label>
                            <input type="number" name="cantidad_diaria" step="0.01" required>
                            <label>Frecuencia:</label>
                            <input type="text" name="frecuencia_alimentacion" required>
                            <input type="submit" name="add_alimentacion" value="Registrar Alimentación">
                        </form>
                    </div>

                    <!-- Formulario para añadir medicamento -->
                    <div class="form-section">
                        <h4>Añadir Medicamento</h4>
                        <form method="POST">
                            <input type="hidden" name="id_animal" value="<?php echo $animal['id_animal']; ?>">
                            <label>Tipo de Medicamento:</label>
                            <input type="text" name="tipo_medicamento" required>
                            <label>Nombre:</label>
                            <input type="text" name="nombre_medicamento" required>
                            <label>Fecha de Administración:</label>
                            <input type="date" name="fecha_administracion" required>
                            <label>Dosis:</label>
                            <input type="text" name="dosis" required>
                            <input type="submit" name="add_medicamento" value="Registrar Medicamento">
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>