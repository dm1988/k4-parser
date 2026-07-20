# Current Task:

## 🎯 Goal

## 1. ✅ Bug: deadhead misclassification
- Resolved: flight events now use the assignment position beside the route (`| AFO`, `| FO`, or `| DH`) instead of treating another crew member's `DH` role as the event's status. Inline and legacy multiline position layouts are covered by regression tests.

- Deadhead status should be resolved from Pos, not just search for any DH reference in roster text. From sample text: "position": "FO". If it were a deadhead assignment the position would be "DH".

- Sample:
{
    "type": "roster",
    "source": "image",
    "document_type": null,
    "file": null,
    "mime": "image/jpeg",
    "parsed": {
        "trip": {
            "trip_number": null,
            "position": "FO",
            "base": "LAX",
            "layovers": [],
            "block_time": null,
            "roster_range": null
        },
        "calendar_events": [
            {
                "type": "deadhead",
                "title": "CKS 255 JFK-CVG",
                "start": "2026-07-23T19:35:00+00:00",
                "end": "2026-07-23T22:05:00+00:00",
                "timezone": "UTC",
                "metadata": {
                    "flight_number": "CKS 255",
                    "origin": "JFK",
                    "destination": "CVG",
                    "position": "CP",
                    "aircraft": "77X",
                    "tail_number": "N794CK",
                    "flightaware_url": "https://www.flightaware.com/live/flight/N794CK",
                    "block_time": "2:30h",
                    "crew_count": 5,
                    "operating_crew_count": 4,
                    "deadheading_crew_count": 1,
                    "crew": [
                        {
                            "name": "Adam Spencer",
                            "employee_id": "70853",
                            "crew_id": "70853",
                            "base": "TYS",
                            "role": "CP",
                            "deadheading": false
                        },
                        {
                            "name": "Cameron Stovold",
                            "employee_id": "71835",
                            "crew_id": "71835",
                            "base": "LAX",
                            "role": "FO",
                            "deadheading": false
                        },
                        {
                            "name": "Ww Anthony Sabanski",
                            "employee_id": "73511",
                            "crew_id": "73511",
                            "base": "JAX",
                            "role": "DH",
                            "deadheading": true
                        },
                        {
                            "name": "Ww Tiyal Bell",
                            "employee_id": "4325",
                            "crew_id": "4325",
                            "base": "CLD",
                            "role": null,
                            "deadheading": false
                        },
                        {
                            "name": "David Gonzalez",
                            "employee_id": "72860",
                            "crew_id": "72860",
                            "base": null,
                            "role": "AFO",
                            "deadheading": false
                        }
                    ],
                    "deadhead": true,
                    "raw_lines": [
                        "@ K4 255 Pos AC Block Nn",
                        "JFK - CVG | AFO 77X 2:30h",
                        "Tail id N794CK Leg LT",
                        "Jul 23 15:35 - Jul 23 18:05",
                        "Duty LT Jul 23 10:10 - Jul 23 18:35 Customer DHL 777 NET",
                        "Catering Ordered",
                        "Crew list",
                        "Name Crew Pos Base",
                        "x Adam Spencer 70853 cP TYS",
                        "x Cameron Stovold 71835 FO LAX",
                        "Ww Anthony Sabanski 73511 DH JAX",
                        "Ww Tiyal Bell 4325 OB CLD",
                        "* David Gonzalez 72860 AFO NUS"
                    ],
                    "duty_raw_lines": [
                        "Duty LT Jul 23 10:10 - Jul 23 18:35 Customer DHL 777 NET",
                        "Catering Ordered",
                        "Crew list",
                        "Name Crew Pos Base",
                        "x Adam Spencer 70853 cP TYS",
                        "x Cameron Stovold 71835 FO LAX",
                        "Ww Anthony Sabanski 73511 DH JAX",
                        "Ww Tiyal Bell 4325 OB CLD",
                        "* David Gonzalez 72860 AFO NUS"
                    ],
                    "leg_local_start": "Jul 23 15:35",
                    "leg_local_end": "Jul 23 18:05",
                    "duty_local_start": "Jul 23 10:10",
                    "duty_local_end": "Jul 23 18:35"
                },
                "download_id": "01KXYA6JVBK5ZTWA7BP9M1APXB"
            },
            {
                "type": "duty",
                "title": "Duty CVG",
                "start": "2026-07-23T22:05:00+00:00",
                "end": "2026-07-23T22:35:00+00:00",
                "timezone": "UTC",
                "metadata": {
                    "station": "CVG",
                    "raw_lines": [
                        "\u00a9 CVG",
                        "0:30"
                    ]
                },
                "download_id": "01KXYA6JVBK5ZTWA7BP9M1APXC"
            },
            {
                "type": "duty",
                "title": "Duty CVG",
                "start": "2026-07-23T22:35:00+00:00",
                "end": "2026-07-26T22:05:00+00:00",
                "timezone": "UTC",
                "metadata": {
                    "station": "CVG",
                    "raw_lines": [
                        "ry CVG",
                        "71:30h"
                    ]
                },
                "download_id": "01KXYA6JVBK5ZTWA7BP9M1APXD"
            }
        ]
    },
    "filters": [],
    "meta": [],
    "parse_key": "01KXYA6JVBK5ZTWA7BP9M1APXE"
}
- The only deadheader in the sample below is :
"name": "Ww Anthony Sabanski",
  "employee_id": "73511",
  "crew_id": "73511",
  "base": "JAX",
  "role": "DH",
  "deadheading": true

