# GOR Smash Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a tested Laravel REST API for GOR Smash court rental operations.

**Architecture:** A Laravel API uses PostgreSQL migrations that mirror the supplied ERD. Controllers remain thin; booking, pricing, payment, and reporting services own transactional business logic. Sanctum authenticates internal roles while booking-code plus telephone-number verification protects customer actions.

**Tech Stack:** PHP 8.3+, Laravel, PostgreSQL 14+, Laravel Sanctum, PHPUnit/Pest, Laravel Scheduler.

**Spec:** `docs/superpowers/specs/2026-11-13-gor-smash-backend-design.md`

## Global Constraints

- Use Laravel and PostgreSQL 14+.
- Use Indonesian domain terms from the ERD and exact booking/payment statuses from the SRS.
- DP is exactly 50% of booking total; pending DP expires after 2 hours.
- Reject active booking overlap using `new_start < existing_end && new_end > existing_start`.
- Enforce at most one reschedule and allow it only more than 24 hours before play.
- Limit proof uploads to JPG/PNG and 5 MB.
- Record Admin actions and automated expiry actions in `log_aktivitas`.

## Review Focus

- Concurrent requests for an identical slot must leave at most one active booking; cover transactional collision check in Task 4.
- A booking that crosses 17:00 must total its hourly normal and evening rates correctly; cover this in Task 3.
- A rejected or expired DP must never be used to mark a booking paid; cover this in Task 5.
- A customer presenting another customer's booking code with the wrong phone number must receive no booking data; cover this in Task 6.
- An exact 24-hour boundary must reject rescheduling and not forfeit DP on cancellation; cover this in Task 5.

---

## File Structure

- `app/Models/` — Eloquent entities and relationships for the eight ERD tables.
- `app/Services/PricingService.php` — hourly tariff lookup and total calculation.
- `app/Services/BookingService.php` — booking creation, collision, cancellation, reschedule, and expiry operations.
- `app/Services/PaymentService.php` — proof upload, DP verification, and cash settlement.
- `app/Services/ReportService.php` — owner report and monitoring queries.
- `app/Http/Controllers/Api/` — request-to-service adapters only.
- `app/Http/Middleware/EnsureRole.php` — internal RBAC enforcement.
- `app/Console/Commands/ExpireBookings.php` — scheduled expiry entry point.
- `database/migrations/` — schema and indexes defined by the supplied ERD.
- `routes/api.php` — public, Admin, and Pemilik REST routes.
- `tests/Feature/` — API, authorization, and transaction behavior tests.
- `tests/Unit/` — pricing and standalone domain logic tests.

### Task 1: Bootstrap Laravel API and Domain Schema

**Files:**
- Create: Laravel application files, `.env.example`, `composer.json`
- Create: `database/migrations/*_create_pengguna_table.php`
- Create: `database/migrations/*_create_pelanggan_table.php`
- Create: `database/migrations/*_create_lapangan_table.php`
- Create: `database/migrations/*_create_tarif_table.php`
- Create: `database/migrations/*_create_booking_table.php`
- Create: `database/migrations/*_create_detail_booking_table.php`
- Create: `database/migrations/*_create_pembayaran_table.php`
- Create: `database/migrations/*_create_log_aktivitas_table.php`
- Create: `app/Models/{Pengguna,Pelanggan,Lapangan,Tarif,Booking,DetailBooking,Pembayaran,LogAktivitas}.php`
- Test: `tests/Feature/DatabaseSchemaTest.php`

**Interfaces:**
- Produces Eloquent models and PostgreSQL schema consumed by all later tasks.

- [ ] **Step 1: Write the failing schema test**

Assert each ERD table exists and that `booking.status_booking` defaults to `Menunggu DP`.

- [ ] **Step 2: Run the schema test to verify it fails**

Run: `php artisan test --filter=DatabaseSchemaTest`
Expected: FAIL because tables do not exist.

- [ ] **Step 3: Create the Laravel API application, install Sanctum, and implement ERD migrations and models**

Use PostgreSQL-native columns, foreign keys, unique constraints, check constraints where supported, and listed indexes. Map `pengguna` to Sanctum authentication.

- [ ] **Step 4: Run schema test and migrations**

