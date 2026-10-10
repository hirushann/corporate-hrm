<?php

namespace App\Livewire\Payroll;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\PayrollRun;
use App\Models\PayslipLine;
use App\Services\PayrollService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Payroll run')]
class Show extends Component
{
    use AuthorizesPermission, WithPagination;

    public PayrollRun $run;

    public string $search = '';

    public ?int $openPayslipId = null;

    // manual line form
    public string $lineType = 'earning';

    public string $lineDescription = '';

    public string $lineAmount = '';

    public string $reviewNote = '';

    public function mount(PayrollRun $run): void
    {
        $this->allow('payroll.view');
        $this->run = $run;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    private function fresh(): PayrollRun
    {
        return $this->run = $this->run->fresh();
    }

    public function recalculate(PayrollService $payroll): void
    {
        $this->allow('payroll.process');
        $payroll->generate($this->fresh());
        session()->flash('status', 'Payroll recalculated from the latest attendance and salary data.');
    }

    public function submit(PayrollService $payroll): void
    {
        $this->allow('payroll.process');
        $payroll->submit($this->fresh(), auth()->user());
        session()->flash('status', 'Submitted for approval.');
    }

    public function approve(PayrollService $payroll): void
    {
        $this->allow('payroll.approve');
        $payroll->approve($this->fresh(), auth()->user());
        session()->flash('status', 'Payroll approved.');
    }

    public function reject(PayrollService $payroll): void
    {
        $this->allow('payroll.approve');
        $this->validate(['reviewNote' => 'required|string|max:255'], ['reviewNote.required' => 'Please give a reason for returning the payroll.']);
        $payroll->reject($this->fresh(), auth()->user(), $this->reviewNote);
        $this->reset('reviewNote');
        session()->flash('status', 'Returned to draft.');
    }

    public function lock(PayrollService $payroll): void
    {
        $this->allow('payroll.lock');
        $payroll->lock($this->fresh(), auth()->user());
        session()->flash('status', 'Payroll locked. Payslips are now available to employees.');
    }

    public function deleteDraft(PayrollService $payroll)
    {
        $this->allow('payroll.process');
        $payroll->deleteDraft($this->fresh(), auth()->user());

        return $this->redirectRoute('payroll.index', navigate: true);
    }

    public function togglePayslip(int $id): void
    {
        $this->openPayslipId = $this->openPayslipId === $id ? null : $id;
        $this->reset('lineDescription', 'lineAmount');
    }

    public function addLine(PayrollService $payroll): void
    {
        $this->allow('payroll.process');
        $this->validate([
            'lineType' => 'required|in:earning,deduction',
            'lineDescription' => 'required|string|max:255',
            'lineAmount' => 'required|numeric|gt:0|max:99999999',
        ]);

        $payroll->addManualLine($this->run->payslips()->findOrFail($this->openPayslipId), $this->lineType, $this->lineDescription, (float) $this->lineAmount);
        $this->reset('lineDescription', 'lineAmount');
    }

    public function removeLine(int $lineId, PayrollService $payroll): void
    {
        $this->allow('payroll.process');
        $line = PayslipLine::whereIn('payslip_id', $this->run->payslips()->select('id'))->findOrFail($lineId);
        $payroll->removeManualLine($line);
    }

    public function render()
    {
        $user = auth()->user();
        $payslips = $this->run->payslips()
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('employee_name', 'like', "%{$this->search}%")->orWhere('employee_no', 'like', "%{$this->search}%")))
            ->with('lines')
            ->orderBy('employee_no')->paginate(20);

        $totals = $this->run->payslips()->selectRaw('count(*) c, sum(gross) gross, sum(total_deductions) ded, sum(net_salary) net, sum(employer_contributions) er')->first();
        $status = $this->run->status;

        return view('livewire.payroll.show', [
            'payslips' => $payslips,
            'totals' => $totals,
            'canProcess' => $user->can('payroll.process'),
            'canApprove' => $user->can('payroll.approve'),
            'canLock' => $user->can('payroll.lock'),
            // The preparer/submitter may not approve their own payroll.
            'conflicted' => in_array($user->id, [$this->run->prepared_by, $this->run->submitted_by], true),
            'draft' => $status === 'draft',
        ]);
    }
}
