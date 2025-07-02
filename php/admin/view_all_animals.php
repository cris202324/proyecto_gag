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

require_once '../conexion.php'; 

$todos_animales = [];
$tipos_de_animales_distintos = [];
$mensaje_error = '';
$mensaje_exito = '';
$total_animales = 0;
$animales_por_pagina = 7; 
$pagina_actual = 1;
$total_paginas = 1;

if (isset($_SESSION['mensaje_accion_animal'])) {
    $mensaje_exito = $_SESSION['mensaje_accion_animal'];
    unset($_SESSION['mensaje_accion_animal']);
}
if (isset($_SESSION['error_accion_animal'])) {
    $mensaje_error = $_SESSION['error_accion_animal'];
    unset($_SESSION['error_accion_animal']);
}

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // LÓGICA DE PAGINACIÓN
        $sql_count = "SELECT COUNT(*) FROM animales";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute();
        $total_animales = (int)$stmt_count->fetchColumn();
        
        $total_paginas = ceil($total_animales / $animales_por_pagina);
        $total_paginas = $total_paginas < 1 ? 1 : $total_paginas;

        if (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) {
            $pagina_actual = (int)$_GET['pagina'];
            if ($pagina_actual < 1) $pagina_actual = 1;
            elseif ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;
        }
        $offset_actual = ($pagina_actual - 1) * $animales_por_pagina;

        // CONSULTA PRINCIPAL PARA OBTENER ANIMALES
        $sql = "SELECT 
                    a.id_animal, a.nombre_animal, a.tipo_animal, a.raza,
                    a.fecha_nacimiento, a.sexo, a.identificador_unico, a.cantidad,
                    DATE_FORMAT(a.fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_f,
                    u.nombre AS nombre_usuario, u.id_usuario AS id_usuario_animal
                FROM animales a
                JOIN usuarios u ON a.id_usuario = u.id_usuario
                ORDER BY a.fecha_registro DESC
                LIMIT :limit OFFSET :offset_val";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int) $animales_por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset_val', (int) $offset_actual, PDO::PARAM_INT);
        $stmt->execute();
        $todos_animales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // OBTENER TIPOS DE ANIMALES DISTINTOS PARA EL FILTRO DE REPORTE
        $sql_tipos = "SELECT DISTINCT tipo_animal FROM animales ORDER BY tipo_animal ASC";
        $stmt_tipos = $pdo->query($sql_tipos);
        $tipos_de_animales_distintos = $stmt_tipos->fetchAll(PDO::FETCH_COLUMN);

    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los animales: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Todos los Animales - Admin GAG</title>
    <style>
        /* --- ESTILOS UNIFICADOS DEL PANEL DE ADMIN --- */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f8f1; font-size: 16px; color: #333; }
        
        /* ESTILO CORRECTO DEL HEADER */
        .header { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 10px 20px; 
            background-color: #e0e0e0; /* FONDO GRIS CLARO */
            border-bottom: 2px solid #ccc; /* BORDE INFERIOR GRIS MÁS OSCURO */
            position: relative; 
        }
        
        .logo img { height: 70px; }
        
        .menu { display: flex; align-items: center; }
        
        /* ESTILO CORRECTO DE LOS BOTONES DEL MENÚ */
        .menu a { 
            margin: 0 5px; 
            text-decoration: none; 
            color: black; 
            padding: 8px 12px; 
            border: 1px solid #ccc; /* BORDE GRIS */
            border-radius: 5px; 
            transition: background-color 0.3s, color 0.3s; 
            white-space: nowrap; 
            font-size: 0.9em; 
        }
        
        .menu a.active, .menu a:hover { 
            background-color: #88c057; 
            color: white !important; 
            border-color: #70a845; 
        }
        
        .menu a.exit { 
            background-color: #ff4d4d; 
            color: white !important; 
            border: 1px solid #cc0000; 
        }
        .menu a.exit:hover { background-color: #cc0000; }
        
        .menu-toggle { display: none; background: none; border: none; font-size: 1.8rem; color: #333; cursor: pointer; padding: 5px; }
        
        .page-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h2.page-title { margin: 0; flex-grow: 1; color: #4caf50; font-size: 1.8em; }
        
        .report-actions-container { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .report-actions-container select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-size: 0.9em; }
        .btn-reporte { padding: 9px 15px; color: white !important; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 0.9em; transition: background-color 0.3s; border: none; cursor: pointer; }
        .btn-reporte.general { background-color: #17a2b8; }
        .btn-reporte.general:hover { background-color: #138496; }
        .btn-reporte.filtrado { background-color: #28a745; }
        .btn-reporte.filtrado:hover { background-color: #218838; }

        .tabla-container-responsive { display: block; width: 100%; overflow-x: auto; }
        .tabla-datos { width: 100%; min-width: 900px; border-collapse: collapse; margin-bottom: 20px; background-color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.1); border-radius: 8px; overflow: hidden; }
        .tabla-datos th, .tabla-datos td { border-bottom: 1px solid #ddd; padding: 10px; text-align: left; font-size: .85em; word-break: break-word; }
        .tabla-datos th { background-color: #f2f2f2; color: #333; font-weight: 700; }
        .tabla-datos tr:last-child td { border-bottom: none; }
        .tabla-datos tr:hover { background-color: #f1f1f1; }
        .tabla-datos .acciones a { display: inline-block; padding: 5px 10px; margin-right: 5px; margin-bottom: 5px; font-size: 0.8em; text-decoration: none; color: white; border-radius: 4px; border: none; cursor: pointer; }
        .tabla-datos .acciones .btn-editar { background-color: #3498db; }
        .tabla-datos .acciones .btn-borrar { background-color: #e74c3c; }
        .paginacion, .no-datos, .error-message, .success-message { /* Estilos genéricos para estos elementos */ }

        @media (max-width: 991.98px) { 
            .header { padding: 10px 15px; }
            .menu-toggle { display: block; } 
            .menu { display: none; flex-direction: column; align-items: stretch; position: absolute; top: 100%; left: 0; width: 100%; background-color: #f8f9fa; padding: 10px 0; box-shadow: 0 4px 8px rgba(0,0,0,.1); z-index: 1000; border-top: 1px solid #ddd; } 
            .menu.active { display: flex; } 
            .menu a { margin: 5px 10px; padding: 12px 15px; text-align: left; } 
        }
        @media (max-width: 768px) {
            .logo img { height: 60px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .report-actions-container { width: 100%; }
        }
        @media (max-width: 480px) {
            .logo img { height: 50px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="admin_dashboard.php">Inicio Admin</a> 
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="admin_manage_trat_pred.php">Tratamientos Pred.</a>
            <a href="view_all_animals.php" class="active">Ver Animales</a> 
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <div class="page-header">
            <h2 class="page-title">Todos los Animales Registrados</h2>
            <?php if (!empty($todos_animales)): ?>
            <div class="report-actions-container">
                <a href="admin_generar_reporte_animales_excel.php" class="btn-reporte general" target="_blank">Reporte General</a>
                <form action="admin_generar_reporte_animales_excel.php" method="GET" target="_blank" style="display:inline-flex; gap:10px; align-items:center;">
                    <select name="tipo_animal" required>
                        <option value="">-- Filtrar por tipo --</option>
                        <?php foreach ($tipos_de_animales_distintos as $tipo): ?>
                            <option value="<?php echo htmlspecialchars($tipo); ?>"><?php echo htmlspecialchars($tipo); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-reporte filtrado">Reporte por Tipo</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($mensaje_error)): ?> <!-- ... (tu código de mensajes) ... --> <?php endif; ?>
        <?php if (!empty($mensaje_exito)): ?> <!-- ... (tu código de mensajes) ... --> <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($todos_animales)): ?>
            <div class="no-datos"><p>No hay animales registrados en el sistema.</p></div>
        <?php elseif (!empty($todos_animales)): ?>
            <div class="tabla-container-responsive">
                <table class="tabla-datos">
                    <thead>
                        <tr>
                            <th>ID</th><th>Nombre</th><th>Tipo</th><th>Cantidad</th><th>Raza</th><th>Usuario</th>
                            <th>F. Nac.</th><th>Sexo</th><th>ID Único</th><th>F. Reg.</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todos_animales as $animal): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($animal['id_animal']); ?></td>
                                <td><?php echo htmlspecialchars($animal['nombre_animal'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($animal['tipo_animal']); ?></td>
                                <td><?php echo htmlspecialchars($animal['cantidad'] ?: 1); ?></td>
                                <td><?php echo htmlspecialchars($animal['raza'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($animal['nombre_usuario']); ?> <br><small>(<?php echo htmlspecialchars($animal['id_usuario_animal']); ?>)</small></td>
                                <td><?php echo $animal['fecha_nacimiento'] ? htmlspecialchars(date("d/m/Y", strtotime($animal['fecha_nacimiento']))) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($animal['sexo']); ?></td>
                                <td><?php echo htmlspecialchars($animal['identificador_unico'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($animal['fecha_registro_f']); ?></td>
                                <td class="acciones">
                                    <a href="admin_edit_animal.php?id_animal=<?php echo $animal['id_animal']; ?>" class="btn-editar">Editar</a>
                                    <a href="admin_delete_animal.php?id_animal=<?php echo $animal['id_animal']; ?>" class="btn-borrar" 
                                       onclick="return confirm('¿Estás seguro de que deseas eliminar este animal? Esta acción también eliminará los registros de alimentación y medicamentos asociados.');">
                                       Borrar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- ... (Tu código de paginación) ... -->
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    const isExpanded = mainMenu.classList.contains('active');
                    menuToggleBtn.setAttribute('aria-expanded', isExpanded);
                });
            }
        });
    </script>
</body>
</html>