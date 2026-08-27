# TODO Useage
- Sections:
  - TODO Useage
  - Roadmap
  - Tasks
  - After branch merge tasks
  - Completed tasks
1. Complete numbered tasks in order
2. Focus on one task at a time indicated by `Current focus: ` in h2 title
3. Only complete assigned task
4. Mark completed by [x] and replacing `Current focus: ` with `Completed: `
5. Reference `# Codex Usage Rules` in AGENTS.md
6. Create a commit message for each task

# Flight Plan Brief Roadmap

Build one reviewable flight-release workspace from the normalized extraction pipeline. Parse each source fact once, keep operational values typed, and present unavailable data honestly instead of inferring it.

### Current foundation

- The feature is an authorized Livewire page with private upload staging, user-scoped result caching, metrics, recoverable errors, and guaranteed upload cleanup.
- `FlightPlanTextExtractor` reads each PDF once, removes null bytes, caches by content hash, and translates parser failures.
- Focused identity, schedule, route, waypoint, maintenance, and fuel extractors feed `ParsedFlightPlanData`; `BuildFlightPlanData` creates the typed aggregate.
- The cached compatibility payload contains both the existing flat route fields and nested `flight_plan_data`.
- The current results UI renders airports, runways, procedures, route tokens, and typed ETOPS data through `FlightReleasePageViewModel`.
- The release header preserves the explicit tail label and falls back to the confirmed flight date when a departure timestamp is unavailable.
- Normalized today: flight/trip/recall identity, aircraft and tail, flight date, airports, route, runways, SID/STAR, distance, ETD/ETA, approved slot instants, release-level fuel, and current ETOPS boundary/equal-time points and scenario text.
- Still legacy-only: airport enrichment, initial altitude, and duration.
- Not yet confirmed from fixtures: release revision, additional fuel, and most fields for Weather, and Weight & Balance.

### Branch completion criteria
- Weather task completed
- Weight & Balance task completed
- Initial altitude revised from legacy arrays to source backed fixtures and can distingush between units: meters or feet
- No known bugs pending
- Tasks include a badging number system to highlight important information within each task that requests user's attention
- Compatibility paths removed
- Extract route button is removed and parsing/extracting begins immediately after file selection
- Refactor to a large dropzone file upload
- All work required to complete this branch is documented above the **Branch Merge** divider; only explicitly deferred, post-merge tasks appear below it.

### Product and UI rules

- Keep the task order fixed: Overview, Jepp PD-Pro, Maintenance Log, Envelope, Flight Init, FMS, Slot Times, Fuel Score, ETOPS, Weather, Weight & Balance.
- Use Aviation Blue for structure, Compass Gold for primary emphasis, and the existing light/dark theme tokens.
- Keep operational values compact and scannable; use monospaced text for codes, times, routes, coordinates, and numeric planning values.
- Label every time basis and fuel unit. Never silently mix UTC/local or pounds/kilograms.
- Distinguish `not present in this release` from `not supported yet`. Do not render zero, empty text, or a green status for missing data.
- Preserve source evidence internally for Weather, ETOPS, Fuel Score, and Weight & Balance.
- Reuse Blade components and view data; do not parse, normalize, query, or authorize inside Blade.
- Every interactive control needs keyboard access, visible focus, an accessible name, and a useful loading/empty/error state.

### Navigation map

| Order | Task             | Suggested icon              | Current availability                |
| ----: | ---------------- | --------------------------- | ----------------------------------- |
|     1 | Overview         | `home`                      | Core data ready                     |
|     2 | Jepp PD-Pro      | `paper-airplane`            | Source-backed task view ready       |
|     3 | Maintenance Log  | `clipboard-document-list`   | Source-backed task view ready       |
|     4 | Envelope         | `document-chart-bar`        | Source-backed task view ready       |
|     5 | Flight Init      | `bolt`                      | Core data ready                     |
|     6 | FMS              | `calculator`                | Core route data ready               |
|     7 | Slot Times       | `clock`                     | Basic source-backed approved slots  |
|     8 | Fuel Score       | `gauge` or closest Heroicon | Source-backed summary and waypoint  |
|     9 | ETOPS            | `globe-alt`                 | Source-backed task view ready       |
|    10 | Weather          | `cloud`                     | Awaiting confirmed fixtures         |
|    11 | Weight & Balance | `scale`                     | Awaiting confirmed fixtures         |

