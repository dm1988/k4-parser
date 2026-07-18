# Current Task:

## 🎯 Goal

### 8. ✅ Use `spatie/icalendar-generator` for RFC-compliant calendar exports

Recommendation: proceed. The current `IcsCalendarService` is a useful application adapter, but its hand-built serializer owns RFC 5545 escaping and does not fold long content lines at the 75-octet boundary. Moving serialization to a maintained package is worthwhile because calendar descriptions can contain long, Unicode-heavy crew and flight metadata.

Keep this refactor narrowly scoped: retain the existing application-facing service and domain formatting, and replace only the manual `VCALENDAR` / `VEVENT` generation. Do not move filtering, event lookup, filenames, HTTP headers, or duty-time calculations into the package integration.

#### 8.1 Capture the existing export contract before installing the package

- Expand `IcsCalendarServiceTest` with characterization coverage for:
  - multiple events in one calendar
  - array events and supported DTO variants
  - UTC conversion for offset-aware start and end values
  - deterministic event UIDs
  - calendar name and description derived from trip metadata
  - optional FlightAware URLs
  - commas, semicolons, backslashes, and embedded newlines
  - long Unicode descriptions that require standards-compliant line folding
  - unsupported event values being skipped consistently
- Keep endpoint tests for full-calendar, single-event, and duty-event downloads.
- Assert semantic calendar properties where property ordering may legitimately change; avoid requiring byte-for-byte output compatibility.

#### 8.2 Install and verify the dependency

- Confirm the selected release supports the project PHP version and required extensions.
- Install with Sail Composer, using an intentional compatible constraint such as:
  - `vendor/bin/sail composer require spatie/icalendar-generator:^3.3`
- Review `composer.json` and `composer.lock`, then run Composer's security audit.
- Do not add a Laravel service provider unless the package documentation explicitly requires one; this is a framework-independent generator.

#### 8.3 Refactor only the serialization boundary

- Keep `IcsCalendarService::serialize(array $events, array $trip = []): string` as the stable application API.
- Preserve the existing event normalization and crew/flight description formatting.
- Replace manual line assembly and `escapeValue()` usage with Spatie `Calendar` and `Event` components.
- Preserve these observable contracts:
  - UTC `DTSTART` and `DTEND` values
  - deterministic UID derived from title, start, and end, including the `@crew-compass` suffix
  - summary, formatted description, and optional FlightAware URL
  - Crew Compass product/calendar metadata
  - trip-specific calendar name
  - CRLF-compatible generated output
- Leave `ParserCalendarExportService`, `ExportFlightDutyCalendarEvent`, and `FlightDutyCalendarEventService` responsible for their current orchestration and domain behavior.
- Remove obsolete custom escaping/serialization helpers only after replacement coverage passes.

#### 8.4 Update regression and compatibility coverage

- Verify response content type and attachment filenames remain unchanged.
- Verify filtered exports, complete exports, individual events, and generated duty events.
- Account for valid package differences in property order, `PRODID`, `DTSTAMP`, escaping, and line folding.
- Validate at least one long, Unicode-heavy generated file with an independent iCalendar parser or validator.
- Perform an import smoke test in the calendar clients the application supports, ideally Apple Calendar, Google Calendar, and Outlook.

#### 8.5 Completion criteria

- All existing calendar behavior remains semantically equivalent.
- Long content lines are folded correctly without corrupting multibyte characters.
- Calendar files import successfully in supported clients.
- Focused calendar/export tests and the full test suite pass.
- Laravel Pint and Composer security audit pass.
- Document any intentional output differences discovered during compatibility testing.

Completed: replaced manual `VCALENDAR` / `VEVENT` serialization with `spatie/icalendar-generator` while retaining `IcsCalendarService` as the application adapter. Preserved UTC timestamps, deterministic Crew Compass UIDs, response headers, filenames, event filtering, duty calculations, calendar metadata, and trailing CRLF output. Added support for every `ParsedEventDTO` variant plus regression coverage for special-character escaping, long Unicode line folding, multiple event types, and existing download endpoints. Intentional output differences are standards-compliant line folding and the package's additional standard `NAME` / `DESCRIPTION` properties alongside their existing `X-WR-*` aliases. Automated tests, Pint, and Composer audit pass; manual Apple Calendar, Google Calendar, and Outlook imports remain a release smoke test.

### 9. ✅ Add targeted regression coverage for the issues already found

- Add or update tests for:
  - parser request validation behavior
  - parser export behavior for all event DTO variants
  - airport lookup retry/timeout handling
  - flight route extractor cache behavior
  - `Aircraft` / `FlightEvent` relationship integrity
  - mobile/UI rendering edge cases for the flight release page where practical
- Prefer small, focused tests tied directly to each bug or refactor target instead of broad end-to-end additions.

Completed: added focused coverage for missing and unsupported roster inputs, direct `Flight` DTO calendar serialization, PDF-text cache invalidation when file contents change at the same path, and mobile-first flight release rendering with long airport metadata. Confirmed the existing suites already cover bounded airport lookup retries/timeouts, all calendar DTO variants, cross-instance route cache reuse, and `Aircraft` / `FlightEvent` inverse and `SET NULL` relationship integrity. The responsive regression exposed and fixed missing word wrapping on flight release airport names and locations.

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

## 13. Streamline the Upload Card Structure
- The file upload input and the "Parse" button feel somewhat detached because they are aligned horizontally with wide gaps.

- Fix: Consider stacking the input elements or tightening the container width. Alternatively, transform the upload zone into a larger, centered drag-and-drop target box with an icon, placing a full-width or cleanly aligned "Parse" button directly beneath it.

## 14. Refine Card Hierarchy and Margins
 - The main blue header card ("Flight Deck") and the white upload card are stacked close together with identical widths, creating a rigid block appearance.

 - Fix: Nest the file upload and filters section inside a single, unified container card, where the dark blue header serves as the hero header of that card. This removes the double-card stacking look and groups the context ("what this tool does") directly with the action ("upload your file").

- Spacing: Increase the vertical spacing (gap or margin-bottom) between the hero header card and the upload card if you keep them separate.

## 15. Improve Grid/Flex Alignment
- Filters Section: The "Filters" label and the "Show options" dropdown toggle are pushed to the extreme edges of the container. If a user expands "Show options," the checkboxes will likely appear far away from the initial visual anchor. Aligning these elements or placing the filter options directly in a collapsible accordion that spans a more readable, centered width would feel more cohesive.

- Alignment: Ensure the text inside the "Choose File" button box vertically aligns perfectly with the text baseline of the "Parse" button.

## 16. Navbar Typography
- The navbar items ("Parse Schedule", "Route Extractor", etc.) are quite close to the top edge of the viewport. Adding a bit more top and bottom padding to the navbar container will give the text room to breathe and look cleaner.

## 17. Install laravel debug bar
