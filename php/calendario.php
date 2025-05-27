<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    // Ajusta esta ruta si login.php está en un nivel diferente
    header("Location: ../login.php"); // Asumiendo que login.php está un nivel arriba (ej. en /proyecto/)
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
            // Asegúrate que la tabla tratamiento_cultivo tenga la columna 'id_cultivo'
            $sql_tratamientos = "SELECT tipo_tratamiento, producto_usado, fecha_aplicacion_estimada
                                 FROM tratamiento_cultivo
                                 WHERE id_cultivo = :id_cultivo AND fecha_aplicacion_estimada IS NOT NULL";
            $stmt_tratamientos = $pdo->prepare($sql_tratamientos);
            $stmt_tratamientos->bindParam(':id_cultivo', $cultivo['id_cultivo']);
            $stmt_tratamientos->execute();
            $tratamientos = $stmt_tratamientos->fetchAll(PDO::FETCH_ASSOC);

            foreach ($tratamientos as $trat) {
                $eventos_calendario[] = [
                    'title' => $trat['tipo_tratamiento'] . " (" . $cultivo['nombre_cultivo'] . ")",
                    'start' => $trat['fecha_aplicacion_estimada'],
                    'description' => $trat['producto_usado'] . " para " . $cultivo['nombre_cultivo'],
                    'className' => 'evento-tratamiento',
                    'cultivo_id' => $cultivo['id_cultivo']
                ];
            }

            // 3. Calcular próximo riego para cada cultivo (simplificado)
            // Asegúrate que la tabla riego tenga la columna 'id_cultivo'
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
                        $diff = $hoy->diff($fechaUltRiegoObj);
                        if ($fechaUltRiegoObj >= $hoy || ($diff->invert && $diff->days < 7)) {
                            $eventos_calendario[] = [
                                'title' => "Riego Estimado (" . $cultivo['nombre_cultivo'] . ")",
                                'start' => $proximo_riego_estimado,
                                'description' => "Según frecuencia: " . $ultimo_riego['frecuencia_riego'],
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
    <title>Calendario General de Actividades</title>
    <!-- Enlace a tu hoja de estilos principal (si los estilos del calendario también están allí) -->
    <link rel="stylesheet" href="../css/estilos.css"> <!-- Ajusta esta ruta según tu estructura -->
    <style>
        /* Estilos generales del cuerpo (ya los tienes, pero para contexto) */
        /* body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        } */ /* Comentado si ya está en estilos.css */

        /* Estilos específicos del calendario */
        .calendario-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 25px;
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
            padding: 8px 18px;
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
            font-size: 1.7em;
            color: #4caf50; /* Verde principal para el título del mes */
            font-weight: bold;
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
            padding: 10px 5px;
            text-align: center;
            min-height: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            font-size: 0.9em;
            position: relative;
            border: none;
        }

        .dia-semana {
            font-weight: bold;
            background-color: #f5f5f5;
            color: #555;
            min-height: auto;
            padding: 12px 5px;
            font-size: 0.85em;
            text-transform: uppercase;
        }

        .dia-numero {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 1.1em;
            color: #444;
            width: 30px;
            height: 30px;
            line-height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dia-calendario.otro-mes .dia-numero {
            color: #b0b0b0;
            opacity: 0.7;
        }

        .dia-calendario.hoy .dia-numero {
            background-color: #4caf50;
            color: white;
            border-radius: 50%;
        }

        .evento-calendario {
            font-size: 0.8em;
            padding: 4px 6px;
            border-radius: 4px;
            margin-top: 4px;
            width: 90%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            border: 1px solid transparent;
            text-align: left;
            cursor: default;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .evento-tratamiento {
            border-color: #6da944;
            background-color: #e8f5e9;
            color: #387002;
        }

        .evento-riego {
            border-color: #81c784;
            background-color: #e6fff0;
            color: #2e7d32;
        }

        .leyenda {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .leyenda h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            font-size: 1.1em;
            font-weight: bold;
        }

        .leyenda-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.95em;
            color: #555;
        }

        .leyenda-color {
            width: 18px;
            height: 18px;
            margin-right: 12px;
            border-radius: 4px;
            border: 1px solid rgba(0,0,0,0.1);
        }
        /* Ajustes para que el contenedor principal de la página tome estos estilos */
        .content.calendario-page-content { /* Usar una clase más específica si es necesario */
            display: block; /* Para que no intente ser flex como el .content del index */
            padding: 0; /* Quitar padding si .calendario-container ya lo tiene */
            margin: 0; /* Quitar margen si .calendario-container ya lo tiene */
        }

    </style>
</head>
<body>
    <div class="header"> <!-- Asumiendo que usas la misma clase .header de tus estilos generales -->
        <div class="logo">
            <img src="../img/logo.png" alt="logo" /> <!-- Ajusta ruta -->
        </div>
        <div class="menu"> <!-- Asumiendo que usas la misma clase .menu -->
            <a href="index.php">Inicio</a> <!-- Ajusta ruta -->
            <a href="miscultivos.php">Mis Cultivos</a> <!-- Ajusta ruta -->
            <a href="animales/mis_animales.php">Mis Animales</a> <!-- Ajusta ruta -->
            <a href="calendario_general.php" class="active">Calendario y Horarios</a> <!-- Asumiendo que este archivo es calendario_general.php -->
            <a href="configuracion.php">Configuración</a> 
            <a href="ayuda.php">Ayuda</a> 
            <a href="cerrar_sesion.php" class="exit">Cerrar sesión</a> <!-- Ajusta ruta -->
        </div>
    </div>

    <!-- Contenedor principal específico para la página del calendario -->
    <div class="content calendario-page-content"> 
        <div class="calendario-container">
            <h2 style="text-align: center; color: #4caf50; margin-bottom: 20px;">Calendario General de Actividades</h2>

            <?php if (!empty($mensaje_error)): ?>
                <p class="error-message" style="background-color: #ffdddd; border: 1px solid #ffcccc; color: #d8000c; padding: 10px; border-radius: 5px; text-align:center;"><?php echo htmlspecialchars($mensaje_error); ?></p>
            <?php endif; ?>

            <div id="calendario-container"> <!-- Este ID es para el JS, el div externo es para el estilo del contenedor principal -->
                <div class="calendario-header">
                    <button id="mes-anterior">< Anterior</button>
                    <h3 id="mes-anio-actual"></h3>
                    <button id="mes-siguiente">Siguiente ></button>
                </div>
                <div class="calendario-grid"> <!-- Para los nombres de los días de la semana -->
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
            </div>
            <div class="leyenda">
                <h4>Leyenda:</h4>
                <div class="leyenda-item"><span class="leyenda-color evento-tratamiento"></span> Tratamiento Programado</div>
                <div class="leyenda-item"><span class="leyenda-color evento-riego"></span> Riego Estimado</div>
            </div>
        </div><!-- Fin de .calendario-container -->
    </div> <!-- Fin de .content.calendario-page-content -->

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
                const mes = fechaActualVisualizada.getMonth();

                mesAnioActualLabel.textContent = `${fechaActualVisualizada.toLocaleString('es-ES', { month: 'long' })} ${anio}`;

                const primerDiaDelMes = new Date(anio, mes, 1).getDay();
                const diasEnMes = new Date(anio, mes + 1, 0).getDate();
                const offsetPrimerDia = primerDiaDelMes; // 0 para Domingo

                for (let i = 0; i < offsetPrimerDia; i++) {
                    calendarioGrid.appendChild(document.createElement('div')).classList.add('dia-calendario', 'otro-mes');
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