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
## Current focus: Bugs: offline fuel score

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


## [x] Complete: Lat / Long cut off, some waypoints prefixed with `-`

#### Goal

Show complete, source-backed latitude/longitude waypoint labels in both Fuel Score views, and distinguish FIR boundary rows from ordinary waypoints without displaying an unexplained leading hyphen. Preserve the original identifiers and coordinates as source evidence.

#### Current implementation

- `FlightPlanTextExtractor` uses Smalot page `getText()` output, removes null bytes, and joins pages. In the supplied PDF, adjacent text chunks within a page are concatenated without a separating space or newline.
- `WaypointExtractor::coordinateDelimitedRecords()` finds coordinate matches, then treats everything up to the next coordinate as that record's content. `COORDINATE_PATTERN` permits an unlimited number of decimal digits in longitude minutes (`\.\d+`).
- `WaypointExtractor::detail()` accepts identifiers matching `-?[A-Z0-9]{2,7}` and preserves a leading hyphen. `WaypointDataBuilder`, `WaypointData`, and `FuelPresenter` pass that identifier through. The regular Fuel Score Blade table and offline Alpine table render it directly; no identifier shortening was found in this path.
- The existing fixture and ordered-extraction test explicitly expect FIR-style identifiers such as `-EDWW`, `-EDVV`, and `-EHAA`. There is no row-kind or separate display-label field in `WaypointData`.

#### Investigation findings

Source: `storage/app/private/flight_releases/CKS020221KCVG.pdf`, especially PDF page 11. Read-only reproduction used the installed Smalot parser and current `WaypointExtractor` through Sail. Individual `getTextArray()` chunks establish where the coordinate ends and the identifier begins.

| Source coordinate and following IDENT | Current extracted result | Finding |
| --- | --- | --- |
| `N50 00.0 W095 00.0` then `50N095` | Coordinate `N50 00.0 W095 00.050`; identifier `N095` | The longitude decimal consumes the identifier's leading `50`. |
| `N61 00.0 W130 00.0` then `61N130` | Coordinate `N61 00.0 W130 00.061`; identifier `N130` | The longitude decimal consumes the identifier's leading `61`. |
| `-CZEG`, followed by `FIR -> CZEG <-` | Identifier `-CZEG` | The hyphen is present in the source; this is an explicitly annotated FIR row. |
| `-PAZA`, followed by `FIR -> PAZA <-` | Identifier `-PAZA` | The hyphen is present in the source; this is an explicitly annotated FIR row. |

The flattened source strings include `W095 00.050N095 0222` and `W130 00.061N130 0409`. This reproduces a parsing boundary defect, not CSS clipping. The PDF's route on pages 3 and 8 and coordinate listing on page 15 independently contain `50N095W` and `61N130W` with matching coordinates.

The earlier report mentioned `N50` instead of `N50W120`. That exact pair was not reproduced in this supplied release; the confirmed cases are `50N095`/`N095` and `61N130`/`N130`. Do not substitute W120 or infer missing longitude from a damaged identifier.

Related boundary observations: the digit-leading departure identifier `39028N` on page 10 is absent from current parser output, and NODLE's coordinate at the end of page 11 is separated from its identifier on page 12 by footer text. Include these as boundary regression cases when changing record segmentation. Missing FRMG on some rows is a separate field-extraction concern and is outside this task.

#### Implementation plan

