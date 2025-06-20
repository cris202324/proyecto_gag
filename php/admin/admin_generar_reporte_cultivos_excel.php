<?php
session_start();
require_once '../conexion.php';
require_once __DIR__ . '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

if (!isset($_SESSION['id_usuario']) || (isset($_SESSION['rol']) && $_SESSION['rol'] != 1)) {
    die("Acceso no autorizado.");
}

if (!isset($pdo)) {
    die("Error crítico: La conexión a la base de datos no está disponible.");
}

// Determinar el rango del reporte
$rango_reporte = isset($_GET['rango_reporte']) ? $_GET['rango_reporte'] : 'general'; // 'general' o 'mes_actual'
$titulo_extra_reporte = "";
$condiciones_sql_extra = "";
$params_sql_extra = [];

if ($rango_reporte === 'mes_actual') {
    $primer_dia_mes_actual = date('Y-m-01');
    $ultimo_dia_mes_actual = date('Y-m-t'); // 't' da el último día del mes actual
    
    $titulo_extra_reporte = " (Iniciados en " . date('F Y') . ")"; // Ej: (Iniciados en Junio 2024)
    $condiciones_sql_extra = " AND c.fecha_inicio BETWEEN :fecha_inicio_mes AND :fecha_fin_mes";
    $params_sql_extra[':fecha_inicio_mes'] = $primer_dia_mes_actual;
    $params_sql_extra[':fecha_fin_mes'] = $ultimo_dia_mes_actual;
}


try {
    $sql_reporte = "SELECT
                        c.id_cultivo, c.fecha_inicio, c.fecha_fin AS fecha_fin_registrada,
                        c.area_hectarea, tc.nombre_cultivo, m.nombre AS nombre_municipio,
                        u.nombre AS nombre_usuario, u.id_usuario AS id_usuario_cultivo,
                        u.email AS email_usuario, ecd.nombre_estado AS estado_actual_cultivo
                    FROM cultivos c
                    JOIN tipos_cultivo tc ON c.id_tipo_cultivo = tc.id_tipo_cultivo
                    JOIN municipio m ON c.id_municipio = m.id_municipio
                    JOIN usuarios u ON c.id_usuario = u.id_usuario
                    LEFT JOIN estado_cultivo_definiciones ecd ON c.id_estado_cultivo = ecd.id_estado_cultivo
                    WHERE 1=1" . $condiciones_sql_extra . " -- El 1=1 es para poder concatenar fácil el AND
                    ORDER BY u.nombre ASC, c.fecha_inicio DESC";

    $stmt_reporte = $pdo->prepare($sql_reporte);
    // Bindear parámetros extra si existen
    foreach ($params_sql_extra as $key => $val) {
        $stmt_reporte->bindValue($key, $val);
    }
    $stmt_reporte->execute();
    $cultivos_para_reporte = $stmt_reporte->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Cultivos' . ($rango_reporte === 'mes_actual' ? '_Mes' : '_General'));


    // --- ESTILOS (DEFINIDOS AQUÍ) ---
    $header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']]
    ];
    $sub_header_style_array = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '88C057']]
    ];
    $data_style_array = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]
    ];


    $fila_actual = 1;

    $sheet->mergeCells('A'.$fila_actual.':I'.$fila_actual);
    $sheet->setCellValue('A'.$fila_actual, 'REPORTE DE CULTIVOS REGISTRADOS - GAG' . $titulo_extra_reporte);
    $sheet->getStyle('A'.$fila_actual)->applyFromArray($header_style_array);
    $sheet->getRowDimension($fila_actual)->setRowHeight(25);
    $fila_actual += 2;

    if (empty($cultivos_para_reporte)) {
        $sheet->setCellValue('A'.$fila_actual, 'No hay cultivos para los criterios seleccionados.');
        $fila_actual++; // Incrementar para que no se sobreescriba si se añade más contenido después
    } else {
        $header_tabla = ['ID Cultivo', 'Nombre Cultivo', 'Usuario', 'Email Usuario', 'Fecha Inicio', 'Fecha Fin', 'Área (ha)', 'Municipio', 'Estado'];
        $col_letra = 'A';
        foreach ($header_tabla as $header_col) {
            $sheet->setCellValue($col_letra.$fila_actual, $header_col);
            $col_letra++;
        }
        $ultima_col_header = chr(ord('A') + count($header_tabla) - 1);
        $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($sub_header_style_array);
        $fila_actual++;

        foreach ($cultivos_para_reporte as $cultivo_item) {
            $sheet->setCellValue('A'.$fila_actual, htmlspecialchars($cultivo_item['id_cultivo']));
            $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($cultivo_item['nombre_cultivo']));
            $sheet->setCellValue('C'.$fila_actual, htmlspecialchars($cultivo_item['nombre_usuario']));
            $sheet->setCellValue('D'.$fila_actual, htmlspecialchars($cultivo_item['email_usuario']));
            $sheet->setCellValue('E'.$fila_actual, htmlspecialchars(date("d/m/Y", strtotime($cultivo_item['fecha_inicio']))));
            $sheet->setCellValue('F'.$fila_actual, htmlspecialchars(date("d/m/Y", strtotime($cultivo_item['fecha_fin_registrada']))));
            $sheet->setCellValue('G'.$fila_actual, htmlspecialchars($cultivo_item['area_hectarea']));
            $sheet->setCellValue('H'.$fila_actual, htmlspecialchars($cultivo_item['nombre_municipio']));
            $sheet->setCellValue('I'.$fila_actual, htmlspecialchars($cultivo_item['estado_actual_cultivo'] ?: 'No definido'));
            
            $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($data_style_array);
            $fila_actual++;
        }
    }

    // Ajustar ancho de columnas automáticamente
    // Es mejor hacerlo después de haber escrito todos los datos para un cálculo más preciso.
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $nombre_archivo_sufijo = ($rango_reporte === 'mes_actual') ? '_MesActual' : '_General';
    $nombre_archivo = 'Reporte_Cultivos_GAG' . $nombre_archivo_sufijo . '_' . date('Ymd_His') . '.xlsx';

    // Headers para la descarga del archivo
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit; 

} catch (PDOException $e) {
    error_log("Error PDO en admin_generar_reporte_cultivos_excel.php: " . $e->getMessage());

    die("Error de base de datos al generar el reporte. Por favor, revise los logs del servidor.");
} catch (Exception $e) {
    error_log("Error general en admin_generar_reporte_cultivos_excel.php: " . $e->getMessage() . " en la línea " . $e->getLine() . "\nStack trace: " . $e->getTraceAsString());
    die("Error general al generar el reporte. Por favor, revise los logs del servidor.");
}
?>