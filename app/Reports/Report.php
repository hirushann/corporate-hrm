<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * A tabular report: filters in, rows out. The same rows feed the on-screen table and the Excel, PDF and CSV exports.
 * Money values are floats, counts are ints, everything else is a string.
 */
abstract class Report
{
    abstract public static function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /** Permission needed to view this report. */
    abstract public function permission(): string;

    /** @return list<string> */
    abstract public function headings(): array;

    /** @return Collection<int, list<mixed>> one list per row, aligned with headings() */
    abstract public function rows(array $filters, User $user): Collection;

    /**
     * Filter definitions: name, label, type (month|date|select), options (select), default, required.
     *
     * @return list<array<string, mixed>>
     */
    public function filters(): array
    {
        return [];
    }

    /** Optional footer row aligned with headings(). */
    public function totals(Collection $rows): ?array
    {
        return null;
    }

    /** Validate and default the filter input; unknown keys are dropped. */
    public function resolveFilters(array $input): array
    {
        $rules = [];
        $defaults = [];

        foreach ($this->filters() as $filter) {
            $name = $filter['name'];
            $defaults[$name] = $filter['default'] ?? null;
            $presence = ($filter['required'] ?? false) ? 'required' : 'nullable';
            $rules[$name] = match ($filter['type']) {
                'month' => [$presence, 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
                'date' => [$presence, 'date'],
                'select' => [$presence, Rule::in(array_map('strval', array_keys($filter['options'] ?? [])))],
                default => [$presence, 'string', 'max:100'],
            };
        }

        $clean = array_intersect_key($input, $rules);
        $clean = array_filter($clean, fn ($v) => $v !== null && $v !== '');
        $values = Validator::make($clean + $defaults, $rules)->validate();

        return $values;
    }
}
