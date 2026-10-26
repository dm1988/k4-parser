User preferences foremost
    - "I want to: "
      - Maximum days off
      - Maximum credit
      - Specific days off
      - Long work block
    - "I would like: "
      - Carry in days
      - Hazard pay

# App Purpose
K4 Bids is a specialized logistics and planning tool for K4 flight crews (pilots). Its primary purpose is to transform complex, raw monthly airline "bid packages" into a ranked, actionable schedule that fits the crew member's personal preferences.

# Key features

## Intelligent Bid Planning
- Converting data-heavy airline packages into ranked choices.
## Professional Workspace
- A central hub for managing monthly flight schedules and bidding workflows.


# Color Pallette

## Architectural Overview & Domain Purpose

**CrewBidPro** is a Web-based pilot schedule and bid package optimization workspace designed specifically for cargo airline pilots (in this context, Kalitta Air B777 First Officers/Captains). The platform ingests monthly bid package data (flying lines, reserve lines, trip details, line credits) and allows crew members to filter, analyze, evaluate, and rank bid lines to maximize earnings and manage duty schedules effectively.

---

## Technical & Functional Features

### 1. Header & System Status

* **User Profile & Equipment Context:** Displays the active user’s name, role, and equipment (e.g., `DA David A Gonzalez B777 FO`).
* **Bid Package Context:** Displays the active bid package title and period (e.g., `OCT 2026 - 777 bid package`).
* **App Update Tracking & Help:** Includes a status indicator for the last app update timestamp with a changelog review link, an FAQ link, a developer contact portal, and session control actions (`Account`, `Sign Out`).
* **Data Import Utility:** An `Import` function to upload or reload a new bid package file into the workspace.

---

### 2. Bid Package Summary Dashboard

Displays real-time aggregate metrics derived from the loaded bid package:

* **Flying lines:** Count of active flying lines (e.g., `87`).
* **Reserve lines:** Count of available reserve lines (e.g., `34`).
* **Trips:** Total trip pairings across all lines (e.g., `92`).
* **Line credit range:** Minimum and maximum line credit range (e.g., `40:29 - 94:40`).
* **Top airport:** Most frequent layover/origin station (e.g., `ANC`).
* **Top leg:** Most frequent flight segment (e.g., `HKG-ANC`).

---

### 3. Interactive Schedule Filter ("Days to Avoid")

* **Calendar Interface:** Displays a full monthly calendar view for selecting specific dates to avoid.
* **Selection Controls:** Includes interactive date tiles and a `Clear Dates` toggle button.
* **Collapsible UI:** Accordion wrapper (`Collapse` / `Expand`) to manage workspace real estate.

---

### 4. Line Filtering & Sorting Controls

An extensive control panel to refine and scope lines based on operational and preference constraints:

* **Credit & Crew:**
* **Credit Sort:** Filters/sorts by highest or lowest credit.
* **Minimum Guarantee:** Options to toggle between `Show All Lines`, `Below 64 hours`, or `64 hours and above`.
* **Crew Composition:** Filters lines based on crew complement (`1 CA 1 FO`, `1 CA 2 FO`, `2 CA 1 FO`, `2 FO ONLY`, `4 CA 4 FO`).


* **Days & Duty:**
* **Duty Days Filter:** Categorizes lines by duty day thresholds (`14`, `15`, `16`).
* **Days Off Filter:** Categorizes lines by total days off (`15`, `16`, `17`).
* **Carry-In Days:** Toggles visibility for carry-in duty (`Hidden`, `Only Carry-In Days`).
* **Split Line:** Filters split schedule configurations (`Hidden`, `Only Split Lines`).


* **Airports & Layover Stations:**
* **City - Start:** Filters by originating airport (`CVG`, `JFK`, `LAX`, `MIA`, `ONT`, `ORD`).
* **City - End:** Filters by terminating airport (`ATL`, `CVG`, `JFK`, `LAX`, `MIA`, `ONT`, `ORD`).
* **City - Stay/Rest:** Filters by layover/rest stations (`ANC`, `BRU`, `CVG`, `EMA`, `GYD`, `HKG`, `HNL`, `ICN`, `LAX`, `LEJ`, `LGG`, `MIA`, `NGO`, `NRT`, `ONT`, `ORD`, `PVG`, `SCL`, `SDF`, `SIN`, `YHM`).
* **Avoid Airports Panel:** Categorized regional multi-select lists to exclude specific airports from line results:
* *Asia:* `SIN`, `HAN`, `TPE`, `HKG`, `PVG`, `ICN`, `NGO`, `NRT`.
* *North & South America + Hawaii:* `ANC`, `YHM`, `YYZ`, `HNL`, `LAX`, `ONT`, `ORD`, `SDF`, `CVG`, `MIA`, `JFK`, `BOG`, `VCP`, `SCL`.
* *Europe & Middle East:* `BRU`, `EMA`, `LGG`, `LEJ`, `TLV`.
* *Unclassified/Package Specific:* `ATL`, `BKK`, `GYD`.




* **Miscellaneous Filters:**
* **Deadheads:** Toggles lines containing deadhead legs (`Hidden`, `Show Deadheads only`).
* **Long Stay:** Toggles lines featuring extended layovers.
* **Trip Start - Breakfast Friendly:** Filters for morning departure lines.



---

### 5. Bid List & Selection Management

* **Selection Tracker:** Displays the count of currently selected lines (e.g., `20 selected`).
* **Global Action Controls:** `Show Selected`, `Clear Bid List`, and `Add All Lines in View` buttons.
* **Interactive Drag-and-Drop Reordering:** Allows ranking selected lines via numbered line pills with drag handles (`⋮⋮`).
* **Quick Navigation:** Jump menu shortcut to jump between workspace sections.

---

### 6. Line Comparison & Data Visualization Workspace

Supports dual viewing formats (**Table** view and **Linear Schedule** view) across three category blocks: *USER SELECTED*, *AVAILABLE*, and *CURRENTLY UNAVAILABLE*.

* **MoneyLine™ Speculative Earnings Engine:**
* Calculates speculative line compensation by combining regular line credit, guaranteed minimum thresholds (64-hour floor), scheduled duty dates, and active longevity/pay rates.
* *Formula Visualization:* `Line Pay + Carry-In Pay = Total Estimated Compensation` (e.g., `$11,275.29 + $1,999.56 = $13,274.85`).


* **Line Data Attributes & Columns:**
* **Line ID:** Line identification badges (e.g., `F20`, `F38`, `F79`).
* **Credit Hours:** Formatted credit time (e.g., `67+40`, `60+05`).
* **Status & Classification:** Reserve indicators (`RSV`), Awarded state (`AWARDED / PROJECTED`), and trip pairing numbers (e.g., `724`, `785`).
* **Assigned Crew Context:** Displays assigned Captains and First Officers (e.g., `CA Ray · FO Hill & Gonzalez`).
* **Line Sorting Parameters:** Sort options including MoneyLine™ (Highest/Lowest), Credit, Carry-In, Duty Days, Days Off, Calendar Start Date, First Show Time, Fatigue Scores (Low to High), Deadhead count, Hotel Rest duration, and Line ID order.
* **Display Toggles:** Custom checkboxes for toggling visual display of `MoneyLine™`, `Fatigue Score`, `Start Date/Time`, `Available + submitted`, `LONG STAY`, and `AWARDED / PROJECTED` details.