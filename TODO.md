# Branch - Flight Plan Extractor Refactor

## Setup

### Completed: app/DTOs/ScheduleData.php
Operational times: ETD, ETA, block time, report time, slot times—each with an explicit UTC/local basis.
Remove overlap from the Flight DTO

Outcome: Added an immutable `ScheduleData` DTO with explicitly named UTC/local ETD, ETA, report, duty-end, and slot fields. Existing block-time durations are represented unambiguously as `blockDuration`. `Flight` now owns one nested schedule instead of duplicating block and local operational-time fields, while `FlightMapper` preserves the existing calendar metadata contract for parsers, views, cached payloads, and exports.

Focused verification: All 29 focused DTO, view-model, component, and calendar-export tests passed with 180 assertions. Pint passed, and final focused Larastan analysis completed with no errors.

Commit message: `refactor: extract flight schedule data`

### Completed: app/ValueObjects/AirportCode.php
Validates and represents an airport identifier. It prevents passing arbitrary strings around as “airport codes.”

Outcome: Added an immutable `AirportCode` value object that trims and uppercases identifiers, accepts only three-letter IATA or four-letter ICAO formats, distinguishes the two formats, compares normalized values, and supports string and scalar JSON serialization. Registry/provider existence remains the responsibility of airport resolution rather than format validation.

Focused verification: All 10 focused AirportCode tests passed with 22 assertions. Pint passed, and final focused Larastan analysis completed with no errors.

Commit message: `feat: add airport code value object`

### Completed: app/ValueObjects/FlightTime.php
Represents a flight-related time with its timezone/context. This prevents UTC and local times from silently getting mixed.

Outcome: Added an immutable `FlightTime` value object with strict ISO-8601 UTC and local construction, explicit timezone and `utc`/`local` basis context, immutable timezone conversion, context-aware equality, same-instant comparison, and object-shaped array/JSON serialization. Local wall-clock validation rejects invalid zones, fixed-offset pseudo-local contexts, and nonexistent or ambiguous daylight-saving times.

Focused verification: All 15 focused FlightTime tests passed with 36 assertions. Pint passed, and final focused Larastan analysis completed with no errors.

Commit message: `feat: add flight time value object`

## Plan: Rename feature
- Rename to `Flight Plan Brief`
- Conveys “the important operational details, cleanly distilled” without promising it creates or files a flight plan.
- Hook `Your flight release, distilled into the details that matter.`

## Views context
| Priority | View             | Implementation focus                                                                              |
| -------: | ---------------- | ------------------------------------------------------------------------------------------------- |
|        1 | Overview         | Navigation shell, shared release context, high-level operational summary.                         |
|        2 | Jepp PD-Pro      | Extract and present Jeppesen performance/planning data.                                           |
|        3 | Maintenance Log  | Parse maintenance/MEL/CDL-style entries and related operational notes.                            |
|        4 | Envelope         | Present performance/envelope data built from the preceding release details.                       |
|        5 | Flight Init      | Build the quick-reference initialization section: identifiers, airports, times, alternates, crew. |
|        6 | FMS              | Normalize route, waypoint, altitude, and FMS-entry data.                                          |
|        7 | Slot Times       | Extract slot/CTOT-style constraints and display them in the flight timeline.                      |
|        8 | Fuel Score       | Build fuel calculations, comparisons, and operational fuel indicators.                            |
|        9 | ETOPS            | Add ETOPS-specific planning, alternates, and compliance information when applicable.              |
|       10 | Weather          | Parse and organize departure, destination, alternate, and enroute weather.                        |
|       11 | Weight & Balance | Extract final weight, balance, payload, and limit information.                                    |

## Shared release context
Here’s a planning-ready data inventory in your existing build order. I’ve separated shared fields from view-specific data so the same release facts don’t get independently re-parsed eleven times.

Available to every view:

* Release ID / revision number
* Release status and authority (for example, “Released IAW OPS SPEC B044”)
* Flight date
* Flight number
* Trip number
* Aircraft type
* Tail number
* Departure, destination, and alternate airport(s)
* Scheduled / estimated / actual times, with time basis clearly labeled
* Crew list
* Source-document metadata: parser version, imported-at time, raw-release link

### 1. Overview

* Release status and revision
* Flight number, tail number, aircraft type, date
* Departure, destination, primary alternate
* ETD and ETA
* Planned takeoff altitude
* Estimated ramp fuel
* Operational-status indicators:

  * GENDEC status
  * Flight plan filing status
  * Slot-time status
  * ETOPS applicability/status
  * Weather / RAIM availability
* MEL/CDL summary:

  * Count
  * MEL or CDL type
  * Item number
  * DMI number
  * Description
  * Operational notes / limitations
