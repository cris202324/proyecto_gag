<?php
session_start();
require_once '../conexion.php'; // $pdo

// Cargar el autoloader de Composer
require_once 'C:/xampp/htdocs/proyecto_gag/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    die("Acceso no autorizado.");
}

if (!isset($pdo)) {
    die("Error crítico: La conexión a la base de datos no está disponible.");
}

try {
    // --- 1. OBTENER TODOS LOS USUARIOS (NO ADMINS) ---
    $stmt_usuarios = $pdo->query("SELECT u.id_usuario, u.nombre AS nombre_usuario, u.email, r.rol AS nombre_rol, e.descripcion AS nombre_estado
                                  FROM usuarios u
                                  JOIN rol r ON u.id_rol = r.id_rol
                                  JOIN estado e ON u.id_estado = e.id_estado
                                  WHERE u.id_rol != 1 
                                  ORDER BY u.nombre ASC");
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

    // Crear una nueva instancia de Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte General GAG');

    // --- ESTILOS PARA ENCABEZADOS ---
    $header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']] // Verde GAG
    ];
    $sub_header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '88C057']] // Verde más claro
    ];
    $data_style_array = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true] // Alinear arriba y ajustar texto
    ];

    // --- TÍTULO DEL REPORTE ---
    $sheet->mergeCells('A1:K1'); // Ajusta el rango de celdas según el número de columnas finales
    $sheet->setCellValue('A1', 'REPORTE GENERAL - GESTIÓN AGRÍCOLA Y GANADERA (GAG)');
    $sheet->getStyle('A1')->applyFromArray($header_style_array);
    $sheet->getStyle('A1')->getFont()->setSize(16);
    $sheet->getRowDimension(1)->setRowHeight(30);
    
    $fila_actual = 3; // Empezar a escribir datos desde esta fila

    if (empty($usuarios)) {
        $sheet->setCellValue('A'.$fila_actual, 'No hay usuarios (no administradores) para reportar.');
        $fila_actual++;
    } else {
        foreach ($usuarios as $usuario) {
            // --- DATOS DEL USUARIO ---
            $sheet->mergeCells('A'.$fila_actual.':K'.$fila_actual);
            $sheet->setCellValue('A'.$fila_actual, 'Usuario: ' . htmlspecialchars($usuario['nombre_usuario']) . ' (ID: ' . htmlspecialchars($usuario['id_usuario']) . ')');
            $sheet->getStyle('A'.$fila_actual)->applyFromArray($sub_header_style_array);
            $sheet->getStyle('A'.$fila_actual)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $fila_actual++;

            $sheet->setCellValue('A'.$fila_actual, 'Email:');
            $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($usuario['email']));
            $sheet->setCellValue('C'.$fila_actual, 'Rol:');
            $sheet->setCellValue('D'.$fila_actual, htmlspecialchars($usuario['nombre_rol']));
            $sheet->setCellValue('E'.$fila_actual, 'Estado:');
            $sheet->setCellValue('F'.$fila_actual, htmlspecialchars($usuario['nombre_estado']));
            $sheet->getStyle('A'.$fila_actual.':F'.$fila_actual)->getFont()->setBold(true);
            $fila_actual++;
            $fila_actual++; // Espacio antes de cultivos

            // --- CULTIVOS DEL USUARIO ---
            $stmt_cultivos = $pdo->prepare("SELECT tc.nombre_cultivo, c.fecha_inicio, c.fecha_fin, c.area_hectarea, m.nombre AS nombre_municipio
                                            FROM cultivos c
                                            JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                                            JOIN municipio m ON c.id_municipio = m.id_municipio
                                            WHERE c.id_usuario = :id_usuario ORDER BY c.fecha_inicio DESC");
            $stmt_cultivos->bindParam(':id_usuario', $usuario['id_usuario']);
            $stmt_cultivos->execute();
            $cultivos_del_usuario = $stmt_cultivos->fetchAll(PDO::FETCH_ASSOC);

            if ($cultivos_del_usuario) {
                $sheet->setCellValue('A'.$fila_actual, 'CULTIVOS DEL USUARIO');
                $sheet->getStyle('A'.$fila_actual)->getFont()->setBold(true)->setSize(12);
                $fila_actual++;
                // Encabezados de la tabla de cultivos
                $sheet->setCellValue('A'.$fila_actual, 'Nombre Cultivo');
                $sheet->setCellValue('B'.$fila_actual, 'Fecha Inicio');
                $sheet->setCellValue('C'.$fila_actual, 'Fecha Fin Estimada');
                $sheet->setCellValue('D'.$fila_actual, 'Área (ha)');
                $sheet->setCellValue('E'.$fila_actual, 'Municipio');
                $sheet->getStyle('A'.$fila_actual.':E'.$fila_actual)->applyFromArray($sub_header_style_array);
                $fila_actual++;

                foreach ($cultivos_del_usuario as $cultivo) {
                    $sheet->setCellValue('A'.$fila_actual, htmlspecialchars($cultivo['nombre_cultivo']));
                    $sheet->setCellValue('B'.$fila_actual, htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_inicio']))));
                    $sheet->setCellValue('C'.$fila_actual, htmlspecialchars(date("d/m/Y", strtotime($cultivo['fecha_fin']))));
                    $sheet->setCellValue('D'.$fila_actual, htmlspecialchars($cultivo['area_hectarea']));
                    $sheet->setCellValue('E'.$fila_actual, htmlspecialchars($cultivo['nombre_municipio']));
                    $sheet->getStyle('A'.$fila_actual.':E'.$fila_actual)->applyFromArray($data_style_array);
                    $fila_actual++;
                }
            } else {
                $sheet->setCellValue('A'.$fila_actual, 'Este usuario no tiene cultivos registrados.');
                $fila_actual++;
            }
            $fila_actual++; // Espacio antes de animales

            // --- ANIMALES DEL USUARIO ---
            $stmt_animales = $pdo->prepare("SELECT nombre_animal, tipo_animal, raza, fecha_nacimiento, sexo, identificador_unico, DATE_FORMAT(fecha_registro, '%d/%m/%Y') as fecha_reg
                                           FROM animales 
                                           WHERE id_usuario = :id_usuario ORDER BY fecha_registro DESC");
            $stmt_animales->bindParam(':id_usuario', $usuario['id_usuario']);
            $stmt_animales->execute();
            $animales_del_usuario = $stmt_animales->fetchAll(PDO::FETCH_ASSOC);

            if ($animales_del_usuario) {
                $sheet->setCellValue('A'.$fila_actual, 'ANIMALES DEL USUARIO');
                $sheet->getStyle('A'.$fila_actual)->getFont()->setBold(true)->setSize(12);
                $fila_actual++;
                // Encabezados de la tabla de animales
                $sheet->setCellValue('A'.$fila_actual, 'Nombre Animal');
                $sheet->setCellValue('B'.$fila_actual, 'Tipo');
                $sheet->setCellValue('C'.$fila_actual, 'Raza');
                $sheet->setCellValue('D'.$fila_actual, 'F. Nacimiento');
                $sheet->setCellValue('E'.$fila_actual, 'Sexo');
                $sheet->setCellValue('F'.$fila_actual, 'ID Único');
                $sheet->setCellValue('G'.$fila_actual, 'F. Registro');
                $sheet->getStyle('A'.$fila_actual.':G'.$fila_actual)->applyFromArray($sub_header_style_array);
                $fila_actual++;

                foreach ($animales_del_usuario as $animal) {
                    $sheet->setCellValue('A'.$fila_actual, htmlspecialchars($animal['nombre_animal'] ?: 'N/A'));
                    $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($animal['tipo_animal']));
                    $sheet->setCellValue('C'.$fila_actual, htmlspecialchars($animal['raza'] ?: 'N/A'));
                    $sheet->setCellValue('D'.$fila_actual, $animal['fecha_nacimiento'] ? htmlspecialchars(date("d/m/Y", strtotime($animal['fecha_nacimiento']))) : 'N/A');
                    $sheet->setCellValue('E'.$fila_actual, htmlspecialchars($animal['sexo']));
                    $sheet->setCellValue('F'.$fila_actual, htmlspecialchars($animal['identificador_unico'] ?: 'N/A'));
                    $sheet->setCellValue('G'.$fila_actual, htmlspecialchars($animal['fecha_reg']));
                    $sheet->getStyle('A'.$fila_actual.':G'.$fila_actual)->applyFromArray($data_style_array);
                    $fila_actual++;
                }
            } else {
                $sheet->setCellValue('A'.$fila_actual, 'Este usuario no tiene animales registrados.');
                $fila_actual++;
            }
            $fila_actual++; // Espacio antes del siguiente usuario
            $fila_actual++; // Doble espacio para mayor separación
        }
    }

    // --- AJUSTAR ANCHO DE COLUMNAS AUTOMÁTICAMENTE ---
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // --- GENERAR Y DESCARGAR EL ARCHIVO EXCEL ---
    $nombre_archivo = 'Reporte_GAG_' . date('Ymd_His') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    die("Error de base de datos al generar el reporte: " . $e->getMessage());
} catch (Exception $e) {
    die("Error general al generar el reporte: " . $e->getMessage());
}