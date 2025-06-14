<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../pages/auth/login.html"); 
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
        body {
            background-color: #f9f9f9; 
            color: #333;
             /* Asumiendo que font-family, margin, padding, font-size base vienen de estilos.css */
        }

        .page-container-user {
            max-width: 1200px;
            margin: 25px auto;
            padding: 20px;
        }
        .page-title-user {
            text-align: center;
            color: #4caf50; 
            margin-bottom: 30px;
            font-size: 2.2em;
            font-weight: 600;
        }
        
        .action-cards-container-user {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            justify-content: center; 
        }
        
        /* ESTILO UNIFICADO PARA TODAS LAS TARJETAS EN ESTA VISTA */
        .gag-card { /* Nueva clase base para todas las tarjetas aquí */
            border-radius: 10px;
            padding: 20px; /* Reducir padding para más espacio para contenido */
            width: 100%;
            max-width: 250px; 
            height: 180px; /* ALTURA FIJA PARA TODAS LAS TARJETAS */
            display: flex;
            flex-direction: column;
            justify-content: center; /* Centrar contenido verticalmente */
            align-items: center;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.25s ease-out, box-shadow 0.25s ease-out;
            cursor: pointer;
            box-sizing: border-box; /* Importante para que el padding no sume al height/width */
            overflow: hidden; /* Esconder contenido que desborde la altura fija */
        }
        .gag-card:hover {
            transform: translateY(-6px) scale(1.03);
        }

        /* Tarjetas de Acción (Enlaces) - hereda de gag-card y añade fondo */
        .card-link-user { 
            background: linear-gradient(135deg, #88c057, #6da944);
            color: white;
            font-size: 1.1em; /* Ajustar si es necesario con altura fija */
        }
        .card-link-user:hover {
            box-shadow: 0 8px 20px rgba(109, 169, 68, 0.3);
        }

        /* Tarjeta del Clima - hereda de gag-card y añade fondo */
        .weather-display-card-user { 
            background-color: #ffffff; 
            color: #333;
            font-size: 1em; /* Fuente base para contenido del clima */
        }
         .weather-display-card-user:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .weather-display-card-user h4 { 
            margin-top:0; margin-bottom:10px; color:#0056b3; 
            font-size:1.05em; width:100%; /* Reducir un poco para que quepa */
        }
        .weather-display-card-user p { 
            margin:3px 0; font-size:0.8em; /* Reducir para que quepa más info */
            line-height: 1.3; 
        }
        .weather-display-card-user #clima-icono img { 
            width:45px; height:45px; margin-bottom:3px;  /* Icono más pequeño */
        }
        .weather-display-card-user #clima-descripcion { 
            text-transform:capitalize; font-weight:bold; 
            margin-bottom:5px; font-size:0.9em; /* Descripción más pequeña */
        }

        /* Media Queries para el contenido de esta página */
        @media (max-width: 991.98px) {
            .gag-card { /* Aplicar a la clase base */
                max-width: calc(50% - 12.5px); 
                height: 170px; /* Ajustar altura si es necesario */
            }
            .page-title-user { font-size: 1.8em; }
        }

        @media (max-width: 767px) {
            .page-container-user { padding: 15px; margin-top: 15px;}
            .page-title-user { font-size: 1.6em; margin-bottom: 20px;}
            .action-cards-container-user { gap: 15px; }
            .gag-card { /* Aplicar a la clase base */
                max-width: 100%; 
                height: 130px; /* Altura ajustada para móvil */
                padding: 15px;
            }
            .card-link-user { font-size: 1em; }
            .weather-display-card-user h4 { font-size: 1em;}
            .weather-display-card-user p { font-size: 0.75em;}
            .weather-display-card-user #clima-icono img { width:40px;height:40px;}
            .weather-display-card-user #clima-descripcion { font-size:0.85em; margin-bottom:3px;}
        }
         @media (max-width: 480px) {
            .page-title-user { font-size: 1.5em; }
            .gag-card { /* Aplicar a la clase base */
                 min-height: 0; /* Quitar min-height si height es fijo */
                 height: 120px; /* Altura más pequeña para móviles muy pequeños */
                 font-size: 0.95em;
            }
            .card-link-user { font-size: 0.95em; }
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
            <a href="calendario.php">Calendario</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container-user">
        <h2 class="page-title-user">Bienvenido a tu Panel GAG, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</h2>
        
        <div class="action-cards-container-user">
            <a href="crearcultivos.php" class="gag-card card-link-user">Nuevos Cultivos</a>
            <a href="animales/crear_animales.php" class="gag-card card-link-user">Nuevos Animales</a>
            <a href="calendario.php" class="gag-card card-link-user">Ver Calendario</a>
            <a href="configuracion.php" class="gag-card card-link-user">Mi Configuración</a>
            <a href="ayuda.php" class="gag-card card-link-user">Ayuda y Soporte</a>

            <div class="gag-card weather-display-card-user">
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
    // ... (resto de tu script para el clima, igual que antes) ...
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