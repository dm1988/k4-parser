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

Organize K4 Bids within the app and define a clean user-facing workspace for monthly package comparison and bid ranking.

### Current implementation

Implemented the initial K4 Bids workflow (2026-10-26). Added protected desktop/mobile navigation after Flight Plan Brief, enabled/admin-only feature settings, admin import preview/publication, encrypted shared package payloads, and owner-scoped encrypted preferences/lists. B747/B777 and CA/FO selection follow imported crew complements. Admins can import supported Lines/Trips PDFs and Overview .xlsx files without adding dependencies; temporary source uploads are deleted after preview, while normalized drafts/revisions are retained privately.

Added compact expandable timeline rows, guided/skippable preferences, strict goal priorities, UTC date/range and start-city exclusions, and basic credit/days-off/selected filters. Incomplete dates retain their warning; selected conflicts remain in the list. Flying and reserve choices share accessible manual/drag ordering, autosave with optimistic revisions, save-error recovery, numbered review/copy, and revision-change review before carrying choices forward. Desktop uses a drawer; mobile uses a full-screen list. Reserve identifiers/occupied markers are orange; flying occupancy is blue; unknown dates show `?`. Source times and blank Overview dates were confirmed as UTC and days off respectively.

### Problem addressed

The app previously had no workspace for turning monthly bid packages into private ranked choices. `CREW_BIDS.md` records the agreed workflow, decluttered information hierarchy, and deferred enhancements. The implementation handles null-padded PDF text, multi-page trips, and the Overview reserve sections' shifted date columns.

### Planning history

The following notes record the earlier documentation-only decisions; implementation and validation outcomes are recorded below.

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

### Integration outcome

- Sail access verified using elevated Docker access. Both new migrations are installed locally; migration status confirmed. No source package was automatically published for users.
- Focused integration: 31 tests passed (139 assertions), covering Crew bidding and existing route-access behavior. After the final revision-preview/import-recovery refinement, its two focused tests passed (16 assertions), including one additional test. Private B747 October/B777 November package imports passed; synthetic coverage checks reserve-column alignment, FO-only complements, midnight/year boundaries, layover occupancy, strict goals, work blocks, ownership, conflicts, revision migration, save recovery, and failed imports.
- Three focused JavaScript tests passed for exact clipboard content, manual-copy fallback, and navigation warnings. Production Vite build passed, including the requested reserve colors. Pint passed after PHP edits. One application-wide Larastan run found eight issues in three new files; corrected null handling and redundant checks, then rechecked those files with zero errors. `git diff --check` passed.
- No manual browser review was performed. Responsive appearance, actual drag/touch interaction, focus restoration, and real clipboard permissions remain to be checked in a browser. Native-text PDFs are supported; OCR/scanned PDFs, extended secondary filters, personal training/vacation imports, pay estimates, and spreadsheet exports remain deferred. No dependencies changed; unrelated TODO edits and `.nvmrc` were preserved.

Commit message: `feat: add crew bidding imports and private ranked bid workspace`

### Follow up #1 outcome (2026-10-27)

Line calendars now have a compact `+N` box for occupied UTC dates after month-end. Linked trip dates determine the count, with duplicate/overlapping dates counted once and the source carry count retained when trip details are unavailable. Monthly days-off and work-block calculations retain their existing scope.

Trip badges below each calendar show start/end airports, source-backed layover airports, and minimum recorded rest. Each badge expands an inline preview with the route, dated UTC legs, overnight arrivals, report/release, deadheads, block times, and rest durations. The line's Details accordion also includes these trip details. Imports now retain per-leg flight identifiers, block times, and rest; previously published packages need reimporting to populate those new fields. Missing trip/hotel information is explicit; the supplied Trips layout contains no hotel details.

Added a keyboard-accessible `Why this matched` disclosure beside line IDs when preferences apply. It explains strict goal priority, actual credit/days-off/block values, and hard-filter results without inventing a percentage score. Follow up #2 remains outside this task.

Validation: Focused Crew Bids feature tests passed (21 tests, 107 assertions), and import tests passed (8 tests, 62 assertions), including private B747 October/B777 November samples, page-spanning trips, missing blocks/rest, duplicate pairings, overlapping carry days, and year boundaries. Production Vite build and Pint passed. One full Larastan run identified an unhandled goal in the new explanation; corrected its fallback, reran the affected priority test (one test, seven assertions), and passed a focused static-analysis recheck with zero errors. No manual browser review was performed. Preserved the user's edits to CREW_BIDS.md.

