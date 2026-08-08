<?php

namespace App\Support;

class ObjectDetectionCatalog
{
    /**
     * @return array<int, array{key: string, label: string, icon: string, objects: array<int, array{value: string, label: string}>}>
     */
    public static function categories(): array
    {
        return config('object_detection.categories');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return collect(self::categories())
            ->flatMap(fn (array $category) => array_column($category['objects'], 'value'))
            ->unique()
            ->values()
            ->all();
    }
}
