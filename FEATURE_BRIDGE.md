Turning your site from a set of discrete utilities (Bidding, Schedule Extraction, Flight Plan Briefs) into a unified, end-to-end pilot lifecycle platform is a huge step—and a logical one, because you already own the underlying data primitives at every stage of the pipeline.

Right now, your data likely lives in silos: a bid package model, an extracted roster/trip model, and a parsed flight release model. To bridge them into a continuous **Bid → Proposed → Flown** pipeline without creating massive technical debt, you need a canonical data architecture that tracks a duty/flight from its initial "planned" state to its "as-flown" reality.

Here is an architectural blueprint for how to connect these three stages cleanly in Laravel.

---

## 1. The Core Lifecycle & State Machine

Instead of treating a Bid Line, an Awarded Schedule, and a Logbook Entry as completely disconnected concepts, model the pilot's month around a unified **Schedule Timeline** that evolves through four distinct states:

| Stage | Trigger / Source | Mutability | Primary Value to Pilot |
| --- | --- | --- | --- |
| **1. Bid (`BID_SIMULATED`)** | K4 Bids line selection / preference engine | Read-only (Package baseline) | "What if I hold F83? What is my projected pay, days off, and commute?" |
| **2. Proposed (`PROPOSED`)** | Initial line award or Jeppesen roster upload | Mutable (Revisions/Swaps) | ICS calendar sync, layover planning, hotel/city guides, pre-trip prep. |
| **3. Active (`DISPATCHED`)** | Flight Plan Brief / Operational Release upload | High-frequency updates | MEL/CDL checks, fuel/W&B, ETOPS, and live actual departure/arrival times. |
| **4. Flown (`LOGGED`)** | Post-flight OOOI times / manual sign-off | Locked / Auditable | Official logbook export (ForeFlight/LogTen CSV), currency tracking, **Pay Audit**. |

---

## 2. Database & Domain Modeling

To prevent painful migrations later, separate **Planned/Scheduled** data from **Actual/Flown** data at the leg level, rather than overwriting scheduled times when a flight delays or reroutes.

### Key Domain Entities

* **`UserMonth` (or `BidPeriod`)**: Anchors a pilot's specific month (e.g., `2026-12`), fleet (`B777`), seat (`FO`), and seniority/base. Links to their awarded `BidLine` (if applicable) and holds their running totals (Scheduled Credit vs. Actual Credit).
* **`DutyPeriod` & `FlightLeg**`: The core operational units. Every leg has:
* **Scheduled fields**: `sched_dep_utc`, `sched_arr_utc`, `sched_block_minutes`, `credit_minutes`, `is_deadhead`.
* **Actual (OOOI) fields**: `out_utc`, `off_utc`, `on_utc`, `in_utc`, `actual_block_minutes`, `night_minutes`, `instrument_minutes`, `is_pf` (Pilot Flying), `landings_day`, `landings_night`, `remarks`.
* **Provenance / Source**: An enum (`BID_AWARD`, `ROSTER_OCR`, `TRIP_PDF`, `FLIGHT_RELEASE`, `MANUAL`) so your parser knows which source wins during a merge.


* **`AircraftTail`**: A normalized registry (e.g., `N772CK`, `N774CK`) populated automatically from Flight Plan Briefs so logbook entries already have accurate registration and aircraft type (`B77L` / `B772`) attached.

---

## 3. High-Value Bridges Between Stages

The real magic of an end-to-end platform is what happens at the *transitions* between your existing tools:

### Bridge A: Bid → Proposed Schedule ("Award Seeding")

* When the bid closes or the pilot knows their awarded line (e.g., **F83**), give them a **"Promote Line to Proposed Schedule"** button.
* This clones the trips and legs from `Trip 703` into their active `2026-12` timeline immediately—giving them an instant `.ics` calendar feed and layover overview before Jeppesen even publishes the final roster.
* When they later upload a Jeppesen screenshot or Trip PDF to **Extract Schedule**, your parser runs a **diff/reconciliation** against their seeded schedule, highlighting schedule changes, reroutes, or deadhead revisions rather than creating duplicate trips.

### Bridge B: Proposed Schedule → Flight Plan Brief ("Auto-Matching")

* When a pilot uploads an Operational Flight Release into **Flight Plan Brief**, match the flight number, date, and city pair (e.g., `K4 255-2`, `NRT → YHM`) to the existing `FlightLeg` in their Proposed Schedule.
* Automatically attach the parsed tail number, route waypoints, ETOPS alternates, and planned fuel to that leg, and transition its status from `PROPOSED` to `DISPATCHED`.

### Bridge C: Flight Plan Brief → Flown Logbook ("One-Tap Logbook & Pay Audit")

* Once a leg is completed, pre-fill a logbook draft using the scheduled/release data:
* Date, Flight #, Dep/Arr ICAOs, Tail #, Aircraft Type, Scheduled Block, and **Crew Manifest** (automatically populating the Captain's name from the release/GENDEC for PIC reference).
* The pilot only needs to input/confirm actual **OUT / OFF / ON / IN** times (or actual block), check **PF/PM**, and log landings.


* **The Killer Feature — Pay Reconciliation**: Because your K4 Bids engine already calculates contractual credit (deadhead 50%, carry-in premiums, minimum guarantees, over-block), comparing **Scheduled Credit** vs. **Actual Flown Credit** automatically flags when a pilot is owed over-block pay or reroute protection.

---

## 4. Suggested Phased Rollout

Because this touches the core data model, rolling it out incrementally keeps your existing users' workflows intact while you build the backbone:

1. **Phase 1: Unified Timeline & "Promote Bid Line"** — Create the canonical `FlightLeg` schema that holds both `scheduled_*` and `actual_*` columns. Allow K4 Bids users to "Activate" a line into their personal schedule, and update the schedule extractor to upsert into this same table.
2. **Phase 2: Release-to-Leg Linking** — Connect the Flight Plan Brief parser to attach tail numbers, crew names, and operational metadata onto the matching `FlightLeg`.
3. **Phase 3: Logbook & Currency UI** — Build the pilot logbook view (`FLOWN` filter on the timeline) with quick OOOI entry, automatic night-time calculation (based on departure/arrival coordinates and UTC timestamps), 90-day landing currency counters, and CSV exports formatted for **LogTen Pro** and **ForeFlight**.
4. **Phase 4: Pay vs. Actuals Ledger** — Surface a monthly reconciliation dashboard comparing Bid Guarantee vs. Proposed Credit vs. Actual Pay Credit Hours (PCH).

---

To help narrow down the immediate next step: how are your **K4 Bids** trips, **Extract Schedule** parses, and **Flight Plan Briefs** currently stored in the database (separate tables per feature, or sharing any trip/leg models)?