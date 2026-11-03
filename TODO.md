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
7. Each task should consist of: Goal, Current implementation, Problem. 
8. Definitions:
   1. FP = Flight Plan more likely the flight plan brief tool
   
Optionally add references and constraints.

# Product and UI rules

- Use Aviation Blue for structure, Compass Gold for primary emphasis, and the existing light/dark theme tokens.
- Keep operational values compact and scannable; use monospaced text for codes, times, routes, coordinates, and numeric planning values.
- Label every time basis and fuel unit. Never silently mix UTC/local or pounds/kilograms.
- Distinguish `not present in this release` from `not supported yet`. Do not render zero, empty text, or a green status for missing data.
- Preserve source evidence internally for Weather, ETOPS, Fuel Score, and Weight & Balance.
- Reuse Blade components and view data; do not parse, normalize, query, or authorize inside Blade.
- Every interactive control needs keyboard access, visible focus, an accessible name, and a useful loading/empty/error state.

# Tasks
## Crew bidding

### Goal

Organize K4 Bids within the app and define a clean user-facing workspace for monthly package comparison and bid ranking.

### Current implementation

Planning completed (2026-10-26): Reorganized `CREW_BIDS.md` into an app placement, access, import, workspace, preferences, ranking/autosave, pay-preview, and delivery specification. Proposed a separate K4 Bids navigation item after Flight Plan Brief, using existing feature-policy conventions; a two-column browsing/bid-list workspace; progressive filters; mobile and keyboard behavior; and explicit missing-data/save states. Preserved the original feature scope while separating initial delivery from enhancements requiring verified definitions or source rules.

Inspected existing navigation, routes, feature configuration, access methods, and private sample filenames. Samples include multiple document types, fleets, and months; document contents and parsing compatibility remain unverified. The plan records package revision/owner isolation, import failure handling, and unresolved pay/export/source decisions. No application behavior or configuration changed; feature implementation remains pending.

### Problem addressed

The original notes mixed interface requests, example data, compensation assumptions, and advanced features without a clear user flow or implementation boundary. `CREW_BIDS.md` now provides the single implementation reference instead of duplicating its plan here.

Validation: Documentation reviewed against the existing app structure and original feature notes. No tests, Pint, or Larastan were run for this documentation-only task.

Planning clarification (2026-10-26): Confirmed admin-imported shared packages with private user preferences/bid lists; B747 and B777 Captain/FO support in the first usable release; and automatic exclusion of available lines conflicting with selected days off. Already selected choices stay in place with conflict flags and user-controlled removal. Users order several goals by strict priority, with lower goals only breaking ties. All work intervals are converted to UTC calendar dates; any work on a selected UTC date, including reserve, training, deadheads, and carry-in/out, counts as a conflict. A long work block means grouping duty days together with fewer separate blocks. Updated `CREW_BIDS.md` accordingly; source time bases and exact block-counting boundaries still need discovery. Documentation-only; no runtime checks required.

First-release scope confirmed (2026-10-26): Import Lines and Trips PDFs plus Overview spreadsheets; defer other document types. Deliver a numbered bid list for review/copy. Defer estimated pay, keeping credit, days off, preferences, and ranking in the first release. Updated the import, tab, output, and delivery sections in `CREW_BIDS.md`; dedicated Training UI and spreadsheet export are deferred. Documentation diff checks passed.

Occupancy and browsing clarified (2026-10-26): Every UTC date from trip report through release counts as occupied, including full layover/rest days, for days-off conflicts and work-block grouping. Eligible users can browse both fleets/seats. A selected start city excludes nonmatching lines. Recorded these decisions in `CREW_BIDS.md`, distinguishing occupied dates from source duty activity and leaving timestamp endpoints/month-boundary counting for discovery. Documentation diff checks passed.

Revision and list behavior confirmed (2026-10-26): New package revisions offer a change review and require user confirmation before carrying choices forward. Lines with incomplete occupied dates remain visible under days-off filters with `Dates incomplete; conflicts unknown`, without claiming a conflict-free match; confirmed conflicts still exclude them. Flying and reserve choices share one ranking within the same month/fleet/seat/revision. Updated behavior and acceptance criteria in `CREW_BIDS.md`. Documentation diff checks passed; no application code changed.

UI direction confirmed (2026-10-26): Compact rows with monthly timelines and expandable details are the default. A `My bid list` button opens a drawer on desktop, replacing the initially proposed permanent side panel. Preferences use guided setup when opening a package, followed by an editable summary. Updated the workspace sketch and interaction descriptions in `CREW_BIDS.md`; mobile presentation and returning-user setup behavior remain open. Documentation diff checks passed.

Mobile and comparison UX confirmed (2026-10-26): Keep the full month visible on phones using small day markers with tap-to-inspect details. Select UTC days off by individual taps with an optional range selector. Allow multiple expanded line rows for comparison. Updated `CREW_BIDS.md` with these choices and accessible interaction requirements. Documentation diff checks passed; no application code changed.

Setup and selection UX confirmed (2026-10-26): Returning users open results directly with saved preferences and an Edit button; first-visit guided setup is skippable. Adding a line appends it, shows its rank and a brief Undo action, and keeps browsing open. On phones, the bid list uses a full-screen panel with `Back to lines` and restored browsing context. Updated `CREW_BIDS.md`; documentation diff checks passed.

Live filtering and information density confirmed (2026-10-26): Preference/filter edits refresh results immediately with a brief loading indicator and no Apply step. Compact timelines distinguish occupied days from days off, with activity types on date inspection and unknown dates kept distinct. Bid-list entries show rank, Line ID, credit, days off, and conflict/uncertainty warnings. Updated `CREW_BIDS.md`; documentation diff checks passed. No application code or runtime checks changed.

