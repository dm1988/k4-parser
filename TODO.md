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
## [x] Completed: Diagnose PDF OCR failures reported after the server migration
Outcome: The checkout already uses Poppler `pdftoppm` for page rendering; the reported Imagick/Ghostscript stack belongs to the earlier implementation. The historical cause remains unconfirmed because the failing PDF and Ghostscript stderr from the migrated worker are unavailable. Current OCR failures stop extraction without caching partial text. Renderer failure logs now record stage, one-based page, exception type, and exit code without recording the private PDF path or subprocess stderr.

Verification: The two focused extractor test files passed in Sail (13 passed, one skipped, 86 assertions); the skipped private ETOPS fixture is unavailable. The available private image-only fixture passed. A new regression verifies that a rasterization failure prevents partial text from being cached. A production smoke test on the actual failing PDF remains required.

Commit message: `fix(ocr): log safe page context for rasterization failures`

## [x] Completed: Include bug email if an error occurs on upload
Outcome: Flight Plan Brief and Schedule Extractor now offer a mailto link to report a possible bug beside upload errors. The schedule's partial extraction failure dialog offers the same link. The address comes from `MAIL_ADMIN_ADDRESS` through `config('mail.admin_address')`, and the link stays hidden when there is no error or no configured address.

Verification: Six focused PHPUnit tests passed (103 assertions), covering clean upload screens, invalid files, a processing exception, and partial schedule failures. Pint passed; Larastan passed once with zero errors.

Follow-up verification: Added explicit unexpected-fixture cases to three schedule test matches after the staged Larastan check flagged them. The two affected tests passed (33 assertions); Pint and Larastan passed with zero errors.

Commit message: `feat: offer bug report email on upload errors`

## [x] Completed: Replace Imagick PDF page rendering with Poppler
Outcome: `PdfImagePageTextExtractor` now renders one selected page to PNG with Poppler's `pdftoppm`, then reads that PNG with Tesseract. This removes the Imagick/Ghostscript rendering and its resource-cleanup issue. It validates both executables, converts zero-based page indexes to Poppler's one-based pages, and deletes both temporary files on success or failure. `PDFTOPPM_PATH` is configurable, and the Sail Dockerfile installs `poppler-utils`.

Verification: Five new extractor tests passed; the existing flight-plan text extractor tests passed against a local container with Poppler installed (12 passing tests, one skipped across the two files; 71 assertions). Pint passed; Larastan passed with zero errors. The full Sail image rebuild was not completed, so production must have `pdftoppm` installed at the configured path before deploying this change.

Commit message: `fix(ocr): replace Imagick PDF rendering with Poppler` (`669c59b`).

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

## [x] Completed: FP: Envelope: Include flight date in task

Completed (2026-10-13): Envelope Flight details now shows the confirmed flight date immediately after Trip number, labeled `Date`, formatted as `10/1/2026`. Missing dates show `Not confirmed`. Removed the Flight number metric from Envelope and moved Tail number before Aircraft type. The field order is Trip number, Date, Tail number, Aircraft type, Departure, Destination.

Reused the flight identity date and metric component with Envelope-specific formatting. Shared header and Maintenance date presentation remain unchanged.

Validation: All 68 focused Envelope feature and flight-release view-model tests passed through Sail (731 assertions), covering field/value order, unpadded dates, missing dates, and refresh persistence. Pint passed; one application-wide Larastan run passed with zero errors. No manual browser review was performed.

Sail was started using temporary `APP_PORT=8080` and `VITE_PORT=5174` overrides because ports 80 and 5173 were occupied; environment files were not changed. Boost documentation access failed with a closed transport, so the official Livewire 4 testing documentation was used as fallback.

Commit message: `feat: add flight date and reorder aircraft fields in envelope task`

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

### Goal

Group exported flight iCalendar notes into compact flight details, flight times, duty times, and crew sections, with less repetition and airport enrichment clutter.

### Current implementation

`IcsDescriptionFormatter` renders flight number, tail/aircraft, and position on one line, followed by the route with available IATA/ICAO codes. UTC flight times come from the actual event boundaries; local flight and duty times retain their source values. Same-day ranges abbreviate the end date; UTC ranges crossing a year include both years. Missing fields are omitted or shown as unavailable for partial ranges/counts, while explicit zero counts remain valid.

Crew names and roles use Unicode-aware column padding, optional bases are omitted when absent, and employee IDs remain unchanged. Existing crew extraction from raw lines remains supported. Airport names, cities, states, countries, resolution status, raw evidence, download metadata, and redundant counters are omitted from flight notes. Custom notes, airline context, and labeled slot times remain in an additional Notes section. Non-flight events retain their existing note layout, and calendar timestamps, URLs, escaping, and line folding remain covered by export tests.

### Problem

The previous formatter emitted almost every metadata item separately, repeated flight/time/crew labels, and mixed airport enrichment details into operational notes.

