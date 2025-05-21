-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 21-05-2025 a las 02:16:23
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.1.25

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
  `frecuencia_alimentacion` varchar(70) NOT NULL
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
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `animales`
--

INSERT INTO `animales` (`id_animal`, `id_usuario`, `nombre_animal`, `tipo_animal`, `raza`, `fecha_nacimiento`, `sexo`, `identificador_unico`, `fecha_registro`) VALUES
(1, 'USR6232', 'berpi', 'vaca', NULL, '2025-05-14', 'Macho', NULL, '2025-05-14 12:10:54'),
(2, 'USR6232', 'berpi', 'vaca', NULL, '2025-05-06', 'Macho', NULL, '2025-05-14 12:25:47');

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
  `id_municipio` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cultivos`
--

INSERT INTO `cultivos` (`id_cultivo`, `id_usuario`, `id_tipo_cultivo`, `fecha_inicio`, `fecha_fin`, `area_hectarea`, `id_municipio`) VALUES
(3, 'USR6232', 1, '2025-05-30', '2025-10-27', 50.00, 1),
(9, 'USR6232', 1, '2025-05-16', '2025-10-13', 50.00, 1),
(10, 'USR6232', 1, '2025-05-01', '2025-09-28', 20.00, 1);

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
(2, 'Espinal', 1, '730501', 26.00, 25.50);

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
  `rol` enum('admin','usuario') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`id_rol`, `rol`) VALUES
(1, 'admin'),
(2, 'usuario');

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
(3, 'platano', 365);

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
(1, 1, 'Fertilización de Siembra', 'NPK 15-15-15', 'Siembra', 150.00, 'kg/ha', 'Incorporar al suelo antes o durante la siembra.', 0),
(2, 1, 'Control Herbicida Pre-emergente', 'Pendimetalina', 'Presiembra / Pre-emergencia', 2.50, 'L/ha', 'Aplicar sobre suelo húmedo.', -2),
(3, 1, 'Abono Nitrogenado Macollamiento', 'Urea', 'Macollamiento (20-25 días)', 75.00, 'kg/ha', 'Primera aplicación de nitrógeno.', 20),
(4, 2, 'Enmienda de Suelo Siembra', 'Cal Dolomita', 'Preparación Hoyo', 0.25, 'kg/hoyo', 'Si análisis de suelo lo requiere.', -5),
(5, 2, 'Fertilización Inicial', 'NPK 18-5-15-6-2 (Café inicio)', 'Siembra (Al hoyo)', 0.05, 'kg/planta', 'Mezclar con tierra del hoyo.', 0),
(6, 2, 'Control de Hormiga Arriera', 'Cebo Hormiguicida', 'Post-siembra', 10.00, 'g/nido', 'Según presencia, proteger plantas jóvenes.', 5);

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
  `fecha_aplicacion_estimada` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tratamiento_cultivo`
--