Commit message: `feat: add carry-day counts and inline crew bid trip previews`

### Follow up #2: Sticky header outcome (2026-10-27)

Made the K4 Bids title, package selector, seat selector, My bid list, and admin Manage package control sticky at the top while scrolling. The wrapping header uses opaque light/dark backgrounds, a subtle divider/shadow, and stays below the bid-list dialog. Sorting/filter controls now scroll normally so they do not overlap the sticky header. Other Follow up #2 items remain pending.

Validation: The focused saved-order/reload feature test passed (one test, nine assertions), production Vite build and Pint passed, and one Larastan run passed with zero errors. No new tests were added for this layout-only change. Manual browser review remains pending. Existing Follow up #1 changes and the user's CREW_BIDS.md edits were preserved.

Commit message: `style: keep crew bidding header visible while scrolling`

### Follow up #2: Flight metrics and match placement outcome (2026-10-27)

Moved `Why this matched` after Credit and Days off, matching the requested card-header order. Its disclosure anchors within the card on narrow screens and aligns to its trigger's right edge on larger screens.

The Trips parser now accepts whitespace between arrival and per-leg block values. Trip previews preserve source blocks and label calculated scheduled blocks when an operating leg has only dated UTC times. Deadhead/identified ground transport retain separate scheduled elapsed durations without inventing an operating block. Existing published trips can show calculated durations without reimporting; reimport is needed to recover original source-only values.

Added airport-specific layover durations from arrival to the next departure, including overnight and year-boundary connections. Source rest remains separate. Zero-length connections remain explicit; overlapping times and airport changes show unavailable layovers with the reason. These changes address missing metrics in line-card trip previews and expanded Details; visual timelines and other Follow up #2 enhancements remain pending.

Validation: 33 focused Crew Bids/import tests passed (190 assertions), including private sample packages, spaced source blocks, legacy payloads, source-value precedence, midnight/year boundaries, zero gaps, invalid connections, transport legs, and card-header order. Production Vite build, Pint, and one Larastan run passed with zero errors. Changed-file diff checks passed. Manual browser review remains pending. Prior work and user edits were preserved.

Commit message: `feat: show crew bid block and layover durations beside clearer match controls`

### Follow up #2: Visual leg timeline outcome (2026-10-27)

Trip previews and expanded line Details now include a horizontal chevron sequence with UTC dates, airport pairs, elapsed durations, and overnight arrival dates. Flying is solid blue, deadheads are striped grey, ground transfers have a separate label, and layovers use pale gold. Native expandable segments reveal exact UTC times, block values, and available source rest. The focusable strip supports horizontal scrolling on narrow screens. Widths are explicitly illustrative; source rest remains separate from layover intervals. Inconsistent connections and absent legs have explicit states. Full textual leg details remain available below the graphic.

Validation: The final focused Crew Bids run passed 25 tests (137 assertions), covering transport classification, midnight/year boundaries, zero layovers, inconsistent connections, empty leg data, and timeline rendering. Production build, Pint, and one Larastan run passed with zero errors. No manual browser review was performed. Prior work and user document edits were preserved.

Commit message: `feat: visualize crew bid trip legs and layovers`

### Follow up #2: Sticky expanded-trip actions outcome (2026-10-27)

The existing line header and Add/Selected control become sticky while a trip or the line's Details accordion is expanded. The bar stays inside its card, below the workspace header and bid-list dialog, with opaque light/dark backgrounds. A ResizeObserver measures the workspace header after initialization and wrapping/context-size changes; Alpine cleanup disconnects it when leaving the component. Native accordion state controls stickiness without duplicating actions or changing saved bid order.

Validation: All five focused JavaScript tests passed, including dynamic header sizing, observer cleanup, and leaving before deferred initialization. The final 25-test Crew Bids run also covered saved-order/reload behavior. Production CSS was checked for the expanded-only sticky selector and measured top offset; build, Pint, Larastan, and changed-file diff checks passed. Manual scrolling/touch/browser review remains pending.

Commit message: `style: keep expanded crew bid actions below the workspace header`

### Follow up #3: Consolidated trip details outcome (2026-10-27)

Replaced stacked trip-detail sections with a compact route/date/metrics summary and three independent tabs: Timeline / Legs, Layovers & Rest, and Pairing Metadata. Long routes truncate in the summary and remain readable in metadata. Leg rows show transport type, route, UTC times, and block duration; exact dates and calculation evidence expand on demand. Layover intervals and source rest now share one compact two-column breakdown rather than repeating below each leg.

