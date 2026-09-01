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

## 19. Remove compatibility paths and complete release verification

Goal:
- Finish the normalized flight-release migration only after every active UI consumer can be rebuilt from one typed cached contract, then run a release-quality verification checkpoint.

Current implementation:
- `FlightPlanResultSerializer` emits the nested `flight_plan_data` aggregate plus 16 flat compatibility keys; `HandleFlightPlanExtraction::RESULT_KEYS` independently mirrors that public allowlist.
- `BuildFlightPlanPageData` now rehydrates airport enrichment and planned duration exclusively from `flight_plan_data`; root compatibility values remain emitted for later writer removal but have no active front-end reader.
- `RouteData` owns enriched departure, destination, and alternate airport details, while `ScheduleData::blockDuration` owns the FPL planned duration. `ParsedFlightPlanData::$legacy` remains only for the compatibility-writer cleanup in the later sequence.
- The orphaned `App\ValueObjects\FlightPlan` was removed during task 18; there is no legacy value-object reconstruction path to preserve or migrate.
- ETOPS qualification is already in `Extractor/Etops/EtopsQualificationExtractor`, not `MaintenanceLogExtractor`. The questioned `ETOPS FLIGHT: NO` and `NO ETOPS` branches remain there and have explicit unit coverage; remove them only when representative source evidence proves those signatures invalid.
- `FlightPlanPageData::availabilityFor()` marks several tasks available unconditionally, including Maintenance Log and Flight Init, while their normalized sections can be absent. Honest task availability therefore needs an explicit per-task audit before release.
- Privacy, scoped cache access, upload deletion, authorization, feature gating, metrics, parser failures, and unexpected-error reporting have focused coverage. Cache expiry itself does not yet have an explicit `FlightPlanResultCacheTest` assertion.

Problem:
- Removing the flat payload now would discard airport details and displayed duration after cache rehydration.
- The duplicated allowlist, serializer fields, parser `legacy` bag, and legacy-oriented tests make it easy to reintroduce a flat consumer.
- Cached results written immediately before deployment need an explicit invalidation/version strategy once compatibility hydration is removed.
- A passing unit suite alone does not verify production asset compilation, responsive interaction, accessibility, or representative PDF/OCR behavior.

Removal sequence:
1. [x] Completed: **Close the two remaining normalized-contract gaps.**
   - Choose and document a typed owner for enriched departure/destination/alternate airport data; serialize it inside the normalized aggregate and rehydrate it without reading root keys.
   - Reconcile the route-extractor `duration` with `ScheduleData::blockDuration`. Add parity fixtures first, then keep one typed source or introduce a clearly named typed planned-duration field if the source meanings differ.
   - Add contract tests proving `FlightPlanPageData` and every visible task render identically from a payload containing only normalized data.

   Outcome: `RouteData` now owns typed airport enrichment alongside the corresponding airport codes, and its serialized form carries departure, destination, and alternate details inside `flight_plan_data.route`. `FlightScheduleExtractor` remains the single parser for FPL planned duration, with `ScheduleData::blockDuration` now driving normalized serialization, page hydration, FMS presentation, and the temporary root compatibility value. Extraction no longer drops airport DTOs into legacy-only staging. Normalized-only contract tests prove page hydration and rendered HTML parity for every visible task, while conflicting root airport and duration values are ignored. Focused DTO, extractor, builder, serializer, view-model, task-rendering, and Livewire regressions pass after Pint.

   Commit message: `refactor: normalize airport and duration ownership`
2. [x] Completed: **Cut over readers before writers.**
   - Remove obsolete normalized-vs-flat fallback fixtures now that `BuildFlightPlanPageData` reads airport data and duration only from `flight_plan_data`.
   - Remove root compatibility values from test payload factories while retaining the regression assertion that conflicting root values are ignored.

   Outcome: Shared page-data and presentation payload factories now contain only the normalized `flight_plan_data` contract. The obsolete mixed-payload versus normalized-only rendering comparison was removed, while the focused regression test still injects conflicting root values and proves the normalized airport, route, runway, and duration values win.

   Commit message: `test: complete normalized reader cutover`
