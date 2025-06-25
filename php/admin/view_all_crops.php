<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    // Asumiendo que este archivo está en una carpeta como 'admin/' o 'php/'
    // y login.html está en 'proyecto_gag/pages/auth/login.html'
    // La ruta a login.html debe ser correcta desde la ubicación de este script
    header("Location: ../../pages/auth/login.html"); // Ajusta esta ruta si es necesario
    exit();
}

// Asumiendo que conexion.php está en el mismo directorio que este script
// o un nivel arriba si este script está en una subcarpeta (ej. 'admin/')
require_once '../conexion.php'; // $pdo

$todos_cultivos = [];
$mensaje_error = '';
$total_cultivos = 0;
$cultivos_por_pagina = 7; // Puedes ajustar este valor
$pagina_actual = 1;
$total_paginas = 1;

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // --- LÓGICA DE PAGINACIÓN ---
        // Contar total de cultivos
        $sql_count = "SELECT COUNT(*) FROM cultivos";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute();
        $total_cultivos = (int)$stmt_count->fetchColumn();
        
        $total_paginas = ceil($total_cultivos / $cultivos_por_pagina);
        $total_paginas = $total_paginas < 1 ? 1 : $total_paginas; // Asegurar al menos 1 página

        if (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) {
            $pagina_actual = (int)$_GET['pagina'];
            if ($pagina_actual < 1) {
                $pagina_actual = 1;
            } elseif ($pagina_actual > $total_paginas) {
                $pagina_actual = $total_paginas;
            }
        }
        $offset_actual = ($pagina_actual - 1) * $cultivos_por_pagina;

        // --- CONSULTA PRINCIPAL PARA OBTENER CULTIVOS (CON PAGINACIÓN) ---
        $sql = "SELECT
                    c.id_cultivo,
                    c.fecha_inicio,
                    c.fecha_fin AS fecha_fin_registrada,
                    c.area_hectarea,
                    tc.nombre_cultivo,
                    m.nombre AS nombre_municipio,
                    u.nombre AS nombre_usuario,
                    u.id_usuario AS id_usuario_cultivo,
                    ecd.nombre_estado AS estado_actual_cultivo
                FROM cultivos c
                JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                JOIN municipio m ON c.id_municipio = m.id_municipio
                JOIN usuarios u ON c.id_usuario = u.id_usuario
                LEFT JOIN estado_cultivo_definiciones ecd ON c.id_estado_cultivo = ecd.id_estado_cultivo
                ORDER BY c.fecha_inicio DESC
                LIMIT :limit OFFSET :offset_val";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int) $cultivos_por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset_val', (int) $offset_actual, PDO::PARAM_INT);
        
        $stmt->execute();
        $todos_cultivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $mensaje_error = "Error al obtener los cultivos: " . $e->getMessage();
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
    <title>Ver Todos los Cultivos - Admin GAG</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            font-size: 16px;
            color: #333;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            background-color: #e0e0e0;
            border-bottom: 2px solid #ccc;
            position: relative;
        }
        .logo img {
            height: 70px;
            transition: height 0.3s ease;
        }
        .menu {
            display: flex;
            align-items: center;
        }
        .menu a {
            margin: 0 5px;
            text-decoration: none;
            color: black;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            transition: background-color 0.3s, color 0.3s, padding 0.3s ease;
            white-space: nowrap;
            font-size: 0.9em;
        }
        .menu a.active,
        .menu a:hover {
            background-color: #88c057;
            color: white !important;
            border-color: #70a845;
        }
        .menu a.exit {
            background-color: #ff4d4d;
            color: white !important;
            border: 1px solid #cc0000;
        }
        .menu a.exit:hover {
            background-color: #cc0000;
            color: white !important;
        }
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.8rem;
            color: #333;
            cursor: pointer;
            padding: 5px;
        }
        
        .page-container {
            max-width: 1100px; /* Ajustado para tablas más anchas */
            margin: 20px auto;
            padding: 20px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap; 
            gap: 10px; 
        }
        .page-header h2.page-title {
            color: #4caf50;
            font-size: 1.8em; 
            margin: 0;
            flex-grow: 1;
        }
        .report-buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: nowrap;
        }
        .btn-reporte {
            padding: 10px 15px;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 0.85em;
            transition: background-color 0.3s;
            white-space: nowrap;
            border: none;
            cursor: pointer;
            text-align: center; /* Para asegurar centrado del texto */
        }
        .btn-reporte.general { background-color: #17a2b8; }
        .btn-reporte.general:hover { background-color: #138496; }
        .btn-reporte.mes-actual { background-color: #28a745; }
        .btn-reporte.mes-actual:hover { background-color: #218838; }
        
        .tabla-cultivos { 
            width:100%;
            border-collapse:collapse;
            margin-bottom:20px;
            background-color:#fff;
            box-shadow:0 2px 8px rgba(0,0,0,.1);
            border-radius:8px;
            overflow:hidden; /* Para que el border-radius afecte a la tabla */
        }
        .tabla-cultivos th, 
        .tabla-cultivos td { 
            border-bottom:1px solid #ddd;
            padding:10px 12px; /* Reducido un poco para más columnas */
            text-align:left;
            font-size:.85em; /* Reducido para más columnas */
        }
        .tabla-cultivos th { 
            background-color:#f2f2f2;
            color:#333;
            font-weight:700;
            border-top:1px solid #ddd; /* Para el primer encabezado */
        }
        .tabla-cultivos tr:last-child td { 
            border-bottom:none; 
        }
        .tabla-cultivos tr:nth-child(even) { 
            background-color:#f9f9f9; 
        }
        .tabla-cultivos tr:hover { 
            background-color:#f1f1f1; 
        }

        .paginacion {
            text-align:center;
            margin-top:30px;
            padding-bottom:20px;
        }
        .paginacion a,
        .paginacion span {
            display:inline-block;
            padding:8px 14px;
            margin:0 4px;
            border:1px solid #ccc;
            border-radius:4px;
            text-decoration:none;
            color:#4caf50;
            font-size:.9em;
        }
        .paginacion a:hover {
            background-color:#e8f5e9;
            border-color:#a5d6a7;
        }
        .paginacion span.actual {
            background-color:#4caf50;
            color:#fff;
            border-color:#43a047;
            font-weight:700;
        }
        .paginacion span.disabled {
            color:#aaa;
            border-color:#ddd;
            cursor:default;
        }
        
        .no-datos { 
            text-align:center;
            padding:30px;
            font-size:1.2em;
            color:#777;
        }
        .error-message { 
            color:#d8000c;
            text-align:left;
            padding:15px;
            background-color:#ffdddd;
            border:1px solid #ffcccc;
            border-radius:5px;
            margin-bottom:20px;
            /* white-space:pre-wrap;
            font-family:monospace; */ /* Comentado para que use la fuente del body */
            font-size: 0.9em;
        }

        @media (max-width:991.98px){
            .menu-toggle{display:block}
            .menu{
                display:none;flex-direction:column;align-items:stretch;position:absolute;
                top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;
                box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc
            }
            .menu.active{display:flex}
            .menu a{
                margin:0;padding:15px 20px;width:100%;text-align:left;
                border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333
            }
            .menu a:last-child{border-bottom:none}
            .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}
            .menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}
            .page-container > .page-header > h2.page-title{font-size:1.6em}
        }
        @media (max-width:768px){
            .logo img{height:60px}
            .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .report-buttons-container { width: 100%; justify-content: flex-start; flex-wrap: wrap;}
            .btn-reporte { flex-basis: calc(50% - 5px); /* Dos botones por fila aprox */ text-align: center; } 
            
            /* Para que la tabla sea scrollable horizontalmente en móviles */
            .tabla-container-responsive { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .tabla-cultivos{display:inline-block; min-width: 100%;} /* Evita que se encoja la tabla */
            .tabla-cultivos th,.tabla-cultivos td{font-size:.8em;padding:8px 10px;}
        }
        @media (max-width:480px){
            .logo img{height:50px}
            .menu-toggle{font-size:1.6rem}
            .page-container > .page-header > h2.page-title{font-size:1.4em}
            .btn-reporte { flex-basis: 100%; margin-bottom: 5px; } /* Un botón por fila */
            .btn-reporte:last-child { margin-bottom: 0; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <!-- Ajusta la ruta a tu logo si es necesario -->
            <img src="../../img/logo.png" alt="Logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
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

    <div class="page-container">
        <div class="page-header">
            <h2 class="page-title">Todos los Cultivos Registrados</h2>
            <?php if (!empty($todos_cultivos) && empty($mensaje_error)): ?>
                <div class="report-buttons-container">
                    <a href="admin_generar_reporte_cultivos_excel.php?rango_reporte=general" class="btn-reporte general" target="_blank">Reporte General</a>
                    <a href="admin_generar_reporte_cultivos_excel.php?rango_reporte=mes_actual" class="btn-reporte mes-actual" target="_blank">Reporte Mes Actual</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <?php if (empty($mensaje_error) && empty($todos_cultivos)): ?>
            <div class="no-datos">
                <p>No hay cultivos registrados en el sistema para mostrar.</p>
            </div>
        <?php elseif (!empty($todos_cultivos)): ?>
            <div class="tabla-container-responsive"> <!-- Contenedor para scroll horizontal en móvil -->
                <table class="tabla-cultivos">
                    <thead>
                        <tr>
                            <th>ID Cultivo</th>
                            <th>Nombre Cultivo</th>
                            <th>Usuario (ID)</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin Estimada</th>
                            <th>Área (ha)</th>
                            <th>Municipio</th>
                            <th>Estado</th>
                            <!-- <th>Acciones</th> (Opcional) -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todos_cultivos as $cultivo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cultivo['id_cultivo']); ?></td>
                                <td><?php echo htmlspecialchars($cultivo['nombre_cultivo']); ?></td>
                                <td><?php echo htmlspecialchars($cultivo['nombre_usuario']); ?> (<?php echo htmlspecialchars($cultivo['id_usuario_cultivo']); ?>)</td>
                                <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_inicio']))); ?></td>
                                <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_fin_registrada']))); ?></td>
                                <td><?php echo htmlspecialchars($cultivo['area_hectarea']); ?></td>
                                <td><?php echo htmlspecialchars($cultivo['nombre_municipio']); ?></td>
                                <td><?php echo htmlspecialchars($cultivo['estado_actual_cultivo'] ?: 'No definido'); ?></td>
                                <!-- <td><a href="admin_edit_cultivo.php?id=<?php echo $cultivo['id_cultivo']; ?>">Editar</a></td> -->
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
                <div class="paginacion">
                    <?php if ($pagina_actual > 1): ?>
                        <a href="?pagina=<?php echo $pagina_actual - 1; ?>">Anterior</a>
                    <?php else: ?>
                        <span class="disabled">Anterior</span>
                    <?php endif; ?>

                    <?php 
                    $rango_paginas = 2; 
                    $inicio_rango = max(1, $pagina_actual - $rango_paginas);
                    $fin_rango = min($total_paginas, $pagina_actual + $rango_paginas);

                    if ($inicio_rango > 1) {
                        echo '<a href="?pagina=1">1</a>';
                        if ($inicio_rango > 2) { echo '<span>...</span>'; }
                    }
                    for ($i = $inicio_rango; $i <= $fin_rango; $i++):
                        if ($i == $pagina_actual): echo '<span class="actual">' . $i . '</span>';
                        else: echo '<a href="?pagina=' . $i . '">' . $i . '</a>'; endif;
                    endfor;
                    if ($fin_rango < $total_paginas) {
                        if ($fin_rango < $total_paginas - 1) { echo '<span>...</span>'; }
                        echo '<a href="?pagina=' . $total_paginas . '">' . $total_paginas . '</a>';
                    }
                    ?>

                    <?php if ($pagina_actual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_actual + 1; ?>">Siguiente</a>
                    <?php else: ?>
                        <span class="disabled">Siguiente</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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