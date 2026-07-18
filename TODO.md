# Current Task:

## 🎯 Goal

### 1. Remove inline JavaScript and view-level composition drift

- Move the inline clipboard script out of `resources/views/flight-release/index.blade.php` into a proper frontend asset/module.
- Remove the third-party inline script injection from `resources/views/layouts/navigation.blade.php` and integrate it in a safer, more maintainable way.
- Review `resources/views/parse.blade.php` and `resources/views/dashboard.blade.php` to avoid building page state directly inside views when controllers/routes should own that responsibility.
- Decide whether `/dashboard` and `/parse` should remain separate entry points or be consolidated around one composition path.

### 2. Enable development guardrails for Eloquent performance issues

- Add `Model::preventLazyLoading()` in non-production environments in `app/Providers/AppServiceProvider.php`.
- Consider whether other local/dev guardrails should also be enabled for query visibility and accidental lazy loading detection.
- Run the affected test set after enabling this to identify hidden relationship-loading problems.

### 3. Improve OCR cache consistency and temporary file handling

- Review `app/Services/RosterSourceResolver.php` caching and temp file management.
- Replace `md5_file()` OCR cache key generation with the same stronger file identity strategy used elsewhere in the app unless there is a deliberate reason not to.
- Confirm temp image cleanup is safe under all failure paths.
- Review validation error keys for OCR/PDF failures to ensure they map cleanly back to the form fields the UI actually renders.

### 4. Use route middleware for auth
Move Authorization to Route Middleware
Your inline authorization blocks check explicit user capabilities and feature flags:

PHP
$this->authorizeScheduleParser($request);
Putting authorization directly inside controller methods prevents standard route caching optimizations and muddies the request mapping responsibility.

Fix: Wrap these rules into custom route middleware (e.g., EnsureFeatureIsEnabled, can:use-schedule-parser)

### 5. Fix airport details popover layering and mobile overflow behavior

- Fix the airport popover/card z-index issue on small screens.
- Ensure large airport metadata content does not render under surrounding UI.
- Verify the interaction works on:
  - mobile widths
  - tablet widths
  - desktop widths
- Confirm the popover remains accessible and readable when airport names or location strings are long.

### 6. Review migrations and schema consistency for `flight_events`

- Revisit `database/migrations/2026_06_22_002913_flight_event.php` for:
  - table naming consistency
  - foreign key target naming
  - index strategy
  - leftover commented scaffolding
- Confirm the schema accurately reflects the intended relationship with `aircraft`.
- Document any forward-fix migration needed rather than mutating an already-run migration if this has been used outside local development.

### 7. Use spatie icalendar-generator package
  - Install package with composer
  - Refactor export and affected services
  - Update tests

### 8. Add targeted regression coverage for the issues already found

- Add or update tests for:
  - parser request validation behavior
  - parser export behavior for all event DTO variants
  - airport lookup retry/timeout handling
  - flight route extractor cache behavior
  - `Aircraft` / `FlightEvent` relationship integrity
  - mobile/UI rendering edge cases for the flight release page where practical
- Prefer small, focused tests tied directly to each bug or refactor target instead of broad end-to-end additions.
