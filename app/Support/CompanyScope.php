<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

/**
 * The company the signed-in user is currently viewing ("All companies" when
 * unset). Chosen with the sidebar switcher (Admin\CompanyScopeController) and
 * kept in the session. The dashboard, device/employee/request lists and the
 * audit log narrow to it; detail pages and forms are not restricted.
 *
 * Usage: CompanyScope::apply(Device::query())->get()
 *        CompanyScope::applyVia(DeviceRequest::query(), 'employee')->get()
 */
class CompanyScope
{
    /** Id of the selected company, or null for all companies. */
    public static function id(): ?int
    {
        return session('company_scope');
    }

    /** The selected company (cached for the request), or null. */
    public static function current(): ?Company
    {
        return once(fn () => static::id() ? Company::find(static::id()) : null);
    }

    /** Narrow a query on a table with a company_id column. No-op for "all companies". */
    public static function apply(Builder $query, string $column = 'company_id'): Builder
    {
        return $query->when(static::id(), fn ($q, $id) => $q->where($column, $id));
    }

    /** Narrow a query to rows whose $relation (employee, device...) is in the company. */
    public static function applyVia(Builder $query, string $relation): Builder
    {
        return $query->when(static::id(), fn ($q, $id) => $q->whereHas($relation, fn ($r) => $r->where('company_id', $id)));
    }
}
