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
## Completed: Bug: Schedule image extraction not working
Currently:
On image upload, an exception is thrown.

Exception thrown: 
  Carbon\Exceptions\InvalidFormatException
  A textual month could not be found

Fix: Investigate extraction regex.

References:
app/Services/Schedule/Extractor/TripInformationParser.php
storage/app/private/schedules/IMG_0471.jpg
storage/app/private/schedules/IMG_0473.jpg

Outcome:

- Restricted Trip Information date ranges to valid three-letter month abbreviations so OCR artifacts such as `Sen` and `San` are ignored instead of passed to Carbon.
- Confirmed both referenced images complete OCR and parsing without exceptions, each producing its expected flight and duty events.
- Added focused regression coverage for the invalid OCR month artifacts while preserving valid schedule date ranges.

Commit message: `fix: ignore invalid OCR month abbreviations`

## Allow image schedule results when exeptions are thrown
Currently:
With multiple image uploads, when 1 or more image fails to extract yet 1 or more images succeeds, no results are shown and an error is shown to the user.

Goal:
If there are images with errors, create a user facing model listing files with extraction errors before showing results.

## Slot time incorrectly extracted
Currently:
Arrival and departure slot times are confused. The example below shows an arrival slot window of 0145-0245, when that is the departure slot window. The display slider incorrectly shows outside of the window, when in reality an arrival of 0431Z UTC falls within the arrival slot time window.

Extracted text:
`APPROVED SLOT TIMES: DEP 0215Z (+/- 30 MIN ) ARR 0445Z (+/- 30 MIN )`
(html) ```
<ol class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <!--[if BLOCK]><![endif]-->                <li class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-3 border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="rounded-full bg-[#1B365D] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-white dark:bg-blue-500/20 dark:text-blue-200">Arrival</span>
                            <span class="font-mono text-sm font-bold text-[#1B365D] dark:text-slate-100">DEP</span>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#B8860B] dark:text-amber-300">UTC</span>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 p-4">
                        <div class="flex flex-col gap-1">
                            <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Date</dt>
                            <dd class="font-mono text-sm font-semibold tabular-nums text-[#0B0E14] dark:text-slate-100">Sep 17, 2026</dd>
                        </div>
                        <div class="flex flex-col gap-1 text-right">
                            <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Time (UTC)</dt>
                            <dd class="font-mono text-lg font-bold tabular-nums text-[#1B365D] dark:text-blue-200">0215Z</dd>
                        </div>
                        <!--[if BLOCK]><![endif]-->                            <div class="col-span-2 flex flex-col gap-1 border-t border-[#1B365D]/10 pt-3 dark:border-slate-700">
                                <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Approved window</dt>
                                <dd class="flex flex-wrap items-baseline justify-between gap-2 font-mono text-sm font-semibold tabular-nums text-[#0B0E14] dark:text-slate-100">
                                    <span>Sep 17, 0145Z–Sep 17, 0245Z UTC</span>
                                    <span class="text-[#B8860B] dark:text-amber-300">± 30 min</span>
                                </dd>
                            </div>
                        <!--[if ENDBLOCK]><![endif]-->                        <!--[if BLOCK]><![endif]-->                            <div class="col-span-2 flex flex-col gap-2 border-t border-[#1B365D]/10 pt-3 dark:border-slate-700">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Planned arrival comparison</dt>
                                    <dd class="font-mono text-xs font-semibold tabular-nums text-[#0B0E14] dark:text-slate-100">Sep 17, 0431Z UTC</dd>
                                </div>
                                <div class="relative h-3 rounded-full bg-[#1B365D]/10 dark:bg-slate-700" aria-hidden="true">
                                    <div class="absolute inset-y-0 left-1/4 right-1/4 rounded-full bg-[#B8860B]/35 dark:bg-amber-400/30"></div>
                                    <div class="absolute -top-1 h-5 w-1 -translate-x-1/2 rounded-full bg-[#1B365D] dark:bg-blue-300 left-full"></div>
                                </div>
                                <div class="flex justify-between gap-3 text-[10px] font-semibold text-[#4A5568] dark:text-slate-400">
                                    <span>Earlier</span>
                                    <span class="text-center text-[#B8860B] dark:text-amber-300">Confirmed window</span>
                                    <span>Later</span>
                                </div>
                                <dd class="text-xs font-semibold text-[#1B365D] dark:text-blue-200">Planned ETA is outside the confirmed window</dd>
                            </div>
                        <!--[if ENDBLOCK]><![endif]-->                    </dl>
                </li>
            <!--[if ENDBLOCK]><![endif]-->        </ol>
```
## Remove info logging
Currently: Every successful extraction gets logged as well as a db record added as a event request.

