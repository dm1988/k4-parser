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
## Bugs: offline fuel score
Page wants to refresh causing Off time / starting FOB to clear out



## [x] Completed: Bug: PDF flight release header/footer extracted into route
With this `DCT ELLAM DCT TIEKL DCT OMSUN DCT 61N130W 60N120W 58N110W/N0491F330 DCT PETMA DCT YQD DCT GABOV DCT SUZLI DCT FGHRN MADII7 KALITTA BRIEF PAGE 2 OF 79 PAGE 2 OF 79` extracted.

Outcome: Route normalization removes `KALITTA BRIEF PAGE n OF n` and standalone `PAGE n OF n` labels, including duplicates, wrapped labels, and page breaks. Both route extraction entry points preserve route tokens and reject routes left empty after cleanup. Cleanup is scoped to the route so the original PDF text remains available to other extractors.

Validation: All 32 focused FlightRouteExtractor tests pass (121 assertions), including the reported route, page-spanning routes, mixed whitespace/case, similar route tokens, and empty-route rejection. Pint and Larastan (app and changed test file) pass.

Commit message: `fix: remove PDF page labels from extracted flight routes`

## [x] Completed: Feat: Domestic / international flight determine
Domestic Flight: A flight that operates entirely within the sovereign territory and airspace of a single country, departing and landing at airports located in the same nation without crossing or clearing international customs boundaries (e.g., PANC to CONUS, or Hawaii to CONUS).

International Flight: A flight where the departure airport and arrival airport (or intermediate technical/operational stops) are located in different sovereign countries or territories, requiring clearance through international customs, immigration, and agricultural control authorities (e.g., PANC to NRT, or CONUS to YVR).

Will determine if a GENDEC is needed. GENDECs are not typically needed on domestic flights. For the flight plan purpose, PANC to Conus or Hawaii to conus are domestic flights.

Logic:
* **PANC to CONUS:** Domestic -> No GENDEC required
* **PHNL / PHOG to CONUS:** Domestic -> No GENDEC required *(Note: State-specific agricultural declarations may apply, but not an international GENDEC)*
* **PANC / CONUS to Foreign Destination (or vice-versa):** International -> GENDEC required

Outcome: Added typed domestic/international/unknown classification using resolved airport countries, exposed through `FlightReleasePageViewModel::flightType()`. Country codes and English country names normalize consistently, including US aliases; missing or unrecognized countries remain unknown. Alaska/Hawaii-to-CONUS flights classify as domestic. Alternates and overflight waypoints do not affect classification. Explicit landing stops can be supplied to the classifier; a known foreign stop makes the trip international, and an unresolved stop prevents a domestic result. The current parser supplies a single leg's endpoints and does not yet extract intermediate landing stops. GENDEC card changes remain in their separate task.

Validation: 28 focused tests pass (112 assertions), covering endpoint classification, country normalization, missing data, territory distinctions, explicit stops, and restored flight-plan/view-model integration. Pint and Larastan pass.

Commit message: `feat: classify flight legs as domestic or international`

## [x] Completed: Feat: GENDEC card
After Domestic / Int flight task is complete and international flights can be determined:
Move from GENDEC in operational status to it's own dedicated card. 
If GENDEC not available and domestic flight, render `Domestic flight: GENDEC likely not required`. If intl and no GENDEC, render card in caution amber with message `GENDEC not found in flight plan. Verify against actual flight plan.`

Outcome: Moved GENDEC out of operational support status into a dedicated overview card. Missing domestic declarations show the requested likely-not-required message. Missing international declarations show the requested verification message on the existing amber warning surface. Unknown flight types explicitly state that classification is undetermined and also request verification. Detected declarations show `GENDEC found in flight plan.` with a green check icon. The MEL/CDL no-restrictions state uses the same green check treatment. The card supports light/dark mode and has no unsupported detail action.

