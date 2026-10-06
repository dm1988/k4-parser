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

## FP: Prelim flight plans
Goal:
1. Determine if an uploaded flight plan is preliminary
2. Tag non finalized data
   1. Weights
   2. Fuel
   3. Route
   4. ETOPS

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

# Completed Tasks

-------------------------------------------------------

## [x] Completed: Flight plan: Crew list: WCAG 2.2 AA compliance
## [x] Completed: Flight plan: header refactor
## [x] Completed: Flight plan: Weight & Balance: Progress bar readability
## [x] Completed: Sloppy static findings
## [x] Completed: feat: Track schedule upload count
## [x] Completed: FP: Offline mode
Chrome is refreshing the page dropping the off time and takeoff fuel and rendering a ERR_Connection 404.

### Goal

Recover the loaded flight's calculator and entered Off time, starting FOB at takeoff, ATA, and AFOB after an offline refresh or browser-initiated tab reload.

### Current implementation

- `OfflineFuelScoreController` retains authenticated, verified, entitled, owner-scoped access. It marks successful calculator responses explicitly and supplies the production asset list, including transitive JavaScript imports, CSS, and asset dependencies from Vite's manifest.
- `offline-fuel-worker.js` caches the successfully authorized calculator HTML (including release data) only after every required asset is available. Canonical calculator navigation uses the network first and falls back to that exact cached release only on network failure. HTTP errors and authentication redirects invalidate the copy instead of falling back. Uncached offline releases receive a useful 503 fallback.
- The worker controls the app origin to observe authentication and Livewire responses, but only calculator pages and their required production assets are cached. Middleware identifies the current owner and release; logout, account changes, release replacement, and clearing invalidate private copies. A Livewire client event also handles streamed extraction, whose headers are sent before extraction completes. Preparation cannot publish stale data after a concurrent invalidation.
- Existing `sessionStorage` drafts continue to preserve exact Off time, starting FOB, ATA, and AFOB strings by owner/release/source signature. `visibilitychange` to hidden and `pagehide` synchronously flush inputs; component cleanup removes the listeners. Reset and unavailable-storage warnings retain their existing behavior.
- The calculator shows preparation, ready, unavailable, recovered-copy, and invalidated-copy states. Readiness requires worker control and successful page/asset preparation. It renders built assets through an isolated Vite instance even when `public/hot` selects development mode for other pages. Missing production builds, unsupported browsers, insecure origins, and cache failures do not claim readiness.

### Problem and implementation outcome (2026-10-06)

