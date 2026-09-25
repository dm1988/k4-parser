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
## Feat: flight plan: Offline fuel score
Goal: Create link on open seperate offline fuel score with a basic java script calculator. Able to calculate ETA and FOB at each waypoint. Link opens in new browser tab.

## Idea: Flight plan: Refactor overview task
- Show ETOPS info if it exists
- Count of ETP points
- ETOPS time (e.g. 180, 210, 240)

## Overview: whole card color change
Instead of 5-xl metric color change, change the whole card color

## Flight plan: Overview: Slot times overview card refactor
Large 5-xl metric for slot time count

Buffer time from planned departure to earliest slot window time.

Context aware `Do not depart early` messaging if planned etd is equal to departure min slot window or if planned eta is equal to planned arrival min slot window

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

Assumptions
Airport - KDFW
Planned runway  - 36L
Outside air temperature - 23.0 °C
Wind (source code)  - 077M07
QNH - 30.18 inHg
Flap    - 15
Anti-ice    - Yes
Source limits

Permitted Calculations
Maximum runway takeoff weight
820,500 LB
Maximum field takeoff weight
772,400 LB
Source-calculated values

Calculated result
Planned takeoff weight
577,300 LB
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

## Flight plan: Triple extract key flight release data
- Key flight plan data is found on the 3 copies. Ensure regex matches 3 times for data found on the top copy.
- If not found 3 times, reduce confidence score yet still present data
- Show user message to check the value

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

## [x] Completed: Flight plan: FMS task info order
## [x] Completed: Add FMS programming tip
## [x] Completed: Flight release more persistent
## [x] Completed: Flight plan: weight & balance visually compare against limits
## [x] Completed: UI optimization for maximum-limit weight cards
## [x] Completed: Progress bar text overlay visual fix
## [x] Completed: Extract weight-balance field rendering logic
## [x] Completed: Add ramp weight to Filament aircraft resource
### [x] Completed: Flight plan: Weight & Balance: Operational badging
### [x] Completed: Flight plan: Overview: Remove Redundant Data (De-cluttering)
### [x] Completed: Flight plan: Overview: Integrate MEL / CDL Summary Block
## [x] Completed: Flight plan: Overview: Weight and Balance
## [x] Completed: Feat: Establish MEL badge color heiracrchy
Currently: badge colors are used on individual maintenance items. Badge counts are always rendered in yellow with no context to the maintenance items within them.

Goal: Use context aware badge colors.
If MEL items exist, badge count should be rendered in red. If no MELS, but CDLs exist, render count in orange. If no MEL or CDL but NEFs exist, render in gray, If only DMIs exist, render in yellow.

```public function badgeColor(): string
    {
        return match ($this) {
            self::Mel => 'bg-red-100 text-red-900 dark:bg-red-400/15 dark:text-red-200',
            self::Cdl => 'bg-orange-100 text-orange-900 dark:bg-orange-400/15 dark:text-orange-200',
            self::Nef => 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100',
            self::Dmi => 'bg-yellow-100 text-yellow-900 dark:bg-yellow-400/15 dark:text-yellow-200',
        };
    }
```

References:
app/View/Presenters/FlightRelease/MaintenancePresenter.php
app/Enums/TaskTone.php

Outcome:

- Added an enum-owned maintenance priority of MEL, CDL, NEF, then DMI for the task counter.
- Reused each maintenance type's existing badge palette: red, orange, gray, and yellow respectively.
- Preserved the green zero-item counter state and kept badge policy out of Blade.
- Added focused hierarchy and Livewire rendering coverage.

Commit message: `feat: color maintenance counters by item priority`

## [x] Completed: Refactor: MEL Dashboard Metric Card

**Context**
Refactoring a badge-style notification into a dashboard metric card. 

**Diagnostics**
Initial analysis of the element structure and content:

Goal: Emphasize MEL count using a font size of 5-xl similar to weight and balance overview card. Count color should correspond to MEL badge color previously implemented in the Maintenance badge count.

Outcome:

- Replaced the small MEL/CDL badge in the Overview card with a 5xl dashboard metric.
- Colored the metric from the highest-priority maintenance type using the enum-owned palette.
- Preserved the MEL/CDL-only count, review action, explanatory copy, accessible count label, and empty state.
- Added focused enum, view-model, and Livewire rendering coverage.

Commit message: `refactor: present mel cdl count as overview metric`

## [x] Completed: Flight plan: W&B Card order

Goal: Prioritize calculated operational totals before their supporting inputs on the dedicated Weight & Balance screen.

Outcome:

- Reordered Base & Payload to zero-fuel weight, basic operating weight, then payload.
- Reordered Departure to ramp weight, takeoff gross weight, then takeoff fuel.
- Preserved Arrival order, comparison configuration, values, calculations, and responsive styling.
- Added focused presenter-output and rendered DOM-order coverage.

Commit message: `refactor: reorder weight balance cards for review flow`

## [x] Completed: Flight plan: Overview: Full card link
Currently: The only link in an overview card is in the action label footer.

Goal: create a component option to have the whole card a clickable link.

Outcome:

- Made the reusable Overview card action cover the full card by default, with an explicit opt-out option.
- Applied it to actionable Overview cards while leaving unavailable and empty cards non-interactive.
- Preserved the footer as a visual action cue and used one full-card Livewire button to avoid nested controls.
- Added full-card keyboard focus, accessible names, loading feedback, and focused Livewire coverage.

Commit message: `feat: make overview cards fully actionable`