The user confirmed an under-three-hour browsing highlight. Connections below 180 minutes appear in the short group with an amber cue; exactly three hours and longer appear separately. Grouping uses duration because domestic/international status is not established by the imported data. Source rest remains distinct from scheduled connection intervals. Tabs support arrow keys, Home/End, roving focus, unique per-preview IDs, and independent state. Existing sticky actions and visual timeline remain available.

Imports now capture source pairing duty days and crew counts for the summary/metadata. Legacy missing values remain explicit. Corrected the earlier reimport guidance: duplicate-source detection previously retained the published payload, so parser-versioned fingerprints now allow a fresh draft revision while preserving the prior published package and private bid lists. Repeated imports with the current parser still reuse the draft. No source files were published automatically.

Validation: 36 focused Crew Bids/import tests passed (225 assertions), including the three-hour boundary, source/legacy metadata, page-spanning trips, and versioned reimport/draft reuse. Seven focused JavaScript tests passed for tab keyboard focus, independent previews, observer cleanup, and existing copy/navigation behavior. Production build, Pint, one Larastan run (zero errors), and changed-file diff checks passed. Manual browser review remains pending. Prior work and the user's document edits were preserved; Follow up #4 was not implemented.

Commit message: `feat: consolidate crew bid trip details into compact tabs`

### Follow up #4: Separate block and credit outcome (2026-10-27)

Line cards, trip summaries, and selected bid entries now distinguish operating Block time from Credit. Highest block time and Highest credit sort independently, retaining manual bid order. Credit goals, filters, and sorting use exact seconds; the display adds seconds only when needed. The contiguous occupied-day metric is explicitly labeled Work blocks.

Complete Trips PDF Block Time and Credit Time totals take priority over Lines/Overview values. Details retain both source values, base credit, deadhead credit, PCH components, and the extra carry-in premium. Deadheads earn 50% of elapsed time and are already included in source trip credit, so their underlying credit is not added twice. Lines-only imports now link pairing IDs directly from the Lines PDF. Duplicate pairing references count once; incomplete linked totals retain source line values and do not apply a partial premium.

The user confirmed that next-month departures determine PCH, replacing the initial UTC-midnight split proposal. Operating legs contribute 100%, deadheads contribute 50%, and ground transfers contribute zero. Adjusted credit adds only 50% of PCH, including credited deadheads, to full Trips base credit. Private November B777 regressions match F83 PCH 7:15, F61 14:30, and F46/F55 7:30. F83 adjusted credit is 101:07:30; F61 104:05; F46 91:37; F55 89:25; F38 91:40; F26 89:45; F23 76:05. The source F23 base totals are Block 74:35 and Credit 75:05.

Marked completed Follow up items with [x] in CREW_BIDS.md and recorded the confirmed examples there. Parser fingerprints now use v3, so reimporting the source documents creates a reviewable draft with these calculations. Existing published packages and private bid lists remain intact until the existing publication/revision-review flow is used. No package was automatically published. Later document requests, including line maximum height and omission of missing metrics, remain outside this task.

Validation: 40 focused Crew Bids/import tests passed (306 assertions), including all seven source examples, next-year departures, a pre-boundary overnight flight, half-credit deadheads, ground exclusions, exact fractional-minute premiums, deduplication, missing pairing totals, independent sorts, and saved bid order. Seven focused JavaScript tests, production Vite build, Pint, one Larastan run (zero errors), and diff checks passed. Database tests ran sequentially in one process. Manual browser review remains pending. Prior work and concurrent user document edits were preserved.

Commit message: `feat: separate crew bid block time and premium-adjusted credit`

### Follow up #4: Compact line height outcome (2026-10-27)

Reorganized collapsed line cards into metrics/action, calendar, and disclosure rows that fit within 150px without a card scrollbar. Credit, Block, and Days off have compact stacked labels; Add/Selected stays beside them. Trips, dates, source details, and match explanations share the footer. Single-trip lines open the trip immediately; multiple trips use a count. Removed duplicate trip previews from source Details. Warning text stays visible in a compact row with full messages in Details; supporting Work blocks move into Details. Date inspection opens the dates disclosure. Expanded cards grow naturally and retain sticky actions.

Validation: 28 focused Crew Bids tests passed (161 assertions). Production build, Pint, one Larastan run (zero errors), and diff checks passed. No new tests were added for this layout change. Manual browser sizing/interaction review remains pending. Prior work was preserved.

