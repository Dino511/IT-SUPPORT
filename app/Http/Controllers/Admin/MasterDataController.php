<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DeviceType;
use App\Models\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Master Data page: companies, device types (with asset tag prefixes) and the
 * dropdown lists (App\Models\Lookup). Super admin only (routes are inside the
 * role:super_admin group). Every change is written to the audit log.
 */
class MasterDataController extends Controller
{
    /**
     * Show one tab of master data, chosen by ?category=:
     * "companies" (default), "device_types", or any Lookup::CATEGORIES key.
     * Lookup rows get a usage count so options in use can't be deleted.
     */
    public function index(Request $request)
    {
        $category = $request->query('category', 'companies');
        abort_unless(in_array($category, ['companies', 'device_types'], true) || isset(Lookup::CATEGORIES[$category]), 404);

        $data = ['category' => $category];
        if ($category === 'companies') {
            $data['companies'] = Company::withCount(['devices', 'employees'])->orderBy('name')->get();
        } elseif ($category === 'device_types') {
            $data['deviceTypes'] = DeviceType::withCount('devices')->orderBy('name')->get();
        } else {
            $data['items'] = Lookup::with('parent')->where('category', $category)->get()
                ->sortBy(fn ($l) => ($l->parent?->value ?? '').' '.$l->value, SORT_NATURAL | SORT_FLAG_CASE)
                ->each(fn ($l) => $l->usage = $l->usageCount());
            $data['brands'] = Lookup::where('category', 'brand')->orderBy('value')->get();
        }
        $data['counts'] = Lookup::selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category');

        return view('admin.master.index', $data);
    }

    /** Add an option to a dropdown list (models need their brand). */
    public function store(Request $request)
    {
        $data = $this->validatedLookup($request);
        $lookup = Lookup::create($data);
        AuditLog::record('lookup.created', 'Added '.Lookup::CATEGORIES[$lookup->category]['label']." option \"{$lookup->value}\""
            .($lookup->parent ? " under {$lookup->parent->value}" : ''));

        return back()->with('success', "\"{$lookup->value}\" added.");
    }

    /**
     * Rename / re-parent / (de)activate. A rename also updates every record
     * that stores the old text so reports stay consistent.
     */
    public function update(Request $request, Lookup $lookup)
    {
        $data = $this->validatedLookup($request, $lookup);
        $data['is_active'] = $request->boolean('is_active');

        DB::transaction(function () use ($lookup, $data) {
            $old = $lookup->value;
            $oldParent = $lookup->parent?->value;
            $lookup->fill($data);
            $changes = collect($lookup->getDirty())->mapWithKeys(fn ($v, $k) => [$k => [$lookup->getOriginal($k), $v]])->all();
            $lookup->save();

            // records store the option's text, so carry a rename over to them
            $meta = Lookup::CATEGORIES[$lookup->category];
            $updated = 0;
            if ($meta['table'] && $old !== $lookup->value) {
                $updated = DB::table($meta['table'])->where($meta['column'], $old)
                    ->when($lookup->category === 'model' && $oldParent, fn ($q) => $q->where('brand', $oldParent))
                    ->update([$meta['column'] => $lookup->value]);
            }
            if ($changes) {
                AuditLog::record('lookup.updated', "Updated {$meta['label']} option \"{$old}\""
                    .($old !== $lookup->value ? " → \"{$lookup->value}\"".($updated ? " ({$updated} record(s) renamed)" : '') : '')
                    .(isset($changes['is_active']) ? ($lookup->is_active ? ', re-activated' : ', deactivated') : ''),
                    ['changes' => $changes]);
            }
        });

        return back()->with('success', 'Option updated.');
    }

    /** Delete an option - only allowed when no record uses it and it has no children. */
    public function destroy(Lookup $lookup)
    {
        if ($lookup->usageCount() > 0 || $lookup->children()->exists()) {
            return back()->with('error', "\"{$lookup->value}\" is in use. Deactivate it instead so it no longer appears in dropdowns.");
        }

        $label = Lookup::CATEGORIES[$lookup->category]['label'];
        $lookup->delete();
        AuditLog::record('lookup.deleted', "Deleted {$label} option \"{$lookup->value}\"");

        return back()->with('success', "\"{$lookup->value}\" deleted.");
    }

    /** Add a company to the group (code is stored upper-case). */
    public function storeCompany(Request $request)
    {
        $company = Company::create($this->validatedCompany($request) + ['is_active' => true]);
        AuditLog::record('company.created', "Added company {$company->name} ({$company->code})".($company->country ? ", {$company->country}" : ''));

        return back()->with('success', "Company {$company->name} added.");
    }

