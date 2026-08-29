<?php

namespace App\Support;

use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Format\Video\X264;

/**
 * Thin wrapper around php-ffmpeg, used wherever an Action needs to run an
 * actual ffmpeg operation against a video file on disk.
 */
class FfmpegVideoProcessor
{
    /**
     * php-ffmpeg's X264 format defaults to 1000kb/s regardless of the
     * source's own bitrate, which can make a trimmed/resized output larger
     * than the original despite being shorter or smaller. Capping it here
     * keeps output size reasonable for typical web-delivery footage.
     */
    private const KILOBITRATE = 800;

    public function thumbnail(string $inputPath, string $outputPath, float $atSecond): void
    {
        FFMpeg::create()
            ->open($inputPath)
            ->frame(TimeCode::fromSeconds($atSecond))
            ->save($outputPath);
    }

    public function extractAudio(string $inputPath, string $outputPath): void
    {
        FFMpeg::create()
            ->open($inputPath)
            ->save(new Mp3, $outputPath);
    }

    public function trim(string $inputPath, string $outputPath, float $startSeconds, float $durationSeconds): void
    {
        FFMpeg::create()
            ->open($inputPath)
            ->clip(TimeCode::fromSeconds($startSeconds), TimeCode::fromSeconds($durationSeconds))
            ->save($this->x264Format(), $outputPath);
    }

    public function resize(string $inputPath, string $outputPath, int $width, int $height): void
    {
        $video = FFMpeg::create()->open($inputPath);

        $video->filters()->resize(new Dimension($width, $height))->synchronize();
        $video->save($this->x264Format(), $outputPath);
    }

    private function x264Format(): X264
    {
        return (new X264)->setKiloBitrate(self::KILOBITRATE);
    }
}