Icons  use hero icons returned in FlightPlanTask enum

# Tasks

## 1. [x] Completed: Flight Init — Normalize initial altitude units

Goal: Represent the confirmed initial altitude as a typed numeric value with an explicit unit instead of displaying the source flight-level code unchanged.

Current behavior: A metric initial altitude such as `S0890` is rendered verbatim. The value represents 8,900 meters, but the extractor does not currently separate the altitude from its unit.
  
- Add a sanitized, source-backed fixture containing a confirmed metric initial altitude, as well as feet. Implement an `isFlightLevel` bool.
- Introduce an altitude unit enum supporting feet, and meters.
- Extract the altitude as an integer and preserve its confirmed source unit.
- Render the normalized altitude with an explicit unit while retaining the original source evidence.
- Test metric and feet inputs, malformed values, and missing initial altitude data.

- Flight level text sample in feet representing FL330: -N0497F330 
- Flight level text sample in meters: -K0920S0890

Outcome: Initial altitude is normalized into a typed integer value with an explicit feet or meters unit and an `isFlightLevel` flag. Flight levels render in operational notation, such as `FL270` for feet and `FL089M` for meters; non-flight-level values retain explicit `ft` or `m` units. Original ICAO codes remain in private source evidence. Sanitized feet and metric fixtures cover confirmed inputs, with focused coverage for malformed and missing values.

Done when: Flight Init displays a typed initial altitude with its correct unit, and unsupported or absent values are reported without inference.

Commit message: `fix: normalize flight init altitude units`

Follow up: 
1. [x] Render FL if flight level. If Meters append M. i.e. FL270 for flight level in feet or FL089M for flight level in meters.

### [x] Completed: Follow up - initial altitude in ft
Problem: FMS only accepts Cruise altitude entry in feet

Currently: Filed initial altitude exists in the code base already but not identified as such.
2 initial altitudes are given in a flight release, one filed, and one for FMS initialization given in flight level thousands of feet. This last case is not handled.

Sample text from release: 
```text
         BURN    TIME   FL   DIST  WIND

DEST RKSI 033.4   01.48  290  0896  P078   BASIC OPTG WEIGHT  310710
```
Current naming convention: 'initial_altitude' => $this->formatInitialAltitude($matches[2]),


- From the sample text, the FMS init flight level is FL290
- Mapping:
Overview and Jepp PD-Pro: Filed initial altitude
FMS: FMS initial altitude

Fix: Extract flight level from seperate section. 
   - Create seperate initial altitude in Flight init DTO
   - Distinguish between FMS initial altitude and filed initial altitude. Rename objects as necessary
   - Extract flight init flight level in services
   - Identify filed initial altitude
   - Distinguish UI labels

Outcome: The ICAO FPL value is retained as the typed filed initial altitude for Overview and Jepp PD-Pro. The destination-summary FL column is independently extracted as a feet-based typed FMS initial altitude and rendered only in FMS. The Flight Init task renders neither altitude.

### Failed tests
Tests\Feature\Livewire\FlightPlanBriefTest - 2 failues in flight level

## 2. [x] Completed: FMS task view

### [x] Bug fixed: Distance not rendered
- Distance extraction now finds every `TOTAL DIST/DEST` occurrence regardless of stripped preceding whitespace and returns the value only when all captured distances agree.
- Verified leading-zero distance `TOTAL DIST/DEST 0896` as 896 NM and `TOTAL DIST/DEST 3597` as 3,597 NM.

### [x] Completed: Cost index missing
Currently:
- No evidence of extraction.
- No fixtures in place. Cost index not found in DTOs
- Cost index is needed in FMS task
- Not rendered

Cost index is found within the fuel burn section. Logically belongs in the Fuel Plan Data.
app/DTOs/FuelPlanData.php
app/Services/FlightPlan/Extractor/FlightFuelExtractor.php

Fix:
- DTO fixture
- Implement extraction logical
- Cost index range 0-999
- Implement UI render within FMS task only
- Sample text
```text
FUEL BURN BASED ON:  CI200
```
- Update tests

Outcome: Cost index is extracted from the fuel-burn basis as a typed integer in the confirmed 0–999 range, retained with private source evidence, serialized through `FuelPlanData`, restored from cached page data, and rendered only in the FMS task. Missing, malformed, and out-of-range values remain unavailable without inference. A sanitized fuel fixture and focused extractor, DTO, aggregate, cache-rehydration, view-model, and Livewire coverage verify the complete path.

