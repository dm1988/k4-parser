# Current Task:

## 🎯 Goal
### 1. Failed tests
Tests\Feature\AdminNavigationTest > non admin users can not see the admin navigat…
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

Error: Call to undefined method App\View\Models\Parser\ParserPageViewModel::fromCurrentSession() in /var/www/html/storage/framework/views/68e1cbe7cb02a05af7b5f0a462942f4b.php:20

-----

Call to undefined method App\View\Models\Parser\ParserPageViewModel::fromCurrentSession() (View: /var/www/html/resources/views/dashboard.blade.php)

  at tests/Feature/AdminNavigationTest.php:35
     31▕         $user = User::factory()->create();
     32▕
     33▕         $this->actingAs($user)
     34▕             ->get('/dashboard')
  ➜  35▕             ->assertOk()
     36▕             ->assertDontSeeText('Admin Panel')
     37▕             ->assertDontSee(route('filament.admin.pages.dashboard'), escape: false);
     38▕     }
     39▕

  ────────────────────────────────────────────────────────────────────────────────────────────
   FAILED  Tests\Feature\AdminNavigationTest > inactive or unverified admins can not see the…
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

Error: Call to undefined method App\View\Models\Parser\ParserPageViewModel::fromCurrentSession() in /var/www/html/storage/framework/views/68e1cbe7cb02a05af7b5f0a462942f4b.php:20
Stack trace:
### 2. Fix event export download ID assignment for all DTO types

[x] Keep download ID assignment in `app/Actions/BuildParserResult.php` rather than `ParserController`.
[x] Assign download IDs to the currently supported event payloads:
  - `Flight`
  - `DutyEvent`
  - array-backed event payloads
[x] Add `withDownloadId(string $downloadId): static` to the `ParsedEventDTO` contract so every future implementation must support ID assignment.
[x] Remove the `method_exists()` fallback from `BuildParserResult` after enforcing the DTO contract.
[x] Update `ParserResultViewModel` to preserve and render direct `DutyEvent` instances, not only normalized array payloads.
[x] Confirm per-event lookup supports both `ParsedEventDTO` objects and normalized array payloads.
[x] Add regression coverage proving a non-`Flight` DTO survives:
  - result assembly and download ID assignment
  - cache normalization and hydration
  - view-model URL generation
  - per-event calendar export

Verification: Laravel Pint passed. The focused DTO, cache, hydration, component, roster, and export suite passed 48 tests with 348 assertions.

### 3. Correct inconsistent `Aircraft` / `FlightEvent` relationship mapping

- Reconcile the mismatch between:
  - `app/Models/FlightEvent.php` using `aircraft_id`
  - `app/Models/Aircraft.php` using `tail_number` in `flightEvents()`
- Decide on the canonical relationship model:
  - foreign key by `aircraft_id`
  - duplicated display-only `tail_number`
  - or another clearly documented approach
- Update model relationships, factories, and Filament resources to use the same rule consistently.
- Review whether `tail_number` should remain denormalized on `flight_events` or be derived from the related aircraft when possible.
- Add tests proving `$flightEvent->aircraft` and `$aircraft->flightEvents` are true inverses.

### 4. Normalize Eloquent model conventions and typing

[x] Clean up `app/Models/Aircraft.php`, `app/Models/Airline.php`, and `app/Models/FlightEvent.php` to match current Laravel conventions.
[x] Add explicit return types for:
  - relationships
  - scopes
  - accessors where appropriate
[x] Replace untyped legacy properties/patterns with consistent modern equivalents where the codebase supports them.
[x] Normalize cast definitions and fillable/guarded strategy across models.
[x] Remove formatting/style drift in these model files so they match the rest of the app.

Verification: Laravel Pint passed. The focused model, Filament resource, policy, seeder, and parser regression suite passed 51 tests with 384 assertions.

### 5. Remove inline JavaScript and view-level composition drift

- Move the inline clipboard script out of `resources/views/flight-release/index.blade.php` into a proper frontend asset/module.
- Remove the third-party inline script injection from `resources/views/layouts/navigation.blade.php` and integrate it in a safer, more maintainable way.
- Review `resources/views/parse.blade.php` and `resources/views/dashboard.blade.php` to avoid building page state directly inside views when controllers/routes should own that responsibility.
- Decide whether `/dashboard` and `/parse` should remain separate entry points or be consolidated around one composition path.

### 6. Enable development guardrails for Eloquent performance issues

- Add `Model::preventLazyLoading()` in non-production environments in `app/Providers/AppServiceProvider.php`.
- Consider whether other local/dev guardrails should also be enabled for query visibility and accidental lazy loading detection.
- Run the affected test set after enabling this to identify hidden relationship-loading problems.

### 7. Improve OCR cache consistency and temporary file handling

- Review `app/Services/RosterSourceResolver.php` caching and temp file management.
- Replace `md5_file()` OCR cache key generation with the same stronger file identity strategy used elsewhere in the app unless there is a deliberate reason not to.
- Confirm temp image cleanup is safe under all failure paths.
- Review validation error keys for OCR/PDF failures to ensure they map cleanly back to the form fields the UI actually renders.

### 8. Use route middleware for auth
Move Authorization to Route Middleware
Your inline authorization blocks check explicit user capabilities and feature flags:

PHP
$this->authorizeScheduleParser($request);
Putting authorization directly inside controller methods prevents standard route caching optimizations and muddies the request mapping responsibility.

Fix: Wrap these rules into custom route middleware (e.g., EnsureFeatureIsEnabled, can:use-schedule-parser)

### 9. Fix airport details popover layering and mobile overflow behavior

- Fix the airport popover/card z-index issue on small screens.
- Ensure large airport metadata content does not render under surrounding UI.
- Verify the interaction works on:
  - mobile widths
  - tablet widths
  - desktop widths
- Confirm the popover remains accessible and readable when airport names or location strings are long.

### 10. Review migrations and schema consistency for `flight_events`

- Revisit `database/migrations/2026_06_22_002913_flight_event.php` for:
  - table naming consistency
  - foreign key target naming
  - index strategy
  - leftover commented scaffolding
- Confirm the schema accurately reflects the intended relationship with `aircraft`.
- Document any forward-fix migration needed rather than mutating an already-run migration if this has been used outside local development.

### 11. Use spatie icalendar-generator package
  - Install package with composer
  - Refactor export and affected services
  - Update tests

### 12. Add targeted regression coverage for the issues already found

- Add or update tests for:
  - parser request validation behavior
  - parser export behavior for all event DTO variants
  - airport lookup retry/timeout handling
  - flight route extractor cache behavior
  - `Aircraft` / `FlightEvent` relationship integrity
  - mobile/UI rendering edge cases for the flight release page where practical
- Prefer small, focused tests tied directly to each bug or refactor target instead of broad end-to-end additions.

### 13. Non-flight event schedule-format test

[x] Update `ParseUploadTest` to assert the UTC schedule format rendered by non-flight event cards: `Jun 13 • 1400 Z - 1600 Z`.
[x] Verify the targeted test passes with 6 assertions.

### 14. Failed test

Tests\Feature\AdminNavigationTest > inactive or unver…
  Expected response status code [200] but received 302.
Failed asserting that 302 is identical to 200.

  at tests/Feature/AdminNavigationTest.php:53
     49▕             ], $attributes))->save();
     50▕
     51▕             $this->actingAs($admin)
     52▕                 ->get('/dashboard')
  ➜  53▕                 ->assertOk()
     54▕                 ->assertDontSeeText('Admin Panel')
     55▕                 ->assertDontSee(route('filament.admin.pages.dashboard'), escape: false);
     56▕         }
