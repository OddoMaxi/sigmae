<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;

class PermissionController extends Controller
{
    /** Liste toutes les permissions groupées par module */
    public function index()
    {
        $permissions = Permission::orderBy('module')->orderBy('action')->get();

        return response()->json([
            'total'      => $permissions->count(),
            'by_module'  => $permissions->groupBy('module'),
            'flat'       => $permissions,
        ]);
    }
}