Follow-up outcome: Cost-index normalization now lives in `FuelPlanFieldNormalizer`; both build actions delegate to the service instead of owning duplicated domain logic.

PDF regression outcome: Cost-index extraction accepts collapsed parser text such as `CI180TAXI` while still rejecting values with more than three digits. The supplied `CKS025625KLAX.pdf` now extracts cost index 180 with retained source evidence.

Commit message: `fix: extract and render FMS cost index`
  
## 3. [x] Completed: Remove Extract route button
- Removes need for user to make an unnecessary additional action (click/tap)
- View results on PDF upload when parsing completes
- Render a preloader using the wire:loading livewire directive
- resources/views/livewire/flight-plan-brief.blade.php

Outcome: Selecting a flight release PDF now starts extraction automatically after Livewire finishes the temporary upload. The separate Extract route button and submit form were removed, while the upload input retains an accessible combined uploading-and-processing status and existing recoverable validation errors.

Commit message: `refactor: extract flight plan immediately after upload`

## 4. [x] Completed: Large upload button
- Similar to extract schedule upload button
- Use same icon
- `Drop your flight plan here`
- `Upload on PDF flight plan. Click to browse your files.`
- Centered layout
- Only focus on the upload section for now. Retain index page as is

Outcome: The flight-plan upload is now a centered, large dashed dropzone matching the schedule extractor's upload treatment and icon. It retains the automatic PDF processing flow, accessible file input, visible focus state, loading status, validation errors, responsive sizing, and dark-mode styling without changing the index or results layouts.

Follow-up outcome: Selecting a file now disables the upload input for the full Livewire request and replaces the dropzone prompt with a centered spinner and processing status, preventing duplicate selections while the PDF uploads and parses.

Follow-up outcome: A successful extraction now dispatches a browser event after the results render, smoothly scrolling to the new `release-summary` anchor while leaving validation and extraction failures at the upload control.

Commit message: `style: enlarge flight plan upload target`

## 5. [x] Completed: Implement Weather

Goal: Organize confirmed weather by airport and route while retaining the source report.

- Use fixtures that can be expanded upon after a MVP weather implementation
- Show raw blocks for departure, destination, and alternate airport for METAR, TAF, RAIM. Keep extraction light weight and narrowly scoped
- Use narrowly scoped parsing; do not build speculative meteorological interpretation. Show raw weather output blocks. For instance, whole METAR section or entire TAF section per airport

- Group departure, destination, alternate weather.

Done when: all METAR and TAF blocks are shown for departure, destination, and alternate airports.

Outcome: Weather extraction now uses the confirmed takeoff-and-landing weather block structure from stored flight releases. It retains every supported METAR/SPECI and TAF report as normalized raw text, groups reports by departure, destination, and alternate (`OTHER`) airport, and preserves the confirmed release-level RAIM validity statement. Typed weather DTOs carry the reports through the normalized aggregate, cached payload, defensive page rehydration, task availability, and overview status. The dedicated responsive Weather task renders every report, explicit per-airport missing states, and a no-interpretation warning without deriving conditions, suitability, or dispatch decisions. Sanitized expandable fixtures and focused extractor, DTO, aggregate, cache-rehydration, view-model, enum, and Livewire coverage verify multiline and flattened parser text, missing roles, malformed cached groups, and unsupported input.

Commit message: `feat: add weather task`

### [x] Completed: Follow up - Preserve TAF new lines in weather reports

Investigation outcome:
- `FlightPlanTextExtractor` does not collapse whitespace; it removes only null bytes from `smalot/pdfparser` output, and the parser retains page text line breaks where the PDF exposes them.
- `WeatherExtractor::airportWeather()` currently calls `Str::squish()` on every METAR/SPECI and TAF report. This is the point that irreversibly collapses TAF continuation lines.
- The weather fixture already contains a multiline departure TAF, but `WeatherExtractorTest` expects the flattened form, so the existing test codifies the bug.
- The Weather Blade view already uses a `<pre>` element with `whitespace-pre-wrap`; DTOs, serialization, cache rehydration, and the view model pass report strings through unchanged. No presentation or schema change is required.
- A PDF whose extracted text is already flat contains no reliable layout signal from which to reconstruct line breaks. Flattened input must remain supported as flat text; the extractor must not invent TAF formatting.

