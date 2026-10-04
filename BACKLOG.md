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

## Paused: Flight release: 24 hour time limit

### Goal

Expire only the cached flight-release result 24 hours after successful extraction. Remove its saved `flight_plan_results` row and any cache entries belonging specifically to that result, and stop serving it through the brief or Fuel Score URL.

### Current implementation

- `FlightPlanResultStore` keeps one encrypted `flight_plan_results` row per user. `save()` replaces that row; `get()` checks owner and key, and `latest()` checks owner, but neither checks age. The row has timestamps, but no explicit extraction or expiry timestamp.
- `FlightPlanBrief` loads the latest row on mount and retrieves it on render. `OfflineFuelScoreController` retrieves the same row by key. Both rely on the store, so expiry enforcement belongs there.
- `FlightPlanTextExtractor` caches PDF text for seven days under a file-hash key. This cache is independent of the saved result and can be shared by identical uploads; it has no owner or result-key mapping. The uploaded PDF is deleted after extraction.
- Existing scheduling in `routes/console.php` can run cleanup of expired cached results.

### Problem

An old cached result remains readable until the user replaces or manually clears it. `updated_at` is not a reliable extraction clock if a row is later touched. Expiration must apply to the saved result rather than the independent, shared PDF-text extraction cache.

### Implementation plan

1. Add an immutable extraction timestamp and an indexed expiry timestamp to `flight_plan_results`. Set both when a successful extraction is saved, including when the user's existing row is replaced. Compute expiry as extraction time plus 24 hours, independent of the flight schedule. Compare instants in UTC and define expiry at the deadline (`now >= expires_at`).
2. Make `FlightPlanResultStore::get()` and `latest()` exclude expired rows and delete a matching expired row when encountered. Keep owner and result-key checks in place. The brief should return to its upload state after expiry; the Fuel Score URL should return 404. Clear any stale Livewire result key or selected task when a rendered result expires so the UI does not retain a link to it.

3. Inventory cache entries belonging specifically to the saved result and invalidate those with the result. Preserve the independent shared PDF-text cache and its existing seven-day lifetime, along with airport and schedule caches. No source-document or other application data cleanup is part of this task.
4. Document the 24-hour cached-result lifetime in the upload/result UI with a concise UTC-aware message, including that re-upload is needed after expiry.

### Acceptance criteria

- A cached result is accessible before extraction time plus 24 hours and unavailable at or after that deadline, including exact-boundary and midnight rollover cases.
- Flight schedule values never shorten or extend the cached-result lifetime. Replacing a result starts a new extraction clock and invalidates the old key.
- Expired data is removed from the database by scheduled cleanup even without another request. Brief and Fuel Score reads deny an expired result immediately, regardless of whether cleanup has run.
- No cache entries belonging specifically to the expired result remain available. Shared PDF-text, airport, and schedule caches retain their existing behavior and lifetimes.
- Ownership checks, encrypted storage, and the normal upload/error flows continue to work.

### Validation for implementation

- Add focused store tests using a frozen clock for the 24-hour deadline, exact equality, midnight rollover, schedule-independent expiration, replacement, ownership, and deletion on read.
- Add focused Livewire and Fuel Score tests for expiry transitions and a cleanup-command test for unattended expiration and repeat runs.
- Run only affected tests through Sail, Pint after PHP changes, and Larastan once at the final integration checkpoint. Record the outcomes here.

### Planning outcome

Restricted expiration to the cached flight-release result and its own cache entries, with a fixed 24-hour lifetime after successful extraction. Removed arrival-time expiration and shared PDF-text cache changes. This update changes documentation only; the task remains paused.

Commit message: `feat: expire cached flight release results after 24 hours`

## Mobile sticky flight header Implementation
**Context**
The user attempted to make a flight header sticky, but the initial application of `position: sticky` failed due to CSS stacking context and parent container constraints.

**Diagnostics**
The investigation identified two primary blockers preventing the header from sticking:

*   **Containment Limitation:** The header was nested inside `section#release-summary`, which had a height nearly identical to the header itself (approx. 186px). A sticky element cannot remain fixed beyond the boundaries of its parent.
*   **Overflow Constraints:** An ancestor element (`div.overflow-hidden.rounded-lg.bg-white`) was set to `overflow: hidden`, which prevents `position: sticky` from functioning on child elements.

| Element | Property | Value | Impact |
| :--- | :--- | :--- | :--- |
| `section#release-summary` | `height` | ~186px | Limits sticky travel range |
| `div.overflow-hidden...` | `overflow` | `hidden` | Breaks sticky positioning |
| Header Element | `position` | `static` | Initial state |

**Actionable Findings**
*   **DOM Restructuring:** To provide sufficient scrolling room, the header must be moved out of the limited `section` container and placed into a taller parent container (e.g., the main content `div`).
*   **Ancestor Updates:** Any ancestor with `overflow: hidden`, `overflow: auto`, or `overflow: scroll` must be changed to `overflow: visible` unless that ancestor is the intended scroll container.
*   **Visual Integrity:** A background color is necessary to prevent underlying content from bleeding through the header during scroll.

**Code Fixes**
The following changes were identified as a potential fix for the live page. These should be adapted for the source components (e.g., React or Vue files):


