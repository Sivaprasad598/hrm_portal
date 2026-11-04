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
 * The reports' own fills, in the Epic HR look: the yellow title bar goes dark,
 * the blue heading rows go a light gold, the blue-grey total rows a paler one.
 * Status tints (present green, absent red ...) are kept as they are.
 */
function reportExportTheme(array $cell): array
{
    $map = [
        'FFE600' => ['bg' => '1F1F1F', 'color' => 'FFFFFF'],
        'D9E8FB' => ['bg' => 'F1E4C6', 'color' => '3B2C0E'],
        'C9D7EA' => ['bg' => 'F1E4C6', 'color' => '3B2C0E'],
        'E7EEF7' => ['bg' => 'FAF5EA', 'color' => '3B2C0E'],
    ];
    $bg = strtoupper((string) ($cell['bg'] ?? ''));

    if (isset($map[$bg])) {
        $cell['bg'] = $map[$bg]['bg'];
        $cell['color'] = $map[$bg]['color'];
    }

    return $cell;
}

/** A row the reports draw as a heading: every cell bold, most of them on the heading fill. */
function reportExportIsHeading(array $row): bool
{
    if (count($row) < 2) {
        return false;
    }

    $onFill = 0;
    foreach ($row as $cell) {
        if (empty($cell['bold'])) {
            return false;
        }
        $onFill += in_array(strtoupper((string) ($cell['bg'] ?? '')), ['D9E8FB', 'C9D7EA'], true) ? 1 : 0;
    }

    return $onFill * 2 >= count($row);
}

