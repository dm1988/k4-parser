# Current Task:

## 🎯 Goal

### 5. ✅ Use route middleware for auth
Move Authorization to Route Middleware
Your inline authorization blocks check explicit user capabilities and feature flags:

PHP
$this->authorizeScheduleParser($request);
Putting authorization directly inside controller methods prevents standard route caching optimizations and muddies the request mapping responsibility.

Fix: Wrap these rules into custom route middleware (e.g., EnsureFeatureIsEnabled, can:use-schedule-parser)

Completed: moved feature availability and user capability checks to route middleware using a parameterized feature middleware and Laravel gates, while preserving disabled-feature 404 and unauthorized-user 403 responses.

### 6. ✅ Fix airport details popover layering and mobile overflow behavior

- Fix the airport popover/card z-index issue on small screens.
- Ensure large airport metadata content does not render under surrounding UI.
- Verify the interaction works on:
  - mobile widths
  - tablet widths
  - desktop widths
- Confirm the popover remains accessible and readable when airport names or location strings are long.

Completed: allowed airport popovers to escape the flight card's clipping boundary, raised the active popover above sibling content, constrained panels to the mobile viewport, wrapped long metadata, and linked each trigger to its uniquely identified panel while preserving Escape-key focus behavior.

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