1. Add small source-derived text fixtures under `tests/Fixtures/FlightPlan/waypoints/` for the confirmed coordinate/IDENT boundaries, both separated and flattened, plus the two FIR rows and their annotations. Keep the private PDF out of committed fixtures; include only the minimal relevant rows.
2. Correct coordinate/record segmentation in `WaypointExtractor` so a numeric identifier cannot extend longitude minutes. Recognize the coordinate together with the following valid IDENT/DIST row structure. Use the source text-chunk boundaries as the reference; if flattened text is ambiguous, preserve the necessary boundaries upstream instead of guessing. Do not globally truncate coordinate precision or merely make the decimal matcher lazy, which could accept another incorrect split. Keep source text available to other extractors unchanged wherever possible.
3. Handle known page labels between a coordinate and its detail row within the computed-flight-plan section. Preserve record order and repeated identifiers/coordinates, retain the alternate-section stop, and do not attach coordinate-less TOC/TOD markers to the preceding waypoint.
4. Preserve the complete source identifier separately from any display normalization. For positively identified whole-degree coordinate fixes, derive an explicit hemisphere label from the verified, uncorrupted coordinate: the supplied examples should display `N50W095` and `N61W130`, while retaining `50N095` and `61N130` as source identifiers. Named fixes such as `NODLE` and `NIPPI`, and other source identifiers such as `51259N`, must not be rewritten just because they contain N/S/E/W or digits. Do not round a non-whole-degree coordinate into a whole-degree label.
5. Classify FIR rows only when the source row has matching explicit FIR evidence. Carry that classification through the typed waypoint data, serialization/restoration, and presenter, following existing enum/DTO conventions. Recommended display: `CZEG (FIR)` and `PAZA (FIR)`, retaining their rows, order, coordinates, and available values. Keep `-CZEG` and `-PAZA` as source identifiers. Do not indiscriminately strip hyphens, discard boundary rows, or turn FIR identifiers into airport lookups; `-ETP1` is a different source marker.
6. Have `FuelPresenter::waypoints()` and `calculatorData()` supply the same display label to the regular and offline views, including accessible waypoint control names. Keep parsing and classification out of Blade and JavaScript. Preserve existing fuel/time calculations, missing-value behavior, and waypoint input associations.
7. Keep older serialized payloads readable with conservative defaults for new metadata. A parser change does not repair already stored `FlightPlanResult` payloads: re-extract the supplied release for validation, without mass rewriting or deleting saved results. If upstream PDF text extraction changes, version its text-cache key so the seven-day cached text cannot conceal the fix; an extractor-only change does not require clearing the raw-text cache.

#### Acceptance criteria

- The supplied release extracts source identifiers `50N095` and `61N130` with coordinates exactly `N50 00.0 W095 00.0` and `N61 00.0 W130 00.0`; neither identifier digits nor DIST digits leak into a coordinate.
- Both Fuel Score views display the complete, hemisphere-explicit labels `N50W095` and `N61W130`, while source evidence retains the original IDENT values. Unknown or ambiguous coordinate labels are not invented.
- Verified FIR rows display `CZEG (FIR)` and `PAZA (FIR)` consistently, retain source identifiers, and remain distinct from neighboring fixes, including PAZA/GAHAM at the same coordinate.
- Numeric-leading identifiers, named fixes, repeated fixes, CRLF/extra whitespace, flattened text, and coordinate/detail page breaks preserve their correct boundaries and order. Alternate rows and coordinate-less markers remain excluded as before.
- Normalized/serialized data round trips without losing original labels or classification. Older payloads remain readable, and calculations retain their original time/fuel inputs and missing-value semantics.

#### Validation and outcome

Investigation baseline: Sail is available. All 7 existing `WaypointExtractorTest` tests pass (22 assertions), despite the reproduced truncation. No recent matching flight-release Debugbar request was available; the findings come from direct PDF/parser reproduction. PDF text chunks and route entries were inspected; the attempted image render was not legible enough for visual confirmation.

Implementation validation: Added source-derived separated and flattened fixtures and focused regression coverage for coordinate/IDENT boundaries, FIR evidence, repeated coordinates, page labels, precision, hemisphere labels, serialization, legacy payloads, and both Fuel Score views. All 104 focused tests passed (929 assertions). Pint passed; Larastan passed with zero errors. A read-only re-extraction of the supplied PDF produced 55 waypoint rows; `39028N`, `50N095`, `61N130`, `-CZEG`, `-PAZA`, GAHAM, and NODLE retained their expected source coordinates and ordering. The typed round trip and both Fuel Score presenter payloads produced `N50W095`, `N61W130`, `CZEG (FIR)`, and `PAZA (FIR)`. No upstream PDF text extraction or frontend asset changed, so the raw-text cache key and Vite bundle did not need updating. Browser visual verification was unavailable.

Outcome: Repaired the numeric IDENT/longitude boundary only when the candidate identifier matches the source coordinate, and handled the known PDF page labels before detail rows. Added typed waypoint kind and a separate display label while retaining source identifiers, coordinates, timing, and fuel. The regular and offline Fuel Score views now use the same label, including offline control names; older saved payloads fall back to their original identifier. The supplied release must be re-extracted in the application to replace any previously saved result.

Commit message: `fix: preserve coordinate waypoint labels and identify FIR rows`

Documentation commit message: `docs: investigate truncated waypoint labels and FIR prefixes`

## Ramp fuel stat card

### Goal

Make ramp fuel the prominent value in the overview Fuel card. For a source value of 125,400 lb, show a large monospaced `125.4` beside a smaller, deemphasized `k lbs`, with Ramp fuel as the supporting label.

### Current implementation