3. [x] Completed: **Remove compatibility writers and staging.**

   Outcome: `FlightPlanResultSerializer` now owns a single normalized public boundary containing only `flight_plan_data`; its route-extractor dependency, flat compatibility fields, and formatting helper were removed. `HandleFlightPlanExtraction` returns that serializer contract directly without a duplicated key allowlist. `ParsedFlightPlanData::$legacy` and route staging were removed, and compatibility-only mocks, fixtures, and assertions were replaced with normalized-contract coverage. Repository searches confirm no production or flight-plan test references remain for the removed staging property, writer allowlist, or route formatting method.

   Commit message: `refactor: remove flight plan compatibility writers`
4. **Version the cache boundary.**
   - Version the flight-plan result cache namespace or payload schema when the cutover ships so pre-cutover arrays cannot be silently interpreted by the new hydrator.
   - Add explicit TTL-expiry, wrong-user, malformed-key, reset/forget, and old-schema rejection tests. It is acceptable for an in-flight pre-release result to return the user to the upload state after deployment; it is not acceptable to partially render mixed schemas.
5. **Finish small compatibility-era UI cleanup.**
   - Define the required typed evidence for every `FlightPlanTask` and test present, absent, unsupported, and confirmed-empty states. Remove unconditional availability where a task's source section can be absent, and keep Overview/shared-context exceptions explicit.
   - Remove `FlightPlanTask::hasCustomView()`, its always-true unit assertion, and the unreachable fallback branch in `workspace.blade.php` after component coverage proves all visible task cases resolve.
   - Keep ETOPS signature changes separate from the contract removal. If the two non-ETOPS signatures are invalid, add a sanitized representative fixture that demonstrates the false classification before deleting the branches and updating `EtopsQualificationExtractorTest`.
6. **Lock the privacy boundary.**
   - Assert `sourceFragments`, raw extracted page text, storage paths, and private evidence are absent from the serialized cache payload, Livewire snapshot, rendered HTML, logs, and validation/error responses.
   - Keep the Livewire component state limited to the locked opaque result key and active task; rehydrated page/view data remains derived server-side.

Focused verification while implementing:
- Contract and hydration: `ExtractFlightPlanDataTest`, `BuildFlightPlanDataTest`, `FlightPlanResultSerializerTest`, `BuildFlightPlanPageDataTest`, and DTO tests for each moved field.
- Cache and privacy: `FlightPlanResultCacheTest` plus the successful extraction, missing-cache, reset, cross-user, source-fragment, and Livewire snapshot assertions in `FlightPlanBriefTest`.
- Lifecycle and failure handling: the successful extraction, request metrics, route-not-found, logging failure, unexpected exception, and upload-deletion cases in `FlightPlanBriefTest`.
- Access boundaries: `FlightReleaseControllerTest`, `FeatureRouteAuthorizationTest`, and the component action authorization case in `FlightPlanBriefTest`.
- Presentation parity: `FlightReleasePageViewModelTest`, `FlightPlanTaskTest`, component tests, and the affected JavaScript test only when interaction code changes.

Final integration checkpoint:
1. Run `vendor/bin/sail bin pint --dirty --format agent` after the final PHP changes.
2. Run the complete PHPUnit suite with `vendor/bin/sail artisan test --compact`.
3. Run the JavaScript suite with `vendor/bin/sail npm test`.
4. Build production assets with `vendor/bin/sail npm run build`.
5. Run Larastan once with the project configuration.
6. Add Larastan to CI or an equally enforced release workflow so the final static-analysis gate is repeatable rather than dependent on a manual checklist.
7. Confirm the worktree contains no accidental generated assets, cache files, or unrelated changes.

Manual release smoke tests:
- Upload representative text-based and OCR/image-only PDFs, including GENDEC, B43/B44, non-ETOPS, ETOPS, missing optional sections, parser failure, and malformed/secured PDF cases.
- Confirm automatic parsing after file selection, recoverable retry/reset behavior, task navigation, copy controls, and no reparsing during refresh/task changes.
- Check mobile, tablet, and desktop layouts; keyboard-only operation; visible focus; accessible names/status announcements; screen-reader reading order; and light/dark themes.
- Confirm request metrics and failure records contain operational metadata but no document content, and verify temporary uploads are deleted after both success and failure.

