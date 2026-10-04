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
## [x] Completed: Sloppy static findings

### Goal

Review Sloppy's findings, fix actionable error handling and duplication, and reduce parsing/formatting complexity while preserving operational values and existing presentation contracts.

### Current implementation

Shared fuel deserialization now lives in `FuelQuantity::fromArray()`; DTOs and mappers reuse the immutable `StringList` normalizer. Flight Init and Takeoff/Landing extraction reuse `TakeoffLandingReportSections`. Calendar serialization delegates description formatting to `IcsDescriptionFormatter`. Trip parsing delegates roster sections/summary to `TripInformationSections` and duty/flight matching to `TripDutyFlightContext`.

### Problem

The original scan reported 62 findings: 26 high, 13 medium, and 23 low. It found silent operational fallbacks, repeated normalization, mixed parser responsibilities, and comments restating code.

### Implementation outcome

- Report airline database failures and image-preprocessing failures while preserving bundled-airline/original-image fallbacks. Optional schedule DTO export failures are reported and display a warning while retaining parsed JSON output.
- Narrow date/value parsing catches to expected validation exceptions so unrelated runtime errors propagate. Document why invalid ETOPS entries are rejected independently and why optional diagnostics must not interrupt extraction.
- Consolidate all nine duplicate-logic findings and remove all five narrative-comment findings. Separate calendar descriptions from serialization and roster structure/context from event parsing.
- Split all five flagged long methods into their distinct jobs: PDF page reading, individual slot extraction, slot comparison/alerts, flight-detail construction, and pasted-text processing. Preserve PDF caching/progress/timing, source evidence, slot order/deduplication, missing-data behavior, calendar formatting, and slot-window thresholds.
- Review the remaining findings individually. No dependency packages or Sloppy thresholds/rules were changed. The optional [agent-skills repository](https://github.com/asyrafhussin/agent-skills) was not needed; the installed Laravel best-practices skill covered this work.

### Final scan and retained findings

`vendor/bin/sail php vendor/bin/sloppy scan --all --no-baseline --format=json` reports **35 findings: 5 high, 4 medium, 26 low**, with a score of **95** (originally 81). There are zero duplicate-logic, narrative-comment, and long-method findings. All high-severity swallowed-exception warnings are cleared.

| Remaining rule | Count | Review decision |
| --- | ---: | --- |
| SL107 Swallowed Exception | 26 low | Narrow parse-or-reject helpers deliberately return null or omit invalid entries; optional PDF logging/Debugbar failures must not break extraction. Missing operational data remains unavailable. |
| SL102 God Class | 5 high | The three flight-plan extraction/building classes coordinate one workflow across typed sections; the two view models expose existing presentation APIs and delegate to presenters. Retain these boundaries rather than add dependency bags or break template contracts solely to reduce a metric. |
| SL207 Excessive Service Dependencies | 3 medium | The same extraction/building orchestrators legitimately coordinate specialized section extractors/builders. Retain explicit constructor injection. |
| SL209 Model Doing Too Much | 1 medium | `User::sendEmailVerificationNotification()` is Laravel's verification-notification customization hook; retain its existing OTP behavior. |

A `.sloppy-baseline.json` containing these 35 findings was added concurrently during this task and preserved. The normal scan passes with **zero new findings**; the unbaselined report above records actual remaining findings rather than presenting them as eliminated.

### Validation outcome

- Focused Sail integration across 24 affected PHPUnit files: 225 tests, 222 passed, two skipped because private PDF fixtures are unavailable, and one private ETOPS OCR test exceeded its existing 30-second Tesseract timeout. That test passed in isolation (one test, two assertions). The final updated TripInformationParser file also passed all 18 tests, including the added regression proving absent local-time keys are omitted.
- Coverage includes fuel unit aliases/zero/invalid amounts, list normalization, repeated report headings, invalid dates/slots, database and OCR fallback reporting, CLI warning/output, roster year rollover, matched/unmatched duty context, calendar exports, builders, and Livewire schedule extraction.
- Pint passes after the PHP changes.
- Larastan ran once over the configured application paths. It reported one PHPDoc error in the extracted duty-context helper: local-time keys were declared required although the method omits unavailable times. Corrected the annotation to optional string keys and verified the behavior with the focused regression. Larastan was not rerun, honoring the one-run limit.
- `git diff HEAD --check` passes. Unrelated TODO edits and the concurrently staged work were preserved.

Commit message: `refactor: address actionable Sloppy static findings`


## Paused: Flight release: 24 hour time limit

### Goal

Expire only the cached flight-release result 24 hours after successful extraction. Remove its saved `flight_plan_results` row and any cache entries belonging specifically to that result, and stop serving it through the brief or Fuel Score URL.

### Current implementation

- `FlightPlanResultStore` keeps one encrypted `flight_plan_results` row per user. `save()` replaces that row; `get()` checks owner and key, and `latest()` checks owner, but neither checks age. The row has timestamps, but no explicit extraction or expiry timestamp.
- `FlightPlanBrief` loads the latest row on mount and retrieves it on render. `OfflineFuelScoreController` retrieves the same row by key. Both rely on the store, so expiry enforcement belongs there.
- `FlightPlanTextExtractor` caches PDF text for seven days under a file-hash key. This cache is independent of the saved result and can be shared by identical uploads; it has no owner or result-key mapping. The uploaded PDF is deleted after extraction.
- Existing scheduling in `routes/console.php` can run cleanup of expired cached results.

### Problem

An old cached result remains readable until the user replaces or manually clears it. `updated_at` is not a reliable extraction clock if a row is later touched. Expiration must apply to the saved result rather than the independent, shared PDF-text extraction cache.

### Implementation plan

1. Add an immutable extraction timestamp and an indexed expiry timestamp to `flight_plan_results`. Set both when a successful extraction is saved, including when the user's existing row is replaced. Compute expiry as extraction time plus 24 hours, independent of the flight schedule. Compare instants in UTC and define expiry at the deadline (`now >= expires_at`).
2. Make `FlightPlanResultStore::get()` and `latest()` exclude expired rows and delete a matching expired row when encountered. Keep owner and result-key checks in place. The brief should return to its upload state after expiry; the Fuel Score URL should return 404. Clear any stale Livewire result key or selected task when a rendered result expires so the UI does not retain a link to it.

3. Inventory cache entries belonging specifically to the saved result and invalidate those with the result. Preserve the independent shared PDF-text cache and its existing seven-day lifetime, along with airport and schedule caches. No source-document or other application data cleanup is part of this task.
4. Document the 24-hour cached-result lifetime in the upload/result UI with a concise UTC-aware message, including that re-upload is needed after expiry.

### Acceptance criteria

- A cached result is accessible before extraction time plus 24 hours and unavailable at or after that deadline, including exact-boundary and midnight rollover cases.
- Flight schedule values never shorten or extend the cached-result lifetime. Replacing a result starts a new extraction clock and invalidates the old key.
- Expired data is removed from the database by scheduled cleanup even without another request. Brief and Fuel Score reads deny an expired result immediately, regardless of whether cleanup has run.
- No cache entries belonging specifically to the expired result remain available. Shared PDF-text, airport, and schedule caches retain their existing behavior and lifetimes.
- Ownership checks, encrypted storage, and the normal upload/error flows continue to work.

### Validation for implementation

- Add focused store tests using a frozen clock for the 24-hour deadline, exact equality, midnight rollover, schedule-independent expiration, replacement, ownership, and deletion on read.
- Add focused Livewire and Fuel Score tests for expiry transitions and a cleanup-command test for unattended expiration and repeat runs.
- Run only affected tests through Sail, Pint after PHP changes, and Larastan once at the final integration checkpoint. Record the outcomes here.

### Planning outcome

Restricted expiration to the cached flight-release result and its own cache entries, with a fixed 24-hour lifetime after successful extraction. Removed arrival-time expiration and shared PDF-text cache changes. This update changes documentation only; the task remains paused.

Commit message: `feat: expire cached flight release results after 24 hours`

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
Refactor the Flight Plan Brief header and action controls in our Livewire component and Blade view:

1. **Header Action Integration:**
   - Move the "Extract another flight plan" button into the top-right corner of the active `Flight Plan Brief` header card.
   - Add a "Clear results" button adjacent/stacked with "Extract another flight plan".
   - Stack both buttons vertically in a subtle button group (or ghost button style) to save horizontal space and avoid crowding the route details (`CKS272`, `SBKP -> SCEL`).
   - Use clean inline SVG or Heroicons for both buttons:
     - `Extract another`: Import/upload icon (e.g., `arrow-up-tray` or `document-plus`).
     - `Clear results`: Close/trash icon (e.g., `x-mark` or `trash`).

2. **State & Header Behavior:**
   - Keep the main "Flight Plan Brief" title visible at all times, but ensure layout hierarchy remains clean.
   - Add a Livewire/Blade action for `clearResults` that resets the active flight model state.
   - When no flight plan is loaded (cleared state), display a clean empty-state upload container under the main title.
   - Convert the header to a flex container (`flex justify-between items-start`) to support a split layout between branding and actions.
   - Implement a `flex-col` button group in the top-right corner to stack actions without interfering with the primary typography.

3. **Styling & Theme Alignment:**
   - Maintain the current Tailwind CSS dark theme (`bg-slate-900`/`bg-slate-800` cards, subtle borders, accent colors).
   - Ensure buttons are responsive and collapse gracefully on mobile viewports.
   - Applied ghost-style borders (`border-[#F8F9FA]/20`) and hover transitions (primary CTA for extraction, transparent red for clearing) to align with the dark theme aesthetic.

### Header refactor outcome

Moved extraction into the main Flight Plan Brief header beside a new Clear results action. Both use stacked ghost buttons, decorative Heroicons, visible keyboard focus, disabled/loading states, gold extraction hover, and a subtle red clearing hover. The title stays visible in every state; actions move below the branding and fill the available width on mobile, leaving the flight/route summary separate.

Added an authorized `clearResults()` action using the existing owner-scoped result deletion and upload reset. Both header actions reset the result key, file, selected task, completion flag, and validation errors; task pages navigate to the upload URL. Clearing persists across reloads and preserves other users' results.

### Header refactor validation

The five focused PHPUnit files pass through Sail: SavedResultTest, UploadTest, AccessTest, ExtractionTest, and FlightPlanTaskRouteTest (32 tests in total). Coverage includes header action placement, persistent title/upload states, owner isolation, repeated clearing, validation reset, authorization, and embedded/task-route transitions. Pint passes. The final focused Larastan check passes with zero errors after correcting a test assertion-chain type issue found by the initial integration run.

Headless Chrome reviewed the actual rendered header in 375px light/dark frames and a 960px light frame using the existing CSS bundle. The header/actions fit without horizontal overflow, stack on mobile, and split horizontally on desktop. This was an isolated layout preview, not an authenticated interaction audit; the old bundle does not include every new utility.

`vendor/bin/sail npm run build` fails because installed Tailwind 4 is configured as a Tailwind 3 PostCSS plugin. Dependency/build configuration changes are outside this task and were left untouched; rebuilding and checking the final generated CSS remain blocked by that existing mismatch.

Commit message: `refactor: integrate flight plan header actions and clear results`

## [x] Completed: Remove results output near header:
Remove `Flight plan brief ready. Upload and extraction completed successfully.`

resources/views/livewire/flight-plan-brief.blade.php

## [x] Completed: Flight plan: Weight & Balance: Progress bar readability

### Goal

Adopt the Chrome DevTools prototype's clean hierarchy and improve colors across Weight & Balance bars, overview alerts, and badges. Preserve all existing classification thresholds, rounding, labels, alert counts, and severity ordering. No animation or transitions. Complete the authorized Tailwind 4 migration to resolve the Vite/PostCSS build failure.

### UI Overhaul: Weight and Balance Progress Bars

Implemented the preferred prototype: external labels, a prominent monospaced planned value with its unit, an uninterrupted 8px (`h-2`) rounded native progress bar, and the structural limit with its unit below. Status wording remains visible so color is not the sole cue. Mobile retains the preferred title/percentage layout. At desktop widths (`xl`), cards with content widths up to `28rem` move the percentage directly above the bar; wider cards retain the right-aligned header percentage. CSS displays only one percentage at a time.

### Gemini enum review outcome

Retained the proposed emerald / sky / amber / rose palette, tinted badges, and subtle colored accents, with darker light-mode shades for readable text and meaningful bar graphics:

| Existing state | Existing rounded utilization | Text: light / dark | Native fill: light / dark |
| --- | --- | --- | --- |
| Safe (normal) | Below 90% | emerald-700 / emerald-400 | emerald-600 / emerald-400 |
| Heavy | 90% to below 98% | sky-700 / sky-400 | sky-600 / sky-400 |
| Caution | 98% through 100% | amber-700 / amber-400 | amber-700 / amber-400 |
| Exceeded (over-limit warning) | Above 100% | rose-700 / rose-400 | rose-600 / rose-400 |

Gemini's proposed thresholds were rejected; enum cases, labels, operational-alert behavior and severity ordering remain unchanged. Muted limit text uses slate-600/slate-400. Text and badges meet [4.5:1 contrast](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html); native fills meet [3:1](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html) against their tracks. Overview cards retain Aviation Blue structure with subtle slate perimeter borders and state-colored left accents/tints.

### Implementation outcome and validation

- Updated the shared Blade component, view-model percentage label, enum presentation classes, and theme-aware native WebKit/Firefox fills. Removed overlay text and Heavy's hard-coded blue override; badges now use independent tinted backgrounds. Added no animation or transitions.
- Preserved one-decimal rounding before classification, existing thresholds/labels/severity/alert counts, progress clamping at 100% with actual exceeded percentages in visible and accessible text, units, and unavailable/conflicting source states.
- Sail focused PHPUnit checks passed: 34 tests, 443 assertions across component rendering, palette contrast, field calculations/boundaries, Livewire Weight & Balance, and affected overview assertions. Pint passed; final targeted Larastan passed with zero errors.
- Completed the authorized Tailwind 4 migration using the installed Vite plugin. Replaced legacy PostCSS/JavaScript configuration with CSS imports, explicit Blade/PHP/JavaScript source paths, the forms plugin, Figtree font, and manual `.dark` mode. Kept the reviewed slate/status palette and existing shadow, radius, blur, border, cursor and placeholder defaults. Migrated focus classes to `outline-hidden` to retain forced-colors accessibility. Updated the project version in AGENTS.md.
- Production `sail npm run build` passed. Eight focused Node tests passed, including actual Vite compilation and existing theme behavior. The new build regression covers PHP enum classes, dark badges, forms, forced-colors focus outlines, native fills and the correctly prefixed dark WebKit track selector.
- Chrome checks passed in eight light/dark/mobile/desktop cases, including a viewport equivalent to 200% zoom reflow and doubled root font size: one visible percentage, correct placement, no clipping/overflow, preserved forms/focus styles and no bar transitions. Minimum measured text/badge/fill contrast: 5.02:1 / 4.84:1 / 3.44:1. Desktop screenshots verified native fills and dark tracks. Firefox runtime checks were unavailable locally.
- Main readability implementation, Tailwind 4 migration, and both follow-ups below are complete.

Implementation commit message: `refactor: migrate to Tailwind 4 and improve weight progress readability`

### [x] Completed follow-up: `Estimated landing weight` card height

Single-field groups align at the top at `xl`, so Arrival and its landing card size to their content instead of stretching to the tallest neighboring column. The landing value, percentage, bar and limit stay together without a fixed height cap. Multi-field column sizing and the mobile stacking behavior are preserved.

### [x] Completed follow-up: Remove `Planned` from each W&B card

Removed the repeated `Planned` label and its value's extra top margin from the shared field component. The section's planned-weight explanation remains; field headings, values, units, limits, statuses and accessibility data are preserved.

Follow-up validation: Sail's nine focused component/Livewire tests passed (201 assertions), Pint passed, and the production Vite build passed. Six Chrome geometry checks passed across desktop/mobile/light/dark and enlarged-text/reflow cases: compact landing card, unchanged neighboring column sizing for the height fix, no clipping/overflow, all seven fields retained and zero repeated `Planned` labels.

Follow-up commit message: `fix: compact weight cards and remove redundant planned labels`

References:
resources/views/components/flight-release/weight-balance-field.blade.php
