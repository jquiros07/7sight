<?php

namespace App\Http\Controllers;

use App\Actions\Search\SearchVideos;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class SearchController extends Controller
{
    public function videos(Request $request, SearchVideos $searchVideos)
    {
        try {
            return response()->json($searchVideos($request->user(), [
                'query' => $request->query,
            ]));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
