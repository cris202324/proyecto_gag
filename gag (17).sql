-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 05-07-2025 a las 18:30:02
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gag`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alimentacion`
--

CREATE TABLE `alimentacion` (
  `id_alimentacion` int(11) NOT NULL,
  `id_animal` int(11) NOT NULL,
  `tipo_alimento` varchar(50) NOT NULL,
  `cantidad_diaria` decimal(5,2) NOT NULL,
  `unidad_cantidad` varchar(20) NOT NULL DEFAULT 'kg',
  `frecuencia_alimentacion` varchar(70) NOT NULL,
  `fecha_registro_alimentacion` date NOT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animales`
--

CREATE TABLE `animales` (
  `id_animal` int(11) NOT NULL,
  `id_usuario` varchar(20) NOT NULL,
  `nombre_animal` varchar(100) DEFAULT NULL,
  `tipo_animal` varchar(50) NOT NULL,
  `raza` varchar(50) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `sexo` enum('Macho','Hembra','Desconocido') DEFAULT 'Desconocido',
  `identificador_unico` varchar(50) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `cantidad` int(11) DEFAULT 1 COMMENT 'Cantidad de animales de este tipo si se registran en lote'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cultivos`
--

CREATE TABLE `cultivos` (
  `id_cultivo` int(11) NOT NULL,
  `id_usuario` varchar(20) NOT NULL,
  `id_tipo_cultivo` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `area_hectarea` decimal(10,2) NOT NULL,
  `id_municipio` int(11) NOT NULL,
  `id_estado_cultivo` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departamento`
--

CREATE TABLE `departamento` (
  `id_departamento` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `codigo_postal` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `departamento`
--

INSERT INTO `departamento` (`id_departamento`, `nombre`, `codigo_postal`) VALUES
(1, 'Tolima', '730001');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado`
--

CREATE TABLE `estado` (
  `id_estado` tinyint(4) NOT NULL,
  `descripcion` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estado`
--

INSERT INTO `estado` (`id_estado`, `descripcion`) VALUES
(1, 'Activo'),
(2, 'Inactivo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado_cultivo_definiciones`
--

CREATE TABLE `estado_cultivo_definiciones` (
  `id_estado_cultivo` tinyint(1) NOT NULL,
  `nombre_estado` varchar(50) NOT NULL,
  `descripcion_estado` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estado_cultivo_definiciones`
--

INSERT INTO `estado_cultivo_definiciones` (`id_estado_cultivo`, `nombre_estado`, `descripcion_estado`) VALUES
(1, 'En Progreso', 'El cultivo está actualmente activo y en desarrollo.'),
(2, 'Terminado', 'El ciclo del cultivo ha concluido y ha sido cosechado o finalizado.'),
(3, 'Cancelado', 'El cultivo fue cancelado antes de su finalización.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medicamentos`
--

CREATE TABLE `medicamentos` (
  `id_medicamento` int(11) NOT NULL,
  `id_animal` int(11) NOT NULL,
  `tipo_medicamento` varchar(50) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `fecha_de_administracion` date NOT NULL,
  `dosis` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `municipio`
--

CREATE TABLE `municipio` (
  `id_municipio` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `id_departamento` int(11) NOT NULL,
  `codigo_postal` varchar(10) DEFAULT NULL,
  `temperatura_actual` decimal(5,2) DEFAULT NULL,
  `temperatura_habitual` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `municipio`
--

INSERT INTO `municipio` (`id_municipio`, `nombre`, `id_departamento`, `codigo_postal`, `temperatura_actual`, `temperatura_habitual`) VALUES
(1, 'Ibagué', 1, '730001', 24.50, 23.00),
(2, 'Espinal', 1, '730501', 26.00, 25.50),
(3, 'Alpujarra', 1, '733060', 22.00, 21.50),
(4, 'Alvarado', 1, '731030', 27.00, 26.50),
(5, 'Ambalema', 1, '731540', 28.00, 27.50),
(6, 'Anzoátegui', 1, '731010', 19.00, 18.50),
(7, 'Armero Guayabal', 1, '731520', 29.00, 28.00),
(8, 'Ataco', 1, '735040', 24.00, 23.50),
(9, 'Cajamarca', 1, '732040', 17.00, 16.50),
(10, 'Carmen de Apicalá', 1, '733540', 26.00, 25.00),
(11, 'Casabianca', 1, '730550', 18.00, 17.00),
(12, 'Chaparral', 1, '735001', 25.00, 24.00),
(13, 'Coello', 1, '732520', 27.00, 26.00),
(14, 'Coyaima', 1, '734501', 28.00, 27.00),
(15, 'Cunday', 1, '733520', 23.00, 22.00),
(16, 'Dolores', 1, '733040', 22.00, 21.00),
(17, 'Espinal', 1, '733001', 29.00, 28.50),
(18, 'Falan', 1, '731560', 24.00, 23.00),
(19, 'Flandes', 1, '733020', 30.00, 29.00),
(20, 'Fresno', 1, '731501', 20.00, 19.50),
(21, 'Guamo', 1, '733501', 28.00, 27.50),
(22, 'Herveo', 1, '730540', 16.00, 15.50),
(23, 'Honda', 1, '732001', 30.00, 29.00),
(24, 'Ibagué', 1, '730001', 23.00, 22.50),
(25, 'Icononzo', 1, '733560', 21.00, 20.00),
(26, 'Lérida', 1, '731550', 28.00, 27.00),
(27, 'Líbano', 1, '731001', 20.00, 19.00),
(28, 'Mariquita', 1, '732020', 27.00, 26.50),
(29, 'Melgar', 1, '733530', 29.00, 28.00),
(30, 'Murillo', 1, '731040', 14.00, 13.50),
(31, 'Natagaima', 1, '734520', 30.00, 29.50),
(32, 'Ortega', 1, '734540', 26.00, 25.00),
(33, 'Palocabildo', 1, '731050', 21.00, 20.50),
(34, 'Piedras', 1, '732501', 27.00, 26.00),
(35, 'Planadas', 1, '735020', 20.00, 19.00),
(36, 'Prado', 1, '733050', 26.00, 25.50),
(37, 'Purificación', 1, '734560', 28.00, 27.00),
(38, 'Rioblanco', 1, '735060', 22.00, 21.00),
(39, 'Roncesvalles', 1, '732050', 17.00, 16.00),
(40, 'Rovira', 1, '732540', 23.00, 22.50),
(41, 'Saldaña', 1, '733550', 29.00, 28.00),
(42, 'San Antonio', 1, '735050', 24.00, 23.00),
(43, 'San Luis', 1, '734001', 25.00, 24.50),
(44, 'Santa Isabel', 1, '731020', 16.00, 15.00),
(45, 'Suárez', 1, '733570', 27.00, 26.50),
(46, 'Valle de San Juan', 1, '732550', 24.00, 23.50),
(47, 'Venadillo', 1, '731060', 28.00, 27.00),
(48, 'Villahermosa', 1, '730560', 19.00, 18.00),
(49, 'Villarrica', 1, '733580', 22.00, 21.50);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `precios_cultivos_actuales`
--

CREATE TABLE `precios_cultivos_actuales` (
  `id_precio` int(11) NOT NULL,
  `id_tipo_cultivo` int(11) NOT NULL,
  `nombre_producto_api` varchar(255) DEFAULT NULL,
  `precio_min` decimal(12,2) DEFAULT NULL,
  `precio_max` decimal(12,2) DEFAULT NULL,
  `precio_promedio` decimal(12,2) DEFAULT NULL,
  `unidad` varchar(50) DEFAULT NULL,
  `fuente_mercado` varchar(150) DEFAULT NULL,
  `fecha_actualizacion_api` date DEFAULT NULL,
  `fecha_consulta_local` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `produccion_cultivo`
--

CREATE TABLE `produccion_cultivo` (
  `id_produccion` int(11) NOT NULL,
  `id_cultivo` int(11) NOT NULL,
  `cantidad_producida` decimal(10,2) NOT NULL,
  `fecha_cosecha` date NOT NULL,
  `calidad_cosecha` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

CREATE TABLE `producto` (
  `id_producto` tinyint(4) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo_producto` tinyint(4) NOT NULL,
  `costo` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registro_sanitario_animal`
--

CREATE TABLE `registro_sanitario_animal` (
  `id_registro_sanitario` int(11) NOT NULL,
  `id_animal` int(11) NOT NULL,
  `id_tipo_mv` int(11) DEFAULT NULL COMMENT 'Referencia al producto usado, si está predefinido',
  `nombre_producto_aplicado` varchar(150) NOT NULL COMMENT 'Nombre del producto usado',
  `tipo_aplicacion_registrada` enum('Vacuna','Medicamento','Desparasitante','Vitamina','Otro') NOT NULL,
  `fecha_aplicacion` date NOT NULL,
  `dosis_aplicada` varchar(100) DEFAULT NULL,
  `via_administracion` varchar(100) DEFAULT NULL,
  `lote_producto` varchar(50) DEFAULT NULL,
  `fecha_vencimiento_producto` date DEFAULT NULL,
  `responsable_aplicacion` varchar(100) DEFAULT NULL COMMENT 'Persona o veterinario',
  `observaciones` text DEFAULT NULL,
  `fecha_proxima_dosis_sugerida` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas_soporte`
--

CREATE TABLE `respuestas_soporte` (
  `id_respuesta` int(11) NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `id_admin` varchar(20) NOT NULL,
  `mensaje_admin` text NOT NULL,
  `fecha_respuesta` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `riego`
--

CREATE TABLE `riego` (
  `id_riego` int(11) NOT NULL,
  `id_cultivo` int(11) NOT NULL,
  `frecuencia_riego` varchar(50) NOT NULL,
  `volumen_agua` decimal(4,2) NOT NULL,
  `metodo_riego` varchar(70) NOT NULL,
  `fecha_ultimo_riego` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `id_rol` tinyint(4) NOT NULL,
  `rol` enum('admin','usuario','superadmin') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`id_rol`, `rol`) VALUES
(1, 'admin'),
(2, 'usuario'),
(3, 'superadmin');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `suelo`
--

CREATE TABLE `suelo` (
  `id_suelo` int(11) NOT NULL,
  `id_cultivo` int(11) NOT NULL,
  `id_tipo_cultivo` int(11) NOT NULL,
  `tipo_suelo` varchar(50) NOT NULL,
  `ph` decimal(4,2) NOT NULL,
  `nivel_nutrientes` varchar(50) NOT NULL,
  `temperatura_actual` decimal(5,2) DEFAULT NULL,
  `fecha_muestreo` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets_soporte`
--

CREATE TABLE `tickets_soporte` (
  `id_ticket` int(11) NOT NULL,
  `id_usuario` varchar(20) NOT NULL,
  `asunto` varchar(255) NOT NULL,
  `mensaje_usuario` text NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado_ticket` enum('Abierto','Respondido','Cerrado') NOT NULL DEFAULT 'Abierto',
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_cultivo`
--

CREATE TABLE `tipos_cultivo` (
  `id_tipo_cultivo` int(11) NOT NULL,
  `nombre_cultivo` varchar(50) NOT NULL,
  `tiempo_estimado_frutos` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipos_cultivo`
--

INSERT INTO `tipos_cultivo` (`id_tipo_cultivo`, `nombre_cultivo`, `tiempo_estimado_frutos`) VALUES
(1, 'Arroz', 150),
(2, 'Café', 1460),
(3, 'platano', 365),
(4, 'Maíz Tecnificado', 120),
(5, 'Frijol', 90),
(6, 'Cacao', 1095),
(7, 'Aguacate Hass', 1825),
(8, 'Caña Panelera', 540);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_medicamento_vacuna`
--

CREATE TABLE `tipos_medicamento_vacuna` (
  `id_tipo_mv` int(11) NOT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `tipo_aplicacion` enum('Vacuna','Medicamento','Desparasitante','Vitamina','Otro') NOT NULL,
  `descripcion_uso` text DEFAULT NULL,
  `especie_objetivo` varchar(100) DEFAULT NULL COMMENT 'Ej: Bovinos, Aves, Porcinos, General',
  `edad_aplicacion_sugerida_dias_min` int(11) DEFAULT NULL COMMENT 'Edad mínima en días para aplicación sugerida',
  `edad_aplicacion_sugerida_dias_max` int(11) DEFAULT NULL COMMENT 'Edad máxima en días para aplicación sugerida',
  `dosis_sugerida` varchar(100) DEFAULT NULL,
  `via_administracion_sugerida` varchar(100) DEFAULT NULL COMMENT 'Ej: Intramuscular, Oral, Subcutánea',
  `frecuencia_sugerida` varchar(100) DEFAULT NULL COMMENT 'Ej: Dosis única, Refuerzo anual, Cada 6 meses',
  `periodo_retiro_carne_dias` int(11) DEFAULT NULL COMMENT 'Días de retiro en carne',
  `periodo_retiro_leche_horas` int(11) DEFAULT NULL COMMENT 'Horas de retiro en leche',
  `notas_adicionales` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipos_medicamento_vacuna`
--

INSERT INTO `tipos_medicamento_vacuna` (`id_tipo_mv`, `nombre_producto`, `tipo_aplicacion`, `descripcion_uso`, `especie_objetivo`, `edad_aplicacion_sugerida_dias_min`, `edad_aplicacion_sugerida_dias_max`, `dosis_sugerida`, `via_administracion_sugerida`, `frecuencia_sugerida`, `periodo_retiro_carne_dias`, `periodo_retiro_leche_horas`, `notas_adicionales`) VALUES
(1, 'Vacuna Triple Aviar (Newcastle, Bronquitis, Gumboro)', 'Vacuna', 'Prevención de enfermedades virales en aves.', 'Aves', 7, 21, NULL, NULL, 'Dosis inicial, refuerzo a las 4 semanas', NULL, NULL, NULL),
(2, 'Ivermectina 1%', 'Desparasitante', 'Control de parásitos internos y externos en ganado.', 'Bovinos', 90, 730, NULL, NULL, 'Cada 6 meses', NULL, NULL, NULL),
(3, 'Hierro Dextrano', 'Vitamina', 'Prevención de anemia en lechones.', 'Porcinos', 1, 3, NULL, NULL, 'Dosis única al nacer', NULL, NULL, NULL),
(4, 'Penicilina', 'Medicamento', 'Tratamiento de infecciones bacterianas generales.', 'General', NULL, NULL, NULL, NULL, 'Según prescripción veterinaria', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_producto`
--

CREATE TABLE `tipo_producto` (
  `id_tipo_producto` int(11) NOT NULL,
  `descripcion` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tratamientos_predeterminados`
--

CREATE TABLE `tratamientos_predeterminados` (
  `id_trat_pred` int(11) NOT NULL,
  `id_tipo_cultivo` int(11) NOT NULL,
  `tipo_tratamiento` varchar(50) NOT NULL,
  `producto_usado` varchar(50) NOT NULL,
  `etapas` varchar(100) NOT NULL,
  `dosis` decimal(8,2) NOT NULL,
  `unidad_dosis` varchar(20) DEFAULT 'kg/ha',
  `observaciones` varchar(100) DEFAULT NULL,
  `dias_despues_inicio_aplicacion` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tratamientos_predeterminados`
--

INSERT INTO `tratamientos_predeterminados` (`id_trat_pred`, `id_tipo_cultivo`, `tipo_tratamiento`, `producto_usado`, `etapas`, `dosis`, `unidad_dosis`, `observaciones`, `dias_despues_inicio_aplicacion`) VALUES
(1, 1, 'Preparación Suelo (Arada)', 'Arado de disco/cincel', 'Presiembra', 1.00, 'Pasada/ha', 'Mejorar estructura del suelo, 15-20 días antes.', -15),
(2, 1, 'Control Herbicida Pre-siembra', 'Glifosato', 'Presiembra', 2.00, 'L/ha', 'Control de malezas existentes, 7 días antes.', -7),
(3, 1, 'Fertilización de Base (Siembra)', 'NPK (10-30-10)', 'Siembra', 200.00, 'kg/ha', 'Incorporar al momento de la siembra.', 0),
(4, 1, 'Control Herbicida Post-emergente', 'Propanil + Otros', 'Post-emergencia (15-20 DDS)', 3.00, 'L/ha', 'Control de malezas de hoja ancha y gramíneas.', 18),
(5, 1, 'Fertilización Nitrogenada (Macollamiento)', 'Urea (46%)', 'Inicio Macollamiento (25-30 DDS)', 100.00, 'kg/ha', 'Estimular desarrollo de macollos.', 28),
(6, 1, 'Control de Plagas (Barrenador)', 'Insecticida Específico', 'Etapa Vegetativa (Según monitoreo)', 0.50, 'L/ha', 'Aplicar al detectar umbrales de daño.', 45),
(7, 1, 'Fertilización Nitrogenada (Primordio Floral)', 'Urea (46%)', 'Inicio Primordio Floral (50-60 DDS)', 100.00, 'kg/ha', 'Para llenado de grano.', 55),
(8, 1, 'Control de Enfermedades (Piricularia)', 'Fungicida Específico', 'Embuchamiento/Espigado', 1.00, 'kg/ha', 'Preventivo o curativo temprano, según monitoreo.', 75),
(9, 2, 'Preparación Hoyo Siembra', 'Materia Orgánica (Compost)', 'Presiembra/Trasplante', 2.00, 'kg/hoyo', 'Mejorar suelo del hoyo, incorporar bien.', -5),
(10, 2, 'Fertilización de Siembra/Trasplante', 'NPK (18-5-15-6Mg-2S)', 'Siembra/Trasplante', 0.08, 'kg/planta', 'Aplicar en corona alrededor de la planta.', 0),
(11, 2, 'Control de Malezas (Plateo)', 'Manual/Guadaña', 'Establecimiento (Cada 60 días)', 1.00, 'Ciclo', 'Mantener área de plato limpia.', 60),
(12, 2, 'Fertilización de Crecimiento (Año 1)', 'NPK (20-10-10)', 'Crecimiento (90 DDS)', 0.10, 'kg/planta', 'Dividir en 2-3 aplicaciones al año.', 90),
(13, 2, 'Control de Broca (Monitoreo)', 'Trampas / Beauveria bassiana', 'Inicio Producción (Según monitoreo)', 1.00, 'Aplicación', 'Monitoreo constante, control biológico/cultural.', 365),
(14, 2, 'Control de Roya (Preventivo)', 'Fungicida Cúprico/Sistémico', 'Épocas Lluviosas (Preventivo)', 2.00, 'kg/ha', 'Según incidencia y condiciones climáticas.', 400),
(15, 3, 'Preparación Sitio y Hoyo', 'Materia Orgánica', 'Presiembra', 3.00, 'kg/hoyo', 'Asegurar buen drenaje y materia orgánica.', -7),
(16, 3, 'Fertilización de Siembra', 'NPK (15-15-15 o similar)', 'Siembra', 0.15, 'kg/planta', 'Aplicar al hoyo de siembra.', 0),
(17, 3, 'Deshoje y Desguasque Sanitario', 'Manual (Machete)', 'Crecimiento (Cada 45-60 días)', 1.00, 'Ciclo', 'Eliminar hojas secas o enfermas y guascas.', 45),
(18, 3, 'Fertilización de Mantenimiento', 'Urea + KCl (o NPK rico en K)', 'Crecimiento/Producción (Cada 90 días)', 0.20, 'kg/planta', 'Aplicar en corona o media luna.', 90),
(19, 3, 'Control de Sigatoka Negra (Preventivo)', 'Fungicida Protector/Sistémico', 'Épocas Lluviosas (Preventivo)', 1.00, 'L/ha', 'Rotar productos, según incidencia.', 120),
(20, 3, 'Control de Picudo Negro (Trampeo)', 'Trampas con atrayente', 'Todo el ciclo (Monitoreo)', 10.00, 'Trampas/ha', 'Revisar y mantener trampas.', 30),
(21, 3, 'Desmane y Protección de Racimo', 'Manual', 'Floración/Fructificación', 1.00, 'Ciclo', 'Eliminar manos falsas, embolsar racimo.', 240),
(22, 4, 'Preparación de Suelo', 'Arado y Rastreado', 'Presiembra', 1.00, 'Pasada/ha', 'Asegurar cama de siembra suelta y nivelada.', -10),
(23, 4, 'Fertilización de Arranque (Siembra)', 'NPK (10-30-10)', 'Siembra', 150.00, 'kg/ha', 'Aplicar al lado y debajo de la semilla (localizado).', 0),
(24, 4, 'Control Herbicida Pre-emergente', 'Atrazina + S-metolacloro', 'Siembra / Pre-emergencia', 2.00, 'L/ha', 'Aplicar sobre suelo húmedo después de sembrar.', 1),
(25, 4, 'Fertilización Nitrogenada (V4-V6)', 'Urea o Nitrato de Amonio', 'Etapa V4-V6 (25-30 DDS)', 150.00, 'kg/ha', 'Segunda dosis de Nitrógeno para crecimiento vegetativo.', 28),
(26, 3, 'Control de Picudo Negro (Trampeo)', 'Trampas con feromona/atrayente', 'Todo el ciclo (Monitoreo)', 10.00, 'Trampas/ha', 'Revisar y mantener trampas.', 30),
(27, 3, 'Desmane y Protección de Racimo', 'Manual', 'Floración/Fructificación', 1.00, 'Ciclo', 'Eliminar manos falsas, embolsar racimo.', 240),
(28, 5, 'Tratamiento de Semilla', 'Fungicida + Insecticida', 'Siembra', 0.10, 'kg/25kg semilla', 'Proteger semilla de plagas y enfermedades del suelo.', 0),
(29, 5, 'Fertilización de Siembra', 'NPK (10-20-20)', 'Siembra', 100.00, 'kg/ha', 'Bajo en N, alto en P y K. El frijol fija nitrógeno.', 0),
(30, 5, 'Control de Malezas', 'Herbicida Post-emergente Selectivo', 'Post-emergencia (15-20 DDS)', 1.50, 'L/ha', 'Controlar competencia inicial.', 18),
(31, 5, 'Control de Mosca Blanca / Trips', 'Insecticida Sistémico', 'Etapa Vegetativa (Monitoreo)', 0.40, 'L/ha', 'Prevenir virosis transmitidas por insectos.', 30),
(32, 5, 'Aplicación Foliar (Floración)', 'Boro y Zinc', 'Inicio de Floración (35-40 DDS)', 1.00, 'L/ha', 'Mejorar cuajado de flores y llenado de vainas.', 38),
(33, 5, 'Control de Antracnosis/Roya', 'Fungicida preventivo', 'Floración/Llenado de vaina', 1.00, 'kg/ha', 'Aplicar si las condiciones son favorables para enfermedad.', 50),
(34, 6, 'Preparación del Terreno y Hoyado', 'Materia Orgánica', 'Presiembra', 2.50, 'kg/hoyo', 'Asegurar buen drenaje y nutrición inicial.', -10),
(35, 6, 'Fertilización de Establecimiento', 'NPK (17-6-18-2)', 'Trasplante', 0.10, 'kg/planta', 'Aplicar en corona a 20cm del tallo.', 0),
(36, 6, 'Poda de Formación (Año 1)', 'Tijera de podar', 'Crecimiento (6-12 meses)', 1.00, 'Ciclo/planta', 'Definir 3-4 ramas principales.', 240),
(37, 6, 'Control de Moniliasis y Escoba de Bruja', 'Poda Sanitaria + Cobre', 'Todo el ciclo (Continuo)', 1.00, 'Ciclo', 'Remoción de frutos y ramas enfermas. Aspersión de cobre.', 365),
(38, 6, 'Fertilización de Producción', 'NPK rico en K', 'Inicio de lluvias', 0.50, 'kg/planta', 'Aplicar 2 veces al año según análisis de suelo.', 730),
(39, 7, 'Análisis de Suelo y Enmienda', 'Cal o Materia Orgánica', 'Presiembra', 2.00, 'kg/hoyo', 'Corregir pH y mejorar estructura del suelo.', -30),
(40, 7, 'Fertilización de Siembra', 'NPK + Elementos Menores', 'Trasplante', 0.15, 'kg/planta', 'Incorporar en el hoyo de siembra.', 0),
(41, 7, 'Poda de Formación y Mantenimiento', 'Tijera/Serrucho', 'Anual (Post-cosecha)', 1.00, 'Ciclo/planta', 'Permitir entrada de luz y aireación.', 365),
(42, 7, 'Control Preventivo de Tristeza', 'Fosfito de Potasio', 'Inicio de lluvias', 2.00, 'L/ha', 'Aplicaciones al suelo (drench) o foliares.', 180),
(43, 7, 'Monitoreo de Barrenadores del Tronco', 'Inspección visual', 'Continuo', 1.00, 'Ciclo', 'Revisar base del tronco y ramas por aserrín.', 90),
(44, 7, 'Fertilización para Producción (Año 3+)', 'Análisis Foliar + NPK', 'Pre-floración y post-cuajado', 1.00, 'kg/planta/año', 'Dividir en 2-3 aplicaciones.', 1095),
(45, 8, 'Preparación de Suelo Profunda', 'Subsolador y Rastra', 'Presiembra', 1.00, 'Pasada/ha', 'Romper compactación y alistar cama de siembra.', -20),
(46, 8, 'Siembra y Tapado de Semilla', 'Semilla (esquejes)', 'Siembra', 10000.00, 'esquejes/ha', 'Asegurar buena densidad y contacto con el suelo.', 0),
(47, 8, 'Fertilización de Establecimiento', 'NPK (15-15-15)', '30-45 DDS', 200.00, 'kg/ha', 'Primer abonado para arranque del cultivo.', 40),
(48, 8, 'Control de Malezas (Aporque)', 'Manual o Mecánico', '60-90 DDS', 1.00, 'Ciclo', 'Aporcar tierra a la base para control y anclaje.', 75),
(49, 8, 'Control de Diatraea (Barrenador)', 'Liberación de Trichogramma', 'Según monitoreo', 50.00, 'pulgadas2/ha', 'Control biológico de la plaga clave.', 120),
(50, 8, 'Segunda Fertilización (Nitrógeno)', 'Urea o Gallinaza', '5-6 meses', 150.00, 'kg/ha', 'Impulsar el crecimiento y acumulación de sacarosa.', 165);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tratamiento_cultivo`
--

CREATE TABLE `tratamiento_cultivo` (
  `id_tratamiento` int(11) NOT NULL,
  `id_cultivo` int(11) NOT NULL,
  `id_tipo_cultivo` int(11) NOT NULL,
  `tipo_tratamiento` varchar(50) NOT NULL,
  `producto_usado` varchar(50) NOT NULL,
  `etapas` varchar(100) NOT NULL,
  `dosis` decimal(5,2) NOT NULL,
  `observaciones` varchar(100) NOT NULL,
  `fecha_aplicacion_estimada` date DEFAULT NULL,
  `fecha_realizacion_real` date DEFAULT NULL,
  `estado_tratamiento` enum('Pendiente','Completado','Cancelado') NOT NULL DEFAULT 'Pendiente',
  `observaciones_realizacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` varchar(20) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `contrasena` varchar(255) DEFAULT NULL,
  `token_recuperacion` varchar(100) DEFAULT NULL,
  `token_expiracion` datetime DEFAULT NULL,
  `token_usado` tinyint(1) DEFAULT 0,
  `id_rol` tinyint(4) NOT NULL,
  `id_estado` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `email`, `contrasena`, `token_recuperacion`, `token_expiracion`, `token_usado`, `id_rol`, `id_estado`) VALUES
('USR2843', 'camilo', 'camilor@gmial.com', '$2y$10$mb9XsIhLWrRDIhgIPMEaBei2EToT6e..NRWhjvyRPZ3X11BFKMAJC', NULL, NULL, 0, 3, 1),
('USR8927', 'david', 'davidfernandoballares@gmail.com', '$2y$10$N70EW9Zwb93GrEG9B2EmlesL3Ltd4Cb7uRzVZDDp6.8NYt3GDy2vS', NULL, NULL, 1, 1, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alimentacion`
--
ALTER TABLE `alimentacion`
  ADD PRIMARY KEY (`id_alimentacion`);

--
-- Indices de la tabla `animales`
--
ALTER TABLE `animales`
  ADD PRIMARY KEY (`id_animal`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `cultivos`
--
ALTER TABLE `cultivos`
  ADD PRIMARY KEY (`id_cultivo`),
  ADD KEY `id_tipo_cultivo` (`id_tipo_cultivo`),
  ADD KEY `id_municipio` (`id_municipio`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `fk_cultivos_estado_cultivo` (`id_estado_cultivo`);

--
-- Indices de la tabla `departamento`
--
ALTER TABLE `departamento`
  ADD PRIMARY KEY (`id_departamento`);

--
-- Indices de la tabla `estado`
--
ALTER TABLE `estado`
  ADD PRIMARY KEY (`id_estado`);

--
-- Indices de la tabla `estado_cultivo_definiciones`
--
ALTER TABLE `estado_cultivo_definiciones`
  ADD PRIMARY KEY (`id_estado_cultivo`),
  ADD UNIQUE KEY `idx_nombre_estado_cultivo` (`nombre_estado`);

--
-- Indices de la tabla `medicamentos`
--
ALTER TABLE `medicamentos`
  ADD PRIMARY KEY (`id_medicamento`);

--
-- Indices de la tabla `municipio`
--
ALTER TABLE `municipio`
  ADD PRIMARY KEY (`id_municipio`),
  ADD KEY `id_departamento` (`id_departamento`);

--
-- Indices de la tabla `precios_cultivos_actuales`
--
ALTER TABLE `precios_cultivos_actuales`
  ADD PRIMARY KEY (`id_precio`),
  ADD UNIQUE KEY `idx_unico_precio` (`id_tipo_cultivo`,`fuente_mercado`,`fecha_actualizacion_api`);

--
-- Indices de la tabla `produccion_cultivo`
--
ALTER TABLE `produccion_cultivo`
  ADD PRIMARY KEY (`id_produccion`),
  ADD KEY `id_cultivo` (`id_cultivo`);

--
-- Indices de la tabla `producto`
--
ALTER TABLE `producto`
  ADD PRIMARY KEY (`id_producto`);

--
-- Indices de la tabla `registro_sanitario_animal`
--
ALTER TABLE `registro_sanitario_animal`
  ADD PRIMARY KEY (`id_registro_sanitario`),
  ADD KEY `idx_id_animal` (`id_animal`),
  ADD KEY `idx_id_tipo_mv` (`id_tipo_mv`);

--
-- Indices de la tabla `respuestas_soporte`
--
ALTER TABLE `respuestas_soporte`
  ADD PRIMARY KEY (`id_respuesta`),
  ADD KEY `id_ticket` (`id_ticket`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Indices de la tabla `riego`
--
ALTER TABLE `riego`
  ADD PRIMARY KEY (`id_riego`),
  ADD KEY `id_cultivo` (`id_cultivo`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indices de la tabla `suelo`
--
ALTER TABLE `suelo`
  ADD PRIMARY KEY (`id_suelo`),
  ADD KEY `id_tipo_cultivo` (`id_tipo_cultivo`),
  ADD KEY `id_cultivo` (`id_cultivo`);

--
-- Indices de la tabla `tickets_soporte`
--
ALTER TABLE `tickets_soporte`
  ADD PRIMARY KEY (`id_ticket`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `tipos_cultivo`
--
ALTER TABLE `tipos_cultivo`
  ADD PRIMARY KEY (`id_tipo_cultivo`);

--
-- Indices de la tabla `tipos_medicamento_vacuna`
--
ALTER TABLE `tipos_medicamento_vacuna`
  ADD PRIMARY KEY (`id_tipo_mv`),
  ADD UNIQUE KEY `idx_nombre_producto_unico` (`nombre_producto`);

--
-- Indices de la tabla `tipo_producto`
--
ALTER TABLE `tipo_producto`
  ADD PRIMARY KEY (`id_tipo_producto`);

--
-- Indices de la tabla `tratamientos_predeterminados`
--
ALTER TABLE `tratamientos_predeterminados`
  ADD PRIMARY KEY (`id_trat_pred`),
  ADD KEY `id_tipo_cultivo` (`id_tipo_cultivo`);

--
-- Indices de la tabla `tratamiento_cultivo`
--
ALTER TABLE `tratamiento_cultivo`
  ADD PRIMARY KEY (`id_tratamiento`),
  ADD KEY `id_tipo_cultivo` (`id_tipo_cultivo`),
  ADD KEY `tratamiento_cultivo_fk_cultivo` (`id_cultivo`),
  ADD KEY `idx_estado_tratamiento` (`estado_tratamiento`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD UNIQUE KEY `unique_token_recuperacion` (`token_recuperacion`),
  ADD KEY `id_rol` (`id_rol`),
  ADD KEY `id_estado` (`id_estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alimentacion`
--
ALTER TABLE `alimentacion`
  MODIFY `id_alimentacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `animales`
--
ALTER TABLE `animales`
  MODIFY `id_animal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `cultivos`
--
ALTER TABLE `cultivos`
  MODIFY `id_cultivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `departamento`
--
ALTER TABLE `departamento`
  MODIFY `id_departamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `estado`
--
ALTER TABLE `estado`
  MODIFY `id_estado` tinyint(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `estado_cultivo_definiciones`
--
ALTER TABLE `estado_cultivo_definiciones`
  MODIFY `id_estado_cultivo` tinyint(1) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `medicamentos`
--
ALTER TABLE `medicamentos`
  MODIFY `id_medicamento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `municipio`
--
ALTER TABLE `municipio`
  MODIFY `id_municipio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT de la tabla `precios_cultivos_actuales`
--
ALTER TABLE `precios_cultivos_actuales`
  MODIFY `id_precio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `produccion_cultivo`
--
ALTER TABLE `produccion_cultivo`
  MODIFY `id_produccion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `producto`
--
ALTER TABLE `producto`
  MODIFY `id_producto` tinyint(4) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `registro_sanitario_animal`
--
ALTER TABLE `registro_sanitario_animal`
  MODIFY `id_registro_sanitario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `respuestas_soporte`
--
ALTER TABLE `respuestas_soporte`
  MODIFY `id_respuesta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `riego`
--
ALTER TABLE `riego`
  MODIFY `id_riego` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `id_rol` tinyint(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `suelo`
--
ALTER TABLE `suelo`
  MODIFY `id_suelo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tickets_soporte`
--
ALTER TABLE `tickets_soporte`
  MODIFY `id_ticket` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `tipos_cultivo`
--
ALTER TABLE `tipos_cultivo`
  MODIFY `id_tipo_cultivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `tipos_medicamento_vacuna`
--
ALTER TABLE `tipos_medicamento_vacuna`
  MODIFY `id_tipo_mv` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tipo_producto`
--
ALTER TABLE `tipo_producto`
  MODIFY `id_tipo_producto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tratamientos_predeterminados`
--
ALTER TABLE `tratamientos_predeterminados`
  MODIFY `id_trat_pred` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT de la tabla `tratamiento_cultivo`
--
ALTER TABLE `tratamiento_cultivo`
  MODIFY `id_tratamiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `animales`
--
ALTER TABLE `animales`
  ADD CONSTRAINT `animales_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `cultivos`
--
ALTER TABLE `cultivos`
  ADD CONSTRAINT `cultivos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cultivos_ibfk_2` FOREIGN KEY (`id_tipo_cultivo`) REFERENCES `tipos_cultivo` (`id_tipo_cultivo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cultivos_ibfk_3` FOREIGN KEY (`id_municipio`) REFERENCES `municipio` (`id_municipio`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cultivos_estado_cultivo` FOREIGN KEY (`id_estado_cultivo`) REFERENCES `estado_cultivo_definiciones` (`id_estado_cultivo`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `municipio`
--
ALTER TABLE `municipio`
  ADD CONSTRAINT `municipio_ibfk_1` FOREIGN KEY (`id_departamento`) REFERENCES `departamento` (`id_departamento`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `precios_cultivos_actuales`
--
ALTER TABLE `precios_cultivos_actuales`
  ADD CONSTRAINT `fk_precio_tipo_cultivo` FOREIGN KEY (`id_tipo_cultivo`) REFERENCES `tipos_cultivo` (`id_tipo_cultivo`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `produccion_cultivo`
--
ALTER TABLE `produccion_cultivo`
  ADD CONSTRAINT `produccion_cultivo_ibfk_1` FOREIGN KEY (`id_cultivo`) REFERENCES `cultivos` (`id_cultivo`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `registro_sanitario_animal`
--
ALTER TABLE `registro_sanitario_animal`
  ADD CONSTRAINT `fk_registro_animal` FOREIGN KEY (`id_animal`) REFERENCES `animales` (`id_animal`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_registro_tipomv` FOREIGN KEY (`id_tipo_mv`) REFERENCES `tipos_medicamento_vacuna` (`id_tipo_mv`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `respuestas_soporte`
--
ALTER TABLE `respuestas_soporte`
  ADD CONSTRAINT `respuestas_soporte_ibfk_1` FOREIGN KEY (`id_ticket`) REFERENCES `tickets_soporte` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `respuestas_soporte_ibfk_2` FOREIGN KEY (`id_admin`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `riego`
--
ALTER TABLE `riego`
  ADD CONSTRAINT `riego_ibfk_1` FOREIGN KEY (`id_cultivo`) REFERENCES `cultivos` (`id_cultivo`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `suelo`
--
ALTER TABLE `suelo`
  ADD CONSTRAINT `suelo_ibfk_1` FOREIGN KEY (`id_cultivo`) REFERENCES `cultivos` (`id_cultivo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `suelo_ibfk_2` FOREIGN KEY (`id_tipo_cultivo`) REFERENCES `tipos_cultivo` (`id_tipo_cultivo`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `tickets_soporte`
--
ALTER TABLE `tickets_soporte`
  ADD CONSTRAINT `tickets_soporte_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `tratamientos_predeterminados`
--
ALTER TABLE `tratamientos_predeterminados`
  ADD CONSTRAINT `tratamientos_predeterminados_ibfk_1` FOREIGN KEY (`id_tipo_cultivo`) REFERENCES `tipos_cultivo` (`id_tipo_cultivo`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `tratamiento_cultivo`
--
ALTER TABLE `tratamiento_cultivo`
  ADD CONSTRAINT `tratamiento_cultivo_fk_cultivo` FOREIGN KEY (`id_cultivo`) REFERENCES `cultivos` (`id_cultivo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tratamiento_cultivo_ibfk_2` FOREIGN KEY (`id_tipo_cultivo`) REFERENCES `tipos_cultivo` (`id_tipo_cultivo`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`id_estado`) REFERENCES `estado` (`id_estado`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
