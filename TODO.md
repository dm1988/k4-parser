# Branch - Flight Plan Brief Refactor

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

## Task navigator
- Left of main content
- Controls views
- Header: Tasks (caps)
  1:  "Overview",           icon: LayoutDashboard or Home
  2:  "Jepp PD-Pro",        icon: PlaneTakeoff   
  3:  "Maintenance Log",    icon: ClipboardList  
  4:  "Envelope",           icon: FileText       
  5:  "Flight Init",        icon: Zap            
  6:  "FMS",                icon: Calculator or CPU            
  7:  "Slot Times",         icon: Clock          
  8:  "Fuel Score",         icon: Gauge          
  9:  "ETOPS",              icon: Globe          
  10: "Weather",            icon: Cloud          
  11: "Weight & Balance", icon: Scale          

  Sample:
  function Nav({ active, onSelect }: { active: number; onSelect: (id: number) => void }) {
  return (
    <nav className="flex w-48 shrink-0 flex-col overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm">
      <div className="border-b border-[#1B365D]/8 bg-[#F8F9FA] px-3 py-2">
        <p className="text-[9px] font-bold uppercase tracking-widest text-[#4A5568]">Tasks</p>
      </div>
      <ul className="flex flex-col divide-y divide-[#1B365D]/6">
        {TASKS.map(({ id, label, icon: Icon }) => {
          const on = id === active;
          return (
            <li key={id}>
              <button
                type="button"
                onClick={() => onSelect(id)}
                className={`flex w-full items-center gap-2 px-3 py-2 text-left transition-colors
                  ${on ? "bg-[#1B365D] text-white" : "hover:bg-[#F8F9FA]"}`}
              >
                <Icon className={`h-3.5 w-3.5 shrink-0 ${on ? "text-white/70" : "text-[#4A5568]"}`} />
                <span className={`text-xs font-medium leading-tight ${on ? "text-white" : "text-[#0B0E14]"}`}>{label}</span>
              </button>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}

## Shared release context
Here’s a planning-ready data inventory in your existing build order. I’ve separated shared fields from view-specific data so the same release facts don’t get independently re-parsed eleven times.

Available to every view:

* Release ID / revision number
* Flight date
* Flight number
* Departure, destination, and alternate airport(s)
* Scheduled / estimated / actual times, with time basis clearly labeled
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

The big implementation boundary: **Weight & Balance, Fuel Score, ETOPS, and Weather should store both raw source text and normalized fields.** Those are the sections where users will most want to verify that the tidy presentation still matches the actual release.

## Completed: Create Flight Plan DTO

Outcome: Added the immutable `FlightPlanData` aggregate with focused identity, route, schedule, and release-level fuel DTOs. Route data uses validated `AirportCode` values, fuel figures use a normalized `FuelQuantity` value object with safe pound/kilogram conversion, flight dates use `CarbonImmutable`, and `FlightTime` now exposes the typed `TimeBasis` enum while preserving its serialized contract. Missing parser fields remain nullable rather than being inferred or parsed in presentation code, and the existing schedule-extractor DTO contract remains backward compatible.

The `BuildFlightPlanData` mapping action remains the next service-layer task so it can map existing parser output without adding rendering logic or speculative parsing.

Focused verification: All 56 focused DTO, value-object, and enum tests passed with 194 assertions. Pint passed, and the final Larastan analysis completed with no errors.

Larastan maintenance follow-up: Kept invalid `TimeBasis::tryFrom()` behavior covered through a typed data provider and replaced redundant enum case type/membership assertions with one exact case-list assertion.

Follow-up verification: All 5 focused `TimeBasis` tests passed with 7 assertions. Pint passed, and focused Larastan analysis completed with no errors.

Follow-up commit message: `test: fix time basis larastan assertions`

Commit message: `feat: add normalized flight plan data objects`

## Completed: Extract normalized data for `BuildFlightPlanData`

Outcome: Added a parse-once flight-release pipeline with a content-hash-cached text reader, focused identity, schedule, route-distance, and fuel extractors, an immutable parsed-data contract, and a pure `BuildFlightPlanData` mapper. `HandleFlightPlanExtraction` now builds and caches the nested normalized aggregate while a compatibility serializer preserves the existing airport, route, ETPS, coordinate, altitude, and duration payload used by the current views. Source evidence stays internal and is not exposed in cached UI data. Unconfirmed release revision, block/report/duty times, local times, and contingency fuel remain `null` rather than being inferred.

Focused verification: All 62 focused extraction, DTO, serializer, route-regression, cache, controller, and Livewire tests passed with 341 assertions. Pint passed, and final Larastan analysis completed with no errors. The private sample release PDF also passed characterization coverage.

Commit message: `feat: extract normalized flight plan data`

Goal: Parse the additional identity, schedule, route, and fuel facts once from one flight-release text payload, retain source evidence where later verification matters, and pass a stable parsed contract to `app/Actions/BuildFlightPlanData.php`. The builder will normalize values and construct `FlightPlanData`; it will not read PDFs, run regexes, call external services, or format UI labels.

### Existing service review

| Component | Keep | Change before builder integration |
| --- | --- | --- |
| `HandleFlightPlanExtraction` | Private upload staging, request metrics, failure reporting, and guaranteed cleanup | Replace the route-only call with `text reader -> extraction coordinator -> BuildFlightPlanData`; keep lifecycle concerns here |
| `FlightRouteExtractor` | Tested ICAO FPL, runway, SID/STAR, ETPS, EENT/EEXP, route normalization, and airport lookup behavior | Move its private PDF parsing/cache behind one reusable flight-release text reader; let route parsing consume text and add labeled total distance |
| `Schedule\Extractor\PdfTextExtractor` | Existing Smalot PDF parsing precedent | Do not inject this schedule-specific service directly into flight-plan parsing; it constructs its own parser and does not share the route extractor's content-hash cache |
| `AirportLookupClient` | Existing resilient airport enrichment with timeouts/retries | Keep enrichment outside `BuildFlightPlanData`; `RouteData` owns validated airport codes, while rich `AirportData` remains compatibility/view data |
| `BuildScheduleResult` | Precedent for a small mapping action | Follow its single-purpose shape, but return `FlightPlanData` instead of a generic result wrapper |
| `FlightPlanResultCache` | Opaque, owner-scoped result storage | Cache a serializable result adapter; do not cache raw PDF text or typed PHP objects because cache object unserialization is disabled |

The current view still rebuilds the legacy `App\ValueObjects\FlightPlan` from a flat array. During migration, preserve its ETPS, coordinates, initial altitude, airport details, and flat route keys through a compatibility serializer. Do not force those fields into unrelated core DTO properties or remove them when the new builder is introduced.

### Source-to-builder contract

Create a small parsed input contract, preferably `app/DTOs/ParsedFlightPlanData.php`, containing nested scalar shapes for `identity`, `schedule`, `route`, and `fuel`, plus narrowly scoped raw source fragments. Focused extractors populate this contract; `BuildFlightPlanData` is the only layer that creates `CarbonImmutable`, `AirportCode`, `FlightTime`, and `FuelQuantity` values.

| Target | Confirmed source in the current sample | Extraction and normalization rule |
| --- | --- | --- |
| `identity.flightNumber` | `CKS256` and `(FPL-CKS256-...)` | Prefer the labeled header value and corroborate it with the FPL identifier; reject conflicting non-empty values |
| `identity.tripNumber` | `TRIP 109546` | Capture only the labeled trip value; do not treat `RECALL 62930` as a trip or revision |
| `identity.recallNumber` | `RECALL 62930` | Capture only the labeled recall value |
| `identity.aircraftType` | `B777-200F`; FPL equipment type `B77L` | Prefer the release header's operational aircraft type; retain the FPL equipment type only as parser evidence/fallback after fixtures confirm the policy |
| `identity.tailNumber` | `N774CK`; `REG/N774CK` | Prefer the labeled header registration and corroborate it with FPL `REG/`; reject conflicts |
| `identity.flightDate` | `05/25/26`; `DOF/260525` | Parse the header date and corroborate it with FPL DOF before creating `CarbonImmutable`; never use the upload date |
| `identity.releaseRevision` | No reliable labeled value confirmed | Keep `null` until another fixture or format specification identifies revision semantics; do not use recall number, release time, or `FLIGHT RELEASE I.F.R 3739` speculatively |
| `schedule.etdUtc` | `SH ETD 02.20Z/25`; FPL departure time `0220` | Parse a complete UTC instant using the confirmed flight date/day marker; corroborate the FPL time |
| `schedule.etaUtc` | `ETA 14.50Z` | Parse the labeled ETA independently; do not derive it from FPL elapsed time |
| `schedule.slotTimesUtc` | `APPROVED SLOT TIMES: ARR RKSI @ 1520Z` | Capture every labeled slot with airport/direction evidence, then serialize the canonical UTC instant expected by the current DTO |
| Remaining schedule fields | No reliable local/report/duty-end/block source confirmed | Leave local, report, duty-end, and block values `null`; the FPL destination value is estimated elapsed time, not automatically block time |
| `route` core fields | Existing ICAO FPL and planned-runway extraction | Reuse current tested text parsing for airport codes, route, runways, SID, and STAR |
| `route.distanceNauticalMiles` | `TOTAL DIST/DEST 5549`; fuel summary destination distance `5549` | Parse the explicitly labeled total-to-destination distance and corroborate duplicate summary values |
| `fuel.ramp` | `TTL RMP 216.8` | Interpret table scaling only after detecting the release's fuel unit/scale; preserve the matched raw fragment |
| `fuel.taxi` | `TAXI 002.0` | Apply the same detected unit/scale as the fuel summary |
| `fuel.takeoff` | `TAKEOFF FUEL 214829` | Prefer the exact labeled weight over a rounded derived value |
| `fuel.trip` | `DEST ... 195.1`; `EST FUEL BURN 195116` | Prefer the exact labeled estimated burn and use the rounded destination burn only as corroboration |
| `fuel.alternate` | `ALTN RKTU 005.6` | Map only the primary planned alternate row, using the detected unit/scale |
| `fuel.finalReserve` | `RESERVE 006.9` | Map the labeled release reserve; preserve the raw row for future Fuel Score verification |
| `fuel.estimatedLanding` | `EST LANDING FUEL: 019713` | Use the exact labeled value |
| `fuel.contingency` | No confirmed one-to-one label | Keep `null`; do not reinterpret holding, additional, ballast, or reclear pad fuel as contingency without a documented rule |

Fuel extraction must detect the document's mass unit and table scale from source context. The current sample contains pound-based evidence such as `INC BURN/1000 LBS`, but the parser must not assume every release is pounds. If unit or scale is ambiguous, retain the raw fragment and leave the normalized `FuelQuantity` null.

### Implementation sequence

1. Add sanitized multiline and flattened text fixtures for the confirmed header, FPL, schedule, distance, and fuel-summary formats. Keep the private sample-PDF test as characterization coverage, not the only CI source of truth.
2. Extract the PDF reading, null-byte cleanup, content-hash cache, and parser exception translation from `FlightRouteExtractor` into one injected `FlightPlanTextExtractor`. Every downstream extractor must receive the same text string so the document is parsed once.
3. Add focused `FlightIdentityExtractor`, `FlightScheduleExtractor`, and `FlightFuelExtractor` services under `app/Services/FlightPlan/Extractor`. Keep `FlightRouteExtractor` route-focused and extend its text result only for confirmed distance. Each service returns parsed scalars plus source fragments, uses `null` for absent optional fields, and never formats display text.
4. Add an `ExtractFlightPlanData` coordinator that accepts the single text payload, invokes the focused extractors, and returns `ParsedFlightPlanData`. It must not perform upload storage, caching, authorization, logging, or DTO presentation.
5. Implement `BuildFlightPlanData` as a pure mapping action from `ParsedFlightPlanData` to `FlightPlanData`. It validates required departure/destination codes, creates typed date/time/fuel values, preserves null optional fields, and contains no regex or source-format knowledge.
6. Add a compatibility serializer for the current flat Livewire/view payload. Merge serialized core DTO data with existing airport enrichment and legacy ETPS/EENT/EEXP/initial-altitude fields until the frontend is migrated deliberately; move ICAO line wrapping to the view boundary when that migration occurs.
7. Wire `HandleFlightPlanExtraction` to the new pipeline without changing its authorization, metrics, structured failures, user-scoped caching, or `finally` cleanup behavior. Update the result allowlist for the compatibility payload only after all existing rendering tests remain green.

### Focused verification plan

- Text reader: parses once, reuses and invalidates the content-hash cache correctly, removes null bytes, and translates unreadable-PDF failures.
- Identity extractor: multiline/flattened input, header/FPL corroboration, missing optional values, conflicting flight/date/tail values, and no speculative release revision.
- Schedule extractor: UTC date composition, midnight rollover only when unambiguous, multiple slots, malformed times, and absent local/report/duty/block values.
- Route extractor: retain every current test and add labeled distance, missing distance, duplicate agreement, and conflicting-distance cases.
- Fuel extractor: all eight target buckets, exact-over-rounded precedence, comma/decimal/leading-zero formats, pounds/kilograms, scale detection, ambiguous unit/scale, missing rows, and protection against similarly named holding/additional/reclear values.
- Builder: full and partial parsed inputs, value-object construction, deterministic serialization, invalid required airport codes, invalid fuel values, and proof that raw document text is not an input.
- Lifecycle integration: one PDF parse, cached compatibility payload, unchanged airport/ETPS rendering, metrics, recoverable failures, unexpected-error reporting, and upload deletion on success and every failure path.

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
Local or UTC
### app/ValueObjects/FuelQuantity.php
Stores an amount plus unit, converts safely when necessary, and formats consistently. Avoid raw 216.8k strings in DTOs.
### app/View/FlightPlan/OverviewViewData.php
Optional adapter specifically for the rendered Overview. Use it only if the Livewire/Blade layout needs display-friendly values, badges, or labels that should not live in the core DTO.

## Create tests
------------
*** Completed ***
------------------------------------------------

### Completed: app/DTOs/ScheduleData.php

### Completed: app/ValueObjects/AirportCode.php

### Completed: app/ValueObjects/FlightTime.php

## Completed: Rename feature

## Completed: Convert Flight Plan Brief to a Livewire single page
