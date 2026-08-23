# Current focus: Refactor blade logic
- Goal move componentName into FlightPlanTask enum
- In workspace blade, migrate large if chain into a switch case

FlightPlanTask is an enum, move view mapping directly onto the Enum. This keeps the Blade template almost logic-free.

In App\Enums\FlightPlanTask.php:

/**
     * Get the corresponding component name.
     * Maps 'jepp_pd_pro' directly to 'flight-release.jepp-pd-pro'
     */
    public function componentName(): string
    {
        return 'flight-release.' . Str::kebab($this->value);
    }

    /**
     * Determine if the task needs airport parameters.
     */
    public function requiresAirports(): bool
    {
        return in_array($this, [self::JeppPdPro, self::Fms], true);
    }

    /**
     * Determine tasks that have dedicated visual components rendered.
     */
    public function hasCustomView(): bool
    {
        return match($this) {
            self::Overview,
            self::JeppPdPro,
            self::MaintenanceLog,
            self::Envelope,
            self::FlightInit,
            self::Fms,
            self::FuelScore,
            self::Etops => true,
            default => false,
        };
    }

Outcome: Moved conventional task component names, airport-prop requirements, and dedicated-view availability into `FlightPlanTask`. The workspace now uses a single availability-first `@switch(true)` and Laravel's dynamic component renderer while preserving Jepp padding, FMS/Jepp airport inputs, unavailable states, and the generic supported-data fallback.

Commit message: `refactor: move flight task view mapping into enum`

# Flight Plan Brief Roadmap

Build one reviewable flight-release workspace from the normalized extraction pipeline. Parse each source fact once, keep operational values typed, and present unavailable data honestly instead of inferring it.

### Current foundation

- The feature is an authorized Livewire page with private upload staging, user-scoped result caching, metrics, recoverable errors, and guaranteed upload cleanup.
- `FlightPlanTextExtractor` reads each PDF once, removes null bytes, caches by content hash, and translates parser failures.
- Focused identity, schedule, route, and fuel extractors feed `ParsedFlightPlanData`; `BuildFlightPlanData` creates the typed aggregate.
- The cached compatibility payload contains both the existing flat route fields and nested `flight_plan_data`.
- The current results UI renders airports, runways, procedures, route tokens, and typed ETOPS data through `FlightReleasePageViewModel`.
- Normalized today: flight/trip/recall identity, aircraft and tail, flight date, airports, route, runways, SID/STAR, distance, ETD/ETA, approved slot instants, release-level fuel, and current ETOPS boundary/equal-time points and scenario text.
- Still legacy-only: airport enrichment, initial altitude, and duration.
- Not yet confirmed from fixtures: release revision, report/duty times, block duration, local times, contingency fuel, and most fields for Jepp PD-Pro, Maintenance Log, Envelope, Fuel Score, Weather, and Weight & Balance.

### Product and UI rules

- Keep the task order fixed: Overview, Jepp PD-Pro, Maintenance Log, Envelope, Flight Init, FMS, Slot Times, Fuel Score, ETOPS, Weather, Weight & Balance.
- Use Aviation Blue for structure, Compass Gold for primary emphasis, and the existing light/dark theme tokens.
- Keep operational values compact and scannable; use monospaced text for codes, times, routes, coordinates, and numeric planning values.
- Label every time basis and fuel unit. Never silently mix UTC/local or pounds/kilograms.
- Distinguish `not present in this release` from `not supported yet`. Do not render zero, empty text, or a green status for missing data.
- Preserve source evidence internally for Weather, ETOPS, Fuel Score, and Weight & Balance. Do not expose raw PDF text or a raw-release link without an approved secure storage and retention design.
- Reuse Blade components and view data; do not parse, normalize, query, or authorize inside Blade.
- Every interactive control needs keyboard access, visible focus, an accessible name, and a useful loading/empty/error state.

### Navigation map

| Order | Task             | Suggested icon              | Initial availability                             |
| ----: | ---------------- | --------------------------- | ------------------------------------------------ |
|     1 | Overview         | `home`                      | Core data ready                                  |
|     2 | Jepp PD-Pro      | `paper-airplane`            | Requires confirmed fixtures                      |
|     3 | Maintenance Log  | `clipboard-document-list`   | Requires confirmed fixtures                      |
|     4 | Envelope         | `document-chart-bar`        | Requires confirmed fixtures                      |
|     5 | Flight Init      | `bolt`                      | Core data ready                                  |
|     6 | FMS              | `calculator`                | Core route data ready                            |
|     7 | Slot Times       | `clock`                     | Basic approved slots ready                       |
|     8 | Fuel Score       | `gauge` or closest Heroicon | Release summary ready; waypoint score pending    |
|     9 | ETOPS            | `globe-alt`                 | Current source-backed task view ready             |
|    10 | Weather          | `cloud`                     | Requires confirmed fixtures                      |
|    11 | Weight & Balance | `scale`                     | Requires confirmed fixtures                      |