    /**
     * Edit or (de)activate a company. Employees and devices link by id, so a
     * rename shows everywhere automatically.
     */
    public function updateCompany(Request $request, Company $company)
    {
        $company->fill($this->validatedCompany($request, $company) + ['is_active' => $request->boolean('is_active')]);
        $changes = collect($company->getDirty())->mapWithKeys(fn ($v, $k) => [$k => [$company->getOriginal($k), $v]])->all();
        $company->save();

        if ($changes) {
            AuditLog::record('company.updated', "Updated company {$company->name}: ".implode(', ', array_keys($changes)), ['changes' => $changes]);
        }

        return back()->with('success', 'Company updated.');
    }

    /** Add a device type; its permanent `code` is generated from the name. */
    public function storeType(Request $request)
    {
        $data = $this->validatedType($request);
        $data['code'] = $this->uniqueCode($data['name']);
        $type = DeviceType::create($data);

        AuditLog::record('device_type.created', "Added device type {$type->name} (asset tag prefix {$type->prefix}-)");

        return back()->with('success', "Device type {$type->name} added.");
    }

    /**
     * Edit a device type (name, prefix, spec flags, active). A new prefix only
     * affects asset tags generated from now on; existing tags are kept.
     */
    public function updateType(Request $request, DeviceType $deviceType)
    {
        $deviceType->fill($this->validatedType($request, $deviceType));
        $changes = collect($deviceType->getDirty())->mapWithKeys(fn ($v, $k) => [$k => [$deviceType->getOriginal($k), $v]])->all();
        $deviceType->save();

        if ($changes) {
            AuditLog::record('device_type.updated', "Updated device type {$deviceType->name}: ".implode(', ', array_keys($changes))
                .(isset($changes['prefix']) ? '. New devices will be tagged '.$deviceType->prefix.'-NNNN; existing tags are unchanged' : ''),
                ['changes' => $changes]);
        }

        return back()->with('success', 'Device type updated.');
    }

    /**
     * Validate an option. The category is fixed once created; a parent (brand)
     * is required for child categories and forbidden otherwise; the same text
     * may not appear twice under the same parent.
     */
    private function validatedLookup(Request $request, ?Lookup $lookup = null): array
    {
        $category = $lookup?->category ?? $request->input('category');
        $hasParent = isset(Lookup::CATEGORIES[$category]['parent']);

        $data = $request->validate([
            'category' => [$lookup ? 'nullable' : 'required', Rule::in(array_keys(Lookup::CATEGORIES))],
            'value' => ['required', 'string', 'max:150'],
            'parent_id' => [$hasParent ? 'required' : 'prohibited', Rule::exists('lookups', 'id')->where('category', 'brand')],
        ], ['parent_id.required' => 'Choose the brand this model belongs to.']);

        $data['value'] = trim($data['value']);
        $data['category'] = $category;
        $data['parent_id'] = $hasParent ? (int) $data['parent_id'] : null;

        $duplicate = Lookup::where('category', $category)->where('value', $data['value'])
            ->where(fn ($q) => $data['parent_id'] ? $q->where('parent_id', $data['parent_id']) : $q->whereNull('parent_id'))
            ->when($lookup, fn ($q) => $q->where('id', '!=', $lookup->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['value' => "\"{$data['value']}\" already exists."]);
        }

        return $data;
    }

    /** Validate a company: unique code (letters, digits, dashes) and unique name. */
    private function validatedCompany(Request $request, ?Company $company = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('companies')->ignore($company)],
            'name' => ['required', 'string', 'max:150', Rule::unique('companies')->ignore($company)],
            'country' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
        ], ['code.regex' => 'The code may only contain letters, numbers and dashes.']);
        $data['code'] = strtoupper($data['code']);

        return $data;
    }

    /** Validate a device type: unique name and unique alphanumeric prefix (stored upper-case). */
    private function validatedType(Request $request, ?DeviceType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('device_types')->ignore($type)],
            'prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('device_types')->ignore($type)],
        ], ['prefix.regex' => 'The prefix may only contain letters and numbers.']);

        $data['prefix'] = strtoupper($data['prefix']);
        $data['has_computer_specs'] = $request->boolean('has_computer_specs');
        $data['has_imei'] = $request->boolean('has_imei');
        $data['is_active'] = $type ? $request->boolean('is_active') : true;

        return $data;
    }

    /** snake_case code from a type name ("Printer" -> "printer"), suffixed _2, _3... if taken. */
    private function uniqueCode(string $name): string
    {
        $base = Str::limit(Str::slug($name, '_'), 25, '') ?: 'type';
        $code = $base;
        for ($i = 2; DeviceType::where('code', $code)->exists(); $i++) {
            $code = "{$base}_{$i}";
        }

        return $code;
    }
}
