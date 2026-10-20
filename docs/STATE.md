# Bariton API State & Architecture

**Current Phase Completed**: Phase 4b (Comprehensive final exams, debounced autosave, server-enforced timer, performance recording grace periods, attempts, retake packages, and verifiable digital certificates with QR codes).  
**Last Updated**: October 2026

---

## 1. Environment & Architecture
- **Framework**: Laravel 13 (PHP 8.3+)
- **Database**: MySQL 8.x via WAMP (`bariton`, engine: `InnoDB`)
- **Authentication**:
  - Web SPA: Laravel Sanctum stateful cookie authentication + Fortify 2FA engine.
  - Mobile: Sanctum Personal Access Tokens.
- **Authorization**:
  - Spatie Laravel-Permission with strict permission checks.
  - Controller code never checks role names directly; all authorizations route through Policies and permission middleware (`permission:xyz`).
- **Audit Logging**: Spatie Activitylog tracking model mutations on `User`, `Course`, `Order`, `RefundRequest`, `CreditNote`, `Assignment`, `AssignmentSubmission`, `Exam`, `ExamAttempt`, and `Certificate`.
- **Exams & Server-Enforced Countdown (Phase 4b)**:
  - `ExamService`: Enforces timer server-side against `expires_at`, debounces intermediate auto-saves while student types, and triggers automatic submission if time expires.
  - Recorded Performance Pause: Freezes timer upon recital video/audio capture and grants a 15-minute grace period (`upload_grace_period_expires_at`) for uploading.
  - Auto-Grading & Manual Grading: Multiple-choice questions auto-grade immediately. Theory analysis and recorded performances route to `ExamGradingService` for manual rubric evaluation.
  - Cooling-off Period & Retake Packages: Enforces `exam_wait_time_hours` (default 24h) and `exam_attempt_limit` (default 3). After 3 failed attempts, unlocks only after purchasing an `exam_retake_package` via checkout.
- **Verifiable Digital Certificates & Public Portal (Phase 4b)**:
  - `CertificateService`: Issues official certificate after student completes all lessons, passes all assignments, and passes the final exam.
  - Frozen Holder Name: Stamped at issue time along with course title and unique alphanumeric code (`BMAS-CERT-XXXXXXXX`).
  - QR Code Generation: Built using `BaconQrCode` to generate clean SVGs pointing to `https://app.bmas.com/verify/{code}` (or local verification route).
  - Landscape PDF Generation: DomPDF template rendering certificate with dual gold/navy ornate border, signatures, QR code, and dynamic `REVOKED` or `REPLACED` security watermarks.
  - Public Verification API (`GET /api/v1/certificates/verify/{code}`): Rate limited (`throttle:30,1`) and strictly privacy-preserving (no user ID, email, or personal contact info).
  - Administrative Revocation & Reissue: Admins revoke with recorded audit reason or reissue after name correction (marking the old code as replaced).
- **Resumable Chunked Uploads & Assessment Architecture (Phase 4a)**:
  - `ChunkedUploadService`: Resumable binary chunked upload mechanism supporting up to 200MB video uploads, tracking completed chunks, resuming after network failure, and sequentially assembling files.
  - Reusable Rubrics: `Rubric`, `RubricCriterion`, and `RubricLevel` tables allowing instructors to construct reusable conservatory grading templates.
  - Grading & Pass/Fail: `AssignmentGradingService` calculates rubric percentage against `enrollment->assignment_pass_mark_percent` (default 70%), transitions status to `graded` (passed) or `resubmission_required` (failed), and tracks attempt numbers.
- **Payment & Receipt Architecture (Phase 3a & 3b)**:
  - `PaymentGatewayInterface` with driver-based `PaymentManager` resolving `mpesa`, `card` / `stripe`, `paypal`, and `cash`.
  - Stripe Payment Gateway: Charges in KES with 3D Secure 2.0 simulation, idempotent request headers (`Idempotency-Key`), webhook verification (`Stripe-Signature`), and card refund execution.
  - PayPal Payment Gateway: Charges international students in USD at the order's locked exchange rate, capture confirmation, and USD refunds.
  - Sequential Receipt Generation (`StandardPdfReceiptIssuer`): Uses `DB::table('receipt_counters')->where('key', 'main')->lockForUpdate()` inside database transactions to guarantee strictly gapless sequential receipt numbers (`BMAS-RCT-000001`, `BMAS-RCT-000002`, ...).
  - Sequential Credit Note Generation (`StandardPdfCreditNoteIssuer`): Row-locked counter (`key = 'credit_note'`, prefix: `BMAS-CRN-000001`) with formal statutory Credit Note PDF rendering.
  - Refund Lifecycle (`RefundService`):
    - Policy validation: 14-day purchase window (`refund_window_days`).
    - Completion blockers: Rejects refund request if student has earned a Certificate of Completion, or if module/course progress is >= 50%.
    - Access revocation: Revokes access only to the refunded module (`EnrollmentModule`), recalculates parent enrollment progress (`recalculateEnrollmentProgress`), and generates an official Credit Note.

---

## 2. Seeded Accounts & Roles
| Role | Demo Email | Initial Password | 2FA Required |
|---|---|---|---|
| `super_admin` | `admin@bmas.com` | `Secret123!` | Recommended |
| `coordinator` | `coordinator@bmas.com` | `Secret123!` | Recommended |
| `instructor` | `instructor@bmas.com` | `Secret123!` | No |
| `finance_staff` | `finance@bmas.com` | `Secret123!` | **Strictly Required** (423 lockout until TOTP confirmed) |
| `student` (Adult) | `student.adult@bmas.com` | `Secret123!` | No |
| `student` (Minor) | `student.minor@bmas.com` | `Secret123!` | No (pending guardian consent) |

