<?php

// Read-only extraction. No grades, formulas, student identities or Excel caches
// are copied into the versioned layout. Run with the original XLSX as argv[1].
require dirname(__DIR__, 2).'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Drawing;

$book = IOFactory::load($argv[1]);
$output = $argv[2] ?? dirname(__DIR__, 2).'/resources/report-cards';
if (is_file($output.'/layout-v1.json')) {
    throw new RuntimeException('Layout versi lama tidak boleh ditimpa. Berikan direktori keluaran baru sebagai argumen kedua.');
}
if (! is_dir($output)) {
    mkdir($output, 0755, true);
}
$layouts = [];
foreach ([1 => 56, 2 => 58] as $semester => $lastRow) {
    $sheet = $book->getSheetByName('CETAK SMT'.$semester);
    $xs = [1 => 0];
    $ys = [4 => 0];
    for ($col = 1; $col <= 17; $col++) {
        $width = $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->getWidth();
        $xs[$col + 1] = $xs[$col] + Drawing::cellDimensionToPixels($width < 0 ? 9.14 : $width, $book->getDefaultStyle()->getFont());
    }
    for ($row = 4; $row <= $lastRow; $row++) {
        $height = $sheet->getRowDimension($row)->getRowHeight();
        $ys[$row + 1] = $ys[$row] + ($height < 0 ? 15 : $height) * 4 / 3;
    }
    $merged = [];
    $hidden = [];
    foreach ($sheet->getMergeCells() as $range) {
        [[$c1,$r1],[$c2,$r2]] = Coordinate::rangeBoundaries($range);
        $merged[Coordinate::stringFromColumnIndex($c1).$r1] = [$c2, $r2];
        for ($r = $r1; $r <= $r2; $r++) {
            for ($c = $c1; $c <= $c2; $c++) {
                if ($r !== $r1 || $c !== $c1) {
                    $hidden["$c:$r"] = true;
                }
            }
        }
    }
    $cells = [];
    for ($row = 4; $row <= $lastRow; $row++) {
        for ($col = 1; $col <= 17; $col++) {
            if (isset($hidden["$col:$row"])) {
                continue;
            }
            $address = Coordinate::stringFromColumnIndex($col).$row;
            [$endCol,$endRow] = $merged[$address] ?? [$col, $row];
            $cell = $sheet->getCell($address);
            $style = $cell->getStyle();
            $font = $style->getFont();
            $value = $cell->isFormula() ? '' : (string) $cell->getValue();
            if (($row >= 16 && $row <= 21) || in_array($address, ['D8', 'D9', 'D10', 'Q8', 'Q9', 'A26', 'J26', 'J28', 'J29', 'J30', 'A33', 'J33', 'J35', 'A36', 'F39', 'F40', 'F41', 'N42', 'N43', 'N47', 'N49', 'N53', 'N55', 'H56', 'H58'], true)) {
                $value = '';
            }
            // N49 is a date only in semester 2; keep semester 1's role label.
            if ($semester === 1 && $address === 'N49') {
                $value = 'Wali Kelas';
            }
            $borders = [];
            foreach (['top', 'bottom', 'left', 'right'] as $side) {
                $styles = [];
                for ($r = $row; $r <= $endRow; $r++) {
                    for ($c = $col; $c <= $endCol; $c++) {
                        if (($side === 'top' && $r !== $row) || ($side === 'bottom' && $r !== $endRow) || ($side === 'left' && $c !== $col) || ($side === 'right' && $c !== $endCol)) {
                            continue;
                        }
                        $edge = $sheet->getStyle([$c, $r])->getBorders()->{'get'.ucfirst($side)}();
                        if ($edge->getBorderStyle() !== 'none') {
                            $styles[] = $edge->getBorderStyle();
                        }
                    }
                }
                $borders[$side] = in_array('medium', $styles, true) || in_array('thick', $styles, true) ? 2 : (in_array('double', $styles, true) ? 3 : ($styles ? 1 : 0));
            }
            $fill = $style->getFill();
            $cells[] = ['address' => $address, 'row' => $row, 'col' => $col, 'x' => $xs[$col], 'y' => $ys[$row], 'width' => $xs[$endCol + 1] - $xs[$col], 'height' => $ys[$endRow + 1] - $ys[$row], 'text' => $value,
                'font' => $font->getName(), 'size' => $font->getSize(), 'bold' => $font->getBold(), 'italic' => $font->getItalic(), 'color' => substr($font->getColor()->getARGB() ?? 'FF000000', -6),
                'align' => $style->getAlignment()->getHorizontal(), 'vertical' => $style->getAlignment()->getVertical(), 'wrap' => $style->getAlignment()->getWrapText(),
                'fill' => $fill->getFillType() === 'solid' ? substr($fill->getStartColor()->getARGB(), -6) : null, 'borders' => $borders];
        }
    }
    $images = [];
    foreach ($sheet->getDrawingCollection() as $i => $drawing) {
        [$c,$r] = Coordinate::coordinateFromString($drawing->getCoordinates());
        $name = 'template-'.$semester.'-'.$i.'.png';
        file_put_contents($output.'/'.$name, file_get_contents($drawing->getPath()));
        $images[] = ['file' => $name, 'row' => $r, 'x' => $xs[Coordinate::columnIndexFromString($c)] + $drawing->getOffsetX(), 'y' => $ys[$r] + $drawing->getOffsetY(), 'width' => $drawing->getWidth(), 'height' => $drawing->getHeight(), 'signature' => $r > 30];
    }
    $layouts[$semester] = ['sheet' => $sheet->getTitle(), 'width' => $xs[18], 'height' => $ys[$lastRow + 1], 'rows' => $ys, 'cells' => $cells, 'images' => $images];
}
file_put_contents($output.'/layout-v1.json', json_encode($layouts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Extracted sanitized CETAK SMT1 and CETAK SMT2 layouts.\n";
