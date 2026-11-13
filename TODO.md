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

AI form prompt completed (2026-11-13): Review bid list now offers an AI form prompt popout with a copy button. It preserves the saved rank order, converts `F`/`Q`/`R` line IDs to numeric portal values, and withholds the prompt for unavailable, invalid, or repeated portal numbers. The existing numbered-list copy remains available; neither action submits a bid. Four focused PHP tests (44 assertions) and 23 focused JavaScript tests passed. Pint, Larastan (zero errors), and the Vite build passed.

Commit message: `feat: add AI form prompt to bid review`

December B777 portal retrieval completed (2026-11-13): Downloaded the public `Lines.pdf`, `Overview.xlsx`, and `Trips.pdf` links supplied for December 2026 into `storage/app/private/Bid_Packages`. All three returned HTTP 200 with expected document types; the existing focused Filament import test parsed them as one December 777 draft with complete source coverage (1 test, 12 assertions). This was a direct, manual download: the existing portal command only discovers the Bid Sheet, and no package downloader or automatic import was enabled. The running application's packages were not changed.

Commit message: `docs: record December 777 portal retrieval`

Branch integration completed (2026-11-13): Merged `origin/crew-bids` into `crew-bids`, retaining the local typed browsing refactor and dated Overview fix alongside the remote published-carry and per-user access changes. Resolved the TODO-only conflict by retaining the remote cleanup and concise local outcomes. Merge commit `0297a0e` contains both histories; the branch is no longer behind its remote. Preserved existing stashes and concurrent staging.

Validation: Elevated Sail was available. All 145 focused tests passed (1,177 assertions), covering imports including the supplied December files, package management, page-data assembly, browsing, carry precedence and user access. Pint passed, the single Larastan run passed with zero errors, and whitespace checks passed. No full suite or browser review was run.

Commit message: `docs: record crew-bids merge verification`

Dated Overview worksheet import completed (2026-11-13): The December B777 worksheet is named `777 December 2026 Overview`. The reader now accepts an optional month/year between either supported fleet and `Overview`, preserving existing names and cross-document validation. All 37 focused import/package-management tests passed (372 assertions), including the three supplied December files; Pint and the single Larastan run passed. Original documents remain unchanged.

Commit message: `fix: accept dated bid overview worksheet names`

CrewBids rendering refactor completed (2026-11-03): Added typed browsing/page-data DTOs, extracted `BrowseBidLines` and `CompareBidPackageRevision`, and introduced `CrewBidsPageDataFactory`. The Livewire component delegates browsing and page assembly while preserving filters, private rankings, eligibility and revision confirmation. The original focused integration passed 100 tests (704 assertions), Pint and Larastan.

Commit message: `refactor: extract crew bid browsing and typed page data`

### Goal

Prepare the K4 Bids branch for a pull request while keeping published package values, private bid lists, and user-visible source labels consistent.

### Current implementation

The branch includes the B747/B777 CA/FO workspace, private ranked lists and preferences, source imports, grouped browsing filters, read-only portal discovery, and Filament package management. The carry badge now uses a published carry count first, including zero; when it is absent, complete linked trips can supply a labelled calculated count of occupied UTC dates after month-end. The detailed product contract and remaining advanced comparison tasks live in CREW_BIDS.md.

### Problem addressed

The prior carry badge could replace a published value with a calculated value and did not identify its basis. Historical crew bidding outcomes also obscured the current PR scope in this file.

Outcome (2026-11-10): Corrected carry precedence and source labels without changing saved package payloads or bid ranks. Consolidated completed crew bidding notes below; the existing commits retain implementation history. The remaining duty/carry comparison filters, source discrepancy indicators, portal submission, and manual browser review are separate work.

Commit message: fix: prefer published carry counts in crew bids

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

1. [x] Completed: **High — Make duty-calendar date handling strict and aware of year boundaries.** (2026-11-03) [FlightDutyEvent.php](app/Services/Calendar/FlightDutyEvent.php) resolves yearless dates against the previous, current, and next years, choosing flight-local dates near their UTC endpoints and duty dates near the corresponding local flight boundary. Malformed text and impossible dates return unavailable; round-trip validation preserves valid padded and unpadded source days. Local flight timestamps must be within 14 hours of UTC, duty report cannot follow departure, and UTC flight duration must be positive. OCR duty-release correction advances at most one day, including across New Year, and the user-confirmed maximum duty span is 30 hours. Unit and duty-export feature coverage includes December/January in both directions, midnight crossings, leap days, different local clocks, invalid input, excessive intervals, and exact/over-limit boundaries. Source local-time metadata remains unchanged.

   Validation: Sail focused tests passed (`FlightDutyEventTest` and `ExportFlightDutyCalendarEventTest`: 67 tests, 158 assertions); Pint passed; one Larastan run passed with zero errors. `git diff HEAD --check` passed. Preserved unrelated working-tree changes and the existing staging state.

   Commit message: `fix: resolve duty calendar dates across year boundaries`

