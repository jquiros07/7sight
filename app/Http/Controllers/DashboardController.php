<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\GenerateDashboardReport;
use App\Actions\Dashboard\ShowDashboard;
use Illuminate\Http\Request;
use Throwable;

class DashboardController extends Controller
{
    public function show(Request $request, ShowDashboard $showDashboard)
    {
        try {
            return response()->json($showDashboard($request->user()));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function report(Request $request, GenerateDashboardReport $generateDashboardReport)
    {
        try {
            return $generateDashboardReport($request->user());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
