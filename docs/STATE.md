# Bariton API State & Architecture

**Current Phase Completed**: Phase 8 (Notification templates, multi-role dashboards & CSV export, health check, automated backups, security headers, deployment setup, mobile readiness, and OpenAPI specification).  
**Platform Status**: All 8 Phases Complete (100% test coverage with 58 Pest feature suites passing, 547 assertions).  
**Last Updated**: October 2026

---

## 1. Environment & Architecture
- **Framework**: Laravel 13 (PHP 8.3+)
- **Database**: MySQL 8.x via WAMP (`bariton`, engine: `InnoDB`)
- **Authentication**:
  - Web SPA: Laravel Sanctum stateful cookie authentication + Fortify 2FA engine (TOTP QR codes, recovery codes).
  - Mobile: Sanctum Personal Access Tokens (Bearer tokens) fully supported across all authenticated endpoints.
- **Authorization**:
  - Spatie Laravel-Permission with strict permission checks.
  - Controller code never checks role names directly; all authorizations route through Policies and permission middleware (`permission:xyz`).
- **Audit Logging**: Spatie Activitylog tracking model mutations on `User`, `Course`, `Order`, `RefundRequest`, `CreditNote`, `Assignment`, `AssignmentSubmission`, `Exam`, `ExamAttempt`, `Certificate`, `Event`, `EventBooking`, `TutoringPackage`, `TutoringSession`, and `NotificationTemplate`.

---

## 2. Phase-by-Phase Completed Systems

### Phase 1: Authentication, RBAC, Settings & Audit Logs
- Fortify 2FA TOTP enforcement for administrative and finance roles (`two_factor_required` middleware).
- Minors registration flow requiring guardian consent (`guardian/confirm`).
- GDPR Data Portability: Async background JSON export generation.
- Spatie Activitylog audit trails.
- System Settings table with dynamic overrides (VAT rate, exchange rates, upload limits).

### Phase 2: Course Catalog & Syllabus Architecture
- Multi-level courses (Beginner, Intermediate, Advanced) with video, audio, and PDF scores.
- Dynamic dual pricing (KES & USD rounded up to next cent).
- Bundle discount pricing and personalized upgrade pricing difference (`bundle_price - owned_modules_value`).
- Signed streaming URLs with HTTP 206 partial content support for audio and secure PDF downloads.

### Phase 3a & 3b: Multi-Gateway Payments, Sequential Receipts & Refunds
- Payment Gateway drivers for Safaricom M-Pesa STK push, Stripe Cards (KES), PayPal (USD), and counter cash.
- Strict gapless sequential receipt numbering using `lockForUpdate()` on `receipt_counters` (`BMAS-RCT-000001`).
- 14-day refund lifecycle rejecting refunds if course progress >= 50% or certificate earned.
- Statutory sequential Credit Notes (`BMAS-CRN-000001`) with PDF rendering and partial module access revocation.

### Phase 4a: Chunked Resumable Uploads & Reusable Rubrics
- Resumable binary chunked upload mechanism supporting up to 200MB video performances.
- Reusable multi-criterion grading rubrics with weighted scores and performance levels.
- Instructor grading studio with automated pass/fail calculations against pass marks.

### Phase 4b: Final Certification Exams & Verifiable Digital Certificates
- Server-enforced countdown timer with debounced autosaving and auto-submission on expiration.
- Performance recording pause with 15-minute grace period for video upload.
- Cooling-off period between failed attempts and paid retake packages.
- Tamper-proof digital certificates with unique codes, BaconQrCode SVG verification links, DomPDF landscape rendering, and dynamic revocation watermarks.

### Phase 5: Professional Skills Validation Path
- Assessment package checkout (`item_type: 'assessment_package'`) allowing direct skills examination.
- Instructional lessons hidden and blocked by `LessonPolicy`.
- Dedicated "Certificate of Skills Validation" with public verification badge noting direct assessment completion.
- Full course upgrade difference pricing (`bundle_price - validation_price`).

### Phase 6: Physical Bootcamps & Workshops
- Physical seat capacity tracking with training-only and training-plus-accommodation pricing.
- Concurrency locking with `lockForUpdate()` inside database transactions preventing overbooking.
- Online hold timers (15m in settings) and cash hold times (48h in settings) with automated seat release.
- Late payment unseated auto-refund flow if seat was taken during payment lag.
- Automated waitlist rotation offering freed seats to next student in line with fresh hold timer.
- Workshop coordinator check-in roster and SMS/email attendance reminders.

### Phase 7: One-on-One Tutoring
- Instructor weekly recurring availability shifts evaluated strictly in `Africa/Nairobi` (EAT) timezone.
- 60-minute teaching blocks with 15-minute inter-session buffers.
- Instructor blackout dates / time-off management.
- Multi-session package definitions (1, 5, 10 sessions) with remaining credit ledgers and daily automated expiry sweep.
- Concurrency locks on booked slots preventing double-booking.
- Rescheduling with 24-hour notice window or emergency waiver (e.g. illness).
- Automatic session credit restoration when instructor cancels.
- Attendance marking (`attended` / `missed`) with instructor feedback notes.

### Phase 8: Notification Templates, Dashboards, Reports & Deployment
- Database notification templates (`notification_templates`) with admin editor and variable token interpolation.
- 8 core message templates seeded: `registration`, `payment_received`, `receipt_issued`, `refund_decision`, `assignment_graded`, `certificate_awarded`, `event_reminder`, `session_reminder`.
- Student notification preferences protecting mandatory security & payment messages while allowing opt-outs for optional reminders.
- Multi-role dashboards with date range filters and CSV export:
  - Admin: Revenue by method & item type, enrollments, exam pass rates, event occupancy, tutoring utilization.
  - Finance: Gross revenue, cash reception entries, pending refunds, credit notes, daily collections summary.
  - Instructor: Assignment grading queue, exam reviews, upcoming 1-on-1 sessions, course roster.
  - Coordinator: Event occupancy meters, accommodation trackers, attendee check-in summaries.
- Production deployment setup:
  - `.env.production.example` for Hostpinnacle cPanel (`app.bmas.com` and `api.bmas.com`).
  - GitHub Actions CI/CD workflow (`.github/workflows/deploy.yml`).
  - Security headers middleware (`SecurityHeadersMiddleware`) enforcing HSTS, CSP, and X-Frame-Options.
  - Nightly database and storage backup command (`php artisan app:backup --verify`).
  - Health check monitoring endpoint (`GET /api/v1/health`).
  - OpenAPI 3.1 specification at `docs/openapi.yaml`.

---

## 3. Seeded Accounts & Roles
| Role | Demo Email | Initial Password | 2FA Required |
|---|---|---|---|
| `admin` | `admin@bmas.com` | `Secret123!` | Yes (TOTP confirmed) |
| `coordinator` | `coordinator@bmas.com` | `Secret123!` | No |
| `instructor` | `instructor@bmas.com` | `Secret123!` | No |
| `finance_manager` | `finance.manager@bmas.com` | `Secret123!` | Yes (TOTP confirmed) |
| `finance_staff` | `finance@bmas.com` | `Secret123!` | Yes (TOTP confirmed) |
| `student` | `student.adult@bmas.com` | `Secret123!` | No |

---

## 4. Test Suite Summary
- **Test Runner**: Pest 3.x / PHPUnit 11.x
- **Feature Suites**: 58 test files
- **Total Tests**: 58 passed (0 failed)
- **Total Assertions**: 547 assertions passed
- **Pint Formatting**: 100% clean PSR-12 / Laravel standards
