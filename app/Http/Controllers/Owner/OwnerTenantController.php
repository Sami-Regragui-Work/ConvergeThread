<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class OwnerTenantController extends Controller
{
    public function close(Tenant $tenant): RedirectResponse
    {
        if ($tenant->id === 1) {
            abort(403, 'The owner workspace cannot be closed.');
        }

        $tenant->update(['closed_by_id' => Auth::id()]);

        return back()->with('success', "Workspace \"{$tenant->name}\" has been closed.");
    }

    public function reopen(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['closed_by_id' => null]);

        return back()->with('success', "Workspace \"{$tenant->name}\" has been reopened.");
    }
}