Code:
        Log::info('K4 extraction completed', [
            'extract_request_id' => $extractRequest->id,
            ...$counts,
        ]);
References:
app/Services/Infrastructure/ExtractRequestLogger.php

## Completed: Bug: RJAA flight release false maintenance conflict and upload error layout

Outcome:

- Allowed the maintenance-section parser to recognize `PASSED RAIM REQUIREMENTS` when native PDF extraction concatenates it directly to preceding text, while retaining the existing genuine duplicate-conflict guard.
- Confirmed the 33-page `CKS021617RJAA.pdf` now extracts as flight `CKS216`, route `RJAA` to `RKSI`, with one MEL item instead of a false conflict.
- Preserved the original extraction exception as the reported wrapper's previous exception while keeping the generic browser-visible error.
- Applied Livewire's explicit `.flex` loading-display modifier and block prompt text so the title and upload instruction remain vertically separated after an error response.
- Added focused parser and Livewire regression coverage for the compact maintenance boundary, exception chain, and post-error prompt markup.

Commit message: `fix: handle duplicated compact maintenance records`

## Completed: Bug: flight plan: EENT / EEXP not extracted

Outcome:

- Identified the production-only cause as missing Imagick support on an image-only ETOPS page and prevented incomplete PDF text from being cached when OCR is unavailable or fails.
- Added regression coverage for the exact private release and confirmed extraction of EENT `N45 54.3 E154 20.0` and EEXP `N57 51.2 W175 26.2`.
- Removed the duplicate 146-page text traversal by assembling native PDF text and identifying OCR pages in one pass.
- Batched uncached airport lookups through Laravel's concurrent HTTP pool while preserving found, missing, and temporarily unavailable cache states per airport.
- Tested 150 DPI / Tesseract PSM 11 across all ten image-only pages found in the available private releases. It retained as little as 16% of PSM 6's recognized token set on one fixture, so the safer 200 DPI / PSM 6 configuration remains in place.

Commit message: `perf: streamline flight release extraction`

Regression evidence:

Expected EENT coordinates: `N45 54.3 E154 20.0`
Expected EEXP coordinates: `N57 51.2 W175 26.2`

Raw text:
```
ONEMU 0249 051 310 25/057 P050 500 833 028 eee ee. 0715 1184 ....
2509 048 LGT -36 550 02.56 1... 2.6. we ee eee 0910
- FL - 330
N45 31.9 E153 43.2
OPULO 0331 054 330 27/071 P049 491 831 036 wee eee 0842 1057 ....
2178 048 -44 539 03.32 1... 2.6. wees wee 0783
N45 54.3 E154 20.0
(EENT) 0034 056 330 29/025 P013 487 833 004 wee ee. 0855 1044 ....
—----- 2144 054 -48 500 03.36 1... 2.6. wees eee. 0769
N48 59.7 E160 00.7
OMOTO 0295 058 330 29/025 P013 487 833 036 wee eee O971 0928 ....
1849 055 -48 500 04.12 1... 1... we ee wee 0653
N49 00.1 E160 01.5
-PAZA ---- --- ==> --/-- = HR HF wee eee TO 0927 LL...
Sass ene 04.12 1... 1... we ee wee 0653
FIR FIR-> PAZA <-—
N49 30.6 E161 07.8
OGDEN 0054 061 330 34/017 M004 485 834 007 wee ee. 0993 0906 ....
1795 059 -50 480 04.19 1... 26. we ee wee 0632
N50 53.9 E164 26.4
OPHET 0152 062 330 32/029 P001 484 834 018 wee ee. 1052 0846 ....
1643 058 -51 484 04.37 2... cee ee ee eee 0572
N51 21.5 E165 37.5
OLCOT 0053 063 330 32/041 PO05 483 834 007 «ee ee. 1073 0826 ....
1590 058 -52 488 04.44 1... 16. we ee wee 0552
N52 53.7 E170 01.2 -ETP1
N52 56.3 E170 09.3
OPAKE 0192 064 330 31/063 P020 482 833 023 eee ee. 1144 0755 2...
1398 057 -53 501 05.07 1... 2.6. wees eee 0480
N54 15.4 E172 49.3
ONEIL 0123 052 330 30/095 P022 481 833 015 eee ee. 1189 0710 ....
1275 041 -54 502 05.22 1... 1.6. we ee eee 0435
N56 05.2 E178 04.3
OBOYD 0211 059 330 30/076 P035 479 833 024 wee ee. 1264 0635 ....
1064 051 -55 513 05.46 1... 26. we ee eee 0360
N57 36.6 W176 26.8
OFORD 0202 062 330 31/050 P013 480 834 025 «ee ee. 1338 0561 ....
0862 056 -55 491 06.11 1... 11. we ee eee. 0286
N57 51.2 W175 26.2
(EEXP) 0035 063 330 33/035 P004 480 834 004 eee ee. 1351 0548 2...
------ 0827 059 -55 483 06.15 1... 26. we ee eee 0273
N58 16.4 W173 34.4
```
## Completed: Bug: Edge case - Incorrect DH extraction

