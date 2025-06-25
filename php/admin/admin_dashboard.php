<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../pages/auth/login.html"); 
    exit();
}

// Verificar si el usuario es admin (id_rol = 1)
require_once '../conexion.php'; 
if (!isset($pdo)) {
    // Ajusta la ruta a conexion.php si es necesario.
    die("Error crítico: No se pudo establecer la conexión a la base de datos.");
}

$stmt_rol_check = $pdo->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = :id_usuario");
$stmt_rol_check->bindParam(':id_usuario', $_SESSION['id_usuario']);
$stmt_rol_check->execute();
$rol_del_usuario_logueado = $stmt_rol_check->fetchColumn();

if ($rol_del_usuario_logueado != 1) {
    // Si no es admin, redirigir a la página principal de usuario o login
    header("Location: ../index.php"); // Ajusta esta ruta a la página de inicio de usuarios no admin
    exit();
}

// Obtener estadísticas básicas
$total_usuarios_no_admin = 0;
$total_cultivos = 0;
$total_animales = 0;
$total_tickets_abiertos = 0;
$stats_error_message = '';

try {
    $total_usuarios_no_admin = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id_rol = 2")->fetchColumn();
    $total_cultivos = $pdo->query("SELECT COUNT(*) FROM cultivos")->fetchColumn();
    $total_animales = $pdo->query("SELECT COUNT(*) FROM animales")->fetchColumn();
    $total_tickets_abiertos = $pdo->query("SELECT COUNT(*) FROM tickets_soporte WHERE estado_ticket = 'Abierto'")->fetchColumn();
} catch (PDOException $e) {
    $stats_error_message = "Error al cargar estadísticas: " . $e->getMessage();
    // Podrías loguear el error aquí: error_log("Error estadísticas dashboard: " . $e->getMessage());
}