Decluttering review completed (2026-10-26): Reduced the planned default row to Line ID, credit, days off, timeline, and Add/selected rank, retaining actionable warnings. Moved duty/city/trip/crew detail, match explanations, package statistics, revision metadata, secondary sorts, and bulk actions behind contextual details/menus. Removed first-release placeholders for deferred features, duplicate filter/calendar summaries, and competing floating actions. Made empty/import states role-aware and preserved visible uncertainty/conflict/save-failure feedback. Updated the workspace sketch and acceptance criteria in `CREW_BIDS.md`. This refines the documentation only; diff checks passed and no tests, Pint, or Larastan were needed.

User workflow expanded (2026-10-26): Added a dedicated five-stage workflow in `CREW_BIDS.md`, defining admin import/publication, crew package selection, skippable preference setup, compact comparison and shortlist building, manual ranking, and saved review/copy. Each stage describes actions and its outcome, with return visits, revision confirmation, warnings, and save recovery included. Kept the stages as a user journey rather than extra permanent UI navigation. Documentation-only; diff checks passed and no runtime checks were required.

Commit message: `docs: define the K4 Bids user workflow from import to copy`

## Unified upload
Currently: 2 tabs have 2 different upload points, user has to choose 
Goal: Have one unified upload path. Service will determine if a schedule or flight plan has been uploaded. 

Cached results: Keep extract schedule and flight plan brief tabs for now. There's not really a better way to render cached results for now.

## Schedule: Max file (screenshot) upload to config
Have setting in .env file as opposed to hard coded.
Currently:
max:5 set in validation
message validation: 'files.max' => 'You may upload up to five images.',

Goal:


References:
.env.example
app/Validation/ExtractValidationRules.php

## Plan: PEST architechure tests

### Goal

Add fast architecture tests for established naming conventions and dependency boundaries. Keep the existing PHPUnit unit and feature classes, and catch structural regressions without booting Laravel, accessing the database, or making network requests.

### Current implementation

- `composer.json` requires PHPUnit `^12.5.12`; the installed version is 12.5.34. Pest and its architecture plugin are not installed. The existing Composer allowance for `pestphp/pest-plugin` permits plugin execution but does not install Pest.
- `phpunit.xml` discovers `tests/Unit` and `tests/Feature`. CI runs `php artisan test --compact --parallel`; the installed Collision test command switches to Pest when Pest is available. Sail already supports `vendor/bin/sail pest`.
- The application has Actions, DTOs, Enums, Mappers, ValueObjects, domain services, infrastructure services, and View Models/Presenters. Existing model-convention and Eloquent-guardrail tests verify runtime behavior; they do not enforce namespace dependencies.
- Naming has intentional variations: Actions expose `handle()`, DTOs include `Flight`, `DutyEvent`, `AirportResolution`, and the abstract `ExtractedEventDTO`; View Models include a factory and `FlightPlanPageData`. DTOs are not uniformly final/readonly, while current ValueObjects are final/readonly.

### Problem

Naming and layer boundaries can drift without a test failure. A blanket preset would impose conventions the project does not follow, and overly broad dependency bans would reject legitimate PDF/OCR, airport lookup, and presentation behavior.

`AGENTS.md` currently says all tests must be PHPUnit classes and Pest tests must be converted. Its dependency rule also requires approval before adding packages. This planning task does not authorize installation or change those rules.

### Planning outcome: installation and coexistence

