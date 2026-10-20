<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    /**
     * Determine if a student's enrollment satisfies all certificate criteria:
     * 1. All lessons completed in all course modules.
     * 2. All assignments passed in all course modules.
     * 3. Final exam attempted and passed.
     */
    public function isEligibleForCertificate(Enrollment $enrollment, ?ExamAttempt $examAttempt = null): bool
    {
        $course = Course::with(['modules.lessons', 'modules.assignments', 'exam'])
            ->find($enrollment->course_id);

        if (! $course) {
            return false;
        }

        // 1. Check all lessons completed (applicable only for standard track; validation track assesses through assignments & exams only)
        if ($enrollment->path_type !== 'validation') {
            $totalLessonIds = $course->modules->flatMap(function ($mod) {
                return $mod->lessons;
            })->pluck('id');

            if ($totalLessonIds->isNotEmpty()) {
                $completedCount = $enrollment->lessonProgress()
                    ->whereIn('lesson_id', $totalLessonIds)
                    ->where('status', 'completed')
                    ->count();

                if ($completedCount < $totalLessonIds->count()) {
                    return false;
                }
            }
        }

        // 2. Check all assignments passed
        $assignments = $course->modules->flatMap(function ($mod) {
            return $mod->assignments;
        });

        if ($assignments->isNotEmpty()) {
            foreach ($assignments as $assignment) {
                $hasPassed = $enrollment->assignmentSubmissions()
                    ->where('assignment_id', $assignment->id)
                    ->where('passed', true)
                    ->exists();

                if (! $hasPassed) {
                    return false;
                }
            }
        }

        // 3. Check final exam passed
        if ($course->exam) {
            $hasPassedExam = ($examAttempt && $examAttempt->passed) || $enrollment->examAttempts()
                ->where('exam_id', $course->exam->id)
                ->where('passed', true)
                ->exists();

            if (! $hasPassedExam) {
                return false;
            }
        }

        return true;
    }

    /**
     * Issue an official verifiable Certificate for an enrollment if eligible.
     */
    public function issueIfEligible(Enrollment $enrollment, ?ExamAttempt $examAttempt = null): ?Certificate
    {
        if (! $this->isEligibleForCertificate($enrollment, $examAttempt)) {
            return null;
        }

        return $this->issue($enrollment, $examAttempt);
    }

    /**
     * Issue an official Certificate.
     */
    public function issue(Enrollment $enrollment, ?ExamAttempt $examAttempt = null): Certificate
    {
        // Return existing issued certificate if present
        $existing = Certificate::where('enrollment_id', $enrollment->id)
            ->where('status', 'issued')
            ->first();

        if ($existing) {
            return $existing;
        }

        $course = $enrollment->course;
        $user = $enrollment->user;
        $code = 'BMAS-CERT-'.strtoupper(Str::random(8));

        // Generate QR code SVG
        $verifyBaseUrl = config('services.certificate.verify_base_url', 'https://app.bmas.com/verify');
        $verifyUrl = rtrim($verifyBaseUrl, '/').'/'.$code;

        $qrCodeSvg = $this->generateQrCodeSvg($verifyUrl);
        $disk = config('filesystems.private_disk', 'local');
        $pdfPath = "certificates/{$code}.pdf";

        $certificate = Certificate::create([
            'uuid' => (string) Str::uuid(),
            'certificate_code' => $code,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrollment_id' => $enrollment->id,
            'exam_attempt_id' => $examAttempt?->id,
            'holder_name' => $user->name, // Frozen at issue time
            'course_title' => $course->title, // Frozen at issue time
            'path_type' => $enrollment->path_type ?? 'standard',
            'status' => 'issued',
            'pdf_path' => $pdfPath,
            'pdf_disk' => $disk,
            'qr_code_svg' => $qrCodeSvg,
            'issued_at' => now(),
        ]);

        $this->renderAndSavePdf($certificate);

        activity()
            ->performedOn($certificate)
            ->causedBy($user)
            ->log('Certificate issued');

        return $certificate;
    }

    /**
     * Revoke an issued certificate.
     */
    public function revoke(Certificate $certificate, User $admin, string $reason): Certificate
    {
        $certificate->update([
            'status' => 'revoked',
            'revocation_reason' => $reason,
            'revoked_at' => now(),
            'revoked_by' => $admin->id,
        ]);

        // Re-render PDF with REVOKED stamp
        $this->renderAndSavePdf($certificate);

        activity()
            ->performedOn($certificate)
            ->causedBy($admin)
            ->withProperties(['reason' => $reason])
            ->log('Certificate revoked');

        return $certificate;
    }

    /**
     * Reissue a certificate after a holder name correction.
     * The old certificate status becomes 'replaced' pointing to new certificate code.
     */
    public function reissue(Certificate $oldCertificate, User $admin, string $newHolderName, ?string $reason = null): Certificate
    {
        $enrollment = $oldCertificate->enrollment;
        $newCode = 'BMAS-CERT-'.strtoupper(Str::random(8));

        $verifyBaseUrl = config('services.certificate.verify_base_url', 'https://app.bmas.com/verify');
        $verifyUrl = rtrim($verifyBaseUrl, '/').'/'.$newCode;
        $qrCodeSvg = $this->generateQrCodeSvg($verifyUrl);

        $disk = config('filesystems.private_disk', 'local');
        $pdfPath = "certificates/{$newCode}.pdf";

        // Create new certificate with corrected holder name
        $newCertificate = Certificate::create([
            'uuid' => (string) Str::uuid(),
            'certificate_code' => $newCode,
            'user_id' => $oldCertificate->user_id,
            'course_id' => $oldCertificate->course_id,
            'enrollment_id' => $oldCertificate->enrollment_id,
            'exam_attempt_id' => $oldCertificate->exam_attempt_id,
            'holder_name' => trim($newHolderName),
            'course_title' => $oldCertificate->course_title,
            'path_type' => $oldCertificate->path_type,
            'status' => 'issued',
            'reissue_replaces_code' => $oldCertificate->certificate_code,
            'pdf_path' => $pdfPath,
            'pdf_disk' => $disk,
            'qr_code_svg' => $qrCodeSvg,
            'issued_at' => now(),
        ]);

        $this->renderAndSavePdf($newCertificate);

        // Update old certificate
        $oldCertificate->update([
            'status' => 'replaced',
            'reissue_replaced_by_code' => $newCode,
            'revocation_reason' => $reason ?? "Reissued with corrected holder name: {$newHolderName}",
        ]);

        $this->renderAndSavePdf($oldCertificate);

        activity()
            ->performedOn($newCertificate)
            ->causedBy($admin)
            ->withProperties([
                'old_code' => $oldCertificate->certificate_code,
                'new_name' => $newHolderName,
                'reason' => $reason,
            ])
            ->log('Certificate reissued with name correction');

        return $newCertificate;
    }

    /**
     * Generate BaconQrCode SVG.
     */
    public function generateQrCodeSvg(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(140),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    /**
     * Render certificate PDF and store to disk.
     */
    public function renderAndSavePdf(Certificate $certificate): void
    {
        try {
            if (empty($certificate->pdf_path)) {
                $certificate->pdf_path = "certificates/{$certificate->certificate_code}.pdf";
                $certificate->pdf_disk = config('filesystems.private_disk', 'local');
                $certificate->saveQuietly();
            }

            if (empty($certificate->qr_code_svg)) {
                $verifyBaseUrl = config('services.certificate.verify_base_url', 'https://app.bmas.com/verify');
                $certificate->qr_code_svg = $this->generateQrCodeSvg(rtrim($verifyBaseUrl, '/').'/'.$certificate->certificate_code);
                $certificate->saveQuietly();
            }

            $qrBase64 = $certificate->qr_code_svg ? base64_encode($certificate->qr_code_svg) : '';
            $pdf = Pdf::loadView('certificates.pdf', [
                'certificate' => $certificate,
                'qrCodeBase64' => $qrBase64,
            ])->setPaper('a4', 'landscape');

            Storage::disk($certificate->pdf_disk ?: 'local')->put($certificate->pdf_path, $pdf->output());
        } catch (\Exception $e) {
            Log::warning('Certificate PDF rendering notice: '.$e->getMessage());
        }
    }
}