---

## 3. Flagship Seeded Course Data
- **Course**: "Classical Piano Mastery" (`slug`: `classical-piano-mastery`)
  - Status: `published`, Instructor: `instructor@bmas.com`
  - Bundle Discount: `percent`, `15%`
  - **Level 1 (Beginner)**: KES 4,500.00 (`price_kes`: 450000)
    - Lesson 1 (YouTube Preview): Posture, Hand Position & Keyboard Geography (`dQw4w9WgXcQ`)
    - Lesson 2 (PDF Score): Treble and Bass Clef Decoded
    - Lesson 3 (Audio Ear-Training): Five-Finger Ear Training Exercises
  - **Level 2 (Intermediate)**: KES 6,500.00 (`price_kes`: 650000)
    - Lesson 1 (YouTube): Major Scales and Finger Crossings
  - **Level 3 (Advanced)**: KES 9,000.00 (`price_kes`: 900000)
    - Lesson 1 (YouTube): Bach Two-Part Invention No. 1
  - **Pricing & Discounts**:
    - Sum of individual modules: KES 20,000.00 ($153.85 USD at 130 rate)
    - 15% Bundle Discount savings: KES 3,000.00 ($23.08 USD)
    - Bundle Price: KES 17,000.00 ($130.77 USD)
  - **Demo Student Enrollment**:
    - `student.adult@bmas.com` owns Module 1 (Beginner, paid KES 4,500.00).
    - Calculated Upgrade Price: KES 17,000 - KES 4,500 = **KES 12,500.00** ($96.16 USD).

---

## 4. Endpoints Implemented (`/api/v1`)
- **Course Catalog & Public**:
  - `GET /api/v1/courses`: Paginated catalog with search and level filters.
  - `GET /api/v1/courses/{slug}`: Course detail with full syllabus, bundle pricing, savings, and personalized upgrade pricing.
  - `GET /api/v1/courses/{slug}/preview/{lessonUuid}`: Free preview lesson data.
- **Media Streaming**:
  - `GET /api/v1/media/lessons/{uuid}`: Signed streaming endpoint supporting HTTP 206 range requests for audio and downloads for PDF.
- **Orders & Payments (Phase 3a)**:
  - `POST /api/v1/orders`: Create an order snapshotting VAT rate, exchange rate, and setting expiry.
  - `GET /api/v1/orders/{uuid}`: Order details with items and payment status.
  - `POST /api/v1/orders/{uuid}/pay/mpesa`: Dispatch Safaricom M-Pesa STK push prompt.
  - `GET /api/v1/orders/{uuid}/status`: Poll order payment status (`paid`, `pending`, `expired`).
  - `POST /api/v1/payments/mpesa/callback/{token}`: Public webhook for Daraja STK Push callbacks with secret token.
- **Student Learning & Orders**:
  - `GET /api/v1/student/enrollments`: Active enrollments list.
  - `GET /api/v1/student/enrollments/{uuid}`: Curriculum tree with accessible/locked flags.
  - `GET /api/v1/student/lessons/{uuid}`: Specific lesson player data.
  - `POST /api/v1/student/lessons/{uuid}/progress`: Record completion.
  - `GET /api/v1/student/orders`: Student order history with receipt links.
  - `GET /api/v1/student/receipts/{uuid}/download`: Download sequential stamped PDF receipt.
- **Finance Staff Operations**:
  - `GET /api/v1/finance/orders`: Search pending orders by customer name, order number, or email.
  - `POST /api/v1/finance/cash-payment`: Record office cash payment, fulfill order, and issue sequential receipt.
  - `GET /api/v1/finance/payments`: Complete payment ledger across all gateways.
- **Instructor Course Management**:
  - `GET /api/v1/instructor/courses`: Assigned courses.
  - `GET /api/v1/instructor/courses/{course}`: Course syllabus.
  - `POST /api/v1/instructor/courses/{course}/modules`: Add module.
  - `PUT/DELETE /api/v1/instructor/modules/{module}`: Edit/delete module.
  - `POST /api/v1/instructor/modules/{module}/lessons`: Add lesson (YouTube ID, Audio/PDF upload).
  - `PUT/DELETE /api/v1/instructor/lessons/{lesson}`: Edit/delete lesson.
- **Admin Management**:
  - `apiResource('admin/courses', CourseController)`: Full CRUD for courses with dynamic discount validation.

---

## 5. Decisions & Rules Enforced
1. **Integer Minor Units**: All money amounts stored as integer minor units in KES (e.g. `500000` = KES 5,000.00).
2. **USD Exchange Conversion**: Stored rate from settings (`usd_exchange_rate = 130.00`). Formula: `ceil(kesMinor / exchangeRate)` in cents, rounded up to the next whole cent.
3. **VAT Rate Snapshotting**: Stored from settings (`vat_rate_percent = 16.00`). Price includes VAT: `vat_kes = round(price * (rate / (100 + rate)))`.
4. **Consecutive Receipts**: Enforced strictly via `DB::table('receipt_counters')->where('key', 'main')->lockForUpdate()` within transactions.
5. **Order Expiry**: Pending orders expire after `order_expiry_minutes` (default 30 mins) configured in system settings.
6. **Access Control**: Enrollment and modules remain strictly locked until order status transitions to `paid`.

---

## 6. Known Gaps / Next Phase (Phase 3b)
- International card payments via Stripe (test mode elements, webhooks, 3D Secure).
- Multi-currency currency switcher (KES/USD) and card checkout.