2. [x] Completed: **High — Define event identity independently of the full parsed payload.** (2026-11-03) Extracted [ScheduleResultMerger.php](app/Services/Schedule/ScheduleResultMerger.php) from `JcaScheduleProcessor`. Matching uses UTC bounds plus flight number/route and operating/deadhead type; other events use their type, assignment/station/title, and hotel where applicable. Unknown or incomplete identities remain separate, and repeated events within one source retain their multiplicity. Complementary metadata and source summaries merge independently of upload order. Conflicting values become unavailable with preserved alternatives; overlapping/shared-boundary observations of the same flight number with incompatible times/routes remain separate and produce visible, escaped warnings that survive cache reload. Source records retain filenames, original upload positions, MIME/document metadata, and original summaries; each event retains its original source observations. Partial-upload success and existing cache/export behavior are preserved.

   Validation: Sail focused tests passed (54 tests, 403 assertions): merger unit rules, schedule-upload feature tests, and three existing DTO cache/crew/ICS regressions. Coverage includes exact duplicates, metadata/key-order differences, conflicting observations, unrelated flights sharing a timestamp, later legs, reversed uploads, per-type identities, repeated/unknown events, and warning escaping. Pint and the frontend build passed; one Larastan run passed with zero errors. `git diff HEAD --check` passed. Preserved unrelated changes and existing staging state.

   Commit message: `fix: merge schedule events by semantic identity`

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

## B. Make OCR resolution configurable
You're currently rendering at 200 DPI.
That's reasonable for many documents, but flight schedules can contain small text, especially aircraft registrations, flight numbers, and timestamps.
I would make DPI configurable, with 300 as an option for difficult documents.

## Don't force PSM 6 for every document
Your current command:
[
    $tesseract,
    $imagePath,
    'stdout',
    '--psm', '6',
]


PSM 6 assumes a single uniform block of text.
That can be effective for flight plan sections, but schedules with multiple columns or complex layouts may benefit from PSM 3 or 4.
I'd make page segmentation mode configurable per extraction call.

# Completed Tasks

-------------------------------------------------------

## [x] Completed: Plan crew bid access roles

### Goal

Define who can see K4 Bids in navigation, open its route, save a private bid list, and manage shared packages.

### Current implementation

`User::canUseCrewBids()` grants access to verified admins or, when configured, all verified users. Both nav layouts use that method; `/crew-bids` also has the feature and authorization middleware. Package management is admin-only. The Users resource cannot currently assign a crew-bid-specific grant.

### Problem addressed

The existing all-users switch cannot give selected ordinary users access without opening the workspace to every verified user. The current crew-bid access check also does not consider `is_active`.

Outcome (2026-11-10): The user chose explicit per-user crew-bid grants plus existing admin access, with package management remaining admin-only. Documented the access matrix, route/nav behavior, grant management, rollout, and focused validation in CREW_BIDS.md. This was a documentation-only plan; no access behavior or account data changed. Implementation is recorded in the following task.

Commit message: `docs: plan explicit crew bid access grants`

## [x] Completed: Implement crew bid access roles

### Goal

Give selected ordinary users access to K4 Bids in navigation and at its direct route, while keeping package management with existing admins.

### Current implementation

Before this change, verified admins or every verified user under `features.crew_bids.for_all_users` could enter the workspace. The Users resource had no crew-bid grant control, and inactive accounts were not excluded by the crew-bid gate.

### Problem addressed

The all-users switch could not grant access to selected crew members. It also bypassed the intended explicit access decision when enabled.

Outcome (2026-11-10): Added a false-by-default `users.crew_bids_access` grant, managed and displayed in Filament Users. Active, verified grantees and existing admins can reach K4 Bids; only admins can manage packages. Removed the crew-bid all-users setting. The existing nav and route use the updated shared access check, and revocation blocks an open workspace without deleting its saved list. Deployment needs the new migration and deliberate grants for intended users; no existing accounts were auto-granted.

Verification: 24 focused tests passed (157 assertions), Pint passed, and one Larastan run passed with zero errors. No full suite or manual browser review was run.

Commit-hook follow-up (2026-11-10): The staged-PHP Larastan check exposed 13 type errors in `CrewBidsTest.php` that the earlier application-only analysis did not cover. Kept the test behavior and fixed Livewire helper inference by splitting assertion chains, used Livewire's public `getData()` for snapshot data, and narrowed date-picker nodes to `DOMElement`. The 25 affected focused tests passed (202 assertions), followed by 2 focused tests (15 assertions) after the final chain fix. Pint and file-scoped Larastan passed; the staged pre-commit check passed after this correction.

Commit message: `feat: grant crew bid access per user`

## [x] Completed: Upgrade bid card depth and header styling

### Goal

Give every shared bid line card more depth, clearer header hierarchy, and consistent 12px spacing.

### Current implementation

Added soft layered shadows, deeper hover shadows, and a 2px hover lift. Cards reserve a 3px left border that turns Aviation Blue when a disclosure is expanded, with a lighter blue accent in dark mode; its fixed width prevents content shifts. Headers use slate-50 in light mode and an opaque slate-800 surface in dark mode. Flying line IDs use bold, darker Slate text. Header, calendar, footer, and warning spacing now use 12px. Hover and state transitions share the disclosure's 240ms cubic-bezier curve, and reduced-motion preferences disable lift and animation.

### Problem addressed

The shared cards needed stronger separation from the background, a differentiated header, more internal space, and a visible expanded state. The view model and existing card/disclosure interactions remain intact.

Verification: Focused Sail tests passed (86 tests, 512 assertions). Pint, one Larastan run with zero errors, the production Vite build, and scoped diff checks passed. Synthetic F83/F55/reserve previews covered a 320px card container in light mode and 1440px desktop in dark mode with an expanded disclosure. Computed styles confirmed 12px padding, the fixed 3px left border, the expanded blue accent, contrasting headers, and no horizontal card overflow. No additional tests were added for this styling-only change. Prior working-tree edits were preserved.

Commit message: `ui: add depth and expanded accents to bid cards`

## [x] Completed: Polish bid card sections and interactions

### Goal

Separate the header, calendar, and footer with consistent spacing and give bid cards clearer hover and expansion feedback.

### Current implementation

