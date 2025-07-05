<?php
// Inicia la sesión de PHP. Es crucial para acceder a variables de sesión como $_SESSION['id_usuario'] y $_SESSION['rol'].
session_start();

// 1. --- VERIFICACIÓN DE ACCESO DE SUPERADMIN ---
// Esta es una barrera de seguridad. Comprueba si el usuario ha iniciado sesión (isset($_SESSION['id_usuario'])),
// si tiene un rol definido (isset($_SESSION['rol'])), y si ese rol es específicamente el de Super Admin (rol ID 3).
// Si alguna de estas condiciones falla, el usuario es redirigido a la página de login y el script se detiene.
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['rol']) || $_SESSION['rol'] != 3) {
    header("Location: ../../pages/login.html");
    exit(); // Detiene la ejecución del script para proteger la página.
}

// Incluye el archivo que establece la conexión a la base de datos y define la variable $pdo.
require_once '../conexion.php'; 

// Inicializa arrays y variables que se usarán más adelante para evitar errores de "variable no definida".
$usuarios = []; // Para almacenar la lista de todos los usuarios.
$roles_disponibles = []; // Para poblar el menú desplegable de roles en el modal.
$estados_disponibles = []; // Para poblar el menú desplegable de estados en el modal.
$mensaje_error = ''; // Para mostrar errores de base de datos.

