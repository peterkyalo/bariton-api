<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\MediaStreamService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MediaStreamController extends Controller
{
    public function __construct(
        protected MediaStreamService $mediaStreamService
    ) {}

    /**
     * Secure media streaming endpoint supporting HTTP Range requests for audio and downloads for PDF.
     * Route must be signed.
     */
    public function stream(Request $request, string $uuid): Response
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Invalid or expired media signature.');
        }

        $lesson = Lesson::where('uuid', $uuid)->firstOrFail();

        return $this->mediaStreamService->streamLessonFile($lesson, $request);
    }
}
