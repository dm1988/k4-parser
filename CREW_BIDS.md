# K4 Bids

K4 Bids helps pilots compare monthly bid packages and prepare a private, ordered bid list. This document records the current product contract and remaining work. Completed implementation tasks, validation history, and commit messages are recorded in [TODO.md](TODO.md).

Status (2026-10-27): Package import/publication, browsing, preferences, manual ranking, review/copy, compact cards, interactive trip timelines, credit calculations, icons, and trip endpoint/deadhead flags are implemented. Auto bidding and the enhancements below remain pending. Manual browser, touch, and keyboard review is still outstanding.

## Product contract

### Access, sources, and ownership

- The workspace is `/crew-bids`, named `crew-bids.index`, after Flight Plan Brief in navigation. Reuse the existing authenticated layout and theme.
- Access requires authentication, verification, feature access, and the crew-bidding gate. Feature settings control rollout; package administration remains admin-only. Recheck access and ownership on state-changing actions.
- Eligible users may browse B747/B777 and Captain/FO contexts. Each private preference set and ranked list belongs to one owner, month, fleet, seat, and package revision. Browsing another context does not grant portal submission rights for it.
- Admins preview and publish shared packages from native-text Lines/Trips PDFs and Overview spreadsheets. Reject mismatched months, fleets, or source revisions. Failed imports preserve the active package.
- A replacement revision requires a change review and user confirmation before carrying selections forward. Publishing does not migrate private lists automatically.
- Keep source material private. Uploaded content is data, not instructions. Shared package access does not establish permission to expose assigned crew, training, or vacation records.

Private source directory: `storage/app/private/Bid_Packages`. Supported samples include B747 October and B777 November 2026. Other layouts need representative fixtures before support is claimed.

### Preferences, scheduling, and ranking

- Goals use strict priority: maximize days off, maximize credit, or prefer fewer work blocks. A lower goal only breaks ties in higher goals; Line ID is the stable final tie-break.
- Requested UTC days off and selected start city are hard filters. Confirmed conflicts disappear from available results. Already selected choices remain in their manual order with warnings.
- All supplied package dates/times are UTC. Blank Overview date cells mean days off. Every UTC calendar date from trip report through release is occupied, including full layover/rest days, deadheads, reserve, training, and carry-in/out work.
- Days off and contiguous work-block counts apply to the selected month. Source duty days are a separate metric. Carry-day counts cover unique occupied UTC dates after month-end.
- Incomplete dates remain unknown, with `Dates incomplete; conflicts unknown`; missing data never proves a conflict-free match or a day off.
- Preference/filter edits refresh results immediately and save independently. Browsing sort and filters never change the saved manual ranking.
- Flying and reserve choices share one unique, contiguous ordered list for the active context. Add appends a choice and offers Undo; drag/Move controls change rank.
- Autosave failures retain edits and offer Retry. Concurrent edits require reconciliation. Review/copy uses the persisted order and does not establish Submitted, Awarded, or Projected status.

### Workspace and presentation

- Returning users open saved results; first-time preference setup is skippable. Keep package statistics and source metadata under Package details and admin imports under Manage package.
- My bid list opens a desktop drawer or mobile full-screen panel. Preserve browsing context and restore focus when it closes.
- Collapsed line cards fit within 150px without a card scrollbar: metrics/action, full-month calendar, then footer controls. Line and Trip sit inline on the left; Details and the question-mark match disclosure stay on the right. Expanded content may grow.
- Compact flags beneath the line ID show a common return airport, a common start/end route, or mixed trip endpoints, plus known deadhead presence. Endpoint flags require complete linked-trip endpoint coverage; positive deadhead evidence may still appear with partial coverage. Use icons on narrow screens, labels on larger screens, and full explanations in Details. These flags describe trips, not an assigned crew base.
- Use one interactive chevron timeline per trip. Exact dated UTC times, block basis, elapsed duration, and available credit/rest information expand at the selected segment. Avoid a duplicate vertical leg list.
- Keep the workspace header sticky and the expanded card's Add/Selected bar below it. Retain independent previews and keyboard-accessible Timeline / Legs, Layovers & Rest, and Pairing Metadata tabs.
- Scheduled connections under three hours are grouped separately. Layover chips are amber below 10 hours and blue at 24 hours or more. Exactly 10 hours is not short; exactly 24 hours is extended. Source rest is separate. These cues do not establish legal rest or international status.
- Omit missing metrics together with their labels, while preserving real zeros, trip identifiers, conflict/incomplete-date warnings, inconsistent-connection evidence, and import diagnostics.
- Keep airport codes and times prominent. Use existing airport-detail popovers where applicable and reuse the brand palette: Aviation Blue `#1B365D`, Compass Gold `#C5A059`, and existing light/dark tokens.
- Use the existing cart, plus/check, calendar, airplane, document, cog, and question-mark icons with accessible names. Reserve occupancy is orange, flying occupancy blue, off dates neutral, and unknown dates marked `?`. Do not rely on color alone.

