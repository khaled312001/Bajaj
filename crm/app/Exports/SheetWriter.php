<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Small helper that renders styled, RTL, formula-safe Excel workbooks. */
class SheetWriter
{
    public const NAVY = '1E3A8A';

    /**
     * @param array<int,array{title:string,headers:array,rows:iterable,summary?:array,money?:array,widths?:array}> $sheets
     */
    public static function build(array $sheets): Spreadsheet
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        foreach ($sheets as $i => $def) {
            $ws = new Worksheet($book, mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $def['title']), 0, 31));
            $book->addSheet($ws, $i);
            self::fill($ws, $def);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    public static function fill(Worksheet $ws, array $def): void
    {
        $ws->setRightToLeft(true);
        $row = 1;

        if (! empty($def['summary'])) {
            foreach ($def['summary'] as $label => $value) {
                $ws->setCellValue([1, $row], $label);
                self::put($ws, 2, $row, $value);
                $ws->getStyle([1, $row, 2, $row])->getFont()->setBold(true);
                $ws->getStyle([1, $row])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0F2FE');
                $row++;
            }
            $row++;
        }

        $cols = count($def['headers']);
        foreach ($def['headers'] as $c => $h) {
            $ws->setCellValueExplicit([$c + 1, $row], (string) $h, DataType::TYPE_STRING);
        }
        $headerRow = $row;
        $style = $ws->getStyle([1, $headerRow, $cols, $headerRow]);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $ws->getRowDimension($headerRow)->setRowHeight(28);

        $row++;
        $first = $row;
        foreach ($def['rows'] as $r) {
            foreach (array_values($r) as $c => $v) {
                self::put($ws, $c + 1, $row, $v);
            }
            $row++;
        }
        $last = $row - 1;

        if ($last >= $first) {
            $ws->getStyle([1, $first, $cols, $last])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
            foreach ($def['money'] ?? [] as $col) {
                $ws->getStyle([$col, $first, $col, $last])->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $ws->setAutoFilter($ws->getCell([1, $headerRow])->getCoordinate() . ':' . $ws->getCell([$cols, $last])->getCoordinate());
        }
        $ws->freezePane($ws->getCell([1, $headerRow + 1])->getCoordinate());

        foreach (range(1, $cols) as $c) {
            $w = $def['widths'][$c - 1] ?? null;
            $dim = $ws->getColumnDimensionByColumn($c);
            $w ? $dim->setWidth($w) : $dim->setAutoSize(true);
        }
    }

    private static function put(Worksheet $ws, int $col, int $row, $v): void
    {
        if ($v === null || $v === '') {
            return;
        }
        if (is_int($v) || is_float($v)) {
            $ws->setCellValue([$col, $row], $v);
        } else {
            // explicit string type: prevents spreadsheet formula injection (=, +, -, @ prefixes)
            $ws->setCellValueExplicit([$col, $row], (string) $v, DataType::TYPE_STRING);
        }
    }

    public static function download(string $filename, array $sheets): StreamedResponse
    {
        return self::stream(self::build($sheets), $filename);
    }

    public static function stream(Spreadsheet $book, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }
}
