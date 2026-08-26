<?php

namespace App\Actions\Dashboard;

use App\Models\User;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class GenerateDashboardReport
{
    public function __construct(
        private readonly ShowDashboard $showDashboard,
    ) {}

    /**
     * Render a downloadable PDF summary of the cross-workspace dashboard -
     * the same data the Dashboard page shows. Inherently scoped to the
     * calling user's own workspaces, same as ShowDashboard.
     */
    public function __invoke(User $user): PdfBuilder
    {
        $dashboard = ($this->showDashboard)($user);

        return Pdf::view('pdfs.dashboard-report', [
            'dashboard' => $dashboard,
            'generatedAt' => now(),
        ])
            ->format('a4')
            ->margins(12, 12, 12, 12)
            ->waitUntilReady()
            ->download('dashboard-report.pdf');
    }
}
