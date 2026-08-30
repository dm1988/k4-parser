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
7. Each task should consist of: Goal, Current implementation, Problem. Optionally add references and constraints.

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
- Still legacy-only: airport enrichment and duration.
- Not yet confirmed from fixtures: release revision, additional fuel.

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

- Use Aviation Blue for structure, Compass Gold for primary emphasis, and the existing light/dark theme tokens.
- Keep operational values compact and scannable; use monospaced text for codes, times, routes, coordinates, and numeric planning values.
- Label every time basis and fuel unit. Never silently mix UTC/local or pounds/kilograms.
- Distinguish `not present in this release` from `not supported yet`. Do not render zero, empty text, or a green status for missing data.
- Preserve source evidence internally for Weather, ETOPS, Fuel Score, and Weight & Balance.
- Reuse Blade components and view data; do not parse, normalize, query, or authorize inside Blade.
- Every interactive control needs keyboard access, visible focus, an accessible name, and a useful loading/empty/error state.

# Tasks

## 9. [x] Completed: UI: Jepp PD Pro task view
Goal:
Within the task view: Move route section above ETOPS critical points section

References:
resources/views/components/flight-release/jepp-pd-pro.blade.php

- Bug fixed: Planned runway extraction now permits a missing SID/STAR and stops at the next planned-runway header, newline, or asterisk divider. Verified against `CKS093312ZSOF 2.pdf`: runway 33 with no SID, and arrival runway 33L with OLMEN OLME2E.

Outcome: The Jepp PD-Pro task now presents the copyable route immediately after airport details and before ETOPS critical points. Focused Livewire coverage verifies the operational section order.

Commit message: `fix: prioritize route in Jepp PD-Pro task`

## 10. [x] Completed: UI: Action oriented labels on Overview
Currently: `Open ` is prefixed followed by the Flight task enum label.

Goal: Customize labels giving action oriented labels within the overview task.

Implementation:
- In enum, create action labels
- Flight and aircraft card footer: ACARS Initialize Flight ->
- Route card: Program FMS ->
- Schedule and slots: Review slot times ->
- Fuel card: Score fuel ->
- ETOPs evidence card: Review ETOPs ->

References:
resources/views/components/flight-release/overview-card.blade.php
app/Enums/FlightPlanTask.php

Outcome: Overview card footers now use task-owned action labels instead of the generic `Open {task}` prefix. Flight initialization, FMS programming, slot review, fuel scoring, and ETOPS review display distinct operational calls to action while preserving accessible task navigation.

Commit message: `feat: add action labels to overview cards`

## 11. [x] Completed: ETOPs
Currently: ETOPS status is assumed through ETOPS data extraction. ETOPS time to alternate is not extracted or exposed.

Goal:
- Determine if a flight qualifies as ETOPS
- Extract and expose ETOPS time rating
- Render an ETOPS badge in flight header

Implementation:
- ETOPs if text: `ETOPS 180  ETOPS ALTERNATE AIRPORTS` found
- Extract ETOPS duration value, usually 180 or 210
- Add badge on section flight info header
- Badge label `ETOPS {duration}`
- Placement: below duration and horizontal flight line, centered

References:

Outcome: ETOPS qualification and its minute rating are now extracted from the bounded `ETOPS {rating} ETOPS ALTERNATE AIRPORTS` source heading by a dedicated ETOPS extractor. The confirmed applicability and validated rating survive typed DTO construction, cache serialization, and cache rehydration without exposing private source evidence. Confirmed ETOPS releases render a centered `ETOPS {rating}` badge below the flight-duration line in the release header; unrelated ETOPS text and invalid zero ratings do not create the badge.

Follow-up outcome: ETOPS applicability detection now belongs to the ETOPS qualification extractor, including explicit yes/no, `NO ETOPS`, and operational numeric evidence. Maintenance extraction is limited to maintenance sections and items; the extraction orchestrator composes ETOPS applicability into the existing normalized maintenance contract for compatibility.

