<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Lookup;
use App\Support\CompanyScope;
use App\Support\Lookups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Employee records (the people devices are issued to): list, add, view, edit.
 * Employees are deactivated rather than deleted. Login accounts for IT staff
 * are managed separately in UserController (super admin only).
 *
 * Routes: admin/employees/* (both roles).
 */
class EmployeeController extends Controller
{
    /** Employee columns backed by a master data list. */
    private const LOOKUP_FIELDS = ['department', 'position'];

    /**
     * Employee list, narrowed to the selected company. Filters: q (ID, name,
     * email) and department. Shows how many devices each person holds.
     */
    public function index(Request $request)
    {
        $employees = CompanyScope::apply(Employee::withCount('devices')->with('user', 'company'))
            ->when($request->q, function ($query, $q) {
                $query->where(fn ($w) => $w->where('employee_no', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%"));
            })
            ->when($request->department, fn ($query, $d) => $query->where('department', $d))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(20)->withQueryString();

        return view('admin.employees.index', [
            'employees' => $employees,
            'departments' => CompanyScope::apply(Employee::query())->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    /** "Add Employee" form; the company defaults to the one being viewed. */
    public function create()
    {
        return $this->form(new Employee(['is_active' => true, 'company_id' => CompanyScope::id()]));
    }

    /** Save a new employee and log it. */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $employee = DB::transaction(function () use ($data) {
            $employee = Employee::create($this->rememberLookups($data));
            AuditLog::record('employee.created', "Added employee {$employee->full_name} [{$employee->employee_no}], {$employee->department} - {$employee->company->name}",
                ['employee' => $employee]);

            return $employee;
        });

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Employee added.');
    }

    /** Employee page: profile, devices held, device history, requests and audit trail (last 100). */
    public function show(Employee $employee)
    {
        $employee->load(['company', 'user', 'devices.company',
            'assignments' => fn ($q) => $q->latest('assigned_at'), 'assignments.device', 'assignments.assignedBy',
            'requests' => fn ($q) => $q->latest(), 'requests.device', 'requests.handler', 'requests.requester']);

        return view('admin.employees.show', [
            'employee' => $employee,
            'logs' => $employee->logs()->with('user', 'device')->latest('created_at')->take(100)->get(),
        ]);
    }

    public function edit(Employee $employee)
    {
        return $this->form($employee);
    }

    /** Save edits; changed fields are recorded old -> new in the audit log. */
    public function update(Request $request, Employee $employee)
    {
        $data = $this->validated($request, $employee);

        DB::transaction(function () use ($employee, $data) {
            $employee->fill($this->rememberLookups($data));
            $changes = collect($employee->getDirty())
                ->mapWithKeys(fn ($new, $key) => [$key => [$employee->getOriginal($key), $new]])->all();
            if (isset($changes['company_id'])) { // log company names, not ids
                $names = Company::whereIn('id', array_filter($changes['company_id']))->pluck('name', 'id');
                $changes['company'] = array_map(fn ($id) => $names[$id] ?? null, $changes['company_id']);
                unset($changes['company_id']);
            }
            $employee->save();

            if ($changes) {
                AuditLog::record('employee.updated', "Updated employee {$employee->full_name}: ".implode(', ', array_keys($changes)),
                    ['employee' => $employee, 'changes' => $changes]);
            }
        });

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Employee updated.');
    }

    /** Shared create/edit form with company and master data dropdowns. */
    private function form(Employee $employee)
    {
        return view('admin.employees.form', [
            'employee' => $employee,
            'companies' => Company::where('is_active', true)->orWhere('id', $employee->company_id)->orderBy('name')->get(),
            'options' => collect(self::LOOKUP_FIELDS)->mapWithKeys(fn ($f) => [$f => Lookup::values($f)])->all(),
        ]);
    }

    /**
     * Validate the employee form. Department/position must come from the master
     * data (Lookups::rule); the company must be active unless already set.
     */
    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'employee_no' => ['required', 'string', 'max:30', Rule::unique('employees')->ignore($employee)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100', Lookups::rule('position', $employee?->position)],
            'department' => ['required', 'string', 'max:100', Lookups::rule('department', $employee?->department)],
            'company_id' => ['required', Rule::exists('companies', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $employee?->company_id))],
            'contact_no' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150', Rule::unique('employees')->ignore($employee)],
        ], [], ['company_id' => 'company']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /** Normalise dropdown values; the super admin's new values join the master data. */
    private function rememberLookups(array $data): array
    {
        foreach (self::LOOKUP_FIELDS as $field) {
            $data[$field] = Lookups::remember($field, $data[$field] ?? null);
        }

        return $data;
    }
}
