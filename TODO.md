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

# Product and UI rules

- Use Aviation Blue for structure, Compass Gold for primary emphasis, and the existing light/dark theme tokens.
- Keep operational values compact and scannable; use monospaced text for codes, times, routes, coordinates, and numeric planning values.
- Label every time basis and fuel unit. Never silently mix UTC/local or pounds/kilograms.
- Distinguish `not present in this release` from `not supported yet`. Do not render zero, empty text, or a green status for missing data.
- Preserve source evidence internally for Weather, ETOPS, Fuel Score, and Weight & Balance.
- Reuse Blade components and view data; do not parse, normalize, query, or authorize inside Blade.
- Every interactive control needs keyboard access, visible focus, an accessible name, and a useful loading/empty/error state.

# Tasks
## Sloppy static findings
./vendor/bin/sloppy

## Paused: Flight release: 24 hour time limit or past ETA

### Goal

Expire a cached flight release at the earlier of 24 hours after extraction and its planned ETA. Remove the expired release from the database and any release-specific cache, and stop serving it through the brief or Fuel Score URL.

### Current implementation

- `FlightPlanResultStore` keeps one encrypted `flight_plan_results` row per user. `save()` replaces that row; `get()` checks owner and key, and `latest()` checks owner, but neither checks age or ETA. The row has timestamps, but no explicit extraction or expiry timestamp.
- `FlightPlanBrief` loads the latest row on mount and retrieves it on render. `OfflineFuelScoreController` retrieves the same row by key. Both rely on the store, so expiry enforcement belongs there.
- `FlightPlanTextExtractor` caches PDF text for seven days under a file-hash key. This cache is independent of the saved result and can be shared by identical uploads; it has no owner or result-key mapping. The uploaded PDF is deleted after extraction.
- `schedule.etaUtc` in the serialized `flight_plan_data` is a dated UTC instant when extraction can establish one; it may be absent. Existing scheduling in `routes/console.php` can run database cleanup.

### Problem

An old result remains readable until the user replaces or manually clears it. The PDF-text cache may retain release content longer than the proposed limit. `updated_at` is not a reliable extraction clock if a row is later touched, and deleting a shared file-hash cache entry for one owner could affect another owner's extraction.

### Implementation plan

1. Add an immutable extraction timestamp and an indexed expiry timestamp to `flight_plan_results`. Set both when a successful extraction is saved, including when the user's existing row is replaced. Compute expiry as the earlier of extraction time plus 24 hours and a valid, dated `schedule.etaUtc`; if ETA is missing or unusable, use the 24-hour deadline. Treat an ETA already in the past at save time as immediately expired. Compare instants in UTC and define expiry at the deadline (`now >= expires_at`).
2. Make `FlightPlanResultStore::get()` and `latest()` exclude expired rows and delete a matching expired row when encountered. Keep owner and result-key checks in place. The brief should return to its upload state after expiry; the Fuel Score URL should return 404. Clear any stale Livewire result key or selected task when a rendered result expires so the UI does not retain a link to it.
3. Add a scheduled cleanup command that deletes expired rows even if their owners never return. Use the same expiry rule for read checks and cleanup; make cleanup safe to repeat and scope deletion to rows whose stored deadline has passed. Define how existing rows receive an expiry during migration so deployment cannot make old releases persist indefinitely or expose them past the new limit.
4. Inventory release-specific cache entries and invalidate them with the result. The current PDF-text cache is shared by file hash, so replace it with an owner/release-scoped entry that can be invalidated, or remove that cache if scoping has no useful benefit. Ensure existing seven-day hash entries age out without being read after the change. Do not flush unrelated airport or schedule caches.
5. Document the expiry behavior in the upload/result UI with a concise UTC-aware message, including that re-upload is needed after expiry. Avoid presenting the planned ETA as a confirmed arrival time.

### Acceptance criteria

- A release is accessible before both deadlines and unavailable at the earlier deadline, including exact-boundary, midnight rollover, and already-past ETA cases.
- Missing or invalid ETA never extends the 24-hour limit; a valid ETA earlier than 24 hours wins. Replacing a release starts a new extraction clock and invalidates the old key.
- Expired data is removed from the database by scheduled cleanup even without another request. Brief and Fuel Score reads deny an expired result immediately, regardless of whether cleanup has run.
- No release-specific cached content remains available after expiry. Shared, unrelated caches are unaffected; pre-change PDF-text entries cannot be reused and expire naturally.
- Ownership checks, encrypted storage, and the normal upload/error flows continue to work.

### Validation for implementation

- Add focused store tests using a frozen clock for both deadlines, exact equality, missing/malformed ETA, replacement, ownership, and deletion on read.
- Add focused Livewire and Fuel Score tests for expiry transitions and a cleanup-command test for unattended expiration and repeat runs.
- Run only affected tests through Sail, Pint after PHP changes, and Larastan once at the final integration checkpoint. Record the outcomes here.