// El bloque try-catch maneja de forma segura los errores que puedan ocurrir durante las consultas a la base de datos.
try {
    // 2. OBTENER TODOS LOS USUARIOS PARA MOSTRARLOS EN LA TABLA
    // Se ejecuta una consulta SQL que une las tablas `usuarios`, `rol` y `estado` para obtener no solo los IDs,
    // sino también los nombres legibles del rol y del estado de cada usuario.
    // Se ordena por rol (para agruparlos visualmente) y luego por nombre.
    $stmt_usuarios = $pdo->query("
        SELECT u.id_usuario, u.nombre, u.email, u.id_rol, r.rol AS nombre_rol, u.id_estado, e.descripcion AS nombre_estado
        FROM usuarios u
        JOIN rol r ON u.id_rol = r.id_rol
        JOIN estado e ON u.id_estado = e.id_estado
        ORDER BY u.id_rol DESC, u.nombre ASC
    ");
    // `fetchAll(PDO::FETCH_ASSOC)` recupera todas las filas del resultado y las guarda en el array `$usuarios`.
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

    // 3. OBTENER ROLES Y ESTADOS DISPONIBLES PARA LOS MENÚS DESPLEGABLES
    // Se obtienen todos los roles disponibles de la tabla 'rol'.
    $stmt_roles = $pdo->query("SELECT id_rol, rol FROM rol ORDER BY id_rol");
    $roles_disponibles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

    // Se obtienen todos los estados disponibles de la tabla 'estado'.
    $stmt_estados = $pdo->query("SELECT id_estado, descripcion FROM estado ORDER BY id_estado");
    $estados_disponibles = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Si alguna de las consultas falla, se captura el error y se guarda un mensaje para mostrarlo al usuario.
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
        /* --- ESTILOS CSS --- */
        /* Aquí se definen todos los estilos para la página, incluyendo el layout, la tabla,
           los botones y el modal (la ventana emergente para editar). */
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f1f1f1; }
        .page-container { max-width: 1200px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color:rgb(49, 145, 68); }
        
        /* Estilos del botón de cerrar sesión */
        .logout-container { text-align: center; margin-bottom: 25px; }
        .btn-logout { 
            display: inline-block; 
            padding: 10px 20px; 
            background-color: #d9534f; /* Color rojo */
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            font-weight: bold; 
            transition: background-color 0.3s ease;
        }
        .btn-logout:hover { background-color: #c9302c; }

        .tabla-usuarios { width: 100%; border-collapse: collapse; margin-top: 20px; }
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
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; margin: auto; padding: 25px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 90%; max-width: 500px; position: relative; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from {opacity: 0; transform: scale(0.95);} to {opacity: 1; transform: scale(1);} }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; position: absolute; top: 10px; right: 20px; }
        .close-button:hover, .close-button:focus { color: black; text-decoration: none; cursor: pointer; }
        .modal h2 { margin-top: 0; color: #333; }
        .modal .form-group { margin-bottom: 15px; }
        .modal label { display: block; margin-bottom: 5px; font-weight: bold; }
        .modal input, .modal select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .modal .btn-save { background-color: #5cb85c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; }
        .modal .btn-save:hover { background-color: #4cae4c; }
        #feedback { margin-top: 15px; font-weight: bold; text-align: center; }
        #feedback.success { color: green; }
        #feedback.error { color: red; }
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML DE LA PÁGINA --- -->
    <div class="page-container">
        <h1>Gestión de Usuarios (Super Admin)</h1>
        <p style="text-align: center; color: #777;">Desde este panel puedes modificar nombre, email, rol, estado y contraseña de cualquier usuario.</p>
        
        <!-- Contenedor y botón para cerrar la sesión del Super Admin -->
        <div class="logout-container">
            <a href="../cerrar_sesion.php" class="btn-logout">Cerrar Sesión</a>
        </div>

        <!-- Muestra un mensaje de error si ocurrió alguno durante la carga de datos. -->
        <?php if ($mensaje_error): ?>
            <p style="color: red; text-align:center;"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <!-- Contenedor para hacer la tabla responsive (scroll horizontal en móviles) -->
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
                    <!-- Bucle que recorre el array de usuarios y crea una fila <tr> por cada uno. -->
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <!-- Se imprimen los datos del usuario en cada celda <td>. `htmlspecialchars` previene ataques XSS. -->
                            <td><?php echo htmlspecialchars($usuario['id_usuario']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td>
                                <!-- Se aplica una clase CSS dinámica basada en el nombre del rol para darle un estilo visual. -->
                                <span class="role-<?php echo strtolower(htmlspecialchars($usuario['nombre_rol'])); ?>">
                                    <?php echo htmlspecialchars(ucfirst($usuario['nombre_rol'])); ?>
                                </span>
                            </td>
                            <td>
                                <!-- Se aplica una clase CSS dinámica basada en el estado para darle un estilo visual. -->
                                <span class="status-<?php echo strtolower(htmlspecialchars($usuario['nombre_estado'])); ?>">
                                    <?php echo htmlspecialchars($usuario['nombre_estado']); ?>
                                </span>
                            </td>
                            <td>
                                <!-- Botón para editar el usuario. Se almacenan todos los datos del usuario en atributos `data-*`.
                                     Esto permite que JavaScript los lea fácilmente cuando se hace clic en el botón. -->
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

    <!-- El Modal (ventana emergente) para Editar Usuario. Inicialmente está oculto (display: none). -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close-button">×</span>
            <h2>Editar Usuario</h2>
            
            <form id="editUserForm">
                <!-- Campo oculto para enviar el ID del usuario que se está editando. -->
                <input type="hidden" id="modalUserId" name="user_id">
                
                <div class="form-group">
                    <label for="modalUserName">Nombre:</label>
                    <input type="text" id="modalUserName" name="user_name" required>
                </div>
                
                <div class="form-group">
                    <label for="modalUserEmail">Email:</label>
                    <input type="email" id="modalUserEmail" name="user_email" required>
                </div>
                
                <div class="form-group">
                    <label for="modalUserRole">Rol:</label>
                    <select id="modalUserRole" name="role_id" required>
                        <!-- Se llena el menú desplegable con los roles obtenidos de la base de datos. -->
                        <?php foreach ($roles_disponibles as $rol): ?>
                            <option value="<?php echo $rol['id_rol']; ?>"><?php echo htmlspecialchars(ucfirst($rol['rol'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modalUserStatus">Estado:</label>
                    <select id="modalUserStatus" name="status_id" required>
                         <!-- Se llena el menú desplegable con los estados obtenidos de la base de datos. -->
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
            <!-- Div para mostrar mensajes de feedback (éxito o error) después de guardar. -->
            <div id="feedback"></div>
        </div>
    </div>

    <!-- --- SCRIPT JAVASCRIPT PARA LA INTERACTIVIDAD DEL MODAL --- -->
    <script>
        // Se ejecuta cuando el contenido HTML de la página ha sido completamente cargado.
        document.addEventListener('DOMContentLoaded', () => {
            // Se obtienen los elementos del DOM necesarios: el modal, el botón de cerrar y todos los botones de editar.
            const modal = document.getElementById('editUserModal');
            const closeButton = modal.querySelector('.close-button');
            const editButtons = document.querySelectorAll('.btn-edit');

            // Función para abrir el modal y llenarlo con los datos del usuario seleccionado.
            function openModal(userData) {
                // Se toman los datos del objeto `userData` y se asignan a los campos del formulario en el modal.
                document.getElementById('modalUserId').value = userData.id;
                document.getElementById('modalUserName').value = userData.nombre;
                document.getElementById('modalUserEmail').value = userData.email;
                document.getElementById('modalUserRole').value = userData.rolId;
                document.getElementById('modalUserStatus').value = userData.estadoId;
                // Se limpian los campos de contraseña y feedback cada vez que se abre.
                document.getElementById('modalNewPassword').value = '';
                document.getElementById('feedback').textContent = '';
                
                // --- Lógica de seguridad para evitar que el superadmin se bloquee a sí mismo ---
                const formElements = document.getElementById('editUserForm').elements;
                const isSelf = '<?php echo $_SESSION['id_usuario']; ?>' === userData.id;
                for (let i = 0; i < formElements.length; i++) {
                    // Si el usuario que se edita es el mismo que está logueado,
                    // se desactivan los campos de rol y estado para que no pueda cambiarlos.
                    if (isSelf && (formElements[i].name === 'role_id' || formElements[i].name === 'status_id')) {
                         formElements[i].disabled = true;
                    } else {
                         formElements[i].disabled = false;
                    }
                }
                // Se muestra un mensaje de advertencia si se está editando a sí mismo.
                if (isSelf) {
                    document.getElementById('feedback').textContent = "No puedes cambiar tu propio rol o estado.";
                    document.getElementById('feedback').className = 'error';
                }
                
                // Se muestra el modal cambiando su estilo 'display'.
                modal.style.display = 'flex';
            }

            // Se añade un "escuchador de eventos" a cada botón de "Editar".
            editButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Cuando se hace clic, se crea un objeto `userData` con la información almacenada en los atributos `data-*` del botón.
                    const userData = {
                        id: button.dataset.id,
                        nombre: button.dataset.nombre,
                        email: button.dataset.email,
                        rolId: button.dataset.rolId,
                        estadoId: button.dataset.estadoId
                    };
                    // Se llama a la función para abrir el modal con estos datos.
                    openModal(userData);
                });
            });

            // Lógica para cerrar el modal: al hacer clic en la '×' o fuera del contenido del modal.
            closeButton.onclick = () => { modal.style.display = 'none'; };
            window.onclick = (event) => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            };

            // Lógica para enviar el formulario de edición usando AJAX (fetch).
            const editForm = document.getElementById('editUserForm');
            editForm.addEventListener('submit', (e) => {
                e.preventDefault(); // Previene que el formulario se envíe de la forma tradicional (recargando la página).
                const formData = new FormData(editForm); // Recolecta todos los datos del formulario.
                const feedbackDiv = document.getElementById('feedback');
                
                feedbackDiv.textContent = 'Guardando...';
                feedbackDiv.className = '';

                // Se realiza una petición POST al script 'superadmin_update_user.php' con los datos del formulario.
                fetch('superadmin_update_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json()) // Se espera una respuesta en formato JSON.
                .then(data => {
                    // Se procesa la respuesta del servidor.
                    if (data.success) {
                        // Si la operación fue exitosa, se muestra un mensaje de éxito.
                        feedbackDiv.textContent = data.message;
                        feedbackDiv.className = 'success';
                        // Después de 2 segundos, se recarga la página para mostrar los cambios en la tabla.
                        setTimeout(() => { window.location.reload(); }, 2000);
                    } else {
                        // Si hubo un error, se muestra el mensaje de error.
                        feedbackDiv.textContent = data.message;
                        feedbackDiv.className = 'error';
                    }
                })
                .catch(error => {
                    // Si hay un error de conexión o en la petición, se muestra un mensaje genérico.
                    console.error('Error:', error);
                    feedbackDiv.textContent = 'Error de conexión. Inténtelo de nuevo.';
                    feedbackDiv.className = 'error';
                });
            });
        });
    </script>
</body>
</html>