<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.html");
    exit();
}

include '../conexion.php'; // Asegúrate que $pdo esté definido aquí
$id_usuario_actual = $_SESSION['id_usuario'];
$animales_usuario = [];
$mensaje_error = '';

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        $sql = "SELECT 
                    id_animal, 
                    nombre_animal, 
                    tipo_animal, 
                    raza, 
                    fecha_nacimiento, 
                    sexo, 
                    identificador_unico,
                    DATE_FORMAT(fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_formateada
                FROM animales
                WHERE id_usuario = :id_usuario
                ORDER BY fecha_registro DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_usuario', $id_usuario_actual);
        $stmt->execute();
        $animales_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los animales: " . $e->getMessage();
        // error_log("Error en mis_animales.php: " . $e->getMessage());
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
    <title>Mis Animales</title>
    <link rel="stylesheet" href="../../css/estilos.css">
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="logo" />
        </div>
        <div class="menu">
            <a href="../index.php">Inicio</a>
            <a href="../miscultivos.php">Mis Cultivos</a>
            <a href="mis_animales.php" class="active">Mis Animales</a>
            <a href="../calendario.php">Calendario y Horarios</a>
            <a href="../configuracion.php">Configuración</a>
            <a href="../ayuda.php">Ayuda</a>
            <a href="../cerrar_sesion.php" class="exit">Cerrar sesión</a>
        </div>
    </div>

    <div class="content-area">
        <h2>Mis Animales Registrados</h2>
        <div style="text-align: center; margin-bottom: 20px;">
            <a href="crear_animales.php" class="btn-add">Registrar Nuevo Animal</a>
        </div>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <div class="animal-list-container">
            <?php if (empty($mensaje_error) && empty($animales_usuario)): ?>
                <div class="no-animales">
                    <p>Aún no has registrado ningún animal.</p>
                </div>
            <?php elseif (!empty($animales_usuario)): ?>
                <?php foreach ($animales_usuario as $animal): ?>
                    <div class="animal-card">
                        <h3>
                            <?php echo htmlspecialchars($animal['tipo_animal']); ?>
                            <?php if (!empty($animal['nombre_animal'])): ?>
                                - <?php echo htmlspecialchars($animal['nombre_animal']); ?>
                            <?php endif; ?>
                        </h3>
                        <p><strong>Raza:</strong> <?php echo htmlspecialchars($animal['raza'] ?: 'No especificada'); ?></p>
                        <p><strong>Sexo:</strong> <?php echo htmlspecialchars($animal['sexo']); ?></p>
                        <?php if (!empty($animal['fecha_nacimiento'])): ?>
                            <p><strong>Fecha de Nacimiento:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($animal['fecha_nacimiento']))); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($animal['identificador_unico'])): ?>
                            <p><strong>ID Adicional:</strong> <?php echo htmlspecialchars($animal['identificador_unico']); ?></p>
                        <?php endif; ?>
                        <p><strong>Registrado el:</strong> <?php echo htmlspecialchars($animal['fecha_registro_formateada']); ?></p>
                        <div class="action-links">
                            <!-- <a href="editar_animal.php?id=<?php echo $animal['id_animal']; ?>">Editar</a> -->
                            <!-- <a href="alimentacion_animal.php?id_animal=<?php echo $animal['id_animal']; ?>">Alimentación</a> -->
                            <!-- <a href="medicamentos_animal.php?id_animal=<?php echo $animal['id_animal']; ?>">Medicamentos</a> -->
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>