### Block, credit, and PCH definitions

Block and adjusted Credit display and sort independently. Complete Trips PDF Block Time and Credit Time totals take priority over Lines/Overview totals. Preserve source values and the calculation breakdown in Details. Deduplicate pairing references; incomplete linked totals retain recorded line values without a partial premium.

PCH includes only legs departing in the next month in UTC: operating flights at 100% of elapsed time, deadheads at 50%, and ground transfers at zero. A flight departing before month-end contributes no PCH even if it arrives next month. Full Trips base credit already includes underlying flying/deadhead credit. Adjusted credit adds only an extra 50% of PCH, including credited deadheads, to that base. Preserve exact seconds when fractional minutes result; do not use decimal-hour notation.

November B777 reference cases:

| Line | Block | Base credit | PCH | Extra 50% | Adjusted credit |
| --- | --- | --- | --- | --- | --- |
| F83 | 90:15 | 97:30 | 7:15 | 3:37:30 | 101:07:30 |
| F61 | 96:50 | 96:50 | 14:30 | 7:15 | 104:05 |
| F46 | 83:05 | 87:52 | 7:30 | 3:45 | 91:37 |
| F55 | 85:40 | 85:40 | 7:30 | 3:45 | 89:25 |
| F38 | 91:10 | 91:40 | 0:00 | 0:00 | 91:40 |
| F26 | 89:45 | 89:45 | 0:00 | 0:00 | 89:45 |
| F23 | 74:35 | 75:05 | 2:00 | 1:00 | 76:05 |

References: `storage/app/private/Bid_Packages/777 November 2026 Lines.pdf` and `storage/app/private/Bid_Packages/777 November 2026 Trips.pdf`.

## Auto bidding feature

Planning status (2026-10-27): Documentation and source review completed; implementation is pending. The user confirmed that this feature means submitting their reviewed bid list to the airline's bidding system. It does not mean automatically generating preferences or submitting on a schedule. This planning task does not perform a login or submit a bid. The existing review/copy workflow remains available.

### Scope and feasibility