Outcome:

- Confirmed from the source image that `BHJCHN` is labeled `CNF #` and is booking confirmation data.
- Excluded lines labeled `CNF` or `Confirmation` from fallback tail-number extraction without changing supported aircraft-registration formats or display precedence.
- Kept `CX 413` resolved to `Cathay Pacific` and added focused parser regression coverage.

Commit message: `fix: correct commercial deadhead tail extraction`

## Completed: Flight init refinement

Outcome:

- Added the confirmed alternate airport and flight duration to the Flight init metrics.
- Reordered the metrics to show ACARS init date, departure airport, arrival airport, alternate airport, flight duration, ETD, and estimated ramp fuel before the crew list.
- Removed tail number and flight number from the metric grid because they remain visible in the release summary.
- Added focused presenter and Livewire coverage for the values and their rendered order.

Commit message: `refactor: refine flight init presentation`

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

## Completed: Allow admins to delete aircraft

Outcome:

- Authorized active administrators to delete individual Aircraft records and perform bulk Aircraft deletion.
- Preserved the existing denial for non-admin users and for restore or force-delete operations.
- Added focused Filament resource coverage for edit-page deletion, bulk deletion, and non-admin denial.

Commit message: `feat: allow admins to delete aircraft`

## Completed: Add aircraft weight fields to Filament resource

Outcome:

- Added maximum zero fuel, maximum takeoff, maximum landing, and minimum flight weight inputs to the Aircraft create and edit forms.
- Added sortable, pound-formatted columns for the four weight limits to the Aircraft table.
- Added focused resource coverage for displaying, creating, editing, and validating the weight fields.

Commit message: `feat: add weight fields to Filament Aircraft form and table, including validation for negative values`


## Flight plan: Parse bottlenecks:

### Completed: Reduce PDF extraction and airport lookup latency

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
3. [x] Completed: Add timing spans around `parseFile()`, page text extraction, and each OCR operation, including page context and whether OCR was required, without recording document contents.
4. [x] Completed: Add focused tests proving airport cache hits avoid provider calls, duplicate codes are resolved once, cache misses retain current results, and provider failures remain non-fatal where currently supported.
5. [x] Completed: Re-profile one cold-cache and one warm-cache parse, then record the timing comparison here before marking the task complete.

References:

- [FlightPlanTextExtractor.php](/home/dm1988/k4-parser/app/Services/FlightPlan/Extractor/FlightPlanTextExtractor.php)
- [FlightRouteExtractor.php](/home/dm1988/k4-parser/app/Services/FlightPlan/Extractor/FlightRouteExtractor.php)
- Existing `AirportCodeCache` implementation and its focused tests.

Proposed commit message: `perf: reduce flight plan parsing bottlenecks`

Outcome:

- Flight-plan airport lookups now reuse cached found, missing, and unavailable resolutions. Provider failures remain non-fatal and return `null` airport metadata.
- Focused coverage verifies cached resolutions avoid repeated provider calls and unavailable providers retain the existing nullable response contract.
- Route stations are deduplicated before cache or provider access, while each departure, destination, and alternate field retains its expected airport metadata.
- Debugbar now groups PDF parsing, per-page text extraction, and OCR timings under `Flight plan extraction`, recording only operation and page metadata plus whether OCR was required.
- Focused cache, duplicate-station, provider-failure, and timing coverage passes: 30 tests with 117 assertions.
- On September 2, 2026, the full normalized extraction service parsed `CKS025625KLAX.pdf` in 1,942.29 ms after clearing only its PDF-text key and the `KLAX`, `RKSI`, and `RKTU` airport keys. The immediate warm-cache parse took 20.14 ms, a 99.0% reduction, with equivalent route output.

Task 1 commit message: `perf: cache flight plan airport lookups`

Task 2 commit message: `perf: deduplicate flight plan airport lookups`

Task 3 commit message: `perf: instrument flight plan text extraction`

Completion commit message: `perf: complete flight plan extraction optimization`
