<?php

namespace App\Http\Controllers;

use App\Actions\Video\DeleteVideo;
use App\Actions\Video\ListVideos;
use App\Actions\Video\ShowVideo;
use App\Actions\Video\UpdateVideo;
use App\Actions\Video\UploadVideo;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class VideoController extends Controller
{
    public function index(Request $request, ListVideos $listVideos)
    {
        try {
            return $listVideos($request->user(), $request->query());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function store(Request $request, UploadVideo $uploadVideo)
    {
        try {
            return response()->json($uploadVideo($request->user(), $request->all()), 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function show(Request $request, Video $video, ShowVideo $showVideo)
    {
        try {
            return $showVideo($request->user(), $video);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function update(Request $request, Video $video, UpdateVideo $updateVideo)
    {
        try {
            return $updateVideo($request->user(), $video, $request->all());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function destroy(Request $request, Video $video, DeleteVideo $deleteVideo)
    {
        try {
            $deleteVideo($request->user(), $video);

            return response()->noContent();
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