Regex follow-up outcome: ETOPS rating extraction now accepts parser-flattened headings concatenated between a preceding coordinate and the following airport token, such as `N36430E127299ETOPS 180 ETOPS ALTERNATE AIRPORTSSFO/KSFO`, while retaining letter and airport-token boundaries. Verified against the extracted text shape from `CKS025625KLAX.pdf`.

Commit message: `feat: extract and display ETOPS rating`

## 12. [x] Completed: Hide ETOPs card if non ETOPS flight

Goal:
- Keep the large, actionable `ETOPS evidence` overview card for confirmed ETOPS flights.
- For a confirmed non-ETOPS flight, remove that large card and show a compact `ETOPS` indicator with a `Non ETOPS` status in `Operational support status`.
- Preserve an explicit distinction between confirmed non-ETOPS and unknown or incomplete ETOPS extraction.

Current implementation:
- `resources/views/components/flight-release/overview.blade.php` always renders the `ETOPS evidence` overview card.
- The card uses `availabilityFor(FlightPlanTask::Etops)` and `overviewEtopsSummary()`; without extracted ETOPS route data it displays `Confirmed release fields` with `Not present in this release`.
- `FlightPlanPageData::availabilityFor()` derives ETOPS task availability from `hasEtopsData()`, which checks extracted rating, entry, equal-time points, and exit data rather than the applicability state.
- The normalized ETOPS DTO already exposes `EtopsApplicability` as `confirmed_etops`, `confirmed_non_etops`, or `unknown`.
- `Operational support status` is populated by `FlightReleasePageViewModel::overviewUnsupportedIndicators()` and already renders compact status rows.
- ETOPS task always renders in Task navigation left pane

Problem:
- Confirmed non-ETOPS releases receive the same prominent ETOPS workspace card as ETOPS releases, even though there is no ETOPS review workflow to perform.
- The current empty-state copy suggests missing extraction rather than an intentional non-ETOPS classification.
- Treating missing ETOPS route data as equivalent to confirmed non-ETOPS would hide extraction uncertainty and could misrepresent a release.
- Tasks with no pertinent flight information clutter the workspace

Plan:
- Add view-model helpers that expose the authoritative ETOPS applicability state and whether the large overview card should render.
- Render the large `ETOPS evidence` card only when applicability is `confirmed_etops`; retain its existing task link, availability, and summary behavior.
- Add an `ETOPS` row to `overviewUnsupportedIndicators()` only when applicability is `confirmed_non_etops`, using a compact status label that reads `Non ETOPS`.
- Keep `unknown` distinct from confirmed non-ETOPS. Do not render a `Non ETOPS` badge for unknown data; preserve an unconfirmed/not-present state where ETOPS status is otherwise surfaced.
- Add focused view-model and Livewire feature coverage for confirmed ETOPS, confirmed non-ETOPS, and unknown applicability, asserting both the presence and absence of the large card and compact indicator.
- Remove ETOPS task navigation and the ETOPS detail view unless applicability is `confirmed_etops`.

Constraints:
- Use the normalized `EtopsData::applicability` value as the source of truth; do not infer non-ETOPS from absent rating, ETP, entry, or exit fields.
- Preserve responsive layout and accessibility semantics when the overview grid contains one fewer card.

References:
- `resources/views/components/flight-release/overview.blade.php`
- `app/View/Models/FlightReleasePageViewModel.php`
- `app/View/Models/FlightPlanPageData.php`
- `app/Enums/EtopsApplicability.php`
- `tests/Feature/Livewire/FlightPlanBriefTest.php`

Outcome: Confirmed ETOPS releases retain the actionable Overview card, task navigation, and detail workspace. Confirmed non-ETOPS releases now replace those surfaces with a compact `ETOPS` / `Non ETOPS` row in `Operational support status`. Unknown applicability remains distinct and does not claim `Non ETOPS`; because no ETOPS workflow is confirmed, its Overview card, task navigation, and detail workspace are also omitted. Direct attempts to select a hidden ETOPS task are rejected. Applicability-only ETOPS results now survive typed DTO construction and cache rehydration even when no rating or route points exist. Verified against `CKS093312ZSOF 2.pdf`, which contains only incidental maintenance references to ETOPS and no operational ETOPS qualification or route data.

