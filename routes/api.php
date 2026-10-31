<?php

use App\Http\Controllers\Api\v1\Admin\ActivityLogController;
use App\Http\Controllers\Api\v1\Admin\CourseController;
use App\Http\Controllers\Api\v1\Admin\NotificationLogController;
use App\Http\Controllers\Api\v1\Admin\NotificationTemplateController;
use App\Http\Controllers\Api\v1\Admin\RoleController;
use App\Http\Controllers\Api\v1\Admin\SettingController;
use App\Http\Controllers\Api\v1\Admin\UserController;
use App\Http\Controllers\Api\v1\AdminCertificateController;
use App\Http\Controllers\Api\v1\AdminCmsController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\CertificateVerificationController;
use App\Http\Controllers\Api\v1\ChunkedUploadController;
use App\Http\Controllers\Api\v1\CmsPageController;
use App\Http\Controllers\Api\v1\ContactInquiryController;
use App\Http\Controllers\Api\v1\CoordinatorEventController;
use App\Http\Controllers\Api\v1\CourseCatalogController;
use App\Http\Controllers\Api\v1\DashboardController;
use App\Http\Controllers\Api\v1\EventController;
use App\Http\Controllers\Api\v1\FinanceEventController;
use App\Http\Controllers\Api\v1\FinancePaymentController;
use App\Http\Controllers\Api\v1\HealthCheckController;
use App\Http\Controllers\Api\v1\InstructorCourseController;
use App\Http\Controllers\Api\v1\InstructorExamGradingController;
use App\Http\Controllers\Api\v1\InstructorGradingController;
use App\Http\Controllers\Api\v1\InstructorTutoringController;
use App\Http\Controllers\Api\v1\MediaStreamController;
use App\Http\Controllers\Api\v1\OrderController;
use App\Http\Controllers\Api\v1\PaymentController;
use App\Http\Controllers\Api\v1\ProfileController;
use App\Http\Controllers\Api\v1\PublicSettingController;
use App\Http\Controllers\Api\v1\RefundController;
use App\Http\Controllers\Api\v1\RubricController;
use App\Http\Controllers\Api\v1\StudentAssignmentController;
use App\Http\Controllers\Api\v1\StudentCourseController;
use App\Http\Controllers\Api\v1\StudentEventBookingController;
use App\Http\Controllers\Api\v1\StudentExamController;
use App\Http\Controllers\Api\v1\StudentNotificationPreferenceController;
use App\Http\Controllers\Api\v1\StudentTutoringController;
use App\Http\Controllers\Api\v1\TutoringPackageController;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bariton API v1 Routes
|--------------------------------------------------------------------------
*/

// Public and Guest Endpoints
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:3,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:5,1');
    Route::post('token', [AuthController::class, 'token'])->middleware('throttle:5,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');

    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
});

Route::post('guardian/confirm', [AuthController::class, 'confirmGuardian'])->middleware('throttle:10,1');
Route::get('settings/public', [PublicSettingController::class, 'index'])->middleware('throttle:60,1');
Route::get('health', [HealthCheckController::class, 'check']);

// Public Course Catalog & Preview
Route::get('courses', [CourseCatalogController::class, 'index']);
Route::get('courses/{slug}', [CourseCatalogController::class, 'show']);
Route::get('courses/{slug}/preview/{lessonUuid}', [CourseCatalogController::class, 'preview']);

// Public Physical Events (Phase 6)
Route::get('events', [EventController::class, 'index']);
Route::get('events/{slug}', [EventController::class, 'show']);

// Public Tutoring Packages & Open Slots (Phase 7)
Route::get('tutoring/packages', [TutoringPackageController::class, 'index']);
Route::get('tutoring/packages/{slug}', [TutoringPackageController::class, 'show']);
Route::get('tutoring/instructors', [StudentTutoringController::class, 'getInstructors']);
Route::get('tutoring/instructors/{instructorId}/slots', [StudentTutoringController::class, 'getAvailableSlots']);

// Signed Media Streaming
Route::get('media/lessons/{uuid}', [MediaStreamController::class, 'stream'])->name('media.lessons.stream');
Route::get('media/attachments/{uuid}', [MediaStreamController::class, 'streamAttachment'])->name('media.attachments.stream');
Route::get('media/submissions/{uuid}', [MediaStreamController::class, 'streamSubmission'])->name('media.submissions.stream');

// Payment Webhooks
Route::post('payments/mpesa/callback/{token}', [PaymentController::class, 'mpesaCallback']);
Route::post('webhooks/stripe', [PaymentController::class, 'stripeWebhook']);
Route::post('webhooks/paypal', [PaymentController::class, 'paypalWebhook']);

// Public Certificate Verification (Rate Limited)
Route::get('certificates/verify/{code}', [CertificateVerificationController::class, 'verify'])
    ->middleware('throttle:30,1');

