<?php

namespace App\Http\Controllers\Admin\Shop\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;

class CatalogExport implements FromCollection, WithEvents, WithCustomStartCell
{
    private $list;

    public function __construct($list)
    {
        $this->list = $list;
    }

    public function collection()
    {
        return collect($this->list);
    }
    public function startCell(): string
    {
        return 'A9';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A9')->getAlignment()->setIndent(1);
                $sheet->getStyle('A9')->getAlignment()->setHorizontal('left');
                $sheet->getStyle('A9')->getAlignment()->setVertical('top');

                // Auto-size columns
                foreach (range('A', $sheet->getHighestColumn()) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Auto-size row heights
                for ($row = 9; $row <= $sheet->getHighestRow(); $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(-1);
                }

                // Add drawing (logo)
                $drawing = new Drawing();
                $drawing->setPath(public_path('logo.png'));
                $drawing->setCoordinates('A2');
                $drawing->setOffsetX(25);
                $drawing->setHeight(130);
                $drawing->setWidth(130);
                $drawing->setWorksheet($sheet);
            },
        ];
    }
}