# Flight Plan Brief Roadmap

Build one reviewable flight-release workspace from the normalized extraction pipeline. Parse each source fact once, keep operational values typed, and present unavailable data honestly instead of inferring it.

### Current foundation

- The feature is an authorized Livewire page with private upload staging, user-scoped result caching, metrics, recoverable errors, and guaranteed upload cleanup.
- `FlightPlanTextExtractor` reads each PDF once, removes null bytes, caches by content hash, and translates parser failures.
- Focused identity, schedule, route, and fuel extractors feed `ParsedFlightPlanData`; `BuildFlightPlanData` creates the typed aggregate.
- The cached compatibility payload contains both the existing flat route fields and nested `flight_plan_data`.
- The current results UI renders airports, runways, procedures, route tokens, and basic ETOPS data through the legacy `FlightReleasePageViewModel`.
- Normalized today: flight/trip/recall identity, aircraft and tail, flight date, airports, route, runways, SID/STAR, distance, ETD/ETA, approved slot instants, and release-level fuel.
- Still legacy-only: airport enrichment, initial altitude, duration, ETOPS points, EENT, and EEXP.
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

| Order | Task | Suggested icon | Initial availability |
| ---: | --- | --- | --- |
| 1 | Overview | `home` | Core data ready |
| 2 | Jepp PD-Pro | `paper-airplane` | Requires confirmed fixtures |
| 3 | Maintenance Log | `clipboard-document-list` | Requires confirmed fixtures |
| 4 | Envelope | `document-chart-bar` | Requires confirmed fixtures |
| 5 | Flight Init | `bolt` | Core data ready |
| 6 | FMS | `calculator` | Core route data ready |
| 7 | Slot Times | `clock` | Basic approved slots ready |
| 8 | Fuel Score | `gauge` or closest Heroicon | Release summary ready; waypoint score pending |
| 9 | ETOPS | `globe-alt` | Basic critical points ready; typed model pending |
| 10 | Weather | `cloud` | Requires confirmed fixtures |
| 11 | Weight & Balance | `scale` | Requires confirmed fixtures |

## 1 Current focus: — Stabilize the result and view-data contract

Goal: Make nested normalized data the primary input to the front end before adding more screens.

- Add a typed page-level view-data layer for shared identity, schedule, route, fuel, airport enrichment, and section availability.
- Read `flight_plan_data` directly and adapt the remaining legacy fields explicitly; do not rebuild the normalized aggregate from flat keys.
- Define one availability state per task: `available`, `not_present`, or `not_supported`.
- Keep the compatibility serializer until every current consumer has migrated.
- Preserve the result cache’s owner scoping, expiry, scalar serialization, and allowlist.
- Add focused tests for complete, partial, malformed, expired, and legacy-compatible payloads.

Done when: the current route card renders from the new page view data with no visual regression and no Blade access to raw cached arrays.

Commit message: `refactor: add typed flight plan page data`

## 2 — Build the responsive task workspace

Goal: Replace the single result card with a reusable shell for all eleven task views.

- Keep the existing page header and `Extract another flight plan` action.
- Add a compact shared release header showing flight number/date, aircraft/tail, route, ETD/ETA, and release revision only when confirmed.
- Add the task navigator as a left rail on desktop and a horizontally scrollable or disclosure-based control on small screens.
- Keep the active task in Livewire state; changing tasks must not reparse the PDF or duplicate cached results.
- Render one content panel at a time with stable keys and task-specific empty/unsupported states.
- Add reusable section header, metric, status, empty-state, and source-evidence components before duplicating markup.
- Cover keyboard navigation, active-state semantics, small-screen behavior, dark mode, and Livewire rehydration.

Done when: all eleven destinations are reachable and accessible, with unavailable tasks clearly labeled and no layout overflow at supported breakpoints.

Commit message: `feat: add flight plan task workspace`

## 3 — Implement Overview

Goal: Provide the highest-value release summary without inventing operational status.

- Show flight number, date, tail, aircraft type, departure, destination, alternate, ETD, ETA, initial altitude, route distance, and ramp fuel when available.
- Show source-backed indicators only. GENDEC, filing, weather/RAIM, maintenance, and other unparsed statuses must remain `not supported` rather than guessed.
- Summarize slot and ETOPS availability from their typed section data.
- Link each summary card to its detailed task.
- Keep airport details secondary to the operational summary.
- Test complete and sparse releases, missing alternate/fuel/time values, units, and navigation links.

Done when: a crew member can identify the flight and spot which detailed sections contain data without opening every task.

Commit message: `feat: add flight plan overview`

## 4 — Implement Jepp PD-Pro

Goal: Present only confirmed performance-planning data from representative PD-Pro sections.

- Add sanitized multiline and flattened fixtures before defining the schema.
- Confirm document revision, runway/condition, performance assumptions, limits, thrust or assumed-temperature data, V-speeds, warnings, and remarks.
- Add focused parsed data, DTO/value objects, source fragments, builder mapping, and section view data.
- Group inputs, computed results, limits, and warnings; keep raw evidence available for verification without exposing the full PDF.
- Render `not present` when a release lacks PD-Pro and `not supported` for fields that remain unconfirmed.
- Test alternate formats, missing fields, invalid values, duplicate agreement, and conflicts.

