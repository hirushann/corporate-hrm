<?php

namespace App\Models;

use App\Support\Permissions;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Guarded([])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUSES = ['active', 'probation', 'suspended', 'resigned', 'terminated'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'join_date' => 'date',
            'confirmation_date' => 'date',
            'resignation_date' => 'date',
            'bank_account_number' => 'encrypted',
            'tax_tin' => 'encrypted',
            'epf_applicable' => 'boolean',
            'etf_applicable' => 'boolean',
            'apit_applicable' => 'boolean',
            'ot_eligible' => 'boolean',
        ];
    }

    /** Next sequential code such as EMP-0001. Call inside a transaction. */
    public static function nextEmployeeNo(): string
    {
        $last = static::withTrashed()->lockForUpdate()->orderByDesc('id')->value('employee_no');
        $number = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;

        return sprintf('EMP-%04d', $number);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (filled($term)) {
            $query->where(fn (Builder $q) => $q
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('employee_no', 'like', "%{$term}%")
                ->orWhere('nic', 'like', "%{$term}%"));
        }
    }

    /**
     * Restrict to the employees a user may see: managers (without a wider HR/payroll role)
     * only see their direct reports; everyone else with access sees all.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $wide = $user->hasAnyRole([Permissions::SUPER_ADMIN, Permissions::HR_MANAGER, Permissions::PAYROLL_OFFICER]);

        if (! $wide && $user->hasRole(Permissions::MANAGER)) {
            $ownId = Employee::where('user_id', $user->id)->value('id');
            $query->where('manager_id', $ownId ?? 0);
        }
    }

    public function scopeWorking(Builder $query): void
    {
        $query->whereIn('status', ['active', 'probation', 'suspended']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class, 'employee_category_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class)->orderByDesc('effective_from');
    }

    public function currentSalary(): HasOne
    {
        return $this->hasOne(EmployeeSalary::class)
            ->where('effective_from', '<=', now()->toDateString())
            ->latest('effective_from');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** The ID this employee is known by on the biometric device. */
    public function deviceId(): string
    {
        return $this->biometric_id ?: $this->employee_no;
    }

    public function components(): HasMany
    {
        return $this->hasMany(EmployeeComponent::class)->orderByDesc('effective_from');
    }

    /** Monthly salary in force on a date (latest revision effective on or before it). */
    public function salaryOn(CarbonInterface $date): ?EmployeeSalary
    {
        return $this->salaries()->whereDate('effective_from', '<=', $date)->reorder('effective_from', 'desc')->first();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest();
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Bank account number and TIN are deliberately not logged.
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['bank_account_number', 'tax_tin', 'updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