Icons  use hero icons returned in FlightPlanTask enum

## 9 — Implement Slot Times

Goal: Present approved slots and later permit constraints with explicit time context.

- Promote slot evidence into a typed slot DTO containing direction/type, airport, canonical UTC instant, and source time.
- Render the currently supported departure/arrival slots first.
- Add tolerance windows, permits, authority/country, validity, revision, status, and notes only after confirmed fixtures.
- Sort slots deterministically while preserving source order where times are equal.
- Never convert to local time without an explicit airport timezone and DST-safe conversion.
- Test midnight rollover, multiple slots, malformed times, missing flight date, ordering, and UTC labels.

Done when: every displayed slot has an airport, direction/type, complete instant, and visible time basis.

Commit message: `feat: add slot times task`

## 10 — Current focus: Implement Fuel Score

Goal: Deliver the release fuel summary first, then add source-backed waypoint monitoring.

- Render ramp, taxi, takeoff, trip, contingency, alternate, final reserve, and estimated landing fuel from `FuelPlanData` with units.
- Preserve legitimate zero values and distinguish them from missing values.
- Add sanitized waypoint fixtures before modeling waypoint, ETA, remaining fuel, and leg duration.
- Keep raw summary/waypoint evidence with normalized values for review.
- Expanding card shows raw waypoint evidence with "More..." label and drop arrow
- Units in 1000xlbs labeled as "k lbs"
- Test pounds/kilograms, scaling, exact-versus-rounded precedence, missing/zero values, ambiguous units, and score boundaries.
- Planned fuel featue: User input `Off` time which calculates an ETA column by adding time to Off time. Ensuring 24 hour time format

Done when: summary values are reliable and no status badge appears without a documented calculation rule.

Commit message: `feat: add fuel score task`

Current outcome: Added the dedicated responsive Fuel Score summary for all eight typed `FuelPlanData` quantities. Pound values are displayed in `k lbs`, kilogram values retain their source unit, legitimate zeroes remain visible, missing values remain distinct, and the view explicitly avoids inferring a score, compliance, or dispatchability. Waypoint monitoring and the user-entered Off-time ETA calculation remain pending until waypoint duration and remaining-fuel values are promoted into the cached typed contract.

## 12 — Implement Weather

Goal: Organize confirmed weather by airport and route while retaining the source report.

- Add sanitized fixtures for METAR, TAF, RAIM, and any supported enroute/significant-weather sections.
- Prefer a proven aviation-weather parser or narrowly scoped parsing; do not build speculative meteorological interpretation.
- Model airport, observation/validity times, raw report, parsed conditions, and source evidence.
- Group departure, destination, alternate, and enroute weather.
- Define warning thresholds with product/domain review before showing operational cautions.
- Test report variants, amendments, missing airports/times, invalid reports, UTC boundaries, and unsupported content.

Done when: parsed fields can always be compared with the retained raw report and warnings have documented rules.

Commit message: `feat: add weather task`

## 13 — Implement Weight & Balance

Goal: Present confirmed weights, indices, and source status without performing an unauthorized calculation.

- Add sanitized fixtures for basic operating weight, payload, fuel, zero-fuel/ramp/takeoff/landing weights and indices, limits, and source statuses.
- Introduce typed mass and index/CG values with explicit units and finite, non-negative validation where appropriate.
- Reuse fuel values and aircraft identity; corroborate duplicated values and reject material conflicts.
- Separate actual/planned values, permitted limits, and source-provided status.
- Test unit variants, zero values, boundaries, conflicts, absent indices/limits, and incomplete sections.

Done when: every comparison is based on confirmed source values and no browser-side arithmetic changes the dispatch result.

Commit message: `feat: add weight and balance task`

## 14 — Remove compatibility paths and complete release verification

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

## 15 - Remove Extract route button
View results on PDF upload when parsing completes

## Add task: review MEL / CDL
- Use counter badge
- Show task at top if items exist
- Have task at bottom if 0

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

# Jepp PD Pro task view
- Move route section above ETOPS critical points section

# FMS task view
- Cost index missing
- Distance to dest value missing
- Create distinction between Alternate airport burn and Reserve fuel calculation. 

