<?php

namespace App\Http\Controllers;

use App\Actions\Camera\CreateCamera;
use App\Actions\Camera\DeleteCamera;
use App\Actions\Camera\ListCameras;
use App\Actions\Camera\ShowCamera;
use App\Actions\Camera\StreamCameraHls;
use App\Actions\Camera\UpdateCamera;
use App\Models\Camera;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CameraController extends Controller
{
    public function index(Request $request, ListCameras $listCameras)
    {
        try {
            return response()->json(['data' => $listCameras($request->user(), $request->integer('workspace_id') ?: null)]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function store(Request $request, CreateCamera $createCamera)
    {
        try {
            return response()->json($createCamera($request->user(), [
                'workspace_id' => $request->workspace_id,
                'name' => $request->name,
                'location' => $request->location,
                'stream_url' => $request->stream_url,
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

    public function show(Request $request, Camera $camera, ShowCamera $showCamera)
    {
        try {
            return response()->json($showCamera($request->user(), $camera));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function update(Request $request, Camera $camera, UpdateCamera $updateCamera)
    {
        try {
            return response()->json($updateCamera($request->user(), $camera, $request->only([
                'name', 'location', 'stream_url',
            ])));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function hls(Request $request, Camera $camera, string $path, StreamCameraHls $streamCameraHls)
    {
        try {
            // The {path} route parameter only ever captures the URL path -
            // MediaMTX's own session id for sub-playlist/segment requests
            // travels as a query string (e.g. ?session=...), which must be
            // forwarded too or MediaMTX rejects the request outright.
            if ($queryString = $request->getQueryString()) {
                $path .= "?{$queryString}";
            }

            return $streamCameraHls($request->user(), $camera, $path);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function destroy(Request $request, Camera $camera, DeleteCamera $deleteCamera)
    {
        try {
            $deleteCamera($request->user(), $camera);

            return response()->noContent();
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
