<?php
// Inicia la sesión de PHP para poder usar variables de sesión como $_SESSION['id_usuario'].
session_start();

// --- CABECERAS HTTP PARA EVITAR CACHÉ DEL NAVEGADOR ---
// Estas cabeceras le dicen al navegador que no guarde una copia local (caché) de esta página,
// asegurando que siempre se muestre la información más reciente.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT"); // Una fecha en el pasado.

// --- VERIFICACIÓN DE PERMISOS DE ADMINISTRADOR ---
// Comprueba si el usuario ha iniciado sesión y si su rol es de administrador (ID 1).
// Si no cumple los requisitos, es redirigido a la página de login.
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    header("Location: ../../pages/auth/login.html");
    exit(); // Detiene la ejecución del script.
}

// --- DEFINICIÓN DE VARIABLES INICIALES ---
// Se inicializan las variables que se usarán para pasar datos de PHP a JavaScript
// para la carga inicial de la página.
$termino_busqueda_inicial = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$pagina_inicial = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$mensaje_error_pagina = '';
$mensaje_exito_accion = '';

// --- MANEJO DE MENSAJES DE SESIÓN ---
// Recupera mensajes de éxito o error de acciones previas (ej. después de borrar un usuario)
// y los elimina de la sesión para que no se muestren de nuevo al recargar.
if (isset($_SESSION['mensaje_accion_usuario'])) {
    $mensaje_exito_accion = $_SESSION['mensaje_accion_usuario'];
    unset($_SESSION['mensaje_accion_usuario']);
}
if (isset($_SESSION['error_accion_usuario'])) {
    $mensaje_error_pagina = $_SESSION['error_accion_usuario'];
    unset($_SESSION['error_accion_usuario']);
}

