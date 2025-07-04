<?php
session_start();

// 1. VERIFICACIÓN DE ACCESO DE SUPERADMIN
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['rol']) || $_SESSION['rol'] != 3) {
    header("Location: ../../pages/login.html");
    exit();
}

require_once '../conexion.php'; // Asegúrate que la ruta a tu conexión es correcta

$usuarios = [];
$roles_disponibles = [];
$estados_disponibles = [];
$mensaje_error = '';

try {
    // 2. OBTENER TODOS LOS USUARIOS PARA MOSTRARLOS
    $stmt_usuarios = $pdo->query("
        SELECT u.id_usuario, u.nombre, u.email, u.id_rol, r.rol AS nombre_rol, u.id_estado, e.descripcion AS nombre_estado
        FROM usuarios u
        JOIN rol r ON u.id_rol = r.id_rol
        JOIN estado e ON u.id_estado = e.id_estado
        ORDER BY u.id_rol DESC, u.nombre ASC
    ");
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

    // 3. OBTENER ROLES Y ESTADOS DISPONIBLES
    $stmt_roles = $pdo->query("SELECT id_rol, rol FROM rol ORDER BY id_rol");
    $roles_disponibles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

    $stmt_estados = $pdo->query("SELECT id_estado, descripcion FROM estado ORDER BY id_estado");
    $estados_disponibles = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $mensaje_error = "Error al obtener los datos de usuarios: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Super Admin</title>
    <style>
        /* (Tus estilos existentes... sin cambios aquí) */
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f1f1f1; }
        .page-container { max-width: 1200px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color:rgb(49, 145, 68); }
        .tabla-usuarios { width: 100%; border-collapse: collapse; }
        .tabla-usuarios th, .tabla-usuarios td { padding: 12px; border: 1px solid #ddd; text-align: left; font-size: 0.9em; }
        .tabla-usuarios th { background-color: #333; color: white; }
        .tabla-usuarios tr:nth-child(even) { background-color: #f9f9f9; }
        .btn-edit { background-color: #f0ad4e; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
        .btn-edit:hover { background-color: #ec971f; }
        .role-superadmin { font-weight: bold; color: #d9534f; }
        .role-admin { font-weight: bold; color: #337ab7; }
        .role-usuario { color: #555; }
        .status-activo { color: #5cb85c; }
        .status-inactivo { color: #777; }
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);
            align-items: center; justify-content: center;
        }
        .modal-content {
            background-color: #fff; margin: auto; padding: 25px; border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 90%; max-width: 500px;
            position: relative; animation: fadeIn 0.3s;
        }
        @keyframes fadeIn { from {opacity: 0; transform: scale(0.95);} to {opacity: 1; transform: scale(1);} }
        .close-button {
            color: #aaa; float: right; font-size: 28px; font-weight: bold;
            position: absolute; top: 10px; right: 20px;
        }
        .close-button:hover, .close-button:focus { color: black; text-decoration: none; cursor: pointer; }
        .modal h2 { margin-top: 0; color: #333; }
        .modal .form-group { margin-bottom: 15px; }
        .modal label { display: block; margin-bottom: 5px; font-weight: bold; }
        .modal input, .modal select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .modal .btn-save {
            background-color: #5cb85c; color: white; padding: 10px 15px; border: none;
            border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px;
        }
        .modal .btn-save:hover { background-color: #4cae4c; }
        #feedback { margin-top: 15px; font-weight: bold; text-align: center; }
        #feedback.success { color: green; }
        #feedback.error { color: red; }
    </style>
</head>
<body>
    <div class="page-container">
        <h1>Gestión de Usuarios (Super Admin)</h1>
        <p style="text-align: center; color: #777;">Desde este panel puedes modificar nombre, email, rol, estado y contraseña de cualquier usuario.</p>
        <a href="../../pages/auth/login.html" style="display: block; text-align: center; margin-bottom: 20px;">Volver al inicio</a>

        <?php if ($mensaje_error): ?>
            <p style="color: red; text-align:center;"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <div style="overflow-x: auto;">
            <table class="tabla-usuarios">
                <thead>
                    <tr>
                        <th>ID Usuario</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['id_usuario']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td>
                                <span class="role-<?php echo strtolower(htmlspecialchars($usuario['nombre_rol'])); ?>">
                                    <?php echo htmlspecialchars(ucfirst($usuario['nombre_rol'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-<?php echo strtolower(htmlspecialchars($usuario['nombre_estado'])); ?>">
                                    <?php echo htmlspecialchars($usuario['nombre_estado']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-edit"
                                        data-id="<?php echo htmlspecialchars($usuario['id_usuario']); ?>"
                                        data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                                        data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                                        data-rol-id="<?php echo htmlspecialchars($usuario['id_rol']); ?>"
                                        data-estado-id="<?php echo htmlspecialchars($usuario['id_estado']); ?>">
                                    Editar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- El Modal para Editar Usuario -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close-button">×</span>
            <h2>Editar Usuario</h2>
            
            <form id="editUserForm">
                <input type="hidden" id="modalUserId" name="user_id">
                
                <!-- ===== NUEVOS CAMPOS DE EDICIÓN ===== -->
                <div class="form-group">
                    <label for="modalUserName">Nombre:</label>
                    <input type="text" id="modalUserName" name="user_name" required>
                </div>
                
                <div class="form-group">
                    <label for="modalUserEmail">Email:</label>
                    <input type="email" id="modalUserEmail" name="user_email" required>
                </div>
                <!-- ==================================== -->
                
                <div class="form-group">
                    <label for="modalUserRole">Rol:</label>
                    <select id="modalUserRole" name="role_id" required>
                        <?php foreach ($roles_disponibles as $rol): ?>
                            <option value="<?php echo $rol['id_rol']; ?>"><?php echo htmlspecialchars(ucfirst($rol['rol'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modalUserStatus">Estado:</label>
                    <select id="modalUserStatus" name="status_id" required>
                         <?php foreach ($estados_disponibles as $estado): ?>
                            <option value="<?php echo $estado['id_estado']; ?>"><?php echo htmlspecialchars($estado['descripcion']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <hr>
                
                <div class="form-group">
                    <label for="modalNewPassword">Nueva Contraseña (dejar en blanco para no cambiar):</label>
                    <input type="password" id="modalNewPassword" name="new_password" autocomplete="new-password">
                </div>

                <button type="submit" class="btn-save">Guardar Cambios</button>
            </form>
            <div id="feedback"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('editUserModal');
            const closeButton = modal.querySelector('.close-button');
            const editButtons = document.querySelectorAll('.btn-edit');

            // Función para abrir el modal y llenarlo con datos
            function openModal(userData) {
                document.getElementById('modalUserId').value = userData.id;
                document.getElementById('modalUserName').value = userData.nombre; // Cambiado a .value
                document.getElementById('modalUserEmail').value = userData.email; // Cambiado a .value
                document.getElementById('modalUserRole').value = userData.rolId;
                document.getElementById('modalUserStatus').value = userData.estadoId;
                document.getElementById('modalNewPassword').value = '';
                document.getElementById('feedback').textContent = '';
                
                // Deshabilitar la edición para el propio superadmin por seguridad
                const formElements = document.getElementById('editUserForm').elements;
                const isSelf = '<?php echo $_SESSION['id_usuario']; ?>' === userData.id;
                for (let i = 0; i < formElements.length; i++) {
                    // Permitir cambiar la contraseña propia, pero no el rol ni el estado
                    if (isSelf && (formElements[i].name === 'role_id' || formElements[i].name === 'status_id')) {
                         formElements[i].disabled = true;
                    } else {
                         formElements[i].disabled = false;
                    }
                }

                if (isSelf) {
                    document.getElementById('feedback').textContent = "No puedes cambiar tu propio rol o estado.";
                    document.getElementById('feedback').className = 'error';
                }

                modal.style.display = 'flex';
            }

            editButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const userData = {
                        id: button.dataset.id,
                        nombre: button.dataset.nombre,
                        email: button.dataset.email,
                        rolId: button.dataset.rolId,
                        estadoId: button.dataset.estadoId
                    };
                    openModal(userData);
                });
            });

            closeButton.onclick = () => { modal.style.display = 'none'; };
            window.onclick = (event) => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            };

            const editForm = document.getElementById('editUserForm');
            editForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(editForm);
                const feedbackDiv = document.getElementById('feedback');
                
                feedbackDiv.textContent = 'Guardando...';
                feedbackDiv.className = '';

                fetch('superadmin_update_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        feedbackDiv.textContent = data.message;
                        feedbackDiv.className = 'success';
                        setTimeout(() => { window.location.reload(); }, 2000);
                    } else {
                        feedbackDiv.textContent = data.message;
                        feedbackDiv.className = 'error';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    feedbackDiv.textContent = 'Error de conexión. Inténtelo de nuevo.';
                    feedbackDiv.className = 'error';
                });
            });
        });
    </script>
</body>
</html>