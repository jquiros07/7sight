<?php

namespace App\Support;

class ReportFormatter
{
    public static function seconds(int|float|string $value): string
    {
        $totalSeconds = (int) floor((float) $value);
        $minutes = intdiv($totalSeconds, 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public static function duration(?int $totalSeconds): string
    {
        if ($totalSeconds === null) {
            return '—';
        }

        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
            : sprintf('%d:%02d', $minutes, $seconds);
    }

    public static function fileSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
