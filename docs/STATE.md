# Bariton API State & Architecture

**Current Phase Completed**: Phase 1 (Foundation, multi-role auth, security, baseline UI, settings & notifications).
**Last Updated**: October 2026

---

## 1. Environment & Architecture
- **Framework**: Laravel 13 (PHP 8.3+)
- **Database**: MySQL 8.x via WAMP (database: `bariton`, test database: `bariton_test`, engine: `InnoDB`)
- **Authentication**:
  - Web SPA: Laravel Sanctum stateful cookie authentication (`EnsureFrontendRequestsAreStateful`) + Laravel Fortify 2FA engine.
  - Mobile: Sanctum Personal Access Tokens (`/api/v1/auth/mobile/login`, `/api/v1/auth/mobile/tokens`, `/api/v1/auth/mobile/logout`).
- **Authorization**:
  - Spatie Laravel-Permission with strict permission-based checks.
  - Controller code never checks role names directly; all authorizations route through Policies and permission middleware (`permission:xyz`).
- **Audit Logging**: Spatie Activitylog tracking model events on `User` and sensitive administrative mutations.
- **SMS Gateway**: Africa's Talking API service (`AfricasTalkingSmsService` with fallback mock/sandbox logging).

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

## 3. Endpoints Implemented (`/api/v1`)
- **Authentication**:
  - `POST /api/v1/auth/register` (Adult / minor with guardian consent token creation)
  - `POST /api/v1/auth/login` (Account lockout after 5 attempts for 15 mins)
  - `POST /api/v1/auth/logout`
  - `POST /api/v1/auth/forgot-password` & `POST /api/v1/auth/reset-password`
  - `GET /api/v1/auth/verify-email/{id}/{hash}` & `POST /api/v1/auth/verification-notification`
  - `GET /api/v1/auth/guardian/status/{token}` & `POST /api/v1/auth/guardian/confirm/{token}`
  - `POST /api/v1/auth/mobile/login`, `GET /api/v1/auth/mobile/tokens`, `DELETE /api/v1/auth/mobile/tokens/{id}`
- **User Profile & Self-Service**:
  - `GET /api/v1/profile`
  - `PUT /api/v1/profile`
  - `PUT /api/v1/profile/password`
  - `POST /api/v1/profile/two-factor/enable` (Generates secret & SVG QR)
  - `POST /api/v1/profile/two-factor/confirm`
  - `DELETE /api/v1/profile/two-factor`
  - `POST /api/v1/profile/export` (GDPR data dump)
  - `DELETE /api/v1/profile` (Soft-delete / anonymization)
- **Admin Management**:
  - `GET/POST /api/v1/admin/users`, `GET/PUT/DELETE /api/v1/admin/users/{user}`
  - `GET /api/v1/admin/roles`
  - `GET/PUT /api/v1/admin/settings` (Cached forever, invalidated on update)
  - `GET /api/v1/admin/activity-logs`
  - `GET /api/v1/admin/notification-logs`
  - `POST /api/v1/admin/notifications/test-sms`
- **Public**:
  - `GET /api/v1/settings/public`

---

## 4. Key Security & Business Logic
1. **Minor Protection**:
   - Students under 18 calculate `is_minor = true` from date of birth.
   - Registration requires `guardian_name`, `guardian_email`, `guardian_phone`, and `guardian_relationship`.
   - Minor student status set to `pending_guardian` and email/SMS confirmation link dispatched to guardian.
   - Login blocked with 403 `guardian_consent_required` until guardian confirms.
2. **Finance Staff 2FA Enforcement**:
   - `EnsureTwoFactorEnabled` middleware blocks all protected endpoints with 423 `two_factor_setup_required` if user has `finance_staff` role or role requiring 2FA and `two_factor_confirmed_at` is null.
3. **Lockout Policy**:
   - 5 failed login attempts trigger a 15-minute lockout via Laravel's rate limiter.