$nombre_municipio_para_clima = "Ibagué"; // Puedes hacerlo configurable más adelante
// Intenta obtener el nombre del administrador de la sesión
$admin_nombre = $_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Administrador'; 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - GAG</title>
    <style>
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
        .page-title { text-align: center; color: #4caf50; margin-bottom: 30px; font-size: 2em; }
        
        .dashboard-section { width: 100%; margin-bottom: 35px; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        .dashboard-section h3.section-title { color: #333; font-size: 1.5em; margin-top: 0; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #88c057; }
        
        .cards-container { display: flex; flex-wrap: wrap; gap: 20px; justify-content: flex-start; }
        
        .card-action-link { 
            background: linear-gradient(to bottom, #88c057, #6da944); 
            color: white !important; 
            border-radius: 8px; padding: 20px; 
            width: 100%; max-width: 220px; 
            min-height: 100px; 
            display: flex; flex-direction: column; 
            justify-content: center; align-items: center; text-align: center; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
            text-decoration: none; font-weight: bold; font-size: 1.05em; 
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out; 
            cursor: pointer; 
            box-sizing: border-box;
        }
        .card-action-link:hover { transform: translateY(-5px); box-shadow: 0 6px 12px rgba(0,0,0,0.15); }
        
        .stat-card { 
            background: linear-gradient(to right, #6AB44A, #4A8C30); 
            color: white; border-radius: 8px; padding: 20px; 
            width: 100%; max-width: 220px; 
            min-height: 130px; 
            display: flex; flex-direction: column; 
            justify-content: center; align-items: center; text-align: center; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
            transition: transform 0.3s ease; 
            box-sizing: border-box;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .card-text { font-size: 0.95em; font-weight: 500; margin-bottom: 8px; opacity: 0.9; }
        .stat-card .card-number { font-size: 2.4em; font-weight: bold; line-height: 1.1; }
        
        .weather-display-card { 
            padding: 15px; background-color: #fff; border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); color: #333; 
            margin: 0; width: 100%; max-width: 220px; 
            min-height: 150px; 
            display: flex; flex-direction: column; align-items: center; text-align: center; 
            box-sizing: border-box;
        }
        .weather-display-card h4 { margin-top:0;margin-bottom:10px;color:#0056b3;font-size:1.1em;width:100%; }
        .weather-display-card p { margin:4px 0;font-size:0.85em; }
        .weather-display-card #clima-icono img { width:50px;height:50px; }
        .weather-display-card #clima-descripcion { text-transform:capitalize;font-weight:bold;margin-bottom:8px; }
        
        .error-message { color: #d8000c; background-color: #ffdddd; border:1px solid #ffcccc; padding:10px; border-radius:5px; text-align:center; margin-bottom:15px; }
        
        @media (max-width: 991.98px) { 
            .menu-toggle { display: block; } 
            .menu { display: none; flex-direction: column; align-items: stretch; position: absolute; top: 100%; left: 0; width: 100%; background-color: #e9e9e9; padding: 0; box-shadow: 0 4px 8px rgba(0,0,0,.1); z-index: 1000; border-top: 1px solid #ccc; } 
            .menu.active { display: flex; } 
            .menu a { margin:0; padding:15px 20px; width:100%; text-align:left; border:none; border-bottom:1px solid #d0d0d0; border-radius:0; color:#333; } 
            .menu a:last-child { border-bottom: none; } 
            .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; } 
            .menu a.exit, .menu a.exit:hover { background-color: #ff4d4d; color: white !important; } 
            .card-action-link, .stat-card, .weather-display-card { max-width: calc(50% - 10px); } 
        }
        @media (max-width: 767px) { 
            .logo img { height: 60px; } 
            .page-title { font-size: 1.6em; } 
            .dashboard-section h3.section-title { font-size: 1.3em; } 
            .card-action-link, .stat-card, .weather-display-card { max-width: 100%; min-height: 100px; } 
            .stat-card .card-number { font-size: 2em; } 
        }
        @media (max-width: 480px) { 
            .logo img { height: 50px; } 
            .menu-toggle { font-size: 1.6rem; } 
            .page-title { font-size: 1.4em; } 
            .dashboard-section h3.section-title { font-size: 1.2em; } 
            .card-action-link { font-size: 1em; } 
            .stat-card .card-text { font-size: 0.9em; } 
            .stat-card .card-number { font-size: 1.8em; } 
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../../img/logo.png" alt="Logo GAG" /> <!-- Ajusta la ruta a tu logo -->
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
        <h2 class="page-title">Panel de Administración GAG - ¡Bienvenido, <?php echo htmlspecialchars($admin_nombre); ?>!</h2>
        
        <?php if (!empty($stats_error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($stats_error_message); ?></p>
        <?php endif; ?>

        <section class="dashboard-section">
            <h3 class="section-title">Estadísticas Rápidas y Clima</h3>
            <div class="cards-container">
                <div class="stat-card">
                    <div class="card-text">Usuarios Registrados</div>
                    <div class="card-number"><?php echo htmlspecialchars($total_usuarios_no_admin); ?></div>
                </div>
                <div class="stat-card">
                    <div class="card-text">Cultivos Totales</div>
                    <div class="card-number"><?php echo htmlspecialchars($total_cultivos); ?></div>
                </div>
                <div class="stat-card">
                    <div class="card-text">Animales Totales</div>
                    <div class="card-number"><?php echo htmlspecialchars($total_animales); ?></div>
                </div>
                 <div class="stat-card">
                    <div class="card-text">Tickets Abiertos</div>
                    <div class="card-number"><?php echo htmlspecialchars($total_tickets_abiertos); ?></div>
                </div>
                 <div class="weather-display-card">
                    <h4>Clima en <span id="clima-ciudad">Cargando...</span></h4>
                    <div id="clima-icono"></div>
                    <p id="clima-descripcion"></p>
                    <p><strong>Temp:</strong> <span id="clima-temp">--</span> °C</p>
                    <p><strong>Humedad:</strong> <span id="clima-humedad">--</span> %</p>
                    <p id="clima-lluvia-pop"></p>
                </div>
            </div>
        </section>

        <section class="dashboard-section">
            <h3 class="section-title">Gestión General</h3>
            <div class="cards-container">
                <a href="view_users.php" class="card-action-link">Ver Usuarios</a>
                <a href="manage_tickets.php" class="card-action-link">Gestionar Tickets Soporte</a>
                <!-- El reporte general se mueve a su propia sección abajo -->
            </div>
        </section>

        <section class="dashboard-section">
            <h3 class="section-title">Gestión Agrícola</h3>
            <div class="cards-container">
                <a href="view_all_crops.php" class="card-action-link">Ver Todos los Cultivos</a>
                <a href="admin_manage_trat_pred.php" class="card-action-link">Gestionar Tratamientos Predeterminados</a> <!-- BOTÓN/TARJETA AÑADIDO AQUÍ -->
            </div>
        </section>

        <section class="dashboard-section">
            <h3 class="section-title">Gestión Ganadera</h3>
            <div class="cards-container">
                <a href="view_all_animals.php" class="card-action-link">Ver Todos los Animales</a>
                <!-- Podrías añadir aquí "Gestionar Tipos de Medicamento/Vacuna" si creas esa interfaz -->
            </div>
        </section>
        
        <section class="dashboard-section">
            <h3 class="section-title">Reportes</h3>
            <div class="cards-container">
                 <a href="generar_reporte_excel.php" class="card-action-link" target="_blank">
                    Generar Reporte General (Excel)
                </a>
            </div>
        </section>

    </div> 

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Menú Hamburguesa
        const menuToggleBtn = document.getElementById('menuToggleBtn');
        const mainMenu = document.getElementById('mainMenu');
        if (menuToggleBtn && mainMenu) {
            menuToggleBtn.addEventListener('click', () => {
                mainMenu.classList.toggle('active');
                menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
            });
        }

        // Lógica del Clima
        const climaCiudadEl = document.getElementById('clima-ciudad');
        const climaIconoEl = document.getElementById('clima-icono');
        const climaDescripcionEl = document.getElementById('clima-descripcion');
        const climaTempEl = document.getElementById('clima-temp');
        const climaHumedadEl = document.getElementById('clima-humedad');
        const climaLluviaPopEl = document.getElementById('clima-lluvia-pop');

        let ciudadParaClima = "<?php echo htmlspecialchars(addslashes($nombre_municipio_para_clima . ',CO')); ?>"; 

        function cargarClima() {
            const urlApiLocal = `../api_clima.php?ciudad=${encodeURIComponent(ciudadParaClima)}`; 

            fetch(urlApiLocal)
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errData => {
                            let errorMsg = `Error HTTP: ${response.status}`;
                            if (errData && errData.error) { errorMsg = errData.error; }
                            else if (errData && errData.message) { errorMsg = `API Clima: ${errData.message}`; }
                            throw new Error(errorMsg);
                        }).catch(() => { 
                            throw new Error(`Error HTTP: ${response.status} al contactar API clima local.`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.cod && data.cod.toString() !== "200") { 
                        if(climaCiudadEl) climaCiudadEl.textContent = ciudadParaClima.split(',')[0];
                        if(climaDescripcionEl) climaDescripcionEl.textContent = `Error: ${data.message || 'No se pudo obtener el clima.'}`;
                        if(climaIconoEl) climaIconoEl.innerHTML = '';
                        if(climaTempEl) climaTempEl.textContent = '--';
                        if(climaHumedadEl) climaHumedadEl.textContent = '--';
                        if(climaLluviaPopEl) climaLluviaPopEl.textContent = '';
                        return;
                    }
                    if(climaCiudadEl) climaCiudadEl.textContent = data.name;
                    if(climaIconoEl) climaIconoEl.innerHTML = `<img src="https://openweathermap.org/img/wn/${data.weather[0].icon}.png" alt="${data.weather[0].description}">`;
                    if(climaDescripcionEl) climaDescripcionEl.textContent = data.weather[0].description;
                    if(climaTempEl) climaTempEl.textContent = data.main.temp.toFixed(1);
                    if(climaHumedadEl) climaHumedadEl.textContent = data.main.humidity;

                    let lluviaInfo = "Prob. lluvia no disponible.";
                    if (data.rain && data.rain['1h']) { 
                        lluviaInfo = `Lluvia (1h): ${data.rain['1h']} mm`;
                    } else if (data.pop !== undefined) { 
                        lluviaInfo = `Prob. lluvia: ${(data.pop * 100).toFixed(0)}%`;
                    }
                    if(climaLluviaPopEl) climaLluviaPopEl.textContent = lluviaInfo;
                })
                .catch(error => {
                    console.error('Error al cargar datos del clima:', error);
                    if(climaCiudadEl) climaCiudadEl.textContent = ciudadParaClima.split(',')[0];
                    if(climaDescripcionEl) climaDescripcionEl.textContent = error.message.includes("API Clima:") || error.message.includes("Error HTTP:") ? error.message : "No se pudo cargar el clima.";
                    if(climaIconoEl) climaIconoEl.innerHTML = '';
                    if(climaTempEl) climaTempEl.textContent = '--';
                    if(climaHumedadEl) climaHumedadEl.textContent = '--';
                    if(climaLluviaPopEl) climaLluviaPopEl.textContent = '';
                });
        }
        if(typeof cargarClima === 'function' && document.getElementById('clima-ciudad')){ 
            cargarClima();
        }
    });
    </script>
</body>
</html>