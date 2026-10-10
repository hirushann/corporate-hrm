<?php

namespace App\Exports;

use App\Reports\Report;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private Report $report, private Collection $rows) {}

    public function title(): string
    {
        return substr(preg_replace('/[^\w \-]/', '', $this->report->title()), 0, 31);
    }

    public function headings(): array
    {
        return $this->report->headings();
    }

    public function array(): array
    {
        $rows = $this->rows->map(fn ($row) => array_map([self::class, 'safe'], $row));
        $totals = $this->report->totals($this->rows);

        return $rows->when($totals, fn ($c) => $c->push(array_map([self::class, 'safe'], $totals)))->all();
    }

    /**
     * Stop spreadsheet formula injection: text that starts with = + - @ (or a tab/CR) is
     * treated as a formula by Excel, so it is prefixed with an apostrophe. Numbers pass through.
     */
    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
