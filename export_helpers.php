<?php
/**
 * Shared PDF/Excel export engine for the standalone attendance report pages
 * (Monthly_attendance.php, Total_Monthly_Attendance.php, annual_attendance.php).
 *
 * Every report builds its table as a plain array of rows, where each row is an
 * array of cells: ['text' => string, 'bg' => 'RRGGBB'|null, 'color' => 'RRGGBB'|null,
 * 'bold' => bool, 'colspan' => int, 'align' => 'left'|'center']. That single
 * structure drives both the PDF (Dompdf) and Excel (PhpSpreadsheet) output, so the
 * exported files keep the same colour-coding as the on-screen table.
 */

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function reportExportEsc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * @param array<int, array<int, array{text?: mixed, bg?: ?string, color?: ?string, bold?: bool, colspan?: int, align?: string}>> $rows
 * @param array{0:int,1:int,2:int,3:int}|null $customPaper Dompdf paper box [0, 0, widthPt, heightPt] for extra-wide grids.
 */
function reportExportPdf(string $filename, string $title, array $rows, ?array $customPaper = null): void
{
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; }
        h1 { font-size: 16px; text-align: center; background: #ffe600; padding: 8px; margin: 0 0 10px 0; border-radius: 4px; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #999; padding: 3px 4px; font-size: 8px; text-align: center; white-space: nowrap; }
    </style></head><body>';
    $html .= '<h1>' . reportExportEsc($title) . '</h1>';
    $html .= '<table>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $style = 'padding:3px 4px;border:1px solid #999;';
            if (!empty($cell['bg'])) {
                $style .= 'background-color:#' . $cell['bg'] . ';';
            }
            if (!empty($cell['color'])) {
                $style .= 'color:#' . $cell['color'] . ';';
            }
            if (!empty($cell['bold'])) {
                $style .= 'font-weight:bold;';
            }
            $style .= 'text-align:' . (($cell['align'] ?? 'center') === 'left' ? 'left' : 'center') . ';';
            $colspan = $cell['colspan'] ?? 1;
            $html .= '<td style="' . $style . '"' . ($colspan > 1 ? ' colspan="' . (int) $colspan . '"' : '') . '>'
                . reportExportEsc($cell['text'] ?? '') . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</table></body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper($customPaper ?? 'A4', $customPaper ? 'landscape' : 'landscape');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    echo $dompdf->output();
    exit;
}

/**
 * @param array<int, array<int, array{text?: mixed, bg?: ?string, color?: ?string, bold?: bool, colspan?: int, align?: string}>> $rows
 */
function reportExportExcel(string $filename, string $title, array $rows): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Report');

    $rowIndex = 1;
    $maxCols = 1;

    foreach ($rows as $row) {
        $colIndex = 1;
        foreach ($row as $cell) {
            $colspan = max(1, (int) ($cell['colspan'] ?? 1));
            $coord = Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;

            $value = $cell['text'] ?? '';
            $sheet->setCellValue($coord, is_numeric($value) ? $value + 0 : $value);

            $cellStyle = $sheet->getStyle($coord);
            if (!empty($cell['bg'])) {
                $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($cell['bg']);
            }
            if (!empty($cell['color'])) {
                $cellStyle->getFont()->getColor()->setRGB($cell['color']);
            }
            if (!empty($cell['bold'])) {
                $cellStyle->getFont()->setBold(true);
            }
            $cellStyle->getAlignment()->setHorizontal(
                ($cell['align'] ?? 'center') === 'left' ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_CENTER
            );
            $cellStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFBBBBBB'));

            if ($colspan > 1) {
                $endCoord = Coordinate::stringFromColumnIndex($colIndex + $colspan - 1) . $rowIndex;
                $sheet->mergeCells($coord . ':' . $endCoord);
            }

            $colIndex += $colspan;
        }

        $maxCols = max($maxCols, $colIndex - 1);
        $rowIndex++;
    }

    for ($c = 1; $c <= $maxCols; $c++) {
        $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Dispatches to the PDF or Excel exporter based on the ?export= query value.
 * Returns false (does nothing) for any other/missing value so callers can just
 * `if (reportExportDispatch(...)) { exit; }`-free — this function exits itself.
 */
function reportExportDispatch(string $format, string $filename, string $title, array $rows, ?array $customPdfPaper = null): void
{
    if ($format === 'pdf') {
        reportExportPdf($filename, $title, $rows, $customPdfPaper);
    } elseif ($format === 'excel') {
        reportExportExcel($filename, $title, $rows);
    }
}