Commit message: `feat: hide ETOPS task for non-ETOPS flights`


## 13. Feat: GENDEC available determination

Goal:
- Determine whether the uploaded release contains a General Declaration (GENDEC) page.
- Expose the determination through the normalized, typed flight-plan result and cached result payload.
- Replace the hard-coded `GENDEC` `Not supported` overview indicator with `Available` or `Not present` based on extracted evidence.

Current implementation:
- `FlightReleasePageViewModel::overviewUnsupportedIndicators()` always reports `GENDEC` as `FlightPlanTaskAvailability::NotSupported`.
- `ExtractFlightPlanData` receives the complete text extracted from every PDF page, but no dedicated service inspects it for a General Declaration section.
- `ParsedFlightPlanData`, `FlightPlanData`, result serialization, and cache rehydration do not contain GENDEC availability data.
- The Overview already renders compact availability values in `Operational support status`; no separate GENDEC task or workspace view currently exists.

Problem:
- Releases containing a usable General Declaration page are presented as unsupported.
- A loose search for `General Declaration` could produce false positives from incidental references or document indexes.
- Adding detection only to the live extraction path would lose the result after serialization and produce different behavior for cached releases.

Plan:
- Add a dedicated GENDEC extractor service that returns a normalized `section_present` boolean and a minimal source fragment for test/debug evidence.
- Detect a bounded General Declaration page signature rather than a single phrase. Require the `General Declaration` heading and nearby structural labels such as `(Outward/Inward)`, `Owner or Operator`, `Marks of Nationality and Registration`, `Departure from`, `Flight No`, `Date`, and `Arrival At`.
- Make the signature tolerant of PDF extraction whitespace, line breaks, and adjacent flattened labels while keeping the search within a limited section window.
- Invoke the extractor once from `ExtractFlightPlanData` using the already extracted full document text.
- Carry the availability through `ParsedFlightPlanData`, a typed GENDEC/general-declaration DTO on `FlightPlanData`, `toArray()`, `FlightPlanResultSerializer`, and `BuildFlightPlanPageData` cache rehydration.
- Update `FlightReleasePageViewModel::overviewUnsupportedIndicators()` so GENDEC maps to `Available` when the section is present and `NotPresent` otherwise.
- Keep GENDEC in `Operational support status`; do not add task navigation or a workspace view until GENDEC content needs to be displayed or acted upon.
- Add focused extractor tests for the representative page, whitespace/flattening variants, a missing page, and incidental `General Declaration` text without the required field structure.
- Add DTO/build/serialization round-trip coverage and focused view-model or Livewire assertions for both `Available` and `Not present` overview states.

Constraints:
- Treat the document signature as evidence of page availability only; do not validate, interpret, or expose crew, passenger, nationality, registration, or customs data in this task.
- Do not retain the full General Declaration page in the public cached result or rendered HTML; source evidence should remain in the existing private extraction evidence path.
- Preserve compatibility when older cached results do not contain the new field by defaulting GENDEC to `Not present`.
- Use the existing `FlightPlanTaskAvailability` labels and status component rather than introducing a GENDEC-specific badge vocabulary.

Representative source signature (PDF extraction may flatten whitespace and labels):
```text
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
```

References:
- `app/Services/FlightPlan/Extractor/ExtractFlightPlanData.php`
- `app/DTOs/ParsedFlightPlanData.php`
- `app/DTOs/FlightPlanData.php`
- `app/Actions/BuildFlightPlanData.php`
- `app/Actions/BuildFlightPlanPageData.php`
- `app/Services/FlightPlan/FlightPlanResultSerializer.php`
- `app/View/Models/FlightReleasePageViewModel.php`
- `resources/views/components/flight-release/overview.blade.php`
- `tests/Unit/View/Models/FlightReleasePageViewModelTest.php`
- `tests/Feature/Livewire/FlightPlanBriefTest.php`

