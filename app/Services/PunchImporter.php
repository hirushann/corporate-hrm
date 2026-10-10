<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Punch;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Imports punches from a CSV export of a biometric device.
 *
 * Accepted headers (case-insensitive): an ID column (employee_no | biometric_id | user_id | id | no)
 * and either a single datetime column (punched_at | datetime | timestamp | time) or separate date + time columns.
 */
class PunchImporter
{
    /** @return array{imported:int, duplicates:int, errors:list<string>, from:?Carbon, to:?Carbon, employee_ids:list<int>} */
    public function import(string $path, string $source = 'csv'): array
    {
        $handle = fopen($path, 'r');
        $result = ['imported' => 0, 'duplicates' => 0, 'errors' => [], 'from' => null, 'to' => null, 'employee_ids' => []];

        if (! $handle) {
            $result['errors'][] = 'Could not read the file.';

            return $result;
        }

        $headerRow = fgetcsv($handle);
        $header = array_map(fn ($h) => Str::of((string) $h)->replace("\xEF\xBB\xBF", '')->trim()->lower()->snake()->toString(), $headerRow ?: []);

        $idCol = $this->find($header, ['employee_no', 'biometric_id', 'device_user_id', 'user_id', 'id', 'no']);
        $dtCol = $this->find($header, ['punched_at', 'datetime', 'date_time', 'timestamp']);
        $dateCol = $this->find($header, ['date']);
        $timeCol = $this->find($header, ['time']);
        // Some devices export one "Time" column that already holds the full date and time.
        if ($dtCol === null && $dateCol === null) {
            $dtCol = $timeCol;
        }

        if ($idCol === null || ($dtCol === null && ($dateCol === null || $timeCol === null))) {
            $result['errors'][] = 'Header row must contain an employee ID column and a date/time column.';
            fclose($handle);

            return $result;
        }

        $employees = Employee::withTrashed()->get(['id', 'employee_no', 'biometric_id']);
        $byDevice = $employees->whereNotNull('biometric_id')->keyBy(fn ($e) => strtolower($e->biometric_id));
        $byNo = $employees->keyBy(fn ($e) => strtolower($e->employee_no));
        $batch = (string) Str::uuid();
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if ($row === [null] || count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $deviceId = trim((string) ($row[$idCol] ?? ''));
            $employee = $byDevice->get(strtolower($deviceId)) ?? $byNo->get(strtolower($deviceId));
            if (! $employee) {
                $result['errors'][] = "Line $line: unknown employee ID '$deviceId'.";

                continue;
            }

            try {
                $raw = $dtCol !== null
                    ? trim((string) $row[$dtCol])
                    : trim($row[$dateCol].' '.$row[$timeCol]);
                $at = Carbon::parse($raw);
            } catch (\Throwable) {
                $result['errors'][] = "Line $line: invalid date/time.";

                continue;
            }

            $created = Punch::query()->insertOrIgnore([
                'device_user_id' => $deviceId, 'employee_id' => $employee->id, 'punched_at' => $at,
                'source' => $source, 'batch' => $batch, 'created_at' => now(), 'updated_at' => now(),
            ]);

            if ($created) {
                $result['imported']++;
                $result['employee_ids'][$employee->id] = $employee->id;
                $result['from'] = $result['from'] ? $result['from']->min($at) : $at->copy();
                $result['to'] = $result['to'] ? $result['to']->max($at) : $at->copy();
            } else {
                $result['duplicates']++;
            }
        }

        fclose($handle);
        $result['employee_ids'] = array_values($result['employee_ids']);

        return $result;
    }

    private function find(array $header, array $candidates): ?int
    {
        foreach ($candidates as $name) {
            $i = array_search($name, $header, true);
            if ($i !== false) {
                return $i;
            }
        }

        return null;
    }
}