* Links or summary status for each detailed view

### 2. Jepp PD-Pro

This one should mirror the actual PD-Pro data rather than guess at fields. Likely candidates:

* Flight / trip number
* Tail number and aircraft type
* Departure, destination, alternate(s)
* Performance planning assumptions
* Runway / runway condition
* Takeoff and landing performance results
* Takeoff weight limits
* Thrust / assumed-temperature details
* V-speeds
* Climb, cruise, or landing performance constraints
* Required remarks, warnings, and dispatch notes
* PD-Pro document revision / generated time

### 3. Maintenance Log

* Date
* Aircraft type
* Tail number
* Trip number
* Flight number
* Departure and destination
* Estimated ramp fuel
* ETOPS flight indicator
* Crew list
* MEL / CDL / DMI items:

  * Type
  * Item number
  * DMI number
  * Description
  * Maintenance status
  * Operational limitations
  * Required procedures or notes
* Maintenance control / log reference, when available

### 4. Envelope

* Trip number
* Tail number
* Aircraft type
* Flight number
* Departure and destination
* Crew list
* Applicable performance envelope or configuration data
* Weight / CG constraints
* Takeoff and landing limitations
* Temperature, runway, obstacle, or contaminated-runway constraints
* Warnings, exceedances, and dispatch remarks
* Reference to the source performance document

### 5. Flight Init

Designed as the fast ACARS/FMS initialization reference:

* Tail number
* Aircraft type
* Flight number
* ACARS initialization date
* Departure date
* ETD
* Estimated ramp fuel
* Departure and destination
* Primary alternate(s)
* Crew list
* Dispatch / release number
* Flight-plan revision
* Operational remarks needed before departure

### 6. FMS

* Flight number
* Aircraft type
* Recall number
* Departure, destination, and alternate
* Departure runway
* Arrival runway
* SID
* STAR
* Route string
* Total route distance
* Initial cruise altitude
* Step climbs / planned altitude profile
* Cost index
* Alternate reserves
* Route constraints
* FMS remarks or special entries

### 7. Slot Times

* Slot airport
* Slot type: departure, arrival, overflight, etc.
* Approved time
* Time tolerance/window, such as ±30 minutes
* Time basis: UTC/local
* Permit country / authority
* Landing or overflight permit number
* Permit status
* Validity period
* Slot revision / last update
* Dispatch notes or coordination instructions

### 8. Fuel Score

This should be a structured waypoint fuel-monitoring table plus a concise summary.

* Waypoint name
* ETA at waypoint
* Planned fuel remaining
* Planned flight level
* Flight phase
* Forecast wind
* Temperature
* Planned Mach / speed
* Time or leg duration
* Fuel-burn delta versus plan, if actual data becomes available
* Fuel-status indicators:

  * On plan
  * Caution
  * Below target
* Summary values:

  * Ramp fuel
  * Taxi fuel
  * Takeoff fuel
  * Trip fuel
  * Contingency fuel
  * Alternate fuel
  * Final reserve
  * Estimated landing fuel
  * Minimum landing fuel

### 9. ETOPS

* ETOPS applicability and approval status
* ETOPS entry and exit time
* ETP / critical points:

  * Sequence number
  * Coordinates
  * Point type, such as EENT or EEXP
  * Time
  * Heading
  * Altitude
  * Wind
* ETOPS alternates:

  * Airport
  * Weather suitability
  * Runway / facility status
  * Diversion time
  * Fuel required
* Critical fuel scenario(s)
* ETOPS remarks, restrictions, and required checks

### 10. Weather

Organize this by airport and route rather than as one undifferentiated text block.

* RAIM prediction / availability
* METAR:

  * Airport
  * Observation time
  * Raw report
  * Parsed wind, visibility, ceiling, temperature/dew point, altimeter
* TAF:

  * Airport
  * Valid period
  * Raw forecast
  * Parsed prevailing and temporary conditions
* Departure, destination, and alternate weather
* Enroute / significant weather, if present
* NOTAM-derived weather or runway impacts
* Weather warnings:

  * Below approach minimums
  * Crosswind concerns
  * Thunderstorm / convective risk
  * Icing
  * Low visibility

### 11. Weight & Balance

* Tail number and aircraft type
* Basic operating weight
* Index / CG data
* Payload
* Taxi fuel
* Zero fuel weight and index
* Ramp fuel and index
* Takeoff fuel
* Takeoff gross weight and index
* Estimated fuel burn
* Estimated landing fuel
* Estimated landing weight and index
* Maximum permitted values:

  * Maximum zero fuel weight
  * Maximum takeoff weight
  * Maximum landing weight
  * CG / index envelope limits