Added a subtle header divider, dotted footer divider, 8px section padding, a minimum 80px ID column, and wrapping summary metrics. Cards have a light shadow and stronger border/shadow feedback on hover, with matching dark-theme styling. Card-background clicks toggle the native date disclosure; nested controls, disclosure summaries, prevented clicks, and text selection retain their existing behavior. Existing summary controls provide keyboard access. The view model, typography, colors, and calendar labels remain intact.

Disclosure content uses a 240ms intrinsic-height transition with a cubic-bezier curve and deferred content visibility. Reduced-motion preferences disable animation; browsers without the required CSS support retain native disclosure behavior. Cards now size to their content rather than using the former 150px maximum, accommodating the requested spacing and warnings without clipping.

### Problem addressed

The card sections lacked separation and hover feedback. Background clicks had no expansion behavior, and disclosure content appeared immediately.

Verification: Focused Sail feature tests passed (76 tests, 458 assertions), and all 15 crew-bidding JavaScript tests passed, including background toggling, independent cards, preserved controls, selected text, and prevented clicks. Pint, one Larastan run with zero errors, the production Vite build, and scoped diff checks passed. Synthetic Chrome previews covered a 320px card container, 1440px desktop, both themes, warnings, long credit values, and expanded date content. A real-time Chrome check confirmed intermediate card heights during opening (156 → 496.6 → 508px) and closing (508 → 171.3 → 156px). Physical touch and live authenticated interaction were not exercised. Existing staged work was preserved.

Commit message: `ui: refine bid card sections and disclosure feedback`

## [x] Completed: Extract a bid line view model while preserving the existing appearance

### Goal

Move line presentation decisions out of Blade while retaining the existing bidding layout and styling.

### Current implementation

Added the readonly CrewBidLineViewModel, built after matching and sorting. It prepares summary/detail metrics, flag icons and existing colors, calendar labels and descriptions, trip disclosure labels, credit explanations, warnings, and manual selection rank. The line component renders that contract rather than the original line array. Matching, credit calculations, source data, and bid ordering remain unchanged.

Per the user's follow-up, reverted the visual changes. Retained the original header layout, typography, badge styles, calendar colors, complete date labels at every width, carry-day badge beside the calendar, disclosure spacing, and 150px collapsed-card limit. Trip details and leg timeline templates match their prior versions. The rebuilt stylesheet matches the stylesheet from before the visual changes.

### Problem addressed

The line template chose icons, formatted calendar labels, selected detail metrics, and assembled credit explanations directly from a loosely structured array. These decisions now have a dedicated, testable presentation contract.

Verification: The focused Sail run passed 86 tests (512 assertions), covering missing versus zero values, second-precision credit, 28/29/30/31-day calendars with complete date labels, retained trip/rank data, credit-source explanations, and escaped metadata. Pint, one application-wide Larastan run with zero errors, and the production Vite build passed after reverting the visuals. Scoped diff checks passed. Unrelated CREW_BIDS.md edits were preserved.

Commit message: `refactor: extract bid line view model without visual changes`

## [x] Completed: Set preferences pop out

### Goal

Open K4 Bids preferences in view from any scroll position.

### Current implementation

Replaced the inline preferences section with a viewport overlay using the existing bid-list focus trap and scroll-lock pattern. The panel fills narrow screens and becomes a centered dialog on larger screens. Its form scrolls independently while Close and Done remain visible. Escape and the desktop backdrop dismiss it; reopening from the toolbar retains current preferences. Existing autosave, initial setup, and manual bid order remain intact. Save status, validation/conflict errors, and failed Done requests appear within the dialog; a failed save keeps it open for retry.

### Problem addressed

The sticky preferences control previously opened a section near the page top, requiring users farther down the line list to scroll back up.

Verification: Sail's focused CrewBids feature tests passed (64 tests, 409 assertions), including save/reopen behavior and a conflicting-tab error displayed inside the dialog. All 13 existing crew-bidding JavaScript tests passed. Pint, the production Vite build, and one Larastan run with zero errors passed. Task-scoped diff checks passed. Manual browser, touch, and keyboard review remains outstanding. The user's CREW_BIDS.md edits were preserved.

Commit message: `fix: open crew bid preferences in a responsive dialog`

## [x] Completed: Plan automated bid-package imports

### Goal

Plan monthly imports from forwarded work Outlook email or scheduled crew-bids website downloads.

### Current implementation

Recorded an email-first automation plan in CREW_BIDS.md, with shared receipt/staging/classification, coherent package grouping, deduplication, revision handling, privacy/retention, scheduler recovery, and phased acceptance criteria. Incorporated the user's policy: automatically publish complete validated first packages; hold revisions for review. Existing importer supports three source roles per group rather than the full ten-attachment email. Identified `/Pages/Documents.aspx` in saved portal navigation as a discovery lead, not a verified package source. Recorded the persistent-authentication decision needed before unattended portal downloads.

### Problem addressed

Monthly import currently requires manual file selection, while complete emails include unsupported and potentially personal documents. The plan prevents partial or mixed revisions from replacing packages and preserves private bid lists. No automation, forwarding rule, credentials, infrastructure, or application changes were introduced.

Verification: Inspected importer/parser/publication code, existing tests, scheduler configuration, and local source filenames. Confirmed Laravel 13.34.0 through Sail and checked Boost scheduler/queue documentation plus official forwarding/inbound-mail references. Connected Outlook is a personal account; the actual work-email manifest remains unverified. Documentation diff check passed; no application tests, Pint, or Larastan were needed for this plan.

Commit message: `docs: plan automated bid package ingestion and publication`

## [x] Completed: Review CREW_BIDS.md against the supplied CBA