Implementation plan:
1. Add a focused report-normalization method in `WeatherExtractor` that normalizes line endings to `\n`, trims report boundaries, removes the trailing parser marker without consuming a preceding newline, collapses horizontal whitespace per line, removes empty boundary lines, and preserves internal line breaks.
2. Use line-preserving normalization for extracted weather reports instead of `Str::squish()`. Apply the same deterministic rule to METAR/SPECI and TAF strings so raw report handling has one contract, while leaving private source-fragment normalization unchanged unless exact evidence formatting is separately required.
3. Update the sanitized weather fixture, if needed, to cover multiple TAF continuation groups such as `FM`, `TEMPO`, and `BECMG`, plus CRLF input. Do not infer breaks from those tokens; assert only breaks present in source text.
4. Update `WeatherExtractorTest` to assert exact multiline TAF output, exact flattened fallback output, normalized CRLF/LF behavior, preserved report boundaries between consecutive reports, horizontal-space cleanup, trailing marker removal, and deduplication after normalization.
5. Add a narrow pipeline/rendering regression assertion showing the multiline string survives typed DTO construction, serialization/cache rehydration, the view model, and escaped `<pre>` rendering. Reuse existing tests at the smallest layers rather than duplicating the full extraction suite.
6. Run the focused weather extractor, DTO/aggregate rehydration, view-model, and Livewire weather tests through Sail; then run Pint for changed PHP files and Larastan once at the final integration checkpoint.

Done when: Parser-provided TAF line breaks survive extraction through rendering exactly as normalized `\n` separators, flattened PDFs remain readable without fabricated formatting, adjacent reports are not merged, and focused regressions pass.

Outcome: Weather reports now normalize CRLF/CR to LF, clean horizontal whitespace per line, remove trailing parser markers, and retain every source-provided internal line break through the typed payload and `<pre>` rendering. When a PDF exposes a TAF as flattened text, recognized ICAO change groups (`FM`, `BECMG`, `TEMPO`, and `PROB30/40`, including combined probability/temporary groups) begin on separate lines; this reconstruction is scoped to TAFs and leaves METAR/SPECI reports unchanged. Focused regressions cover the supplied two-TAF KCVG shape, existing multiline TAFs, consecutive report boundaries, deduplication after normalization, change-group formatting, and rendered multiline output.

### [x] Completed: Follow up - RAIM not found

Problem: RAIM info is not always extracted or found.

Sample: `PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION
VALID FROM 0715Z TO 0935Z`

Outcome: RAIM extraction now accepts parser-flattened releases where the `VALID` label is concatenated to `NAVIGATION` and the following NOTAM section is concatenated directly after the ending `Z` time. It still requires exact four-digit UTC validity times and does not consume adjacent section text. Verified against the supplied KCVG release shape with focused positive and malformed-boundary regressions.

## 6. Implement Weight & Balance

Goal: Present confirmed weights, indices, and source status without performing an unauthorized calculation.

- Add sanitized fixtures for basic operating weight, payload, fuel, zero-fuel/ramp/takeoff/landing weights and indices, limits, and source statuses.
- Introduce typed mass and index/CG values with explicit units and finite, non-negative validation where appropriate.
- Reuse fuel values and aircraft identity; corroborate duplicated values and reject material conflicts.
- Separate actual/planned values, permitted limits, and source-provided status.
- Test unit variants, zero values, boundaries, conflicts, absent indices/limits, and incomplete sections.

Done when: every comparison is based on confirmed source values and no browser-side arithmetic changes the dispatch result.

Commit message: `feat: add weight and balance task`

## 7. Add task: review MEL / CDL
- Use counter badge
- Show task at top if items exist
- Have task at bottom if 0

## 8. Jepp PD Pro task view
- Within the task view: Move route section above ETOPS critical points section
- resources/views/components/flight-release/jepp-pd-pro.blade.php

- Bug fixed: Planned runway extraction now permits a missing SID/STAR and stops at the next planned-runway header, newline, or asterisk divider. Verified against `CKS093312ZSOF 2.pdf`: runway 33 with no SID, and arrival runway 33L with OLMEN OLME2E.

## 9. Action oriented labels on Overview
- Flight and aircraft card footer: ACARS Initialize Flight ->
- Route card: Program FMS ->
- Schedule and slots -> review slot times
- Fuel card: Score fuel ->
- ETOPs evidence card: Review ETOPs ->

