<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    // Ajusta esta ruta si login.php está en un nivel diferente
    header("Location: ../login.html"); // Asumiendo que login.html está un nivel arriba (ej. en /proyecto/)
    exit();
}

include 'conexion.php'; // $pdo
$id_usuario_actual = $_SESSION['id_usuario'];
$eventos_calendario = [];
$mensaje_error = '';

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
                        // Formatear $hoy a solo fecha para comparación precisa si $fechaUltRiegoObj no tiene hora
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
        /* Estilos generales del cuerpo (si no están en estilos.css) */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }

        /* Contenedor principal de la página */
        .page-container {
            max-width: 1100px; /* Ligeramente más ancho para el calendario */
            margin: 20px auto;
            padding: 15px; /* Padding base para móviles */
        }
        .page-container > h2.page-title {
            text-align: center;
            color: #4caf50;
            margin-bottom: 25px;
            font-size: 1.8em;
        }

        /* Estilos específicos del calendario */
        .calendario-wrapper { /* Nuevo wrapper para centrar el calendario en sí */
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
            background-color: #f0f0f0;
            color: #333;
            border: 1px solid #ccc;
            padding: 8px 15px; /* Reducido para mejor ajuste */
            cursor: pointer;
            border-radius: 5px;
            font-size: 0.9em;
            transition: background-color 0.3s ease, box-shadow 0.2s ease;
        }

        .calendario-header button:hover {
            background-color: #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .calendario-header h3 {
            margin: 0;
            font-size: 1.5em; /* Ajustado */
            color: #4caf50;
            font-weight: bold;
            text-align: center; /* Centrar título del mes */
            flex-grow: 1; /* Para que ocupe espacio y permita centrar */
        }

        .calendario-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: #dddddd;
            border: 1px solid #dddddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .dia-semana, .dia-calendario {
            background-color: #ffffff;
            padding: 8px 4px; /* Padding ajustado */
            text-align: center;
            min-height: 80px; /* Altura mínima ajustada */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            font-size: 0.85em;
            position: relative;
            border: none;
        }

        .dia-semana {
            font-weight: bold;
            background-color: #f5f5f5;
            color: #555;
            min-height: auto;
            padding: 10px 5px;
            font-size: 0.8em; /* Un poco más pequeño */
            text-transform: uppercase;
        }

        .dia-numero {
            font-weight: bold;
            margin-bottom: 6px; /* Menos margen */
            font-size: 1em; /* Ajustado */
            color: #444;
            width: 26px; /* Ajustado */
            height: 26px;
            line-height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%; /* Siempre redondo */
        }

        .dia-calendario.otro-mes .dia-numero {
            color: #b0b0b0;
            opacity: 0.6;
        }

        .dia-calendario.hoy .dia-numero {
            background-color: #4caf50;
            color: white;
        }

        .evento-calendario {
            font-size: 0.75em; /* Más pequeño para caber más */
            padding: 2px 4px;
            border-radius: 3px;
            margin-top: 3px;
            width: 95%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            border: 1px solid transparent;
            text-align: left;
            cursor: default;
            box-shadow: 0 1px 1px rgba(0,0,0,0.05);
        }

        .evento-tratamiento {
            border-left: 3px solid #6da944; /* Borde lateral distintivo */
            background-color: #e8f5e9;
            color: #387002;
        }

        .evento-riego {
            border-left: 3px solid #81c784;
            background-color: #e6fff0;
            color: #2e7d32;
        }

        .leyenda {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .leyenda h4 {
            margin-top: 0;
            margin-bottom: 12px;
            color: #333;
            font-size: 1.05em;
            font-weight: bold;
        }

        .leyenda-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.9em;
            color: #555;
        }

        .leyenda-color {
            width: 16px;
            height: 16px;
            margin-right: 10px;
            border-radius: 3px;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .error-message-calendar { /* Clase específica para errores del calendario */
            background-color: #ffdddd; 
            border: 1px solid #ffcccc; 
            color: #d8000c; 
            padding: 10px; 
            border-radius: 5px; 
            text-align:center;
            margin-bottom: 20px;
        }

        /* --- INICIO DE ESTILOS RESPONSIVOS PARA CALENDARIO --- */
        @media (max-width: 768px) {
            .page-container {
                padding: 10px;
            }
            .page-container > h2.page-title {
                font-size: 1.5em;
                margin-bottom: 20px;
            }
            .calendario-wrapper {
                padding: 15px;
            }
            .calendario-header h3 {
                font-size: 1.3em;
            }
            .calendario-header button {
                padding: 6px 10px;
                font-size: 0.8em;
            }
            .dia-semana {
                font-size: 0.7em; /* Nombres de días más pequeños */
                padding: 8px 2px;
            }
            .dia-calendario {
                min-height: 70px; /* Menos altura mínima en tabletas */
                padding: 6px 2px;
            }
            .dia-numero {
                font-size: 0.9em;
                width: 22px;
                height: 22px;
                line-height: 22px;
                margin-bottom: 4px;
            }
            .evento-calendario {
                font-size: 0.7em;
                width: 100%; /* Ocupar todo el ancho disponible */
                padding: 2px;
            }
        }

        @media (max-width: 480px) {
            .page-container > h2.page-title {
                font-size: 1.3em;
            }
            .calendario-header {
                flex-direction: column; /* Apilar controles del header */
                gap: 10px;
            }
             .calendario-header h3 {
                font-size: 1.2em;
                order: -1; /* Poner título arriba */
            }
            .dia-semana {
                font-size: 0.6em; /* Muy pequeño, para que quepan */
            }
            .dia-calendario {
                min-height: 60px; /* Aún menos altura */
            }
            .dia-numero {
                font-size: 0.8em;
                width: 20px;
                height: 20px;
                line-height: 20px;
            }
            .evento-calendario {
                /* Ya es bastante pequeño, podría ocultarse si hay demasiados */
                /* O mostrar solo un punto de color y el detalle al hacer hover/tap si es posible */
            }
            .leyenda h4 { font-size: 1em; }
            .leyenda-item { font-size: 0.8em; }
            .leyenda-color { width: 12px; height: 12px; margin-right: 8px; }
        }
        /* --- FIN DE ESTILOS RESPONSIVOS --- */
    </style>
</head>
<body>
    <div class="header"> <!-- Este header debe tomar estilos de tu estilos.css general -->
        <div class="logo">
            <img src="../img/logo.png" alt="Logo GAG" /> <!-- Ajusta esta ruta -->
        </div>
        <div class="menu"> <!-- Este menú debe tomar estilos de tu estilos.css general -->
            <a href="index.php">Inicio</a>
            <a href="miscultivos.php">Mis Cultivos</a>
            <a href="animales/mis_animales.php">Mis Animales</a>
            <a href="calendario_general.php" class="active">Calendario</a>
            <a href="configuracion.php">Configuración</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="cerrar_sesion.php">Cerrar Sesión</a>
        </div>
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
            <div class="calendario-grid"> <!-- Contenedor para los nombres de los días -->
                <div class="dia-semana">Dom</div>
                <div class="dia-semana">Lun</div>
                <div class="dia-semana">Mar</div>
                <div class="dia-semana">Mié</div>
                <div class="dia-semana">Jue</div>
                <div class="dia-semana">Vie</div>
                <div class="dia-semana">Sáb</div>
            </div>
            <div class="calendario-grid" id="dias-calendario-grid">
                <!-- Los días del mes se generarán aquí por JavaScript -->
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
                calendarioGrid.innerHTML = '';
                const anio = fechaActualVisualizada.getFullYear();
                const mes = fechaActualVisualizada.getMonth(); // 0-11

                mesAnioActualLabel.textContent = `${fechaActualVisualizada.toLocaleString('es-ES', { month: 'long' })} ${anio}`;

                const primerDiaDelMes = new Date(anio, mes, 1).getDay(); // 0 (Dom) - 6 (Sáb)
                const diasEnMes = new Date(anio, mes + 1, 0).getDate();
                
                // Ajuste para que Domingo sea el primer día (si getDay() devuelve 0 para Domingo)
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

                    // Añadir eventos del día
                    eventosDesdePHP.filter(e => e.start === fechaCeldaStr).forEach(evento => {
                        const divEvento = document.createElement('div');
                        divEvento.classList.add('evento-calendario');
                        if(evento.className) { 
                            divEvento.classList.add(evento.className);
                        }
                        divEvento.textContent = evento.title;
                        divEvento.title = evento.description || evento.title; // Tooltip
                        celdaDia.appendChild(divEvento);
                    });
                    calendarioGrid.appendChild(celdaDia);
                }
            }

            btnMesAnterior.addEventListener('click', () => {
                fechaActualVisualizada.setMonth(fechaActualVisualizada.getMonth() - 1);
                renderizarCalendario();
            });

            btnMesSiguiente.addEventListener('click', () => {
                fechaActualVisualizada.setMonth(fechaActualVisualizada.getMonth() + 1);
                renderizarCalendario();
            });
            
            renderizarCalendario(); // Renderizar al cargar la página
        });
    </script>
</body>
</html>