## 14. Feat: Determine B43 or B44 release
- Add B44 tag if B44 release
- B44 criteria: Text found in release: `RELEASED IAW OPS SPEC B044`
- Future task: B44 info

## 15. Bug: Loose crew name regex extraction
- Crew details are extracted within name:
1. `Additional` extracted as name: PAYNE R ADDNTL
2. `IRP` extracted as name: GONZALEZ D IRP
3. `HIGH MINS` extracted as name: FERGUSON S HIGH MINS

- Fix: parse out HIGH MINS, IRP, HIGH MINS removing it from the name
- Stop crew name parsing on line break
- Add a high mins flag to the DTO contract
- Front end display as a caution

Regression outcome: The flattened release-manifest parser now retains a final employee record when the PDF appends empty `IRP`, `MX`, `LM`, and `ACM` role-column placeholders before `CIRCLE THE APPROPRIATE STATUS`. Verified against the supplied release and captured in a deidentified fixture: the PIC, SIC/FO, and final IRP are all extracted without treating placeholder roles as part of a crew name.

## 16. Implement weight limits in aircraft table and provide 747 AC seeder
- Weight limits do not exist in DB
- Create a list of fields to be added
- Add 747 ac seeder

### 17. Reserve fuel
- Create distinction between Alternate airport burn and Reserve fuel calculation. 
- Differed due to needing aircraft type fixture and distintion between 747 and 777 aircraft type
- Requires full fleet in production database.
- coincides with future 747 seeder into production
- will have to add migration for reserve fuel additive

## 18. Cleanup
- Move 2 maintenance DTOs into Maintenance DTO subfolder
- Identify DTOs and Services that would benefit from improved folder structure

## 19. Remove compatibility paths and complete release verification

Goal: Finish the migration only after every active front-end consumer uses typed page data.

- Remove flat route keys, legacy `App\ValueObjects\FlightPlan` reconstruction, and compatibility serializer branches only after parity tests pass.
- Keep source fragments out of Livewire snapshots and cached public result payloads unless explicitly allowlisted and sanitized.
- Verify authorization, feature flags, metrics, cache isolation/expiry, upload deletion, parser failures, and unexpected-error reporting.
- Run focused PHPUnit tests while implementing each task.
- Run Pint after PHP changes and JavaScript tests after interaction changes.
- At the final integration checkpoint, run the full PHPUnit suite, production Vite build, JavaScript suite, and Larastan once.
- Perform manual responsive, keyboard, screen-reader-label, light/dark, and representative-PDF smoke tests.
- Remove hasCustom view from Tasks enum.
- Move ETOPs functions out of MaintenanceLogExtractor into ETOPs services. i.e. etopsApplicability
- These 2 etops if conditions not possible:
        if (preg_match('/\bETOPS(?:\h+FLIGHT)?\h*[:=-]\h*(YES|Y|NO|N)\b/i', $text, $explicit) === 1) {
            return in_array(Str::upper($explicit[1]), ['YES', 'Y'], true)
                ? EtopsApplicability::ConfirmedEtops
                : EtopsApplicability::ConfirmedNonEtops;
        }

        if (preg_match('/\bNO\h+ETOPS\b/i', $text) === 1) {
            return EtopsApplicability::ConfirmedNonEtops;
        }
- 
- Update this file with actual outcomes and remove completed implementation detail instead of adding duplicate plans.

Done when: no UI depends on the flat compatibility payload, all enabled tasks have honest availability states, and the full integration checkpoint passes.

Commit message: `refactor: complete flight plan workspace migration`


-------------------
**Branch Merge**
-------------------

# After branch merge tasks