// --- VERIFICACIÓN DE LA CONEXIÓN A LA BASE DE DATOS ---
// Se incluye el archivo de conexión.
require_once '../conexion.php';
// Se comprueba si la variable $pdo fue creada correctamente en el archivo incluido.
if (!isset($pdo)) {
    // Si no hay conexión y no hay otro error previo, se define un mensaje de error crítico.
    if (empty($mensaje_error_pagina)) {
        $mensaje_error_pagina = "Error crítico: La conexión a la base de datos no está disponible. La funcionalidad de la tabla de usuarios estará deshabilitada.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - Admin GAG</title>
    <style>
        /* --- ESTILOS CSS --- */
        /* Aquí se definen todos los estilos para la página, incluyendo el layout,
           la cabecera, el menú, la tabla, los botones, la paginación y la responsividad.
           Están diseñados para ser consistentes con el resto del panel de administración. */
        
        body{font-family:Arial,sans-serif;margin:0;padding:0;background-color:#f9f9f9;font-size:16px}
        .header{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background-color:#e0e0e0;border-bottom:2px solid #ccc;position:relative}
        .logo img{height:70px;}
        .menu{display:flex;align-items:center}
        .menu a{margin:0 5px;text-decoration:none;color:#000;padding:8px 12px;border:1px solid #ccc;border-radius:5px;white-space:nowrap;font-size:.9em;transition:background-color .3s, color .3s}
        .menu a.active,.menu a:hover{background-color:#88c057;color:#fff!important;border-color:#70a845}
        .menu a.exit{background-color:#ff4d4d;color:#fff!important;border:1px solid #c00}
        .menu a.exit:hover{background-color:#c00;color:#fff!important}
        .menu-toggle{display:none;background:0 0;border:none;font-size:1.8rem;color:#333;cursor:pointer;padding:5px}
        
        .page-container{max-width:1100px;margin:20px auto;padding:20px}
        .page-container > h2.page-title{text-align:center;color:#4caf50;margin-bottom:25px;font-size:1.8em}
        
        .controles-tabla{margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
        .search-form {display:flex; flex-grow:1; max-width:400px; }
        .search-form input[type="text"]{padding:8px 10px;border:1px solid #ccc;border-radius:5px;font-size:.9em;flex-grow:1;}
        .btn-generar-reporte {padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 0.9em; text-decoration: none; font-weight: bold; transition: background-color 0.3s;}
        .btn-generar-reporte:hover {background-color: #0056b3;}

        #tablaUsuariosContainer .tabla-datos {width:100%;border-collapse:collapse;margin-bottom:0;background-color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.1);border-radius:8px;overflow:hidden}
        #tablaUsuariosContainer .tabla-datos th, #tablaUsuariosContainer .tabla-datos td {border-bottom:1px solid #ddd;padding:10px;text-align:left;font-size:.85em;word-break:break-word}
        #tablaUsuariosContainer .tabla-datos th {background-color:#f2f2f2;color:#333;font-weight:700;border-top:1px solid #ddd}
        #tablaUsuariosContainer .tabla-datos .acciones a {display:inline-block;padding:5px 8px;margin-right:5px;margin-bottom:5px;font-size:0.8em;text-decoration:none;color:white;border-radius:4px;border:none;cursor:pointer;transition: background-color 0.2s ease;}
        #tablaUsuariosContainer .tabla-datos .acciones .btn-editar {background-color:#3498db;}
        #tablaUsuariosContainer .tabla-datos .acciones .btn-borrar {background-color:#e74c3c;}
        
        #paginacionContainer .paginacion{text-align:center;margin-top:20px;padding-bottom:20px}
        #paginacionContainer .paginacion a,#paginacionContainer .paginacion span{display:inline-block;padding:8px 14px;margin:0 4px;border:1px solid #ccc;border-radius:4px;text-decoration:none;color:#4caf50;font-size:.9em}
        #paginacionContainer .paginacion a:hover{background-color:#e8f5e9;}
        #paginacionContainer .paginacion span.actual{background-color:#4caf50;color:#fff;border-color:#43a047;font-weight:700}
        #paginacionContainer .paginacion span.disabled{color:#aaa;border-color:#ddd;}
        
        .no-datos {text-align:center;padding:30px;font-size:1.2em;color:#777}
        .error-message, .success-message {text-align:center;padding:15px;border-radius:5px;margin-bottom:20px;font-size:0.9em;}
        .error-message {color:#d8000c;background-color:#ffdddd;border:1px solid #ffcccc;}
        .success-message {color:#270;background-color:#DFF2BF;border:1px solid #4F8A10;}
        #loadingMessage {text-align:center; font-style:italic; color:#777; padding:20px; display:none;}

        @media (max-width:991.98px){
            .menu-toggle{display:block}
            .menu{display:none;flex-direction:column;align-items:stretch;position:absolute;top:100%;left:0;width:100%;background-color:#e9e9e9;padding:0;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000;border-top:1px solid #ccc}
            .menu.active{display:flex}
            .menu a{margin:0;padding:15px 20px;width:100%;text-align:left;border:none;border-bottom:1px solid #d0d0d0;border-radius:0;color:#333}
        }
        @media (max-width:768px){
            .controles-tabla {flex-direction:column; align-items:stretch;}
            .search-form {max-width:none; margin-bottom:10px;} 
            .btn-generar-reporte {width:100%; text-align:center; box-sizing: border-box;}
            #tablaUsuariosContainer .tabla-datos{display:block;overflow-x:auto;white-space:nowrap}
        }
    </style>
</head>
<body>
    <!-- --- ESTRUCTURA HTML DE LA PÁGINA --- -->

    <!-- Cabecera con logo y menú de navegación -->
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="admin_dashboard.php">Inicio Admin</a> 
            <a href="view_users.php" class="active">Ver Usuarios</a>
            <a href="view_all_crops.php">Ver Cultivos</a>
            <a href="admin_manage_trat_pred.php">Tratamientos Pred.</a>
            <a href="view_all_animals.php">Ver Animales</a> 
            <a href="manage_tickets.php">Gestionar Tickets</a>
            <a href="../cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <!-- Contenido principal de la página -->
    <div class="page-container">
        <h2 class="page-title">Lista de Usuarios (No Administradores)</h2>

        <?php 
        // Se muestran los mensajes de éxito o error si existen.
        if (!empty($mensaje_exito_accion)) {
            echo '<p class="success-message">' . htmlspecialchars($mensaje_exito_accion) . '</p>';
        }
        if (!empty($mensaje_error_pagina)) { 
            echo '<p class="error-message">' . htmlspecialchars($mensaje_error_pagina) . '</p>';
        }
        ?>

        <!-- Solo se muestran los controles y la tabla si la conexión a la BD es exitosa -->
        <?php if (isset($pdo) && empty($mensaje_error_pagina) || (isset($pdo) && !strpos($mensaje_error_pagina, "crítico")) ): ?>
            <div class="controles-tabla">
                <!-- Formulario de búsqueda -->
                <form id="searchForm" class="search-form" onsubmit="return false;"> 
                    <input type="text" id="searchInput" name="buscar" placeholder="Buscar por nombre o email..." value="<?php echo htmlspecialchars($termino_busqueda_inicial); ?>">
                </form>
                <!-- Botón para generar el reporte Excel -->
                <div>
                    <a href="#" id="btnGenerarReporteUsuarios" class="btn-generar-reporte">
                        Generar Reporte Excel
                    </a>
                </div>
            </div>

            <!-- Contenedores donde AJAX cargará dinámicamente el contenido -->
            <div id="tablaUsuariosContainer">
                <p id="loadingMessage">Cargando usuarios...</p>
            </div>
            <div id="paginacionContainer">
                <!-- La paginación se cargará aquí con AJAX -->
            </div>
        <?php endif; ?>
    </div>

    <!-- --- SCRIPT JAVASCRIPT PARA LA INTERACTIVIDAD (AJAX) --- -->
    <script>
        // Se ejecuta cuando el contenido HTML de la página ha sido completamente cargado.
        document.addEventListener('DOMContentLoaded', function() {
            // Lógica para el menú hamburguesa
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');
            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
                });
            }

            // Se ejecuta el script AJAX solo si la conexión a la BD no fue un error crítico.
            <?php if (isset($pdo) && empty($mensaje_error_pagina) || (isset($pdo) && !strpos($mensaje_error_pagina, "crítico"))): ?>
                
                // Obtención de elementos del DOM para manipularlos con JavaScript.
                const searchInput = document.getElementById('searchInput');
                const tablaContainer = document.getElementById('tablaUsuariosContainer');
                const paginacionContainer = document.getElementById('paginacionContainer');
                const loadingMessage = document.getElementById('loadingMessage');
                const btnGenerarReporte = document.getElementById('btnGenerarReporteUsuarios');
                
                // Variables para mantener el estado actual de la búsqueda y paginación.
                let currentPage = <?php echo $pagina_inicial; ?>;
                let currentSearchTerm = searchInput ? searchInput.value : "<?php echo htmlspecialchars(addslashes($termino_busqueda_inicial)); ?>"; 
                let searchTimeout; // Para controlar el tiempo de espera antes de buscar.

                // Función principal que hace la petición AJAX para obtener los usuarios.
                function fetchUsuarios(termino = '', pagina = 1) {
                    // Muestra el mensaje de "cargando" y limpia los contenedores.
                    if(loadingMessage) loadingMessage.style.display = 'block';
                    if(tablaContainer) tablaContainer.innerHTML = ''; 
                    if(paginacionContainer) paginacionContainer.innerHTML = ''; 
                    
                    // Construye la URL para la petición AJAX.
                    const url = `ajax_buscar_usuarios.php?buscar=${encodeURIComponent(termino)}&pagina=${pagina}`;
                    
                    // `fetch` realiza la petición al servidor.
                    fetch(url)
                        .then(response => {
                            if (!response.ok) { throw new Error(`Error HTTP: ${response.status}`); }
                            return response.json(); // Convierte la respuesta del servidor (que es JSON) a un objeto JavaScript.
                        })
                        .then(data => { // `data` es el objeto JavaScript con la tabla y la paginación.
                            if(loadingMessage) loadingMessage.style.display = 'none';
                            if (data.error) {
                                if(tablaContainer) tablaContainer.innerHTML = `<p class="error-message">${data.error}</p>`;
                            } else {
                                // Inserta el HTML recibido en los contenedores correspondientes.
                                if(tablaContainer) tablaContainer.innerHTML = data.tabla;
                                if(paginacionContainer) paginacionContainer.innerHTML = data.paginacion;
                                
                                // Actualiza el estado actual.
                                currentPage = pagina; 
                                currentSearchTerm = termino; 
                                
                                // Actualiza la URL del navegador sin recargar la página.
                                updateURL(termino, pagina);
                                
                                // Vuelve a añadir los "event listeners" a los nuevos elementos cargados.
                                addPaginacionListeners(); 
                                addDeleteConfirmations(); 
                            }
                        })
                        .catch(error => { // Manejo de errores en la petición AJAX.
                            if(loadingMessage) loadingMessage.style.display = 'none';
                            console.error('Error en fetchUsuarios:', error);
                            if(tablaContainer) tablaContainer.innerHTML = `<p class="error-message">Error al cargar los datos: ${error.message}. Intente más tarde.</p>`;
                        });
                }

                // Función para actualizar la URL del navegador.
                function updateURL(termino, pagina) { 
                    const baseUrl = window.location.pathname; 
                    const params = new URLSearchParams();
                    if (termino) { params.append('buscar', termino); }
                    if (pagina > 1) { params.append('pagina', pagina); }
                    const newURL = params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
                    // `history.pushState` cambia la URL sin recargar la página.
                    window.history.pushState({path: newURL}, '', newURL);
                }
                
                // Añade "event listeners" a los enlaces de paginación cargados por AJAX.
                function addPaginacionListeners() { 
                    if (!paginacionContainer) return;
                    const paginacionLinks = paginacionContainer.querySelectorAll('.paginacion a');
                    paginacionLinks.forEach(link => {
                        link.addEventListener('click', function(e) {
                            e.preventDefault(); // Evita que el enlace recargue la página.
                            const targetPage = this.dataset.pagina; // Obtiene el número de página del atributo 'data-pagina'.
                            fetchUsuarios(currentSearchTerm, targetPage); 
                        });
                    });
                 }

                // Añade "event listeners" a los botones de borrado para mostrar un mensaje de confirmación.
                function addDeleteConfirmations() {
                    if (!tablaContainer) return;
                    const deleteLinks = tablaContainer.querySelectorAll('.btn-borrar');
                    deleteLinks.forEach(link => {
                        link.removeEventListener('click', handleDeleteConfirm); 
                        link.addEventListener('click', handleDeleteConfirm);
                    });
                }
                
                // Función que muestra el `confirm()` de JavaScript.
                function handleDeleteConfirm(event) {
                    const userNameElement = this.closest('tr').querySelector('td:nth-child(2)');
                    const userName = userNameElement ? userNameElement.textContent : 'este usuario';
                    if (!confirm(`¿Estás realmente seguro de que deseas eliminar a ${userName}? Esta acción no se puede deshacer.`)) {
                        event.preventDefault(); // Cancela la acción del enlace si el usuario hace clic en "Cancelar".
                    }
                 }

                // Añade el "event listener" al botón de generar reporte.
                if (btnGenerarReporte) {
                    btnGenerarReporte.addEventListener('click', function(e) {
                        e.preventDefault();
                        // Obtiene el término de búsqueda actual para pasarlo al script de reporte.
                        const terminoActualParaReporte = searchInput ? searchInput.value : '';
                        let urlReporte = 'generar_reporte_usuarios.php';
                        if (terminoActualParaReporte) {
                            urlReporte += '?buscar=' + encodeURIComponent(terminoActualParaReporte);
                        }
                        // Abre el reporte en una nueva pestaña.
                        window.open(urlReporte, '_blank');
                    });
                }

                // Lógica de inicio de la carga de datos.
                if (searchInput && tablaContainer && paginacionContainer && loadingMessage) {
                    // Escucha el evento 'input' en el campo de búsqueda.
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout); // Cancela búsquedas anteriores para no sobrecargar el servidor.
                        const searchTerm = this.value;
                        // Espera 300 milisegundos después de que el usuario deja de teclear para buscar.
                        searchTimeout = setTimeout(() => {
                            fetchUsuarios(searchTerm, 1); // Busca siempre desde la página 1.
                        }, 300);
                    });
                    // Carga inicial de usuarios al cargar la página.
                    fetchUsuarios(currentSearchTerm, currentPage);
                } else {
                    // Muestra un mensaje si los contenedores principales no se encuentran en la página.
                    if(loadingMessage && !(document.querySelector('.error-message') && document.querySelector('.error-message').textContent.includes("crítico")) ) { 
                        loadingMessage.textContent = "Error: Faltan componentes de la página para mostrar usuarios.";
                        loadingMessage.style.display = 'block';
                    }
                }
            <?php endif; // Fin del if (isset($pdo)) ?>
        });
    </script>
</body>
</html>