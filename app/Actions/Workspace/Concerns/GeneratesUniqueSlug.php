<?php

namespace App\Actions\Workspace\Concerns;

use App\Models\Workspace;
use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    /**
     * Generate a unique slug for the given name, optionally ignoring one workspace's own row.
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            Workspace::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}