- **Does Pest need to be installed?** Yes, to use Pest's `arch()` API. For this PHPUnit 12 project, evaluate `pestphp/pest:^4`; its architecture plugin is included as a dependency, so a separate architecture-plugin requirement is unnecessary. Generic installation instructions now describe Pest 5; do not adopt that major without reviewing its PHPUnit and PHP requirements. [Pest installation](https://pestphp.com/docs/installation), [Pest 4 dependency metadata](https://github.com/pestphp/pest/blob/4.x/composer.json).
- **Can it run alongside the existing suite?** Yes. Pest builds on PHPUnit and can run existing PHPUnit classes; conversion is unnecessary. Use Pest for architecture tests and retain current behavioral test classes. Verify discovery and the existing parallel runner after installation. Plain PHPUnit is not the runner for Pest `arch()` files. [PHPUnit migration guide](https://pestphp.com/docs/migrating-from-phpunit-guide).
- **Version resolution needs review.** The inspected Pest 4 branch restricts its PHPUnit patch version to 12.5.33, below the installed 12.5.34. Released package constraints may differ. Inspect a Composer dry run and review any proposed downgrade or other package changes before installation; PHP/major-version compatibility alone is insufficient.
- **Prerequisite for implementation:** explicitly authorize the development dependency and a narrow exception permitting Pest architecture tests, while retaining PHPUnit class tests elsewhere. If retaining the current rules is preferred, use PHPUnit classes for these rules instead; naming checks can use reflection, while dependency checks need reliable source analysis. Pest is optional for the architectural goal.

### Proposed implementation

1. Confirm Sail is available. After the prerequisite decisions, resolve a compatible Pest 4 release through Sail with a Composer dry run and review the dependency delta. Preserve the PHPUnit requirement where compatible. Initialize only the necessary Pest configuration; do not convert existing tests or install Drift, browser, or Livewire plugins for static architecture checks.
2. Put `NamingTest.php` and `LayeringTest.php` under `tests/Unit/Architecture`, which the existing Unit suite already discovers. Keep architecture tests independent of `Tests\TestCase`, `RefreshDatabase`, and global Laravel hooks. Check any generated `tests/Pest.php` configuration so it does not rebind existing tests or boot Laravel for architecture files.
3. Audit each proposed rule against the current source, then implement the naming and dependency rules below. Use recursive namespace discovery so new classes are covered automatically. Keep exceptions limited to exact classes with a stated reason; report unexpected violations before deciding on production refactors.
4. Verify source analysis detects actual dependencies, including fully qualified references, type declarations, inheritance, and trait use; avoid import-only regex checks. Static class rules do not prove absence of dynamic container lookups, runtime I/O, or logic inside Blade. Keep behavioral tests and Larastan responsible for their existing concerns.
5. Confirm architecture files are included by the normal runner and existing CI command. Use the focused architecture command locally; avoid a second CI invocation that runs the same architecture tests twice. Update this entry with implementation outcomes and validation counts.

### Initial rules

| Scope | Rule | Existing allowances |
| --- | --- | --- |
| Application declarations | Namespace and declaration names match PSR-4 paths and case. Enums are enums; Models extend Eloquent Model; Controllers extend the application Controller except the base Controller itself. | Reuse current Laravel/Filament structure. |
| Names | Requests end in `Request`, Policies in `Policy`, Mappers in `Mapper`, and Flight Release Presenters in `Presenter`. Actions expose public `handle()`. ValueObjects remain final/readonly. | Policy concern traits are excluded from the class suffix rule. No universal DTO/View Model suffix, Action suffix, `__invoke()`, or application-wide final/readonly rule. |
| DTOs, ValueObjects, Enums | No dependencies on Actions, application Models, Services, Http, Livewire, Filament, or View namespaces. | Allow other data types, enums, exceptions, PHP interfaces, Carbon, and Laravel support utilities already in use. |
| Domain parsing/services, Mappers, Infrastructure | No dependency on Http Controllers/Requests, Livewire, Filament, or View classes. | Services may use Models, lookup clients, cache, logging, PDF/OCR, and framework contracts. Do not ban all framework dependencies. |
| Flight Plan parsing | Preserve the direction toward data types and extraction helpers; prevent presentation dependencies. | `FlightCrewExtractor` legitimately uses `Schedule\Extractor\CrewListParser`. PDF/text and route extractors have legitimate I/O/cache/lookup dependencies. Do not assume every extractor is pure. |
| View Models and Presenters | No dependency on Models, extraction Actions, Infrastructure, lookup clients, or document extractors. | Allow DTOs, ValueObjects, Enums, presentation helpers, and the existing `RoutePresenter` dependency on `FlightTypeClassifier`. Actions may build View Models, as `BuildFlightPlanPageData` already does. |

Use targeted expectations rather than blanket presets, strict-types requirements, line-count limits, or an invented repository layer. [Architecture expectations](https://pestphp.com/docs/arch-testing).

### Acceptance and validation for implementation

- Naming and layering violations identify the offending class and rule. Newly added classes are discovered, and a rule fails if its intended scope unexpectedly matches no declarations.
- Demonstrate failure for a deliberate naming violation and a forbidden dependency, then restore the valid source. Include a fully qualified dependency case to catch checks that only inspect imports. Keep any checker fixtures within the test suite and outside application discovery.
- Run `vendor/bin/sail pest --compact tests/Unit/Architecture`, then focused existing PHPUnit files through `vendor/bin/sail artisan test --compact`, including a DTO test and an existing Livewire lifecycle test. Verify the normal runner discovers the architecture files and review the parallel runner without running the entire suite at this checkpoint.
- Run Pint after PHP changes and Larastan once at final integration. Record results here. No packages, test files, configuration, or application code changed during planning; executable validation remains for implementation.

Planning commit message: `docs: plan Pest architecture tests and adoption constraints`

Implementation commit message: `test: enforce application naming and layer boundaries`

## Plan: Flight plan: Reserve fuel
### Goal

Show alternate-airport burn and calculated FMS reserve fuel as distinct values. Resolve the reserve additive from the aircraft fleet record, with explicit support for 747-400F, 777-F, and 777-300ERSF. Keep source fuel quantities separate from the derived calculation and label every unit.

### Current implementation

- `FlightFuelExtractor` already extracts `ALTN` into `alternate` and `RESERVE` into `final_reserve`. `FuelPlanData` preserves these as separate `FuelQuantity` values; `BuildFlightPlanData` and `BuildFlightPlanPageData` retain them through serialization and saved-result loading.
- `FuelPresenter::alternateReserve()` returns only `fuelPlan.alternate`. `FlightReleasePageViewModel::fmsFields()` labels that value `Alternate Airport Reserves`, so alternate burn is presented as reserve fuel without a calculation. Fuel Score already lists the source Alternate and Reserve separately.
- The `aircraft` table stores tail number, type, model, and weight limits, but has no reserve additive. `AircraftWeightLimitResolver` provides an existing pattern for resolving aircraft data by normalized tail number during extraction.
- `AircraftWeightSeeder` imports 74Y, 77V, and 77X aircraft as 747-400F, 777-300ERSF, and 777-F. The connected development database currently contains 22, 7, and 8 records respectively. The original task notes that the full fleet is implemented in production; production coverage was not independently verified. `AircraftSeeder` alone covers only the two 777 models.
- `AircraftFactory` chooses type and model independently and only generates 777 aircraft. It needs consistent, explicit fleet states for this calculation's tests.

### Problem

The FMS label conflates alternate burn with reserve fuel. There is no stored aircraft additive or confirmed calculation contract, and current fixtures cannot reliably distinguish the three fleet models. A guessed formula, missing additive treated as zero, or mixed pounds/kilograms could produce a misleading operational value.

### Calculation decisions needed before implementation

- Confirm the exact FMS reserve formula: which of alternate burn:
  - alternate + finalReserve
- Supply approved additive values and units for each model; 
  - the two 777 variants share a value, while 747 differs
  - 777 = 8,000lbs additive
  - 747 = ?
- Define the no-alternate case: whether the confirmed formula permits calculation without alternate burn. A missing value is not an explicit zero.
- Confirm calculation/display rounding. Recommended storage is whole pounds per aircraft, conversion through `FuelQuantity` when the release uses kilograms, and rounding only for display; adjust storage precision if the supplied values require it.

### Implementation plan

1. Add a new reversible migration for a nullable `aircraft.reserve_fuel_additive_lb` column, subject to the confirmed units/precision. Use no zero default. Add the fillable attribute and integer cast to `Aircraft`; keep existing migrations unchanged. Expose an optional, non-negative integer field with an `lb` suffix in the existing Aircraft admin form and a clearly labeled table column.
2. Populate approved fleet values through an idempotent, narrowly scoped seeder, separate from the schema migration. Match the established model names, leave unsupported models unavailable, and avoid overwriting configured tail-specific values on reruns. Verify production fleet coverage and populate additives before expecting complete reserve calculations. Keep 747 fleet import work outside this task unless records are actually missing.
3. Add an aircraft reserve-additive resolver following the normalized-tail lookup pattern and a small calculation service that consumes typed source quantities plus the resolved additive. Resolve during `HandleFlightPlanExtraction`, pass the result into the builder, and keep database queries and arithmetic out of Blade and presenters. Convert all required inputs into one unit before applying the confirmed formula; preserve explicit zero and return unavailable when a required input or additive is missing or invalid.
4. Extend normalized fuel data with separate additive and calculated FMS reserve fields, retaining `alternate` and `finalReserve` as source values. Serialize the additive used and the calculated result with the saved release so later fleet edits do not silently change an existing brief. Update DTO array shapes, builders, and saved-result tests. Older payloads without the new fields should still load, with calculated reserve unavailable until re-upload.
5. Replace the ambiguous FMS field with distinct `Alternate airport burn` and `FMS reserve fuel` values. Keep source `RESERVE` labeled as `Release reserve` wherever needed to distinguish it from the derived value. Update the FMS section's source-only heading/evidence text to acknowledge the calculation, and distinguish missing release data from unavailable fleet configuration. Reuse the existing metric components and compact layout.
6. Add deterministic factory states for all three models with matching type/model values. Cover resolution, the approved formula, normalized tails, missing/unknown aircraft, unconfigured additives, explicit zero, invalid values, unit conversion, and the approved no-alternate behavior. Verify that calculated values survive save/reload and that older saved releases retain honest unavailable states.

### Validation for implementation

Run only affected PHPUnit tests through Sail: the new resolver/calculator tests, affected DTO/builders/serializer tests, `AircraftResourceTest`, additive-seeder tests, FMS view-model tests, and `tests/Feature/Livewire/FlightPlanBrief/Tasks/FmsTest.php`. Run the extractor test if its source-field contract changes. After PHP edits, run `vendor/bin/sail bin pint --dirty --format agent`, then Larastan once at the final integration checkpoint. Check the FMS display in both themes and the production Vite build if frontend assets change. Record actual outcomes here when implemented.

### Planning outcome

Traced the source-to-FMS data flow, confirmed Sail is running and the connected development database contains all three fleet models, and identified the misleading FMS label and missing additive storage. This is a documentation-only plan; application code and production data were not changed. Formula, additive values, no-alternate behavior, and rounding remain open business inputs.

Commit message for implementation: `feat: distinguish alternate burn from calculated FMS reserve fuel`

Commit message for this plan: `docs: plan aircraft-specific flight plan reserve fuel`

-------------------------------------------------------

## [ ] Codebase: Improve parsing logic and application boundaries

### Goal

Improve correctness at schedule merge, duty-calendar, and extraction lifecycle boundaries; make saved-result reconstruction and page rendering easier to maintain. Preserve source evidence, explicit UTC/local and fuel units, owner isolation, and existing successful-upload behavior.

### Current implementation

The application already separates extractors, normalization builders, DTOs, infrastructure services, and presenters. Schedule uploads support partial success and cached results; flight-plan results are encrypted and restored through `BuildFlightPlanPageData`. This review found specific weaknesses within those boundaries rather than a need to replace the architecture.

### Problem

Findings below are ordered by impact. They are static source observations; operational impact and fixes still need focused regression coverage.

1. **High — Make duty-calendar date handling strict and aware of year boundaries.** In [FlightDutyEvent.php](app/Services/Calendar/FlightDutyEvent.php), `parseLocalTime()` assigns each yearless local timestamp the corresponding UTC reference year, calls `createFromFormat()` without handling invalid local input, and does not check a format round trip. `shiftForwardAfter()` then adds days without a bound. A flight near January 1 whose local date is December 31 can acquire a wrong-year duty offset; malformed local text can throw instead of returning the method's unavailable result. Resolve local dates against nearby year candidates and the flight context, reject invalid dates, and bound any OCR day correction while preserving the supported previous-day OCR correction. Cover December/January in both directions, malformed/impossible local dates, ordinary midnight crossings, and implausible duty intervals in `FlightDutyEventTest` and the duty-export feature test.

2. **High — Define event identity independently of the full parsed payload.** [JcaScheduleProcessor.php](app/Services/Schedule/JcaScheduleProcessor.php), `mergeParsedResults()`, hashes `serialize($eventData)` for deduplication and retains non-event result data from the first source. The same flight in overlapping screenshots remains duplicated when raw lines, optional metadata, or array key order differ; conflicting data has no explicit reconciliation rule. Extract a focused merger with identity rules per event type, retain source provenance, and surface incompatible time/route values instead of silently overwriting them. Extend the existing exact-duplicate screenshot test to cover metadata differences, conflicting observations, unrelated events sharing a timestamp, and reversed upload order. Keep legitimate separate events distinct.

3. **High — Separate safe upload feedback from exception diagnostics.** [JcaScheduleProcessor.php](app/Services/Schedule/JcaScheduleProcessor.php), `fileFailure()`/`displayedFileFailure()`, passes arbitrary exception messages into `ScheduleExtractor::$failedFiles` and the results modal. Internal paths, parser details, or document fragments can therefore become user-visible when an unexpected exception contains them. Map expected failures to useful public messages/codes and retain unexpected diagnostics in server reporting with request/file-position context. Cover an exception containing a private path or document fragment and verify it is absent from Livewire state and rendered feedback while successful files remain available.

4. **Medium — Make extraction outcomes reflect partial success and persistence failures.** [HandleExtractExecution.php](app/Actions/Extract/HandleExtractExecution.php) ignores returned `failed_files` when completing the request; [ExtractRequestLogger.php](app/Services/Infrastructure/ExtractRequestLogger.php), `complete()`, always records `success`. Separately, [HandleFlightPlanExtraction.php](app/Actions/FlightPlan/HandleFlightPlanExtraction.php) marks success before [FlightPlanBrief.php](app/Livewire/FlightPlanBrief.php) saves the encrypted result. A multi-image partial failure appears fully successful, and a failed result save can leave a successful extraction record despite failed user delivery. Define processing versus delivery outcomes explicitly, retain partial-file counts without storing document text, and place lifecycle ownership at the action that coordinates the relevant persistence boundary. Cover partial success, all-file failure, result-save failure, and retention of the previous brief. Decide whether existing `partial` status denotes in-progress work or a terminal partial success before reusing it.

5. **Medium — Batch uncached airport lookups with bounded concurrency.** [AirportResolver.php](app/Services/Schedule/AirportResolver.php), `resolveMany()`, deduplicates codes but performs every cache miss synchronously in a loop. Each code has its own connection/read timeout and retries, so latency accumulates across a roster. [AirportLookupClient.php](app/Services/Clients/AirportLookupClient.php) already pools ICAO requests, while the schedule path supports both IATA and ICAO. Extend the existing client boundary to batch both code types with a concurrency limit, resolve cache hits first, and preserve per-code found/missing/unavailable outcomes and their current TTLs. Extend `AirportResolverTest` and client coverage for mixed codes, cached hits, one provider failure, and bounded batch size; use deterministic HTTP fakes rather than wall-clock timing assertions.

6. **Medium — Give persisted flight-plan hydration an explicit home and compatibility contract.** [FlightPlanResultSerializer.php](app/Services/FlightPlan/FlightPlanResultSerializer.php) only writes the payload wrapper; [BuildFlightPlanPageData.php](app/Actions/FlightPlan/BuildFlightPlanPageData.php) also reconstructs the full domain DTO, handles legacy aliases, and repeats field mapping present in [BuildFlightPlanData.php](app/Actions/FlightPlan/BuildFlightPlanData.php). New fields require coordinated changes across extraction, serialization, and page reconstruction. Move persisted-payload hydration into the existing serialization/domain boundary, reuse the established section builders, and keep page-data assembly focused on presentation input. Add an explicit schema version and supported legacy behavior if the persisted format evolves. Verify fresh-extraction/save/reload parity, legacy `envelope` handling, unknown preliminary status, malformed optional sections, and retention of valid source quantities/evidence. Do not collapse the different validation needs of extracted and persisted data into one permissive mapper.

7. **Low — Reuse the current brief and view model within one Livewire request.** [FlightPlanBrief.php](app/Livewire/FlightPlanBrief.php), `hydrate()`, task validation, and `render()` can independently load the same encrypted payload; `currentViewModel()` rebuilds the DTO and presenters on each call. Reuse one result/model per request, with explicit invalidation after upload, clear, or result-key changes. Preserve cross-request stale-tab checks and authorization. Use existing navigation/lifecycle tests to verify the rendered model changes immediately after replacement/clear and stale keys still redirect; add a focused dependency call-count assertion where repeated reconstruction is actually exercised. Keep the existing presenter boundaries rather than introducing a second presentation system.

### Review outcome (2026-10-26)

- Reviewed schedule input/OCR, multi-source processing, airport enrichment, extraction logging, flight-plan normalization/storage/restoration, Livewire lifecycle, calendar exports, and representative related tests and offline-fuel JavaScript. This was a targeted static review, not an exhaustive security audit or runtime performance measurement.
- Recorded seven improvement areas with concrete source references and regression expectations. No application code, dependencies, schema, or tests changed. Existing OCR configuration and architecture-test tasks remain separate; this task does not authorize dependency installation.
- `.ai/rules` is absent. Applied `AGENTS.md` and the Laravel best practices skill. Documentation-only scope uses the Sail prerequisite exception; tests, Pint, and Larastan were not run. `git diff --check -- TODO.md` passed.
- Preserved pre-existing changes in `TODO.md`, `CREW_BIDS.md`, and `.nvmrc`. Implementation remains pending; this review completes the requested documentation only.

Commit message for this review: `docs: record codebase logic and organization improvements`

# Completed Tasks

-------------------------------------------------------

## [x] Completed: Schedule: Cleanup Schedule Notes
## [x] Completed: Schedule: Flight radar option
## Completed: Security email
## [x] Completed: Diagnose PDF OCR failures reported after the server migration
## [x] Completed: Include bug email if an error occurs on upload
## [x] Completed: Replace Imagick PDF page rendering with Poppler
## B. Make OCR resolution configurable
## Don't force PSM 6 for every document
## [x] Completed: FP: Envelope: Include flight date in task
## [x] Completed: FP: Delay reporting
## [x] Completed: FP: Prelim flight plans

### Goal

Detect explicitly preliminary flight plans, omit their weight/fuel presentation from Overview, and qualify planning values in dedicated tasks while preserving source values, units, and the document warning. An absent marker does not establish finality or operational approval.

### Current implementation

- Added the focused `ReleaseStatusExtractor`, `ReleaseStatus` enum, and readonly `ReleaseStatusData`. Detection requires the complete supported header warning at a line/asterisk boundary, tolerating case, whitespace/wrapping, and ordinary dash variants, including the supplied PDF's fused asterisk/header layout. The combined native/OCR text is searched; no page provenance is invented. Incidental preliminary prose, partial warnings, filenames, revisions, final-destination headings, and B043/B044 text do not establish preliminary/final status.
- The first matched warning fragment and status pass through parsed/normalized DTOs, builders, serialization, encrypted result storage, and saved-result rebuilding under `flight_plan_data.releaseStatus`. Duplicate headers do not accumulate document text. Missing/malformed metadata and preliminary metadata without a nonempty string warning load as `Unknown` without losing the brief. A completed detection without the supported marker yields `NotMarkedPreliminary`, with no final/green status badge.
- Added a shared `Preliminary flight plan` warning with `Payload estimated — Do not fuel aircraft` and compact section qualifiers for route, FMS, ETOPS, fuel, planned weights, and release context. Unknown results show a neutral message recommending re-upload. Display copy is derived separately; raw matched evidence stays out of Livewire properties, snapshots, and calculator JSON.
- Preliminary Overview omits Ramp Fuel and Weight & Balance alert cards, their values and accessible labels, and fuel/weight navigator indicators in both desktop and mobile markup. Task links remain available. Route and present ETOPS summaries retain preliminary labels. Unknown and unmarked results preserve existing Overview behavior.
- Dedicated tasks retain source values/units, missing-data labels, conflicts, applicability/availability, copying, and structural-limit alerts. Weight comparisons are qualified as preliminary while aircraft structural limits remain reference values. Ambiguous release-value headings now use source wording. Actual FOB inputs remain actual observations.
- Offline Fuel Score receives only derived status/warning display data. Its prepared HTML retains the warning; the page cache is now `pages-v2`, and activation removes obsolete prepared pages. Updated pages require online re-preparation. Existing owner/result-key isolation, calculator arithmetic, draft version, and draft source identity remain unchanged. An already offline browser receives the worker update only after reconnecting.
- Successful replacement uploads replace status and values together; failed replacements retain the previous result/status. Legacy results require re-upload for classification because their source PDF is no longer retained. No dependency, migration, backfill, or additional persistent model was added. The private PDF and crew details were not committed.

### Problem addressed

Previously preliminary planning figures could appear with the same presentation as another parsed release, and no preliminary metadata survived refresh. Explicit document evidence now persists independently of source-value agreement, task availability, ETOPS applicability, and B44 authorization.

### Validation outcome (2026-10-26)

- Elevated Sail access confirmed running application, MySQL, and Redis services. Installed versions included Laravel 13.34.0, Livewire 4.4.8, PHPUnit 12.5.37, and Smalot PDF Parser 2.12.5. Boost documentation transport closed; official Laravel/Livewire testing and Blade documentation were used as fallback. `.ai/rules` is absent.
- Main focused integration run: **199 passed, 2 skipped, 2,214 assertions**. The supplied private preliminary PDF regression passed; the two skips were existing unavailable private fixtures. Coverage includes header variants/negatives, appended and cached OCR text, encrypted extraction/save/reload, raw-evidence privacy, every direct task route, failed/successful replacement, pounds/kilograms, legacy metadata, preserved missing/conflicting values, and exceeded-limit alerts. The full suite was not run.
- Visual review found the remaining sidebar weight count on preliminary Overview. After suppressing fuel/weight navigator indicators, the preliminary feature and navigation tests passed in the follow-up run. The corrected final `OverviewTest` run passed **5 tests, 199 assertions**, checking the full rendered markup and accessible labels rather than visible text alone. Dedicated tasks retain their counters and B44 badge.
- `vendor/bin/sail node --test tests/JavaScript/offline-fuel-worker.test.js tests/JavaScript/offline-fuel-draft.test.js`: **27 passed**, including obsolete-cache removal, reconnect fallback, warning recovery after preparation/restart, and actual-input draft recovery.
- `vendor/bin/sail bin pint --dirty --format agent`: passed after PHP/test changes. One `vendor/bin/sail bin phpstan analyse --memory-limit=1G --no-progress` application run passed with zero errors. `vendor/bin/sail npm run build` passed. Task-scoped staged/unstaged `git diff --check` passed; unrelated concurrent CREW_BIDS.md whitespace was left untouched.
- Reviewed actual Blade components and production CSS in headless Chrome using synthetic, nonpersistent data: light/narrow Overview, dark/desktop Overview, dark/narrow Weight & Balance, and the corrected desktop navigator. Warning text and section qualifiers were readable, cards/indicators were omitted on Overview, and the dedicated exceeded-limit warning/reference limit remained visible. Reviewed widths were 500 and 1,440 pixels. Live authenticated interaction, physical-device offline reload, and actual scanner OCR were not manually exercised; offline/OCR contracts have deterministic automated coverage.
- Initial concurrent feature processes collided on the existing file-backed SQLite test database. Subsequent integration runs used one sequential process with an isolated in-memory database. The malformed disposable test file was preserved under `/tmp`, reset, and restored by `vendor/bin/sail artisan test --compact tests/Feature/FlightPlanResultStoreTest.php --filter=test_it_persists_an_encrypted_result_with_an_opaque_key` (1 passed, 4 assertions); SQLite integrity_check then returned `ok`. The application database was not migrated.

The main integration command was:

```bash
vendor/bin/sail exec -T -e DB_DATABASE=:memory: -e DB_CONNECTION=sqlite laravel.test php artisan test --compact \
  tests/Unit/ReleaseStatusExtractorTest.php \
  tests/Unit/DTOs/ReleaseStatusDataTest.php \
  tests/Unit/ExtractFlightPlanDataTest.php \
  tests/Unit/FlightPlanTextExtractorTest.php \
  tests/Unit/DTOs/FlightPlanDataTest.php \
  tests/Unit/Actions/FlightPlan/BuildFlightPlanDataTest.php \
  tests/Unit/Actions/FlightPlan/BuildFlightPlanPageDataTest.php \
  tests/Unit/FlightPlanResultSerializerTest.php \
  tests/Unit/View/Models/FlightReleasePageViewModelTest.php \
  tests/Feature/PreliminaryFlightPlanTest.php \
  tests/Feature/FlightPlanResultStoreTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Lifecycle/ExtractionTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Lifecycle/SavedResultTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Workspace/OverviewTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/FmsTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/EtopsTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/WeightAndBalanceTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/EnvelopeTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/FlightInitTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/MaintenanceLogTest.php \
  tests/Feature/OfflineFuelScoreTest.php
```

Unrelated working-tree changes and concurrent staging were preserved.

Commit message: `feat: identify preliminary flight plans and label planning data`

## [x] Completed: FP: Remove page labels before field extraction

Goal: Remove printed page headers and footers before extracting any flight-plan fields.

Problem: Page-label removal was local to route, waypoint, maintenance-description, and dispatcher-note handling. The shared PDF text reader and extraction coordinator still passed printed labels into identity, fuel, and other field extractors. Weather also used the printed footer as a section boundary.

Current implementation: Added a shared text normalizer for branded, standalone, repeated, fused, and wrapped page labels. PDF text is normalized after native/OCR assembly and cache retrieval, including the uncached/document-text fallback. The coordinator normalizes direct text input before invoking any field extractor. Route, waypoint, and weather entrypoints use the same normalizer. Labels become form-feed page breaks so weather remains bounded; preliminary-warning and alternate-heading detection recognize those boundaries. Existing operational headers and route identifiers remain content. Existing text-cache entries are cleaned on read without requiring another OCR pass.

Regression coverage checks native/OCR/cached input, missing cache keys, label formats, idempotence, every coordinator dependency receiving cleaned text, and upload/save recovery of identity, fuel, primary waypoints, and bounded weather.

Verification (2026-11-01): The final focused Sail integration run passed 128 tests (780 assertions); one existing unavailable private route fixture was skipped. The supplied RJAA PDF regression passed. An earlier run hit the existing 30-second OCR timeout on page 93; the final run passed without changing OCR limits. Pint passed after formatting the coordinator test, and one Larastan run passed with zero errors. Staged and unstaged whitespace checks passed. No full suite or manual browser review was run.

Commit message: `fix: normalize flight plan page labels before field extraction`

## [x] Completed: FP: Fuel Score: Exclude alternate routing

Goal: Extract and present only departure-to-destination waypoints in Fuel Score. Alternate routes, their waypoints, and their TOC/TOD markers remain unsupported.

Problem: In `CKS024231RJAA.pdf`, page 4/5 footers separate the two primary computed-flight-plan header rows. The parser skipped that header and selected the complete header in the alternate section, presenting the VHHH-to-VMMC alternate as the primary route.

Current implementation: Remove page labels before matching waypoint headers and bound extraction before the alternate section before searching for a table. Missing or incomplete primary headers yield no waypoints instead of falling back to an alternate table. Alternate rows and phase markers are excluded from extracted waypoints, source fragments, saved results produced by new extraction, and Fuel Score calculator data. Existing alternate-airport identification and fuel figures retain their separate meanings.

Added a compact route-table fixture, header and alternate-heading regressions, a saved-calculator rendering regression, and an optional regression using the supplied private PDF. The private PDF and crew details are not included in the changes.

Verification (2026-11-01): All 33 focused waypoint-extractor and offline Fuel Score tests passed through elevated Sail access (251 assertions), including the supplied PDF. The regressions reproduced the alternate-table fallback before the fix. Primary waypoints begin with `35464N`, `ARIES`, and `A4L11`, end with `LIMES` and `VHHH`, and retain destination duration 04:12 and planned FOB 19,800 lb. Alternate waypoints and phase markers stay excluded. Pint passed; Larastan passed once with zero errors; `git diff --check` passed. No full suite or manual browser review was run.

Existing saved briefs and prepared offline calculator pages require re-uploading the PDF and preparing offline recovery again to replace previously extracted alternate waypoints; stored results lack the source section needed for safe automatic repair.

Commit message: `fix: exclude alternate routes from primary fuel score waypoints`

## [x] Completed: Preserve iPad multipart uploads outside the service worker

Goal: Allow flight release uploads from Chrome on iPad while retaining offline fuel calculator recovery and private-cache invalidation.

Problem: Production logs show one selected file followed by a multipart request with no parsed files on iPadOS 26.6 / Chrome 156.0.8078.54, from both On My iPad and company OneDrive. Mac Chrome succeeds against the same FPM settings. The worker reissued every same-origin request through fetch(event.request), matching the multipart body-loss path reported in [WebKit bug 319396, comment 7](https://bugs.webkit.org/show_bug.cgi?id=319396#c7). This is a likely trigger, pending confirmation on the affected iPad.

Current implementation: Multipart requests return from the worker's fetch listener without respondWith or fetch, leaving transmission to the browser. Other requests retain existing behavior, including JSON responses that invalidate cached private data after logout, account changes, release replacement, or clearing results. No PHP, parser, upload limit, dependency, or Vite bundle changes.

Verification: The new upload bypass regression failed before the fix. All 26 focused worker and offline-recovery JavaScript tests passed through Sail after the fix, including multipart headers with boundaries and mixed casing, offline recovery, and JSON mutation invalidation. Git diff whitespace checks passed. Pint and Larastan were not needed for this JavaScript-only change.

Production follow-up: Deploy the updated public/offline-fuel-worker.js through the normal release process. On the iPad, open the fuel calculator online to trigger worker registration/update, allow the update to activate, then reload Flight Plan Brief and select the PDF again. Existing skipWaiting/clients.claim activation is retained. Confirm a successful temporary-upload response with one file and one returned path, followed by completion_received. No production deployment or physical-iPad verification was performed here; if the failure persists with the updated worker active, investigate the broader WebKit file-body issue before changing upload limits.

Commit message: `fix: bypass service worker for multipart uploads`

## [x] Completed: Reject empty Livewire uploads and add safe diagnostics

Outcome: Reproduced Livewire 4.4.8 accepting missing/empty temporary-upload collections and returning empty paths, which its single-file completion handler indexes without checking. Added application middleware requiring a nonempty file list before storage, with a recoverable JSON 422 error keyed for Livewire's upload error handling. The endpoint retains signature validation, CSRF middleware, throttling, individual-file validation, and support for multiple files in other components. Flight Plan Brief rejects malformed single-file completions through the normal upload-error lifecycle while preserving signed-reference validation, saved results, and existing parsing.

Diagnostics: Missing/malformed collections and rejected completions emit bounded warnings. `LIVEWIRE_UPLOAD_DIAGNOSTICS=true` additionally logs upload starts, request status, file/path counts, property presence, numeric PHP upload errors, and effective request-process PHP settings. An `X-Upload-Request-Id` response header identifies request logs. Keyed reference hashes correlate successful temporary uploads with completion calls; repeated hashes for the same component identify repeated completion requests. These records exclude original filenames, PDF data, signed references/URLs, and authentication tokens. Normal lifecycle logging is disabled by default.

Verification: Through Sail, 39 distinct focused endpoint, upload lifecycle, extraction, and saved-result tests passed; one unavailable private fixture was skipped. Coverage includes missing/malformed requests and completions, rejected PDFs, PHP upload errors, signed-reference rejection, multi-file compatibility, successful single uploads, retry and repeated uploads, preserved saved results, and diagnostic privacy/correlation. The existing full-PDF fixture hit a 30-second OCR timeout on page 103 during one run, then passed in isolation without an OCR change. The single Larastan pass reported two related type diagnostics in the file-error logging callback: Laravel's file() annotation assumes a flat file list. Changed inspection to Symfony's file bag so malformed nested multipart entries remain representable, and reran 14 focused request/diagnostic tests including a new nested-entry regression (all passed, 55 assertions). Static analysis was not rerun, honoring the one-pass rule. Pint passed before this final correction; final formatting follows it.

Production follow-up: Deploy through the normal release procedure, rebuild configuration/route caches, and reload PHP-FPM. Temporarily enable diagnostics and refresh configuration to investigate the missing-file trigger. Verify successful PDF upload, invalid-file feedback, retry, and repeated upload in a fresh browser session. Empty requests should return 422 instead of successful empty paths; completion errors should release the loading state. Check the web-request `php_sapi` and limits in diagnostics, and identify whether K4's Nginx server/location uses the observed 20M or 30M limit. The supplied `sudo -u www-data php` check still used CLI. Production's intermittent file-loss trigger and real-browser recovery remain to be confirmed; no deployment or production configuration changes were performed here.

Commit message: `fix: reject empty Livewire uploads and add safe diagnostics`

## Completed: FP: Flight-plan block split across PDF pages

### Goal

Allow `CKS020426RKSI.pdf` to upload and extract its route successfully.

### Current implementation

Page labels are removed from the complete ICAO flight-plan block before matching its fields. Added a portable text regression and an upload test using the private supplied PDF when available.

### Problem addressed

The equipment field ends on page 2 and departure starts on page 3. Intervening page labels caused full flight-plan matching to fail before the existing route-only cleanup could run.

Validation (2026-10-26): The new text test reproduced the reported error before the fix. Focused Sail route tests and the supplied-PDF upload test passed: 33 passed, one unavailable private fixture skipped, 129 assertions. The supplied release completed processing and displayed RKSI/VHHH. Pint passed for changed PHP files; one Larastan run passed with zero errors. No full suite was run.

Commit message: `fix: parse flight-plan blocks split across PDF pages`

## Completed: FP: Delay reporting: Departure UTC time format

- [x] Format delay reporting UTC timestamps as `2026-10-26 03:47z`.

### Goal

Use `YYYY-MM-DD HH:mmz` for UTC date/time displays in delay reporting.

### Current implementation

Scheduled departure, report timeline and delay endpoints, autosave status, and saved history timestamps now share the requested format. Departure timestamps are converted to UTC before display; missing or unreadable values show `Not recorded`. Stored snapshots and native datetime input values retain their existing formats.

### Problem addressed

Scheduled departure previously displayed raw ISO text such as `2026-10-25T01:00:00+00:00`, while other delay timestamps used inconsistent suffixes and precision.

Validation: Sail focused tests passed (38 tests, 217 assertions), Pint passed, and Larastan passed once with zero errors. Existing unrelated working-tree changes preserved.

Commit message: `fix: standardize delay reporting UTC timestamps`
