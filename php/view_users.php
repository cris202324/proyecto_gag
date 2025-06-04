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

// --- DEFINICIÓN DE VARIABLES INICIALES ---
// Estas variables se usan en el HTML y JS, así que deben definirse ANTES de cualquier 'die' o 'exit' potencial.
$termino_busqueda_inicial = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$pagina_inicial = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

$mensaje_error_pagina = ''; // Para errores críticos de la página (ej. fallo de conexión)
$mensaje_exito_accion = ''; // Para mensajes de acciones (editar/borrar) que vienen de sesión

// Obtener mensajes de sesión de acciones previas (editar/borrar usuario)
if (isset($_SESSION['mensaje_accion_usuario'])) {
    $mensaje_exito_accion = $_SESSION['mensaje_accion_usuario'];
    unset($_SESSION['mensaje_accion_usuario']); 
}
if (isset($_SESSION['error_accion_usuario'])) {
    // Si hay un error de acción, lo mostramos en $mensaje_error_pagina
    $mensaje_error_pagina = $_SESSION['error_accion_usuario']; 
    unset($_SESSION['error_accion_usuario']);
}

// Incluir conexión DESPUÉS de definir variables que el HTML podría necesitar
require_once 'conexion.php'; 

if (!isset($pdo)) {
    // Establecer mensaje de error si $pdo no está y no hay otro mensaje de error prioritario
    if (empty($mensaje_error_pagina)) { 
        $mensaje_error_pagina = "Error crítico: La conexión a la base de datos no está disponible. La funcionalidad de la tabla de usuarios estará deshabilitada.";
    }
}
// La lógica de obtener datos ($usuarios, $total_paginas, etc.) se ha movido a ajax_buscar_usuarios.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - Admin GAG</title>
    <style>
        /* Estilos generales */
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;transition:height .3s ease}
        .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;white-space:nowrap;font-size:.9em;transition:background-color .3s, color .3s}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}
        
        .page-container{max-width:1100px;margin:20px auto;padding:20px}
        .page-container > h2.page-title{text-align:center;color:#4caf50;margin-bottom:25px;font-size:1.8em}
        
        .controles-tabla{margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
        .search-form { display:flex; flex-grow:1; max-width:400px; }
        .search-form input[type="text"]{padding:8px 10px;border:1px solid #ccc;border-radius:5px 0 0 5px;font-size:.9em;flex-grow:1; border-right:none;}
        /* .search-form button[type="submit"]{padding:8px 15px;background-color:#5cb85c;color:white;border:none;border-radius:0 5px 5px 0;cursor:pointer;font-size:.9em} */
        /* .search-form button[type="submit"]:hover{background-color:#4cae4c} */

        #tablaUsuariosContainer .tabla-datos {width:100%;border-collapse:collapse;margin-bottom:0;background-color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.1);border-radius:8px;overflow:hidden}
        #tablaUsuariosContainer .tabla-datos th, #tablaUsuariosContainer .tabla-datos td {border-bottom:1px solid #ddd;padding:10px;text-align:left;font-size:.85em;word-break:break-word}
        #tablaUsuariosContainer .tabla-datos th {background-color:#f2f2f2;color:#333;font-weight:700;border-top:1px solid #ddd}
        #tablaUsuariosContainer .tabla-datos tr:last-child td {border-bottom:none}
        #tablaUsuariosContainer .tabla-datos tr:nth-child(even) {background-color:#f9f9f9}
        #tablaUsuariosContainer .tabla-datos tr:hover {background-color:#f1f1f1}
        #tablaUsuariosContainer .tabla-datos .acciones a {display:inline-block;padding:5px 8px;margin-right:5px;margin-bottom:5px;font-size:0.8em;text-decoration:none;color:white;border-radius:4px;border:none;cursor:pointer;transition: background-color 0.2s ease;}
        #tablaUsuariosContainer .tabla-datos .acciones .btn-editar {background-color:#3498db;}
        #tablaUsuariosContainer .tabla-datos .acciones .btn-editar:hover {background-color:#2980b9;}
        #tablaUsuariosContainer .tabla-datos .acciones .btn-borrar {background-color:#e74c3c;}
        #tablaUsuariosContainer .tabla-datos .acciones .btn-borrar:hover {background-color:#c0392b;}
        
        #paginacionContainer .paginacion{text-align:center;margin-top:20px;padding-bottom:20px}
        #paginacionContainer .paginacion a,#paginacionContainer .paginacion span{display:inline-block;padding:8px 14px;margin:0 4px;border:1px solid #ccc;border-radius:4px;text-decoration:none;color:#4caf50;font-size:.9em}
        #paginacionContainer .paginacion a:hover{background-color:#e8f5e9;border-color:#a5d6a7}
        #paginacionContainer .paginacion span.actual{background-color:#4caf50;color:#fff;border-color:#43a047;font-weight:700}
        #paginacionContainer .paginacion span.disabled{color:#aaa;border-color:#ddd;cursor:default}
        
        .no-datos {text-align:center;padding:30px;font-size:1.2em;color:#777}
        .error-message, .success-message {text-align:center;padding:15px;border-radius:5px;margin-bottom:20px;font-size:0.9em;}
        .error-message {color:#d8000c;background-color:#ffdddd;border:1px solid #ffcccc;}
        .success-message {color:#270;background-color:#DFF2BF;border:1px solid #4F8A10;}
        #loadingMessage { text-align:center; font-style:italic; color:#777; padding:20px; display:none;}

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
            .controles-tabla { flex-direction:column; align-items:stretch; }
            .search-form { max-width:none; } 
            .tabla-datos{display:block;overflow-x:auto;white-space:nowrap}
            .tabla-datos th,.tabla-datos td{font-size:.8em;padding:7px 9px}
            .page-container > h2.page-title{font-size:1.6em}
            #tablaUsuariosContainer .tabla-datos .acciones a { display: block; margin-bottom: 5px; width: 100%; box-sizing: border-box; text-align:center;}
        }
        @media (max-width:480px){
            .logo img{height:50px}
            .menu-toggle{font-size:1.6rem}
            .page-container > h2.page-title{font-size:1.4em}
            .tabla-datos th,.tabla-datos td{font-size:.75em}
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="logo GAG" /> <!-- Ajusta esta ruta -->
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="admin_dashboard.php">Inicio</a> 
            <a href="view_users.php" class="active">Ver Usuarios</a> 
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="view_all_animals.php">Ver Animales</a>
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h2 class="page-title">Lista de Usuarios (No Administradores)</h2>

        <?php 
        if (!empty($mensaje_exito_accion)) {
            echo '<p class="success-message">' . htmlspecialchars($mensaje_exito_accion) . '</p>';
        }
        if (!empty($mensaje_error_pagina)) { 
            echo '<p class="error-message">' . htmlspecialchars($mensaje_error_pagina) . '</p>';
        }
        ?>

        <?php if (isset($pdo) && empty($mensaje_error_pagina) || (isset($pdo) && !strpos($mensaje_error_pagina, "crítico")) ): // Solo mostrar controles y tabla si hay conexión y no hay error CRÍTICO de página ?>
            <div class="controles-tabla">
                <form id="searchForm" class="search-form" onsubmit="return false;"> 
                    <input type="text" id="searchInput" name="buscar" placeholder="Buscar por nombre o email..." value="<?php echo htmlspecialchars($termino_busqueda_inicial); ?>">
                </form>
            </div>

            <div id="tablaUsuariosContainer">
                <p id="loadingMessage">Cargando usuarios...</p>
            </div>
            <div id="paginacionContainer">
                <!-- La paginación se cargará aquí con AJAX -->
            </div>
        <?php endif; // Fin de if (isset($pdo)) ?>
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

            <?php if (isset($pdo) && empty($mensaje_error_pagina) || (isset($pdo) && !strpos($mensaje_error_pagina, "crítico"))): // Solo ejecutar JS de AJAX si $pdo está disponible y no hay error crítico ?>
                const searchInput = document.getElementById('searchInput');
                const tablaContainer = document.getElementById('tablaUsuariosContainer');
                const paginacionContainer = document.getElementById('paginacionContainer');
                const loadingMessage = document.getElementById('loadingMessage');
                
                let currentPage = <?php echo $pagina_inicial; ?>;
                let currentSearchTerm = searchInput ? searchInput.value : "<?php echo htmlspecialchars(addslashes($termino_busqueda_inicial)); ?>"; 
                let searchTimeout;

                function fetchUsuarios(termino = '', pagina = 1) {
                    if(loadingMessage) loadingMessage.style.display = 'block';
                    if(tablaContainer) tablaContainer.innerHTML = ''; 
                    if(paginacionContainer) paginacionContainer.innerHTML = ''; 

                    const url = `ajax_buscar_usuarios.php?buscar=${encodeURIComponent(termino)}&pagina=${pagina}`;

                    fetch(url)
                        .then(response => {
                            if (!response.ok) { throw new Error(`Error HTTP: ${response.status}`); }
                            return response.json();
                        })
                        .then(data => {
                            if(loadingMessage) loadingMessage.style.display = 'none';
                            if (data.error) {
                                if(tablaContainer) tablaContainer.innerHTML = `<p class="error-message">${data.error}</p>`;
                            } else {
                                if(tablaContainer) tablaContainer.innerHTML = data.tabla;
                                if(paginacionContainer) paginacionContainer.innerHTML = data.paginacion;
                                currentPage = pagina; 
                                currentSearchTerm = termino; 
                                updateURL(termino, pagina);
                                addPaginacionListeners(); 
                                addDeleteConfirmations(); 
                            }
                        })
                        .catch(error => {
                            if(loadingMessage) loadingMessage.style.display = 'none';
                            console.error('Error en fetchUsuarios:', error);
                            if(tablaContainer) tablaContainer.innerHTML = `<p class="error-message">Error al cargar los datos: ${error.message}. Intente más tarde.</p>`;
                        });
                }

                function updateURL(termino, pagina) { 
                    const baseUrl = window.location.pathname; 
                    const params = new URLSearchParams();
                    if (termino) { params.append('buscar', termino); }
                    if (pagina > 1) { params.append('pagina', pagina); }
                    const newURL = params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
                    window.history.pushState({path: newURL}, '', newURL);
                }
                
                function addPaginacionListeners() { 
                    if (!paginacionContainer) return;
                    const paginacionLinks = paginacionContainer.querySelectorAll('.paginacion a');
                    paginacionLinks.forEach(link => {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            const targetPage = this.dataset.pagina;
                            fetchUsuarios(currentSearchTerm, targetPage); 
                        });
                    });
                 }

                function addDeleteConfirmations() { 
                    if (!tablaContainer) return;
                    const deleteLinks = tablaContainer.querySelectorAll('.btn-borrar');
                    deleteLinks.forEach(link => {
                        link.removeEventListener('click', handleDeleteConfirm); 
                        link.addEventListener('click', handleDeleteConfirm);
                    });
                }

                function handleDeleteConfirm(event) { 
                    const userNameElement = this.closest('tr').querySelector('td:nth-child(2)');
                    const userName = userNameElement ? userNameElement.textContent : 'este usuario';
                    if (!confirm(`¿Estás realmente seguro de que deseas eliminar a ${userName}? Esta acción no se puede deshacer.`)) {
                        event.preventDefault();
                    }
                 }

                if (searchInput && tablaContainer && paginacionContainer && loadingMessage) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        const searchTerm = this.value;
                        searchTimeout = setTimeout(() => {
                            fetchUsuarios(searchTerm, 1); 
                        }, 300);
                    });
                    // Carga inicial de usuarios
                    fetchUsuarios(currentSearchTerm, currentPage);
                } else {
                    if(loadingMessage && !(document.querySelector('.error-message') && document.querySelector('.error-message').textContent.includes("crítico")) ) { 
                        loadingMessage.textContent = "Error: Faltan componentes de la página para mostrar usuarios.";
                        loadingMessage.style.display = 'block';
                    }
                }
            <?php endif; // Fin del if (isset($pdo)) para el script JS ?>
        });
    </script>
</body>
</html>