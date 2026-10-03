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
## Mobile sticky flight header

## Paused: Bugs: offline fuel score

### Goal

Keep entered Off time, starting FOB, and waypoint ATA/AFOB through a same-tab reload, including the reported iPad Chrome background/foreground case while the iPad is offline.

### Findings

The standalone Alpine calculator initialized all inputs to blank and kept them only in memory. The reviewed calculator code has no reload timer or explicit page refresh. The reported device is an iPad using Chrome; the user reports returning to the same tab after using another app while the iPad is offline. Browser tab suspension or reload is plausible in this scenario, but it has not been reproduced or confirmed as the trigger. `vite.config.js` enables development refresh, and recent Debugbar requests and browser logs did not identify an offline-calculator refresh. Standard `sessionStorage` is designed to survive reloads and tab restores, but its behavior after an iPad browser process is discarded must be checked on the affected device. More decisively, the current page cannot load at all after an offline navigation/reload because the app has no service worker or offline page shell; saved inputs alone cannot solve that case.

### Outcome

- Added a versioned `sessionStorage` draft scoped by the authenticated owner and `flightPlanKey`. The controller supplies that scope after its existing ownership check.
- The draft saves only entered strings for Off time, starting FOB, ATA, and AFOB. It restores them before Alpine watchers are attached, then recalculates from the current release. Partial values and explicit zeroes survive a reload.
- The source signature includes the fuel unit, confirmed fuel quantities, and ordered waypoint identifiers, coordinates, times, TBO, and remaining fuel. Each reading is also tied to its waypoint position and source identity. Malformed or incompatible drafts are discarded, and drafts do not cross owners or release keys.
- Reset clears live inputs and the draft; queued Alpine watchers cannot resurrect prior values. If storage blocks removal but permits writing, Reset replaces the old draft with an empty one. Storage failures leave calculations usable and show a short accessible notice when recovery cannot be trusted.
- Page copy now explains same-tab recovery and that loading or refreshing the page still requires a connection. No service worker or offline page-load cache was introduced.

### Validation

The focused JavaScript calculator and draft tests pass: 20 tests in the affected files, including reload recovery, initialization order, repeated identifiers, scope/source isolation, Reset, malformed drafts, and read/write/remove failures. `OfflineFuelScoreTest` passes (4 tests, 53 assertions). Pint, the production Vite build, and one final Larastan pass on changed PHP and the focused test pass. Browser lifecycle verification was unavailable in this environment.

### Remaining investigation

The affected iPad is offline when Chrome returns. On that device, enter Off time, starting FOB, ATA, and AFOB; switch to another app and return to the same tab. Record whether the page stays mounted or reloads, any prompt, and whether the four values return. Repeat after a longer background period. The implemented `sessionStorage` draft recovers a same-tab reload only when the page can load; it cannot restore the calculator after an offline navigation/reload. If the browser discards the tab session itself, even tab-scoped storage may be unavailable. Compare development and production builds before attributing the refresh to Vite.

### Proposed offline reload extension — authorization required

A calculator-only service worker could serve a static offline shell for the exact fuel-score route when a network request fails and cache the versioned CSS/JavaScript assets. To make the release and its entered values available in that shell, it would also need a local copy of the source-backed calculator data. The safer proposed design encrypts the minimum required source payload in browser Cache Storage with a per-tab key in `sessionStorage`, scopes it to the authenticated owner and release, gives it a short expiry, and clears it when possible. The service worker must use the server response whenever online and never turn a server 403/404 into cached content. The offline shell would show an explicit unavailable state if the key, assets, or cache are missing. An offline reload could not re-check server authorization; anyone who can use the still-open tab and its key could view that cached release until the session ends. Browser eviction or loss of the tab session can still prevent restoration.

This extension needs focused tests for route scope, network-first behavior, encryption/decryption, expiration, missing keys/assets, source mismatch, and unauthorized online responses, followed by an actual Chrome-on-iPad offline background/reload check. It also needs a visible readiness state so the user knows when the offline copy has been prepared. The attempted service-worker/cache implementation was rejected by automatic approval review because persisting authenticated release content on the device is a security/privacy side effect not specifically authorized for this task. No service-worker or release cache code was added. Continue this part only after explicit approval for that on-device storage behavior.

