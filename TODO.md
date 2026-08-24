# Flight Plan Brief Roadmap

Build one reviewable flight-release workspace from the normalized extraction pipeline. Parse each source fact once, keep operational values typed, and present unavailable data honestly instead of inferring it.

### Current foundation

- The feature is an authorized Livewire page with private upload staging, user-scoped result caching, metrics, recoverable errors, and guaranteed upload cleanup.
- `FlightPlanTextExtractor` reads each PDF once, removes null bytes, caches by content hash, and translates parser failures.
- Focused identity, schedule, route, waypoint, maintenance, and fuel extractors feed `ParsedFlightPlanData`; `BuildFlightPlanData` creates the typed aggregate.
- The cached compatibility payload contains both the existing flat route fields and nested `flight_plan_data`.
- The current results UI renders airports, runways, procedures, route tokens, and typed ETOPS data through `FlightReleasePageViewModel`.
- Normalized today: flight/trip/recall identity, aircraft and tail, flight date, airports, route, runways, SID/STAR, distance, ETD/ETA, approved slot instants, release-level fuel, and current ETOPS boundary/equal-time points and scenario text.
- Still legacy-only: airport enrichment, initial altitude, and duration.
- Not yet confirmed from fixtures: release revision, additional fuel, and most fields for Weather, and Weight & Balance.

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

## 9 — Current focus: Implement Slot Times

Goal: Present approved slots and later permit constraints with explicit time context.

- Promote slot evidence into a typed slot DTO containing direction/type, airport, canonical UTC instant, and source time.
- Render the currently supported departure/arrival slots first.
- Add tolerance windows, permits, authority/country, validity, revision, status, and notes only after confirmed fixtures.
- Sort slots deterministically while preserving source order where times are equal.
- Never convert to local time without an explicit airport timezone and DST-safe conversion.
- Test midnight rollover, multiple slots, malformed times, missing flight date, ordering, and UTC labels.

Done when: every displayed slot has an airport, direction/type, complete instant, and visible time basis.

Commit message: `feat: add slot times task`

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

# ETOPs badge on section flight info header
- Below duration and horizontal flight line, centered

# Large upload button
- Similar to extract schedule upload button

# Move 2 maintenance DTOs into Maintenance DTO subfolder

# Tripe extract key flight release data
- Key flight plan data is found on the 3 copies. Ensure regex matches 3 times for data found on the top copy.
- If not found 3 times, reduce confidence score yet still present data
- Show user message to check the value

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

## Crew high mins
- Currently crew member name is extracted as: FERGUSON S HIGH MINS
- Fix: parse out HIGH MINS removing it from the name
- Add a high mins flag to the DTO contract
- Front end display as a caution

# Hide ETOPs card if non ETOPS flight
- Currently ETOPS card always displayed rendering:
ETOPS evidence
Confirmed release fields
Not present in this release
- Fix: Visually minimize ETOPs presence. If non ETOPS flight, remove from large card in workspace and render in the `Operational support status` section with a `Non ETOPS` badge

# Create a way to turn tasks on or off
- in ENV and config files
- in coordination with enum

# Refactor FlightPlanBriefTest
- Split tests and organize into folders grouped by test focus area

# PEST architechure tests
- Does pest need to be installed? 
- Can I run along side existing test suite?
- Naming
- Layering
- 
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

## 8 — Completed: Implement FMS

## 9 — Completed: Reject solar forecasts from flight crew

## Setup needed prior to creating new tasks and views

### Completed: Waypoints extraction service

### Completed: ETOPS DTO foundation

## 11 — Completed: Implement ETOPS

# Completed: Refactor blade logic

## Completed: UI Redesign: Flight Plan Brief Header Component

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
*   **Refine Hierarchy:** Use `font-black` for airport codes and `font-mono` for callsigns to match industry standards. In dark mode: `font-white`.
*   **Enhance Aesthetics:** Apply `backdrop-filter: blur(8px)` and reduce the container background opacity to integrate the component better into modern dark/light mode UI.
*   **Data Density:** Remove repetitive labels like "FLIGHT" or "ROUTE" in favor of visual groupings to reduce cognitive load.

