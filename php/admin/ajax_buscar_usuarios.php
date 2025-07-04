<?php
session_start();
require_once '../conexion.php'; // $pdo

// --- VERIFICACIÓN DE ACCESO DE ADMIN ---
// Solo administradores y superadministradores pueden usar esta función
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], [1, 3])) {
    header('Content-Type: application/json');
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Acceso no autorizado.']);
    exit();
}

$usuarios = [];
$html_tabla = '';
$html_paginacion = '';
$termino_busqueda = '';
$total_usuarios = 0;
$usuarios_por_pagina = 7;
$pagina_actual = 1;
$total_paginas = 1;

if (!isset($pdo)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la BD.']);
    exit();
}

try {
    $param_busqueda_like = null; 
    if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {
        $termino_busqueda = trim($_GET['buscar']);
        $param_busqueda_like = "%" . $termino_busqueda . "%";
    }

    // --- LÓGICA DE PAGINACIÓN - CONTEO ---
    // ===== CAMBIO 1: Añadir la condición para filtrar solo por rol 'usuario' (id_rol = 2) =====
    $sql_count = "SELECT COUNT(*) 
                  FROM usuarios u 
                  WHERE u.id_rol = 2"; // <-- Filtro añadido aquí
    $params_for_execute_count = [];
    if ($param_busqueda_like !== null) {
        $sql_count .= " AND (u.nombre LIKE ? OR u.email LIKE ?)";
        $params_for_execute_count[] = $param_busqueda_like;
        $params_for_execute_count[] = $param_busqueda_like;
    }
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params_for_execute_count);
    $total_usuarios = (int)$stmt_count->fetchColumn();
    
    $total_paginas = ceil($total_usuarios / $usuarios_por_pagina);
    $total_paginas = $total_paginas < 1 ? 1 : $total_paginas;

    if (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) {
        $pagina_actual = (int)$_GET['pagina'];
        if ($pagina_actual < 1) { $pagina_actual = 1; }
        elseif ($pagina_actual > $total_paginas) { $pagina_actual = $total_paginas; }
    }
    $offset_actual = ($pagina_actual - 1) * $usuarios_por_pagina;

    // --- CONSULTA PRINCIPAL ---
    // ===== CAMBIO 2: Añadir la misma condición a la consulta principal =====
    $sql_main = "SELECT u.id_usuario, u.nombre, u.email, u.id_estado, 
                       e.descripcion as nombre_estado
                 FROM usuarios u
                 JOIN estado e ON u.id_estado = e.id_estado
                 WHERE u.id_rol = 2"; // <-- Filtro añadido aquí
    $params_for_execute_main = [];
    if ($param_busqueda_like !== null) {
        $sql_main .= " AND (u.nombre LIKE ? OR u.email LIKE ?)";
        $params_for_execute_main[] = $param_busqueda_like;
        $params_for_execute_main[] = $param_busqueda_like;
    }
    $sql_main .= " ORDER BY u.nombre ASC LIMIT ? OFFSET ?";
    $params_for_execute_main[] = (int) $usuarios_por_pagina;
    $params_for_execute_main[] = (int) $offset_actual;
    
    $stmt_main = $pdo->prepare($sql_main);
    $stmt_main->execute($params_for_execute_main);
    $usuarios = $stmt_main->fetchAll(PDO::FETCH_ASSOC);

    // --- GENERAR HTML DE LA TABLA ---
    ob_start(); 
    if (empty($usuarios) && !empty($termino_busqueda)) {
        echo '<div class="no-datos"><p>No se encontraron usuarios que coincidan con "' . htmlspecialchars($termino_busqueda) . '".</p></div>';
    } elseif (empty($usuarios)) {
        echo '<div class="no-datos"><p>No hay usuarios registrados para mostrar.</p></div>';
    } else {
    ?>
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID Usuario</th><th>Nombre</th><th>Email</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($usuario['id_usuario']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['nombre_estado']); ?></td>
                        <td class="acciones">
                            <!-- Los enlaces de acciones se mantienen -->
                            <a href="admin_edit_user.php?id_usuario=<?php echo htmlspecialchars($usuario['id_usuario']); ?>&pagina=<?php echo $pagina_actual; ?>&buscar=<?php echo urlencode($termino_busqueda); ?>" class="btn-editar">Editar</a>
                            <?php if ($_SESSION['id_usuario'] !== $usuario['id_usuario']): ?>
                            <a href="admin_delete_user.php?id_usuario=<?php echo htmlspecialchars($usuario['id_usuario']); ?>&pagina=<?php echo $pagina_actual; ?>&buscar=<?php echo urlencode($termino_busqueda); ?>" 
                               class="btn-borrar" 
                               data-nombre-usuario="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                               onclick="return confirmDelete(this);"> 
                               Borrar
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php
    }
    $html_tabla = ob_get_clean(); 

    // --- GENERAR HTML DE LA PAGINACIÓN ---
    // (Tu código de paginación existente es correcto y no necesita cambios)
    ob_start();
    if ($total_paginas > 1): ?>
        <div class="paginacion">
            <?php if ($pagina_actual > 1): ?>
                <a href="#" data-pagina="<?php echo $pagina_actual - 1; ?>" data-buscar="<?php echo htmlspecialchars($termino_busqueda); ?>">Anterior</a>
            <?php else: ?>
                <span class="disabled">Anterior</span>
            <?php endif; ?>
            <?php 
            $rango_paginas = 2; $inicio_rango = max(1, $pagina_actual - $rango_paginas); $fin_rango = min($total_paginas, $pagina_actual + $rango_paginas);
            if ($inicio_rango > 1) { echo '<a href="#" data-pagina="1" data-buscar="'.htmlspecialchars($termino_busqueda).'">1</a>'; if ($inicio_rango > 2) { echo '<span>...</span>'; } }
            for ($i = $inicio_rango; $i <= $fin_rango; $i++):
                if ($i == $pagina_actual): echo '<span class="actual">' . $i . '</span>';
                else: echo '<a href="#" data-pagina="' . $i . '" data-buscar="'.htmlspecialchars($termino_busqueda).'">' . $i . '</a>'; endif;
            endfor;
            if ($fin_rango < $total_paginas) { if ($fin_rango < $total_paginas - 1) { echo '<span>...</span>'; } echo '<a href="#" data-pagina="'.$total_paginas.'" data-buscar="'.htmlspecialchars($termino_busqueda).'">' . $total_paginas . '</a>'; }
            ?>
            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="#" data-pagina="<?php echo $pagina_actual + 1; ?>" data-buscar="<?php echo htmlspecialchars($termino_busqueda); ?>">Siguiente</a>
            <?php else: ?>
                <span class="disabled">Siguiente</span>
            <?php endif; ?>
        </div>
    <?php endif;
    $html_paginacion = ob_get_clean();

    // Enviar respuesta JSON
    header('Content-Type: application/json');
    echo json_encode([
        'tabla' => $html_tabla,
        'paginacion' => $html_paginacion,
        'totalUsuarios' => $total_usuarios 
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500); 
    echo json_encode(['error' => "Error al obtener los usuarios: " . $e->getMessage()]);
}
?>