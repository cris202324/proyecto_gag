<?php
session_start();
require_once '../conexion.php';
require_once 'C:/xampp/htdocs/proyecto_gag/vendor/autoload.php';

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

try {
    // Consulta para obtener todos los tickets con toda la información relevante
    $sql_reporte = "SELECT 
                        t.id_ticket,
                        t.asunto,
                        t.mensaje_usuario, 
                        DATE_FORMAT(t.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion_f,
                        t.estado_ticket,
                        DATE_FORMAT(t.ultima_actualizacion, '%d/%m/%Y %H:%i') as ultima_actualizacion_f,
                        u.nombre AS nombre_usuario,
                        u.email AS email_usuario,
                        (SELECT GROUP_CONCAT(CONCAT(DATE_FORMAT(r.fecha_respuesta, '%d/%m/%Y %H:%i'), ' (Admin: ', ua.nombre, '): ', r.mensaje_admin) SEPARATOR '\n---\n') 
                         FROM respuestas_soporte r 
                         JOIN usuarios ua ON r.id_admin = ua.id_usuario 
                         WHERE r.id_ticket = t.id_ticket
                         ORDER BY r.fecha_respuesta ASC) as historial_respuestas
                    FROM tickets_soporte t
                    JOIN usuarios u ON t.id_usuario = u.id_usuario
                    ORDER BY t.id_ticket DESC";
    
    $stmt_reporte = $pdo->prepare($sql_reporte);
    $stmt_reporte->execute();
    $tickets_para_reporte = $stmt_reporte->fetchAll(PDO::FETCH_ASSOC);

    // --- Iniciar la creación del Excel ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte de Tickets');

    // --- Estilos ---
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

    // Título del Reporte
    $sheet->mergeCells('A'.$fila_actual.':H'.$fila_actual);
    $sheet->setCellValue('A'.$fila_actual, 'REPORTE DE TICKETS DE SOPORTE - GAG');
    $sheet->getStyle('A'.$fila_actual)->applyFromArray($header_style_array);
    $sheet->getRowDimension($fila_actual)->setRowHeight(25);
    $fila_actual += 2;

    if (empty($tickets_para_reporte)) {
        $sheet->setCellValue('A'.$fila_actual, 'No hay tickets para reportar.');
    } else {
        // Encabezados de la tabla
        $header_tabla = [
            'ID Ticket', 'Estado', 'Fecha Creación', 'Últ. Actualización', 
            'Usuario', 'Email Usuario', 'Asunto y Mensaje', 'Historial de Respuestas'
        ];
        $col_letra = 'A';
        foreach ($header_tabla as $header_col) {
            $sheet->setCellValue($col_letra.$fila_actual, $header_col);
            $col_letra++;
        }
        $ultima_col_header = chr(ord('A') + count($header_tabla) - 1);
        $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($sub_header_style_array);
        $fila_actual++;

        // Datos de los tickets
        foreach ($tickets_para_reporte as $ticket) {
            $asunto_mensaje = "Asunto: " . $ticket['asunto'] . "\n---\n" . "Mensaje: " . $ticket['mensaje_usuario'];

            $sheet->setCellValue('A'.$fila_actual, $ticket['id_ticket']);
            $sheet->setCellValue('B'.$fila_actual, $ticket['estado_ticket']);
            $sheet->setCellValue('C'.$fila_actual, $ticket['fecha_creacion_f']);
            $sheet->setCellValue('D'.$fila_actual, $ticket['ultima_actualizacion_f']);
            $sheet->setCellValue('E'.$fila_actual, $ticket['nombre_usuario']);
            $sheet->setCellValue('F'.$fila_actual, $ticket['email_usuario']);
            $sheet->setCellValue('G'.$fila_actual, $asunto_mensaje);
            $sheet->setCellValue('H'.$fila_actual, $ticket['historial_respuestas'] ?: 'Sin respuestas.');
            
            $sheet->getStyle('A'.$fila_actual.':'.$ultima_col_header.$fila_actual)->applyFromArray($data_style_array);
            $sheet->getRowDimension($fila_actual)->setRowHeight(-1);
            
            $fila_actual++;
        }
    }

    // Ajustar ancho de columnas
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        if ($col === 'G' || $col === 'H') {
            $sheet->getColumnDimension($col)->setWidth(50);
        } else {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
    $sheet->getStyle('G:H')->getAlignment()->setWrapText(true);


    $nombre_archivo = 'Reporte_Tickets_Soporte_GAG_' . date('Ymd_His') . '.xlsx';

    // Headers para la descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    error_log("Error PDO en admin_generar_reporte_tickets.php: " . $e->getMessage());
    die("Error de base de datos al generar el reporte.");
} catch (Exception $e) {
    error_log("Error general en admin_generar_reporte_tickets.php: " . $e->getMessage());
    die("Error general al generar el reporte.");
}
?>