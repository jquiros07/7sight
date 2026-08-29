<?php

namespace App\Http\Controllers;

use App\Actions\Video\AnalyzeVideo;
use App\Actions\Video\DeleteVideo;
use App\Actions\Video\DownloadVideoToolJobOutput;
use App\Actions\Video\ExtractVideoAudio;
use App\Actions\Video\FlagAnalysisJobForReview;
use App\Actions\Video\GenerateVideoInsights;
use App\Actions\Video\GenerateVideoReport;
use App\Actions\Video\GenerateVideoThumbnail;
use App\Actions\Video\InquireAboutVideo;
use App\Actions\Video\ListVideoInquiries;
use App\Actions\Video\ListVideos;
use App\Actions\Video\RequestAiContentAnalysis;
use App\Actions\Video\RequestVideoResize;
use App\Actions\Video\RequestVideoTrim;
use App\Actions\Video\ShowAiContentAnalysis;
use App\Actions\Video\ShowVideo;
use App\Actions\Video\ShowVideoThumbnail;
use App\Actions\Video\ShowVideoToolJob;
use App\Actions\Video\StreamVideo;
use App\Actions\Video\UnflagAnalysisJobForReview;
use App\Actions\Video\UpdateVideo;
use App\Actions\Video\UploadVideo;
use App\Enums\AnalysisType;
use App\Jobs\GenerateVideoInsightsJob;
use App\Models\AiContentAnalysis;
use App\Models\AnalysisJob;
use App\Models\Video;
use App\Models\VideoToolJob;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            return response()->json($uploadVideo($request->user(), [
                'workspace_id' => $request->workspace_id,
                'title' => $request->title,
                'description' => $request->description,
                'file' => $request->file('file'),
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

    public function stream(Request $request, Video $video, StreamVideo $streamVideo)
    {
        try {
            return $streamVideo($request->user(), $video);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function report(Request $request, Video $video, GenerateVideoReport $generateVideoReport)
    {
        try {
            return $generateVideoReport($request->user(), $video);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function insights(Request $request, Video $video, GenerateVideoInsights $generateVideoInsights)
    {
        try {
            $validated = $request->validate([
                'type' => ['nullable', 'string', Rule::in([
                    AnalysisType::ObjectDetection->value,
                    AnalysisType::ThreatDetection->value,
                    AnalysisType::ContentModeration->value,
                    AnalysisType::TextDetection->value,
                ])],
            ]);

            $type = isset($validated['type']) ? AnalysisType::from($validated['type']) : null;

            return response()->json($generateVideoInsights($request->user(), $video, $type));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    /**
     * Called internally by the analysis-worker once a video's analysis jobs
     * all complete. Queues the full insights generation to run as the
     * video's uploader, since there's no requesting user for this call.
     */
    public function generateInsights(Video $video)
    {
        try {
            GenerateVideoInsightsJob::dispatch($video);

            return response()->json(['message' => 'Insight generation queued.'], 202);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function update(Request $request, Video $video, UpdateVideo $updateVideo)
    {
        try {
            return $updateVideo($request->user(), $video, $request->only([
                'title', 'description', 'analysis_types', 'auto_start_analysis', 'analysis_config',
            ]));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function analyze(Request $request, Video $video, AnalyzeVideo $analyzeVideo)
    {
        try {
            return response()->json($analyzeVideo($request->user(), $video));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function inquiries(Request $request, Video $video, ListVideoInquiries $listVideoInquiries)
    {
        try {
            return response()->json($listVideoInquiries($request->user(), $video, $request->query()));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function inquire(Request $request, Video $video, InquireAboutVideo $inquireAboutVideo)
    {
        try {
            return response()->json($inquireAboutVideo($request->user(), $video, [
                'question' => $request->question,
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

    public function flagAnalysisJob(Request $request, Video $video, AnalysisJob $analysisJob, FlagAnalysisJobForReview $flagAnalysisJobForReview)
    {
        try {
            $validated = $request->validate([
                'note' => ['nullable', 'string', 'max:2000'],
            ]);

            return response()->json($flagAnalysisJobForReview($request->user(), $video, $analysisJob, $validated['note'] ?? null));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function unflagAnalysisJob(Request $request, Video $video, AnalysisJob $analysisJob, UnflagAnalysisJobForReview $unflagAnalysisJobForReview)
    {
        try {
            return response()->json($unflagAnalysisJobForReview($request->user(), $video, $analysisJob));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function generateThumbnail(Request $request, Video $video, GenerateVideoThumbnail $generateVideoThumbnail)
    {
        try {
            return response()->json($generateVideoThumbnail($request->user(), $video));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function showThumbnail(Request $request, Video $video, ShowVideoThumbnail $showVideoThumbnail)
    {
        try {
            return $showVideoThumbnail($request->user(), $video);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function extractAudio(Request $request, Video $video, ExtractVideoAudio $extractVideoAudio)
    {
        try {
            return $extractVideoAudio($request->user(), $video);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function trim(Request $request, Video $video, RequestVideoTrim $requestVideoTrim)
    {
        try {
            return response()->json($requestVideoTrim($request->user(), $video, [
                'start_seconds' => $request->start_seconds,
                'end_seconds' => $request->end_seconds,
            ]), 202);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function resize(Request $request, Video $video, RequestVideoResize $requestVideoResize)
    {
        try {
            return response()->json($requestVideoResize($request->user(), $video, [
                'width' => $request->width,
                'height' => $request->height,
            ]), 202);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function showToolJob(Request $request, Video $video, VideoToolJob $videoToolJob, ShowVideoToolJob $showVideoToolJob)
    {
        try {
            return response()->json($showVideoToolJob($request->user(), $video, $videoToolJob));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function downloadToolJob(Request $request, Video $video, VideoToolJob $videoToolJob, DownloadVideoToolJobOutput $downloadVideoToolJobOutput)
    {
        try {
            return $downloadVideoToolJobOutput($request->user(), $video, $videoToolJob);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function requestAiContentAnalysis(Request $request, Video $video, RequestAiContentAnalysis $requestAiContentAnalysis)
    {
        try {
            return response()->json($requestAiContentAnalysis($request->user(), $video, [
                'start_seconds' => $request->start_seconds,
                'end_seconds' => $request->end_seconds,
            ]), 202);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Request failed.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function showAiContentAnalysis(Request $request, Video $video, AiContentAnalysis $aiContentAnalysis, ShowAiContentAnalysis $showAiContentAnalysis)
    {
        try {
            return response()->json($showAiContentAnalysis($request->user(), $video, $aiContentAnalysis));
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