- `resources/views/components/flight-release/overview.blade.php` renders ramp fuel inside the generic `metric` component within the Fuel overview card. The value is a single small string.
- `FuelPresenter::overviewRampFuel()` returns `FuelQuantity::format()`, such as `125,400 LB`; `FlightReleasePageViewModel` passes that string to the view. Missing ramp fuel returns `null`.
- `overview-stat.blade.php` already gives MEL/CDL, Weight & Balance, and slot counts a prominent monospaced value, but it has no separate unit presentation. The Fuel card uses `overview-card` for its heading, status, and Fuel Score action.
- Existing view-model tests cover pound, zero-kilogram, and missing ramp values. They expect the current unscaled overview string.

### Problem

The ramp fuel figure is visually buried in a nested metric cell. Its number and unit cannot have separate emphasis because they arrive as one formatted string. A stat treatment must still identify the fuel unit and keep a real zero distinct from missing source data.

### Implementation plan

1. Expose overview ramp fuel as presentation data from `FuelPresenter` through `FlightReleasePageViewModel`: numeric display text, unit display text, and a complete accessible description. Keep formatting and unit decisions out of Blade.
2. For pounds, divide the source amount by 1,000 and format one decimal place: 125,400 lb becomes `125.4` plus `k lbs`. For kilograms, retain the source unit and show the amount as `kg` without converting it to pounds. Preserve zero as a value; return a missing state only when the ramp quantity is absent.
3. Reuse or narrowly extend `overview-stat` to render the number large and monospaced, the unit smaller and lower contrast, and Ramp fuel as a supporting label. Give assistive technology one complete reading of value and unit. Keep existing count-stat callers working.
4. Replace only the Fuel card's nested `metric` with the stat presentation. For missing ramp fuel, show `Not present in this release` without a numeric zero or orphaned unit. Preserve the card's Fuel Score action, availability status, responsive grid behavior, and light/dark palette.

### Acceptance criteria

- The Fuel overview card displays 125,400 lb as prominent `125.4` with subdued `k lbs` and a visible Ramp fuel label; the complete value is accessible as one description.
- A kilogram source displays its own correctly labelled value, and a legitimate zero remains visible. Missing ramp fuel shows only the explicit missing-data message.
- The stat remains legible in the narrow overview grid and in dark mode. Other overview stats, Fuel Score details, and the card action retain their behavior.

### Validation for implementation

- Update focused `FlightReleasePageViewModelTest` cases for pound scaling/rounding, kilograms, zero, and missing ramp fuel.
- Update focused `FlightPlanBriefTest` rendering assertions for the large number, subdued unit, accessible description, missing state, and unchanged Fuel Score action. Retain assertions for the other overview stat callers.
- Run only affected tests through Sail, build frontend assets if Blade classes change, then run Pint if PHP changes and Larastan once at the final implementation checkpoint. Check the card visually at narrow and wide widths in light and dark mode; record results here.

Planning outcome: Identified the existing small metric rendering, reusable overview stat component, and source-unit edge cases. Documented the presenter, component, accessibility, and validation work. Application code is unchanged; implementation remains open.

Proposed implementation commit message: `feat: display overview ramp fuel as a prominent stat`

Documentation commit message: `docs: plan ramp fuel overview stat`

## Flight release: 24 hour time limit or past ETA
Clear flight release from cache and db if more than 24 hours since extraction or current UTC time is greater than ETA.

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

## feat: Track schedule upload count
- For multiple file uploads within each user request

## Flight plan: Crew list: WCAG 2.2 AA compliance

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

## Flight plan: Refactor FlightPlanBriefTest
- Split tests and organize into folders grouped by test focus area

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

## [x] Completed: Fix flight plan uploads that leave the spinner running
## [x] Completed: Show flight plan upload and extraction progress
## [x] Completed: Fix Livewire test response type inference
## [x] Completed: Flight plan: Overview: Slot times overview card refactor
### Context aware slot times
### Slot time widget
## [x] Completed: Bug: PDF flight release header/footer extracted into route
## [x] Completed: Feat: Domestic / international flight determine
## [x] Completed: Feat: GENDEC card
## [x] Completed: feat: Overview cards Spatial Organization (Grid & Layout)
Responsive Flow: Switch the grid from fixed columns to a repeat(auto-fit, minmax(280px, 1fr)) pattern. This ensures that cards resize intelligently based on screen width, preventing data from feeling cramped or overly stretched.
Whitespace: Increased padding and gaps to create "breathable" space, which reduces cognitive load and allows the eye to focus on individual metrics.

Outcome: Replaced the fixed one/two/six-column overview grid and per-card column spans with `repeat(auto-fit, minmax(280px, 1fr))`, allowing every visible card to flow according to available width. Increased overview section padding, inter-section spacing, card-grid gaps, and overview-card padding/internal gaps while preserving existing card content, actions, warning surfaces, and dark-mode styling.