Commit message: `feat: expire flight releases after 24 hours or planned ETA`

## Plan: feat: Track schedule upload count
- For multiple file uploads within each user request

## Unified upload
Currently: 2 tabs have 2 different upload points, user has to choose 
Goal: Have one unified upload path. Service will determine if a schedule or flight plan has been uploaded. 

Cached results: Keep extract schedule and flight plan brief tabs for now. There's not really a better way to render cached results for now.

## Flight plan: Create a way to turn tasks on or off
- in ENV and config files
- in coordination with enum

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

- Confirm the exact FMS reserve formula: which of alternate burn, the release's `RESERVE` amount, and the aircraft additive participate. Do not assume `alternate + finalReserve + additive` or count the source reserve twice.
- Supply approved additive values and units for each model; confirm whether the two 777 variants share a value and whether tail-specific overrides are needed.
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

# Completed Tasks

-------------------------------------------------------

## [x] Completed: feat: Overview cards Spatial Organization (Grid & Layout)
## [x] Completed: Refactor welcome page for use with new features
## [x] Complete: Lat / Long cut off, some waypoints prefixed with `-`
## [x] Complete: Ramp fuel stat card
## [x] Completed: Move B44 badge to Ramp Fuel card
## [x] Completed: Extract dispatcher notes
## Completed: Mobile flight plan hamburger menu
## [x] Completed: Flight plan task routes
## [x] Completed: Bug: Crew name extract boundary
## [x] Completed: Flight plan: Refactor FlightPlanBriefTest
## [x] Completed: Flight plan: Crew list: WCAG 2.2 AA compliance

Currently: Crew role avatars use 12px white text on role-specific solid backgrounds. The emerald-600 and amber-600 light-mode combinations measure approximately 3.77:1 and 3.19:1, below the 4.5:1 WCAG 2.2 AA minimum for normal text. Existing component and enum tests preserve these failing color combinations.

Goal: Make the reusable crew card WCAG 2.2 AA compliant in Maintenance Log, Envelope, and Flight Init while retaining a compact, scannable role avatar.

Acceptance criteria:

- Every role-label foreground/background combination reaches at least 4.5:1 contrast in light and dark modes.
- Role identity remains available as visible text and through an accurate accessible name; color must not be the only distinguishing cue.
- Names, employee numbers, base details, missing-value labels, and High mins status retain sufficient contrast and meaningful reading order.
- Crew cards remain readable at 200% zoom and reflow without clipped role labels, names, or identifiers at supported breakpoints.
- Update role-color and employee-card tests to cover every palette group, fallback roles, missing roles, accessible labels, and both theme palettes.
- Perform a browser accessibility check for contrast, semantics, and reflow after the focused automated tests pass.

References:

- `app/Enums/CrewPosition.php`
- `resources/views/components/flight-release/employee-card.blade.php`
- `tests/Unit/Enums/CrewPositionTest.php`
- `tests/Feature/EmployeeCardComponentTest.php`

### Outcome

Darkened light-mode captain and relief-pilot badges to emerald-700 and amber-700, and made the foreground explicit in every role palette. Preserved compact visible role labels and accurate accessible names, including unknown and missing roles. Crew names, employee numbers, and base details can now wrap anywhere; role avatars have a minimum height rather than a fixed height so long fallback labels remain visible. Removed the faded employee-number marker and hid the decorative High mins icon from assistive technology.

### Validation

The focused enum, employee-card, Maintenance Log, Envelope, and Flight Init tests pass: 13 tests, 551 assertions. Tests calculate WCAG contrast for every role and fallback in both themes, compositing translucent dark backgrounds over the card surface, and verify visible/accessibly named roles, missing employee numbers, and reading order. Pint, the production Vite build, and one focused Larastan run pass with zero errors.

A headless Chrome check of the actual Blade component with production CSS passed 336 card checks covering all roles plus unknown/missing roles, both themes, widths of 320/640/768/1024 CSS pixels, and normal/200% CSS scaling. Computed text contrast was at least 4.7588:1, exceeding the [WCAG normal-text minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html), with no card or text overflow. The check verified role names and native list markup; it used an isolated component fixture rather than an authenticated full-page screen-reader audit or native browser zoom.

Commit message: `fix: improve crew card contrast and accessible reflow`

### Follow-up outcome

Updated the two stale captain badge assertions in `FlightReleasePageViewModelTest` from emerald-600 to emerald-700 for Maintenance Log and Flight Init. The focused test file passes through Sail: 66 tests, 661 assertions. Pint passes, and one focused Larastan run on the updated test passes with zero errors.

Follow-up commit message: `fix: update crew member role badge color test`