Run: `php artisan migrate:fresh --env=testing && php artisan test --filter=DatabaseSchemaTest`
Expected: PASS.

### Task 2: Authentication, Roles, and Master Data

**Files:**
- Create: `app/Http/Controllers/Api/AuthController.php`
- Create: `app/Http/Middleware/EnsureRole.php`
- Create: `database/seeders/DatabaseSeeder.php`
- Modify: `bootstrap/app.php`, `routes/api.php`
- Test: `tests/Feature/AuthAndRoleTest.php`

**Interfaces:**
- Produces `POST /api/login`, `POST /api/logout`, and `role:Admin|Pemilik` middleware.
- Consumes `Pengguna` from Task 1.

- [ ] **Step 1: Write failing feature tests**

Test that valid Admin credentials issue a token, an unauthenticated internal request is rejected, and Admin cannot access an owner route.

- [ ] **Step 2: Run tests to verify failure**

Run: `php artisan test --filter=AuthAndRoleTest`
Expected: FAIL because routes and middleware do not exist.

- [ ] **Step 3: Implement login/logout, hashed passwords, Sanctum tokens, and role middleware**

Return JSON only; give forbidden role access HTTP 403.

- [ ] **Step 4: Run authorization tests**

Run: `php artisan test --filter=AuthAndRoleTest`
Expected: PASS.

### Task 3: Pricing and Schedule Availability

**Files:**
- Create: `app/Services/PricingService.php`
- Create: `app/Http/Controllers/Api/ScheduleController.php`
- Modify: `routes/api.php`
- Test: `tests/Unit/PricingServiceTest.php`
- Test: `tests/Feature/ScheduleTest.php`

**Interfaces:**
- Produces `PricingService::calculate(CarbonInterface $date, string $start, string $end): array` returning hourly tariff breakdown and `total_biaya`.
- Produces `GET /api/jadwal?tanggal=YYYY-MM-DD`.
- Consumes `Tarif`, `Lapangan`, `Booking`, and `DetailBooking`.

- [ ] **Step 1: Write failing pricing tests**

Assert weekday 16:00–18:00 computes Rp150.000 (Rp60.000 + Rp90.000), Saturday hours use Rp90.000, and a missing tariff is rejected.

- [ ] **Step 2: Run pricing tests to verify failure**

Run: `php artisan test --filter=PricingServiceTest`
Expected: FAIL because `PricingService` does not exist.

- [ ] **Step 3: Implement hourly tariff calculation**

Resolve tariffs by applicable day and each one-hour interval, returning the tariff IDs and snapshot values needed for detail records.

- [ ] **Step 4: Run pricing tests to verify pass**

Run: `php artisan test --filter=PricingServiceTest`
Expected: PASS.

- [ ] **Step 5: Write failing schedule test and then implement schedule endpoint**

Test that active bookings appear occupied and cancelled bookings do not. Run the test before and after implementation.

### Task 4: Booking Creation and Collision Prevention

**Files:**
- Create: `app/Services/BookingService.php`
- Create: `app/Http/Controllers/Api/BookingController.php`
- Create: `app/Http/Requests/StoreBookingRequest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/BookingTest.php`

**Interfaces:**
- Produces `BookingService::create(array $data, ?int $adminId): Booking`.
- Produces `POST /api/booking/ketersediaan`, `POST /api/booking`, and `POST /api/admin/booking`.
- Consumes `PricingService::calculate` from Task 3.

- [ ] **Step 1: Write failing booking feature tests**

Test successful public booking creates `Menunggu DP`, two-hour deadline, 50% DP response, and audit record for admin booking. Test overlapping active booking returns HTTP 422 while adjacent 10:00–11:00 and 11:00–12:00 bookings succeed.

- [ ] **Step 2: Run booking tests to verify failure**

Run: `php artisan test --filter=BookingTest`
Expected: FAIL because booking routes and service do not exist.

- [ ] **Step 3: Implement transactional booking creation and availability check**

Upsert customers by `no_hp`; lock the relevant court rows; execute the overlap query inside the transaction; create header and per-hour detail rows with tariff snapshots; generate a unique `BK-YYYYMMDD-NNN` code.

- [ ] **Step 4: Run booking tests to verify pass**

