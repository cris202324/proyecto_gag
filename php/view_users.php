<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    header("Location: ../login.html"); 
    exit();
}

require_once 'conexion.php'; // $pdo

$usuarios = []; 
$mensaje_error = '';
$termino_busqueda = '';
$total_usuarios = 0;
$usuarios_por_pagina = 7;
$pagina_actual = 1;
$total_paginas = 1;

if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // --- LÓGICA DE BÚSQUEDA ---
        $param_busqueda_like = null; 
        if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {
            $termino_busqueda = trim($_GET['buscar']);
            $param_busqueda_like = "%" . $termino_busqueda . "%";
        }

        // --- LÓGICA DE PAGINACIÓN - CONTEO ---
        $sql_count = "SELECT COUNT(*) FROM usuarios WHERE id_rol != 1";
        $params_for_execute_count = []; // <--- INICIALIZAR AQUÍ

        if ($param_busqueda_like !== null) {
            $sql_count .= " AND (nombre LIKE ? OR email LIKE ?)";
            $params_for_execute_count[] = $param_busqueda_like;
            $params_for_execute_count[] = $param_busqueda_like;
        }
        // No es necesario un 'else' para $params_for_execute_count, ya está inicializado como vacío.
        
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute($params_for_execute_count);
        $total_usuarios = (int)$stmt_count->fetchColumn();
        
        $total_paginas = ceil($total_usuarios / $usuarios_por_pagina);
        $total_paginas = $total_paginas < 1 ? 1 : $total_paginas;

        if (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) {
            $pagina_actual = (int)$_GET['pagina'];
            if ($pagina_actual < 1) {
                $pagina_actual = 1;
            } elseif ($pagina_actual > $total_paginas) {
                $pagina_actual = $total_paginas;
            }
        }
        $offset_actual = ($pagina_actual - 1) * $usuarios_por_pagina;

        // --- CONSULTA PRINCIPAL PARA OBTENER USUARIOS (SOLUCIÓN FINAL) ---
        $sql_main = "SELECT id_usuario, nombre, email, id_rol, id_estado 
                     FROM usuarios 
                     WHERE id_rol != 1";
        
        $params_for_execute_main = []; // <--- INICIALIZAR AQUÍ

        if ($param_busqueda_like !== null) {
            $sql_main .= " AND (nombre LIKE ? OR email LIKE ?)";
            $params_for_execute_main[] = $param_busqueda_like; // Para el primer LIKE ?
            $params_for_execute_main[] = $param_busqueda_like; // Para el segundo LIKE ?
        }
        
        $sql_main .= " ORDER BY nombre ASC LIMIT ? OFFSET ?";
        $params_for_execute_main[] = (int) $usuarios_por_pagina; // Para LIMIT ?
        $params_for_execute_main[] = (int) $offset_actual;       // Para OFFSET ?
        
        $stmt_main = $pdo->prepare($sql_main);
        $stmt_main->execute($params_for_execute_main); 
        
        $usuarios = $stmt_main->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) { 
        $mensaje_error = "Error al obtener los usuarios: " . $e->getMessage() . " (Código: " . $e->getCode() . ")";
        // if (isset($sql_main)) { $mensaje_error .= "<br>SQL: " . htmlspecialchars($sql_main); }
        // if (!empty($params_for_execute_main)) { $mensaje_error .= "<br>Params: " . htmlspecialchars(print_r($params_for_execute_main, true)); }
    }
}

