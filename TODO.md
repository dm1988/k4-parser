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
## [x] Completed: Overview: whole card color change
Instead of 5-xl metric color change, change the whole card color. For the 5xl stat,

Overview cards affected:
- W&B
- MEL
- Slot (metric not yet implemented)
- GENDEC (not yet implemented)


**Context**
Styling adjustment for status cards within a flight plan task panel. The goal was to shift the visual emphasis from the individual metric (`.text-5xl`) to the entire card container (`article`) using subtle, "glassmorphism" design techniques.

**Diagnostics**
The target elements were identified within a grid container (`.grid.xl\:grid-cols-6`).

| Element Type | Primary Selector | Status Logic |
| :--- | :--- | :--- |
| **Alert Card** | `article` containing `.text-red-700` | Red status (e.g., MEL/CDL) |
| **Info Card** | `article` containing `.text-[#1B365D]` | Blue status (e.g., Weight & Balance) |

**Actionable Findings**
* **Container Styling:** To avoid overwhelming the UI, solid backgrounds were replaced with low-opacity tints (5-10%) and a `backdrop-filter: blur(8px)` to create a glass effect.
* **Accent Indicators:** A 4px left-border was identified as a more effective status indicator than full-card saturation.

**Code Fixes**
The following CSS strategy was identified to achieve the glassmorphism effect and specific link overrides. These styles should be adapted into the project's Tailwind configuration or CSS modules.


`````css
/* Example styling for Alert/Red cards */
.card-alert {
  background-color: rgba(239, 68, 68, 0.05);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-left: 4px solid #ef4444;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

/* Example styling for Info/Blue cards */
.card-info {
  background-color: rgba(14, 165, 233, 0.05);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(14, 165, 233, 0.3);
  border-left: 4px solid #0ea5e9;
}
`````

**Outcome**

- Moved MEL/CDL and Weight & Balance status emphasis from the 5xl metric to the full Overview card surface.
- Added subtle 5–10% status tints, an 8px backdrop blur, a 4px left accent, light/dark borders, and neutral high-contrast metric text.
- Preserved maintenance priority and Weight & Balance severity palettes while adding a reusable custom-surface option for future Slot and GENDEC cards.
- Added focused enum, view-model, and Livewire rendering coverage. Focused tests, Pint, the production Vite build, and Larastan pass.

Commit message: `refactor: move overview status color to cards`

## Flight plan: Overview: Slot times overview card refactor
Large 5-xl metric for slot time count

Buffer time from planned departure to earliest slot window time.

Context aware `Do not depart early` messaging if planned etd is equal to departure min slot window or if planned eta is equal to planned arrival min slot window. Render card in caution amber.

## Feat: Domestic / international flight determine
Domestic Flight: A flight that operates entirely within the sovereign territory and airspace of a single country, departing and landing at airports located in the same nation without crossing or clearing international customs boundaries (e.g., PANC to CONUS, or Hawaii to CONUS).

International Flight: A flight where the departure airport and arrival airport (or intermediate technical/operational stops) are located in different sovereign countries or territories, requiring clearance through international customs, immigration, and agricultural control authorities (e.g., PANC to NRT, or CONUS to YVR).

Will determine if a GENDEC is needed. GENDECs are not typically needed on domestic flights. For the flight plan purpose, PANC to Conus or Hawaii to conus are domestic flights.

Logic:
* **PANC to CONUS:** Domestic -> No GENDEC required
* **PHNL / PHOG to CONUS:** Domestic -> No GENDEC required *(Note: State-specific agricultural declarations may apply, but not an international GENDEC)*
* **PANC / CONUS to Foreign Destination (or vice-versa):** International -> GENDEC required

## Feat: GENDEC card
After Domestic / Int flight task is complete and international flights can be determined:
Move from GENDEC in operational status to it's own dedicated card. 
If GENDEC not available and domestic flight, render `Domestic flight: GENDEC likely not required`. If intl and no GENDEC, render card in caution amber with message `GENDEC not found in flight plan. Verify against actual flight plan.`

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