The previous implementation saved inputs but required a connection to reload the page, so a discarded Chrome tab could not reconstruct the calculator offline. The new cache supplies the page, release, and assets across a worker restart, and the existing draft restores its entered values. Chrome discards cannot be intercepted reliably; saving when the tab becomes hidden prepares for that lifecycle transition. [Chrome lifecycle guidance](https://developer.chrome.com/docs/web-platform/page-lifecycle-api?authuser=00).

A replaced or cleared release still produces a real server 404. That behavior and access enforcement are preserved, with cleanup preventing the cached copy from bypassing the response. The original automatic-refresh trigger was not reproduced in a real Chrome session.

Offline readiness requires HTTPS (or localhost), same-origin production assets, enabled browser storage, and a successfully prepared copy before losing connectivity. A reported unavailable state was traced to the original development-mode guard despite an existing production build. The calculator now uses that build without stopping Vite or changing the application's shared renderer. If no build exists, the development calculator still loads but has no offline asset list. Calculator frontend edits require a new production build. Closing the tab clears its entered draft; reopening can recover a prepared page but does not promise recovery of closed-tab inputs. [MDN service workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers), [MDN sessionStorage](https://developer.mozilla.org/en-US/docs/Web/API/Window/sessionStorage). A web app manifest is not required for reload recovery and was not added.

### Validation outcome

Sail became available for implementation. Boost's version-specific documentation search ran through its installed MCP server via Sail. Focused checks passed: 28 unique PHPUnit tests across `OfflineFuelScoreTest`, `OfflineFuelScoreCacheTest`, Livewire `SavedResultTest`/`ExtractionTest`, and `AuthenticationTest`; 46 unique JavaScript tests across the draft, lifecycle, worker, readiness/invalidation, and calculator files. Coverage includes worker restart with no network, all required assets, exact draft restoration, unauthorized/404/redirect responses, cache failures, missing snapshots, deployment asset changes, owner isolation, streamed mutation cleanup, and invalidation during preparation. Production Vite build and Pint passed. Full application Larastan ran once and reported two findings in the initial asset traversal; that traversal was corrected, followed by a passing focused class recheck and its two dependency tests. No browser automation was available, so real Chrome discard and visual verification remain unperformed. Unrelated TODO edits and the separate fuel-score variance task were preserved.

Commit message: `fix: recover flight plan fuel calculator after offline tab reloads`

Development-mode follow-up: 13 focused PHPUnit tests passed across `OfflineFuelScoreCacheTest`, `OfflineFuelScoreTest`, and `ViteDisabledTest`. The integration test uses a real Vite renderer with a hot file and build manifest, verifies built calculator tags match the cache header, and confirms the shared renderer and hot file retain development mode. Missing-build fallback also passes. Pint, one full Larastan run (zero errors), and the refreshed production build passed. Real browser readiness remains unverified.

Follow-up commit message: `fix: serve built calculator assets while Vite development mode is active`

Form-field follow-up: Off time, starting FOB, and repeated ATA/AFOB inputs were missing IDs and names, producing Chrome's form-element warnings. All fields now have IDs, names, and matching label associations; waypoint fields use the row index so repeated waypoint identifiers remain distinct. Six focused `OfflineFuelScoreTest` tests passed, including a markup regression checking identifier/name uniqueness across 50 waypoint rows. Pint and one Larastan run passed with zero errors. Chrome's Issues panel was not directly verified.

Form-field commit message: `fix: identify offline fuel calculator input fields`

## [x] Completed: Bug: Extracted crew list:

### Goal

Extract the four crew members from the applicable flight-release manifest in `CKS024201RJGG.pdf`, excluding unrelated Hong Kong runway-closure and contact information.

### Current implementation

`FlightCrewExtractor` prioritizes manifests bounded by `121-91 FLIGHT RELEASE I.F.R` and their status/release-time/fuel footer or the next release heading. It handles flattened and multiline rows, repeated release pages, empty initial manifests, and single-member manifests. The generic `CREW`/`CREW LIST` fallback accepts consecutive crew rows and stops at unrelated prose. Role-like text elsewhere in the document is no longer treated as an unheaded manifest. Existing name parsing, employee numbers, deduplication, and high-minimums annotations are retained.

### Problem

Extracted 
```text
AC
PHONE +852
Employee number:
#
2910
ISO
```
Expected:
`   70388 PIC MACDONALD T
   72480 SIC/FO  SINHA A
  ADDNTL
   71022 CAPT BRANDT-JENSEN J
   73425 IRP  TAYLOR K`

The current extracted text was from page 112 on a page about Hong Kong Runway closures. 

Fix: Ensure regex extraction comes from the applicable section or look for the surrounding context without breaking previous extraction tests.

` 121-91 FLIGHT RELEASE I.F.R
   70388 PIC MACDONALD T
   72480 SIC/FO  SINHA A
  ADDNTL
   71022 CAPT BRANDT-JENSEN J
   73425 IRP  TAYLOR K
IRP
       MX                              LM
       ACM                             ACM
       ACM                             ACM
       ACM                             ACM
       ACM                             ACM
   CIRCLE `

### Implementation and validation outcome (2026-10-06)

The supplied private PDF now extracts `MACDONALD T` / `70388` / `PIC`, `SINHA A` / `72480` / `SIC/FO`, `BRANDT-JENSEN J` / `71022` / `CAPT`, and `TAYLOR K` / `73425` / `IRP`. Regression tests cover competing NOTAM text before and after the release, repeated flattened manifests, bounded source evidence, unheaded false positives, unrelated prose under a crew heading, empty initial manifests, single-member manifests, and the supplied PDF's embedded text. Existing crew extraction tests remain passing.

Through Sail, 30 focused PHPUnit tests passed across `FlightCrewExtractorTest`, `CrewListParserTest`, `ExtractFlightPlanDataTest`, and the crew-containing serialization round trip in `SubdomainDataBuilderTest`. One separate private-PDF characterization test was skipped because its fixture is unavailable. Pint passed; one full Larastan run passed with zero errors. Boost's version-specific documentation search succeeded. Unrelated working-tree changes were preserved.

Previously saved releases must be uploaded/extracted again to replace the stored incorrect crew list. No frontend rebuild is required. The private-PDF regression test reads embedded PDF text; OCR and browser behavior were not separately exercised.

Commit message: `fix: scope crew extraction to the flight release manifest`

## [x] Completed: FP: Fuel Score: Include TOC, TOD
### Goal

Include source TOC and TOD rows in Fuel Score's waypoint list, in release order, with UTC ETA calculated from Off time and their cumulative duration just like fixes and FIR boundaries.

### Current implementation

`WaypointExtractor` now separates TOC/TOD rows within the computed flight plan, preserving source order and each row's TIME, T/TME, TBO, FRMG, and evidence. It handles multiline, CRLF, and flattened text, including markers before the first fix and labels joined to a preceding DSTN value or closing FIR arrow. Explicit marker coordinates are retained; absent coordinates stay null, and departure-field coordinates are not borrowed.

The waypoint DTO, extraction/serialization builders, and calculator payload support null coordinates for TOC/TOD. Builders continue requiring coordinates for fixes and FIR boundaries, and infer the existing phase kinds from TOC/TOD identifiers. Fuel Score's existing waypoint rendering, ETA calculations, and draft storage handle these rows without additional frontend logic.

Classification is centralized in `WaypointKind::fromIdentifier($identifier, $kind)`. The extractor and both builder paths reuse it; the builder's private classifier and the extractor's duplicate TOC/TOD branches were removed. TOC/TOD identifiers take precedence over supplied metadata, valid supplied kinds are retained for other identifiers, and missing, invalid, or non-string kinds fall back to `Fix`.

### Problem

The previous coordinate-only extraction and normalization omitted coordinate-less TOC/TOD, removing useful ETA and fuel comparison points. These rows now use their own cumulative duration and fuel fields. Missing cumulative time retains the calculator's unavailable ETA state; missing fuel remains absent instead of becoming zero or inheriting an adjacent row's data.

### Implementation and validation outcome

Planned and tagged `Current focus:` before implementation, then completed through Sail. Twenty-one unique focused PHPUnit tests passed across `WaypointExtractorTest`, `SubdomainDataBuilderTest`, and `OfflineFuelScoreTest`; the extractor's twelve tests passed again after adding departure-field coordinate isolation. Twenty-two focused JavaScript tests passed across `waypoint-fuel-monitor.test.js` and `offline-fuel-draft.test.js`. Coverage includes source order, optional coordinates, repeated markers, computed-section/alternate boundaries, null fields and explicit zeroes, JSON round trips, calculator output, UTC midnight rollover, and separate restored inputs for coordinate-less markers. Pint passed and one full Larastan run passed with zero errors. Boost's documentation search was attempted but its remote service was unreachable; official [Laravel 13 HTTP testing documentation](https://laravel.com/framework/docs/http-tests) was used as the fallback.

Previously extracted saved releases need their PDF uploaded/extracted again to populate TOC/TOD; the old normalized data cannot reconstruct omitted rows. No frontend asset rebuild is needed for this server-side extraction change. Real browser visual verification was not performed. Unrelated TODO edits were preserved.

Commit message: `feat: include TOC and TOD in fuel score waypoint estimates`

Enum follow-up: 35 focused PHPUnit tests passed across `Enums/WaypointKindTest`, `WaypointExtractorTest`, `SubdomainDataBuilderTest`, and `OfflineFuelScoreTest`. New enum tests cover identifier precedence, optional metadata, every valid kind, and malformed values. Pint and one Larastan run passed with zero errors. Boost's version-specific documentation search succeeded for this refactor.

Enum refactor commit message: `refactor: resolve waypoint kinds through the enum`

PDF follow-up (`storage/app/private/flight_releases/CKS024201RJGG.pdf`): the real embedded text joins rows as `0460TOC` and `FIR-> VHHK <-TOD`, with TOD's secondary row on the next page. The previous boundary rule rejected digits and hyphens before a marker, yielding 47 main-leg waypoints and no TOC/TOD. The boundary rule now recognizes completed DSTN fields and closing FIR arrows without splitting ordinary fix identifiers such as `1234TOC`. A regression fixture retains the PDF's actual computed flight plan text and page breaks. The failing-before-fix regression and calculator feature test now verify 49 main-leg rows, TOC between ESPAN/KEC (15 minutes, FRMG `0801`, TBO `0103`), and TOD between VHHK/MAGOG (174 minutes, FRMG `0400`, TBO `0505`). Both coordinates remain null and alternate rows stay excluded.

Twenty-one unique focused PHPUnit tests passed across `WaypointExtractorTest` and `OfflineFuelScoreTest`, including the successful real-text regression recheck; fourteen `waypoint-fuel-monitor.test.js` tests passed, including midnight rollover with this PDF's durations. Pint and one Larastan run passed with zero errors. Boost's documentation search succeeded. Previously saved normalized results still require uploading/extracting the PDF again; refreshing the old result alone cannot add omitted rows. Real browser visual verification was not performed. Unrelated crew-task changes and staging were preserved.

PDF fix commit message: `fix: extract joined TOC and TOD rows from flight release PDFs`