### Goal

Check the crew-bidding document against the supplied 2021 CKS CBA, especially Part I Sections 5 and 17, and document discrepancies in CREW_BIDS.md.

### Current implementation

Added a dated source review with eight compensation findings and eight scheduling findings, each with clause and printed/PDF page references. Qualified the historical carry-credit formula and examples, corrected the guarantee's verification status, identified the rate arrays as Appendix 5-A's January 1, 2024 longevity-year 1–12 values, and distinguished contractual deadlines from pending live portal discovery. Recorded the agreement's renewal/effective-date provisions and the limits of applying the supplied text to 2026 packages. Application code and portal behavior were not changed.

### Problem addressed

The document called the carry-premium formula confirmed while leaving CBA-defined guarantees, rate provenance, and scheduling constraints unresolved. The review separates direct discrepancies, missing contractual qualifications, and product choices.

Follow-up clarification: Section 5.M establishes transition-credit allocation: all PCH for the transitioning duty is paid in the new bid period, and transition PCH is credited to the compensation Month of the last duty assignment. Added this confirmed rule beside the Carry-In Days definition and January–March compensation boundaries, and explicitly labeled the old departure-only formula as historical implementation. Remaining uncertainty concerns source coverage for applying the rule, additional premium eligibility, and current applicability; the allocation rule itself is defined by the supplied CBA.

User-confirmed application: Recorded one duty period, potentially containing multiple legs, as the Section 5.M duty-assignment boundary. Carry-in credit is the greater of awarded or actually worked credit for each Z day, summed after the daily comparisons; each flight segment's full credit belongs to its departure Z day. Replaced the earlier two-leg illustration with a daily comparison showing why comparing aggregate totals gives the wrong result. Distinguished daily valuation from Section 5.M pay-period allocation and awarded planning values from finalized pay. Attributed the daily greater-of rule to the user's clarification, without inventing a supporting CBA clause. The extra 50% remains conditional on qualifying excess days or scheduled-day-off work, with flight and non-flight treatment kept separate. Preserved the user's documentation-only scope.

Verification: Read both key chapters in full and checked related definitions, rest/deadhead/hostile-area clauses, duration, and implementation LOA through Sail PDF extraction. Compared both stored rate arrays with Appendix 5-A. Documentation-only change; application tests, Pint, and Larastan were not run. Final whitespace/diff review passed.

Commit message: `docs: review crew bidding rules against the 2021 CBA`

## [x] Completed: Seat link before header save status

### Goal

Swap the seat preference link and Saved indicator in the header.

### Current implementation

The First Officer/Captain preference link now precedes the save status, followed by My bid list.

### Problem addressed

The header previously displayed Saved before the seat link. Pint, one Larastan run with zero errors, and diff checks passed. Tests were not needed for this element-order-only change.

Commit message: `ui: place bid seat link before header save status`

## [x] Completed: Addition notice beside header save status

### Goal

Move the added-at-rank notice and Undo addition to the left of Saved in the header.

### Current implementation

The existing notice now appears immediately before the header save status, with its eight-second dismissal, Undo action, loading feedback, and polite status announcement preserved. The header can wrap on narrow screens. Save-recovery actions remain below the header without an empty notice row.

### Problem addressed

Addition feedback previously occupied a separate row below the toolbar. Two focused undo/save-recovery cases passed (10 assertions), along with Pint, one Larastan run with zero errors, and diff checks. No tests were added for this layout-only change.

Commit message: `ui: move bid addition notice beside header save status`

## [x] Completed: Back to lines icon

### Goal

Add an icon to the slide-over's Back to lines button.

### Current implementation

The button pairs the existing label with a decorative left-arrow Heroicon using the established icon sizing and inline spacing. Its immediate close action and focus restoration remain intact.

### Problem addressed

The return action previously had only text. The focused drawer feature test passed (nine assertions), alongside Pint, one Larastan run with zero errors, and diff checks. No tests were added for this visual-only change.

Commit message: `ui: add a back arrow to the bid list close button`

## [x] Completed: Responsive Add and crew bids controls review

### Goal

Improve Add responsiveness and review K4 Bids controls for appropriate JavaScript, debounce, and loading feedback.

### Current implementation

| Controls | Decision and resulting behavior |
| --- | --- |
| Add | Immediate client-side Adding feedback, a reduced-motion-aware spinner, and a guard against duplicate clicks. Selection still waits for server validation. Failures clear the pending state and show the existing request error. Loading disables Add during list mutations and context changes while leaving unrelated filter requests independent. |
| My bid list, Selected, and Back to lines | The drawer stays mounted and opens/closes locally, with reactive expanded state, focus trapping, initial focus, and focus restoration. |
| Manage package, preferences, filters, package info, trip tabs, and disclosures | Keep immediate local JavaScript or native interactions; no debounce or request is needed to open these views. |
| Sort, start airport, layover airport, and Selected only | Send discrete changes immediately on change instead of waiting 250ms. Layover quick filters update their pressed styling immediately while requesting results. |
| Goals, goal ordering, and UTC days off | Preserve immediate client feedback and the shared 250ms preference-save debounce. Done retains its 250ms delay to include pending edits and now shows save progress. |
| Minimum credit and minimum days off | Retain the 400ms typing debounce. Results show delayed loading feedback while filtering. |
| Date-range application, resets, retries, revision review, list moves/removal, and bulk list actions | Keep server validation and persistence, with scoped progress labels and duplicate-action disabling. Empty bulk actions are disabled. |
| Upload, preview, and publication | Show upload/preview/publication progress and prevent overlapping import operations. Keep server-side admin checks. |
| Review and copy | Keep review server-backed and clipboard copying local. Disable copying while requests or preference edits are pending, or when the list is empty. |