### Goal

Add a link from the Flight Plan Brief fuel task to a separate, focused fuel-score calculator that opens in a new browser tab. After the page loads, the calculator must work entirely in the browser and calculate an estimated UTC arrival time and fuel on board (FOB) for each supported waypoint.

### Calculation contract

* Inputs are Off time in four-digit UTC (`HHMM`) and starting FOB in the release's confirmed fuel unit.
* Waypoint ETA equals Off time plus the waypoint's confirmed cumulative duration, with 24-hour rollover. Label every result as UTC.
* Planned burn to a waypoint equals release takeoff fuel minus that waypoint's confirmed planned remaining fuel.
* Calculated waypoint FOB equals the user-entered starting FOB minus the planned burn to that waypoint.
* Preserve the confirmed release unit throughout a calculation; never convert or mix pounds and kilograms implicitly.
* Do not calculate a waypoint ETA when cumulative duration is unavailable.
* Do not calculate a waypoint FOB when starting FOB, release takeoff fuel, waypoint remaining fuel, or a consistent unit is unavailable or invalid.
* Reject non-numeric or negative fuel inputs and do not present negative calculated FOB as a valid result.
* Calculated values are planning aids only. Do not infer a fuel score, compliance status, dispatchability, or safety determination.

### Implementation plan

1. Add an authenticated, verified, flight-release-authorized GET route for a standalone fuel-score page, keyed by the current `flightPlanKey`.
2. Resolve the result through `FlightPlanResultStore` for the authenticated owner. Return a not-found response for a malformed, expired, missing, or other-user key rather than exposing whether a result exists.
3. Reuse `BuildFlightPlanPageData` and a dedicated presenter/view-data method to provide only the identity, fuel unit, takeoff fuel, and waypoint fields required by the calculator. Do not parse, normalize, query, or authorize in Blade.
4. Add a clearly labeled `Open offline fuel calculator` link to the existing Fuel Score task. Open it with `target="_blank"` and `rel="noopener noreferrer"`, retain visible keyboard focus, and only render it when a current result key is available.
5. Simplify the existing Livewire Fuel Score task to source review only: remove the Off time input, planned ETA column, ETA calculation state, and all calculator bindings from `fuel-score.blade.php`.
6. Remove the waypoint `More…` details/summary accordion and render the full waypoint source list directly on the Livewire page. Use server-rendered Blade rows so the list does not depend on calculator JavaScript.
7. Build a compact standalone Blade page using the existing Crew Compass palette and light/dark theme behavior. Include flight identity, UTC/unit labels, Off time and starting FOB inputs, a reset action, calculation guidance, and a responsive waypoint results table.
8. Move/refactor the ETA logic from `waypoint-fuel-monitor.js` into pure ETA and FOB calculation functions plus Alpine state loaded only by the standalone page. Remove the calculator module from the main application entry point when the Livewire view no longer consumes it.
9. Calculate results locally after the initial page render. Do not make Livewire, fetch, analytics, or other network requests while values are entered or recalculated.
10. Define "offline" as no server dependency after the authorized page and its compiled assets have loaded. Do not add service-worker/PWA caching or promise that a fresh page can be opened or refreshed without a network connection in this task.
11. Keep entered Off time and FOB ephemeral to the tab. Do not write operational values to the database, URL, `localStorage`, or `sessionStorage`.
12. Render unavailable source inputs as `Not present in this release` and calculated values as `Unable to calculate`, with a concise reason. Preserve explicit zero values.
13. Update focused Livewire/PHPUnit coverage to confirm the waypoint list is always visible, the `More…` accordion and embedded ETA controls are absent, and the standalone link has the required attributes. Also cover route authentication, verification, feature authorization, result ownership/not-found behavior, required page data, units, and missing-source states.
14. Add focused JavaScript tests for the standalone calculator's UTC validation, midnight rollover, planned-burn and FOB calculations, zero values, decimal handling, invalid/negative inputs, unit mismatch, unavailable waypoint fields, and prevention of negative output.
15. Run the focused PHPUnit and JavaScript tests, Pint for changed PHP files, a production Vite build, and Larastan at the final integration checkpoint.