Commit message: `style: organize collapsed crew bid cards within 150px`

### Follow up #5: Omit missing metrics outcome (2026-10-27)

Cards, trip summaries/metadata, leg details, the Trips tab, and bid-list entries now omit missing values together with their labels. Removed empty hotel, source-rest, crew, report, block, credit, and carry-credit placeholders. Trip identifiers remain visible when their detailed source record is absent. Empty timelines and absent layover summaries no longer generate placeholder content. Known zeros remain visible; conflict/incomplete-date warnings, inconsistent connection evidence, missing saved choices, and source import diagnostics retain their meaning. Credit provenance appears only for available values. Match explanations omit unavailable credit/days-off/work-block metrics while preserving hard-filter uncertainty messages.

Validation: Missing-data coverage passed in the final 35-test Crew Bids integration run (202 assertions), including omission in cards, match explanations and selected choices, legacy trip values, known zero credit/block/rest/duty counts, retained warnings, and source carry counts. Pint, production build, Larastan (zero errors), and diff checks passed. No parser reimport is needed for this display change. Manual browser review remains pending.

Commit message: `fix: omit missing crew bid metrics and placeholder rows`

### Trip preview refinements A–C outcome (2026-10-27)

Removed the separate vertical leg list. One timeline chevron per leg now opens its dated UTC departure/arrival, source/calculated block basis, scheduled elapsed time, and available deadhead credit/source rest in place. The chevron shows operating block, deadhead credit, or transport elapsed time as appropriate. A rendered regression confirms the source flight identifier is shown once in its preview.

Scheduled layovers below 600 minutes receive amber short-layover chips; those at or above 1,440 minutes receive blue extended-layover chips. Both the timeline and layover table share the classification. Exactly ten hours is untagged and exactly twenty-four hours is extended. Existing under-three-hour grouping remains. Source rest stays independent of these browsing cues; no legal-rest or international classification is inferred. Overlaps and airport changes retain uncertainty messages and receive no duration chip.

Retained the existing compact sticky action header rather than adding another bar. Expanded cards keep their ID, metrics, and Add/Selected action below the measured workspace header, without a card scroll container. The compiled production CSS includes the expanded-only sticky positioning and measured top offset; existing ResizeObserver sizing/cleanup and saved-selection behavior remain covered.

Validation: Final focused Crew Bids tests passed (35 tests, 202 assertions), including exact 10h/24h boundaries, zero gaps, year boundaries, contradictory source-rest duration, invalid connections, unique leg information, missing metrics, and saved bid order. Seven JavaScript tests passed for sticky-header resizing/cleanup, tab keyboard focus/independence, and copy/navigation behavior. Pint, production build, one final Larastan run (zero errors), and diff checks passed. Manual browser sizing/scrolling review remains pending; prior working-tree changes were preserved. CREW_BIDS.md marks A–C and missing-data omission completed.

Commit message: `feat: unify trip timelines and highlight short and extended layovers`

### Line footer alignment outcome (2026-10-27)

Renamed the dates disclosure to Line, updated calendar instructions/accessibility text, and ordered the collapsed footer Line → Trip → Details → Why this matched. Line and Trip sit inline on the left; Details and Why this matched stay on the right. Removed Trip's first-position override and used an automatic margin before Details to separate the groups. Expanded Line/Trip summaries stay left-aligned, with Details/match summaries right-aligned and their contents in normal reading alignment.

Validation: The focused inline-trip preview test passed (one test, 26 assertions). Production build, Pint, one Larastan run (zero errors), and diff checks passed. No new tests were added for this copy/layout-only change. Prior working-tree changes were preserved.

Commit message: `style: rename and align crew bid line footer controls`

### Crew Bids icons outcome (2026-10-27)

Added shopping-cart, plus, check, calendar, airplane, document, and settings icons to the corresponding bid-list, Add/Selected, Line/Trip, Details, and Manage package controls. Why this matched now uses an icon-only question-mark disclosure with an accessible name, screen-reader text, tooltip, and keyboard focus styling. Decorative icons are hidden from assistive technology. Kept Line/Trip on the left and Details/match controls on the right, including the expanded match summary. Compact icon sizes preserve the card rows. Reused installed Blade Heroicons 2.7.0 without dependency changes.

Validation: Three focused Crew Bids tests passed (43 assertions), covering inline previews, matching explanations, and saved flying/reserve selections. Production build, Pint, one Larastan run (zero errors), and diff checks passed. No new tests were added for this presentation-only change. Manual browser review remains pending; existing working-tree changes were preserved.

