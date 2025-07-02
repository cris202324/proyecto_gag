<?php
session_start();
// (Cabeceras de no-cache)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");


if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    header("Location: ../pages/auth/login.html"); // Ajusta esta ruta si es necesario
    exit();
}
include '../conexion.php'; 
$tratamientos_pred = [];
$mensaje_error = '';
$mensaje_exito = '';

if(isset($_SESSION['mensaje_exito_trat_pred'])){
    $mensaje_exito = $_SESSION['mensaje_exito_trat_pred'];
    unset($_SESSION['mensaje_exito_trat_pred']);
}
if(isset($_SESSION['mensaje_error_trat_pred'])){
    $mensaje_error = $_SESSION['mensaje_error_trat_pred'];
    unset($_SESSION['mensaje_error_trat_pred']);
}


if (!isset($pdo)) {
    $mensaje_error = "Error: Conexión a la base de datos no disponible.";
} else {
    try {
        $sql = "SELECT tp.*, tc.nombre_cultivo 
                FROM tratamientos_predeterminados tp
                JOIN tipos_cultivo tc ON tp.id_tipo_cultivo = tc.id_tipo_cultivo
                ORDER BY tc.nombre_cultivo, tp.dias_despues_inicio_aplicacion, tp.tipo_tratamiento";
        $stmt = $pdo->query($sql);
        $tratamientos_pred = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener tratamientos predeterminados: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Tratamientos Predeterminados - Admin</title>
    <style>
        /* --- INICIO: ESTILOS COPIADOS DEL DASHBOARD PARA CONSISTENCIA --- */
        /* Estilos generales */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f8f1; font-size: 16px; color: #333; }
        .header { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background-color: #e0e0e0; border-bottom: 2px solid #ccc; position: relative; }
        .logo img { height: 70px; }
        .menu { display: flex; align-items: center; }
        .menu a { margin: 0 5px; text-decoration: none; color: black; padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; transition: background-color 0.3s, color 0.3s; white-space: nowrap; font-size: 0.9em; }
        .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: #70a845; }
        .menu a.exit { background-color: #ff4d4d; color: white !important; border: 1px solid #cc0000; }
        .menu a.exit:hover { background-color: #cc0000; }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.8rem; color: #333; cursor: pointer; padding: 5px; }
        
        .page-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .page-title { text-align: center; color: #4caf50; margin-bottom: 20px; font-size: 1.8em; }
        
        /* Estilos específicos para esta página */
        .gestion-container { background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); padding: 25px; }
        .btn-add-new { display: inline-block; margin-bottom: 20px; padding: 10px 15px; background-color: #28a745; color: white !important; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 0.9em; }
        .btn-add-new:hover { background-color: #218838; }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        td.actions a { margin-right: 8px; color: #007bff; text-decoration: none; }
        td.actions a:hover { text-decoration: underline; }
        td.actions a.delete { color: #dc3545; }
        
        .mensaje { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; }
        .mensaje.exito { background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .mensaje.error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        .no-records { text-align: center; padding: 20px; font-style: italic; color: #777; }
        
        /* Media Queries para responsividad del menú */
        @media (max-width: 991.98px) { 
            .menu-toggle { display: block; } 
            .menu { display: none; flex-direction: column; align-items: stretch; position: absolute; top: 100%; left: 0; width: 100%; background-color: #e9e9e9; padding: 0; box-shadow: 0 4px 8px rgba(0,0,0,.1); z-index: 1000; border-top: 1px solid #ccc; } 
            .menu.active { display: flex; } 
            .menu a { margin:0; padding:15px 20px; width:100%; text-align:left; border:none; border-bottom:1px solid #d0d0d0; border-radius:0; color:#333; } 
            .menu a:last-child { border-bottom: none; } 
            .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; } 
            .menu a.exit, .menu a.exit:hover { background-color: #ff4d4d; color: white !important; } 
        }
        @media (max-width: 767px) { 
            .logo img { height: 60px; } 
            .page-title { font-size: 1.5em; } 
        }
        @media (max-width: 480px) { 
            .logo img { height: 50px; } 
            .menu-toggle { font-size: 1.6rem; } 
            .page-title { font-size: 1.4em; }
        }
        /* --- FIN: ESTILOS COPIADOS DEL DASHBOARD --- */
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="Logo GAG"> <!-- Ajusta la ruta a tu logo -->
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <!-- Menú unificado, ajustado para esta página -->
            <a href="admin_dashboard.php">Inicio Admin</a> 
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="admin_manage_trat_pred.php" class="active">Tratamientos Pred.</a> <!-- CLASE ACTIVE AQUÍ -->
            <a href="view_all_animals.php">Ver Animales</a> 
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <div class="gestion-container">
            <h1 class="page-title">Gestionar Tratamientos Predeterminados</h1>

            <?php if ($mensaje_exito): ?>
                <p class="mensaje exito"><?php echo htmlspecialchars($mensaje_exito); ?></p>
            <?php endif; ?>
            <?php if ($mensaje_error): ?>
                <p class="mensaje error"><?php echo htmlspecialchars($mensaje_error); ?></p>
            <?php endif; ?>

            <a href="admin_edit_trat_pred.php" class="btn-add-new">Crear Nuevo Tratamiento Pred.</a>

            <?php if (empty($tratamientos_pred) && empty($mensaje_error)): ?>
                <p class="no-records">No hay tratamientos predeterminados registrados.</p>
            <?php elseif (!empty($tratamientos_pred)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cultivo Asociado</th>
                                <th>Tipo Tratamiento</th>
                                <th>Producto</th>
                                <th>Etapas</th>
                                <th>Dosis</th>
                                <th>Unidad</th>
                                <th>Días Aplicación</th>
                                <th>Observaciones</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tratamientos_pred as $trat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($trat['id_trat_pred']); ?></td>
                                    <td><?php echo htmlspecialchars($trat['nombre_cultivo']); ?></td>
                                    <td><?php echo htmlspecialchars($trat['tipo_tratamiento']); ?></td>
                                    <td><?php echo htmlspecialchars($trat['producto_usado']); ?></td>
                                    <td><?php echo htmlspecialchars($trat['etapas']); ?></td>
                                    <td><?php echo htmlspecialchars(rtrim(rtrim(number_format($trat['dosis'], 2, '.', ''), '0'), '.')); ?></td>
                                    <td><?php echo htmlspecialchars($trat['unidad_dosis']); ?></td>
                                    <td><?php echo htmlspecialchars($trat['dias_despues_inicio_aplicacion']); ?></td>
                                    <td><?php echo htmlspecialchars($trat['observaciones'] ?: '-'); ?></td>
                                    <td class="actions">
                                        <a href="admin_edit_trat_pred.php?id=<?php echo $trat['id_trat_pred']; ?>">Editar</a>
                                        <a href="admin_delete_trat_pred.php?id=<?php echo $trat['id_trat_pred']; ?>" class="delete" onclick="return confirm('¿Estás seguro de eliminar este tratamiento predeterminado? Esta acción no se puede deshacer.');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Lógica del Menú Hamburguesa
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