## Reorganize overview task
- Remove duplicate data that exists in flight strip header
- Show MELs/CDLs if they exist
- Show ETOPS info if it exists

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


## 6. [x] Completed: Implement Weight & Balance
* If any ambiguity exists, clarify before proceeding.

Goal: Present confirmed weights and source status without performing an unauthorized calculation.

- Add sanitized fixtures for:
  - basic operating weight
  - planned payload
  - fuel, Fixtures for fuel already exist. Ensure coverage.
  - zero-fuel weight
  - ramp weight
  - takeoff gross weight
  - estimated landing weight
  - limits - to be provided by db. Not yet implemented.
- Introduce typed mass values with explicit units and finite, non-negative validation where appropriate.
- Reuse fuel values and aircraft identity; corroborate duplicated values.
- Separate actual/planned values, permitted limits, and source-provided status.
- Actual values are not implemented at this time. Render only planned values and limits to user.
- Test unit variants, zero values, boundaries, conflicts, and incomplete sections.

- unsanitized release text for weights:
```text
BASIC OPTG WEIGHT  335858ALTN RKTU 005.2   00.19  170  0070  P012   PAYLOAD            018000HOLDING   005.9   00.30                    ZERO FUEL WEIGHT   353858RESERVE   006.0   00.28                    TAKEOFF FUEL       223489ADDNL     000.0   00.00                    TAKEOFF GROSS WT   577347BALLAST   000.0                            EST FUEL BURN      205454 ◀
KALITTA BRIEF PAGE 8 OF 197
PAGE 8 OF 197

MIN FUEL  222.5   15.07                    EST LANDING WEIGHT 371893EXTRA     000.0   00.00                    INC BURN/1000 LBS:  0376R/R PAD   001.0   00.04                    FUEL BURN BASED ON:  CI180TAXI      002.0   00.00TTL RMP   225.5   15.11                    EST LANDING FUEL:  018035REFILE FLT 524   ORG NUZAN   / DEST PANC                  FUEL  TIME  DIST              
```
- Deferred limits entirely until db structure exists
- Mass units must not be converted. Units listed in release are in pounds.
- Every displayed weight is source-extracted except planned ramp weight, which is derived server-side from confirmed zero-fuel weight and ramp fuel in the same unit.
- Source status labels:
  - Confirmed
  - Conflict
  - Not present
  - Limit unavailable
- Additional clarification: 
  - Derrive Ramp weight by adding zero fuel weight and ramp fuel. A seperate service may be the most appropriate place after source data is available. 
  - Source values that disagree should show conflict. 
  - Use source status per field.

Done when: every comparison is based on confirmed source values and no browser-side arithmetic changes the dispatch result.

Outcome: Basic operating weight, planned payload, takeoff fuel, zero-fuel weight, takeoff gross weight, and estimated landing weight are normalized as typed mass fields with independent source statuses. Matching duplicate source values are confirmed; disagreements render as conflicts without exposing an uncertain value. Planned ramp weight is derived server-side only from confirmed zero-fuel weight and ramp fuel sharing the same unit, with no unit conversion or browser arithmetic. Actual values remain excluded, database-backed limits remain typed but hidden until available, and private source fragments are not serialized into the Livewire payload. Sanitized fixtures and focused extractor, DTO, builder, cache-rehydration, view-model, and Livewire tests cover confirmed, zero, conflict, incomplete, and unit-mismatch paths.

Commit message: `feat: add weight and balance task`

### [x] Completed: Follow up - UI improvements
UI improvements to enhance usability and visual hierarchy in the **Weight & Balance** task workspace:

**Visual Hierarchy & Categorization**

- **Group by Operational Phase:** Instead of displaying all six metrics in a generic grid, group them logically into distinct phases:
  - **Base & Payload:** Basic Operating Weight + Planned Payload $\rightarrow$ Planned Zero-Fuel Weight.
  - **Departure:** Planned Ramp Weight and Planned Takeoff Gross Weight (with Planned Takeoff Fuel paired alongside).
  - **Arrival:** Planned Estimated Landing Weight.