## 2. Parser - change "parser" verbage to "extract"
- "Extract" tells the user exactly what the tool does for them (pulling out their data).

- Reword tool description: Upload a roster screenshot or trip PDF. The JCA Extractor will instantly convert it into calendar-ready events.

- Navigation bar: Extract Schedule

## 3. Action oriented tool names
- Focuses on what the user does. It keeps the navigation clean and intentional.
{{ __('Extract Schedule') }}
{{ __('Extract Flight Plan') }}
- Update navigation bar
- Review titles within each tool

## 4. Refactor to 2 views
- First view is for uploading
- 2nd is for results
- Have a primary button "Extract another roster". Leads to first view

## 5. Spell out Jeppesen Crew Access
- Should use JCA acryonm only after spelling out Jeppesen Crew Access
- Reference:
<header className="mb-10 text-center">
  <span className="text-xs font-bold tracking-widest uppercase text-[#C5A059] block mb-2">
    Jeppesen Crew Access
  </span>
  <h1 className="text-4xl md:text-5xl font-black tracking-tight text-[#1B365D] mb-4">
    Schedule Extractor
  </h1>
  <p className="text-base text-[#4A5568] max-w-md mx-auto leading-relaxed">
    Upload a roster screenshot or trip PDF to instantly convert your schedule into calendar-ready events.
  </p>
</header>

## 6. Streamline the Upload Card Structure
- The file upload input and the "Parse" button feel somewhat detached because they are aligned horizontally with wide gaps.

- Fix: Consider stacking the input elements or tightening the container width. 

- Alternatively, transform the upload zone into a larger, centered drag-and-drop target box with an icon, placing a full-width or cleanly aligned "Parse" button directly beneath it.

## 7. Refine Card Hierarchy and Margins
 - The main blue header card ("Flight Deck") and the white upload card are stacked close together with identical widths, creating a rigid block appearance.

 - Fix: Nest the file upload and filters section inside a single, unified container card, where the dark blue header serves as the hero header of that card. This removes the double-card stacking look and groups the context ("what this tool does") directly with the action ("upload your file").

- Spacing: Increase the vertical spacing (gap or margin-bottom) between the hero header card and the upload card if you keep them separate.

## 8. Improve Grid/Flex Alignment
- Filters Section: The "Filters" label and the "Show options" dropdown toggle are pushed to the extreme edges of the container. If a user expands "Show options," the checkboxes will likely appear far away from the initial visual anchor. Aligning these elements or placing the filter options directly in a collapsible accordion that spans a more readable, centered width would feel more cohesive.

- Alignment: Ensure the text inside the "Choose File" button box vertically aligns perfectly with the text baseline of the "Parse" button.

## 9. Navbar Typography
- The navbar items ("Parse Schedule", "Route Extractor", etc.) are quite close to the top edge of the viewport. Adding a bit more top and bottom padding to the navbar container will give the text room to breathe and look cleaner.

## 10. Install laravel debug bar

## 11. Organize services
- Create directory framework
mkdir -p Services/{Roster/Parsers,FlightPlan/Parsers,Calendar,Infrastructure}