References: [page-session behavior](https://developer.mozilla.org/en-US/docs/Web/API/Window/sessionStorage), [WebKit background tab suspension](https://webkit.org/blog/8970/how-web-content-can-affect-power-usage/), and [service-worker navigation interception](https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerGlobalScope/fetch_event).

Commit message: `fix: preserve offline fuel score inputs across reloads`

## Sloppy static findings
./vendor/bin/sloppy

## Paused: Flight release: 24 hour time limit or past ETA

### Goal

Expire a saved flight release at the earlier of 24 hours after extraction and its planned ETA. Remove the expired release from the database and any release-specific cache, and stop serving it through the brief or Fuel Score URL.

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

## Implement Crew Compass tie-ins, branding, and marketing

### Goal

Integrate useful Crew Compass city and layover information into K4 schedule results so extracted trips naturally connect users back to Crew Compass destination content.

### Current implementation

Airport information is already enriched for flight origins and destinations.

Crew Compass content is not currently surfaced directly within schedule cards, and layover events expose a station code without resolving that station to a canonical Crew Compass city.

### Problem

K4 and Crew Compass currently behave more like separate products than parts of the same ecosystem. Layovers are particularly valuable moments to surface Crew Compass information, but their station codes are not yet enriched with city/guide/place data.

### Implementation plan

1. Extend the Crew Compass airport/provider response with a typed city summary containing:

   * canonical city identifier or slug,
   * guide availability,
   * guide URL,
   * available places count,
   * city URL.
2. Resolve Crew Compass cities using airport/station codes rather than city-name matching.
3. Extend schedule enrichment to collect unique layover station codes alongside flight origins and destinations.
4. Reuse the existing cached airport-resolution/provider flow rather than introducing Blade-side requests.
5. Attach the resulting Crew Compass city summary to layover metadata.
6. Expose the same typed summary through the relevant event and flight-card view models.
7. Build a reusable Blade city-summary component.
8. Render the component:

   * primarily below hotel details on layover cards,
   * secondarily inside origin/destination airport popovers.
9. Do not duplicate the summary inside the expanded airport-details accordion.
10. For layovers, display:

    * resolved city,
    * whether a layover guide exists,
    * number of available places,
    * guide/city links when available.
11. Handle provider failures and cities with no guide or places without breaking schedule rendering.
12. Add focused tests for:

    * available guide and places,
    * no guide,
    * zero places,
    * duplicate city/station resolution,
    * provider failure,
    * layover enrichment,
    * flight-card/popover rendering.

### Acceptance criteria

* Layover stations resolve to canonical Crew Compass city data when available.
* Crew Compass summaries appear on layover cards below hotel information.
* Compact summaries appear in origin and destination airport popovers.
* Duplicate station/city lookups do not cause redundant provider calls.
* Blade components perform no parsing, querying, normalization, or authorization.
* Missing Crew Compass data is shown as unavailable rather than as empty or misleading values.
* Provider failure does not prevent schedule results from rendering.

### Proposed commit message

`feat: integrate Crew Compass city content into schedules`

## Plan: feat: Track schedule upload count
- For multiple file uploads within each user request

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

## Unified upload
Currently: 2 tabs have 2 different upload points, user has to choose 
Goal: Have one unified upload path. Service will determine if a schedule or flight plan has been uploaded. 

Cached results: Keep extract schedule and flight plan brief tabs for now.


## Flight plan: Add task: Takeoff and Landing Report
Feat: TLR Validity check

Naming outcome: Renamed the view-model presentation API from the ambiguous `envelope*` prefix to `tlr*`. The normalized payload continues using its existing `envelope` storage key until the broader data contract is migrated.

Commit message: `refactor: rename envelope view model methods to tlr`

Source inputs:
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

## Flight plan: Create a way to turn tasks on or off
- in ENV and config files
- in coordination with enum

## PEST architechure tests
- Does pest need to be installed? 
- Can I run along side existing test suite?
- Naming
- Layering

## 17. Flight plan: Reserve fuel
- Create distinction between Alternate airport burn and Reserve fuel calculation. 
- Differed due to needing aircraft type fixture and distintion between 747 and 777 aircraft type
- Requires full fleet in production database. Implemented.
- coincides with future 747 seeder into production
- Add migration for reserve fuel additive

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
Pilot name extracted as `SINHA A IRP MX LM ACM`, expected `SINHA A`

Follow-up: `SINHA A IRP MX LM ACM YATES R` must yield two members: IRP `SINHA A` and ACM `YATES R`.

Reference:
storage/app/private/flight_releases/CKS021823RJAA.pdf

Confirmed follow-up reference: `storage/app/private/flight_releases/CKS021617RJAA.pdf`.

### Outcome

Fixed manifest name cleanup to remove the full trailing sequence of role placeholders and annotations. Previously, a trailing `HIGH MINS` annotation was removed alone, leaving `IRP MX LM ACM` in the name. High mins flags, crew identifiers, multiword names, and source evidence remain intact.

The referenced PDF currently contains a different roster and no `SINHA` entry. A regression input reproduces the exact reported name contamination with placeholders followed by `HIGH MINS`.

### Validation

Focused crew parser and flight crew extractor tests pass: 20 tests, 97 assertions. The new parser regression failed with the reported contaminated name before the fix. Pint passes; the single final Larastan run passes with zero errors.

Commit message: `fix: strip combined crew manifest annotations from names`

### Follow-up outcome

The confirmed source has `72480 IRP SINHA A` followed by empty role placeholders and `ACM YATES R` without an employee number. Manifest boundaries now recognize a role followed by a name even when the employee number is absent, while excluding empty role placeholders and form headings. Yates is retained as a separate ACM with a null employee number in the extracted release. Duplicate manifests remain deduplicated, and source evidence is preserved.

The parser regression reproduced the exact combined name before the fix. Both focused crew test files pass with 22 tests and 103 assertions. Direct extraction of the confirmed PDF produces SURADKAR A (PIC, High mins), DATOO R (SIC/FO), SINHA A (IRP), and YATES R (ACM). Pint and the single final Larastan run pass with zero errors.

Follow-up commit message: `fix: split crew manifest members without employee numbers`

## [x] Completed: Flight plan: Refactor FlightPlanBriefTest
- Split tests and organize into folders grouped by test focus area

### Outcome

Moved the 34 existing tests into 14 focused PHPUnit classes under `tests/Feature/Livewire/FlightPlanBrief`, grouped into `Lifecycle`, `Security`, `Workspace`, and individual `Tasks` panels. The original test file was moved into an abstract `FlightPlanBriefTestCase` that shares the existing parsed-release fixtures, Mockery helpers, and `RefreshDatabase` behavior. Test method names and assertions are preserved; imports and namespaces follow each file's focus.

### Validation

The original file and the reorganized directory both pass with 34 tests and 850 assertions. Pint passes after formatting; the single final Larastan run on the reorganized directory passes with zero errors. Run the focused group with `vendor/bin/sail artisan test --compact tests/Feature/Livewire/FlightPlanBrief` or select any individual test file within it.

Commit message: `refactor: organize flight plan brief tests by focus area`
