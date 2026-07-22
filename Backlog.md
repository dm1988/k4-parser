## 4. Streamline the Upload Card Structure
- The file upload input and the "Parse" button feel somewhat detached because they are aligned horizontally with wide gaps.

- Fix: Consider stacking the input elements or tightening the container width. 

- Alternatively, transform the upload zone into a larger, centered drag-and-drop target box with an icon, placing a full-width or cleanly aligned "Parse" button directly beneath it.

## 5. Refine Card Hierarchy and Margins
 - The main blue header card ("Flight Deck") and the white upload card are stacked close together with identical widths, creating a rigid block appearance.

 - Fix: Nest the file upload and filters section inside a single, unified container card, where the dark blue header serves as the hero header of that card. This removes the double-card stacking look and groups the context ("what this tool does") directly with the action ("upload your file").

- Spacing: Increase the vertical spacing (gap or margin-bottom) between the hero header card and the upload card if you keep them separate.

## 6. Improve Grid/Flex Alignment
- Filters Section: The "Filters" label and the "Show options" dropdown toggle are pushed to the extreme edges of the container. If a user expands "Show options," the checkboxes will likely appear far away from the initial visual anchor. Aligning these elements or placing the filter options directly in a collapsible accordion that spans a more readable, centered width would feel more cohesive.

- Alignment: Ensure the text inside the "Choose File" button box vertically aligns perfectly with the text baseline of the "Parse" button.

## 7. Navbar Typography
- The navbar items ("Parse Schedule", "Route Extractor", etc.) are quite close to the top edge of the viewport. Adding a bit more top and bottom padding to the navbar container will give the text room to breathe and look cleaner.

## 8. Install laravel debug bar

## Prep for rename
- Rename ParserEventType Enum to ScheduleEventType.php
- Add 2 folders in Enum: Schedule and 

## 9. Organize services - Complete 7/22/2026
 ### Only begin after livewire-schedule-parser branch is merged
- Create directory framework
  cd app/Services
  mkdir -p Services/{Schedule/ Extractor,FlightPlan/ Extractor,Calendar,Infrastructure}
- Move files:
  - Schedule Domain
  git mv Parsers/JcaScheduleParsingService.php Schedule/JcaScheduleProcessor.php
  git mv ScheduleInputResolver.php Schedule/ScheduleInputResolver.php
  git mv Extractors/SchedulePdfExtractor.php Schedule/PdfTextExtractor.php
  git mv Parsers/CrewListParser.php Schedule/ Extractor/CrewListParser.php
  git mv Parsers/PublishedRosterParser.php Schedule/Extractor/PublishedRosterParser.php
  git mv Parsers/ScheduleFormatParser.php Schedule/Extractor/ScheduleFormatParser.php
  git mv Parsers/TripInformationParser.php Schedule/Extractor/TripInformationParser.php

  - FlightPlan Domain
  git mv Extractors/FlightRouteExtractor.php FlightPlan/Extractor/FlightRouteExtractor.php
  - Note: Create your FlightReleaseProcessor.php entry point here if it's new

  - Calendar Domain
  git mv Calendar/IcsCalendarService.php Calendar/IcsGenerator.php
  git mv Calendar/FlightDutyCalendarEventService.php Calendar/FlightDutyEvent.php
  git mv Calendar/ParserCalendarExportService.php Calendar/ExportPayload.php

  - Clients (Keep folder, fix name)
  git mv Clients/AirlineCodeLookup.php Clients/AirlineCodeLookupClient.php

  - Infrastructure
  git mv Infrastructure/ParseRequestLogger.php Infrastructure/ScheduleRequestLogger.php
  git mv Infrastructure/ParserResultCache.php Infrastructure/EngineResultCache.php

- Clean up legacy directories
  - find Parsers Extractors -type f
  - rmdir Parsers Extractors
- Update namespaces and references
- Fix service-provider bindings and controllers
- Run a global search in your IDE for App\Services\. Update any locations where these classes were typed, imported (use), or bound in your AppServiceProvider.php.
- Flush cache
- Test

app/Services/
├── Schedule/
│   ├── JcaScheduleProcessor.php      # (Was JcaScheduleParsingService) Coordinates
│   ├── ScheduleInputResolver.php   # Deals directly with raw inputs/requests
│   └── Extractor/                    # Internal text sub-parsers used by the engine
│       ├── CrewListParser.php
│       ├── PdfTextExtractor.php   # (Was SchedulePdfExtractor) Low-level PDF tool
│       ├── PublishedRosterParser.php
│       ├── ScheduleFormatParser.php
│       └── TripInformationParser.php
│
├── FlightPlan/
│   ├── FlightReleaseProcessor.php      # Main entry point for the route engine
│   └──  Extractor/
│       └── FlightRouteExtractor.php       # text parser
│
├── Calendar/
│   ├── IcsGenerator.php  # (Was IcsCalendarService) Handles raw .ics payload syntax
│   ├── FlightDutyEvent.php   # Was FlightDutyCalendarEventService
│   └── ExportPayload.php        # (Was ParserCalendarExportService) Wraps data ready for client delivery
│
├── Clients/
│   ├── AirportLookupClient.php
│   └── AirlineCodeLookupClient.php     # Fixed name consistency (Added "Client")
│
└── Infrastructure/
    ├── ScheduleRequestLogger.php              # (Was ParseRequestLogger)
    └── EngineResultCache.php           # (Was ParserResultCache)

## 10. Use a descriptive footer disclaimer in Parse schedule tool:
- To safely clarify your tool's relationship to the platform, add a small, subtle line of text at the very bottom of your application page layout:

Disclaimer: This tool is an independent utility built for crew convenience and is not affiliated with, authorized, or endorsed by Jeppesen or Boeing.

## 11. One line status message
- In schedule extractor, shorten status codes
- System online: Ready to process
- 0.3 MB image selected, ready to upload

## 12. Resolve Larastan findings (5 errors remaining at level 5)

Run with `vendor/bin/sail bin phpstan analyse --no-progress`. Keep Larastan in `require-dev` and fix root causes rather than adding a baseline or blanket `ignoreErrors` entries.

### Low — safe cleanup after contract fixes

- [ ] Remove redundant `array_values()` calls on values already typed as lists in `app/DTOs/ParsedEventDTO.php:165`, `app/Mappers/DutyEventMapper.php:124`, `app/Mappers/FlightMapper.php:182`, and `app/Services/RosterParser.php:622` (4 errors).
- [ ] Remove `app/Services/RosterParser.php:751::firstMatchingLine()` if repository-wide usage confirms it is dead code (1 error).

After each cluster, run the focused PHPUnit tests and Larastan again. Finish with `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail bin phpstan analyse --no-progress`, and the relevant parser/auth/view-model test files.
