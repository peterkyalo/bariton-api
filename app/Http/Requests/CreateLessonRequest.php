<?php

namespace App\Http\Requests;

use App\Models\Lesson;
use App\Services\SettingsService;
use App\Http\Requests\Concerns\SanitizesRichText;
use Illuminate\Foundation\Http\FormRequest;

class CreateLessonRequest extends FormRequest
{
    use SanitizesRichText;

    public function authorize(): bool
    {
        return $this->user()?->can('courses.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeRichTextFields(['content']);
        if ($this->filled('youtube_video_id')) {
            $this->merge([
                'youtube_video_id' => Lesson::extractYouTubeId($this->input('youtube_video_id')),
            ]);
        }
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
            'type' => ['required', 'in:youtube,audio,pdf,richtext,text'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_preview' => ['nullable', 'boolean'],
            'content' => ['nullable', 'string'],
        ];

        if ($type === 'youtube') {
            $rules['youtube_video_id'] = ['required', 'string', 'max:100'];
        } elseif ($type === 'richtext' || $type === 'text') {
            $rules['content'] = ['required', 'string'];
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
            'content.required' => 'The lesson content / rich text material is required.',
        ];
    }
}
