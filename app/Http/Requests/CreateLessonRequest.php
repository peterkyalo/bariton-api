<?php

namespace App\Http\Requests;

use App\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

class CreateLessonRequest extends FormRequest
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

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:1'],
            'type' => ['required', 'in:youtube,audio,pdf'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_preview' => ['nullable', 'boolean'],
        ];

        if ($type === 'youtube') {
            $rules['youtube_video_id'] = ['required', 'string', 'max:50'];
        } elseif ($type === 'audio') {
            $rules['file'] = [
                'required',
                'file',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/aac,audio/flac,audio/mp4,audio/x-m4a',
                "max:{$maxAudioKb}",
            ];
        } elseif ($type === 'pdf') {
            $rules['file'] = [
                'required',
                'file',
                'mimetypes:application/pdf',
                "max:{$maxPdfKb}",
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'file.mimetypes' => 'Uploaded file type is invalid for the selected lesson format.',
            'file.max' => 'Uploaded file exceeds the maximum allowed upload limit in settings.',
            'youtube_video_id.required' => 'A valid YouTube video ID or URL is required for YouTube lessons.',
        ];
    }
}