**Grid & Spacing Optimization**

- **Consistent 3-Column Layout:** Use a balanced 3-column grid on desktop (e.g., Base Data | Departure | Arrival) to prevent awkward trailing single cards like Planned Estimated Landing Weight.
- **Card Footers for Context:** Move explanatory micro-copy (such as *"Derived server-side..."*) directly inside the footer of the specific card it references rather than letting it sit below the entire grid block.

**Data Density & Typography**

- **Unit De-emphasis:** Reduce the visual weight of the unit label (`LB`) relative to the numerical values (e.g., smaller font size or muted color) so the flight crew can scan numbers quickly.

Outcome: The task workspace now uses three balanced desktop columns for Base & Payload, Departure, and Arrival. Each phase keeps its related planned masses together, the ramp derivation explanation is attached to the ramp-weight card footer, and mass units use smaller muted typography so numeric values remain visually dominant. Confirmed badges and unavailable-limit placeholders remain hidden; conflict and missing-source statuses continue to render per field.

## 7. [x] Completed: Maintenance task: NEF Badges
Goal: render a source backed NEF badge instead of a MEL badge on NEF maintenance items.

Currently: non equipment furnishings (NEF) are being extracted as MELs. There is no distinction between the two. CDLs are properly being distinguished. 

- NEFs have `NEF` in the MEL number
- Sample: `M 25-20-1-NEF-16DMI 100224958 MISCELLANEOUS INTERIOR TRIM (NON-STRUCTURAL PANELS AND MOLDINGS)`
- app/Services/FlightPlan/Extractor/MaintenanceLogExtractor.php
  - validNumber function add NEF
- `tests/Fixtures/FlightPlan/maintenance-log/zsof-variable-mel-numbers.txt` provides a testing sample
- NEF should numbers remain copyable like MEL/CDL numbers
- Retain summary heading as is, do not include `NEF`

### In enum add, color, title, and description. Title is Deferred Maintenance Item, Non-Essential Equipment & Furnishings, etc

Badge colors and description:
Red: MEL (Minimum Equipment List)
* Description: MEL items involve required aircraft systems or instruments. They carry strict operational constraints, specific flight conditions (e.g., "Day VFR only"), and hard calendar deadlines.

Orange: CDL (Configuration Deviation List)

* Description: CDL items involve missing external parts (like a missing aerodynamic fairing, static wick, or flap seal). They directly impact performance, fuel burn, or weight limitations. 

Yellow: DMI (Deferred Maintenance Item)

* Description: DMI is a broad category used for tracking parts on order, planned maintenance tasks, or open discrepancies that do not ground the aircraft but need attention.

Gray: NEF (Non-Essential Equipment & Furnishings)

* Description: NEF items are strictly cosmetic or passenger-convenience features (like a broken passenger seat recline or a chipped galley trim). They have zero impact on safety, airworthiness, or performance. 

Outcome: Maintenance numbers containing a complete `NEF` segment are normalized to the typed NEF item type before validation, deduplication, and conflict detection. `MaintenanceItemType` now owns the operational title, description, and light/dark badge color for MEL, CDL, DMI, and NEF; NEF uses a neutral gray badge. The maintenance presenter exposes this enum metadata, NEF numbers remain copyable, and the existing `MEL / CDL` summary heading is retained. Focused extractor, enum, view-model, and Livewire coverage verifies classification boundaries, conflict labels, metadata, badge rendering, and cache-backed presentation.

Commit message: `feat: distinguish NEF maintenance badges`

## 8. [x] Completed: Add task: Review MEL / CDL
Goal: add Review MEL / CDL including MEL,NEF,CDL, and DMI list. Copyable fields: MEL,NEF, and CDL. Keep formatting similar to Maintenance log section under Maintenance log task. Retain applicable caution messages

