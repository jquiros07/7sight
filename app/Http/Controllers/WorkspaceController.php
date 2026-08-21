<?php

namespace App\Http\Controllers;

use App\Actions\Workspace\CreateWorkspace;
use App\Actions\Workspace\DeleteWorkspace;
use App\Actions\Workspace\GenerateWorkspaceInsightSummary;
use App\Actions\Workspace\ListWorkspaces;
use App\Actions\Workspace\ShowWorkspace;
use App\Actions\Workspace\ShowWorkspaceDashboard;
use App\Actions\Workspace\UpdateWorkspace;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WorkspaceController extends Controller
{
    public function index(Request $request, ListWorkspaces $listWorkspaces)
    {
        try {
            return $listWorkspaces($request->user(), $request->query());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function store(Request $request, CreateWorkspace $createWorkspace)
    {
        try {
            return response()->json($createWorkspace($request->user(), $request->all()), 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function show(Request $request, Workspace $workspace, ShowWorkspace $showWorkspace)
    {
        try {
            return $showWorkspace($request->user(), $workspace);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function dashboard(Request $request, Workspace $workspace, ShowWorkspaceDashboard $showWorkspaceDashboard)
    {
        try {
            return response()->json($showWorkspaceDashboard($request->user(), $workspace));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function generateInsightSummary(Request $request, Workspace $workspace, GenerateWorkspaceInsightSummary $generateWorkspaceInsightSummary)
    {
        try {
            return response()->json($generateWorkspaceInsightSummary($request->user(), $workspace), 201);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function update(Request $request, Workspace $workspace, UpdateWorkspace $updateWorkspace)
    {
        try {
            return $updateWorkspace($request->user(), $workspace, $request->all());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function destroy(Request $request, Workspace $workspace, DeleteWorkspace $deleteWorkspace)
    {
        try {
            $deleteWorkspace($request->user(), $workspace);

            return response()->noContent();
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