Previous extracted flight iCal note:
```
✈️ FLIGHT DETAILS
• ICN - HKG
• Destination iata: HKG
• Destination icao: VHHH
• Destination name: Hong Kong International Airport
• Destination city: Hong Kong
• Destination state: NT
• Destination country: HK
• Origin iata: ICN
• Origin icao: RKSI
• Origin name: Incheon International Airport
• Origin city: Seoul
• Origin state: 28
• Origin country: KR
• Flight number: CKS 256
• Position: AFO
• Aircraft: 77X
• Tail number: N774CK
• Block time: 4:00h
• Leg local start: Oct 08 01:00
• Leg local end: Oct 08 04:00
• Duty local start: Oct 07 23:00
• Duty local end: Oct 08 04:30
• Origin airport status: found
• Destination airport status: found

👥 CREW LOGISTICS
• Crew count: 4
• Operating crew count: 4
• Deadheading crew count: 0
• Crew Members:
  └─ Paolo Falco Beccalli (CP • PVD • #70978)
  └─ Ali Guner (FO • SRQ • #72446)
  └─ Eduardo Nunez Duarte (AFO • #72311)
  └─ David Gonzalez (AFO • #72860)

⏰ TIMES
• UTC start: 10-07 16:00 Z
• UTC end: 10-07 20:00 Z
```

Implemented layout (illustrative crew IDs):
```
✈️ FLIGHT DETAILS
Flight: CKS 256  |  Tail: N774CK (77X)  |  Pos: AFO
Route: ICN (RKSI) ➔ HKG (VHHH)

⏰ TIMES
UTC:   Oct 07 16:00z ➔ 20:00z  (Block: 4:00h)
Local: Oct 08 01:00  ➔ 04:00

👨‍✈️ DUTY TIMES
Local: Oct 07 23:00  ➔ Oct 08 04:30

👥 CREW (4 Ops / 0 DH)
• Paolo Falco Beccalli  │ CP  │ PVD │ #70999
• Ali Guner             │ FO  │ SRQ │ #72999
• Eduardo Nunez Duarte  │ AFO │ #72999
• David Gonzalez        │ AFO │ #72999
```

### Validation outcome (2026-10-12)

All 34 focused formatter, ICS serializer, roster export, and flight-duty export tests passed through Sail. New formatter coverage verifies the compact layout, omitted airport enrichment, explicit UTC/local labels, timezone offsets, day/year rollovers, partial and invalid dates, missing fields, zero/unknown crew counts, source employee IDs, Unicode alignment, deadheads, and retained custom/slot notes. Pint passed for this task's PHP files; one application-wide Larastan run passed with zero errors. Boost's version-specific documentation search succeeded. Existing staged and unrelated changes were preserved. Existing parsed results use the new layout on their next calendar download; already imported calendars need the newly exported file.

Commit message: `feat: compact schedule calendar notes into operational sections`

## [x] Completed: Schedule: Flight radar option

### Goal

Centralize flight-tracker URL generation and let users choose FlightAware or Flightradar24 for schedule links and iCalendar exports.

### Confirmed choices

- Apply the preference to both schedule flight cards and iCal exports.
- Prefer the tail number; use the flight number when the tail is unavailable, including commercial deadheads.
- Use the current user preference when viewing or downloading previously parsed schedules. Already imported calendar files require a new export to change their links.

### Current implementation

- `FlightTracker` defines the two supported choices, labels, and FlightAware fallback for unset/invalid stored preferences. Profile validation rejects unsupported, empty, or malformed submitted choices and preserves the preference when another profile field is updated.
- A new reversible migration adds `users.flight_tracker_preference` with the database default `flightaware`; the User model mirrors this default and includes the preference in its fillable attributes. The profile form includes a labeled select, helper text, validation errors, and light/dark styling.
- `FlightTrackerUrlGenerator` is the shared source for extraction's legacy FlightAware URL, current flight-card links, and the iCalendar URL property. Configured roots live in `services.flightaware.url` and `services.flightradar24.url`, exposed as `FLIGHTAWARE_URL` and `FLIGHTRADAR24_URL` in `.env.example`. Defaults work without editing a local `.env`.
- Tail identifiers preserve registration hyphens and use each provider's expected case. Flight-number links use ICAO airline prefixes for FlightAware and IATA prefixes for Flightradar24. Company codes use existing schedule preferences; commercial conversions reuse the bundled airline-code data through `AirlineCodeLookupClient`, with no database queries during tracker URL generation. The service is scoped per request to reuse its lookup data.
- Unknown ICAO-to-IATA mappings fall back to a FlightAware flight-number URL and label. Missing or invalid identifiers produce no tracker link. Generic duties and layovers have no tracker URL.
- Tracker links are separate from calendar download URLs. `FlightMapper` no longer overwrites tracker metadata with a per-event iCal download URL, and aircraft types such as `77X` are no longer treated as tail registrations. Existing cached schedules use their structured identifiers and current preference instead of trusting an old stored URL.

### Problem

FlightAware URLs were repeated in extraction and Blade, users could not select another tracker, and flight DTOs reused `downloadUrl` for both tracker links and calendar downloads. That could place an application download URL in the iCalendar URL property. Flights without tails also lacked a tracking link.

### Validation outcome (2026-10-12)

