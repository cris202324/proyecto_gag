<?php
session_start();
require_once '../../conexion.php'; // Sube dos niveles
require_once __DIR__ . '/../../../vendor/autoload.php'; // Sube tres niveles

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 1. --- VERIFICACIÓN Y OBTENCIÓN DE DATOS INICIALES ---
if (!isset($_SESSION['id_usuario'])) {
    die("Acceso no autorizado.");
}
$id_usuario_actual = $_SESSION['id_usuario'];
$nombre_usuario_actual = $_SESSION['nombre_usuario'] ?? 'Usuario';

if (!isset($pdo)) {
    die("Error crítico de conexión a la BD.");
}

try {
    // 2. --- CONSULTA 1: OBTENER TODOS LOS ANIMALES DEL USUARIO ---
    $sql_animales = "SELECT
                        id_animal, nombre_animal, tipo_animal, raza, fecha_nacimiento,
                        sexo, identificador_unico, cantidad, fecha_registro
                    FROM animales
                    WHERE id_usuario = :id_usuario
                    ORDER BY tipo_animal, fecha_registro DESC";
    $stmt_animales = $pdo->prepare($sql_animales);
    $stmt_animales->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_animales->execute();
    $animales_para_reporte = $stmt_animales->fetchAll(PDO::FETCH_ASSOC);

    // 3. --- CONSULTA 2: OBTENER TODO EL HISTORIAL SANITARIO DE ESOS ANIMALES ---
    $sql_sanidad = "SELECT 
                        s.id_animal, a.tipo_animal, a.nombre_animal,
                        s.fecha_aplicacion, s.nombre_producto_aplicado, s.tipo_aplicacion_registrada, 
                        s.dosis_aplicada, s.via_administracion, s.responsable_aplicacion, 
                        s.observaciones, s.fecha_proxima_dosis_sugerida
                    FROM registro_sanitario_animal s
                    JOIN animales a ON s.id_animal = a.id_animal
                    WHERE a.id_usuario = :id_usuario
                    ORDER BY s.id_animal, s.fecha_aplicacion DESC";
    $stmt_sanidad = $pdo->prepare($sql_sanidad);
    $stmt_sanidad->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_STR);
    $stmt_sanidad->execute();
    $sanidad_para_reporte = $stmt_sanidad->fetchAll(PDO::FETCH_ASSOC);


    // 4. --- CREACIÓN DEL DOCUMENTO EXCEL ---
    $spreadsheet = new Spreadsheet();

    // 5. --- DEFINICIÓN DE ESTILOS (AQUÍ PARA QUE ESTÉN DISPONIBLES) ---
    $header_style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0056b3']] // Azul para animales
    ];
    $sub_header_style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6c757d']] // Gris
    ];
    $data_style = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]
    ];


    // ==================================================================
    // 6. --- HOJA 1: LISTA DE ANIMALES ---
    // ==================================================================
    $sheet_animales = $spreadsheet->getActiveSheet();
    $sheet_animales->setTitle('Mis Animales');
    
    $fila_actual_animales = 1;
    $sheet_animales->mergeCells('A'.$fila_actual_animales.':I'.$fila_actual_animales); // Son 9 columnas
    $sheet_animales->setCellValue('A'.$fila_actual_animales, 'REPORTE DE MIS ANIMALES - Usuario: ' . htmlspecialchars($nombre_usuario_actual));
    $sheet_animales->getStyle('A'.$fila_actual_animales)->applyFromArray($header_style);
    $sheet_animales->getRowDimension($fila_actual_animales)->setRowHeight(25);
    $fila_actual_animales += 2;

    if (empty($animales_para_reporte)) {
        $sheet_animales->setCellValue('A'.$fila_actual_animales, 'No tienes animales registrados para reportar.');
    } else {
        $header_tabla_animales = ['ID Animal', 'Tipo Animal', 'Nombre/Lote', 'ID Adicional', 'Raza', 'Sexo', 'F. Nacimiento', 'Cantidad', 'F. Registro'];
        $col_letra = 'A';
        foreach ($header_tabla_animales as $header_col) {
            $sheet_animales->setCellValue($col_letra.$fila_actual_animales, $header_col);
            $col_letra++;
        }
        $ultima_col_animales = chr(ord('A') + count($header_tabla_animales) - 1);
        $sheet_animales->getStyle('A'.$fila_actual_animales.':'.$ultima_col_animales.$fila_actual_animales)->applyFromArray($sub_header_style);
        $fila_actual_animales++;

        foreach ($animales_para_reporte as $animal_item) {
            $sheet_animales->setCellValue('A'.$fila_actual_animales, $animal_item['id_animal']);
            $sheet_animales->setCellValue('B'.$fila_actual_animales, htmlspecialchars($animal_item['tipo_animal']));
            $sheet_animales->setCellValue('C'.$fila_actual_animales, htmlspecialchars($animal_item['nombre_animal'] ?: 'N/A'));
            $sheet_animales->setCellValue('D'.$fila_actual_animales, htmlspecialchars($animal_item['identificador_unico'] ?: 'N/A'));
            $sheet_animales->setCellValue('E'.$fila_actual_animales, htmlspecialchars($animal_item['raza'] ?: 'N/A'));
            $sheet_animales->setCellValue('F'.$fila_actual_animales, htmlspecialchars($animal_item['sexo']));
            $sheet_animales->setCellValue('G'.$fila_actual_animales, $animal_item['fecha_nacimiento'] ? date("d/m/Y", strtotime($animal_item['fecha_nacimiento'])) : 'N/A');
            $sheet_animales->setCellValue('H'.$fila_actual_animales, $animal_item['cantidad']);
            $sheet_animales->setCellValue('I'.$fila_actual_animales, date("d/m/Y", strtotime($animal_item['fecha_registro'])));
            $sheet_animales->getStyle('A'.$fila_actual_animales.':'.$ultima_col_animales.$fila_actual_animales)->applyFromArray($data_style);
            $fila_actual_animales++;
        }
    }
    foreach (range('A', 'I') as $col) { $sheet_animales->getColumnDimension($col)->setAutoSize(true); }


    // ==================================================================
    // 7. --- HOJA 2: HISTORIAL SANITARIO ---
    // ==================================================================
    $sheet_sanidad = $spreadsheet->createSheet();
    $sheet_sanidad->setTitle('Historial Sanitario');

    $fila_actual_sanidad = 1;
    $sheet_sanidad->mergeCells('A'.$fila_actual_sanidad.':H'.$fila_actual_sanidad);
    $sheet_sanidad->setCellValue('A'.$fila_actual_sanidad, 'HISTORIAL SANITARIO DE TODOS LOS ANIMALES');
    
    $header_style_sanidad = $header_style;
    $header_style_sanidad['fill']['startColor']['rgb'] = 'dc3545'; // Rojo
    $sheet_sanidad->getStyle('A'.$fila_actual_sanidad)->applyFromArray($header_style_sanidad);
    $sheet_sanidad->getRowDimension($fila_actual_sanidad)->setRowHeight(25);
    $fila_actual_sanidad += 2;

    if (empty($sanidad_para_reporte)) {
        $sheet_sanidad->setCellValue('A'.$fila_actual_sanidad, 'No hay registros sanitarios para tus animales.');
    } else {
        $header_tabla_sanidad = ['ID Animal', 'Animal', 'Fecha Aplic.', 'Producto Aplicado', 'Tipo', 'Dosis', 'Vía', 'Próx. Dosis'];
        $col_letra = 'A';
        foreach ($header_tabla_sanidad as $header_col) {
            $sheet_sanidad->setCellValue($col_letra.$fila_actual_sanidad, $header_col);
            $col_letra++;
        }
        $ultima_col_sanidad = chr(ord('A') + count($header_tabla_sanidad) - 1);
        $sheet_sanidad->getStyle('A'.$fila_actual_sanidad.':'.$ultima_col_sanidad.$fila_actual_sanidad)->applyFromArray($sub_header_style);
        $fila_actual_sanidad++;

        foreach ($sanidad_para_reporte as $reg) {
            $nombre_completo_animal = $reg['tipo_animal'] . (!empty($reg['nombre_animal']) ? ' "' . $reg['nombre_animal'] . '"' : '');
            $sheet_sanidad->setCellValue('A'.$fila_actual_sanidad, $reg['id_animal']);
            $sheet_sanidad->setCellValue('B'.$fila_actual_sanidad, htmlspecialchars($nombre_completo_animal));
            $sheet_sanidad->setCellValue('C'.$fila_actual_sanidad, date("d/m/Y", strtotime($reg['fecha_aplicacion'])));
            $sheet_sanidad->setCellValue('D'.$fila_actual_sanidad, htmlspecialchars($reg['nombre_producto_aplicado']));
            $sheet_sanidad->setCellValue('E'.$fila_actual_sanidad, htmlspecialchars($reg['tipo_aplicacion_registrada']));
            $sheet_sanidad->setCellValue('F'.$fila_actual_sanidad, htmlspecialchars($reg['dosis_aplicada'] ?: '-'));
            $sheet_sanidad->setCellValue('G'.$fila_actual_sanidad, htmlspecialchars($reg['via_administracion'] ?: '-'));
            $sheet_sanidad->setCellValue('H'.$fila_actual_sanidad, $reg['fecha_proxima_dosis_sugerida'] ? date("d/m/Y", strtotime($reg['fecha_proxima_dosis_sugerida'])) : '-');
            
            $sheet_sanidad->getStyle('A'.$fila_actual_sanidad.':'.$ultima_col_sanidad.$fila_actual_sanidad)->applyFromArray($data_style);
            $fila_actual_sanidad++;
        }
    }
    foreach (range('A', 'H') as $col) { $sheet_sanidad->getColumnDimension($col)->setAutoSize(true); }


    // 8. --- FINALIZAR Y ENVIAR ---
    $spreadsheet->setActiveSheetIndex(0);

    $nombre_archivo = 'Reporte_Completo_Animales_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log("Error en generar_reporte_mis_animales.php: " . $e->getMessage());
    die("Error general al generar el reporte.");
}
?>