# Action oriented labels on Overview
- Flight and aircraft card footer: ACARS Initialize Flight ->
- Route card: Program FMS ->
- Schedule and slots -> review slot times
- Fuel card: Score fuel ->
- ETOPs evidence card: Review ETOPs ->

# Create maintenance overview card
- Show's MEL status
- Action oriented footer: Review MELs ->

# Large upload button
- Similar to extract schedule upload button

# Move 2 maintenance DTOs into Maintenance DTO subfolder

# GENDEC
- Define General Declaration in CONTEXT.md
- **General Declaration / GENDEC** - A General Declaration (GENDEC) is an official international aviation and customs document required by border control, immigration, custom, and public health authorities when an aircraft arrives in or departs from a foreign country.
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

## UI Redesign: Flight Plan Brief Header Component

**Context**
Redesign of a data-heavy flight information section (originally a standard description list) into a visual "Flight Strip" for a more intuitive dashboard experience.

**Diagnostics**
The original layout utilized a Tailwind-styled `dl` (description list) with the following technical characteristics:
*   **Structure:** A grid-based layout (`grid-cols-2` scaling to `xl:grid-cols-6`).
*   **Styling:** Contained within a `section` element featuring `bg-[#F8F9FA]` (light) or `bg-slate-800/70` (dark) with a subtle border and shadow.
*   **Data Points:** Flight callsign, date, aircraft type, tail number, route (origin/destination), and UTC timestamps (ETD/ETA).

**Visual Layout Improvements**
The information was restructured to prioritize aviation-specific hierarchy:

| Feature                 | Change Implementation                                                                  |
| :---------------------- | :------------------------------------------------------------------------------------- |
| **Callsign & Aircraft** | Grouped with a flight icon; increased font size to `text-lg`.                          |
| **Route Visualization** | Implemented a horizontal journey flow (Origin → Destination) using a progress line.    |
| **Temporal Data**       | Paired ETD/ETA directly with their respective airport codes; centered flight duration. |
| **Status Indication**   | Added a color-coded status badge (`emerald-500/10`) for immediate visibility.          |

**Proposed Code Fixes**
The following changes were identified as a potential fix for the live page to replace the rigid grid with a flexible, visual container:


`````html
<!-- Proposed replacement for the internal container -->
<div class="flex flex-col md:flex-row items-center justify-between gap-6 p-4">
  <!-- Flight Identity -->
  <div class="flex items-center gap-4">
    <div class="h-12 w-12 rounded-full bg-blue-600/10 flex items-center justify-center text-blue-600">
      <!-- SVG Icon -->
    </div>
    <div>
      <h3 class="text-lg font-bold text-slate-900 dark:text-white">${callsign}</h3>
      <p class="text-xs font-medium text-slate-500">${aircraft} • ${tail}</p>
    </div>
  </div>

  <!-- Journey Visualization -->
  <div class="flex-1 flex items-center justify-center gap-4 max-w-md w-full">
    <div class="text-right">
      <div class="text-xl font-black">${origin}</div>
      <div class="text-xs font-bold text-blue-600 uppercase">${etd} UTC</div>
    </div>
    
    <div class="flex-1 flex flex-col items-center gap-1">
      <div class="text-[10px] font-bold text-slate-400">${duration}</div>
      <div class="w-full h-px bg-slate-200 relative">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-2 h-2 rounded-full border-2 bg-white"></div>
      </div>
      <div class="text-[10px] font-bold text-slate-400">${date}</div>
    </div>

    <div class="text-left">
      <div class="text-xl font-black">${destination}</div>
      <div class="text-xs font-bold text-blue-600 uppercase">${eta} UTC</div>
    </div>
  </div>
</div>
`````


**Actionable Recommendations**
*   **Refine Hierarchy:** Use `font-black` for airport codes and `font-mono` for callsigns to match industry standards.
*   **Enhance Aesthetics:** Apply `backdrop-filter: blur(8px)` and reduce the container background opacity to integrate the component better into modern dark/light mode UI.
*   **Data Density:** Remove repetitive labels like "FLIGHT" or "ROUTE" in favor of visual groupings to reduce cognitive load.

*Note: The code fixes and findings above were identified on a live page in DevTools. When applying them to your codebase, please adapt them to your project's specific technical stack (e.g., Tailwind CSS classes, CSS modules, framework components) rather than applying them as literal CSS overrides.*

# Create a way to turn tasks on or off
- in ENV and config files
- in coordination with enum

# Refactor FlightPlanBriefTest
- Split tests and organize into folders grouped by test focus area
-------------------------------------------------------

**Completed**

-------------------------------------------------------