Constraints:
- Do not remove a compatibility field until a typed equivalent and parity test exist.
- Do not keep a silent fallback for old cache schemas after the versioned cutover.
- Run focused tests during each batch, Pint after PHP changes, JavaScript tests after interaction changes, and Larastan only at this final integration checkpoint.
- Replace this plan with concise actual outcomes when completed instead of appending a duplicate implementation log.

Done when: no UI depends on the flat compatibility payload, all enabled tasks have honest availability states, and the full integration checkpoint passes.

Commit message: `refactor: complete flight plan workspace migration`

## 25. [x] Completed: Bug: No TLR extracted data with specific flight plan

Outcome: PDF text flattening joined the `MFPTW` heading directly to the airport code and joined the report timestamp and result values to their following labels. The TLR extractor now accepts those missing horizontal boundaries while retaining the existing typed field validation. A sanitized regression fixture covers the joined heading, report reference, and remarks boundaries. The supplied `CKS022329VHHH.pdf` now extracts the VHHH runway 25C result, report reference, weather inputs, limits, source-calculated weights, source-coded V-speeds, anti-ice state, and `WET RUNWAY` warning.

Commit message: `fix: extract flattened TLR result boundaries`

## 26. ETOPS counter badge
- Count of ETP points

## 27. [x] Completed: Bug: Envelope task availability

Outcome: Envelope availability no longer depends on Takeoff and Landing Report extraction because the task presents shared flight and crew context. It remains selectable when TLR data is absent or contains no supported result, and focused unit and Livewire coverage confirms the Envelope panel still renders the available route context instead of a not-present or unsupported state. The obsolete TLR-specific availability helper was removed.

Commit message: `fix: keep envelope task available`

-------------------
**Branch Merge**
-------------------

# After branch merge tasks

## Reorganize overview task
- Remove duplicate data that exists in flight strip header
- Show MELs/CDLs if they exist
- Show ETOPS info if it exists

## Crew list: role avatar
- Have crew role displayed inside an avatar bubble
Entry: Crew Card UI Refactor
Goal Improve the visual hierarchy and scannability of the crew roster by moving the "Crew Role" (e.g., PIC, SIC, MX) from a secondary text line into a prominent "Avatar Bubble" anchor. The design must be professional, differentiate roles at a glance, and maintain high readability in both light and dark modes without being visually overwhelming.

Current Setup