Captured Debugbar requests took 2.6–3.9 seconds; one 3.56-second request spent only 26ms on queries and reached its first Blade view after about 3.14 seconds. Code review found repeated decoding of the encrypted package payload for each eligible line. Rendering now decodes each package once and reuses it for line presentation, trips, and revision comparisons. No post-change live timing benchmark was performed.

### Problem addressed

Add previously had no progress label and was disabled by every component request. Several other server controls had no local progress feedback, while opening the drawer required a request. Focused Sail checks passed: 67 bids/filter cases (375 assertions), 13 JavaScript cases including immediate pending state, duplicate suppression, and failure/retry handling, two import cases (14 assertions), and four follow-up cases after the final payload-access correction (29 assertions). Production build, Pint, and diff checks passed. The single Larastan run reported one redundant null-safe expression; it was corrected and the affected paths passed focused tests. Larastan was not rerun under the one-run rule. Browser interaction/focus review remains outstanding. Existing changes and concurrent staging were preserved.

Commit message: `perf: improve crew bid controls and reuse decoded package data`

## [x] Completed: Open Manage package immediately

### Goal

Make the Manage package controls respond immediately.

### Current implementation

Both Manage package buttons toggle the client-side Livewire property directly and announce their expanded state. The admin-only import panel stays rendered with `wire:show` and `x-cloak`, retaining its file input while hidden. Import and publication actions keep their server-side permission checks.

### Problem addressed

Opening and closing the panel previously waited for a server-rendered response. Five focused Sail feature tests passed (32 assertions), covering import/publication, failed imports, access, metadata escaping, and withholding the form from non-admins even when they set the visibility property. Pint, one final Larastan run (zero errors), and diff checks passed. Browser interaction review remains outstanding.

Commit message: `perf: toggle package management without a server round trip`

## [x] Completed: Tool-based user preferences and saved K4 bid seat

### Goal

Move the bid seat selector into user preferences, grouped by tool.

### Current implementation

The Profile form now groups User preferences into Schedule Extractor (airline codes and flight tracker) and K4 Bids (bid seat). The existing profile Save action validates and persists First Officer/Captain for the authenticated user. Omitted seat values preserve the saved choice. K4 Bids loads that seat when opening the workspace and retains it during package changes; each seat keeps its existing private preferences and bid list. The toolbar displays the active seat as a link to the K4 Bids preference section, replacing its selector. Already-open workspaces retain their current seat until reopened.

The migration preserves each existing user's latest workspace seat, with deterministic ordering for tied update times, and defaults users without a workspace to First Officer. The new column and migration record are confirmed in the local database. Existing workspace records are preserved.

### Problem addressed

Seat selection previously lived among workspace controls instead of the user's tool settings. Focused Sail checks passed: 28 profile, migration, and seat-context cases (136 assertions), followed by 68 bids and package-import regression cases (474 assertions). Coverage includes invalid values, omitted preferences, per-user saves, saved-seat precedence over recent workspace history, seat-specific lists and eligibility, package changes, no-package initialization, migration defaults/backfill, and rollback preservation of workspaces. Pint, production build, one final Larastan run (zero errors), and diff checks passed. Browser visual review remains outstanding. Existing toolbar changes and concurrent staging were preserved.

Commit message: `feat: move bid seat into tool-based user preferences`

## [x] Completed: Two-tier crew bids toolbar

### Goal

Separate package/account setup from result navigation, preferences, sorting, and filtering.

### Current implementation

The sticky header now contains two distinct rows. The command bar groups the package selector, publication status, and admin-only Manage package cog in one pill, with Package details beside it and the seat selector and My bid list action on the right. The controls row places Lines/Trips/Reserve on the left, the matching count and preference summary together in the center-left, and sorting and Filters together on the right. Both rows wrap within narrow screens and retain existing theme styles, accessible names, focus treatments, Livewire bindings, and permission checks. The existing resize observer measures the full header for expanded-card action offsets.

### Problem addressed

Package administration and result controls previously competed for space, while the matching count and preferences were grouped with sorting toward the right. Focused Sail checks passed: 64 crew bids feature tests (357 assertions), 11 JavaScript tests, Pint, the production build, and one final Larastan run with zero errors. Diff checks passed. Browser visual review remains outstanding. No new tests were added for this layout-only change.

Commit message: `refactor: split crew bids toolbar into command and control rows`

## [x] Completed: Immediate goal selection and priority ordering

### Goal

Update chosen goals and their priority order immediately while debouncing preference saves.

### Current implementation

The priority list now renders from the client-side goals array. Checkbox changes, Move up, and drag ordering share one 250ms save debounce. Reordering updates that array immediately and uses the bundled Alpine Sort plugin; its DOM order is restored before the keyed loop applies the new order. The client-owned list stays intact during server renders. Native ordered-list markers replace manually repeated rank numbers. Existing server validation, strict ranking, saved goal order, bid-list order, and unsaved-change protection remain in place.

### Problem addressed

The previous goal list waited for a server render after checkbox changes, while Move up called the server immediately. Five focused Sail feature cases passed (42 assertions), covering exact client-order persistence/reload, strict ranking, invalid goals, date selection, and retained bid order. Eleven focused JavaScript tests passed, including immediate moves, drag-order reconciliation, invalid targets, and deferred-save protection. Pint, production build, one final Larastan run (zero errors), and changed-file diff checks passed.