## 1 — Completed: Stabilize the result and view-data contract

## 2 — Completed: Build the responsive task workspace

## 3 — Completed: Implement Overview

## 4 — Completed: Implement Jepp PD Pro

## 5 — Completed: Implement Maintenance Log

## 6 — Completed: Implement Envelope

## 7 — Completed: Implement Flight Init
Crew list follow up change: Add employee numbers to each crew member

Goal: Provide a fast, ACARS flight initialization reference from existing normalized data.

Fields:
Tail Number: N774CK
ETD
Est. Ramp Fuel
Flight Number: CKS256
Departure
Destination
CREW LIST including employee numbers
ACARS INIT DATE 25
- ACARS INIT DATE must be taken from the TLR page not the flight date
Sample data:
TAKEOFF AND LANDING REPORT CKS 0524 KDFW-RKSI 11MAY26
TLR-30 SEQ-93651152 11MAY26 1355Z
A/C N770CK B777-300ER GE90-115BL
ACARS INIT DATE 11

Should return 11
- Create tests for flight data that differs from an ACARS INIT DATE. It should return the ACARS date.

Done when: supported initialization values can be reviewed without returning to the PDF.

Outcome: Added a dedicated read-only Flight Init workspace for tail, UTC ETD, ramp fuel, flight, route, explicit TLR ACARS INIT DATE, and crew employee numbers, without copy controls. ACARS dates and employee numbers are normalized by a dedicated service; the ACARS date never falls back to the release flight date. Employee numbers are retained in the owner-scoped normalized result for this task, while raw crew and TLR source fragments remain private.

Verification: Focused extractor, normalization, DTO, builder, serializer, view-model, Livewire rendering, adjacent task, and Flight Init copy-control absence tests pass. Pint, the production asset build, and final Larastan analysis were run successfully.

Commit message: `feat: add flight init task`

ACARS date follow-up outcome: The TLR extractor now accepts PDF whitespace, including a line or page separator between `ACARS INIT DATE` and its day value. It avoids Unicode regex mode so an unrelated invalid byte cannot make the match fail, and it accepts the confirmed PDF concatenation `GE90-110BLACARS INIT DATE   25` without requiring a word boundary before `ACARS`. The supplied same-line, wrapped, invalid-byte, and concatenated forms all return `25` without falling back to either report date.

ACARS date follow-up verification: The 24 focused extractor, normalizer, builder, page-data, and serializer tests passed with 151 assertions, and the focused Flight Init Livewire test passed with 40 assertions. Pint passed, and targeted Larastan analysis reported no errors.

ACARS date follow-up commit message: `fix: parse concatenated ACARS init label`


## 8 — Completed: Implement FMS
Extracted Fields

- FMS View
- Flight Number: CKS256
- AC Type: B777-200F
- RECALL Number - 5 digit
- Departure
- Destination
- Alternate
- Planned Departure / Arrival Runway, SID, and STAR
- Distance to Destination: 5549
- Initial Altitude
- Cost Index
- Alternate Airport Reserves

* No copyable fields

Goal: Move the current route-oriented UI into a dedicated FMS task and extend it safely.

- Migrate airports, runways, SID, STAR, route, distance, initial altitude, duration, and recall number from the current card.
- Preserve token-aware route display and wrapping without adding FMS copy controls.
- Add cost index, step climbs, altitude profile, constraints, and remarks only after confirmed fixture coverage.
- Keep airport enrichment in view data and normalized airport codes in the core route DTO.
- Test flattened/multiline routes, long tokens, missing procedures, alternate absence, and legacy parity.

Done when: the old route card can be removed without losing any current route or airport capability.

Outcome: Replaced the generic route card with a dedicated, responsive FMS workspace for confirmed flight identity, five-digit RECALL number, airports and enrichment, planned runways and procedures, route distance, initial altitude, planned duration, alternate airport reserves with units, and token-aware route display. FMS contains no copy controls; Jepp PD-Pro retains its independent route-copy presentation. Cost index, step climbs, altitude profiles, constraints, and remarks remain omitted because no confirmed fixture contract exists. Invalid four- or six-digit RECALL values are ignored without discarding the remaining release identity.

Verification: Focused identity, route, fuel, page-data compatibility, view-model, complete and sparse Livewire FMS, Jepp regression, and workspace rehydration tests pass. Pint, the production asset build, and final Larastan analysis were run successfully.

Commit message: `feat: add fms task`

## 9 — Completed: Reject solar forecasts from flight crew