- Currently: structure already in place found in maintenance log task. MEL list is in non reuseable blade component
- Fix: Seperate MEL/CDL list into it's own reuseable component
- Hero icon: wrench-screwdriver
- Use counter badge totaling MEL, NEF, DMI, and CDL counts
- Show task directly below Overview if items exist; Overview always remains first
- Have task at bottom if 0

Implementation plan:

1. Add `ReviewMelCdl` to `FlightPlanTask` with the `Review MEL / CDL` label, `wrench-screwdriver` icon, dedicated Blade component, and availability derived from maintenance-item presence.
2. Add task-ordering logic outside Blade:
   - Place Review MEL / CDL directly below Overview when any MEL, CDL, NEF, or DMI item exists; Overview always remains first.
   - Place Review MEL / CDL last when the combined item count is zero.
   - Preserve the relative order of every existing task.
3. Extract the maintenance-item list from `maintenance-log.blade.php` into a reusable Blade component that accepts presented maintenance items as a prop.
   - Preserve type badges, statuses, descriptions, references, limitations, and procedures.
   - Keep MEL, CDL, and NEF numbers copyable.
   - Keep DMI numbers non-copyable.
   - Preserve responsive behavior, dark-mode styling, keyboard access, accessible labels, and copy status announcements.
4. Replace the inline list in the Maintenance Log task with the reusable component without changing its current behavior.
5. Create the Review MEL / CDL task view using the reusable item-list component.
   - Display all MEL, CDL, NEF, and DMI items.
   - Retain the `No airworthiness determination` caution.
   - Retain applicable source-evidence and privacy messaging.
   - Provide an honest empty state when no supported items exist.
   - Do not duplicate flight context or crew information from the Maintenance Log task.
6. Add a numeric navigator badge containing the combined MEL, CDL, NEF, and DMI count. Keep it visually and accessibly distinct from the existing availability indicator.
7. Add focused PHPUnit coverage for:
   - Enum label, icon, component mapping, and task registration.
   - Review MEL / CDL appearing first when items exist and last when none exist.
   - The combined navigator counter.
   - Rendering all four maintenance item types.
   - Copy controls for MEL, CDL, and NEF, and no copy control for DMI.
   - Statuses, limitations, procedures, caution messaging, source evidence, and the empty state.
   - Maintenance Log continuing to render the extracted shared component correctly.
8. Run the focused enum and Livewire tests, run Pint after PHP changes, and run Larastan once at the final integration checkpoint.
9. After verification, replace `Current focus:` with `Completed:`, record the outcome in this task, and add its commit message.

References:
app/Actions/BuildFlightPlanPageData.php
app/DTOs/MaintenanceItemData.php
app/Enums/MaintenanceItemType.php
app/Enums/FlightPlanTask.php
app/View/Models/FlightPlanPageData.php
app/View/Models/FlightReleasePageViewModel.php
resources/views/components/flight-release/task-navigator.blade.php
resources/views/components/flight-release/maintenance-log.blade.php
resources/views/components/flight-release/workspace.blade.php
tests/Unit/Enums/FlightPlanTaskTest.php
tests/Feature/Livewire/FlightPlanBriefTest.php

Outcome: Added a dedicated Review MEL / CDL task with the `wrench-screwdriver` icon and a combined MEL, CDL, NEF, and DMI counter badge. Overview always remains first; the review task appears directly below it when source-listed maintenance items exist and moves to the bottom when the count is zero. The Maintenance Log and review task now share reusable maintenance-item and airworthiness-caution Blade components while preserving badges, statuses, descriptions, references, limitations, procedures, dark-mode styling, and accessible copy controls for MEL, CDL, and NEF only. Review availability is source-backed, empty releases remain explicit, and source evidence stays private. Focused enum, view-model, and Livewire coverage verifies metadata, ordering, counts, all item types, copy behavior, caution messaging, empty state, and Maintenance Log compatibility. Pint and the final Larastan checkpoint pass.

Commit message: `feat: add MEL and CDL review task`