// Public CMS Pages & Contact Inquiries
Route::get('pages', [CmsPageController::class, 'index']);
Route::get('pages/{slug}', [CmsPageController::class, 'show']);
Route::post('contact', [ContactInquiryController::class, 'store'])
    ->middleware('throttle:10,1');

// Authenticated Endpoints (Sanctum cookie or token)
Route::middleware(['auth:sanctum', EnsureUserIsActive::class, EnsureTwoFactorEnabled::class])->group(function () {
    // Auth Session / Token
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::delete('auth/token', [AuthController::class, 'revokeToken']);
    Route::post('auth/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:3,1');

    // Profile & Security
    Route::get('me', [ProfileController::class, 'me']);
    Route::put('me', [ProfileController::class, 'update']);
    Route::put('me/password', [ProfileController::class, 'updatePassword']);

    // Two-Factor Authentication
    Route::post('me/two-factor-authentication', [ProfileController::class, 'enableTwoFactor']);
    Route::get('me/two-factor-qr-code', [ProfileController::class, 'twoFactorQrCode']);
    Route::get('me/two-factor-secret-key', [ProfileController::class, 'twoFactorSecret']);
    Route::post('me/confirmed-two-factor-authentication', [ProfileController::class, 'confirmTwoFactor']);
    Route::get('me/two-factor-recovery-codes', [ProfileController::class, 'recoveryCodes']);
    Route::delete('me/two-factor-authentication', [ProfileController::class, 'disableTwoFactor']);

    // GDPR / Kenya Data Protection Act Portability & Deletion
    Route::get('me/data-exports', [ProfileController::class, 'dataExports']);
    Route::post('me/data-exports', [ProfileController::class, 'requestDataExport'])->middleware('throttle:3,1');
    Route::get('me/data-exports/{id}/download', [ProfileController::class, 'downloadDataExport']);
    Route::post('me/deletion-request', [ProfileController::class, 'requestDeletion']);
    Route::delete('me/deletion-request', [ProfileController::class, 'cancelDeletion']);

    // Student Learning, Orders & Refunds
    Route::prefix('student')->group(function () {
        Route::get('enrollments', [StudentCourseController::class, 'enrollments']);
        Route::get('enrollments/{uuid}', [StudentCourseController::class, 'enrollmentDetail']);
        Route::get('lessons/{uuid}', [StudentCourseController::class, 'lessonDetail']);
        Route::post('lessons/{uuid}/progress', [StudentCourseController::class, 'recordProgress']);
        Route::get('orders', [OrderController::class, 'studentOrders']);
        Route::get('receipts/{uuid}/download', [OrderController::class, 'downloadReceipt']);
        Route::post('orders/{uuid}/refund-requests', [RefundController::class, 'store']);
        Route::get('refund-requests', [RefundController::class, 'studentIndex']);
        Route::get('credit-notes/{uuid}/download', [RefundController::class, 'downloadCreditNote']);
        Route::get('modules/{moduleUuid}/assignments', [StudentAssignmentController::class, 'moduleAssignments']);
        Route::get('assignments/{uuid}', [StudentAssignmentController::class, 'show']);
        Route::post('assignments/{uuid}/submit', [StudentAssignmentController::class, 'submit']);

        // Final Exams & Attempts (Phase 4b)
        Route::get('courses/{courseSlugOrUuid}/exam', [StudentExamController::class, 'showCourseExam']);
        Route::post('exams/{examUuid}/start', [StudentExamController::class, 'start']);
        Route::get('exams/attempts/{attemptUuid}', [StudentExamController::class, 'showAttempt']);
        Route::post('exams/attempts/{attemptUuid}/answers', [StudentExamController::class, 'saveAnswers']);
        Route::post('exams/attempts/{attemptUuid}/record-stop', [StudentExamController::class, 'recordStop']);
        Route::post('exams/attempts/{attemptUuid}/submit', [StudentExamController::class, 'submit']);

        // Verifiable Certificates (Phase 4b)
        Route::get('certificates', [StudentExamController::class, 'certificates']);
        Route::get('certificates/{uuid}/download', [StudentExamController::class, 'downloadCertificate']);

        // Bootcamps & Workshops (Phase 6)
        Route::get('events/bookings', [StudentEventBookingController::class, 'index']);
        Route::get('events/bookings/{uuid}', [StudentEventBookingController::class, 'show']);
        Route::post('events/bookings/{uuid}/refund', [StudentEventBookingController::class, 'requestRefund']);
        Route::get('events/waitlists', [StudentEventBookingController::class, 'myWaitlists']);

        // One-on-One Tutoring (Phase 7)
        Route::get('tutoring/packages', [StudentTutoringController::class, 'myPackages']);
        Route::post('tutoring/book', [StudentTutoringController::class, 'book']);
        Route::get('tutoring/sessions', [StudentTutoringController::class, 'mySessions']);
        Route::post('tutoring/sessions/{uuid}/reschedule', [StudentTutoringController::class, 'reschedule']);

        // Notification Preferences (Phase 8)
        Route::get('notification-preferences', [StudentNotificationPreferenceController::class, 'show']);
        Route::put('notification-preferences', [StudentNotificationPreferenceController::class, 'update']);
    });

    // Event Online Holds & Waitlists (Phase 6)
    Route::post('events/{slug}/book', [EventController::class, 'book']);
    Route::post('events/{slug}/waitlist', [EventController::class, 'waitlist']);

    // Resumable Chunked Uploads
    Route::prefix('uploads/chunked')->group(function () {
        Route::post('initiate', [ChunkedUploadController::class, 'initiate']);
        Route::post('{uuid}/chunk', [ChunkedUploadController::class, 'uploadChunk']);
        Route::get('{uuid}/status', [ChunkedUploadController::class, 'status']);
        Route::post('{uuid}/complete', [ChunkedUploadController::class, 'complete']);
    });

    // Orders & Payments
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{uuid}', [OrderController::class, 'show']);
    Route::post('orders/{uuid}/pay/mpesa', [PaymentController::class, 'initiateMpesa']);
    Route::post('orders/{uuid}/pay/stripe', [PaymentController::class, 'initiateStripe']);
    Route::post('orders/{uuid}/pay/stripe/confirm', [PaymentController::class, 'confirmStripe']);
    Route::post('orders/{uuid}/pay/paypal', [PaymentController::class, 'initiatePaypal']);
    Route::post('orders/{uuid}/pay/paypal/capture', [PaymentController::class, 'capturePaypal']);
    Route::get('orders/{uuid}/status', [PaymentController::class, 'checkStatus']);

    // Finance Operations
    Route::prefix('finance')->middleware('permission:dashboard.finance')->group(function () {
        Route::get('orders', [FinancePaymentController::class, 'orders']);
        Route::post('cash-payment', [FinancePaymentController::class, 'recordCash']);
        Route::get('payments', [FinancePaymentController::class, 'payments']);
        Route::get('refund-requests', [RefundController::class, 'financeIndex']);
        Route::post('refund-requests/{uuid}/review', [RefundController::class, 'financeReview']);
        Route::get('credit-notes', [RefundController::class, 'financeCreditNotes']);

        // Physical Events Finance (Phase 6)
        Route::post('events/{slug}/cash-hold', [FinanceEventController::class, 'createCashHold']);
        Route::get('event-refunds', [FinanceEventController::class, 'refunds']);
        Route::post('event-refunds/{uuid}/approve', [FinanceEventController::class, 'approveRefund']);
        Route::post('event-refunds/{uuid}/reject', [FinanceEventController::class, 'rejectRefund']);
    });

    // Coordinator Event Management (Phase 6)
    Route::prefix('coordinator')->middleware('permission:events.manage')->group(function () {
        Route::get('events', [CoordinatorEventController::class, 'index']);
        Route::post('events', [CoordinatorEventController::class, 'store']);
        Route::get('events/{id}', [CoordinatorEventController::class, 'show']);
        Route::put('events/{id}', [CoordinatorEventController::class, 'update']);
        Route::get('events/{id}/attendees', [CoordinatorEventController::class, 'attendees']);
        Route::post('events/bookings/{uuid}/checkin', [CoordinatorEventController::class, 'checkIn']);
        Route::post('events/{id}/reminders', [CoordinatorEventController::class, 'sendReminders']);
    });

    // Instructor Course Management
    Route::prefix('instructor')->middleware('permission:courses.teach|tutoring.manage-availability')->group(function () {
        Route::get('courses', [InstructorCourseController::class, 'index']);
        Route::get('courses/{course}', [InstructorCourseController::class, 'show']);
        Route::post('courses/{course}/modules', [InstructorCourseController::class, 'createModule']);
        Route::put('modules/{module}', [InstructorCourseController::class, 'updateModule']);
        Route::delete('modules/{module}', [InstructorCourseController::class, 'deleteModule']);
        Route::post('modules/{module}/lessons', [InstructorCourseController::class, 'createLesson']);
        Route::put('lessons/{lesson}', [InstructorCourseController::class, 'updateLesson']);
        Route::delete('lessons/{lesson}', [InstructorCourseController::class, 'deleteLesson']);

        // Assessment Grading & Rubrics
        Route::get('grading', [InstructorGradingController::class, 'queue']);
        Route::get('grading/{uuid}', [InstructorGradingController::class, 'show']);
        Route::post('grading/{uuid}/grade', [InstructorGradingController::class, 'grade']);
        Route::apiResource('rubrics', RubricController::class);

        // Final Exam Grading Studio (Phase 4b)
        Route::get('exam-grading', [InstructorExamGradingController::class, 'queue']);
        Route::get('exam-grading/{uuid}', [InstructorExamGradingController::class, 'show']);
        Route::post('exam-grading/{uuid}/grade', [InstructorExamGradingController::class, 'grade']);

        // One-on-One Tutoring Management (Phase 7)
        Route::get('tutoring/availability', [InstructorTutoringController::class, 'getAvailability']);
        Route::put('tutoring/availability', [InstructorTutoringController::class, 'setAvailability']);
        Route::get('tutoring/blocked-dates', [InstructorTutoringController::class, 'getBlockedDates']);
        Route::post('tutoring/blocked-dates', [InstructorTutoringController::class, 'addBlockedDate']);
        Route::delete('tutoring/blocked-dates/{id}', [InstructorTutoringController::class, 'deleteBlockedDate']);
        Route::get('tutoring/sessions', [InstructorTutoringController::class, 'sessions']);
        Route::put('tutoring/sessions/{uuid}/details', [InstructorTutoringController::class, 'updateSessionDetails']);
        Route::post('tutoring/sessions/{uuid}/cancel', [InstructorTutoringController::class, 'cancelSession']);
        Route::post('tutoring/sessions/{uuid}/attendance', [InstructorTutoringController::class, 'markAttendance']);
    });

    // Admin Endpoints
    Route::prefix('admin')->group(function () {
        // Tutoring Packages (Phase 7)
        Route::post('tutoring/packages', [TutoringPackageController::class, 'store'])->middleware('permission:tutoring.manage-packages');
        Route::put('tutoring/packages/{id}', [TutoringPackageController::class, 'update'])->middleware('permission:tutoring.manage-packages');
        Route::delete('tutoring/packages/{id}', [TutoringPackageController::class, 'destroy'])->middleware('permission:tutoring.manage-packages');

        // Certificates Management (Phase 4b)
        Route::middleware('permission:courses.manage-all')->group(function () {
            Route::get('certificates', [AdminCertificateController::class, 'index']);
            Route::post('certificates/{uuid}/revoke', [AdminCertificateController::class, 'revoke']);
            Route::post('certificates/{uuid}/reissue', [AdminCertificateController::class, 'reissue']);
        });

        // Courses
        Route::apiResource('courses', CourseController::class);

        // Users
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::put('users/{user}/roles', [UserController::class, 'updateRoles']);
        Route::put('users/{user}/status', [UserController::class, 'updateStatus']);

        // Roles & Permissions
        Route::get('roles', [RoleController::class, 'index']);

        // Settings
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);

        // Audit Trail
        Route::get('activity-log', [ActivityLogController::class, 'index']);
        Route::get('notification-logs', [NotificationLogController::class, 'index']);
        Route::post('test-sms', [NotificationLogController::class, 'testSms']);

        // Notification Templates (Phase 8)
        Route::middleware('permission:notifications.view')->group(function () {
            Route::get('notification-templates', [NotificationTemplateController::class, 'index']);
            Route::get('notification-templates/{key}', [NotificationTemplateController::class, 'show']);
            Route::put('notification-templates/{key}', [NotificationTemplateController::class, 'update'])->middleware('permission:notifications.manage');
            Route::post('notification-templates/{key}/test-send', [NotificationTemplateController::class, 'testSend'])->middleware('permission:notifications.manage');
        });

        // CMS Pages & Inquiries Management
        Route::get('cms/pages', [AdminCmsController::class, 'index']);
        Route::post('cms/pages', [AdminCmsController::class, 'store']);
        Route::get('cms/pages/{id}', [AdminCmsController::class, 'show']);
        Route::put('cms/pages/{id}', [AdminCmsController::class, 'update']);
        Route::delete('cms/pages/{id}', [AdminCmsController::class, 'destroy']);
        Route::get('cms/contact-inquiries', [AdminCmsController::class, 'inquiries']);
        Route::put('cms/contact-inquiries/{id}', [AdminCmsController::class, 'updateInquiry']);
    });

    // Multi-Role Dashboards & Reports (Phase 8)
    Route::prefix('dashboards')->group(function () {
        Route::get('admin', [DashboardController::class, 'admin'])->middleware('permission:dashboard.admin');
        Route::get('admin/export-csv', [DashboardController::class, 'adminCsv'])->middleware('permission:dashboard.admin');
        Route::get('finance', [DashboardController::class, 'finance'])->middleware('permission:dashboard.finance');
        Route::get('finance/export-csv', [DashboardController::class, 'financeCsv'])->middleware('permission:dashboard.finance');
        Route::get('instructor', [DashboardController::class, 'instructor'])->middleware('permission:dashboard.instructor');
        Route::get('coordinator', [DashboardController::class, 'coordinator'])->middleware('permission:dashboard.coordinator');
    });
});
