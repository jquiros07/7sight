<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateVideoStoragePaths extends Command
{
    protected $signature = 'videos:migrate-storage-paths';

    protected $description = 'Move existing video files into the videos/{workspace_id}/{user_id}/ folder structure and update their stored path.';

    public function handle(): int
    {
        $videos = Video::withTrashed()->get();

        foreach ($videos as $video) {
            $oldPath = $video->path;
            $newPath = sprintf(
                'videos/%d/%s/%s',
                $video->workspace_id,
                $video->user_id ?? 'unassigned',
                basename($oldPath),
            );

            if ($oldPath === $newPath) {
                continue;
            }

            try {
                $disk = Storage::disk($video->disk);

                if (! $disk->exists($oldPath)) {
                    $this->warn("Skipping video {$video->id}: file not found at {$oldPath}");

                    continue;
                }

                $disk->move($oldPath, $newPath);
                $video->update(['path' => $newPath]);

                $this->info("Moved video {$video->id}: {$oldPath} -> {$newPath}");
            } catch (Throwable $e) {
                Log::error($e->getMessage(), ['exception' => $e, 'video_id' => $video->id]);
                $this->error("Failed to move video {$video->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