// ... EL RESTO DE TU HTML Y CSS SIGUE IGUAL ...
// Pega aquí el HTML completo desde <!DOCTYPE html> hasta </html>
// que te proporcioné en la respuesta donde todo estaba unido y el menú sándwich funcionaba.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Ver Usuarios - Admin GAG</title>
    <style>
        /* Estilos generales */
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;transition:height .3s ease}
        .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;transition:background-color .3s,color .3s,padding .3s ease;white-space:nowrap;font-size:.9em}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}
        .page-container{max-width:1000px;margin:20px auto;padding:20px}
        .page-container>h2.page-title{text-align:center;color:#4caf50;margin-bottom:25px;font-size:1.8em}
        .controles-tabla{margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
        .controles-tabla form{display:flex;gap:10px}
        .controles-tabla input[type=text]{padding:8px 10px;border:1px solid #ccc;border-radius:5px;font-size:.9em;min-width:200px;flex-grow:1}
        .controles-tabla button[type=submit]{padding:8px 15px;background-color:#5cb85c;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:.9em}
        .controles-tabla button[type=submit]:hover{background-color:#4cae4c}
        .tabla-usuarios{width:100%;border-collapse:collapse;margin-bottom:20px;background-color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.1);border-radius:8px;overflow:hidden}
        .tabla-usuarios td,.tabla-usuarios th{border-bottom:1px solid #ddd;padding:12px 15px;text-align:left;font-size:.9em}
        .tabla-usuarios th{background-color:#f2f2f2;color:#333;font-weight:700;border-top:1px solid #ddd}
        .tabla-usuarios tr:last-child td{border-bottom:none}
        .tabla-usuarios tr:nth-child(even){background-color:#f9f9f9}
        .tabla-usuarios tr:hover{background-color:#f1f1f1}
        .paginacion{text-align:center;margin-top:30px;padding-bottom:20px}
        .paginacion a,.paginacion span{display:inline-block;padding:8px 14px;margin:0 4px;border:1px solid #ccc;border-radius:4px;text-decoration:none;color:#4caf50;font-size:.9em}
        .paginacion a:hover{background-color:#e8f5e9;border-color:#a5d6a7}
        .paginacion span.actual{background-color:#4caf50;color:#fff;border-color:#43a047;font-weight:700}
        .paginacion span.disabled{color:#aaa;border-color:#ddd;cursor:default}
        .no-usuarios{text-align:center;padding:30px;font-size:1.2em;color:#777}
        .error-message{color:#d8000c;text-align:left;padding:15px;background-color:#ffdddd;border:1px solid #ffcccc;border-radius:5px;margin-bottom:20px;white-space:pre-wrap; font-family: monospace; font-size: 14px;}
        @media (max-width:991.98px){.menu-toggle{display:block}.menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}.menu.active{display:flex}.menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}.menu a:last-child{border-bottom:none}.menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}.menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}}
        @media (max-width:767.98px){.logo img{height:60px}.controles-tabla{flex-direction:column;align-items:stretch}.controles-tabla form{width:100%;flex-direction:column}.controles-tabla input[type=text]{min-width:0;width:100%;box-sizing:border-box}.controles-tabla button[type=submit]{width:100%;box-sizing:border-box}.tabla-usuarios{display:block;overflow-x:auto;white-space:nowrap}.tabla-usuarios td,.tabla-usuarios th{font-size:.85em;padding:8px}.page-container>h2.page-title{font-size:1.6em}}
        @media (max-width:575.98px){.logo img{height:50px}.menu-toggle{font-size:1.6rem}.page-container>h2.page-title{font-size:1.4em}.tabla-usuarios td,.tabla-usuarios th{font-size:.8em}}
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo" />
        </div>
        <div class="menu">
            <a href="admin_dashboard.php" class="active">Inicio</a>
            <a href="view_users.php">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="view_all_animals.php">Ver Animales</a>
            <a href="manage_users.php">Gestionar Usuarios</a>
            <a href="manage_animals.php">Gestionar Animales</a>
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </div>
    </div>

    <div class="page-container">
        <h2 class="page-title">Lista de Usuarios (No Administradores)</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <div class="controles-tabla">
            <form action="view_users.php" method="GET">
                <input type="text" name="buscar" placeholder="Buscar por nombre o email..." value="<?php echo htmlspecialchars($termino_busqueda); ?>">
                <button type="submit">Buscar</button>
            </form>
        </div>

        <?php if (empty($mensaje_error) && empty($usuarios) && !empty($termino_busqueda)): ?>
            <div class="no-usuarios">
                <p>No se encontraron usuarios que coincidan con "<?php echo htmlspecialchars($termino_busqueda); ?>".</p>
            </div>
        <?php elseif (empty($mensaje_error) && empty($usuarios)): ?>
            <div class="no-usuarios">
                <p>No hay usuarios (no administradores) registrados para mostrar.</p>
            </div>
        <?php elseif (!empty($usuarios)): ?>
            <table class="tabla-usuarios">
                <thead> <tr><th>ID Usuario</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th></tr> </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['id_usuario']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td><?php echo ($usuario['id_rol'] == 2) ? 'Usuario' : ('Rol ID: ' . htmlspecialchars($usuario['id_rol'])); ?></td>
                            <td><?php echo ($usuario['id_estado'] == 1) ? 'Activo' : ('Inactivo (ID: ' . htmlspecialchars($usuario['id_estado']) .')'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_paginas > 1): ?>
                <div class="paginacion">
                    <?php if ($pagina_actual > 1): ?><a href="?pagina=<?php echo $pagina_actual - 1; ?>&buscar=<?php echo urlencode($termino_busqueda); ?>">Anterior</a><?php else: ?><span class="disabled">Anterior</span><?php endif; ?>
                    <?php $rango_paginas = 2; $inicio_rango = max(1, $pagina_actual - $rango_paginas); $fin_rango = min($total_paginas, $pagina_actual + $rango_paginas);
                    if ($inicio_rango > 1) { echo '<a href="?pagina=1&buscar=' . urlencode($termino_busqueda) . '">1</a>'; if ($inicio_rango > 2) { echo '<span>...</span>'; } }
                    for ($i = $inicio_rango; $i <= $fin_rango; $i++): 
                        if ($i == $pagina_actual): echo '<span class="actual">' . $i . '</span>'; else: echo '<a href="?pagina=' . $i . '&buscar=' . urlencode($termino_busqueda) . '">' . $i . '</a>'; endif; 
                    endfor; 
                    if ($fin_rango < $total_paginas) { if ($fin_rango < $total_paginas - 1) { echo '<span>...</span>'; } echo '<a href="?pagina=' . $total_paginas . '&buscar=' . urlencode($termino_busqueda) . '">' . $total_paginas . '</a>'; }
                    ?>
                    <?php if ($pagina_actual < $total_paginas): ?><a href="?pagina=<?php echo $pagina_actual + 1; ?>&buscar=<?php echo urlencode($termino_busqueda); ?>">Siguiente</a><?php else: ?><span class="disabled">Siguiente</span><?php endif; ?>
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
                    menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
                });
            }
        });
    </script>
</body>
</html>