Done when: every displayed PD-Pro value is traceable to a sanitized fixture and has an explicit unit or context.

Commit message: `feat: add jepp pd pro task`

## 5 — Implement Maintenance Log

Goal: Turn confirmed MEL/CDL/DMI content into a reviewable operational list.

- Add sanitized fixtures covering no items, one item, multiple items, wrapped descriptions, and operational limitations.
- Model item type, number, DMI/reference, description, status, limitations, procedures, and source evidence only when confirmed.
- Reuse shared flight identity and fuel context instead of reparsing it.
- Present count and severity/status summary above an item list; never infer dispatchability.
- Keep maintenance control references and raw fragments private to the result lifecycle.
- Test malformed numbering, duplicate items, missing optional notes, and absent sections.

Done when: maintenance entries are faithful to their source text and the UI makes no airworthiness decision.

Commit message: `feat: add maintenance log task`

## 6 — Implement Envelope

Goal: Present confirmed performance-envelope constraints with clear provenance.

- Start with sanitized fixtures and define whether the source is PD-Pro, weight-and-balance, or another release section.
- Model configuration, weight/CG constraints, runway/temperature/obstacle assumptions, takeoff/landing limitations, and warnings only after confirmation.
- Reuse typed aircraft, route, crew, and performance data rather than duplicating extraction.
- Separate assumptions, permitted envelope, calculated result, and source warnings visually.
- Do not calculate an envelope in the browser or label a condition safe solely from partial data.
- Test boundary values, missing limits, conflicting sources, units, and unavailable sections.

Done when: the view is a faithful presentation of a confirmed envelope result, not an independent performance calculator.

Commit message: `feat: add flight envelope task`

## 7 — Implement Flight Init

Goal: Provide a fast, copy-friendly initialization reference from existing normalized data.

- Show tail, aircraft type, flight number, flight date, ETD, ramp fuel, departure, destination, alternate, trip/recall numbers, and confirmed revision.
- Add crew and operational remarks only after their own fixtures and typed extraction exist.
- Reuse accessible copy controls with live-region feedback.
- Label unavailable ACARS-specific values rather than deriving them from unrelated release timestamps.
- Test copy payloads, missing alternate/revision/fuel, unit labels, and mobile layout.

Done when: supported initialization values can be reviewed and copied without returning to the PDF.

Commit message: `feat: add flight init task`

## 8 — Implement FMS

Goal: Move the current route-oriented UI into a dedicated FMS task and extend it safely.

- Migrate airports, runways, SID, STAR, route, distance, initial altitude, duration, and recall number from the current card.
- Preserve token-aware route display and copying; route wrapping remains a presentation concern.
- Add cost index, step climbs, altitude profile, constraints, and remarks only after confirmed fixture coverage.
- Keep airport enrichment in view data and normalized airport codes in the core route DTO.
- Test flattened/multiline routes, long tokens, missing procedures, alternate absence, copy behavior, and legacy parity.

Done when: the old route card can be removed without losing any current route or airport capability.

Commit message: `feat: add fms task`

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

## 10 — Implement Fuel Score

Goal: Deliver the release fuel summary first, then add source-backed waypoint monitoring.

- Render ramp, taxi, takeoff, trip, contingency, alternate, final reserve, and estimated landing fuel from `FuelPlanData` with units.
- Preserve legitimate zero values and distinguish them from missing values.
- Add sanitized waypoint fixtures before modeling waypoint, ETA, planned remaining fuel, flight level, phase, wind, temperature, speed, and leg duration.
- Define score thresholds and actual-versus-plan inputs as product rules before showing `on plan`, `caution`, or `below target`.
- Keep raw summary/waypoint evidence with normalized values for review.
- Test pounds/kilograms, scaling, exact-versus-rounded precedence, missing/zero values, ambiguous units, and score boundaries.

Done when: summary values are reliable and no status badge appears without a documented calculation rule.

Commit message: `feat: add fuel score task`

## 11 — Implement ETOPS

Goal: Replace legacy ETOPS arrays with typed, source-backed operational data.

- Add ETOPS DTOs for applicability, entry/exit points, ETPs, scenarios, coordinates, alternates, diversion data, critical fuel, restrictions, and source fragments as fixtures permit.
- Migrate current ETP, EENT, and EEXP values without changing their displayed meaning.
- Validate coordinate formats and preserve sequence/order.
- Present critical points, alternates, fuel scenarios, and remarks in separate sections.
- Do not infer approval, suitability, or compliance from the presence of an ETOPS section.
- Test no-ETOPS releases, multiple ETPs, duplicate labels, malformed coordinates, partial sections, and legacy parity.

Done when: the compatibility ETOPS fields are no longer needed by the front end.

Commit message: `feat: add etops task`

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

Goal: Present confirmed weights, indices, CG limits, and source status without performing an unauthorized calculation.

- Add sanitized fixtures for basic operating weight, payload, fuel, zero-fuel/ramp/takeoff/landing weights and indices, limits, and source statuses.
- Introduce typed mass and index/CG values with explicit units and finite, non-negative validation where appropriate.
- Reuse fuel values and aircraft identity; corroborate duplicated values and reject material conflicts.
- Separate actual/planned values, permitted limits, and source-provided status.
- Add cargo/compartment distribution only if confirmed by fixtures and required by the product.
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