- Move files:
    - Roster Domain
    git mv Parsers/JcaScheduleParsingService.php Roster/JcaRosterProcessor.php
    git mv ScheduleInputResolver.php Roster/ScheduleInputResolver.php
    git mv Extractors/SchedulePdfExtractor.php Roster/PdfTextExtractor.php
    git mv Parsers/CrewListParser.php Roster/Parsers/CrewListParser.php
    git mv Parsers/PublishedRosterParser.php Roster/Parsers/PublishedRosterParser.php
    git mv Parsers/ScheduleFormatParser.php Roster/Parsers/ScheduleFormatParser.php
    git mv Parsers/TripInformationParser.php Roster/Parsers/TripInformationParser.php

    - FlightPlan Domain
    git mv Extractors/FlightRouteExtractor.php FlightPlan/Parsers/FlightRouteParser.php
    - Note: Create your FlightReleaseProcessor.php entry point here if it's new

    - Calendar Domain
    git mv Calendar/IcsCalendarService.php Calendar/IcsGenerator.php
    git mv Calendar/FlightDutyCalendarEventService.php Calendar/RosterCalendarMapper.php
    git mv Calendar/ParserCalendarExportService.php Calendar/ExportPayloadService.php

    - Clients (Keep folder, fix name)
    git mv Clients/AirlineCodeLookup.php Clients/AirlineCodeLookupClient.php

    - Infrastructure
    git mv Infrastructure/ParseRequestLogger.php Infrastructure/PipelineLogger.php
    git mv Infrastructure/ParserResultCache.php Infrastructure/EngineResultCache.php

- Clean up legacy directories
    - rmdir Parsers Extractors
- Update namespaces and references
- Fix Service Provider bindings and Controllers:Step 
- Run a global search in your IDE for App\Services\. Update any locations where these classes were typed, imported (use), or bound in your AppServiceProvider.php.
- Flush cache
- Test

app/Services/
├── Roster/
│   ├── JcaRosterProcessor.php      # (Was JcaScheduleParsingService) Coordinates
│   ├── ScheduleInputResolver.php   # Deals directly with raw inputs/requests
│   ├── PdfTextExtractor.php        # (Was SchedulePdfExtractor) Low-level PDF tool
│   └── Parsers/                    # Internal text sub-parsers used by the engine
│       ├── CrewListParser.php
│       ├── PublishedRosterParser.php
│       ├── ScheduleFormatParser.php
│       └── TripInformationParser.php
│
├── FlightPlan/
│   ├── FlightReleaseProcessor.php      # Main entry point for the route engine
│   └── Parsers/
│       └── FlightRouteParser.php       # (Was FlightRouteExtractor) text parser
│
├── Calendar/
│   ├── IcsGenerator.php  # (Was IcsCalendarService) Handles raw .ics payload syntax
│   ├── RosterCalendarMapper.php        # (Was FlightDutyCalendarEventService) Maps roster lines to standard objects
│   └── ExportPayloadService.php        # (Was ParserCalendarExportService) Wraps data ready for client delivery
│
├── Clients/
│   ├── AirportLookupClient.php
│   └── AirlineCodeLookupClient.php     # Fixed name consistency (Added "Client")
│
└── Infrastructure/
    ├── PipelineLogger.php              # (Was ParseRequestLogger)
    └── EngineResultCache.php           # (Was ParserResultCache)

## 12. Use a descriptive footer disclaimer in Parse schedule tool: 
- To safely clarify your tool's relationship to the platform, add a small, subtle line of text at the very bottom of your application page layout:

Disclaimer: This tool is an independent utility built for crew convenience and is not affiliated with, authorized, or endorsed by Jeppesen or Boeing.

## 13. One line status message
- In schedule extractor, shorten status codes
- System online: Ready to process
- 0.3 MB image selected, ready to upload

## 14. Resolve Larastan findings (5 errors remaining at level 5)

Run with `vendor/bin/sail bin phpstan analyse --no-progress`. Keep Larastan in `require-dev` and fix root causes rather than adding a baseline or blanket `ignoreErrors` entries.

### Low — safe cleanup after contract fixes

- [ ] Remove redundant `array_values()` calls on values already typed as lists in `app/DTOs/ParsedEventDTO.php:165`, `app/Mappers/DutyEventMapper.php:124`, `app/Mappers/FlightMapper.php:182`, and `app/Services/RosterParser.php:622` (4 errors).
- [ ] Remove `app/Services/RosterParser.php:751::firstMatchingLine()` if repository-wide usage confirms it is dead code (1 error).

After each cluster, run the focused PHPUnit tests and Larastan again. Finish with `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail bin phpstan analyse --no-progress`, and the relevant parser/auth/view-model test files.