Reviewed the actual Blade goal controls with bundled Alpine and synthetic state/save handling in headless Chrome. Rapid selection, repeated Move up, and checkbox off/on changes updated the DOM with zero immediate saves, then produced one final save after the debounce. A simulated drag callback kept DOM/state order aligned and saved once afterward; no JavaScript errors occurred. This checked client interaction independently of authenticated Livewire requests, which were covered by the focused feature tests; a physical drag gesture was not exercised. Existing source-document edits and concurrent staging/commits were preserved.

Commit message: `perf: update goal priorities instantly with debounced saves`

## [x] Completed: Open crew bid preferences immediately

### Goal

Show or hide the preferences editor immediately when its toolbar button is clicked.

### Current implementation

The toolbar button now toggles the client-side Livewire property directly, with a reactive expanded state. The editor stays rendered and uses `wire:show` to toggle visibility; `x-cloak` prevents an initial flash. Opening/closing no longer waits for a debounce or server response, and hiding the editor retains its inputs and pending selection timers. Preference-save and Done debounces remain in place.

### Problem addressed

The opener previously waited 250ms before requesting a server-rendered editor. Four focused Sail feature tests passed (34 assertions), covering preferences initialization/reload, calendar persistence, priorities, and escaped package metadata. Pint, one final Larastan run (zero errors), and changed-file diff checks passed. No new tests were added for framework visibility internals. Existing working-tree changes were preserved.

Commit message: `fix: open crew bid preferences without a server round trip`

## [x] Completed: Debounce Set preferences and the days-off calendar

### Goal

Reduce repeated preference saves while users choose several goals or UTC days off, with immediate selection feedback.

### Current implementation

Preference open/Done actions now use a 250ms debounce. Goal checkboxes and the days-off calendar each share a 250ms change debounce, sending their complete deferred selection after input settles. Calendar cells retain their layout using native keyboard-accessible checkboxes and immediate checked-state styling. Server-side model updates sort selected dates before saving; existing date validation, saved bid ordering, conflict visibility, clear/range actions, and package-month range defaults are preserved. Pending preference changes participate in the existing leave-page warning before their debounced request begins.

### Problem addressed

Individual day clicks previously sent immediate toggle actions, while each goal checkbox had its own timer. Grouping each selector's updates preserves the final selection when multiple dates are chosen or the same date is toggled repeatedly. Twelve focused Sail feature cases passed (104 assertions), including multi-date persistence/reload, deselection, conflict retention, invalid dates, clearing, and existing range/goal/list behavior. All eight focused JavaScript tests passed, including leave-page protection during the debounce interval. Pint, production build, and changed-file diff checks passed. Browser request timing was not measured. Earlier working-tree changes were preserved.

One final application-wide Larastan run passed with zero errors.

Commit message: `perf: debounce preference actions and batch days-off selection`

## [x] Completed: Start date ranges in the bid-package month

### Goal

Open range date pickers in the selected bid-package month instead of the current calendar month.

### Current implementation

Both From and Through default to the first day of the package month whenever a package/seat context loads. Picker minimum/maximum dates use that month's first/last UTC calendar dates, including leap years. Switching packages resets the range to the new first day. Defaults do not add requested days off or save preferences until the user adds the range; existing server-side date validation remains in place.

### Problem addressed

Empty range fields let the browser initially show the current month, forcing users to navigate to the bid month. Eight focused Sail tests passed (67 assertions), covering first-day defaults with a different current date, year boundaries, February/leap February, rendered picker limits, package switching, explicit range application, existing range/undo behavior, and rejection of out-of-package dates without saving. Pint, one final Larastan run (zero errors), and changed-file diff checks passed. Existing debounce and documentation changes were preserved.

Commit message: `fix: initialize bid date ranges from the package month`

## [x] Completed: Debounce crew bid filter updates

### Goal

Reduce redundant result refreshes during rapid filter and preference changes.

### Current implementation

Added Livewire's 250ms network debounce to sort, required start city, layover airport, goal checkboxes, and Selected only. Native input state still updates immediately. Minimum credit and minimum days off retain their existing 400ms debounce. Package/seat changes, action buttons, date selection, and local panel controls remain immediate.

### Problem addressed

These live controls previously sent a refresh for every change, including intermediate keyboard selections and repeated checkbox changes. Confirmed the installed Livewire 4.4.8 runtime applies explicit network debounce to selects and checkboxes. Four focused Sail feature tests passed (40 assertions), covering strict goals/start-city filtering, sorting, combined layover filters, reset/context changes, and saved order. Pint, one final Larastan run (zero errors), and changed-file diff checks passed. No new tests were added for framework debounce internals; browser request timing was not measured. Existing TODO outcomes were preserved.

Commit message: `perf: debounce crew bid filter and preference updates`

## [x] Completed: Trip leg and layover quick filters

### Goal

Find lines with extended layovers at a chosen airport and exclude short connections without changing the private bid order.

### Current implementation

Added combinable 24h+ and avoid-under-10h controls, a connected-layover airport selector, clear/reset actions, and gold outlines with text on matching timeline segments. Uses existing scheduled UTC connection calculations; source rest remains separate. Positive extended-layover evidence can match partial trip coverage, while avoiding short connections requires all linked trips and consistent connections. Selected lines remain in the bid drawer in their original order. Filters reset with the package/seat context.

### Problem addressed

Previously duration flags required inspecting each expanded pairing individually. The focused Sail regression run passed 11 tests (44 assertions), including exact thresholds, combined filters, absent/empty trips, inconsistent connections, reset/context changes, and preserved source payloads and saved choices.

Commit message: `feat: filter crew bid lines by layover duration and airport`

## [x] Completed: Crew complement and deadhead summary indicators

