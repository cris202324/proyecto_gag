<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    header("Location: ../../pages/auth/login.html"); // Ajusta esta ruta
    exit();
}

require_once '../conexion.php'; // $pdo

$todos_animales = [];
$mensaje_error = '';
$mensaje_exito = ''; // Para mensajes de éxito de acciones como borrar
$total_animales = 0;
$animales_por_pagina = 7; 
$pagina_actual = 1;
$total_paginas = 1;

// Verificar si hay un mensaje de la acción de borrar (o editar)
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
        // --- LÓGICA DE PAGINACIÓN ---
        $sql_count = "SELECT COUNT(*) FROM animales";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute();
        $total_animales = (int)$stmt_count->fetchColumn();
        
        $total_paginas = ceil($total_animales / $animales_por_pagina);
        $total_paginas = $total_paginas < 1 ? 1 : $total_paginas;

        if (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) {
            $pagina_actual = (int)$_GET['pagina'];
            if ($pagina_actual < 1) {
                $pagina_actual = 1;
            } elseif ($pagina_actual > $total_paginas) {
                $pagina_actual = $total_paginas;
            }
        }
        $offset_actual = ($pagina_actual - 1) * $animales_por_pagina;

        // --- CONSULTA PRINCIPAL PARA OBTENER ANIMALES ---
        $sql = "SELECT 
                    a.id_animal, a.nombre_animal, a.tipo_animal, a.raza,
                    a.fecha_nacimiento, a.sexo, a.identificador_unico,
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
        /* Estilos generales (asumiendo que vienen de un CSS global o son similares a otras páginas admin) */
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;transition:height .3s ease}
        .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;transition:background-color .3s,color .3s,padding .3s ease;white-space:nowrap;font-size:.9em}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}
        
        .page-container{max-width:1200px; /* Más ancho para tabla con más columnas */ margin:20px auto;padding:20px}
        .page-container > h2.page-title{text-align:center;color:#4caf50;margin-bottom:25px;font-size:1.8em}
        
        .tabla-datos { width:100%;border-collapse:collapse;margin-bottom:20px;background-color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.1);border-radius:8px;overflow:hidden }
        .tabla-datos th, .tabla-datos td { border-bottom:1px solid #ddd;padding:10px;text-align:left;font-size:.8em; word-break:break-word; } /* Padding y font-size ajustados */
        .tabla-datos th { background-color:#f2f2f2;color:#333;font-weight:700;border-top:1px solid #ddd }
        .tabla-datos tr:last-child td { border-bottom:none }
        .tabla-datos tr:nth-child(even) { background-color:#f9f9f9 }
        .tabla-datos tr:hover { background-color:#f1f1f1 }
        .tabla-datos .acciones a, .tabla-datos .acciones button { /* Estilos para botones de acción en la tabla */
            display:inline-block; padding: 5px 10px; margin-right:5px; margin-bottom:5px; /* Para que se apilen bien en móvil */
            font-size:0.8em; text-decoration:none; color:white; border-radius:4px; border:none; cursor:pointer;
        }
        .tabla-datos .acciones .btn-editar { background-color:#3498db; } /* Azul */
        .tabla-datos .acciones .btn-editar:hover { background-color:#2980b9; }
        .tabla-datos .acciones .btn-borrar { background-color:#e74c3c; } /* Rojo */
        .tabla-datos .acciones .btn-borrar:hover { background-color:#c0392b; }


        .paginacion{text-align:center;margin-top:30px;padding-bottom:20px}
        .paginacion a,.paginacion span{display:inline-block;padding:8px 14px;margin:0 4px;border:1px solid #ccc;border-radius:4px;text-decoration:none;color:#4caf50;font-size:.9em}
        .paginacion a:hover{background-color:#e8f5e9;border-color:#a5d6a7}
        .paginacion span.actual{background-color:#4caf50;color:#fff;border-color:#43a047;font-weight:700}
        .paginacion span.disabled{color:#aaa;border-color:#ddd;cursor:default}
        
        .no-datos { text-align:center;padding:30px;font-size:1.2em;color:#777 }
        .error-message, .success-message { 
            text-align:center;padding:15px;border-radius:5px;margin-bottom:20px;
            font-family:monospace;font-size:14px 
        }
        .error-message { color:#d8000c; background-color:#ffdddd; border:1px solid #ffcccc; }
        .success-message { color:#270; background-color:#DFF2BF; border:1px solid #4F8A10; }


        @media (max-width:991.98px){
            .menu-toggle{display:block}
            .menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}
            .menu.active{display:flex}
            .menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}
            .menu a:last-child{border-bottom:none}
            .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:transparent}
            .menu a.exit,.menu a.exit:hover{background-color:#ff4d4d;color:#fff!important}
        }
        @media (max-width:768px){
            .logo img{height:60px}
            .tabla-datos{display:block;overflow-x:auto;white-space:nowrap}
            .tabla-datos th,.tabla-datos td{font-size:.75em;padding:6px 8px} /* Más pequeño */
            .page-container > h2.page-title{font-size:1.6em}
            .tabla-datos .acciones a, .tabla-datos .acciones button { display: block; margin-bottom: 5px; width: 100%; box-sizing: border-box; text-align:center;}

        }
        @media (max-width:480px){
            .logo img{height:50px}
            .menu-toggle{font-size:1.6rem}
            .page-container > h2.page-title{font-size:1.4em}
            .tabla-datos th,.tabla-datos td{font-size:.7em} /* Aún más pequeño */
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
    </div>

    <div class="page-container">
        <h2 class="page-title">Todos los Animales Registrados</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_exito)): ?>
            <p class="success-message"><?php echo htmlspecialchars($mensaje_exito); ?></p>
        <?php endif; ?>


        <?php if (empty($mensaje_error) && empty($todos_animales)): ?>
            <div class="no-datos">
                <p>No hay animales registrados en el sistema.</p>
            </div>
        <?php elseif (!empty($todos_animales)): ?>
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Raza</th>
                        <th>Usuario</th>
                        <th>F. Nac.</th>
                        <th>Sexo</th>
                        <th>ID Único</th>
                        <th>F. Reg.</th>
                        <th>Acciones</th> <!-- Nueva columna -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todos_animales as $animal): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($animal['id_animal']); ?></td>
                            <td><?php echo htmlspecialchars($animal['nombre_animal'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($animal['tipo_animal']); ?></td>
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
                    menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
                });
            }
        });
    </script>
</body>
</html>