<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutoringSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'scheduled_start' => $this->scheduled_start?->toISOString(),
            'scheduled_end' => $this->scheduled_end?->toISOString(),
            'mode' => $this->mode,
            'meeting_link' => $this->meeting_link,
            'location' => $this->location,
            'status' => $this->status,
            'cancellation_reason' => $this->cancellation_reason,
            'notice_waived' => (bool) $this->notice_waived,
            'waiver_reason' => $this->waiver_reason,
            'instructor_notes' => $this->instructor_notes,
            'student' => $this->student ? [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'email' => $this->student->email,
                'phone' => $this->student->phone,
            ] : null,
            'instructor' => $this->instructor ? [
                'id' => $this->instructor->id,
                'name' => $this->instructor->name,
                'email' => $this->instructor->email,
            ] : null,
            'course' => $this->course ? [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'slug' => $this->course->slug,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
