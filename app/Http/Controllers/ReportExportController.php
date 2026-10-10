<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Company;
use App\Reports\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $key, string $format)
    {
        $user = $request->user();
        $report = ReportRegistry::find($key);

        abort_unless($report && $user->can($report->permission()) && $user->can('reports.export'), 403);
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);

        $filters = $report->resolveFilters($request->query());
        $rows = $report->rows($filters, $user);
        $name = $key.'-'.now()->format('Ymd-His');

        activity()->causedBy($user)->withProperties(['report' => $key, 'format' => $format, 'filters' => $filters, 'rows' => $rows->count()])->log('report exported');

        if ($format === 'pdf') {
            $labels = collect($report->filters())->mapWithKeys(fn ($f) => [
                $f['label'] => $f['type'] === 'select' ? ($f['options'][$filters[$f['name']] ?? ''] ?? 'All') : ($filters[$f['name']] ?? ''),
            ])->filter()->all();

            return Pdf::loadView('reports.pdf', [
                'title' => $report->title(), 'filters' => $labels, 'headings' => $report->headings(),
                'rows' => $rows, 'totals' => $report->totals($rows), 'generatedBy' => $user->name,
                'company' => Company::first(),
            ])->setPaper('a4', 'landscape')->download("$name.pdf");
        }

        return Excel::download(new ReportExport($report, $rows), "$name.$format", $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX);
    }
}