/** When the file was made, in the company's time. */
function reportExportNow(): string
{
    try {
        $tz = (function_exists('company') && company() ? company()->timezone : null)
            ?: \App\Models\Company::query()->value('timezone') ?: 'Asia/Kolkata';

        return \Carbon\Carbon::now($tz)->format('d M Y, h:i A');
    } catch (\Throwable $e) {
        return date('d M Y, h:i A');
    }
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
        h1 { font-size: 15px; text-align: center; background: #1f1f1f; color: #fff; padding: 9px; margin: 0 0 4px 0; border-bottom: 3px solid #b8892e; }
        .meta { font-size: 8px; color: #777; text-align: right; margin: 0 0 8px 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #d9d9d9; padding: 3px 4px; font-size: 8px; text-align: center; white-space: nowrap; }
    </style></head><body>';
    $html .= '<h1>' . reportExportEsc($title) . '</h1>';
    $html .= '<p class="meta">' . reportExportEsc(reportExportCompany()) . ' &middot; Generated ' . reportExportNow() . '</p>';
    $html .= '<table>';

    foreach ($rows as $r => $row) {
        // The title row is the <h1> above
        if ($r === 0 && count($row) === 1 && ($row[0]['colspan'] ?? 1) > 1) {
            continue;
        }
        $html .= '<tr>';
        foreach ($row as $cell) {
            $cell = reportExportTheme($cell);
            $style = 'padding:3px 4px;border:1px solid #d9d9d9;';
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
    $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
    $spreadsheet->getProperties()->setCreator(reportExportCompany())->setTitle($title);

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(rtrim(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', strtok($title, '—') ?: 'Report'), 0, 31)) ?: 'Report');
    $sheet->setShowGridlines(false);

    $rowIndex = 1;
    $maxCols = 1;
    $headingRow = null;
    $nameColumn = null;
    $dataRows = 0;
    $widths = [];
    $leftCells = [];
    $headingRows = [];

    foreach ($rows as $r => $row) {
        $isTitle = $r === 0 && count($row) === 1 && ($row[0]['colspan'] ?? 1) > 1;
        $isHeading = reportExportIsHeading($row);
        $colIndex = 1;

        foreach ($row as $cell) {
            $cell = reportExportTheme($cell);
            $colspan = max(1, (int) ($cell['colspan'] ?? 1));
            $coord = Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;

            $value = $cell['text'] ?? '';
            $sheet->setCellValue($coord, is_numeric($value) ? $value + 0 : $value);

            $fill = $cell['bg'] ?? null;
            // Plain data rows striped for reading across
            if (!$fill && !$isTitle && !$isHeading && $dataRows % 2 === 1) {
                $fill = 'FAFAFA';
            }
            $style = [];
            if ($fill) {
                $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]];
            }
            if (!empty($cell['color'])) {
                $style['font']['color'] = ['rgb' => $cell['color']];
            }
            if (!empty($cell['bold'])) {
                $style['font']['bold'] = true;
            }
            if (($cell['align'] ?? 'center') === 'left') {
                $leftCells[] = $coord;
            }
            if ($isHeading) {
                $style['alignment']['wrapText'] = true;
                if ($nameColumn === null && stripos((string) $value, 'name') !== false) {
                    $nameColumn = $colIndex;
                }
            }
            if ($style) {
                $sheet->getStyle($coord)->applyFromArray($style);
            }

            if ($colspan > 1) {
                $endCoord = Coordinate::stringFromColumnIndex($colIndex + $colspan - 1) . $rowIndex;
                $sheet->mergeCells($coord . ':' . $endCoord);
            } else {
                // Column width from what is in it (headings wrap, so count their longest word)
                $len = $isHeading ? max(array_map('mb_strlen', explode(' ', (string) $value) ?: [''])) : mb_strlen((string) $value);
                $widths[$colIndex] = max($widths[$colIndex] ?? 0, $len);
            }

            $colIndex += $colspan;
        }

        if ($isTitle) {
            $sheet->getStyle('A' . $rowIndex)->getFont()->setSize(14)->setBold(true);
            $sheet->getRowDimension($rowIndex)->setRowHeight(30);
            $sheet->getStyle('A' . $rowIndex)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK)->getColor()->setRGB('B8892E');

            // Who and when, under the title
            $rowIndex++;
            $sheet->setCellValue('A' . $rowIndex, reportExportCompany() . '  ·  Generated ' . reportExportNow());
            $sheet->mergeCells('A' . $rowIndex . ':' . Coordinate::stringFromColumnIndex(max(1, (int) ($row[0]['colspan'] ?? 1))) . $rowIndex);
            $sheet->getStyle('A' . $rowIndex)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('808080');
            $sheet->getStyle('A' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $rowIndex++;
            continue;
        }

        if ($isHeading) {
            $headingRow ??= $rowIndex;
            $headingRows[] = [$rowIndex, $colIndex - 1];
            $sheet->getRowDimension($rowIndex)->setRowHeight(32);
        } else {
            $dataRows++;
            $sheet->getRowDimension($rowIndex)->setRowHeight(18);
        }

        $maxCols = max($maxCols, $colIndex - 1);
        $rowIndex++;
    }

    // Borders and centring for the whole table at once (cell by cell is what made big reports time out)
    $firstTableRow = $headingRow ?? 3;
    if ($rowIndex - 1 >= $firstTableRow) {
        $range = 'A' . $firstTableRow . ':' . Coordinate::stringFromColumnIndex($maxCols) . ($rowIndex - 1);
        $sheet->getStyle($range)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        // ... but the left-aligned cells keep left
        foreach ($leftCells as $coord) {
            $sheet->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
    }

    foreach ($headingRows as [$r, $lastCol]) {
        $sheet->getStyle('A' . $r . ':' . Coordinate::stringFromColumnIndex($lastCol) . $r)
            ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('B8892E');
    }

    foreach ($widths as $c => $len) {
        $sheet->getColumnDimensionByColumn($c)->setWidth(min(max($len + 3, 6), $c === $nameColumn ? 40 : 18));
    }

    // Heading row: stays in view, with filters; the name column stays too
    if ($headingRow) {
        $freezeCol = Coordinate::stringFromColumnIndex(($nameColumn ?? 0) + 1);
        $sheet->freezePane($freezeCol . ($headingRow + 1));
        $sheet->setAutoFilter('A' . $headingRow . ':' . Coordinate::stringFromColumnIndex($maxCols) . ($rowIndex - 1));
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headingRow, $headingRow);
    }

    // Prints landscape, one page wide
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
        ->setFitToWidth(1)->setFitToHeight(0);
    $sheet->getPageMargins()->setLeft(0.3)->setRight(0.3)->setTop(0.4)->setBottom(0.4);
    $sheet->getHeaderFooter()->setOddFooter('&L' . reportExportCompany() . '&RPage &P of &N');
    $sheet->setSelectedCell('A1');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/** The company the report is for, on the exported file. */
function reportExportCompany(): string
{
    try {
        // company() can come back without its name inside these pages; the table has it
        $name = function_exists('company') && company() ? (string) company()->company_name : '';

        return $name ?: (string) (\Illuminate\Support\Facades\DB::table('companies')->where('id', (int) ($_GET['company_id'] ?? 0))->value('company_name')
            ?: \Illuminate\Support\Facades\DB::table('companies')->value('company_name') ?: 'Attendance');
    } catch (\Throwable $e) {
        return 'Attendance';
    }
}

/**
 * Dispatches to the PDF or Excel exporter based on the ?export= query value.
 * Returns false (does nothing) for any other/missing value so callers can just
 * `if (reportExportDispatch(...)) { exit; }`-free — this function exits itself.
 */
function reportExportDispatch(string $format, string $filename, string $title, array $rows, ?array $customPdfPaper = null): void
{
    // A month's sheet for every employee is a large file to build
    @set_time_limit(300);
    @ini_set('memory_limit', '1024M');

    if ($format === 'pdf') {
        reportExportPdf($filename, $title, $rows, $customPdfPaper);
    } elseif ($format === 'excel') {
        reportExportExcel($filename, $title, $rows);
    }
}
