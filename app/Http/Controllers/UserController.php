<?php

namespace App\Http\Controllers;

use App\Actions\User\ListUserWorkspacePermissions;
use Illuminate\Http\Request;
use Throwable;

class UserController extends Controller
{
    public function permissions(Request $request, ListUserWorkspacePermissions $listUserWorkspacePermissions)
    {
        try {
            $permissions = $listUserWorkspacePermissions($request->user());

            return response()->json(empty($permissions) ? (object) [] : $permissions);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
