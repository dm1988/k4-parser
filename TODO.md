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
## Idea: Flight plan: Refactor overview task
- Emphsize attention items
- Remove duplicate data that exists in flight strip header
- Show MELs/CDLs if they exist
- Show ETOPS info if it exists

## [x] Completed: Flight plan: weight & balance visually compare against limits

Outcome:

- Resolved structural limits from the aircraft matched by the normalized flight-release tail number.
- Persisted the applicable ramp, zero-fuel, takeoff, and landing limits with the normalized release result.
- Added planned-versus-limit cards with labeled units, utilization percentages, accessible progress indicators, and green, blue, amber, or red operating-margin states.
- Reported unmatched aircraft, absent limits, and incompatible units as unavailable without inferring operational values.
- Added focused resolver, normalization, persistence, threshold, unavailable-state, and Livewire rendering coverage.

Commit message: `feat: compare planned weights with aircraft limits`

## Follow up:
Derived / Calculated Indicators: The card note mentions "Derived server-side from confirmed zero-fuel weight...". Adding a small info icon or tooltip next to derived values helps distinguish between manually entered inputs and system-calculated totals.

## [x] Completed: UI optimization for maximum-limit weight cards

Outcome:

- Consolidated percentage, maximum weight, unit, and limit label inside the progress bar for zero-fuel, ramp, takeoff, and landing weight cards.
- Removed each maximum-limit card's redundant structural-limit column and external operating-state labels.
- Increased progress-bar height and added a responsive, truncated, high-contrast overlay that remains readable across all status colors.
- Preserved accessible progress names and value text, plus explicit unavailable-limit handling.
- Added focused Livewire coverage for all four integrated limit cards.

Commit message: `refactor: consolidate weight limits into progress bars`

## [x] Completed: Progress bar text overlay visual fix

Outcome:

- Corrected the optical vertical alignment of maximum-limit progress-bar text with a compact line height and slight top offset.
- Removed the dark pill background and shadow from the text while preserving its responsive truncation and contrast.
- Added focused Livewire assertions for the overlay alignment and simplified text treatment.

Commit message: `fix: align weight progress bar overlays`

## [x] Completed: Extract weight-balance field rendering logic

Outcome:

- Added a typed weight-balance field view model for formatted values, status styling, comparison state, progress values, and accessibility labels.
- Added enums that own comparison labels, progress and text classes, plus source-status badge classes.
- Reduced the Blade component to declarative rendering against named view-model values and predicates.
- Added cross-browser progress-fill styling so safe, heavy, caution, and exceeded states render their intended colors.
- Set the heavy progress fill to the specified high-visibility blue, including an explicit WebKit override.
- Added focused coverage for integrated limits, exceeded limits, source conflicts, incompatible units, and all rendered tone classes.

Commit message: `refactor: extract weight balance field view model`

## Feat: flight plan: Offline fuel score
Goal: Create link on open seperate offline fuel score with a basic java script calculator. Able to calculate ETA and FOB at each waypoint.

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
* Schedule and Flight Plan Extractors are both visible with equivalent product hierarchy.
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

## Refactor welcome page for use with new features

Audit outcome:

- The page is positioned as a Jeppesen Crew Access Schedule Extractor rather than Crew Compass's K4 Extractor product entry point. Its title, header, hero, benefits, screenshot, primary CTA, and account-security copy all describe only the Schedule Extractor.
- The visual treatment relies on indigo, emerald, and amber accents instead of the documented Aviation Blue, Compass Gold, Cloud White, Midnight, and Steel Gray palette, despite reusable `cc-*` styles and the Crew Compass logo already existing.
- The header and hero each use an `h1`, the screenshot has generic alternative text, and interactive elements need consistent keyboard-focus treatment.
- The public route is static, while authenticated feature access is already centralized in `User::canUseScheduleExtractor()` and `User::canUseFlightRelease()`. The welcome page can remain presentation-only and use those existing decisions for authenticated CTAs without adding a new backend layer or querying from Blade.