Run: `php artisan test --filter=BookingTest`
Expected: PASS.

### Task 5: Payments and Booking Lifecycle

**Files:**
- Create: `app/Services/PaymentService.php`
- Create: `app/Http/Controllers/Api/PaymentController.php`
- Modify: `app/Services/BookingService.php`, `routes/api.php`
- Create: `app/Console/Commands/ExpireBookings.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/PaymentAndLifecycleTest.php`

**Interfaces:**
- Produces proof upload, DP verification, cash settlement, cancel, reschedule, and `bookings:expire` command.
- Consumes `BookingService::create`, `Booking`, `Pembayaran`, and `LogAktivitas`.

- [ ] **Step 1: Write failing lifecycle tests**

Test valid JPG proof creates pending DP at exactly 50%; verified DP changes booking status; settlement rejects incorrect remaining amount and accepts exact cash; expiry cancels old pending booking and logs it; cancellation at 24 hours does not forfeit DP; reschedule at 24 hours and a second reschedule both return HTTP 422.

- [ ] **Step 2: Run lifecycle tests to verify failure**

Run: `php artisan test --filter=PaymentAndLifecycleTest`
Expected: FAIL because endpoints and command do not exist.

- [ ] **Step 3: Implement payment and lifecycle operations**

Wrap status transitions and audit logs in database transactions. Schedule `bookings:expire` every minute. Use local public storage for development proof URLs.

- [ ] **Step 4: Run lifecycle tests to verify pass**

Run: `php artisan test --filter=PaymentAndLifecycleTest`
Expected: PASS.

### Task 6: Customer Access, Admin History, and Owner Reports

**Files:**
- Create: `app/Services/ReportService.php`
- Create: `app/Http/Controllers/Api/CustomerController.php`
- Create: `app/Http/Controllers/Api/OwnerReportController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ReportingAndCustomerAccessTest.php`

**Interfaces:**
- Produces customer booking lookup, customer history, reports, quiet-hour analysis, booking monitoring, and audit-log endpoints.
- Consumes domain models created in Tasks 1–5.

- [ ] **Step 1: Write failing reporting and customer-protection tests**

Test wrong phone number returns 404 for a booking code; Admin history summarizes frequency, payment total, and cancellations; Pemilik receives only verified-payment revenue, monitoring categories, empty-slot data, and admin audit entries.

- [ ] **Step 2: Run tests to verify failure**

Run: `php artisan test --filter=ReportingAndCustomerAccessTest`
Expected: FAIL because reporting routes do not exist.

- [ ] **Step 3: Implement customer and report endpoints**

Validate date range query values, enforce roles, and return zero-valued summaries when no data exists.

- [ ] **Step 4: Run tests to verify pass**

Run: `php artisan test --filter=ReportingAndCustomerAccessTest`
Expected: PASS.

### Task 7: API Documentation and Final Verification

**Files:**
- Create: `README.md`
- Create: `.env.example`
- Modify: `routes/api.php`
- Test: all tests

**Interfaces:**
- Produces setup instructions, endpoint reference, development scheduler command, and example roles.

- [ ] **Step 1: Write a failing route-contract test for documented health endpoint**

Test `GET /api/health` returns `{ "status": "ok" }`.

- [ ] **Step 2: Run test to verify failure**

Run: `php artisan test --filter=HealthTest`
Expected: FAIL because the endpoint does not exist.

- [ ] **Step 3: Implement the health endpoint and write setup/API documentation**

Include PostgreSQL setup, migrations, scheduler, test command, auth endpoints, and all delivered API groups.

- [ ] **Step 4: Run the full verification suite**

Run: `php artisan test`
Expected: PASS with zero failures.

## Self Review

- SRS requirements REQ-01 through REQ-16 are covered respectively by Tasks 3, 4, 4, 4, 3, 4, 5, 5, 5, 5, 5, 6, 6, 6, 6, and 6.
- The ERD tables and architecture layers are covered by Tasks 1–7; Redis, S3/R2, and edge infrastructure remain explicitly out of scope for this development implementation.
- Cross-period tariffs, exact time boundaries, data isolation, overlap, expiry, and invalid payment status each have an owning test task.
- Interface names and ownership match the file structure and later task dependencies.