Outcome: Flight-release crew extraction now requires a recognized crew role before accepting a parsed member. If a `CREW LIST` span contains unrelated numbered text such as a NOAA solar forecast and yields no valid crew, extraction falls back to the ID-first release manifest elsewhere in the PDF text. The regression fixture confirms the NOAA issue date and forecast headings are excluded while the actual PIC, SIC/FO, and IRP records are returned.

Verification: The 14 focused flight crew extractor, shared crew parser, and aggregate extractor tests pass with 128 assertions. Pint passed, and targeted Larastan analysis of the modified extractor and regression test reported no errors.

Commit message: `fix: reject solar forecast crew entries`

## Setup needed prior to creating new tasks and views

### Completed: Waypoints extraction service

Outcome: Confirmed no existing waypoint extractor and added a focused computed-flight-plan service with a sanitized fixture. It requires the paired table headers, preserves source order and duplicate identifiers, and extracts canonical coordinates, identifiers, optional source `TIME`, and optional source `T/TME` values without stripping leading zeroes or inventing units. Coordinate-backed FIR crossing rows remain represented with a missing leg time, coordinate-less markers such as TOC are not assigned a nearby coordinate, and bounded source evidence contains only the extracted waypoint rows. The normalized extraction orchestrator now invokes the service once per release and retains its raw normalized records and private evidence in `ParsedFlightPlanData` for later DTO mapping.

Verification: All 5 focused waypoint extractor tests and both normalized extraction-orchestrator tests passed with 66 assertions, covering the representative 10-record table, missing headers, duplicate identifiers, leading zeroes, FIR placeholders, TOC coordinate isolation, CRLF, horizontal whitespace, bounded evidence, single-pass invocation, and parsed-data retention.

Commit message: `feat: add waypoint extraction service`

### Completed: ETOPS DTO foundation

Outcome: Added immutable ETOPS DTOs under `App\DTOs\Etops` for applicability, entry and exit points, ordered equal-time points, validated degrees-and-minutes coordinates, alternates, diversion data, scenarios, and critical fuel with explicit units. Duplicate ETP labels and source order are preserved, partial or absent sections remain representable, and the normalized flight-plan aggregate now has an optional ETOPS section without changing the legacy extractor or front end.

Verification: The 4 focused ETOPS DTO tests passed with 22 assertions. The 2 `FlightPlanData` aggregate tests, 6 builder tests, and serializer regression test passed with 56 combined assertions.

Commit message: `feat: add typed etops data objects`

## 11 — Completed: Implement ETOPS

Goal: Replace legacy ETOPS arrays with typed, source-backed operational data.

- Add ETOPS DTOs for applicability, entry/exit points, ETPs, scenarios, coordinates, alternates, diversion data, critical fuel, restrictions, and source fragments as fixtures permit.
- Completed current focus: Migrate current ETP, EENT, and EEXP values without changing their displayed meaning.
- Validate coordinate formats and preserve sequence/order.
- Present critical points, alternates, fuel scenarios, and remarks in separate sections.
- Do not infer approval, suitability, or compliance from the presence of an ETOPS section.
- Test no-ETOPS releases, multiple ETPs, duplicate labels, malformed coordinates, partial sections, and legacy parity.

Done when: the compatibility ETOPS fields are no longer needed by the front end.

Outcome: Promoted the existing ETP, EENT, and EEXP extraction results into the normalized parsed contract and existing typed ETOPS DTOs. The normalized builder validates coordinates, retains point ordering, keeps alternate airport order and scenario text, carries explicit applicability without inferring ETOPS approval, and omits malformed optional points without failing the release. Exact repeated rows from duplicated PDF sections are collapsed while distinct points with duplicate labels remain in source order. Cached page data reconstructs ETOPS exclusively from `flight_plan_data.etops`; the page model and view model no longer consume the flat ETOPS compatibility fields. The dedicated responsive ETOPS task view separates applicability, boundary points, equal-time points, alternate pairings, and current source scenario text, labels unavailable diversion/critical-fuel/remark details honestly, protects source evidence, and states that no approval, suitability, compliance, or dispatchability determination is made. Jepp PD-Pro retains its existing displayed meaning, while the serializer continues emitting flat keys only for compatibility consumers outside the front end.

Verification: Pint passed. The 55 focused extractor, builder, page-data, and view-model tests passed with 336 assertions, covering no ETOPS data, partial entry-only data, multiple points, duplicate labels, exact repeated rows, malformed coordinates, ordering, typed reconstruction, and legacy display parity. The 3 focused Livewire ETOPS task, absent-state, and Jepp parity tests passed with 81 assertions. Focused Larastan analysis of the modified extractor and view model passed with zero errors.

Commit message: `feat: add etops task`