## 10. ETOPs badge on section flight info header
- Below duration and horizontal flight line, centered

## 11. Cleanup
- Move 2 maintenance DTOs into Maintenance DTO subfolder

## 12. Feat: GENDEC available determination
- Create service to determine gendec availablity
- Search for General Declaration page
- Sample text:
General Declaration
(Outward/Inward)
Owner or Operator:
K4
Marks of Nationality and Registration:
N774CK
Departure from:
Los Angeles
United States
Flight No:
K4256
Date:
24May2026
Arrival At:

## 13. Bug: Loose crew name regex extraction
- Crew details are extracted within name:
1. `Additional` extracted as name: PAYNE R ADDNTL
2. `IRP` extracted as name: GONZALEZ D IRP
3. `HIGH MINS` extracted as name: FERGUSON S HIGH MINS

- Fix: parse out HIGH MINS, IRP, HIGH MINS removing it from the name
- Stop crew name parsing on line break
- Add a high mins flag to the DTO contract
- Front end display as a caution

Regression outcome: The flattened release-manifest parser now retains a final employee record when the PDF appends empty `IRP`, `MX`, `LM`, and `ACM` role-column placeholders before `CIRCLE THE APPROPRIATE STATUS`. Verified against the supplied release and captured in a deidentified fixture: the PIC, SIC/FO, and final IRP are all extracted without treating placeholder roles as part of a crew name.

## 14. Hide ETOPs card if non ETOPS flight
- Currently ETOPS card always displayed rendering:
ETOPS evidence
Confirmed release fields
Not present in this release
- Fix: Visually minimize ETOPs presence. If non ETOPS flight, remove from large card in workspace and render in the `Operational support status` section with a `Non ETOPS` badge

## 15. Remove compatibility paths and complete release verification

Goal: Finish the migration only after every active front-end consumer uses typed page data.

- Remove flat route keys, legacy `App\ValueObjects\FlightPlan` reconstruction, and compatibility serializer branches only after parity tests pass.
- Keep source fragments out of Livewire snapshots and cached public result payloads unless explicitly allowlisted and sanitized.
- Verify authorization, feature flags, metrics, cache isolation/expiry, upload deletion, parser failures, and unexpected-error reporting.
- Run focused PHPUnit tests while implementing each task.
- Run Pint after PHP changes and JavaScript tests after interaction changes.
- At the final integration checkpoint, run the full PHPUnit suite, production Vite build, JavaScript suite, and Larastan once.
- Perform manual responsive, keyboard, screen-reader-label, light/dark, and representative-PDF smoke tests.
- Update this file with actual outcomes and remove completed implementation detail instead of adding duplicate plans.

Done when: no UI depends on the flat compatibility payload, all enabled tasks have honest availability states, and the full integration checkpoint passes.

Commit message: `refactor: complete flight plan workspace migration`

## 16. 747 AC seeder
- Add 747 ac seeder
### Reserve fuel
- Create distinction between Alternate airport burn and Reserve fuel calculation. 
- Differed due to needing aircraft type fixture and distintion between 747 and 777 aircraft type
- Requires full fleet in production database.
- coincides with future 747 seeder into production
- will have to add migration for reserve fuel additive

## Feat: Determine B43 or B44 release
- Add B44 tag if B44 release
- Future task: B44 info

## Maintenance task: NEF Badges
- Currently non equipment furnishings (NEF) are being extracted as MELs. There is no distinction between the two. CDLs are properly being distinguished. 

-------------------
**Branch Merge**
-------------------

# After branch merge tasks

## Add task: Takeoff and Landing Report

Naming outcome: Renamed the view-model presentation API from the ambiguous `envelope*` prefix to `tlr*`. The normalized payload continues using its existing `envelope` storage key until the broader data contract is migrated.

Commit message: `refactor: rename envelope view model methods to tlr`

Source inputs:

Assumptions
Airport - KDFW
Planned runway  - 36L
Outside air temperature - 23.0 °C
Wind (source code)  - 077M07
QNH - 30.18 inHg
Flap    - 15
Anti-ice    - Yes
Source limits

Permitted Calculations
Maximum runway takeoff weight
820,500 LB
Maximum field takeoff weight
772,400 LB
Source-calculated values