* Status for each limiting value: within limits, caution, exceeded
* Cargo / compartment distribution, if the release provides it

The big implementation boundary: **Weight & Balance, Fuel Score, ETOPS, and Weather should store both raw source text and normalized fields.** Those are the sections where users will most want to verify that the tidy presentation still matches the actual release.

## Create Flight Plan DTO
- Use immutable readonly DTOs and typed value objects for airports, times, fuel, and weights.
- Parse once, normalize once, render many times.
### DTO Ownership
| DTO                  | Owns                                                |
| -------------------- | --------------------------------------------------- |
| `FlightIdentityData` | Flight/trip, tail, aircraft, date, release revision |
| `ScheduleData`       | All operational times and their time basis          |
| `RouteData`          | Airports, route, runways, SID/STAR, distance        |
| `FuelPlanData`       | Release-level fuel figures                          |
| View DTOs            | Only fields unique to that view                     |


## Initial files
app/DTOs/FlightPlanData.php
app/DTOs/FlightIdentityData.php
app/DTOs/ScheduleData.php
app/DTOs/RouteData.php
app/DTOs/FuelPlanData.php
app/Actions/BuildFlightPlanData.php
app/ValueObjects/AirportCode.php
app/ValueObjects/FlightTime.php
app/ValueObjects/FuelQuantity.php
app/Enums/TimeBasis.php

### app/Actions/BuildFlightPlanData.php
- The orchestrator. It receives a parsed flight release/model, pulls from its existing parser output, creates the nested DTOs, and returns one FlightPlanData. 
- No rendering logic.
- BuildFlightPlanData should map existing parsed output only. 
- If a field does not exist yet, create a focused parser enhancement for that field—don’t parse raw release text in a component.
### app/DTOs/FlightPlanData.php
The top-level immutable object for one normalized release. Contains shared data plus optional section DTOs: overview, FMS, ETOPS, weather, etc.
### app/DTOs/FlightIdentityData.php
Basic identity: flight number, trip number, aircraft type, tail number, flight date, release revision.
### app/DTOs/ScheduleData.php
Operational times: ETD, ETA, block time, report time, slot times—each with an explicit UTC/local basis.
### app/DTOs/FuelPlanData.php
Fuel figures and units: ramp, taxi, takeoff, trip, contingency, alternate, final reserve, estimated landing fuel.
### app/Enums/TimeBasis.php
??
### app/ValueObjects/FuelQuantity.php
Stores an amount plus unit, converts safely when necessary, and formats consistently. Avoid raw 216.8k strings in DTOs.
### app/View/FlightPlan/OverviewViewData.php
Optional adapter specifically for the rendered Overview. Use it only if the Livewire/Blade layout needs display-friendly values, badges, or labels that should not live in the core DTO.

## Create tests
------------

# Review welcome page for use with new features

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

# Identify places to incorporate Crew Compass

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

# Track schedule upload count
- For multiple file uploads within each user request

# Confidence score for extraction

# flight plan extractor: Progress after submit
- Parse on file select ... processing
- with progress spinner or text

# flight plan extractor: Extract new route: don't show upload again
- Use an extractor another button, 2 page
- Hides the upload on the results page
- Extract new route: don't show upload again

# Feat: Look into offline use

# Feat: Fuel score

# Feat: Crew Rest

# Chore: update laravel

# Plan: Release flight extractor trial
Goal: release the flight extractor as a clearly labeled demo, validate usage, and then gate it behind its own Stripe subscription at $5 per year with a one-time two-month trial per user.

## Phase 1: Demo release and measurement

Implementation outcome:

- Added a reusable, dark-mode-compatible `Demo` badge with default, info, and success variants, custom slot content, and safe fallback styling to both flight-plan navigation links without changing their existing feature gate.
- Demo access now explicitly requires a verified email. Verified non-admin users receive access when the environment-specific demo override is enabled; admins retain access; unverified users remain blocked.
- The disabled master feature continues to return 404 and hide navigation access for every user.
- Added a flight-plan-specific Filament dashboard widget for request volume, failure count and rate, average processing time, and distinct-user adoption using existing `extract_requests` data.
- Added focused navigation, authorization, extraction logging, model entitlement, dashboard layout, and metric tests covering enabled, disabled, admin, verified-user, and unverified-user behavior.
- Kept `.env.example` defaulted to `FEATURES_FLIGHT_RELEASE_FOR_ALL_USERS=false`. Deployment action remaining: set this value to `true` in the target environment and refresh cached configuration when the demo is released.

Focused verification: 34 tests passed with 226 assertions. Pint and Larastan completed successfully.

Commit message: `feat: release and measure flight plan demo access`

## Phase 2: Stripe and Cashier foundation

