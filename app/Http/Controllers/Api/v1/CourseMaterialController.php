<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Images and learning materials (YouTube, PDF, audio, image) for courses and modules.
 */
class CourseMaterialController extends Controller
{
    private const IMAGE_MIMES = 'image/jpeg,image/png,image/webp,image/gif';

    private const AUDIO_MIMES = 'audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/aac,audio/flac,audio/mp4,audio/x-m4a';

    public function __construct(protected SettingsService $settings) {}

    /**
     * Upload an image to embed inside a rich text editor. Returns a public URL.
     */
    public function uploadEditorImage(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('courses.update'), 403, 'This action is unauthorized.');

        $request->validate([
            'image' => ['required', 'file', 'mimetypes:'.self::IMAGE_MIMES, 'max:'.$this->maxKb('image')],
        ], $this->messages());

        $path = $request->file('image')->store('editor-images', 'public');

        return response()->json([
            'message' => 'Image uploaded successfully.',
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
        ], 201);
    }

    public function setCourseCover(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $path = $this->storeCover($request, 'course-covers');
        $this->deleteStoredFile($course->thumbnail_disk, $course->thumbnail_path);

        $course->update(['thumbnail_path' => $path, 'thumbnail_disk' => 'public']);

        return response()->json([
            'message' => 'Course cover image updated.',
            'thumbnail_url' => $course->fresh()->thumbnail_url,
        ]);
    }

    public function removeCourseCover(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $this->deleteStoredFile($course->thumbnail_disk, $course->thumbnail_path);
        $course->update(['thumbnail_path' => null]);

        return response()->json(['message' => 'Course cover image removed.']);
    }

    public function setModuleCover(Request $request, Module $module): JsonResponse
    {
        $this->authorize('update', $module);

        $path = $this->storeCover($request, 'module-covers');
        $this->deleteStoredFile($module->image_disk, $module->image_path);

        $module->update(['image_path' => $path, 'image_disk' => 'public']);

        return response()->json([
            'message' => 'Module image updated.',
            'image_url' => $module->fresh()->image_url,
        ]);
    }

    public function removeModuleCover(Request $request, Module $module): JsonResponse
    {
        $this->authorize('update', $module);

        $this->deleteStoredFile($module->image_disk, $module->image_path);
        $module->update(['image_path' => null]);

        return response()->json(['message' => 'Module image removed.']);
    }

    public function storeCourseAttachment(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        return $this->storeAttachment($request, $course);
    }

    public function storeModuleAttachment(Request $request, Module $module): JsonResponse
    {
        $this->authorize('update', $module);

        return $this->storeAttachment($request, $module);
    }

    public function destroyAttachment(Request $request, Attachment $attachment): JsonResponse
    {
        $parent = $attachment->attachable;
        abort_unless($parent, 404);
        $this->authorize('update', $parent);

        $this->deleteStoredFile($attachment->file_disk, $attachment->file_path);
        $attachment->delete();

        return response()->json(['message' => 'Learning material removed.']);
    }

    private function storeAttachment(Request $request, Model $parent): JsonResponse
    {
        if ($request->filled('youtube_video_id')) {
            $request->merge(['youtube_video_id' => Lesson::extractYouTubeId($request->input('youtube_video_id'))]);
        }

        $kind = $request->input('kind');

        $rules = [
            'kind' => ['required', 'in:'.implode(',', Attachment::KINDS)],
            'title' => ['nullable', 'string', 'max:255'],
        ];

        if ($kind === 'youtube') {
            $rules['youtube_video_id'] = ['required', 'string', 'regex:/^[A-Za-z0-9_-]{11}$/'];
        } elseif ($kind === 'pdf') {
            $rules['file'] = ['required', 'file', 'mimetypes:application/pdf', 'max:'.$this->maxKb('pdf')];
        } elseif ($kind === 'audio') {
            $rules['file'] = ['required', 'file', 'mimetypes:'.self::AUDIO_MIMES, 'max:'.$this->maxKb('audio')];
        } elseif ($kind === 'image') {
            $rules['file'] = ['required', 'file', 'mimetypes:'.self::IMAGE_MIMES, 'max:'.$this->maxKb('image')];
        }

        $data = $request->validate($rules, $this->messages());

        $attributes = [
            'kind' => $kind,
            'title' => $data['title'] ?? null,
            'order' => ((int) $parent->attachments()->max('order')) + 1,
        ];

        if ($kind === 'youtube') {
            $attributes['youtube_video_id'] = $data['youtube_video_id'];
        } else {
            /** @var UploadedFile $file */
            $file = $request->file('file');
            $disk = $kind === 'image' ? 'public' : config('filesystems.private_disk', 'local');
            $directory = $kind === 'image' ? 'course-materials/images' : 'course-materials';

            $attributes['file_path'] = $file->storeAs($directory, Str::uuid().'.'.($file->getClientOriginalExtension() ?: $this->defaultExtension($kind)), $disk);
            $attributes['file_disk'] = $disk;
            $attributes['file_name'] = $file->getClientOriginalName();
            $attributes['mime_type'] = $file->getMimeType();
            $attributes['file_size_bytes'] = $file->getSize();
        }

        $attachment = $parent->attachments()->create($attributes);

        return response()->json([
            'message' => 'Learning material added.',
            'attachment' => new AttachmentResource($attachment),
        ], 201);
    }

    private function storeCover(Request $request, string $directory): string
    {
        $request->validate([
            'image' => ['required', 'file', 'mimetypes:'.self::IMAGE_MIMES, 'max:'.$this->maxKb('image')],
        ], $this->messages());

        $path = $request->file('image')->store($directory, 'public');

        if (! $path) {
            throw ValidationException::withMessages(['image' => 'The image could not be stored.']);
        }

        return $path;
    }

    private function deleteStoredFile(?string $disk, ?string $path): void
    {
        if ($disk && $path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function maxKb(string $type): int
    {
        $defaults = ['audio' => 50, 'pdf' => 20, 'image' => 5];

        return (int) $this->settings->get("max_{$type}_upload_mb", $defaults[$type]) * 1024;
    }

    private function defaultExtension(string $kind): string
    {
        return ['pdf' => 'pdf', 'audio' => 'mp3', 'image' => 'jpg'][$kind] ?? 'bin';
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'file.mimetypes' => 'Uploaded file type is invalid for the selected material type.',
            'image.mimetypes' => 'Only JPEG, PNG, WebP or GIF images are allowed.',
            'file.max' => 'Uploaded file exceeds the maximum allowed upload limit in settings.',
            'image.max' => 'Image exceeds the maximum allowed upload limit in settings.',
            'youtube_video_id.regex' => 'A valid YouTube video ID or URL is required.',
        ];
    }
}