### Goal

Compare crew distribution and known deadhead legs without opening Details.

### Current implementation

Crew complement now appears below each line ID when present. The existing evidence-based DH indicator keeps its text visible on narrow screens. Route flags, accessible explanations, missing-data omission, and source payloads are preserved.

### Problem addressed

Crew distribution was only available in Details, and narrow screens hid the DH label. Eleven focused Sail cases passed (76 assertions), including collapsed-header content, incomplete trip coverage, unlinked deadheads, duplicate pairings, and missing metrics.

Commit message: `feat: surface crew complement and deadheads in bid summaries`

## [x] Completed: Compact crew bid controls and package metadata

### Goal

Reduce the space above line results while keeping package information, preferences, sorting, and quick filters accessible.

### Current implementation

Moved package details into a native modal opened by the info icon beside the package selector. Saved status now sits in the header; transient notices and save recovery still appear when needed. Lines/Trips/Reserve, match count, a preferences pill, sort, and Filters share one responsive control bar. Filters expands into horizontal layover chips, the airport selector, and existing numeric/selected controls; a gold indicator identifies active filters when collapsed. Preferences still opens the existing editor. Mobile wrapping keeps the bid-list action beside the title and the match count beside the view tabs.

### Problem addressed

Separate status, package metadata, preferences, sorting, and bordered layover rows pushed results down the page. The default view now keeps metadata and filters closed, with keyboard focus, pressed/expanded states, light/dark styling, and existing loading/error feedback.

### Final integration outcome (2026-10-28)

All 55 focused Crew Bids feature cases passed (278 assertions), and all seven focused JavaScript tests passed. After the visual review's final mobile spacing refinement, the two affected rendering/saved-order cases passed again (40 assertions). Pint and the final production build passed. One application-wide Larastan run passed with zero errors; the full test suite was not run.

Reviewed actual Blade markup and production CSS with synthetic, nonpersistent data in headless Chrome at 390px and 1440px, including light/dark appearance, collapsed/expanded filters, card summaries, and the package modal. Neither exact viewport had horizontal overflow. The mobile header/control bar measured 181px/105px, and desktop measured 69px/53px. Trusted Escape input closed the modal and returned focus to its info button without closing Filters; another Escape closed Filters and returned focus to its trigger. Live authenticated interaction and physical touch-device review remain untested. Existing source-document edits, pay-rate data, other working-tree changes, and concurrent staging were preserved.

Commit message: `style: consolidate crew bid controls and collapse secondary metadata`

## [x] Completed: Crew bid pay rates JSON

### Goal

Provide valid JSON pay-rate data for the crew bid tool.

### Current implementation

Converted `app/Data/pay_rates.json` from a JavaScript fragment to a JSON object with quoted `CA` and `FO` keys and numeric arrays. Preserved all 12 rates per seat in their original order.

### Problem addressed

Unquoted keys, `Object.freeze()` calls, and a trailing JavaScript closing wrapper prevented JSON parsing.

Validation: Sail JSON parsing and exact comparison passed for all 24 rates. One Larastan run passed with zero errors. No PHP files changed; Pint was not required. Unrelated working-tree changes preserved.

Commit message: `fix: convert crew bid pay rates to valid JSON`

### Estimated pay specification outcome (2026-10-28)

Updated the Estimated pay section in `CREW_BIDS.md` to reference `app/Data/pay_rates.json` and describe the supplied `CA`/`FO` arrays with 12 numeric rates each. Rate input is now available; rate management, dollar estimates, and pay sorting remain deferred. Currency, effective dates, fleet applicability, longevity mapping, guarantee treatment, and the pay formula still need confirmation.

Validation: Sail JSON parsing confirmed 12 entries per seat. Documentation-only update; checked the focused diff for whitespace errors. No PHP files changed; tests, Pint, and Larastan were not needed. Preserved unrelated working-tree changes.

Commit message: `docs: reference supplied rates in estimated pay specification`

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

## [x] Completed: Crew bidding

### Goal

Give eligible crew a private, reviewable workspace for monthly bid packages and ranked choices.

### Current implementation

The initial release imports supported Lines/Trips PDFs and Overview spreadsheets into encrypted shared packages. Crew can browse B747/B777 packages by seat, filter available lines, inspect UTC calendars and trip details, set strict ranking goals, and save a numbered bid list with conflict and revision review. Filament manages draft import/replacement, publication, archive/restore, and deletion under policy and database protections. Source files are not retained after import.

Read-only Kalitta portal discovery validates the observed login and Bid Sheet structure using transient credentials. It does not submit bids; the closed-window report and open-window submission/receipt contract remain unverified. CREW_BIDS.md is the product contract for implemented behavior and pending comparisons.

### Problem addressed

K4 Parser previously lacked a shared package workflow and owner-scoped bid ranking. This branch also established source-aware trip display, missing-data handling, keyboard interaction, responsive controls, and admin lifecycle operations.

Historical validation used focused Sail PHP and JavaScript tests, production asset builds, Pint, and Larastan. Private source samples and manual browser interactions have separate availability and verification limits. Commit history retains the individual implementation and documentation outcomes.

Commit message: feat: add crew bidding imports and private ranked bid workspace

## [x] Completed: Bug: Extracted crew list:
## [x] Completed: FP: Fuel Score: Include TOC, TOD
## [x] Completed: FP: Missing SIGWX and additional fuel notes

### Goal

Extract all five dispatcher notes from `storage/app/private/flight_releases/CKS024201RJGG.pdf`, including the government SIGWX forecast and additional holding fuel for destination thunderstorms.

### Current implementation