Through Sail, all 123 focused URL-generator, profile, User/Flight DTO, parser, view-model, flight-card, cached roster export, ICS serialization, and flight-duty export tests passed with 705 assertions. Coverage includes both providers, company/commercial flight numbers, hyphenated tails, configured URL roots, invalid/unset preferences, missing identifiers, unknown airline fallback, saved preference changes affecting cached exports, and distinct calendar download links. Pint completed successfully; one application-wide Larastan run passed with zero errors; the production Vite build passed. Profile/card markup was verified programmatically; no manual browser review was performed.

Boost's version-specific documentation and users-schema checks succeeded. The migration is recorded as applied in local Sail, and a read-only aggregate confirmed both existing development users have the FlightAware default. Other deployment databases must run the new migration before using the profile setting. Dependency files and unrelated TODO edits were preserved.

The provider URL formats were checked against [Flightradar24 aircraft history](https://www.flightradar24.com/data/aircraft/n772ck), [Flightradar24 flight history](https://www.flightradar24.com/data/flights/k4240), and [FlightAware flight tracking](https://www.flightaware.com/live/flight/CKS240).

Commit message: `feat: add flight tracker preferences for schedule links and calendar exports`

## Completed: Security email
Goal: Send a clear update about the reported eight-and-a-half-hour outage associated with a php2shell remote code execution vulnerability, then introduce the Flight Plan Brief and other meaningful changes since the previous user email.

Current implementation: The previous user announcement is `VersionOneAnnouncement` and its `mail:version-one` command. That email described Schedule Extractor v1. Git history shows the announcement code was added on 2026-08-03, but does not establish when the full email was sent. The command's recipient rule is active users with nonempty email addresses, including unverified users; it queues individual messages and guards against a duplicate full send.

Problem: The incident details and approximate previous-send time are recorded below. The corrected 08:00–16:30 window spans eight and a half hours. The incident date, impact assessment, production patch status, and delivery record still need confirmation. Do not imply that user data was or was not accessed without evidence.

Plan:
1. Confirm the incident record before writing final copy: affected service and time window with timezone, actual downtime, the vulnerability name and how it affected this deployment, containment and remediation completed, whether any user data or accounts were affected, and any action users need to take. Record what remains unknown and give a follow-up date or channel if the investigation is still open.
   * From 08:00 to 16:30 Central time (eight and a half hours); confirm the incident date and whether daylight saving time applied before using a CST/CDT abbreviation.
   * CVE-2026-109618 and CVE-2026-109619
   * Upload-progress unserializer use-after-free
   * Remote process-memory disclosure
   * Fatal-error backtrace use-after-free
   * OPcache type confusion
   * Only affect was downtime, no server compromises, no unauthorized access
   * Patches and updates in place
   * No user data or accounts affected
   * No user action needed
   * Verify the technical descriptions against the PHP security advisories before using them in public copy; verify the deployment and incident records separately for the claims about this service.
2. Find the actual send date and content of the last user email from delivery or queue records. Use that date as the start of the feature audit; the 2026-08-03 source commit is only a provisional lower bound. Compare deployed releases and user-visible behavior, not just commit titles.
    * Version one announcement sent around Mon, Aug 3, 3:51 PM

3. Build a short, verified change list. Lead with the new Flight Plan Brief and what a user can do with it. Candidate changes to check: saved latest flight release, overview and task navigation, weight and balance and ETOPS summaries, offline waypoint fuel calculator, dispatcher notes, and schedule flight-tracker preferences, calendar notes, and reserve badges. Omit anything not live for recipients or already announced.
4. Draft one email with this order: incident and apology; restoration and current status; impact and required user action (or an explicit statement that the investigation is ongoing); concise Flight Plan Brief introduction; up to three additional changes; support contact and expected follow-up. Keep the security subject and opening factual; do not let the feature announcement obscure the incident.
5. Review the draft against the incident record and deployed features. Preview the rendered email and links, confirm the intended recipients and mail configuration, and use a guarded, one-time send with delivery monitoring. Record the send date, audience, final copy, and any follow-up needed in this section.

Completion criteria: Incident claims are approved against evidence, the feature list is verified from the last actual send, recipients and links are checked, and the email is sent once with a recorded outcome.

Implementation outcome: Added a queued security update mailable and Markdown email, plus `mail:security-update` with a single-address review option, confirmed full-audience send, staggered broadcast delivery, and a duplicate-send guard. The copy leads with the incident, states the supplied impact assessment and the corrected eight-and-a-half-hour outage, and highlights Flight Plan Brief and selected schedule improvements. The product button leads to the public page so account-specific feature access is handled there.

Verification: Five focused PHPUnit tests passed (32 assertions). Pint passed for the changed PHP files, and Larastan passed once with zero errors.

Pending send: Confirm the incident date, impact assessment, production patch status, last mailing record, and which highlighted features are deployed for recipients. Review a rendered copy and the production audience before queueing the full send. This Sail checkout uses `APP_ENV=local`; run the actual send in production and record its delivery outcome here afterward.

Commit message: `feat: add security update email and guarded send command`
