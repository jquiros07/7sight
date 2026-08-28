<?php

namespace App\Http\Controllers;

use App\Actions\Workspace\CancelInvitation;
use App\Actions\Workspace\InviteMember;
use App\Actions\Workspace\ListWorkspaceMembers;
use App\Actions\Workspace\RemoveMember;
use App\Actions\Workspace\UpdateMemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WorkspaceMemberController extends Controller
{
    public function index(Request $request, Workspace $workspace, ListWorkspaceMembers $listWorkspaceMembers)
    {
        try {
            return response()->json($listWorkspaceMembers($request->user(), $workspace));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function invite(Request $request, Workspace $workspace, InviteMember $inviteMember)
    {
        try {
            return response()->json($inviteMember($request->user(), $workspace, [
                'email' => $request->email,
                'role' => $request->role,
            ]), 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function updateRole(Request $request, Workspace $workspace, User $member, UpdateMemberRole $updateMemberRole)
    {
        try {
            return response()->json($updateMemberRole($request->user(), $workspace, $member, [
                'role' => $request->role,
            ]));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function remove(Request $request, Workspace $workspace, User $member, RemoveMember $removeMember)
    {
        try {
            $removeMember($request->user(), $workspace, $member);

            return response()->noContent();
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function cancelInvitation(Request $request, Workspace $workspace, WorkspaceInvitation $invitation, CancelInvitation $cancelInvitation)
    {
        try {
            $cancelInvitation($request->user(), $workspace, $invitation);

            return response()->noContent();
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