Validation: 93 focused view-model and Livewire tests pass (1,415 assertions), including all six classification/presence combinations, green success checks, removal from operational support status, and overview rendering. Pint, Larastan, and the Vite build pass. Browser visual verification was not available in this session.

Commit message: `feat: add dedicated GENDEC overview card`

## feat: Overview cards Spatial Organization (Grid & Layout)
Responsive Flow: Switch the grid from fixed columns to a repeat(auto-fit, minmax(280px, 1fr)) pattern. This ensures that cards resize intelligently based on screen width, preventing data from feeling cramped or overly stretched.
Whitespace: Increased padding and gaps to create "breathable" space, which reduces cognitive load and allows the eye to focus on individual metrics.

## Refactor welcome page for use with new features

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
5. Give both tools equal hierarchy and keep the existing Flight Plan `Demo` state visible while applicable.
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
* Schedule and Flight Plan Brief are both visible with equivalent product hierarchy.
* CTA behavior reflects existing user entitlements.
* Authorization remains enforced by the existing backend mechanisms.
* The page follows the documented Crew Compass palette and light/dark themes.
* There is only one page-level `h1`.
* All controls have visible keyboard focus and accessible names.
* Existing public navigation, privacy, feedback, login/registration, and independence messaging remains available.

### Proposed commit message

`refactor: make welcome page a branded product hub`

---

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

### [x] Completed: Flight plan: Weight & Balance: Operational badging
### [x] Completed: Flight plan: Overview: Remove Redundant Data (De-cluttering)
### [x] Completed: Flight plan: Overview: Integrate MEL / CDL Summary Block
## [x] Completed: Flight plan: Overview: Weight and Balance
## [x] Completed: Feat: Establish MEL badge color heiracrchy
## [x] Completed: Refactor: MEL Dashboard Metric Card
## [x] Completed: Flight plan: W&B Card order
## [x] Completed: Flight plan: Overview: Full card link
## [x] Completed: feat: flight plan: Offline fuel score
## [x] Completed: Follow up 3
## [x] Completed: Follow up 4
## [x] Completed: Follow up 5: Table Refactor: Waypoint Estimates
### [x] Follow up 6: Fuel score table refinements
## [x] Completed: Line Break Fix: Flexbox Label Content
## [x] Completed: feat: Flight plan: overview: ETOPS card
## [x] Completed: Overview: whole card color change
## [x] Completed: Fix flight plan uploads that leave the spinner running
## [x] Completed: Show flight plan upload and extraction progress
## [x] Completed: Fix Livewire test response type inference
## [x] Completed: Flight plan: Overview: Slot times overview card refactor
### Context aware slot times
### Slot time widget
Large 5-xl metric for slot time count. Remove ETD and ETA fields. Keep card links. Should render `1` in 5-xl then `approved slot time` in normal text-sm. Alert flags for close UTC slot windows.

Outcome: Weight & Balance, MEL/CDL, and Slot Times now share the reusable overview-stat component. When slots are present, the slot card shows a large monospaced count with singular/plural `approved slot time` copy, retains its detail action, and omits ETD/ETA fields. When no slot times are present, the overview omits the Slot Times card.

Slot comparisons use full UTC dates. Exact matches to the departure or arrival window start show `Do not depart early`; times within the user-selected 10 minutes of either boundary and times outside the window receive caution alerts and an amber overview card. The Slot Times task displays the signed buffer from the earliest window time (ETD for departure, ETA for arrival), the calculation basis, and contextual alert details. Missing planned time, tolerance, or direction leaves the buffer unavailable.

Validation: Focused view-model and Livewire tests pass, covering departure/arrival comparisons, 10/11-minute thresholds, both window boundaries, times outside the window, midnight/year rollover, explicit zero tolerance, missing/invalid source values, singular/plural rendering, missing-card omission, and preserved task links. Pint, the production Vite build, and Larastan (including both changed test files) pass. Browser visual verification was unavailable in this environment.

Commit message: `refactor: add shared overview stats and hide empty slot card`
