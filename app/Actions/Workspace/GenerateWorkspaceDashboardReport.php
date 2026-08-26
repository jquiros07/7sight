<?php

namespace App\Actions\Workspace;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class GenerateWorkspaceDashboardReport
{
    public function __construct(
        private readonly ShowWorkspaceDashboard $showWorkspaceDashboard,
    ) {}

    /**
     * Render a downloadable PDF summary of a workspace's dashboard - the
     * same data the workspace Dashboard page shows. Requires workspace
     * membership, enforced by ShowWorkspaceDashboard itself.
     */
    public function __invoke(User $user, Workspace $workspace): PdfBuilder
    {
        $dashboard = ($this->showWorkspaceDashboard)($user, $workspace);

        return Pdf::view('pdfs.workspace-dashboard-report', [
            'dashboard' => $dashboard,
            'generatedAt' => now(),
        ])
            ->format('a4')
            ->margins(12, 12, 12, 12)
            ->waitUntilReady()
            ->download($this->fileName($workspace));
    }

    private function fileName(Workspace $workspace): string
    {
        $slug = Str::slug($workspace->name) ?: 'workspace';

        return "{$slug}-dashboard-report.pdf";
    }
}