**Outcome:** Replaced the rigid release-summary metric grid with a responsive flight strip that groups the flight and aircraft identity, pairs UTC departure and arrival times with their airports, and centers the planned duration and release date along a visual route line. Missing values remain explicit, the optional release revision is presented neutrally, and no operational status is inferred. The component retains accessible summary semantics, dark-mode styling, and caller-supplied attributes.

**Verification:** Focused Livewire rendering coverage confirms the responsive header structure, visual treatment, accessible summary label, and flight-strip information hierarchy.

Date/time follow-up outcome: Departure and arrival dates now render on their own lines beneath the corresponding airport codes. Canonical UTC timestamps are formatted as four-digit aviation times with a separate `Z` label, and overnight arrival dates remain distinct from departure dates. Missing dates and times remain explicit.

Flight-strip spacing follow-up outcome: UTC times now use compact lowercase aviation notation without whitespace (for example, `1430z`), and the journey visualization is capped at Tailwind's `max-w-xl` width so the route line and airport endpoints remain visually cohesive on wide screens.

Responsive-header follow-up outcome: Reduced mobile vertical spacing, replaced the fixed desktop identity width with a flexible 280px minimum, removed journey auto-centering, and added a subtle mobile-only surface around the flight data. The journey retains the previously requested `max-w-xl` cap and becomes transparent within the desktop row.

Dark-mode follow-up outcome: Matched the mobile journey surface to the header's `slate-800/80` dark background token.

Glass-surface follow-up outcome: Added a small backdrop blur to the semi-transparent Journey section in both themes.

Outer-container alignment outcome: Matched the release-summary section to the site header with solid `#F8F9FA` and `slate-800` backgrounds and removed its backdrop blur, while preserving the rounded card geometry and full border. The nested Journey info bar retains its separate mobile treatment.

Commit message: `feat: redesign flight plan brief header`

## 10 — Completed: Implement Fuel Score

Goal: Present the confirmed release fuel summary and add source-backed waypoint fuel monitoring without inventing a fuel score or operational status.

### Completed: Release fuel summary

- Added a dedicated responsive Fuel Score view using typed `FuelPlanData` values.
- Displayed ramp, taxi, takeoff, trip, alternate, final reserve, and estimated landing fuel with explicit units.
- Presented pounds in thousands with the amount separated from the subtle `k lbs` unit label; kilogram values retain their source unit.
- Preserved legitimate zero values and kept missing quantities visibly distinct.
- Kept fuel and waypoint source evidence private and out of the Livewire snapshot.
- Explicitly avoided inferring a score, compliance result, or dispatchability status.
- Covered pounds, kilograms, scaling, exact source values, missing values, zero values, and ambiguous units with focused tests.

### Completed: Waypoint monitoring and planned ETA

- Promoted sanitized waypoint identifiers, coordinates, leg/cumulative durations, and corroborated remaining-fuel values into readonly typed DTOs and the cached flight-plan contract.
- Preserved source order, duplicate identifiers, explicit zero fuel, and honest nulls for sparse or malformed rows; ambiguous release fuel units withhold waypoint fuel.
- Kept raw fuel and waypoint evidence private while rendering only sanitized rows in the Fuel Score disclosure.
- Added optional validated 24-hour Off time input and client-only planned ETA calculation with UTC midnight rollover; the planned column remains hidden until valid calculated values exist.
- Covered extractor, DTO, cache reconstruction, view-model, Livewire, and JavaScript behavior with focused tests, including malformed Off times, missing durations, duplicate waypoints, zero fuel, ambiguous units, sparse rows, and evidence isolation.
- Follow-up: waypoint extraction now supports flattened PDF text layers where both headers and all rows are concatenated; the supplied PANC release produces 52 ordered typed waypoints from `JOH` through `KMIA`.
- Follow-up: compact waypoint FRMG values are expanded to actual fuel quantities (`1477` becomes `147,700 lb`) and displayed as `147.7 k lbs`; coordinates remain typed but are omitted from the Fuel Score browser payload and table.

Outcome: every displayed fuel or waypoint value is source-backed, planned ETA remains clearly separate from extracted data, and no operational score or status is inferred.

Commit message: `feat: add fuel score task`
