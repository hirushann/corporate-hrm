<?php

namespace App\Livewire\Reports;

use App\Reports\Report;
use App\Reports\ReportRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Report')]
class Show extends Component
{
    private const DISPLAY_LIMIT = 500;

    public string $key;

    /** @var array<string, mixed> filter values, kept in the URL so a report can be bookmarked */
    #[Url(as: 'f')]
    public array $f = [];

    public function mount(string $key): void
    {
        $report = ReportRegistry::find($key);
        abort_unless($report && auth()->user()->can($report->permission()), 403);

        $this->key = $key;
        foreach ($report->filters() as $filter) {
            $this->f[$filter['name']] ??= $filter['default'] ?? '';
        }
    }

    private function report(): Report
    {
        $report = ReportRegistry::find($this->key);
        abort_unless($report && auth()->user()->can($report->permission()), 403);

        return $report;
    }

    public function render()
    {
        $report = $this->report();
        $filters = $report->resolveFilters($this->f);
        $rows = $report->rows($filters, auth()->user());

        return view('livewire.reports.show', [
            'report' => $report,
            'rows' => $rows->take(self::DISPLAY_LIMIT),
            'total' => $rows->count(),
            'limit' => self::DISPLAY_LIMIT,
            'totals' => $report->totals($rows),
            'query' => http_build_query(array_filter($filters, fn ($v) => $v !== null && $v !== '')),
            'canExport' => auth()->user()->can('reports.export'),
        ])->title($report->title());
    }
}
