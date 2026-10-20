# Bariton API State & Architecture

**Current Phase Completed**: Phase 2 (Courses, modules, levels, pricing and lessons).
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
- **Audit Logging**: Spatie Activitylog tracking model mutations on `User` and `Course`.
- **Media Streaming**:
  - `MediaStreamService` with support for HTTP 206 Partial Content range requests (`Accept-Ranges: bytes`) for smooth audio streaming and seeking.
  - Temporary signed routes via `URL::temporarySignedRoute()`.

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

## 3. Flagship Seeded Course Data (Phase 2)
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
- **Student Learning**:
  - `GET /api/v1/student/enrollments`: Active enrollments list.
  - `GET /api/v1/student/enrollments/{uuid}`: Curriculum tree with accessible/locked flags.
  - `GET /api/v1/student/lessons/{uuid}`: Specific lesson player data.
  - `POST /api/v1/student/lessons/{uuid}/progress`: Record completion (YouTube 90%, Audio 90%, PDF mark complete).
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
3. **Bundle Discount Rule**: Discounts that make the bundle price zero or equal/greater than the module sum are strictly rejected during course creation, update, and module price changes.
4. **Upgrade Pricing**: `max(0, bundle_price_kes - total_paid_for_owned_modules_kes)`.
5. **Progress Auto-Completion**:
   - YouTube: auto-completes when >= 90% watched.
   - Audio: auto-completes when >= 90% played.
   - PDF: student manual mark complete.
   - Course and module progress percentages dynamically recalculated and saved.

---

## 6. Known Gaps / Next Phase (Phase 3)
- Order checkout and payment gateways (M-Pesa STK Push, Cash entry, Stripe test mode, and sequential receipt issuing).