### Acceptance criteria

* The Flight Plan Brief Fuel Score task contains an accessible link that opens the standalone calculator in a new browser tab without giving the new page access to `window.opener`.
* The Livewire Fuel Score task shows the complete waypoint source list without a `More…` accordion or another disclosure action.
* The Livewire page no longer displays an Off time input or calculated waypoint ETA and does not initialize calculator JavaScript.
* ETA and FOB calculation functions and interactive state are dedicated to the standalone page.
* Only an authenticated, verified, authorized owner can open a calculator for a stored flight-plan result.
* The page makes no network request to calculate or recalculate values after its initial load.
* A valid Off time calculates UTC ETA for every waypoint with a confirmed cumulative duration, including correct midnight rollover.
* A valid starting FOB calculates waypoint FOB from confirmed release takeoff fuel and planned remaining fuel in one explicitly displayed unit.
* Missing, inconsistent, malformed, or unsafe inputs do not produce a plausible-looking value; the affected row explains why it cannot be calculated.
* Explicit zero values remain distinguishable from missing values.
* User-entered values are not persisted or included in URLs.
* The standalone layout remains usable by keyboard and at narrow/mobile widths, with visible focus and accessible labels.
* The page states that results are browser-calculated planning aids and do not determine compliance, dispatchability, or safety.
* Focused PHPUnit and JavaScript tests pass, Pint reports clean formatting for changed PHP, the Vite production build succeeds, and final Larastan passes.

### References

* `app/Livewire/FlightPlanBrief.php`
* `app/Services/Infrastructure/FlightPlanResultStore.php`
* `app/View/Presenters/FlightRelease/FuelPresenter.php`
* `resources/views/components/flight-release/fuel-score.blade.php`
* `resources/js/waypoint-fuel-monitor.js`
* `tests/Feature/Livewire/FlightPlanBriefTest.php`
* `tests/JavaScript/waypoint-fuel-monitor.test.js`

### Outcome

Implemented the owner-scoped standalone calculator and new-tab link. The Livewire Fuel Score task now shows server-rendered waypoint source rows directly, without the `More…` accordion or ETA controls. ETA and FOB calculations run only in the standalone tab, with explicit UTC and fuel units and unavailable-value reasons.

Focused route and Livewire tests, JavaScript calculator tests, Pint, the production Vite build, and final Larastan passed.

### Commit message

`feat: add standalone offline waypoint fuel calculator`

### [x] Follow up

Follow-up outcome: ETA now depends only on Off time and confirmed cumulative duration. The standalone table uses the requested column order, normal-weight waypoint labels, source TBO above ATA, source FRMG above AFOB, actual burn (ABO) above its signed comparison, and a rightmost destination estimate. Burn comparison uses the previous actual fuel reading (or starting FOB for the first leg). Destination fuel projects the entered AFOB using the release's confirmed estimated landing fuel; unavailable or inconsistent source values remain uncalculated. Focused extraction, page-data, route-rendering, and JavaScript tests pass.

### [x] Follow up 2

Follow-up 2 outcome: Every waypoint defaults to collapsed and expands independently with a keyboard-accessible `+` control. Collapsed rows show waypoint, ETA, cumulative duration, planned FOB, and destination estimate; TBO, ATA, AFOB, and fuel/burn comparisons are shown on expansion. Empty inputs display quiet placeholders instead of unavailable-calculation prompts, while entered invalid data still receives a reason. The fuel column is labeled `Fuel on board` with `Planned FOB · AFOB` beneath it.

## [x] Completed: Follow up 3

Outcome: Each expanded waypoint displays a signed UTC minute difference below ATA once a valid ATA is entered. Positive means ahead, negative means behind, and zero means on time. The calculation handles midnight rollover by using the shorter clock difference; an exact 12-hour separation is left unclassified because the waypoint date is unavailable. Focused JavaScript and page-rendering tests cover the result and empty or invalid inputs.

