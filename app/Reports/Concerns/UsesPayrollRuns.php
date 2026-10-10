<?php

namespace App\Reports\Concerns;

use App\Models\PayrollRun;

trait UsesPayrollRuns
{
    /** @param list<string> $statuses */
    protected function runFilter(array $statuses): array
    {
        $runs = PayrollRun::whereIn('status', $statuses)->orderByDesc('period')->get();

        return [
            'name' => 'run', 'label' => 'Payroll month', 'type' => 'select', 'required' => true,
            'options' => $runs->mapWithKeys(fn ($r) => [$r->id => $r->label().' ('.$r->status.')'])->all(),
            'default' => (string) $runs->first()?->id,
        ];
    }

    /** @param list<string> $statuses */
    protected function findRun(array $filters, array $statuses): ?PayrollRun
    {
        return isset($filters['run']) ? PayrollRun::whereIn('status', $statuses)->find($filters['run']) : null;
    }
}