Validation: 10 focused view-model and Livewire overview tests pass (238 assertions), including a rendered-layout assertion for the auto-fit grid, increased spacing, and removal of fixed column/span classes. Pint, Larastan, and the Vite build pass. Browser visual verification was not available in this session.

Commit message: `feat: improve overview card responsive layout`

## [x] Completed: Refactor welcome page for use with new features

### Goal

Turn the welcome page from a Schedule Extractor landing page into the branded Crew Compass / K4 Extractor product entry point for both Schedule Extractor and Flight Plan Extractor.

### Current implementation

The current page is centered almost entirely on the Jeppesen Crew Access Schedule Extractor. The title, hero, screenshot, benefits, CTA, and security messaging all reinforce that single feature.

Reusable Crew Compass branding, `cc-*` styles, theme controls, and existing entitlement methods are already available.

### Problem

The application now contains multiple extraction products, but the public entry page still presents K4 as a single-purpose schedule tool. Its visual hierarchy, branding, accessibility, and authenticated CTAs also need to be brought in line with the current product/UI rules.

### Implementation plan

1. Reframe page metadata, navigation, and hero around:

   * Crew Compass as the parent brand.
   * K4 Extractor as the application.
   * A single descriptive page `h1`.
2. Replace Schedule-only hero messaging with product-level copy describing document-to-reviewable-information extraction.
3. Keep Jeppesen Crew Access references within Schedule Extractor-specific content rather than as the overall product identity.
4. Add a reusable feature-card Blade component and present:

   * Schedule Extractor.
   * Flight Plan Extractor.
5. Keep both tools visible, emphasize Schedule Extractor as the primary CTA, and clearly identify Flight Plan as a Demo / Preview.
6. Move the current schedule screenshot into Schedule-specific supporting content rather than using it as the product-wide hero.
7. Make CTAs access-aware using the existing:

   * `User::canUseScheduleExtractor()`
   * `User::canUseFlightRelease()`
8. Do not introduce authorization logic into Blade or rely on hidden navigation as authorization.
9. Restyle the page using existing Crew Compass utilities and the documented Aviation Blue / Compass Gold visual system.
10. Improve:

    * semantic landmarks,
    * heading hierarchy,
    * image alt text,
    * keyboard focus,
    * light/dark states,
    * responsive behavior.
11. Update focused feature tests covering:

    * Crew Compass / K4 branding,
    * both extractor summaries,
    * guest CTAs,
    * authenticated CTAs,
    * disabled-feature states,
    * Flight Plan demo badge,
    * theme controls,
    * disclaimer/footer content,
    * removal of Schedule-only assumptions.
12. Validate with focused PHPUnit tests, Pint, production Vite build, and a final Larastan pass.

### Acceptance criteria

* The welcome page clearly represents K4 Extractor as a multi-tool Crew Compass application.
* Schedule and Flight Plan Brief are both visible, with a primary Schedule CTA and a secondary Flight Plan CTA.
* CTA behavior reflects existing user entitlements.
* Authorization remains enforced by the existing backend mechanisms.
* The page follows the documented Crew Compass palette and light/dark themes.
* There is only one page-level `h1`.
* All controls have visible keyboard focus and accessible names.
* Existing public navigation, privacy, feedback, login/registration, and independence messaging remains available.

Outcome: Reframed the welcome page as the Crew Compass / K4 Extractor product entry point with the headline “Turn Crew Documents into Actionable Flight Data.” Reusable tool cards have calendar/flight icons, a filled primary Schedule CTA, an outlined Flight Plan CTA, and a prominent Demo / Preview badge. Each available card has one native link covering its full area with keyboard focus styling; unavailable cards remain noninteractive. WelcomeController resolves actions through existing entitlement methods, including login, email verification, account-restricted, and disabled-feature states. Existing backend authorization remains in place. Added explicit high-contrast navigation and moved Data Security & Privacy directly below the tools, covering private upload storage, account/sign-in protection, unique passwords, and the privacy policy without unsupported encryption or deletion guarantees. Preserved theme controls, schedule-specific screenshot content, registration, dashboard, feedback, and independence messaging.

Validation: The latest 27 focused welcome, badge, and theme tests pass (251 assertions), including native card links, noninteractive unavailable cards, CTA hierarchy, preview status, and security content. Existing route-authorization tests passed during the initial refactor. Pint, the production Vite build, and one final Larastan pass covering the application, routes, and changed tests pass. Browser visual verification was not performed.

Commit message: `refactor: make welcome page a branded product hub`

---
