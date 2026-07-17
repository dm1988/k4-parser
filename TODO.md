# Current Task: 

## 🎯 Goal

### 1. Refactor parser request handling and controller responsibilities

- Replace inline validation in `app/Http/Controllers/ParserController.php` with dedicated Form Request classes for:
  - `parseFlight`
  - `parseHotel`
  - `parseRoster`
- Move parsing, result assembly, and cache persistence out of `ParserController` into smaller action or service classes.
- Reduce controller method size so each action mainly coordinates:
  - authorization
  - validated input
  - service/action execution
  - redirect/response generation
- Revisit repeated try/catch/logging blocks in parser actions and centralize the shared parse lifecycle where possible.
- Add or update focused feature tests covering the new request classes and extracted services.

### 2. Harden outbound airport lookup HTTP behavior

- Update `app/Services/AirportLookupClient.php` to include:
  - `connectTimeout()`
  - explicit `retry()` behavior with bounded backoff
  - consistent handling for transient upstream failures
- Review whether 404, 422, 429, 500, and 503 responses should be handled differently.
- Confirm logging includes enough context for debugging without leaking unnecessary payload data.
- Add tests covering:
  - successful lookups
  - timeout / connection failures
  - 404 not found responses
  - temporary upstream failures that should retry

### 3. Fix `FlightRouteExtractor` dependency and caching behavior

- Remove the implicit fallback to `ArrayStore` in `app/Services/FlightRouteExtractor.php`.
- Ensure the extractor always uses Laravel-managed dependencies from the container instead of constructing fallback implementations directly.
- Stop instantiating `AirportLookupClient` with `new AirportLookupClient`; inject and resolve it through the container.
- Confirm PDF text caching is real cross-request caching, not object-lifetime-only caching.
- Verify the class remains easy to unit test after dependency cleanup.
- Add tests proving:
  - parsed PDF text is cached through the configured cache repository
  - airport lookup dependencies are injected rather than constructed internally

### 4. Fix event export download ID assignment for all DTO types

- Update `app/Http/Controllers/ParserController.php` so `attachDownloadIds()` assigns IDs to:
  - `Flight`
  - `DutyEvent`
  - any other `ParsedEventDTO` implementation
  - array-backed event payloads
- Confirm per-event export URLs work for both flight and duty-style events.
- Review `app/View/Models/Parser/ParserResultViewModel.php` and related DTOs to ensure all render paths preserve `downloadId` consistently.
- Add tests covering event export for non-`Flight` DTOs so this regression cannot reappear.

### 5. Correct inconsistent `Aircraft` / `FlightEvent` relationship mapping

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

### 6. Normalize Eloquent model conventions and typing

- Clean up `app/Models/Aircraft.php`, `app/Models/Airline.php`, and `app/Models/FlightEvent.php` to match current Laravel conventions.
- Add explicit return types for:
  - relationships
  - scopes
  - accessors where appropriate
- Replace untyped legacy properties/patterns with consistent modern equivalents where the codebase supports them.
- Normalize cast definitions and fillable/guarded strategy across models.
- Remove formatting/style drift in these model files so they match the rest of the app.

### 7. Remove inline JavaScript and view-level composition drift

- Move the inline clipboard script out of `resources/views/flight-release/index.blade.php` into a proper frontend asset/module.
- Remove the third-party inline script injection from `resources/views/layouts/navigation.blade.php` and integrate it in a safer, more maintainable way.
- Review `resources/views/parse.blade.php` and `resources/views/dashboard.blade.php` to avoid building page state directly inside views when controllers/routes should own that responsibility.
- Decide whether `/dashboard` and `/parse` should remain separate entry points or be consolidated around one composition path.

### 8. Enable development guardrails for Eloquent performance issues

- Add `Model::preventLazyLoading()` in non-production environments in `app/Providers/AppServiceProvider.php`.
- Consider whether other local/dev guardrails should also be enabled for query visibility and accidental lazy loading detection.
- Run the affected test set after enabling this to identify hidden relationship-loading problems.

### 9. Improve OCR cache consistency and temporary file handling

- Review `app/Services/RosterSourceResolver.php` caching and temp file management.
- Replace `md5_file()` OCR cache key generation with the same stronger file identity strategy used elsewhere in the app unless there is a deliberate reason not to.
- Confirm temp image cleanup is safe under all failure paths.
- Review validation error keys for OCR/PDF failures to ensure they map cleanly back to the form fields the UI actually renders.

### 10. Fix airport details popover layering and mobile overflow behavior

- Fix the airport popover/card z-index issue on small screens.
- Ensure large airport metadata content does not render under surrounding UI.
- Verify the interaction works on:
  - mobile widths
  - tablet widths
  - desktop widths
- Confirm the popover remains accessible and readable when airport names or location strings are long.

### 11. Review migrations and schema consistency for `flight_events`

- Revisit `database/migrations/2026_06_22_002913_flight_event.php` for:
  - table naming consistency
  - foreign key target naming
  - index strategy
  - leftover commented scaffolding
- Confirm the schema accurately reflects the intended relationship with `aircraft`.
- Document any forward-fix migration needed rather than mutating an already-run migration if this has been used outside local development.

### 12. Add targeted regression coverage for the issues already found

- Add or update tests for:
  - parser request validation behavior
  - parser export behavior for all event DTO variants
  - airport lookup retry/timeout handling
  - flight route extractor cache behavior
  - `Aircraft` / `FlightEvent` relationship integrity
  - mobile/UI rendering edge cases for the flight release page where practical
- Prefer small, focused tests tied directly to each bug or refactor target instead of broad end-to-end additions.

### 13. Failed test

Tests\Feature\ParseUploadTest > non flight event card header displays…    
  Expected: <div\n
      class="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-5 py-6  lg:grid-cols-2 ">\n
      <section>\n
  ... (207 more lines)

  To contain: Jun 13 • 2:00 PM - 4:00 PM

  at tests/Feature/ParseUploadTest.php:121
    117▕         $page = $this->get(route('parse.index'));
    118▕ 
    119▕         $page->assertOk()
    120▕             ->assertSee('Jun 13', false)
  ➜ 121▕             ->assertSee('Jun 13 • 2:00 PM - 4:00 PM', false);
    122▕     }
    123▕ 
    124▕     public function test_parse_failure_is_recorded_and_logged_without_input_contents(): void
    125▕     {