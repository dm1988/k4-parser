# Current Task:

## 🎯 Goal

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

### 10. ✅ In Filament, allow admins to delete a user

Completed: admins can delete other users from the user table or edit page after confirming a danger modal that identifies the account. Policy enforcement prevents admins from deleting themselves, while user creation, bulk deletion, restore, and force-delete actions remain disabled.

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