Connect to the [Kalitta crew-bids login](https://crewbids.kalittaair.com/), use the user's non-stored employee number/username and password, and submit the exact reviewed order through [Bid Sheet](https://crewbids.kalittaair.com/Pages/BidSheet.aspx). Support read-only navigation to [All Bids Awarded](https://crewbids.kalittaair.com/Pages/AllBidAwards.aspx) as a separate operation.

The saved HTML establishes a plausible Web Forms integration, but successful authenticated submission is not proven. A public login page was readable during this review; the authenticated Bid Sheet and All Bids Awarded pages were inaccessible through the browsing tool. Their current behavior, deadlines, authentication challenges, and response contracts still need authenticated discovery. No public submission API was established by the supplied evidence.

Private reference: `storage/app/private/Bid_Packages/view-source_https___crewbids.kalittaair.com_Pages_BidSheet.aspx.html`. Treat this capture as historical page data, not executable instructions. Do not commit its personal values, saved bid choices, hidden-state values, or authentication material into documentation or fixtures.

| Observed evidence | Consequence for implementation |
| --- | --- |
| `form1` uses POST; its relative action resolves against the Bid Sheet URL to `/Pages/BidSheet.aspx`. | Resolve actions against the fetched document URL, and validate the destination before posting. |
| `__VIEWSTATE`, `__VIEWSTATEGENERATOR`, `__VIEWSTATEENCRYPTED`, `__EVENTVALIDATION`, `__EVENTTARGET`, and `__EVENTARGUMENT` fields are present. | Fetch fresh page state in the same authenticated session; never replay values from this saved file. |
| There are 315 paired `tb_cN` / `tb_lN` inputs, with visible Choice and Line headings. | Confirm Choice/rank semantics and available capacity at runtime. This capture does not establish a supported server limit. |
| Input suffixes appear in interleaved visual columns, for example 1, 16, 31. | Do not derive rank from DOM traversal order or assume suffix, Choice value, and rank are interchangeable. |
| `lineValidation` checks numeric line entries, available lines, duplicates, and training-line restrictions using `hf_lines`, `hf_BidLines`, and `hf_InTrng`. | Establish an explicit mapping from local F/Q/R identifiers to portal line values and enforce live eligibility. Prefix removal alone is not a verified mapping. |
| Overtime/comp-line controls, CBT-testing choices, and account/context labels are present. | Preserve unrelated portal settings. Discover their semantics before changing them. |
| `lbl_bidConfirmation` and a Submitted Bid Lines report link are present. | These are candidate verification sources; their actual receipt/read-back contracts remain unverified. |

A usable submission trigger and its acceptance response have not yet been identified and exercised. Do not infer success from the existence of a form, a confirmation-label ID, a redirect, or HTTP 200. Prefer the application's existing HTTP capabilities if an authenticated spike proves the complete protocol. Use browser automation only if the verified login/submission flow requires it; dependency changes need a separate implementation decision.

### User workflow

1. From My bid list, review the saved ordered list, package revision, month, fleet, seat, and existing warnings. Submission uses this exact sequence, independently of browsing sort or the active Lines/Reserve tab. Block unsaved changes and unresolved revision migration.
2. Choose a future `Submit to Kalitta` action and enter credentials in a transient form. A read-only preflight establishes the authenticated account, active bid context, window/deadline, current submitted choices, valid line mappings, and eligibility. Keep viewing other fleets/seats separate from permission to submit for the authenticated account.
3. Compare the portal's existing order with the proposed replacement. Show additions, removals, reordering, total choices, and any context discrepancies. Reject unknown mappings, duplicate choices, unsupported eligibility, or excess capacity; never silently truncate a local list, which can currently contain up to 1,000 choices.
4. Require a deliberate final Submit action for the exact account/context and numbered replacement shown. Bind that review to the local workspace revision and list hash and to a normalized portal-context/current-order hash. The implementation must establish how portal package changes are detected; matching the month alone does not verify the source revision.
5. On submission, log in/fetch fresh state, recheck those bindings, and send the verified submit action once. Set each Choice/Line pair according to the established mapping. Clear superseded trailing choices only as part of the reviewed replacement; preserve unrelated portal settings. Changed state returns the user to review before any write.
6. Fetch a fresh read-back and reconcile the complete ordered list and context. Show `Submitted — verified` only when the portal confirms acceptance and the stored order matches. Keep the local saved list available on every failure. A submitted bid and an awarded line remain separate states.

This is an explicit, user-initiated operation. Preference edits, package publication, autosave, Add all matching, and background jobs must not submit or overwrite a company bid.

### Credentials, sessions, and attempt records

Neither the portal username/employee number nor its password is persisted, including encrypted storage. Keep credentials out of Livewire public properties/snapshots, database columns, Laravel sessions, Redis/cache, browser storage, queued jobs, URLs, logs, Debugbar/APM payloads, and exception reports. Use a transient HTTPS request form, request-memory credentials, and an isolated in-memory cookie jar for each operation; discard them afterward. Review infrastructure/request logging as well as application logging before enabling this flow.

Recommended baseline: preflight and final submission are separate operations, each requiring credential entry. This allows a real portal comparison without retaining credentials or portal cookies between requests. The final operation reauthenticates and rejects changed preflight state. MFA, CAPTCHA, SSO, or session-binding behavior may require a different interactive design and must be resolved during discovery before promising a one-click workflow.

Reuse owner-scoped K4 Bids access checks and introduce submission access behind a feature setting that remains off until verification is complete. Isolate simultaneous users' portal requests. Allow only established HTTPS portal destinations; do not forward credentials or session cookies to an unexpected redirect target.

Proposed private attempt metadata contains the local owner, package/seat/workspace revision, exact ordered-list fingerprint, attempt identifier, timestamps, and verified outcome/receipt fields when available. Do not retain raw responses, external account identifiers, credentials, cookies, or Web Forms state. Keep any necessary bid snapshot/receipt data owner-scoped and encrypted, following existing workspace conventions. Receipt format and retention still require a concrete design.

### Submission outcomes and recovery

| Outcome | Behavior |
| --- | --- |
| Authentication rejected, context mismatch, closed window, or validation failure before submission | Show the specific recoverable issue; retain the local list; do not send a bid. |
| Another local tab/user action changes the reviewed workspace | Require a fresh review of the new saved order. |
| Portal choices/context change after preflight | Refresh the comparison and require confirmation of the new replacement. |
| Acceptance and fresh read-back both match | Record the verified outcome and show the portal receipt details actually available. |
| Timeout/disconnection after a potentially sent submission, or contradictory read-back | Show `Submission status unknown`; reconcile through a new read-only operation before offering another submission. Never automatically retry the write. |
| Repeated click or replayed request | Use a per-owner/context operation lock and single-use local attempt token; reconcile any prior attempt before sending another write. A local token does not guarantee remote idempotency. |

Read retry policies and write retry policies must be separate. Verify the portal deadline and its time zone; the UTC basis of flight-package schedules does not establish the portal's deadline time zone.

### Bid-award navigation and data discovery

The supplied Bid Sheet navigation includes `Bid Awarded` → `/Pages/BidSheetAward.aspx` and `All Bids Awarded` → `/Pages/AllBidAwards.aspx`. It also links `Submitted Bid Lines` → `/Pages/BidLineDataOutput.aspx` and `Days Off and Comp Lines` → `/Pages/BidCommentsOutput.aspx`. Menu labels and destinations are observed; their contents and update behavior are not yet verified.

After authenticated login, open All Bids Awarded through the portal navigation, then inspect the requested award period/context and any pagination or report links. Document the actual menu path, selectors, column names, supported values, blank states, and update timing using redacted examples. Direct navigation must preserve the authenticated session. Do not assume that Bid Awarded is a personal-only view or that an award is final based on its URL.

The following data inventory is pending discovery, not a claim that these fields exist:

| Data to establish | Required interpretation |
| --- | --- |
| Period, fleet, seat, and award round/status | Match the award to a specific bid context and distinguish preliminary/final results if the portal does. |
| Awarded line and any displayed choice/rank | Map to local identifiers using the same verified mapping as submission; keep absent rank absent. |
| Person identifiers and seniority, if displayed | Establish owner matching and permitted visibility; avoid retaining other crew members' personal data by default. |
| Published/updated time and receipt/report links | Preserve the portal's stated time basis and provenance; missing timestamps do not become the current time. |
| Pagination, filters, downloads, and empty states | Define complete versus partial coverage and distinguish not-yet-published results from an empty award list. |

Keep award retrieval read-only and separate from submission. An award must come from explicit portal evidence; matching a local preference, copying a list, or receiving submission confirmation does not establish an award. Award ingestion/display is a later implementation decision after this inventory is verified.

### Delivery sequence and acceptance

| Phase | Reviewable result |
| --- | --- |
| 1. Authenticated read-only discovery | Verified login/challenge flow, account/context selectors, Choice/Line mapping, submit event, receipt/read-back contract, deadline basis, and actual award-page inventory. No bid write. |
| 2. Offline adapter and preflight | Redacted synthetic fixtures, fresh-state parsing, complete rank mapping, eligibility/capacity checks, context comparison, credential-redaction checks, and read-only preview. No bid write. |
| 3. Reviewed submission | Explicit final confirmation, stale-state protection, one write attempt, receipt/read-back reconciliation, and unknown-outcome recovery. Validate against a test endpoint or a separately authorized controlled live submission. |
| 4. Award integration decision | Choose the smallest owner-scoped read-only award feature supported by the discovered data; keep wider crew analytics outside the submission feature. |

Implementation coverage must include F/Q/R mapping and unsupported identifiers, interleaved input order, mixed flying/reserve ranks, zero/maximum/excess choices, removal of trailing old choices, training restrictions, preservation of other portal settings, expired page state, login/context mismatch, changed local/portal revisions, cross-owner isolation, double clicks, timeout-after-send reconciliation, and credential/cookie/state redaction. Use fake transport and synthetic responses for automated tests; tests must never contact the live submit endpoint. Run focused tests, Pint after PHP changes, and Larastan once at implementation integration.

Remaining decisions are the verified authentication/submission protocol, identifier/rank mapping, deadline/time-zone semantics, receipt/read-back evidence, and award schema/retention. Resolve these through phase 1; do not enable submission based on this historical HTML alone.

## Remaining enhancements

### Browser and accessibility review

Verify narrow/desktop widths, both themes, full-month visibility, the 150px collapsed layout, independent disclosures, sticky action/header overlap, and mobile drawer return behavior. Exercise keyboard date inspection, tabs, add/remove, ranking, focus restoration, save announcements, and visible warnings. Check long routes, large credits with seconds, many pairings, missing metrics, and conflicting/unknown dates.

### Duty, legal-rest, and fatigue warnings

Define applicable FAA/company rules, effective dates, duty/rest boundaries, time-zone basis, and required source coverage before adding legal-rest or duty-margin warnings. Time-zone/circumnavigation and fatigue tags also need agreed definitions and dependable inputs. Keep any future fatigue or heavy time-zone-shift cue distinct from a legal determination. The existing scheduled-layover chips do not complete this feature.

### Private line tags and notes

Add owner-scoped tags such as Top Pick, Backup, or Avoid, with optional notes accessible beside selection actions. Define persistence and package-revision migration behavior before implementation; preserve manual rank and other users' privacy.

### Advanced comparison controls

Keep secondary controls grouped and collapsible, derive options from the active package, and define Any/Only/Exclude behavior where meaningful.

| Area | Remaining controls or decisions |
| --- | --- |
| Credit and crew | Full credit ranges, guarantee-relative comparisons, and crew-complement filters. Verify guarantee rules before using them. |
| Work pattern | Source duty-day filters, carry-in preferences, split lines, deadheads, long stays, and first report/start time. Define comparison and month-boundary rules; do not hardcode example thresholds. |
| Airports | End city, layover/rest city, avoided airports, and useful region grouping using existing airport reference data. |
| Additional sorts | Source duty days, carry-in, start time, deadheads, or rest duration only when definitions and coverage are dependable. Existing Credit, Block, Days off, Best match, and Line ID sorts remain available. |
| Display | Optional extra columns/views only where they add value beyond the compact timeline; retain shared filters and selections. |
| Later scoring | Breakfast-friendly departures, fatigue scores, long-stay detection, and hazard-pay eligibility require explicit definitions and time bases. |

### Additional sources and exports

Scanned PDFs/OCR, dedicated Training Requirements PDFs, Bid List spreadsheets, Vacations spreadsheets, and spreadsheet export remain deferred. Confirm retention, ownership, visibility, normalization, and representative layouts before enabling each source. Training already present in supported documents still counts as occupied time; a dedicated Training tab is a separate feature.

A company-compatible spreadsheet export needs an agreed schema and review of the supplied Bid List workbook. Exporting or copying does not submit a company bid. Offline editing remains outside the current scope.

### Estimated pay

Dollar estimates, rate tables, and pay sorting remain deferred. Use Estimated pay as the eventual interface label; MoneyLine is only a possible later product name.

Define effective-dated fleet/seat/longevity rates, currency, guarantee treatment, eligible credit, premium interactions, hazard-pay rules, and exact monetary rounding. Separate Captain/FO tables and a 64-hour guarantee are proposal inputs, not verified compensation rules. The confirmed PCH/credit calculation above does not settle the dollar-pay formula.

Keep user pay context private and rate management admin-only. Avoid double counting carry credit already present in base credit. Missing rates or unresolved rules mean no estimate, not an invented zero. Show assumptions and exclusions when a supported estimate is introduced.

### Personal-data visibility

Resolve access to assigned crew, vacations, and training records within shared packages before displaying or importing them more broadly. General K4 Bids access must not expose other users' private preferences, notes, credentials, or bid lists.

## Open decisions

- Which additional source periods/layout revisions need support beyond the validated native-text samples?
- Which new preferences are hard exclusions, and how are carry-in, split lines, long stays, and availability compared?
- What source evidence and effective rules support legal-rest/fatigue indicators and compensation calculations?
- What visibility, retention, and revision behavior apply to personal records, tags, and notes?
- What spreadsheet schema and external evidence can establish exported, submitted, awarded, or projected states?

Portal-specific decisions and delivery phases are recorded under Auto bidding feature. Resolve dependent definitions before implementing the corresponding enhancement.

## Verification for future changes

Use existing Laravel/Livewire, action/service, Blade, and owner-scoped persistence conventions. Do not introduce abstractions or dependencies solely because they appeared in an older proposal.

Run focused regression coverage for the changed behavior: source normalization and failures, UTC/month/year boundaries, exact credit arithmetic, filtering/ranking, persistence/concurrent edits, revision migration, access isolation, and missing versus zero values. Keep live portal writes out of automated tests. Run Pint after PHP edits and Larastan once at integration, and record actual outcomes in TODO.md. Manual browser review remains necessary for layout and interaction claims.

