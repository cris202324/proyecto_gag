<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.html"); 
    exit();
}

include 'conexion.php'; // $pdo
$id_usuario_actual = $_SESSION['id_usuario'];
$eventos_calendario = [];
$mensaje_error = '';

// ... (tu lógica PHP existente para obtener $eventos_calendario y $mensaje_error) ...
// La lógica PHP no necesita cambios para el menú sándwich.
// Asegúrate que esta lógica esté completa aquí:
if (!isset($pdo)) {
    $mensaje_error = "Error crítico: La conexión a la base de datos no está disponible.";
} else {
    try {
        // 1. Obtener todos los cultivos activos del usuario
        $sql_cultivos = "SELECT c.id_cultivo, tc.nombre_cultivo, c.fecha_inicio
                         FROM cultivos c
                         JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                         WHERE c.id_usuario = :id_usuario";
        $stmt_cultivos = $pdo->prepare($sql_cultivos);
        $stmt_cultivos->bindParam(':id_usuario', $id_usuario_actual);
        $stmt_cultivos->execute();
        $cultivos = $stmt_cultivos->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cultivos as $cultivo) {
            // 2. Obtener tratamientos para cada cultivo
            $sql_tratamientos = "SELECT tipo_tratamiento, producto_usado, fecha_aplicacion_estimada
                                 FROM tratamiento_cultivo
                                 WHERE id_cultivo = :id_cultivo AND fecha_aplicacion_estimada IS NOT NULL";
            $stmt_tratamientos = $pdo->prepare($sql_tratamientos);
            $stmt_tratamientos->bindParam(':id_cultivo', $cultivo['id_cultivo']);
            $stmt_tratamientos->execute();
            $tratamientos = $stmt_tratamientos->fetchAll(PDO::FETCH_ASSOC);

            foreach ($tratamientos as $trat) {
                $eventos_calendario[] = [
                    'title' => $trat['tipo_tratamiento'] . " (" . htmlspecialchars($cultivo['nombre_cultivo']) . ")",
                    'start' => $trat['fecha_aplicacion_estimada'],
                    'description' => $trat['producto_usado'] . " para " . htmlspecialchars($cultivo['nombre_cultivo']),
                    'className' => 'evento-tratamiento',
                    'cultivo_id' => $cultivo['id_cultivo']
                ];
            }

            // 3. Calcular próximo riego para cada cultivo (simplificado)
            $sql_riego = "SELECT frecuencia_riego, fecha_ultimo_riego
                          FROM riego
                          WHERE id_cultivo = :id_cultivo
                          ORDER BY fecha_ultimo_riego DESC LIMIT 1";
            $stmt_riego = $pdo->prepare($sql_riego);
            $stmt_riego->bindParam(':id_cultivo', $cultivo['id_cultivo']);
            $stmt_riego->execute();
            $ultimo_riego = $stmt_riego->fetch(PDO::FETCH_ASSOC);

            if ($ultimo_riego && $ultimo_riego['fecha_ultimo_riego']) {
                $proximo_riego_estimado = null;
                try {
                    $fechaUltRiegoObj = new DateTime($ultimo_riego['fecha_ultimo_riego']);
                    $frecuencia = strtolower($ultimo_riego['frecuencia_riego']);
                    $intervalo = null;

                    if (strpos($frecuencia, 'diario') !== false) {
                        $intervalo = 'P1D';
                    } elseif (preg_match('/cada (\d+) d[ií]as/', $frecuencia, $matches)) {
                        $intervalo = 'P' . $matches[1] . 'D';
                    } elseif (strpos($frecuencia, 'semanal') !== false) {
                        $intervalo = 'P7D';
                    }

                    if ($intervalo) {
                        $fechaUltRiegoObj->add(new DateInterval($intervalo));
                        $proximo_riego_estimado = $fechaUltRiegoObj->format('Y-m-d');
                        
                        $hoy = new DateTime();
                        $hoy->setTime(0,0,0); 
                        $fechaProximoRiegoComparar = clone $fechaUltRiegoObj;
                        $fechaProximoRiegoComparar->setTime(0,0,0);
                        $diff = $hoy->diff($fechaProximoRiegoComparar);

                        if ($fechaProximoRiegoComparar >= $hoy || ($diff->invert && $diff->days < 7)) {
                            $eventos_calendario[] = [
                                'title' => "Riego Estimado (" . htmlspecialchars($cultivo['nombre_cultivo']) . ")",
                                'start' => $proximo_riego_estimado,
                                'description' => "Según frecuencia: " . htmlspecialchars($ultimo_riego['frecuencia_riego']),
                                'className' => 'evento-riego',
                                'cultivo_id' => $cultivo['id_cultivo']
                            ];
                        }
                    }
                } catch (Exception $e) {
                    // error_log("Error calculando próximo riego para cultivo " . $cultivo['id_cultivo'] . ": " . $e->getMessage());
                }
            }
        }
    } catch (PDOException $e) {
        $mensaje_error = "Error al cargar datos para el calendario: " . $e->getMessage();
        // error_log($mensaje_error);
    }
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
    <title>Calendario General de Actividades - GAG</title>
    <link rel="stylesheet" href="../css/estilos.css"> <!-- Ruta al CSS general -->
    <style>
        /* Estilos generales del cuerpo (si no están en estilos.css global o quieres anular) */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            font-size: 16px; /* Mantener consistencia con estilos.css */
        }

        /* Cabecera (Tomado de tu estilos.css general) */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            background-color: #e0e0e0;
            border-bottom: 2px solid #ccc;
            position: relative; /* Para el menú sándwich */
        }

        .logo img {
            height: 70px; /* Ajustado para que no sea tan grande */
            transition: height 0.3s ease;
        }

        .menu {
            display: flex;
            align-items: center;
        }

        .menu a {
            margin: 0 5px;
            text-decoration: none;
            color: black;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            transition: background-color 0.3s, color 0.3s, padding 0.3s ease;
            white-space: nowrap;
            font-size: 0.9em;
        }

        .menu a.active,
        .menu a:hover {
            background-color: #88c057;
            color: white !important;
            border-color: #70a845;
        }

        .menu a.exit {
            background-color: #ff4d4d;
            color: white !important;
            border: 1px solid #cc0000;
        }
        .menu a.exit:hover {
            background-color: #cc0000;
            color: white !important;
        }

        /* Botón del menú hamburguesa (tomado de estilos.css) */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.8rem;
            color: #333;
            cursor: pointer;
            padding: 5px;
        }

        /* Contenedor principal de la página */
        .page-container {
            max-width: 1100px;
            margin: 20px auto;
            padding: 15px;
        }
        .page-container > h2.page-title {
            text-align: center;
            color: #4caf50;
            margin-bottom: 25px;
            font-size: 1.8em;
        }

        /* Estilos específicos del calendario */
        .calendario-wrapper {
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        }
        .calendario-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .calendario-header button {
            background-color: #f0f0f0; color: #333; border: 1px solid #ccc;
            padding: 8px 15px; cursor: pointer; border-radius: 5px; font-size: 0.9em;
            transition: background-color 0.3s ease, box-shadow 0.2s ease;
        }
        .calendario-header button:hover { background-color: #e0e0e0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .calendario-header h3 { margin: 0; font-size: 1.5em; color: #4caf50; font-weight: bold; text-align: center; flex-grow: 1; }
        .calendario-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background-color: #dddddd; border: 1px solid #dddddd; border-radius: 5px; overflow: hidden; }
        .dia-semana, .dia-calendario { background-color: #ffffff; padding: 8px 4px; text-align: center; min-height: 80px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; font-size: 0.85em; position: relative; border: none; }
        .dia-semana { font-weight: bold; background-color: #f5f5f5; color: #555; min-height: auto; padding: 10px 5px; font-size: 0.8em; text-transform: uppercase; }
        .dia-numero { font-weight: bold; margin-bottom: 6px; font-size: 1em; color: #444; width: 26px; height: 26px; line-height: 26px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        .dia-calendario.otro-mes .dia-numero { color: #b0b0b0; opacity: 0.6; }
        .dia-calendario.hoy .dia-numero { background-color: #4caf50; color: white; }
        .evento-calendario { font-size: 0.75em; padding: 2px 4px; border-radius: 3px; margin-top: 3px; width: 95%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border: 1px solid transparent; text-align: left; cursor: default; box-shadow: 0 1px 1px rgba(0,0,0,0.05); }
        .evento-tratamiento { border-left: 3px solid #6da944; background-color: #e8f5e9; color: #387002; }
        .evento-riego { border-left: 3px solid #81c784; background-color: #e6fff0; color: #2e7d32; }
        .leyenda { margin-top: 25px; padding-top: 15px; border-top: 1px solid #e0e0e0; }
        .leyenda h4 { margin-top: 0; margin-bottom: 12px; color: #333; font-size: 1.05em; font-weight: bold; }
        .leyenda-item { display: flex; align-items: center; margin-bottom: 8px; font-size: 0.9em; color: #555; }
        .leyenda-color { width: 16px; height: 16px; margin-right: 10px; border-radius: 3px; border: 1px solid rgba(0,0,0,0.1); }
        .error-message-calendar { background-color: #ffdddd; border: 1px solid #ffcccc; color: #d8000c; padding: 10px; border-radius: 5px; text-align:center; margin-bottom: 20px; }

        /* --- MEDIA QUERIES (incluyendo para el menú sándwich) --- */
        @media (max-width: 991px) { /* Breakpoint donde aparece el sándwich */
            .menu-toggle {
                display: block; /* Mostrar el botón hamburguesa */
            }
            .menu {
                display: none; /* Ocultar el menú normal */
                flex-direction: column;
                align-items: stretch;
                position: absolute;
                top: 100%; 
                left: 0;
                width: 100%;
                background-color: #e9e9e9; 
                padding: 0;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                z-index: 1000;
                border-top: 1px solid #ccc;
            }
            .menu.active { display: flex; }
            .menu a {
                margin: 0; padding: 15px 20px; width: 100%; text-align: left;
                border: none; border-bottom: 1px solid #d0d0d0; border-radius: 0;
                color: #333;
            }
            .menu a:last-child { border-bottom: none; }
            .menu a.active, .menu a:hover { background-color: #88c057; color: white !important; border-color: transparent; }
            .menu a.exit, .menu a.exit:hover { background-color: #ff4d4d; color: white !important; }

            /* Ajustes del calendario para tabletas */
            .page-container > h2.page-title { font-size: 1.6em; margin-bottom: 20px;}
            .calendario-wrapper { padding: 15px; }
            .calendario-header h3 { font-size: 1.4em; }
            .calendario-header button { padding: 7px 12px; font-size: 0.85em; }
            .dia-semana { font-size: 0.75em; padding: 9px 3px; }
            .dia-calendario { min-height: 75px; padding: 7px 3px; }
            .dia-numero { font-size: 0.95em; width: 24px; height: 24px; line-height: 24px; margin-bottom: 5px; }
            .evento-calendario { font-size: 0.72em; }
        }

        @media (max-width: 767px) { /* Ajustes adicionales para móviles más pequeños */
            .logo img { height: 60px; }
            .menu-toggle { font-size: 1.6rem; }

            .page-container > h2.page-title { font-size: 1.5em; }
            .calendario-header h3 { font-size: 1.3em; }
            .dia-semana { font-size: 0.7em; }
            .dia-calendario { min-height: 70px; }
            .dia-numero { font-size: 0.9em; }
        }

        @media (max-width: 480px) {
            .logo img { height: 50px; }
            .page-container > h2.page-title { font-size: 1.3em; }
            .calendario-header { flex-direction: column; gap: 10px; }
            .calendario-header h3 { font-size: 1.2em; order: -1; }
            .dia-semana { font-size: 0.6em; }
            .dia-calendario { min-height: 60px; }
            .dia-numero { font-size: 0.8em; width: 20px; height: 20px; line-height: 20px; }
            .leyenda h4 { font-size: 1em; }
            .leyenda-item { font-size: 0.8em; }
            .leyenda-color { width: 12px; height: 12px; margin-right: 8px; }
        }
        /* --- FIN DE ESTILOS RESPONSIVOS --- */
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../img/logo.png" alt="Logo GAG" /> <!-- Ajusta esta ruta -->
        </div>
        <!-- Botón Hamburguesa -->
        <button class="menu-toggle" id="menuToggleBtn" aria-label="Abrir menú" aria-expanded="false">
            ☰ <!-- Icono de hamburguesa -->
        </button>
        <nav class="menu" id="mainMenu"> <!-- Contenedor del menú con ID -->
            <a href="index.php">Inicio</a>
            <a href="miscultivos.php">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario.php" class="active">Calendario</a> <!-- Asumiendo que este es calendario_general.php -->
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php" class="exit">Cerrar Sesión</a>
        </nav>
    </div>

    <div class="page-container">
        <h2 class="page-title">Calendario General de Actividades</h2>

        <?php if (!empty($mensaje_error)): ?>
            <p class="error-message-calendar"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>

        <div class="calendario-wrapper">
            <div class="calendario-header">
                <button id="mes-anterior">< Anterior</button>
                <h3 id="mes-anio-actual"></h3>
                <button id="mes-siguiente">Siguiente ></button>
            </div>
            <div class="calendario-grid">
                <div class="dia-semana">Dom</div>
                <div class="dia-semana">Lun</div>
                <div class="dia-semana">Mar</div>
                <div class="dia-semana">Mié</div>
                <div class="dia-semana">Jue</div>
                <div class="dia-semana">Vie</div>
                <div class="dia-semana">Sáb</div>
            </div>
            <div class="calendario-grid" id="dias-calendario-grid">
                <!-- Días generados por JS -->
            </div>
        
            <div class="leyenda">
                <h4>Leyenda:</h4>
                <div class="leyenda-item"><span class="leyenda-color evento-tratamiento"></span> Tratamiento Programado</div>
                <div class="leyenda-item"><span class="leyenda-color evento-riego"></span> Riego Estimado</div>
            </div>
        </div> <!-- Fin calendario-wrapper -->
    </div> <!-- Fin page-container -->

    <script>
        const eventosDesdePHP = <?php echo json_encode($eventos_calendario); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // --- LÓGICA PARA EL CALENDARIO ---
            const calendarioGrid = document.getElementById('dias-calendario-grid');
            const mesAnioActualLabel = document.getElementById('mes-anio-actual');
            const btnMesAnterior = document.getElementById('mes-anterior');
            const btnMesSiguiente = document.getElementById('mes-siguiente');
            let fechaActualVisualizada = new Date();

            function formatearFecha(dateObj) {
                const anio = dateObj.getFullYear();
                const mes = String(dateObj.getMonth() + 1).padStart(2, '0');
                const dia = String(dateObj.getDate()).padStart(2, '0');
                return `${anio}-${mes}-${dia}`;
            }

            function renderizarCalendario() {
                if (!calendarioGrid) return; // Seguridad si el elemento no existe
                calendarioGrid.innerHTML = '';
                const anio = fechaActualVisualizada.getFullYear();
                const mes = fechaActualVisualizada.getMonth();
                if(mesAnioActualLabel) {
                    mesAnioActualLabel.textContent = `${fechaActualVisualizada.toLocaleString('es-ES', { month: 'long' })} ${anio}`;
                }
                const primerDiaDelMes = new Date(anio, mes, 1).getDay();
                const diasEnMes = new Date(anio, mes + 1, 0).getDate();
                const offsetPrimerDia = primerDiaDelMes;

                for (let i = 0; i < offsetPrimerDia; i++) {
                    const celdaVacia = document.createElement('div');
                    celdaVacia.classList.add('dia-calendario', 'otro-mes');
                    calendarioGrid.appendChild(celdaVacia);
                }

                const hoyStr = formatearFecha(new Date());
                for (let dia = 1; dia <= diasEnMes; dia++) {
                    const celdaDia = document.createElement('div');
                    celdaDia.classList.add('dia-calendario');
                    const fechaCeldaStr = formatearFecha(new Date(anio, mes, dia));
                    const diaNumero = document.createElement('span');
                    diaNumero.classList.add('dia-numero');
                    diaNumero.textContent = dia;
                    celdaDia.appendChild(diaNumero);

                    if (fechaCeldaStr === hoyStr) {
                        celdaDia.classList.add('hoy');
                    }

                    eventosDesdePHP.filter(e => e.start === fechaCeldaStr).forEach(evento => {
                        const divEvento = document.createElement('div');
                        divEvento.classList.add('evento-calendario');
                        if (evento.className) {
                            divEvento.classList.add(evento.className);
                        }
                        divEvento.textContent = evento.title;
                        divEvento.title = evento.description || evento.title;
                        celdaDia.appendChild(divEvento);
                    });
                    calendarioGrid.appendChild(celdaDia);
                }
            }

            if (btnMesAnterior) {
                btnMesAnterior.addEventListener('click', () => {
                    fechaActualVisualizada.setMonth(fechaActualVisualizada.getMonth() - 1);
                    renderizarCalendario();
                });
            }
            if (btnMesSiguiente) {
                btnMesSiguiente.addEventListener('click', () => {
                    fechaActualVisualizada.setMonth(fechaActualVisualizada.getMonth() + 1);
                    renderizarCalendario();
                });
            }
            
            if (calendarioGrid) { // Solo renderizar si existe la grilla
                 renderizarCalendario();
            }

            // --- LÓGICA PARA EL MENÚ HAMBURGUESA ---
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const mainMenu = document.getElementById('mainMenu');

            if (menuToggleBtn && mainMenu) {
                menuToggleBtn.addEventListener('click', () => {
                    mainMenu.classList.toggle('active');
                    const isExpanded = mainMenu.classList.contains('active');
                    menuToggleBtn.setAttribute('aria-expanded', isExpanded);
                });
            }
        });
    </script>
</body>
</html>