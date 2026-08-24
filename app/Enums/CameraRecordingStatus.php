<?php

namespace App\Enums;

enum CameraRecordingStatus: string
{
    case Recording = 'recording';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