Refactor plan:

1. Reframe the metadata and header around the brand hierarchy: Crew Compass as the umbrella brand, K4 Extractor as the application, and one descriptive page `h1`. Reuse the existing Crew Compass logo and theme selector, and retain login, registration, dashboard, privacy, feedback, and independence links.
2. Replace the Schedule-only hero with concise product-level copy based on the shared promise: turn operational documents into reviewable information without manual re-entry. Keep Jeppesen Crew Access as supported Schedule Extractor context rather than the page's identity, and include the operational-verification disclaimer required by the brand voice.
3. Add a responsive two-tool section using a reusable Blade feature-card component. Give Schedule Extractor and Flight Plan Extractor equal visual hierarchy, crew-familiar descriptions, suitable icons, and a `Demo` badge on Flight Plan Extractor while that status applies. Move the existing phone screenshot into Schedule-specific supporting content instead of using it as the product-wide hero; do not invent a Flight Plan screenshot.
4. Make calls to action access-aware. Guests receive registration and login paths; authenticated users see direct links only for tools allowed by the existing entitlement methods, with a clear unavailable state otherwise. Keep hidden navigation from being treated as authorization and preserve all route middleware and gates.
5. Restyle the page with existing `cc-*` utilities and supported Tailwind CSS 3 classes, adding narrowly scoped reusable marketing styles only where repetition warrants it. Apply Aviation Blue to structure, Compass Gold to emphasis and CTAs, Cloud White/Midnight surfaces, Steel Gray secondary copy, matching dark mode, responsive spacing, visible focus states, semantic landmarks, and specific image alternative text.
6. Update focused PHPUnit feature coverage for Crew Compass/K4 Extractor identity, both tool summaries, guest and authenticated CTA states, feature-disabled states, the demo badge, theme controls, disclaimer/footer content, and removal of Schedule-only assumptions. During implementation, run the focused welcome/theme tests, Pint after PHP or Blade changes, a production Vite build for Tailwind validation, then Larastan once at the final integration checkpoint.

Proposed commit message: `refactor: make welcome page a branded product hub`

## Implement CrewCompass tie ins, branding, and marketing

Reference figma make plan

Audit outcome:

- Airport info is complete in flight cards and the flight-route extractor.
- Primary placement: show Crew Compass content on each layover card, below the hotel details. Display the resolved city, whether a layover guide is available, the number of available places, and links to the guide/city when available.
- Secondary placement: add the same compact city summary to origin and destination airport popovers. Do not duplicate it in the expanded airport-details accordion.
- Data gap: airport enrichment currently handles flight origins and destinations only. Layover events expose a station code but are not resolved to a canonical Crew Compass city.

Simple plan:

1. Extend the Crew Compass airport provider response with a canonical city identifier/slug, guide availability and URL, places count, and city URL. Resolve by airport/station code rather than city name.
2. Extend schedule enrichment to include unique layover station codes and attach the city summary to layover metadata, reusing the existing cached airport-resolution flow and avoiding requests from Blade views.
3. Expose typed city-summary data through the event and flight-card view models, then render a reusable Crew Compass city-summary component on layover cards and airport popovers.
4. Add focused provider, enrichment, view-model, and Blade component tests for available, unavailable, zero-place, duplicate-city, and provider-failure cases.

## feat: Track schedule upload count
- For multiple file uploads within each user request

## Flight plan: Crew list: role avatar
- Have crew role displayed inside an avatar bubble
Entry: Crew Card UI Refactor
Goal Improve the visual hierarchy and scannability of the crew roster by moving the "Crew Role" (e.g., PIC, SIC, MX) from a secondary text line into a prominent "Avatar Bubble" anchor. The design must be professional, differentiate roles at a glance, and maintain high readability in both light and dark modes without being visually overwhelming.

Current Setup