Calculated result
Planned takeoff weight
577,300 LB
V1
71 kt - Need to add 100 kts
VR
76 kt - Need to add 100 kts
V2
83 kt - Need to add 100 kts
Source remarks

**Warnings**
No supported source warnings were listed with the selected result.

No independent performance determination

This view repeats the confirmed source result. It does not calculate an envelope or label the condition safe; review the controlling performance report.

## Create maintenance overview card
- Show's MEL status
- Action oriented footer: Review MELs ->

## Triple extract key flight release data
- Key flight plan data is found on the 3 copies. Ensure regex matches 3 times for data found on the top copy.
- If not found 3 times, reduce confidence score yet still present data
- Show user message to check the value

## Create a way to turn tasks on or off
- in ENV and config files
- in coordination with enum

## Refactor FlightPlanBriefTest
- Split tests and organize into folders grouped by test focus area

## PEST architechure tests
- Does pest need to be installed? 
- Can I run along side existing test suite?
- Naming
- Layering
  
-------------------------------------------------------

# Completed Tasks

-------------------------------------------------------


## 1 — Completed: Stabilize the result and view-data contract

## 2 — Completed: Build the responsive task workspace

Outcome update: Available tasks no longer render redundant status badges or dots; `Not present` and `Not supported` indicators remain visible.

## 3 — Completed: Implement Overview

## 4 — Completed: Implement Jepp PD Pro

## 5 — Completed: Implement Maintenance Log

## 6 — Completed: Implement Envelope

## 7 — Completed: Implement Flight Init

## 8 — Completed: Implement FMS

## 9 — Completed: Reject solar forecasts from flight crew

## Setup needed prior to creating new tasks and views

### Completed: Waypoints extraction service

### Completed: ETOPS DTO foundation

## 11 — Completed: Implement ETOPS

## Completed: Refactor blade logic

## Completed: UI Redesign: Flight Plan Brief Header Component

### Completed: Release fuel summary

### Completed: Waypoint monitoring and planned ETA

## 9 — Completed: Implement Slot Times

## Completed: Critical — Recover six missing MELs from CKS093312ZSOF 2.pdf

Investigation outcome: The release contains eight operational MEL records under `MEL/CDL`, but `MaintenanceLogExtractor` returns only `22-99-02` / DMI `100230537` and `22-99-01` / DMI `100230538`. The following six source-backed MELs are omitted:

| MEL number | DMI | Source description |
| --- | --- | --- |
| `25-20-1-NEF-16` | `100224958` | MISCELLANEOUS INTERIOR TRIM (NON-STRUCTURAL PANELS AND MOLDINGS) |
| `23-27-1-2` | `100230493` | DATA COMMUNICATION MANAGEMENT SYSTEM (ETOPS) ACPT/CANC/RJCT SWITCH LIGHTS |
| `47-11-1-1` | `100230523` | NITROGEN GENERATION SYSTEM (NGS) NITROGEN GENERATION PERFORMANCE |
| `25-25-3-3` | `100230529` | SUPERNUMERARY SEATS (777F) LEG RESTS (M) |
| `22-11-7` | `100230535` | AUTOMATIC LANDING SYSTEM (AUTOLAND) (LMP) AUTOMATIC LANDING SYSTEM (AUTOLAND) |
| `27-02-3` | `100230536` | PRIMARY FLIGHT COMPUTER CHANNELS (LMP) (M) |

Root cause: Both the operational-item matcher and `validNumber()` require the third MEL/CDL number segment to contain 2–4 characters and allow only one optional suffix. These six confirmed source formats use a one-character third segment, and `25-20-1-NEF-16` contains two additional segments, so they are rejected before DTO construction. Separately, the maintenance-section header regex accepts bare `MAINTENANCE`, causing this fixture's retained evidence to begin at the unrelated phrase `MAINTENANCE WRITE UP IS` instead of the actual `MEL/CDL` heading.

Required outcome:

- Support the confirmed variable-length MEL/CDL number formats without accepting arbitrary prose as an item number.
- Anchor operational extraction to the actual `MEL/CDL` section when the release uses that heading.
- Extract all eight records once, retaining DMI and description evidence across the page break.
- Add a sanitized fixture covering these exact number shapes, page-header interruption, and adjacent flattened records.
- Test malformed numbers, duplicate records, conflicting duplicates, and section-start false positives.

Commit message: `fix: recover operational MEL records`
