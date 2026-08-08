<?php

namespace App\Http\Controllers;

use App\Actions\ObjectDetection\ListObjectDetectionCategories;
use Throwable;

class ObjectDetectionCategoryController extends Controller
{
    public function index(ListObjectDetectionCategories $listCategories)
    {
        try {
            return response()->json(['data' => $listCategories()]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
