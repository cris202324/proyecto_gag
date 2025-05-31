<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.html"); 
    exit();
}

// Redirigir si es admin
if (isset($_SESSION['rol']) && $_SESSION['rol'] == 1) {
    header("Location: admin_dashboard.php"); 
    exit();
}

include_once 'conexion.php'; 
$nombre_municipio_usuario = "Ibagué"; // Valor por defecto
if(isset($pdo) && isset($_SESSION['id_usuario'])) {
    // Tu lógica para obtener el municipio del usuario aquí si la tienes
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Usuario - GAG</title>
    <link rel="stylesheet" href="../css/estilos.css"> <!-- TU ARCHIVO CSS GENERAL -->
    <style>
        /* Estilos generales para el cuerpo de ESTA PÁGINA si necesitas anular o añadir */
        body {
            /* font-family: Arial, sans-serif; Ya debería estar en estilos.css */
            /* margin: 0; padding: 0; Ya debería estar en estilos.css */
            background-color: #f9f9f9; /* Fondo general que tenías */
            /* font-size: 16px; Ya debería estar en estilos.css */
            color: #333;
        }

        /* Contenedor Principal de la Página */
        .page-container-user { /* Clase específica para esta página */
            max-width: 1200px;
            margin: 25px auto;
            padding: 20px;
        }
        .page-title-user { /* Clase específica para el título */
            text-align: center;
            color: #4caf50; /* Verde principal GAG */
            margin-bottom: 30px;
            font-size: 2.2em;
            font-weight: 600;
        }
        
        /* Contenedor para las tarjetas de acción */
        .action-cards-container-user { /* Clase específica */
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            justify-content: center;
        }
        
        /* Tarjetas de Acción (Enlaces) - Estilo GAG */
        .card-link-user { /* Clase específica */
            background: linear-gradient(135deg, #88c057, #6da944);
            color: white;
            border-radius: 10px;
            padding: 25px;
            width: 100%;
            max-width: 250px;
            min-height: 130px; 
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-decoration: none;
            font-weight: bold;
            font-size: 1.15em;
            transition: transform 0.25s ease-out, box-shadow 0.25s ease-out;
            cursor: pointer;
        }
        .card-link-user:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 8px 20px rgba(109, 169, 68, 0.3);
        }

        /* Tarjeta del Clima */
        .weather-display-card-user { /* Clase específica */
            padding: 20px; background-color: #ffffff; border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); color: #333;
            margin: 0; 
            width: 100%; max-width: 250px; min-height: 160px;
            display: flex; flex-direction: column; align-items: center; text-align: center;
            box-sizing: border-box;
            transition: transform 0.25s ease-out, box-shadow 0.25s ease-out;
        }
         .weather-display-card-user:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .weather-display-card-user h4 { margin-top:0;margin-bottom:12px;color:#0056b3;font-size:1.1em;width:100%; }
        .weather-display-card-user p { margin:5px 0;font-size:0.9em; }
        .weather-display-card-user #clima-icono img { width:55px;height:55px; margin-bottom:5px; }
        .weather-display-card-user #clima-descripcion { text-transform:capitalize;font-weight:bold;margin-bottom:10px; font-size:1em; }

        /* Media Queries para el contenido de esta página */
        @media (max-width: 991.98px) {
            .card-link-user, .weather-display-card-user {
                max-width: calc(50% - 12.5px); 
            }
            .page-title-user { font-size: 1.8em; }
        }

        @media (max-width: 767px) {
            .page-container-user { padding: 15px; margin-top: 15px;}
            .page-title-user { font-size: 1.6em; margin-bottom: 20px;}
            .action-cards-container-user { gap: 15px; }
            .card-link-user, .weather-display-card-user {
                max-width: 100%; 
                min-height: 110px;
                padding: 20px;
            }
            .card-link-user { font-size: 1.1em; }
        }
         @media (max-width: 480px) {
            .page-title-user { font-size: 1.5em; }
            .card-link-user { min-height: 100px; font-size: 1em; }
        }
    </style>
</head>
<body>
    <!-- SECCIÓN HEADER: Usará las clases .header, .logo, .menu, .menu-toggle de tu estilos.css -->
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo GAG" />
        </div>
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="menu" id="mainMenu">
            <a href="index.php" class="active">Inicio</a>
            <a href="miscultivos.php">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario_general.php">Calendario</a> <!-- Asegúrate que este sea el nombre correcto -->
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container-user"> <!-- Contenedor específico para esta página -->
        <h2 class="page-title-user">Bienvenido a tu Panel GAG, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</h2>
        
        <div class="action-cards-container-user"> <!-- Contenedor específico -->
            <a href="crearcultivos.php" class="card-link-user">Nuevos Cultivos</a>
            <a href="animales/crear_animales.php" class="card-link-user">Nuevos Animales</a>
            <a href="calendario_general.php" class="card-link-user">Ver Calendario</a>
            <a href="configuracion.php" class="card-link-user">Mi Configuración</a>
            <a href="ayuda.php" class="card-link-user">Ayuda y Soporte</a>

            <div class="weather-display-card-user"> <!-- Clase específica -->
                <h4>Clima en <span id="clima-ciudad">Cargando...</span></h4>
                <div id="clima-icono"></div>
                <p id="clima-descripcion"></p>
                <p><strong>Temp:</strong> <span id="clima-temp">--</span> °C</p>
                <p><strong>Humedad:</strong> <span id="clima-humedad">--</span> %</p>
                <p id="clima-lluvia-pop"></p>
            </div>
        </div> 
    </div> 

<script>
// TU JAVASCRIPT COMPLETO (Menú hamburguesa y clima)
document.addEventListener('DOMContentLoaded', function() {
    // --- LÓGICA PARA EL MENÚ HAMBURGUESA ---
    const menuToggleBtn = document.getElementById('menuToggleBtn');
    const mainMenu = document.getElementById('mainMenu');
    if (menuToggleBtn && mainMenu) {
        menuToggleBtn.addEventListener('click', () => {
            mainMenu.classList.toggle('active');
            menuToggleBtn.setAttribute('aria-expanded', mainMenu.classList.contains('active'));
        });
    }

    // --- LÓGICA PARA EL CLIMA ---
    const climaCiudadEl = document.getElementById('clima-ciudad');
    const climaIconoEl = document.getElementById('clima-icono');
    const climaDescripcionEl = document.getElementById('clima-descripcion');
    const climaTempEl = document.getElementById('clima-temp');
    const climaHumedadEl = document.getElementById('clima-humedad');
    const climaLluviaPopEl = document.getElementById('clima-lluvia-pop');

    let ciudadParaClima = "<?php echo htmlspecialchars(addslashes($nombre_municipio_usuario . ',CO')); ?>";

    function cargarClima() {
        const urlApiLocal = `api_clima.php?ciudad=${encodeURIComponent(ciudadParaClima)}`; 

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
                if (data.rain && data.rain['1h']) { lluviaInfo = `Lluvia (1h): ${data.rain['1h']} mm`; }
                else if (data.pop !== undefined) { lluviaInfo = `Prob. lluvia: ${(data.pop * 100).toFixed(0)}%`; }
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
    if(typeof cargarClima === 'function' && climaCiudadEl){
        cargarClima();
    }
});
</script>
</body>
</html>