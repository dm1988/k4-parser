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

## Github CI Tests fail

Check github env

Illuminate\Foundation\ComposerScripts::postAutoloadDump
  > @php artisan package:discover --ansi
  
     InvalidArgumentException 
  
    Please provide a valid cache path.
  
    at vendor/laravel/framework/src/Illuminate/View/Compilers/Compiler.php:75
       71▕         $compiledExtension = 'php',
       72▕         $shouldCheckTimestamps = true,
       73▕     ) {
       74▕         if (! $cachePath) {
    ➜  75▕             throw new InvalidArgumentException('Please provide a valid cache path.');
       76▕         }
       77▕ 
       78▕         $this->files = $files;
       79▕         $this->cachePath = $cachePath;
  
        +19 vendor frames 
  
    20  [internal]:0
        Illuminate\Foundation\Application::{closure:Illuminate\Foundation\Application::boot():1138}()
        +6 vendor frames 
  
    27  artisan:16
        Illuminate\Foundation\Application::handleCommand()
  
  Script @php artisan package:discover --ansi handling the post-autoload-dump event returned with error code 1
  Error: Process completed with exit code 1

## Flight plan: Parse bottlenecks:

### Reduce PDF extraction and airport lookup latency

Goal:

- Reduce uncached flight-plan parse time by eliminating avoidable airport API latency and identifying the slow stages within PDF parsing and OCR.

Problem:

- PDF parsing/OCR is the largest and most variable cost, but the current Debugbar measurements do not distinguish `parseFile()`, page text extraction, and per-page OCR.
- Departure, destination, and alternate airport metadata requests run sequentially, adding roughly 1.1–1.5 seconds to an uncached parse.
- Flight-plan airport lookups bypass the existing airport cache, causing repeated remote requests for airport codes already resolved elsewhere.

Current setup:

- A 5.77-second request spent 73.52 ms on 10 database queries, 823 μs on a missed PDF text-cache lookup, 28.62 ms writing that cache, 3.846 seconds in the extraction pipeline, and 521 ms + 283 ms + 279 ms on three sequential airport API calls. Rendering and other overhead were comparatively small.
- A preceding 15.51-second request showed the same pattern, with 13.488 seconds spent in extraction.
- The requests used different PDF hashes, so both legitimately missed the seven-day text cache.
- The database-backed cache and query count are not material bottlenecks.

Implementation / fixes:

1. [x] Completed: Route flight-plan departure, destination, and alternate lookups through the existing `AirportCodeCache`, preserving the current airport metadata contract and failure behavior.
2. [x] Completed: Deduplicate airport codes before lookup so identical route stations are resolved once per parse.
3. Add timing spans around `parseFile()`, page text extraction, and each OCR operation, including page context and whether OCR was required, without recording document contents.
4. Add focused tests proving airport cache hits avoid provider calls, duplicate codes are resolved once, cache misses retain current results, and provider failures remain non-fatal where currently supported.
5. Re-profile one cold-cache and one warm-cache parse, then record the timing comparison here before marking the task complete.

References:

- [FlightPlanTextExtractor.php](/home/dm1988/k4-parser/app/Services/FlightPlan/Extractor/FlightPlanTextExtractor.php)
- [FlightRouteExtractor.php](/home/dm1988/k4-parser/app/Services/FlightPlan/Extractor/FlightRouteExtractor.php)
- Existing `AirportCodeCache` implementation and its focused tests.

Proposed commit message: `perf: reduce flight plan parsing bottlenecks`

Outcome:

- Flight-plan airport lookups now reuse cached found, missing, and unavailable resolutions. Provider failures remain non-fatal and return `null` airport metadata.
- Focused coverage verifies cached resolutions avoid repeated provider calls and unavailable providers retain the existing nullable response contract.
- Route stations are deduplicated before cache or provider access, while each departure, destination, and alternate field retains its expected airport metadata.

Task 1 commit message: `perf: cache flight plan airport lookups`

Task 2 commit message: `perf: deduplicate flight plan airport lookups`

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

## Flight plan: Refactor overview task
- Emphsize attention items
- Remove duplicate data that exists in flight strip header
- Show MELs/CDLs if they exist
- Show ETOPS info if it exists

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

## Completed: Repair Composer lockfile for CI
## Completed: Resolve Larastan errors in tests
## Completed: Chore: update laravel
