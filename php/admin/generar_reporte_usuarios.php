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

$termino_busqueda_reporte = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$param_busqueda_like_reporte = null;
if (!empty($termino_busqueda_reporte)) {
    $param_busqueda_like_reporte = "%" . $termino_busqueda_reporte . "%";
}

try {
    // --- OBTENER USUARIOS (NO ADMINS), FILTRADOS SI HAY BÚSQUEDA ---
    $sql_usuarios = "SELECT u.id_usuario, u.nombre AS nombre_usuario, u.email, r.rol AS nombre_rol, e.descripcion AS nombre_estado
                     FROM usuarios u
                     JOIN rol r ON u.id_rol = r.id_rol
                     JOIN estado e ON u.id_estado = e.id_estado
                     WHERE u.id_rol != 1"; // Excluir otros admins
    
    $params_for_execute = [];
    if ($param_busqueda_like_reporte !== null) {
        $sql_usuarios .= " AND (u.nombre LIKE ? OR u.email LIKE ?)";
        $params_for_execute[] = $param_busqueda_like_reporte;
        $params_for_execute[] = $param_busqueda_like_reporte;
    }
    $sql_usuarios .= " ORDER BY u.nombre ASC";
    
    $stmt_usuarios = $pdo->prepare($sql_usuarios);
    $stmt_usuarios->execute($params_for_execute);
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

    // Crear una nueva instancia de Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte de Usuarios');

    // --- ESTILOS ---
    $header_style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']]
    ];
    $data_style = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ];

    // --- TÍTULO DEL REPORTE ---
    $sheet->mergeCells('A1:E1');
    $sheet->setCellValue('A1', 'REPORTE DE USUARIOS (NO ADMINISTRADORES) - GAG');
    $sheet->getStyle('A1')->applyFromArray($header_style);
    $sheet->getStyle('A1')->getFont()->setSize(14);
    $sheet->getRowDimension(1)->setRowHeight(25);

    if (!empty($termino_busqueda_reporte)) {
        $sheet->mergeCells('A2:E2');
        $sheet->setCellValue('A2', 'Filtro de búsqueda aplicado: "' . htmlspecialchars($termino_busqueda_reporte) . '"');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $fila_inicio_datos = 4;
    } else {
        $fila_inicio_datos = 3;
    }
    
    // Encabezados de la tabla
    $sheet->setCellValue('A'.$fila_inicio_datos, 'ID Usuario');
    $sheet->setCellValue('B'.$fila_inicio_datos, 'Nombre Completo');
    $sheet->setCellValue('C'.$fila_inicio_datos, 'Correo Electrónico');
    $sheet->setCellValue('D'.$fila_inicio_datos, 'Rol');
    $sheet->setCellValue('E'.$fila_inicio_datos, 'Estado');
    $sheet->getStyle('A'.$fila_inicio_datos.':E'.$fila_inicio_datos)->applyFromArray($header_style);
    
    $fila_actual = $fila_inicio_datos + 1;

    if (empty($usuarios)) {
        $sheet->mergeCells('A'.$fila_actual.':E'.$fila_actual);
        $sheet->setCellValue('A'.$fila_actual, 'No hay usuarios (no administradores) para reportar con los filtros aplicados.');
        $fila_actual++;
    } else {
        foreach ($usuarios as $usuario) {
            $sheet->setCellValue('A'.$fila_actual, htmlspecialchars($usuario['id_usuario']));
            $sheet->setCellValue('B'.$fila_actual, htmlspecialchars($usuario['nombre_usuario']));
            $sheet->setCellValue('C'.$fila_actual, htmlspecialchars($usuario['email']));
            $sheet->setCellValue('D'.$fila_actual, htmlspecialchars($usuario['nombre_rol']));
            $sheet->setCellValue('E'.$fila_actual, htmlspecialchars($usuario['nombre_estado']));
            $sheet->getStyle('A'.$fila_actual.':E'.$fila_actual)->applyFromArray($data_style);
            $fila_actual++;
        }
    }

    // Ajustar ancho de columnas automáticamente
    foreach (range('A', 'E') as $col) { // Solo hasta la columna E
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Generar y descargar el archivo Excel
    $nombre_archivo = 'Reporte_Usuarios_GAG_' . date('Ymd_His') . '.xlsx';
    
    // Limpiar cualquier salida previa si hubo echos de depuración (importante)
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    if (ob_get_length()) ob_end_clean();
    die("Error de base de datos al generar el reporte de usuarios: " . $e->getMessage());
} catch (Exception $e) { // Captura excepciones de PhpSpreadsheet también
    if (ob_get_length()) ob_end_clean();
    die("Error general al generar el reporte de usuarios: " . $e->getMessage());
}
?>