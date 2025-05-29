<?php
session_start();

// Evitar caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.html"); // Ajusta esta ruta si es necesario
    exit();
}

// Verificar el rol del usuario y redirigir si es admin A OTRO DASHBOARD
// Si quieres que el admin también vea este index.php pero con opciones diferentes,
// esta lógica de redirección debería quitarse o modificarse.
// Por ahora, si es admin, lo manda a admin_dashboard.php
if (isset($_SESSION['rol']) && $_SESSION['rol'] == 1) {
    header("Location: admin_dashboard.php"); // Asegúrate que este archivo exista si mantienes esta lógica
    exit();
}

// Obtener el nombre del municipio del usuario para el clima
// Esto es un placeholder, idealmente vendría de la sesión o BD después del login
// si asocias un municipio al usuario o a su finca principal.
include_once 'conexion.php'; // Para $pdo
$nombre_municipio_usuario = "Ibagué"; // Valor por defecto
if(isset($pdo) && isset($_SESSION['id_usuario'])) {
    // Ejemplo: Si guardas id_municipio en la tabla usuarios
    // $stmt_mun = $pdo->prepare("SELECT m.nombre FROM usuarios u JOIN municipio m ON u.id_municipio_fk = m.id_municipio WHERE u.id_usuario = :id_user LIMIT 1");
    // $stmt_mun->bindParam(':id_user', $_SESSION['id_usuario']);
    // $stmt_mun->execute();
    // $municipio_data = $stmt_mun->fetch(PDO::FETCH_ASSOC);
    // if ($municipio_data && $municipio_data['nombre']) {
    //    $nombre_municipio_usuario = $municipio_data['nombre'];
    // }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Panel de Usuario - GAG</title>
    <link rel="stylesheet" href="../css/estilos.css"> <!-- Asegúrate que esta ruta es correcta -->
    <style>
        /* Estilos para la tarjeta del clima personalizada */
        .weather-display-card {
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            color: #333;
            margin: 10px; /* Consistente con .card si .content tiene gap */
            width: 100%;  /* Que ocupe el espacio de una tarjeta */
            max-width: 320px; /* Mismo max-width que las .card */
            min-height: 150px; /* Mismo min-height que las .card */
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-sizing: border-box;
        }
        .weather-display-card h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #0056b3; /* Azul oscuro para el título */
            font-size: 1.1em;
            width: 100%;
        }
        .weather-display-card p {
            margin: 4px 0;
            font-size: 0.85em;
        }
        .weather-display-card #clima-icono img {
            width: 50px;
            height: 50px;
        }
        .weather-display-card #clima-descripcion {
            text-transform: capitalize;
            font-weight: bold;
            margin-bottom: 8px;
        }
        /* .content ya está definido en estilos.css, no es necesario aquí si es el mismo */
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo GAG" /> <!-- Ajusta ruta -->
        </div>
        
        <!-- Botón Hamburguesa -->
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">
            ☰ <!-- Icono de hamburguesa -->
        </button>

        <nav class="menu" id="mainMenu"> <!-- Usar <nav> es más semántico y añadir ID -->
            <a href="index.php" class="active">Inicio</a>
            <a href="miscultivos.php">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario.php">Calendario</a> <!-- Cambiado a calendario_general.php -->
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="content">
        <a href="crearcultivos.php" class="card">Nuevos Cultivos</a>
        <a href="animales/crear_animales.php" class="card">Nuevos Animales</a>
        <a href="calendario.php" class="card">Ver Calendario</a>
        <a href="configuracion.php" class="card">Configuración</a>
        <a href="ayuda.php" class="card">Ayuda</a>

        <!-- Tarjeta para mostrar el Clima -->
        <div class="weather-display-card"> <!-- Puede usar la clase 'card' si quieres el fondo verde o esta clase separada -->
            <h4>Clima en <span id="clima-ciudad">Cargando...</span></h4>
            <div id="clima-icono"></div>
            <p id="clima-descripcion"></p>
            <p><strong>Temp:</strong> <span id="clima-temp">--</span> °C</p>
            <p><strong>Humedad:</strong> <span id="clima-humedad">--</span> %</p>
            <p id="clima-lluvia-pop"></p>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- LÓGICA PARA EL CLIMA ---
    const climaCiudadEl = document.getElementById('clima-ciudad');
    const climaIconoEl = document.getElementById('clima-icono');
    const climaDescripcionEl = document.getElementById('clima-descripcion');
    const climaTempEl = document.getElementById('clima-temp');
    const climaHumedadEl = document.getElementById('clima-humedad');
    const climaLluviaPopEl = document.getElementById('clima-lluvia-pop');

    // Usar la variable PHP para la ciudad. Asegúrate que $nombre_municipio_usuario esté definida y escapada.
    let ciudadParaClima = "<?php echo htmlspecialchars(addslashes($nombre_municipio_usuario . ',CO')); // Añadir ',CO' para Colombia si aplica ?>";

    function cargarClima() {
        // Asumiendo que api_clima.php está en el mismo directorio que este index.php (ambos en /php/)
        const urlApiLocal = `api_clima.php?ciudad=${encodeURIComponent(ciudadParaClima)}`;

        fetch(urlApiLocal)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errData => {
                        let errorMsg = `Error HTTP: ${response.status}`;
                        if (errData && errData.error) {
                            errorMsg = errData.error;
                        } else if (errData && errData.message) {
                            errorMsg = `API Clima: ${errData.message}`;
                        }
                        throw new Error(errorMsg);
                    }).catch(() => {
                        throw new Error(`Error HTTP: ${response.status} al contactar el servicio de clima local.`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.cod && data.cod.toString() !== "200") { // Error devuelto por OpenWeatherMap API
                    climaCiudadEl.textContent = ciudadParaClima.split(',')[0]; // Mostrar la ciudad intentada
                    climaDescripcionEl.textContent = `Error: ${data.message || 'No se pudo obtener el clima.'}`;
                    climaIconoEl.innerHTML = '';
                    climaTempEl.textContent = '--';
                    climaHumedadEl.textContent = '--';
                    climaLluviaPopEl.textContent = '';
                    return;
                }

                climaCiudadEl.textContent = data.name;
                climaIconoEl.innerHTML = `<img src="https://openweathermap.org/img/wn/${data.weather[0].icon}.png" alt="${data.weather[0].description}">`;
                climaDescripcionEl.textContent = data.weather[0].description;
                climaTempEl.textContent = data.main.temp.toFixed(1);
                climaHumedadEl.textContent = data.main.humidity;

                let lluviaInfo = "Prob. lluvia no disponible.";
                if (data.rain && data.rain['1h']) {
                    lluviaInfo = `Lluvia (1h): ${data.rain['1h']} mm`;
                } else if (data.pop !== undefined) { 
                    lluviaInfo = `Prob. lluvia: ${(data.pop * 100).toFixed(0)}%`;
                }
                climaLluviaPopEl.textContent = lluviaInfo;

            })
            .catch(error => {
                console.error('Error al cargar datos del clima:', error);
                climaCiudadEl.textContent = ciudadParaClima.split(',')[0]; // Mostrar la ciudad intentada
                climaDescripcionEl.textContent = error.message.includes("API Clima:") || error.message.includes("Error HTTP:") ? error.message : "No se pudo cargar el clima.";
                climaIconoEl.innerHTML = '';
                climaTempEl.textContent = '--';
                climaHumedadEl.textContent = '--';
                climaLluviaPopEl.textContent = '';
            });
    }

    if (climaCiudadEl) { // Solo intentar cargar el clima si los elementos existen
        cargarClima();
        // setInterval(cargarClima, 15 * 60 * 1000); // Opcional: recargar cada 15 minutos
    }

    // --- LÓGICA PARA EL MENÚ HAMBURGUESA ---
    const menuToggleBtn = document.getElementById('menuToggleBtn'); // Cambiado el ID del botón
    const mainMenu = document.getElementById('mainMenu'); // ID para el contenedor del menú

    if (menuToggleBtn && mainMenu) {
        menuToggleBtn.addEventListener('click', () => {
            mainMenu.classList.toggle('active');
            // Para accesibilidad, actualiza aria-expanded
            const isExpanded = mainMenu.classList.contains('active');
            menuToggleBtn.setAttribute('aria-expanded', isExpanded);
        });
    }
});
</script>
</body>
</html>