Commit message: `feat: compare waypoint ETA and ATA in offline fuel score`

## [x] Completed: Follow up 4

Outcome: Each waypoint's cumulative ABO is the entered actual Starting FOB at takeoff minus that waypoint's AFOB; earlier waypoint AFOB entries are no longer required. The cumulative planned burn comes from the waypoint's TBO, interpreted in the release fuel unit at the same ×100 scale as FRMG. The signed comparison is TBO minus ABO, so positive means below planned burn. The page labels cumulative values and explains missing starting FOB, TBO, or fuel units. Focused JavaScript and page tests, Pint, and the production build pass.

Commit message: `feat: compare cumulative waypoint burn with TBO`

## [x] Completed: Follow up 5: Table Refactor: Waypoint Estimates

Outcome: The standalone waypoint table now uses compact, single-line collapsed rows with `ETA`, `T/TME`, and `Fuel on board` headers. ATA, TBO, AFOB, comparisons, and calculation guidance live in a separate expandable detail row, so they do not increase the collapsed row height. Summary cells use `py-1` and middle alignment; the detail row is cloaked until Alpine initializes. Existing UTC and fuel-unit guidance remains visible above the table. Focused page and JavaScript tests, Pint, and the production build pass.

Commit message: `refactor: compact offline waypoint estimates table`

### [x] Follow up 6: Fuel score table refinements

Outcome: Replaced the high-contrast expand glyph with a muted, thin-stroke chevron that rotates when a waypoint opens. ETA, T/TME, planned FOB, and destination fuel are right-aligned; `min` and fuel units render in smaller secondary text while preserving explicit zero and unavailable states. Softer row borders, a subtle hover state, and a muted destination dash improve scanability. Focused JavaScript and page tests, Pint, and the production build pass.

Commit message: `refactor: refine offline fuel score waypoint table`

## [x] Completed: Line Break Fix: Flexbox Label Content

Outcome: Wrapped the takeoff Starting FOB caption, including its dynamic fuel unit, in one non-wrapping span. The column flex label now has one caption item above its input instead of separate text fragments. A focused page test checks the rendered markup; Pint and the production build pass.

Commit message: `fix: keep takeoff FOB label on one line`

## [x] Completed: feat: Flight plan: overview: ETOPS card
- Rendered only if ETOPS flight
- Count of ETP points
- ETOPS time (e.g. 180, 210, 240)

Goal: Show a compact, source-backed ETOPS overview card only when the flight is confirmed ETOPS. Present the number of equal-time points and the confirmed ETOPS rating in minutes.

Current implementation: The overview already conditionally renders an ETOPS evidence card, but it shows a generic summary of critical points and boundary-point labels. Typed ETOPS data already contains applicability, rating minutes, and equal-time points.

Problem: The card does not directly answer how many ETPs were extracted or what ETOPS time applies. An empty ETP list or missing rating must not appear as a confirmed zero or invented rating.

Implementation plan: Add nullable ETP-count and rating display methods to the existing ETOPS presenter and page view model; replace the generic overview metric with two labeled metrics; keep the existing confirmed-ETOPS visibility rule and detail navigation; cover confirmed, non-ETOPS, and missing-source states with focused tests.

Outcome: The confirmed-ETOPS overview card now shows source-backed ETP count and ETOPS time in minutes. Entry and exit boundary points are excluded from the count. When either value is unavailable, its metric says `Not present in this release` instead of showing zero or an inferred rating. Non-ETOPS flights still have no ETOPS card, and the detailed ETOPS view remains unchanged. Focused view-model and Livewire tests, Pint, the Vite build, and Larastan pass.

Follow-up outcome: The overview time and ETOPS badge now share one confirmed-rating lookup while retaining their separate `180 min` and `ETOPS 180` formats. Focused view-model tests cover confirmed ratings, absent ratings, non-ETOPS flights, and missing ETOPS data.

Commit message: `feat: show ETP count and ETOPS time on overview card`
