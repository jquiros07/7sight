<?php

namespace App\Actions\ObjectDetection;

use App\Support\ObjectDetectionCatalog;

class ListObjectDetectionCategories
{
    /**
     * @return array<int, array{key: string, label: string, icon: string, objects: array<int, array{value: string, label: string}>}>
     */
    public function __invoke(): array
    {
        return ObjectDetectionCatalog::categories();
    }
}