Container: ul grid using grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 for responsive layout.
Card Structure:
Horizontal flex layout (flex items-center gap-3).
Backgrounds: bg-white (light) / bg-slate-900 (dark).
Borders: Subtle navy tint border-[#1B365D]/10.
Avatar Bubble: A 12x10 (48px wide) flex container with a uniform background (bg-[#1B365D]/5) and role-specific text coloring.
Details: A vertical stack containing the Name (bold) and Employee Number (monospace, prefixed with #).
Implementation Details

Layout Logic:
Switched from flex-col to flex-row (using items-center) to place the role avatar as a visual "bullet" on the left.
Role-Based Semantic Styling:
Unified Background: All bubbles use bg-[#1B365D]/5 to maintain page consistency.
Text Color Palette:
PIC: text-blue-600
SIC/FO: text-indigo-600
MX: text-amber-600
LM: text-emerald-600
Others: text-slate-600
Typography:
Role: text-[10px] font-black uppercase tracking-wider for a "badge" aesthetic.
Employee ID: Simplified to a small secondary row (text-[10px]) to reduce vertical height.
Dark Mode Support: All colors include dark: variants (e.g., dark:bg-slate-800 for the bubble and dark:text-blue-400 for role text) to ensure WCAG contrast compliance.

## Flight plan: Add task: Takeoff and Landing Report

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

## Flight plan: Aircraft lookup and display weights
- Lookup aircraft by tail_number in db
- Expose weights to user
- Compare planned weights to aircraft weight limits

## 17. Flight plan: Reserve fuel
- Create distinction between Alternate airport burn and Reserve fuel calculation. 
- Differed due to needing aircraft type fixture and distintion between 747 and 777 aircraft type
- Requires full fleet in production database.
- coincides with future 747 seeder into production
- will have to add migration for reserve fuel additive

## Flight plan: Smart maintenance counter badges
- If MELs exist render in warning
- If no MELs but CDLs present, render caution
- If no MEL and CDLs but NEF or COI carry over, render Neutral
- No maintenance items, render success

app/Enums/TaskTone.php
  
-------------------------------------------------------

# Completed Tasks

-------------------------------------------------------

## Completed: Schedule: cannot remove selected upload images
## Completed: Slot time incorrectly extracted
## [x] Completed: Flight plan: Employee number missing from Maintenance log task and create common component
## [x] Completed: Flight plan: Refactoring Employee Card Components
## [x] Completed: Flight plan: flight info header refactor
## [x] Completed: Remove info logging
## [x] Completed: Flight plan: Hide Planned Duration in FMS task
## [x] Completed: Flight plan: FMS task info order

Outcome:

- Reordered the FMS setup metrics to AC type, flight number, recall number, alternate, destination distance, alternate reserves, initial flight level, and cost index.
- Added the alternate airport as its own FMS metric card.
- Moved `Planned runways and procedures` above `Airport context`.
- Added focused view-model and Livewire regression coverage for both ordering requirements.

Commit message: `refactor: reorder fms task information`

## [x] Completed: Add FMS programming tip

Outcome:

- Added a gold-accent programming tip card with a light-bulb icon to the FMS task.
- Positioned the tip between `Planned runways and procedures` and `Airport context`.
- Added focused Livewire coverage for the tip content and placement.

Commit message: `feat: add fms programming tip`

## [x] Completed: Flight release more persistent

Outcome:

- Persisted one encrypted, opaque-keyed flight-plan result per user with cascade deletion and owner-scoped reads and deletes.
- Restored the latest authorized result on fresh Livewire mounts independently of cache or session lifetime.
- Preserved the prior saved release when replacement extraction fails, while explicit discard removes it across refreshes.
- Kept uploaded PDFs ephemeral and limited persistence to the serializer's normalized `flight_plan_data` payload.
- Added focused coverage for encryption, replacement, authorization boundaries, restoration, discard, failure preservation, and cleanup.

Commit message: `feat: persist the latest flight release per user`