Commit message: `style: add icons to crew bidding actions and disclosures`

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

## [x] Completed: FP: Delay reporting

### Goal

Allow captains to record delay observations in an autosaving UTC form and generate a copyable, factual report text block for company use. Preserve owner-only history independently of the currently loaded flight plan.

### Current implementation

- Added the FlightDelay model, migration, factory states, and user relationship. One report per owner/release key is enforced by a unique constraint. Flight identity is snapshotted server-side; the snapshot and narrative fields are encrypted and hidden from incidental model serialization. Clearing/replacing a brief retains its reports; deleting the owner removes them.
- Added the Delay reporting task and a dedicated Livewire form for logbook receipt, flight following brief, fueling/loading completion, load master briefing, new payload, new recall/flight plan, new TLR, Other, delay start/end, cause, supplied timeline, actions, notes, and additional facts.
- Inputs autosave after a 500 ms pause. Timestamp fields have immediate server-UTC Now controls; manual timestamps use explicit UTC with minute precision. Invalid/incomplete dates preserve their saved values while unrelated valid changes can save. Retry feedback, field errors, and saved/unsaved indicators preserve input after failure.
- Pending edits are flushed before report generation, task switching, clearing/replacing a brief, or in-app navigation. Incomplete browser datetime inputs are prevented from becoming accidental clears. Closing with unsaved input triggers the browser's unsaved-change warning; offline persistence is not provided.
- Save actions recheck authentication, verification, feature/gate access, owner/release context, and revision inside a transaction. Stale briefs and conflicting tabs cannot overwrite reports. Changed/conflicting inputs invalidate the report preview.
- Added a formatter with chronological event ordering, stable ties, complete UTC dates, honest missing-data labels, multiline narrative preservation, and explicitly labeled delay-interval duration. Reports are escaped, selectable, and copyable with success/failure feedback and manual-copy fallback. No email is drafted or sent.
- Added paginated, read-only history and detail routes under the existing flight-release access controls. History is reachable with or without a loaded brief when the owner has reports; another owner's reports do not expose the link. Direct empty-history visits show an empty state. Historical reports use their snapshot without replacing or reparsing the current brief.
- Moved field rules, messages, attributes, and validation into app/Validation/FlightDelayValidationRules.php, as requested. FlightDelayFields retains field definitions, saved-input presentation, UTC normalization, and flight-context mapping.

### Problem addressed

The brief previously offered no persistent delay timeline or consistent report text. Its saved-result row is replaced on upload and removed on clearing, so reports now have independent ownership and a release-key snapshot rather than a cascading dependency on that row.

### Confirmed behavior (2026-10-26)

The user confirmed one timestamp per milestone, read-only history, all populated notes included in report text, and duration calculated only from explicitly entered delay start/end times. Now applies to event/delay timestamps only; narrative fields have no separate recorded-at timestamp. Missing endpoints yield an unavailable duration; zero minutes is valid, and negative intervals are rejected. A new release key starts a separate report.

### Validation outcome (2026-10-26)

- Sail: 32 delay form/history feature tests passed (171 assertions). Coverage includes UTC/midnight, creation/reload/clearing, empty-history prevention, independent validation, failed-save retry, duration, report content/escaping, owner isolation, access gates, identifier tampering, conflicting tabs, stale briefs, history retention, pagination, and account deletion.
- Existing integration checks covered task enums, page-data availability, task routes, navigation, saved-result lifecycle, access, and view models. The corrected integration subset passed 87 tests (902 assertions); the other 24 affected tests passed in the initial focused run. No full suite was run.
- Sail: seven new JavaScript tests passed for pending-save navigation, invalid datetime preservation, generation, recovery, close warnings, and listener cleanup. Both existing delegated-copy tests also passed.
- Production Vite build passed. The local delay schema and named history routes were verified. git diff --check passed.
- Pint passed after PHP changes. One application-wide Larastan run identified model-cast inference/nullsafe-access issues; accurate cast return types and null-coalescing access resolved them. The subsequent focused static recheck of the six implicated files passed with zero errors.
- No manual browser/visual review was performed; actual clipboard permissions, native datetime editing behavior, and light/dark/mobile appearance were not manually exercised. Automated component and JavaScript tests cover their implemented contracts.

Unrelated TODO content, working-tree changes, and concurrent staging were preserved. No dependencies changed.

Commit message: `feat: add UTC flight delay reporting with autosaved observations`

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