`DispatcherNotesExtractor` recognizes both missing headings through the existing bulleted-note parser. It retains source order, joins wrapped bullet text, preserves heading/bullet line breaks, and bounds extraction at the next note, boxed separator, MEL/CDL section, or end of text. Existing ENROUTE SIGWX notes reuse the same parser.

### Problem

The parser's supported heading patterns omitted `GOVT SIGWX PROG FORECASTS AREAS OF` and `ADDITIONAL FUEL ADDED`, silently returning only the runway, maintenance, and FSA notes. The supplied PDF and four synthetic layout regressions reproduced the omission before the fix.

### Validation outcome (2026-10-06)

Through Sail, all 12 `DispatcherNotesExtractorTest` cases passed, including the supplied PDF's exact five-note result and the existing RJAA, PANC, and KCVG release regressions. The focused extraction orchestration and Notes rendering checks also passed: 14 passing tests and 99 assertions total; one separate private-PDF characterization was skipped because `CKS025625KLAX.pdf` is unavailable. Pint passed, and one full application Larastan run passed with zero errors. Boost's version-specific documentation search succeeded. Unrelated working-tree changes and staging were preserved.

Previously saved releases require uploading/extracting the PDF again to populate the two omitted notes. No frontend rebuild is required.

Commit message: `fix: extract government SIGWX and additional fuel dispatcher notes`

## [x] Completed: Capture flight-plan test streams

### Goal

Keep Livewire flight-plan progress frames out of PHPUnit console output while preserving progress assertions.

### Current implementation

The shared flight-plan test case captures output with a callback buffer that absorbs Livewire's explicit flushes and closes in teardown. Extraction tests check captured success and failure progress plus an empty output buffer. Browser streaming remains unchanged.

### Problem

Livewire writes progress frames directly and flushes the buffer, leaking JSON into otherwise passing test output.

### Validation outcome (2026-10-06)

Through Sail, all 41 focused flight-plan Livewire tests passed with 977 assertions and no streamed JSON in console output. The updated extraction test file also passed individually. Pint passed and one full application Larastan run passed with zero errors. Unrelated TODO edits were preserved.

Commit message: `test: capture flight-plan progress streams during Livewire tests`

## [x] Completed: Schedule: R2 status determine

### Goal

Use Duty as the umbrella event type for reserve, training, and deadhead assignments, classify specific duties from assignment context, and replace generic Duty badges with R1, R2, R4, or Training where supported.

### Current implementation

- `DutyEventType` represents FlightDuty, Deadhead, Training, ReserveR1, ReserveR2, ReserveR4, and GeneralDuty. `ScheduleEventType` retains Flight, Duty, Layover, Off, 1in7, and Unknown; legacy deadhead/training values normalize to Duty.
- `DutyTypeResolver` recognizes reserve codes, training keywords, deadhead markers, and flight-duty context. `TripInformationParser` applies it to both tabular and date-range formats, keeps assignment activity codes out of flight-number metadata, and produces `R2 LAX` from the supplied noisy R2 text with LAX station context. Published-roster reserve assignments use the same resolver.
- Duty cards show subtype labels and colors with light/dark variants: amber R1, indigo R2, purple R4, blue Training, slate Deadhead/GeneralDuty, and green FlightDuty. Duty subtype metadata and typed duty DTOs survive serialization and download updates.
- Route-bearing deadheads retain their flight-card route, flight number, airline, timing, and calendar download details. Assignment classification excludes crew-list context; an operating flight remains a flight when a crew member is deadheading. Reserve and training duties remain separate from overlapping flights.
- Extraction and calendar filters include reserve, training, and deadheads under **Duties only**. **Flights only** includes operating flights. Existing legacy deadhead and training payloads still load; saved results missing assignment evidence require re-extraction to obtain newly supported classifications.

### Problem

R2 had only a generic Duty badge, while other reserve/training assignments could be parsed as flights. Activity codes were stored as flight numbers, and deadhead/training classifications were separate event types rather than duty subtypes.

### Validation outcome (2026-10-12)

Through Sail, all 161 focused resolver, parser, DTO, view-model, card-rendering, calendar-export, Livewire schedule, and extraction-logging tests passed with 1,050 assertions. Coverage includes the supplied noisy R2 text, training/reserve assignments in both trip-information formats, legacy payloads, serialization, overlapping duties, and extraction/export filtering. Pint completed successfully; one application-wide Larastan run passed with zero errors. Boost's version-specific documentation search succeeded.

The production Vite build passed after `npm ci --ignore-scripts --no-audit --no-fund` restored the existing lockfile's packages; installed Tailwind 3 had conflicted with the declared Tailwind 4 version. Dependency manifests and lockfiles were unchanged. Badge markup and light/dark classes were verified programmatically; no manual browser review was performed. `duty_extracted_example.json` was unavailable, so its quoted noisy text was covered by a regression fixture in the parser test. Other TODO sections were preserved.

Commit message: `feat: classify schedule duty subtypes and display reserve badges`

## [x] Completed: Repair Pint commit-hook failure

### Outcome (2026-10-12)

Reproduced Pint's broken SHA512 PHAR signature inside Sail and reinstalled only `laravel/pint` v1.30.5 with Composer, bypassing cache, scripts, plugins, and autoloader generation. Dependency manifests, lockfiles, application files, and the Git index were unchanged.

The repaired executable reports Pint 1.30.5. The existing pre-commit hook passed its focused staged-file Pint check and one staged-file Larastan run with zero errors. No application tests were needed for this vendor-only repair. Existing working-tree changes were preserved; no commit was created.

Commit message: `docs: record Pint installation repair and successful hook checks`

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
