<?php

namespace App\Http\Requests;

use App\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('courses.update') ?? false;
    }

    public function rules(): array
    {
        $settingsService = app(SettingsService::class);
        $maxAudioKb = (int) $settingsService->get('max_audio_upload_mb', 50) * 1024;
        $maxPdfKb = (int) $settingsService->get('max_pdf_upload_mb', 20) * 1024;

        $type = $this->input('type');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:1'],
            'type' => ['sometimes', 'required', 'in:youtube,audio,pdf'],
            'youtube_video_id' => ['nullable', 'string', 'max:50'],
            'file' => [
                'nullable',
                'file',
                function ($attribute, $value, $fail) use ($type, $maxAudioKb, $maxPdfKb) {
                    if ($type === 'audio') {
                        $allowed = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/aac', 'audio/flac', 'audio/mp4', 'audio/x-m4a'];
                        if (! in_array($value->getMimeType(), $allowed)) {
                            $fail('The audio file must be a valid audio format.');
                        }
                        if ($value->getSize() > $maxAudioKb * 1024) {
                            $fail("The audio file exceeds {$maxAudioKb} KB limit.");
                        }
                    } elseif ($type === 'pdf') {
                        if ($value->getMimeType() !== 'application/pdf') {
                            $fail('The document must be a valid PDF file.');
                        }
                        if ($value->getSize() > $maxPdfKb * 1024) {
                            $fail("The PDF file exceeds {$maxPdfKb} KB limit.");
                        }
                    }
                },
            ],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_preview' => ['nullable', 'boolean'],
        ];
    }
}
