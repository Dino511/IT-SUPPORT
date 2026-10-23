<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Handles the sidebar "Viewing company" dropdown: stores the chosen company
 * in the session (see App\Support\CompanyScope). Empty = all companies.
 * Returns to the page the user was on.
 */
class CompanyScopeController extends Controller
{
    public function __invoke(Request $request)
    {
        $id = $request->validate(['company_id' => ['nullable', Rule::exists('companies', 'id')]])['company_id'] ?? null;

        $id ? session(['company_scope' => (int) $id]) : session()->forget('company_scope');

        return back();
    }
}