1. Create a Stripe product with a recurring annual price of $6; store only its Stripe price ID in billing configuration and environment variables.
- Sandbox Product ID: prod_V5e95Fh4fVJrkH
2. Add Cashier's `Billable` trait to `User`, cast `trial_ends_at` as a datetime, and verify the existing Cashier customer/subscription migrations match Cashier v16 requirements.
3. Use a dedicated subscription name such as `flight-release` instead of `default`, keeping this entitlement independent from future paid feature tiers.
4. Configure test/live Stripe keys, webhook signing secrets, currency, and Cashier path without committing secrets.

## Phase 3: Checkout and one-time trial

1. Add an authenticated billing page showing the annual price, trial terms, current subscription state, renewal date, and cancellation or grace-period state.
2. Start the `flight-release` subscription through Stripe Checkout with `trialUntil(now()->addMonths(2))`, collecting a payment method so billing can begin automatically after the trial.
3. Prevent repeat trials by checking retained subscription history before offering trial terms; returning subscribers start paid access immediately.
4. Add named routes and thin controllers for checkout, success, cancellation, and Stripe's billing portal. Handle incomplete/SCA payments through Cashier's payment-confirmation flow.
5. Make checkout creation idempotent so repeated submissions cannot create duplicate subscriptions.

## Phase 4: Entitlement and lifecycle handling

1. Centralize flight-extractor entitlement in `User::canUseFlightRelease()`: the master feature must be enabled, admins retain access, the demo override grants temporary access, and regular users otherwise need an active, trialing, or grace-period `flight-release` subscription.
2. Keep the existing gate and route middleware as the single authorization boundary, and use the same method to decide whether navigation is rendered.
3. Configure Cashier's signed webhook endpoint and CSRF exclusion, then verify subscription creation, updates, cancellation, payment failure, and deletion synchronize locally.
4. Show actionable billing states for trialing, active, incomplete, past-due, canceled-on-grace-period, and ended subscriptions; never grant access to incomplete or past-due subscriptions.
5. Turn `FEATURES_FLIGHT_RELEASE_FOR_ALL_USERS` back to `false` only after checkout, webhooks, and entitlement behavior are verified in production test mode.

## Phase 5: Verification and launch

1. Add PHPUnit coverage for configuration, gates, middleware, checkout authorization, one-time trial eligibility, subscription states, admin bypass, demo override, and canceled/grace-period access.
2. Test webhook signature rejection and representative Stripe lifecycle payloads without making normal unit and feature tests depend on live Stripe network access.
3. Complete Stripe test-mode smoke tests for successful checkout, 3DS/SCA, declined payment, trial conversion, cancellation, portal return, and webhook replay.
4. Run focused tests and Pint during implementation, then run the full PHPUnit suite, Larastan, and a production Vite build at the final integration checkpoint.
5. Document the production rollout checklist in this section: Stripe product/price IDs, webhook URL and events, secrets, demo-flag cutoff, monitoring window, and rollback by restoring the demo override.

Open decisions before implementation:

- “two months” means two calendar months
- Existing demo users receive the full trial when billing launches
- Confirm tax handling and the customer-facing refund/cancellation policy before enabling live charges.

------------------------------------------------

# Completed

## Completed: Buy me a coffee modal

# Completed: Expose registration on the login page

## Completed: Small-screen local times overflow

# Complete: Context engineering

# Completed: Flight Plan Extractor copy messages after repeated copies

Outcome: Copy-status messages now remain fully visible for two seconds before fading over 300 milliseconds. Reusing Departure, Destination, Alternate, Route, or detail copy controls cancels their prior timers and visibly restores the status instead of immediately fading it again. Added a JavaScript regression that exercises Departure, Destination, Alternate, and a fourth copy using a previously used control, plus a focused Blade rendering assertion for the corrected transition state.

Focused verification: 1 focused PHPUnit test passed with 32 assertions, all 7 JavaScript tests passed, Pint passed, Larastan passed with no errors, and the production Vite/Tailwind build completed successfully.

Commit message: `fix: keep repeated copy messages visible`

Static-analysis follow-up: Added a typed `expectOnce()` boundary for the test file's Mockery expectations, replaced the dynamically inferred log spy assertion with explicit `error` and `warning` facade expectations, and added a PHPStan stub for Mockery's runtime `CompositeExpectation` fluent methods. This preserves strict staged-test analysis without ignores, baselines, excluded tests, or a new dependency.

Follow-up verification: All 13 Flight Release controller tests passed with 147 assertions, Pint passed, the staged-file pre-commit hook passed, and direct Larastan analysis—including the registered Mockery stub—completed with no errors.

Follow-up commit message: `test: make flight release mocks larastan-safe`
