That table list (23 tables on SQLite via Sail) tells the whole story: right now, your application is a brilliant collection of purpose-built micro-utilities—`bid_packages`, `extract_requests`, `flight_plan_results`, and `flight_events` are all operating as independent domain islands.

To pivot this into a seamless **Bid → Proposed → Flown** platform without breaking your existing features or rewriting your parsers from scratch, you don't need to burn down those tables. Instead, you can introduce a **Canonical Flight Event & Logbook Layer** that bridges them together.

Here is how you can design the pivot cleanly in Laravel:

---

## 1. The Strategy: Don't Rewrite Parsers, Build a Common Normalizer

Your existing extractors (`Extract Schedule` / Jeppesen PDF, `Flight Plan Brief`, and `K4 Bids`) are already producing rich structured arrays or JSON payloads. Instead of forcing them to save into separate dead-end tables, have them feed into a shared **`pilot_flight_legs`** (or unified timeline) table via a common DTO (Data Transfer Object) or normalizer service.

### Recommended Target Schema: `pilot_flight_legs`

Add a new migration for a unified table that can represent a leg across its entire lifecycle:

```php
Schema::create('pilot_flight_legs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();

    // Lifecycle & Provenance State
    $table->string('state')->default('proposed'); // bid_simulated, proposed, dispatched, flown
    $table->string('source_type')->nullable();    // k4_bid, jeppesen_roster, flight_release, manual
    $table->unsignedBigInteger('source_id')->nullable(); // Polymorphic pointer back to original table if needed

    // Trip & Flight Identification
    $table->string('bid_period')->index();        // e.g., '2026-12'
    $table->string('pairing_code')->nullable();   // e.g., 'Trip 714'
    $table->string('flight_number');              // e.g., 'K4 253-2'
    $table->string('departure_station', 4);       // NRT
    $table->string('arrival_station', 4);         // YHM
    $table->string('tail_number', 10)->nullable(); // Populated from release (e.g., N772CK)

    // Scheduled Times (UTC)
    $table->timestamp('scheduled_out_utc')->nullable();
    $table->timestamp('scheduled_off_utc')->nullable();
    $table->timestamp('scheduled_on_utc')->nullable();
    $table->timestamp('scheduled_in_utc')->nullable();
    $table->integer('scheduled_block_minutes')->default(0);
    $table->integer('credit_minutes')->default(0);
    $table->boolean('is_deadhead')->default(false);

    // Actual OOOI Times (Flown / Logbook Stage)
    $table->timestamp('actual_out_utc')->nullable();
    $table->timestamp('actual_off_utc')->nullable();
    $table->timestamp('actual_on_utc')->nullable();
    $table->timestamp('actual_in_utc')->nullable();
    $table->integer('actual_block_minutes')->default(0);

    // Pilot Logging & Currency Attributes
    $table->boolean('is_pilot_flying')->default(false);
    $table->integer('landings_day')->default(0);
    $table->integer('landings_night')->default(0);
    $table->text('pilot_remarks')->nullable();

    $table->timestamps();
});

```

---

## 2. Step-by-Step Implementation Roadmap

### Step 1: The "Promote Bid Line" Action (Bid ➔ Proposed)

When a pilot selects or views a line in your `[K4 Bids](http://127.0.0.1:8000/crew-bids)` module (like **F86** currently active on your screen), add a primary CTA: **"Set as Active Proposed Schedule"**.

* When clicked, your controller reads the JSON payload/trips associated with that bid package line and bulk-inserts them into `pilot_flight_legs` with `state = 'proposed'`.
* This instantly populates their calendar view and ICS feed without waiting for management to publish the official monthly roster.

### Step 2: The Reconciliation Layer (Proposed ➔ Roster Extract)

When the official Jeppesen roster or trip PDF is uploaded via `[Extract Schedule](http://127.0.0.1:8000/dashboard)`:

* Instead of dropping data into an isolated extract table, your parser runs a **matcher algorithm** against the existing `pilot_flight_legs` for that `bid_period`.
* Matches on `flight_number` and `departure_station` within a 24-hour UTC window.
* **Smart Diffing**: If the airline changed a departure time or swapped a leg, flag it in the UI as a **Schedule Change / Reroute** rather than blindly wiping out user customizations.

### Step 3: Operational Release Enrichment (Proposed ➔ Dispatched)

When an operational release is processed via `[Flight Plan Brief DEMO](http://127.0.0.1:8000/flight-plan-brief)`:

* Automatically query `pilot_flight_legs` for the matching active leg on that date.
* Update the record with the verified **tail number** (e.g., pulling directly from your `aircraft` table mapping `N772CK` / `N774CK`), fuel plans, routing, and captain details.
* Transition the leg state to `dispatched`.

### Step 4: The Logbook & Pay Audit Screen (Dispatched ➔ Flown)

After the trip is complete, transform your logs into an audit powerhouse:

* **One-Tap OOOI Confirmation**: Present a clean post-flight review modal where the pilot just confirms actual OUT/OFF/ON/IN times.
* **Instant Pay Audit**: Because you already track contractual rules in your bidding engine, compare `credit_minutes` (Bid/Scheduled) against `actual_block_minutes` or delay triggers to automatically calculate over-block pay adjustments.
* **Export**: Add a single export button to download a clean CSV formatted for **LogTen Pro** or **ForeFlight**.

---

### Where to start?

Keep all 23 tables intact and build `pilot_flight_legs` as the central consumer table.