`````css
/* 1. Ensure the header has the correct sticky properties */
.flight-header {
  position: sticky;
  top: 0;
  z-index: 50;
  background-color: white; /* Match theme background */
  width: 100%;
}

/* 2. Remove sticky-breaking overflow on ancestors */
.ancestor-container {
  overflow: visible !important;
}
`````


**Implementation Guidance**
1.  Relocate the header component in the source code so it is a sibling to the content it should scroll over, rather than being nested inside a height-constrained section.
2.  Verify that all parent containers between the header and the `body` (or the primary scrollable area) have `overflow: visible`.
3.  Add a `z-index` and background color/blur to maintain legibility.

## Paused: Bugs: offline fuel score

### Goal

Keep entered Off time, starting FOB, and waypoint ATA/AFOB through a same-tab reload, including the reported iPad Chrome background/foreground case while the iPad is offline.

### Findings

The standalone Alpine calculator initialized all inputs to blank and kept them only in memory. The reviewed calculator code has no reload timer or explicit page refresh. The reported device is an iPad using Chrome; the user reports returning to the same tab after using another app while the iPad is offline. Browser tab suspension or reload is plausible in this scenario, but it has not been reproduced or confirmed as the trigger. `vite.config.js` enables development refresh, and recent Debugbar requests and browser logs did not identify an offline-calculator refresh. Standard `sessionStorage` is designed to survive reloads and tab restores, but its behavior after an iPad browser process is discarded must be checked on the affected device. More decisively, the current page cannot load at all after an offline navigation/reload because the app has no service worker or offline page shell; saved inputs alone cannot solve that case.

### Outcome

- Added a versioned `sessionStorage` draft scoped by the authenticated owner and `flightPlanKey`. The controller supplies that scope after its existing ownership check.
- The draft saves only entered strings for Off time, starting FOB, ATA, and AFOB. It restores them before Alpine watchers are attached, then recalculates from the current release. Partial values and explicit zeroes survive a reload.
- The source signature includes the fuel unit, confirmed fuel quantities, and ordered waypoint identifiers, coordinates, times, TBO, and remaining fuel. Each reading is also tied to its waypoint position and source identity. Malformed or incompatible drafts are discarded, and drafts do not cross owners or release keys.
- Reset clears live inputs and the draft; queued Alpine watchers cannot resurrect prior values. If storage blocks removal but permits writing, Reset replaces the old draft with an empty one. Storage failures leave calculations usable and show a short accessible notice when recovery cannot be trusted.
- Page copy now explains same-tab recovery and that loading or refreshing the page still requires a connection. No service worker or offline page-load cache was introduced.

### Validation

The focused JavaScript calculator and draft tests pass: 20 tests in the affected files, including reload recovery, initialization order, repeated identifiers, scope/source isolation, Reset, malformed drafts, and read/write/remove failures. `OfflineFuelScoreTest` passes (4 tests, 53 assertions). Pint, the production Vite build, and one final Larastan pass on changed PHP and the focused test pass. Browser lifecycle verification was unavailable in this environment.

### Remaining investigation

The affected iPad is offline when Chrome returns. On that device, enter Off time, starting FOB, ATA, and AFOB; switch to another app and return to the same tab. Record whether the page stays mounted or reloads, any prompt, and whether the four values return. Repeat after a longer background period. The implemented `sessionStorage` draft recovers a same-tab reload only when the page can load; it cannot restore the calculator after an offline navigation/reload. If the browser discards the tab session itself, even tab-scoped storage may be unavailable. Compare development and production builds before attributing the refresh to Vite.

### Proposed offline reload extension — authorization required

A calculator-only service worker could serve a static offline shell for the exact fuel-score route when a network request fails and cache the versioned CSS/JavaScript assets. To make the release and its entered values available in that shell, it would also need a local copy of the source-backed calculator data. The safer proposed design encrypts the minimum required source payload in browser Cache Storage with a per-tab key in `sessionStorage`, scopes it to the authenticated owner and release, gives it a short expiry, and clears it when possible. The service worker must use the server response whenever online and never turn a server 403/404 into cached content. The offline shell would show an explicit unavailable state if the key, assets, or cache are missing. An offline reload could not re-check server authorization; anyone who can use the still-open tab and its key could view that cached release until the session ends. Browser eviction or loss of the tab session can still prevent restoration.

This extension needs focused tests for route scope, network-first behavior, encryption/decryption, expiration, missing keys/assets, source mismatch, and unauthorized online responses, followed by an actual Chrome-on-iPad offline background/reload check. It also needs a visible readiness state so the user knows when the offline copy has been prepared. The attempted service-worker/cache implementation was rejected by automatic approval review because persisting authenticated release content on the device is a security/privacy side effect not specifically authorized for this task. No service-worker or release cache code was added. Continue this part only after explicit approval for that on-device storage behavior.

References: [page-session behavior](https://developer.mozilla.org/en-US/docs/Web/API/Window/sessionStorage), [WebKit background tab suspension](https://webkit.org/blog/8970/how-web-content-can-affect-power-usage/), and [service-worker navigation interception](https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerGlobalScope/fetch_event).

Commit message: `fix: preserve offline fuel score inputs across reloads`

## Flight plan: Add task: Takeoff and Landing Report
### Answer why first?
TLR validation? Requires robust weather implementation and detailed ruleset. High liability.

Just because it's in the release? 

----------

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
