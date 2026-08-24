<?php

namespace App\Http\Controllers;

use App\Actions\Camera\CancelCameraRecording;
use App\Actions\Camera\CreateVideoFromRecordingClip;
use App\Actions\Camera\DownloadCameraRecording;
use App\Actions\Camera\ListCameraRecordings;
use App\Actions\Camera\StartCameraRecording;
use App\Models\Camera;
use App\Models\CameraRecording;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CameraRecordingController extends Controller
{
    public function index(Request $request, Camera $camera, ListCameraRecordings $listCameraRecordings)
    {
        try {
            return response()->json(['data' => $listCameraRecordings($request->user(), $camera)]);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function store(Request $request, Camera $camera, StartCameraRecording $startCameraRecording)
    {
        try {
            return response()->json($startCameraRecording($request->user(), $camera, [
                'duration_minutes' => $request->duration_minutes,
            ]), 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function cancel(Request $request, Camera $camera, CameraRecording $recording, CancelCameraRecording $cancelCameraRecording)
    {
        try {
            return response()->json($cancelCameraRecording($request->user(), $camera, $recording));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function clip(Request $request, Camera $camera, CameraRecording $recording, CreateVideoFromRecordingClip $createVideoFromRecordingClip)
    {
        try {
            return response()->json($createVideoFromRecordingClip($request->user(), $camera, $recording, [
                'start_seconds' => $request->start_seconds,
                'end_seconds' => $request->end_seconds,
                'title' => $request->title,
                'description' => $request->description,
                'analysis_types' => $request->analysis_types,
                'auto_start_analysis' => $request->auto_start_analysis,
                'analysis_config' => $request->analysis_config,
            ]), 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function download(Request $request, Camera $camera, CameraRecording $recording, DownloadCameraRecording $downloadCameraRecording)
    {
        try {
            return $downloadCameraRecording($request->user(), $camera, $recording);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
