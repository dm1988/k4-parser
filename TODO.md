# Current Task:

## 🎯 Goal

### 1. ✅ Mimecast strips email verification links
- New users with kalittaair.com domains cannot verify emails due to mimecast protections
- Fallback to OTP Codes: Always include a plain text, 6-digit One-Time Password (OTP) in the email alongside the verification link.
- 15 minute OTP timeout
- Rebuild front end auth verify pages to allow OTP
- Updated frontend verification page should default to showing the "Enter 6-digit code" input fields directly underneath the "Waiting for verification..." message, so users don't have to hunt for a separate page.
- Apply Laravel's built-in ThrottleRequests middleware to your OTP verification route. Limit users to 3 to 5 attempts per window before locking them out or destroying the token.

PHP
// routes/web.php
Route::post('/email/verify-otp', [VerifyEmailController::class, 'verifyOtp'])
    ->middleware(['auth', 'throttle:5,1']); // 5 attempts per minute
- Treat OTPs like passwords. Hash them using bcrypt() or Hash::make() before saving them to your users or a verification_otps table, and store the expiration timestamp alongside it.

PHP
// When generating:
$otp = random_int(100000, 999999);
$user->update([
    'otp_hash' => Hash::make($otp),
    'otp_expires_at' => now()->addMinutes(15),
]);
- Single-Use Invalidation (Atomic Verification)
The moment an OTP is successfully verified—or if a new one is requested—the old OTP must be immediately wiped out (null) to prevent replay attacks.
- Suggested format:
Verify Your Account
Click the link below to verify your email address:
[ Verify Email Address Button ]

Using an enterprise email network?
If the button above says "expired" or doesn't work, enter this 6-digit code on the verification page instead:
149 822 (Expires in 15 minutes)

- Ensure testing coverage

Completed: added keyed SHA-256 hashed, single-use 6-digit OTPs with a 15-minute expiry, OTP delivery alongside the signed verification link, an inline verification form, route throttling, and regression coverage.

### 2. Remove inline JavaScript and view-level composition drift

- Move the inline clipboard script out of `resources/views/flight-release/index.blade.php` into a proper frontend asset/module.
- Remove the third-party inline script injection from `resources/views/layouts/navigation.blade.php` and integrate it in a safer, more maintainable way.
- Review `resources/views/parse.blade.php` and `resources/views/dashboard.blade.php` to avoid building page state directly inside views when controllers/routes should own that responsibility.

### 3. Enable development guardrails for Eloquent performance issues

- Add `Model::preventLazyLoading()` in non-production environments in `app/Providers/AppServiceProvider.php`.
- Consider whether other local/dev guardrails should also be enabled for query visibility and accidental lazy loading detection.
- Run the affected test set after enabling this to identify hidden relationship-loading problems.

### 4. Improve OCR cache consistency and temporary file handling

- Review `app/Services/RosterSourceResolver.php` caching and temp file management.
- Replace `md5_file()` OCR cache key generation with the same stronger file identity strategy used elsewhere in the app unless there is a deliberate reason not to.
- Confirm temp image cleanup is safe under all failure paths.
- Review validation error keys for OCR/PDF failures to ensure they map cleanly back to the form fields the UI actually renders.

### 5. Use route middleware for auth
Move Authorization to Route Middleware
Your inline authorization blocks check explicit user capabilities and feature flags:

PHP
$this->authorizeScheduleParser($request);
Putting authorization directly inside controller methods prevents standard route caching optimizations and muddies the request mapping responsibility.

Fix: Wrap these rules into custom route middleware (e.g., EnsureFeatureIsEnabled, can:use-schedule-parser)

### 6. Fix airport details popover layering and mobile overflow behavior

- Fix the airport popover/card z-index issue on small screens.
- Ensure large airport metadata content does not render under surrounding UI.
- Verify the interaction works on:
  - mobile widths
  - tablet widths
  - desktop widths
- Confirm the popover remains accessible and readable when airport names or location strings are long.

### 7. Review migrations and schema consistency for `flight_events`

- Revisit `database/migrations/2026_06_22_002913_flight_event.php` for:
  - table naming consistency
  - foreign key target naming
  - index strategy
  - leftover commented scaffolding
- Confirm the schema accurately reflects the intended relationship with `aircraft`.
- Document any forward-fix migration needed rather than mutating an already-run migration if this has been used outside local development.

### 8. Use spatie icalendar-generator package
  - Install package with composer
  - Refactor export and affected services
  - Update tests

### 9. Add targeted regression coverage for the issues already found

- Add or update tests for:
  - parser request validation behavior
  - parser export behavior for all event DTO variants
  - airport lookup retry/timeout handling
  - flight route extractor cache behavior
  - `Aircraft` / `FlightEvent` relationship integrity
  - mobile/UI rendering edge cases for the flight release page where practical
- Prefer small, focused tests tied directly to each bug or refactor target instead of broad end-to-end additions.

### 10. In filement, allow admins to delete a user
- have a modal to confirm the action

### 11. Improve verify email markdown
- Use markdown in VerifyEmailWithOtp.php
public function toMail(mixed $notifiable): MailMessage
{
    $verificationUrl = $this->verificationUrl($notifiable);
    // Format OTP as "123 - 456" for even cleaner visual separation
    $formattedOtp = substr($this->otp, 0, 3) . ' - ' . substr($this->otp, 3);

    return (new MailMessage)
        ->subject('Verify Your Account')
        ->greeting('Verify your email address')
        ->line('Please click the button below to complete your account setup:')
        ->action('Verify Email Address', $verificationUrl)
        ->line('---') // Visual separator line
        ->line('**Alternative Verification Code**')
        ->line('If you are on an enterprise network where links are blocked, or if the button above has expired, enter this code on the verification page:')
        ->line('**' . $formattedOtp . '**') // Bolded OTP
        ->line('*This code expires in 15 minutes.*') // Italicized secondary context
        ->line('---')
        ->line('If you did not create an account, you can safely ignore this email.');
}
- Update tests

### 12. Clarify JCA schedule parsing service names

- Rename services whose current names hide their role in the four supported input paths (parsed text, screenshot OCR, Trip Information PDF, and Published Roster PDF):
  - `RosterParser` to `TripInformationParser` because it parses Trip Information-formatted text, including pasted text and screenshot OCR output, rather than every roster format.
  - `RosterSourceResolver` to `ScheduleInputResolver` because it normalizes all four schedule inputs and identifies PDF document formats.
  - `RosterDocumentParser` to `ScheduleFormatParser` because it dispatches normalized schedule text to the parser for the detected document format.
  - `ScheduleParserService` to `JcaScheduleParsingService` because it is the top-level JCA parsing workflow rather than a generic schedule parser.
  - `CrewParserService` to `CrewListParser` to describe the crew-row parsing and summary responsibility without the generic `Service` suffix.
- Refactor `PdfScheduleParser` before renaming it: separate generic PDF text/metadata extraction from its legacy Trip Information-specific parsing, then name the extraction service `SchedulePdfExtractor`.
- Keep `PublishedRosterParser`, `AirlineCodeLookup`, and `AirportLookupClient`; their names already describe their responsibilities accurately.
- Update dependency injection, service-provider bindings, command/controller call sites, tests, and filenames for each rename.
- Run the focused parser and upload test suites after the rename.