INSERT INTO `tratamiento_cultivo` (`id_tratamiento`, `id_cultivo`, `id_tipo_cultivo`, `tipo_tratamiento`, `producto_usado`, `etapas`, `dosis`, `observaciones`, `fecha_aplicacion_estimada`) VALUES
(1, 9, 1, 'Fertilización de Siembra', 'NPK 15-15-15', 'Siembra', 150.00, 'Incorporar al suelo antes o durante la siembra.', '2025-05-16'),
(2, 9, 1, 'Control Herbicida Pre-emergente', 'Pendimetalina', 'Presiembra / Pre-emergencia', 2.50, 'Aplicar sobre suelo húmedo.', '2025-05-14'),
(3, 9, 1, 'Abono Nitrogenado Macollamiento', 'Urea', 'Macollamiento (20-25 días)', 75.00, 'Primera aplicación de nitrógeno.', '2025-06-05'),
(4, 10, 1, 'Fertilización de Siembra', 'NPK 15-15-15', 'Siembra', 150.00, 'Incorporar al suelo antes o durante la siembra.', '2025-05-01'),
(5, 10, 1, 'Control Herbicida Pre-emergente', 'Pendimetalina', 'Presiembra / Pre-emergencia', 2.50, 'Aplicar sobre suelo húmedo.', '2025-04-29'),
(6, 10, 1, 'Abono Nitrogenado Macollamiento', 'Urea', 'Macollamiento (20-25 días)', 75.00, 'Primera aplicación de nitrógeno.', '2025-05-21');

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
('123456789', 'Admin', 'admin@gmail.com', '$2y$10$UZA8liswCKj0VsF.FO3P0uqQ6lY9YUnl/BtfZUG4rP9vPk1TQj8Su', 'a5210347225959aadefa2c02815b1f3ddbe0fb189300fb5488b07dfb3968cd95', NULL, 0, 1, 1),
('987654321', 'User', 'user@gmail.com', 'userpassword', NULL, NULL, 0, 2, 1),
('USR3700', 'fabian', 'edwinfabian.2006@gmail.com', '$2y$10$.BvIDT3txJjQeofgLnjtJeNsviRmnz8yf1x5EtGSZFCPqGeR8snh2', NULL, NULL, 0, 2, 1),
('USR5834', 'shomin', 'tuvieja@gmail.com', '$2y$10$Ex5njLNf1AgkK2EfhWGinuZeIW75vOTN1O73ySQbLInQjP.Xz.ibi', NULL, NULL, 0, 2, 1),
('USR6232', 'cristian', 'criscardona301@gmail.com', '$2y$10$R4FGX1WI4eyQJ8m4cTuQneWLt8QdoJsibipnWr49gvg1jlMm9f6q2', NULL, NULL, 0, 1, 1),
('USR8927', 'david', 'davidfernandoballares@gmail.com', '$2y$10$pOTAHwwlCjGX/mgiGhNPEeYOY6lcbmh3AJzYukCEX4uO3ksvLu0Ii', NULL, NULL, 1, 2, 1);

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
  ADD KEY `id_usuario` (`id_usuario`);

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
-- Indices de la tabla `tipos_cultivo`
--
ALTER TABLE `tipos_cultivo`
  ADD PRIMARY KEY (`id_tipo_cultivo`);

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
  ADD KEY `tratamiento_cultivo_fk_cultivo` (`id_cultivo`);

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
  MODIFY `id_alimentacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `animales`
--
ALTER TABLE `animales`
  MODIFY `id_animal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `cultivos`
--
ALTER TABLE `cultivos`
  MODIFY `id_cultivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
-- AUTO_INCREMENT de la tabla `medicamentos`
--
ALTER TABLE `medicamentos`
  MODIFY `id_medicamento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `municipio`
--
ALTER TABLE `municipio`
  MODIFY `id_municipio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- AUTO_INCREMENT de la tabla `riego`
--
ALTER TABLE `riego`
  MODIFY `id_riego` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `id_rol` tinyint(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `suelo`
--
ALTER TABLE `suelo`
  MODIFY `id_suelo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_cultivo`
--
ALTER TABLE `tipos_cultivo`
  MODIFY `id_tipo_cultivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tipo_producto`
--
ALTER TABLE `tipo_producto`
  MODIFY `id_tipo_producto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tratamientos_predeterminados`
--
ALTER TABLE `tratamientos_predeterminados`
  MODIFY `id_trat_pred` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `tratamiento_cultivo`
--
ALTER TABLE `tratamiento_cultivo`
  MODIFY `id_tratamiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  ADD CONSTRAINT `cultivos_ibfk_3` FOREIGN KEY (`id_municipio`) REFERENCES `municipio` (`id_municipio`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `municipio`
--
ALTER TABLE `municipio`
  ADD CONSTRAINT `municipio_ibfk_1` FOREIGN KEY (`id_departamento`) REFERENCES `departamento` (`id_departamento`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `produccion_cultivo`
--
ALTER TABLE `produccion_cultivo`
  ADD CONSTRAINT `produccion_cultivo_ibfk_1` FOREIGN KEY (`id_cultivo`) REFERENCES `cultivos` (`id_cultivo`) ON DELETE CASCADE ON UPDATE CASCADE;

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