Container: ul grid using grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 for responsive layout.
Card Structure:
Horizontal flex layout (flex items-center gap-3).
Backgrounds: bg-white (light) / bg-slate-900 (dark).
Borders: Subtle navy tint border-[#1B365D]/10.
Avatar Bubble: A 12x10 (48px wide) flex container with a uniform background (bg-[#1B365D]/5) and role-specific text coloring.
Details: A vertical stack containing the Name (bold) and Employee Number (monospace, prefixed with #).
Implementation Details

Layout Logic:
Switched from flex-col to flex-row (using items-center) to place the role avatar as a visual "bullet" on the left.
Role-Based Semantic Styling:
Unified Background: All bubbles use bg-[#1B365D]/5 to maintain page consistency.
Text Color Palette:
PIC: text-blue-600
SIC/FO: text-indigo-600
MX: text-amber-600
LM: text-emerald-600
Others: text-slate-600
Typography:
Role: text-[10px] font-black uppercase tracking-wider for a "badge" aesthetic.
Employee ID: Simplified to a small secondary row (text-[10px]) to reduce vertical height.
Dark Mode Support: All colors include dark: variants (e.g., dark:bg-slate-800 for the bubble and dark:text-blue-400 for role text) to ensure WCAG contrast compliance.

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

## Aircraft lookup and display weights
- Lookup aircraft by tail_number in db
- Expose weights to user
- Compare planned weights to aircraft weight limits

## 17. Reserve fuel
- Create distinction between Alternate airport burn and Reserve fuel calculation. 
- Differed due to needing aircraft type fixture and distintion between 747 and 777 aircraft type
- Requires full fleet in production database.
- coincides with future 747 seeder into production
- will have to add migration for reserve fuel additive
  
-------------------------------------------------------

# Completed Tasks

-------------------------------------------------------

## 16. [x] Completed: Implement weight limits in aircraft table and provide an Aircraft seeder
- Weight limits do not exist in DB
- Create a migration adding the following fields:
  - Max ramp weight
  - Max Zero fuel weight
  - Max takeoff weight
  - Max landing weight
  - Max autoland weight
  - Minimum flight weight
  - Engines (string)
- Create a seeder
Commands:
./vendor/bin/sail artisan make:migration add_weight_limits_and_engines_to_aircraft_table --table=aircraft

./vendor/bin/sail artisan make:seeder AircraftWeightSeeder
./vendor/bin/sail artisan migrate

./vendor/bin/sail artisan db:seed --class=AircraftWeightSeeder

Outcome: Added nullable aircraft weight-limit and engine columns plus an idempotent, transaction-backed fleet importer for all 37 aircraft in the supplied K4 dataset. The importer maps MZFW, MTOW, MLW, and OEW to their corresponding aircraft weight fields, imports available engine data by unique tail number, creates missing fleet records, preserves unrelated existing aircraft data, and leaves unsupported MRW and autoland values unmapped rather than guessing.

## 18. [x] Completed: Identify cleanup

Goal:
- Make the flight-release code easier to navigate and change by giving each domain one clear owner, breaking up oversized classes, and removing files that no longer participate in a supported path.

Current implementation:
- `app/DTOs` now has domain folders for ETOPS, Maintenance, Weather, and Weight & Balance, while several release-level DTOs remain at the root until their stable construction boundaries are split.
- `BuildFlightPlanData` is 557 lines and `BuildFlightPlanPageData` is 662 lines. Both construct the same nested aggregate from different array shapes and duplicate fuel, maintenance, weather, ETOPS, crew, waypoint, and scalar normalization.
- `ExtractFlightPlanData` and both builders instantiate some concrete collaborators as constructor defaults. This bypasses container-managed dependency injection and makes the real dependency boundary inconsistent.
- `FlightReleasePageViewModel` is 1,168 lines and presents every task from one class, so unrelated task changes share one dependency and one large test fixture.
- `FlightRouteExtractor` now owns route facts only: airports, route text, runways/procedures, and distance. ETOPS route points, filed altitude, and planned duration are extracted by their respective ETOPS, Flight Init, and Schedule boundaries.
- `EtopsData::applicability` is the single normalized ETOPS applicability owner; Maintenance consumes that aggregate instead of retaining a duplicate field.
- Takeoff and Landing Report extraction, staging, DTOs, fixtures, and source evidence use explicit `TakeoffLandingReport*` language. The existing `Envelope` UI task label/slug and serialized `envelope` alias remain only as deliberate task 19 compatibility paths.
- Confirmed dead scaffolds and unused runtime definitions have been removed. Sail, Compose, and the documented Docker baseline now consistently use PHP 8.4.

Problem:
- Domain boundaries are expressed inconsistently by folders, names, and data ownership.
- Large builders and the all-purpose view model increase regression risk and make focused tests harder to understand.
- Duplicate compatibility-era fields can diverge while still producing apparently valid UI output.
- Dead files and unused runtime definitions obscure the supported architecture.

Identified cleanup, in implementation order:
1. [x] Completed: **Correct domain ownership before moving namespaces.**

   Outcome: Maintenance DTOs now live under `App\DTOs\Maintenance`, and ETOPS applicability exists only in the ETOPS aggregate. Takeoff and Landing Report classes, staging data, source evidence, tests, and fixtures use explicit TLR terminology; the old serialized `envelope` key and UI task slug remain documented compatibility aliases for task 19. `EtopsRouteExtractor` owns ETP/EENT/EEXP data, `FlightInitExtractor` owns filed altitude, and `FlightScheduleExtractor` owns FPL planned duration, leaving `FlightRouteExtractor` responsible only for route facts. Flat serializer compatibility values are composed from those normalized owners instead of being parsed by the route boundary. Focused parser, DTO, builder, serializer, view-model, and Livewire regressions pass after Pint.

   Verification follow-up outcome: `FlightReleaseControllerTest` now asserts the current automatic-upload dropzone copy and verifies that the obsolete `Extract route` control remains absent.

   Static-analysis follow-up outcome: `FlightScheduleExtractor::extract()` now declares `slot_source_text` and each slot's nullable `tolerance_minutes` in its public return shape, matching the normalized data it always emits and removing the reported test-offset ambiguity. The private sample ownership assertion now uses PHPUnit's object-property constraint, preserving the Maintenance/ETOPS boundary check without an always-false `property_exists()` call.

   Commit message: `refactor: correct flight plan domain ownership`
2. [x] Completed: **Split construction and presentation by subdomain.**
   - Extract small builders/hydrators for ETOPS, Maintenance, Weather, Crew, Waypoints, and TLR, following the existing `WeightBalanceDataBuilder` pattern.
   - Reuse those collaborators from both initial construction and cached-payload rehydration so validation/defaulting rules have one implementation.
   - Require collaborators through constructor injection instead of defaulting parameters to `new ...`; keep direct construction in tests explicit when appropriate.
   - Split `FlightReleasePageViewModel` into task-specific presenters or immutable view-data objects, leaving a small release-level facade for task visibility, navigation, identity, and shared header data.
   - Replace the broad array-shape staging fields in `ParsedFlightPlanData` incrementally with typed extractor results or typed subdomain input DTOs. Preserve private source evidence separately from the serializable aggregate.

   Outcome: ETOPS, Maintenance, Weather, Crew, Waypoint, TLR, and Weight/Balance builders now provide shared rules for fresh extraction and cached-payload rehydration. Construction collaborators are required through constructor injection, while ten task-focused presenters are composed behind the stable release view-model facade. Crew and Maintenance are the first broad staging arrays replaced by typed input DTOs, preserving source evidence and serialized/rendered compatibility. The focused builder, extractor, serializer, presenter, and Livewire regressions pass after Pint; Larastan remains reserved for task 19.

   Commit message: `refactor: split flight plan hydration and presentation`
3. [x] Completed: **Make folders mirror stable domains.**
   - After responsibilities are split, group flight-plan actions under `App\Actions\FlightPlan` and keep schedule/extract/auth actions in their own domains. Avoid creating folders that would contain only one arbitrary class.
   - Mirror production namespaces in tests as files move; do not perform a repository-wide test shuffle without a corresponding production boundary change.

   Outcome: Flight-plan construction, page hydration, and extraction orchestration now live under `App\Actions\FlightPlan`; schedule result construction lives under `App\Actions\Schedule`; and shared extraction execution/logging lives under `App\Actions\Extract`. Direct action unit tests mirror those production namespaces, while consumer tests remain in their established feature and view-model domains. All affected imports were updated without changing behavior. The focused flight-plan, schedule, extraction, view-model, and Livewire regressions pass after Pint.

   Commit message: `refactor: organize actions by stable domain`
4. [x] Completed: **Remove confirmed residue.**
   - Delete the orphaned `App\ValueObjects\FlightPlan` after a final reference search, the empty `App\Jobs\ProcessImageOcr` scaffold, and the placeholder `tests/Unit/ExampleTest.php`; retain the feature smoke test because it verifies real landing-page behavior.
   - Select and document one supported Sail PHP image, update `compose.yaml` and the technical baseline to agree, then remove only the unreferenced Docker version directories.
   - Remove `FlightPlanTask::hasCustomView()` and the unreachable workspace fallback together after asserting every visible task has a dedicated component.

   Outcome: Removed the unreferenced `App\ValueObjects\FlightPlan`, empty `ProcessImageOcr` job, and placeholder unit test while retaining the feature smoke test. PHP 8.4 is now the single documented and Compose-backed Sail runtime; unused Docker 8.0, 8.1, 8.2, 8.3, and 8.5 definitions were removed. Every flight-plan task now has a test-confirmed dedicated Blade component, allowing the always-true `hasCustomView()` method and unreachable workspace fallback to be deleted. Compose validation, focused task/workspace tests, and Pint pass; Larastan remains reserved for task 19.

   Commit message: `chore: remove obsolete project files`

Constraints:
- Make each namespace/responsibility move an independently reviewable change with its imports and focused tests; do not combine all cleanup into one mechanical commit.
- Preserve serialized key compatibility until task 19 deliberately versions and removes that contract.
- Do not add interfaces or abstraction layers unless there are multiple implementations or a real application boundary.
- Run the narrowest affected PHPUnit tests for each batch, Pint after PHP changes, and reserve Larastan for the final integration checkpoint in task 19.

Completion outcome: each flight-release fact has one domain owner, the two aggregate-building paths share subdomain hydration rules, task presentation is split by domain, stable namespaces mirror those boundaries, and confirmed dead files/runtime definitions are removed without changing user-visible behavior.

Proposed commit sequence:
- `refactor: organize flight plan domain data`
- `refactor: split flight plan hydration and presentation`
- `chore: remove obsolete project files`

## 20. [x] Completed: Remove from overview: `Flight plan filing`
- Found in section: Operational support status

Outcome: Removed the unsupported `Flight plan filing` indicator from Operational support status while preserving the remaining GENDEC, Weather / RAIM, Maintenance, and conditional ETOPS statuses.

Commit message: `fix: remove flight plan filing overview status`

## 21. [x] Completed: Add B44 badge to Fuel Score in Task navigator
- So B44 flight plan status is visible in all tasks

Currently: B44 only shows on Overview view. When user navigates to another task, that potentially important info is no longer visible.

Goal: Add B44 badge to Fuel Score in Task navigator when a flight plan is filed under OPSPEC B44.

Implementation: Keep style the same as in the Route/FMS card found in the overview blade file. Consider creating a reusable card.

References:
app/Enums/OperationsSpecification.php
app/DTOs/ReleaseAuthorizationData.php
app/DTOs/FlightPlanData.php
resources/views/components/flight-release/overview.blade.php
resources/views/components/flight-release/task-navigator.blade.php

Outcome: Confirmed B044 releases now show the amber `B44` authorization badge on the Fuel Score task navigator item, keeping the authorization visible across every task. B043 and unknown releases render no navigator badge. The Overview route card and navigator reuse one Blade badge component so their wording and styling remain consistent.

Commit message: `feat: add B44 badge to fuel score navigation`

## 22. [x] Completed: Slot times badge
Currently: when slot times are not present, a warning badge shows. 
Problem: This is not necessarily a bad thing

Fix: Render a counter badge in caution yellow. If no slot times found, hide the task in the navigator and hide the approved slots card within the Schedule and slots card in the overview view.

Implementation:
- Make the counter badge reuseable

References:
app/View/Models/FlightReleasePageViewModel.php
resources/views/components/flight-release/task-navigator.blade.php

Outcome: Approved slot times now use a reusable amber counter badge in the task navigator. Releases without approved slots omit the Slot Times navigator task, the Approved slots overview metric, and the unavailable overview action/status affordances, while preserving the schedule card's ETD and ETA values. Follow-up fixture coverage accepts three-letter IATA slot formats such as `NRT 2115Z+-30` and `HKG DEP 2200Z (+/- 90 MINS)` without inventing a missing direction. The existing 12 MB upload limit remains unchanged. Focused parser, view-model, validation, and Livewire regressions pass after Pint.

Commit message: `feat: show approved slot count in task navigation`

## 23. [x] Completed: MEL CDL Badge
Currently: when no MEL / CDLs present, success green badge is rendered drawing a users attention
Problem: no need to draw a users attention
Fix: Remove availability badge, only showing count badge. Render count badge in green.

Outcome: The MEL / CDL task navigator entry now uses a single item-count badge: green when the count is zero and caution amber when one or more items require attention. Its separate availability dot is no longer rendered, while other task availability indicators and the amber approved-slot counter remain unchanged. Focused Livewire regressions pass after Pint.

Commit message: `fix: simplify MEL CDL task badge`

## 24. Update tests: Overview card: Auto-fit grid on operational support status
This allows the items to automatically expand and fill the available width, so you never have a "blank" spot regardless of how many items there are.

Implementation:
Change your grid container classes as follows:

html

{{-- Before --}}
<div class="grid grid-cols-1 gap-px bg-[#1B365D]/10 dark:bg-slate-700 sm:grid-cols-2 xl:grid-cols-4">

{{-- After (The Fix) --}}
<div class="grid grid-cols-1 gap-px bg-[#1B365D]/10 dark:bg-slate-700 sm:grid-cols-2 xl:grid-cols-[repeat(auto-fit,minmax(0,1fr))]">
Use code snippets with caution

References:
resources/views/components/flight-release/overview.blade.php
