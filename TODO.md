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
7. Each task should consist of: Goal, Current implementation, Problem. 
8. Definitions:
   1. FP = Flight Plan more likely the flight plan brief tool
   
Optionally add references and constraints.

# Product and UI rules

- Use Aviation Blue for structure, Compass Gold for primary emphasis, and the existing light/dark theme tokens.
- Keep operational values compact and scannable; use monospaced text for codes, times, routes, coordinates, and numeric planning values.
- Label every time basis and fuel unit. Never silently mix UTC/local or pounds/kilograms.
- Distinguish `not present in this release` from `not supported yet`. Do not render zero, empty text, or a green status for missing data.
- Preserve source evidence internally for Weather, ETOPS, Fuel Score, and Weight & Balance.
- Reuse Blade components and view data; do not parse, normalize, query, or authorize inside Blade.
- Every interactive control needs keyboard access, visible focus, an accessible name, and a useful loading/empty/error state.

# Tasks
## [x] Completed: Resolve seven reported test failures

Goal: Recheck and fix the seven failures in the supplied test output without weakening their assertions.

Current implementation (2026-11-16): Excluded internal source_observations from flight and other calendar descriptions, preserving operational notes and stored provenance. Limited each Tesseract subprocess to one OpenMP thread with a child-only environment setting to reduce CPU contention; rasterization and OCR timeouts, OCR-required page handling and cleanup remain enforced. Added regressions for both calendar-description paths and subprocess/parent environment isolation. The five reported CrewBidEmployeeTest failures already pass with the current queued-refresh assertions and worker execution; their pasted output used older synchronous expectations, with mismatched source-line excerpts.

Problem addressed: Calendar metadata reconciliation introduced nested source observations which the notes formatter flattened back into origin/destination/raw-source fields. The large private PDF's OCR failure was intermittent: logs identify a Tesseract timeout on an image-only page; the same upload passed in isolation before changes. Tesseract's documented single-thread option limits per-process contention without increasing the timeout or skipping image pages.

Validation: All seven reported cases passed across focused Sail runs using isolated in-memory SQLite. The five bid cases passed unchanged; the calendar-export case reproduced before the fix. All three new regression cases failed before implementation. After implementation, 27 calendar/ICS/OCR and export cases passed (183 assertions), and the supplied private PDF upload passed with real OCR (7 assertions), for 33 distinct passing cases including the five bid cases. The PDF case took 49.81 seconds before and 38.43 seconds after the change in these individual runs; this is not a controlled performance benchmark or a guarantee under arbitrary load. Pint, whitespace checks and the single Larastan pass (zero errors) passed. Debugbar had no recent captured CLI issue summaries. No full suite was rerun.

Commit message: `fix: omit calendar provenance and bound OCR thread usage`

## [x] Completed: Verify and recover nonconsecutive global choice ranks

Goal: Refresh shared bids when global report ranks have gaps without publishing conflicting data or silently changing the portal's printed ranks.

Current implementation (2026-11-16): Replaced unconditional consecutive-rank rejection with a read-only, same-session comparison against the affected employee's individually selected report. Fresh selector state, employee identity/directory, position and pagination are verified. A complete individual rank sequence can fill omitted global rows only when every existing global rank/line agrees; identical sparse sequences confirm printed gaps. Conflicts, unavailable reports, changed directories, repeated ranks and inconsistent pagination retain the last successful snapshot. Extra report reads share the existing 180-second and 32 MB limits. Global exports are read before individual selection so they retain their original scope. Encrypted snapshots now retain printed ranks alongside ordered line numbers; prediction labels and submitted-choice tables preserve those numbers, with backward-compatible handling of older snapshots. Updated the README lifecycle and troubleshooting guidance.

Problem addressed: Queueing removed the web-request wait but retained an assumption that each employee's printed choices must be exactly 1 through N. The supplied FO frame has consecutive ranks on page 1 of 116 and does not identify the later failing group. The new verification handles both omitted global rows and independently confirmed sparse choices without assuming which caused the live failure.

Validation: The focused report/queue/prediction/employee integration run passed all 67 cases (431 assertions), including recovery, confirmed gaps/blank first slot, individual pagination, repeated line choices, rejected inconsistencies, unchanged snapshots, source-rank display, legacy snapshots and an anonymous fixture matching the supplied nested frame/fixed-header structure. The final frame/export checks passed both cases (7 assertions), bringing distinct passing coverage to 68 PHP cases. Final Pint, whitespace checks and one Larastan pass (zero errors) passed. No real credentials, names or submitted choices from the supplied frame were saved in fixtures. Debugbar had no captured protected refresh requests. No full suite or agent-performed live portal login was run; a fresh live attempt is needed after restarting the long-running worker.

Commit message: `fix: verify and recover gaps in shared portal bid ranks`

## [x] Completed: Document Artisan commands, jobs, workers and queues

Goal: Provide the application's command and background-process operating reference in README.md.

Current implementation (2026-11-16): Added all nine application-defined Artisan commands with arguments/options, requirements, outcomes and examples; an index of all 208 registered development-installation commands; the complete application job/message and queue inventory; exact default, broadcasts and shared-bid worker commands; timeout/reservation and credential-expiry behavior; development process groups, production supervision/restarts, pause/resume, failure recovery, UTC scheduler frequencies and conditions, and troubleshooting. Documented retained account/system email and portal-only K4 workflows. Replaced unsupported AeroDataBox examples and corrected test commands to use the base test class's required in-memory SQLite environment. Configuration refresh guidance preserves the distinction between configuration caches and broader application-cache clearing.

Problem addressed: The README omitted the dedicated bid worker, broadcasts queue, custom command options, schedule conditions and background-job recovery steps, leaving operators without a complete startup/runbook reference.

Validation: Compared the README with application command signatures, job/message classes, configuration, development registration and scheduler source. Inspected Artisan's installed command registry, help output and schedule:list through Sail without executing imports, cleanup, mail sends or queued work. Verified all 208 visible command names and documented Artisan examples against that registry. Whitespace checks passed. Documentation-only changes; tests, Pint and Larastan were not needed. Existing implementation changes remain untouched.

Commit message: `docs: document Artisan commands jobs queues and worker operations`

## [x] Completed: Queue shared-bid refreshes and portal exports

Goal: Return immediately when updating shared bids, with progress and failure feedback while Kalitta is read in the background.

Current implementation (2026-11-16): Refresh and export requests dispatch an encrypted job to the dedicated database-backed crew-bid-refreshes connection/queue. The worker consumes a temporary encrypted login reference, rechecks account/package access and package identity, reads and validates the complete report, and replaces the shared snapshot only after report validation. Credentials are removed when consumed, on dispatch/worker failure, or expire after ten minutes. Per-context locks prevent duplicate queued/running logins; one attempt, a 240-second timeout and a 300-second reservation prevent unintended replay. Authenticated status polling updates only progress text, with deliberate View updated prediction and private PDF/Excel download actions. Exports expire after 15 minutes. Both Artisan dev and Composer dev register the dedicated worker.

Problem addressed: The web request waited for every portal page and optional export. Missing choice ranks blocked the request, although the last good snapshot was already protected. That validation remains enforced and now appears as a background failure with a retry action. A queued entry created during implementation lacked newer lock metadata; compatibility handling now rejects it safely, discards its temporary login, and allows a fresh attempt.

Validation: Forty distinct focused queue, employee-page and shared-report PHP cases passed across the integration and correction runs. The final queued-refresh file passed all 11 cases (110 assertions), including a real database queue payload/decryption/execution round trip, missing ranks preserving the previous snapshot, privacy/ownership, access and package changes, expired credentials, deduplication, dispatch failure and legacy metadata. All 71 focused JavaScript checks passed, including progress updates without navigation, network recovery and cleanup. Pint, Vite build, whitespace checks and the single Larastan pass (zero errors) passed. No full suite, agent-performed live portal login or production deployment was run.

Operations: Start the worker with `vendor/bin/sail artisan queue:work crew-bid-refreshes --queue=crew-bid-refreshes --tries=1 --timeout=240`. Existing long-running workers must be restarted after code changes. Failed attempts require a fresh form submission because their temporary credentials are discarded.

Commit message: `feat: queue shared bid refreshes with private progress and exports`

## [x] Completed: Pause bid refreshes while overlays are open

Goal: Preserve open dialogs, drawers and menus during automatic updates and provide an action for stale predictions.

Current implementation (2026-11-16): Cancel automatic Livewire polls while overlays, pending saves or unsaved changes are present. Manual actions remain available. Stale prediction dialogs now offer Update submitted bids, opening the existing shared-refresh credential form.

Problem addressed: Automatic refreshes interrupted overlays, and stale predictions offered no direct refresh action.

Validation: Sail passed 14 focused JavaScript checks, 13 prediction tests (74 assertions), and the existing shared-refresh integration case (32 assertions). Pint, the Vite build and one Larastan pass (zero errors) passed. No full suite or manual browser review was run.

Commit message: `fix: pause bid refreshes behind overlays and expose stale refresh action`

## [x] Completed: Use portal-only K4 bid workflows

Goal: Use direct portal documents and authenticated reports for K4 bid ingestion and verification while retaining account email, as clarified by the user.

Current implementation (2026-11-16): Reviewed the existing direct downloader, scheduled import, admin fetch and submission read-back paths. Removed the unused bid-email receipt parser and its obsolete tests, replaced the submission screen's email-confirmation instruction with portal-report and award guidance, and added assertions that package publication/revision handling and bid submission send no mail or notifications. Rewrote CREW_BIDS.md around portal manifests, fetch history, document validation, private staging and in-app operational notices. Removed the Outlook/forwarding/Graph/provider intake plan and email-receipt verification dependency. Existing account verification/password-reset behavior remains available.

Problem addressed: The backlog and submitted-bid screen directed core bid workflows toward company email despite the implemented portal downloader and authenticated report reader.

Validation: An earlier Sail test request was blocked by automatic approval review because its usage limit was exhausted. After access recovered, all six focused portal-download/publication/submission/reconciliation and account-email checks passed in guarded in-memory SQLite (75 assertions). The code changes had already been integrated into the working baseline; this completion record preserves the user's subsequent credential-storage backlog addition.

Final checks: Pint and one Larastan pass completed with zero errors.

Commit message: `refactor: use portal-only K4 bid ingestion and verification`

## [x] Completed: My bid list: multi select / bulk actions

Goal: Add checkbox selection and bulk removal/reordering to the My bid list drawer with persistent selection and controls that stay visible while scrolling.

Current implementation (2026-11-16): Added an Edit Mode/Done toggle that replaces drag grips and individual move/remove controls with labelled checkboxes and disables sortable initialization for that mode. A toolbar outside the scrolling list shows an immediate selection count, Select all/Deselect all, Move to top, Move to bottom and confirmed Remove selected actions. Nonadjacent choices move together in their current list order and remain checked for follow-up moves. Removed rows leave both the list and its checked selection; other removal/clear paths prune stale checks. Done, saved reload and package-context changes reset edit selection. Bulk changes use one existing optimistic workspace save, preserving preferences and other users' lists; validation rejects invalid, duplicate and out-of-list IDs. Save failures retain local edits, moved-row checks and Retry save behavior, with errors visible inside the drawer. Copy/submission loading guards cover bulk saves. Existing light/dark styles, focus affordances and transitions are preserved. Removed this completed section from CREW_BIDS.md.

Problem addressed: Reorganizing or removing multiple choices required repeated individual actions, and the drawer had no checked selection or persistent bulk-action toolbar.

Footer refinement: Moved Sync from K4, Add all matching lines and Clear bid list out of the scrolling content into a secondary More actions dropdown to the right of Review / Copy (or Copy numbered list / Retry save). The menu opens above the footer, supports keyboard focus and Escape dismissal without closing the drawer, closes after local actions and resets when the drawer closes. Mobile uses a compact labelled ellipsis button; larger screens also show More actions text.

Validation: Elevated Sail was available. All 21 bulk-action tests passed (147 assertions), plus seven existing reorder/append/undo/conflict/status cases (65 assertions). All seven focused JavaScript checks passed, including local selection counts and footer-menu dismissal. The initial unavailable arrow-icon names were replaced with installed Heroicons and Edit Mode rendering passed. The single Larastan pass reported one redundant is_string check; removed it while preserving strict list-membership filtering, then all nine affected validation/removal/retry tests passed (68 assertions). Static analysis was not rerun, honoring the one-pass rule. The footer render check passed after relocation (20 assertions); final Pint, the production Vite build and whitespace checks passed. No full suite or manual browser/touch review was performed. The unrelated FEATURES_END_TO_END.md file was left untouched.

Commit message: `feat: add bid list bulk actions and footer menu`

## [x] Completed: Sort options: Add My Bid List

Goal: Add My Bid List immediately above Submitted bid order and display saved choices in their manual order in the workspace.

Current implementation (2026-11-16): Added the My Bid List sort option. As confirmed by the user, matching saved choices appear first in manual rank order, followed by other matching lines in natural Line ID order. Empty lists use Line ID order. Existing filters, tabs and Selected only remain independent; filtered choices keep their original ranks. The sort follows manual reordering and the active package workspace without changing saved choices, preferences or submission records. Removed the completed section from CREW_BIDS.md while preserving the user's prior removal of the Refactor My bid list section.

Problem addressed: The workspace could sort by published values or confirmed submitted order but could not follow the user's current private bid-list order.

Validation: All 20 focused sort, page-data and goal-validation cases passed through elevated Sail using guarded in-memory SQLite (85 assertions). Coverage includes independent local/submitted ordering, unchanged saved choices, natural ordering of unselected lines, filters/tabs, retained ranks, manual moves, cleared lists and package-context changes. Pint, the single Larastan pass (zero errors) and whitespace checks passed. No full suite, manual browser review or frontend build was needed; existing enum-driven Blade rendering exposes the new option without asset changes.

Commit message: `feat: sort crew bid lines by My Bid List order`

## [x] Completed: Sync from K4 to My Bid list

Goal: Pull already submitted K4 bids into My bid list, warn before overwriting existing choices, and give the drawer's More actions controls a consistent presentation.

Current implementation (2026-11-16): Added Sync from K4 alongside matching full-width, icon-labelled Add all matching lines and Clear bid list controls. Sync opens a protected native credential form with package/seat context and a required replacement checkbox for populated lists. It reuses the authenticated Submitted Bid Lines PDF reader, verifies stable portal context, maps every report number to one eligible package line, and saves the complete original order through the existing optimistic workspace save. Preferences remain intact; mismatches, missing/ambiguous lines, failed reports and concurrent workspace/package changes preserve saved choices. Credentials are discarded and never bound to Livewire state or flashed as input. Reading works with a closed bid window or disabled outbound submission feature. Successful sync reopens the imported package/seat in the list drawer with a status message. Removed this completed section from CREW_BIDS.md.

Problem addressed: Users could submit their local choices but could not populate or replace a private list from their existing K4 submission; the existing secondary actions lacked a consistent layout.

Validation: Elevated Sail was available. The focused integration pass passed 66 PHP tests with one unavailable private-report fixture skipped (321 assertions); the final strengthened sync file passed all 24 cases (149 assertions), bringing distinct passing PHP coverage to 67 cases. Coverage includes report order differing from form drafts, new/empty/populated lists, replacement confirmation, preserved preferences, seat restoration, context/line rejection, failed reads, access restrictions and concurrent edits. All 13 focused drawer/status/focus JavaScript checks, Pint, the production Vite build and whitespace checks passed. The user confirmed live sync works after an initial unsupported-Bid-Sheet response; that initial response's cause was not established. The Debugbar troubleshooting check found no captured sync requests; the protected credential routes disable profiling. No full suite or agent-performed live portal login was run.

Static analysis: The single final Larastan pass completed with zero errors.

Commit message: `feat: sync submitted K4 bids into My bid list`

## [x] Completed: Remove implemented work from CREW_BIDS.md

Goal: Keep CREW_BIDS.md focused on remaining work without repeating completed feature plans and implementation history.

Current implementation (2026-11-16): Consolidated the document around remaining advanced comparisons, email intake/import operations, live submission verification, award discovery, unresolved compensation/scheduling rules and deferred enhancements. Removed the completed prediction, employee/seniority, access/admin, manual package-management, workspace/preferences, scheduled portal adapter, estimated-pay implementation and submitted-order-sort plans. Preserved approved comparison definitions, source completeness and privacy constraints, rate/compensation assumptions, CBA clause/page references, explicit unsupported cases and manual verification gaps. Renumbered remaining comparison tasks and updated their dependencies. At the user's explicit request, the restored My bid list section remains with its actual implementation outcome; all other completion history points to TODO.md.

Problem addressed: Implemented features appeared as unfinished work, stale focus headings conflicted with the current application, and repeated plans obscured remaining requirements.

Validation: Compared implementation claims with the current Blade/Livewire header, pay services, source parser and recorded TODO outcomes. Reviewed remaining sections and dependency references, and checked the diff for whitespace errors. This documentation cleanup required no additional application tests, build, Pint or Larastan pass. The preceding layout task records its focused verification. Unrelated working-tree changes were preserved.

Commit message: `docs: remove completed crew bid plans and retain remaining work`

## [x] Completed: Refactor My bid list

Goal: Keep My bid list and its attached cloud/status dropdown at the right edge of the header, immediately after the Captain/First Officer preference link.

Current implementation (2026-11-16): Restored the requested section in CREW_BIDS.md and implemented three independent command-bar groups. The title/package/prediction context flexes on desktop and occupies its own row below the large breakpoint. The Captain/First Officer preference link is now a separate middle control immediately before an independently end-aligned My bid list group containing its notice and attached cloud/status dropdown. Existing button/link styles, responsive spacing, Alpine triggers, focus handling, Livewire state and sticky-header measurement are preserved.

Problem addressed: Source order alone did not implement the requested separation of the middle seat control from the far-right bid-list action group. The earlier verification-only completion was premature; the Blade structure now implements that separation.

Validation: Elevated Sail was available. After the actual layout change, all three focused seat/empty-package/bid-order PHP cases passed (24 assertions) using explicit testing variables and guarded in-memory SQLite; all 10 focused header/status/drawer-focus/sticky-offset JavaScript cases passed. Pint, the production Vite build, the single Larastan pass (zero errors), and whitespace checks passed. No new tests were added for this layout-only change. Manual browser/touch review was not performed. Unrelated portal diagnostics, tests and documentation edits were preserved.

Commit message: `ui: separate the seat control from right-aligned bid list actions`

## [x] Completed: Clarify shared bid refresh context failures

Goal: Make shared-bid refresh failures actionable while preserving the last successful snapshot, and confirm a successful live refresh.

Current implementation (2026-11-16): Replaced the combined unsupported-account/context message with specific static errors for a returned login page, missing signed-in account, missing package table, unsupported/missing CA/FO position and unsupported/missing 747/777 fleet. Existing account/package verification remains enforced. Added parser regressions and a refresh endpoint regression proving that a login page produces actionable feedback, retains the prior encrypted snapshot, does not flash credentials and never proceeds to View All. The supplied private saved Bid Sheet contains all expected labels, so it does not explain the live failure. Protected refresh requests deliberately have no Debugbar records. No live portal login, credential capture or local application database write was performed.

Problem addressed: The same generic error represented several unrelated failures, concealing whether the portal had returned a login page or an unsupported Bid Sheet. The user confirmed the browser shows an open December 2026 package (4219, 777, revision 0, 102 lines). On retry, the user observed “Reading the complete global report…” followed by “Shared bids updated for all K4 Bids users.” The live refresh now succeeds; the earlier failure's exact cause was not established, so the diagnostic change is not attributed as its proven fix.

Validation: Eight targeted regressions reproduced the generic diagnostic before the change. The focused portal parser, snapshot, employee endpoint and report pass verified 110 cases; its new snapshot-preservation test initially compared newly created and reloaded attribute ordering, then passed in isolation after using a reloaded baseline (one test, seven assertions). Pint, one Larastan pass (zero errors), and whitespace checks passed. All database-backed tests used explicit testing variables and guarded in-memory SQLite. The user confirmed a successful authenticated live refresh on 2026-11-16. No full suite or agent-performed live portal login was run. Closing this task changed documentation only; no checks were repeated.

Commit message: `fix: explain portal context failures during shared bid refresh`

## [x] Completed: Bid prediction UI cleanup

Goal: Move bid predictions into a compact header pill and modal, and replace bulky per-line projection warnings with accessible availability colors.

Current implementation (2026-11-16): Added a compact chart-icon header pill showing `F56 · Likely hold`, `Unavailable` or `No hold`, with an amber micro-dot for stale shared bids. The full prediction name and stale explanation remain in its accessible text and hover title. The pill uses subtle borders, a rounded shape and a slate-800 dark background. Alpine opens the existing native-dialog pattern with Escape/backdrop/close dismissal and trigger focus restoration. The modal retains source dates, seniority, prediction basis, limitations and employee/shared-data access, and adds remaining/capacity and senior-hold counts for all package lines. The visible header pill preserves 60-second shared-data polling. Following the requested accent-stripe refinement, line cards and their sticky headings keep neutral backgrounds (white/light, slate-900/dark), with a 4px emerald stripe for available lines and amber for likely holds or one remaining position out of multiple. Unavailable lines use slate-600 stripes and 60% opacity, returning to full opacity on hover or keyboard focus; the modal key uses the same palette. Compact labelled icons and hover descriptions preserve non-color status and capacity information. Missing or ineligible predictions remain neutral. Private order and prediction calculations are unchanged.

Problem addressed: The separate prediction panel and repeated warning paragraphs consumed browsing space and obscured line comparisons.

Validation: All 12 focused prediction tests passed (68 assertions), including modal placement, capacity breakdown, removed warning paragraphs, labelled status icons, missing-link/stale feedback and preserved private state. All 57 crew-bids JavaScript regressions, Pint, the production Vite build, the single Larastan pass (zero errors) and whitespace checks passed. Database-backed tests used explicit testing variables and guarded in-memory SQLite. No full suite or manual browser/touch review was performed. Existing documentation changes were preserved.

Refinement validation: The accent-stripe and compact-pill refinements each passed the 12 focused prediction tests (68 assertions), Pint, the production Vite build and whitespace checks. These presentation-only refinements added no tests and reused the task's completed single Larastan pass. The compact pill retains the same modal trigger, visible polling and accessible stale-data explanation. Existing staged changes were preserved.

Commit message: `ui: move bid predictions into a header modal with line accent stripes`

## [x] Completed: Manage seniority and shared bids in Filament

Goal: Give admins Filament controls for manual seniority imports, roster and shared snapshot inspection, verified employee-link management, and explicitly authorized user/admin role changes.

Current implementation (2026-11-16): Added Pilot Seniority Lists and Shared Bid Snapshots resources under K4 Bids. Admins can manually import a supplied seniority PDF through the existing validated action, inspect dated source metadata, and search/paginate imported employee numbers, names and ranks. Snapshot pages filter month/fleet/seat, label stale data, retain original submitted choice order and link to the protected refresh page for a matching published package. Roster/snapshot records have no manual create/edit/delete path. Users now shows role, verified employee number and verification time; identity fields remain read-only and a confirmed Unlink employee number action clears only that user's identity link, with immediate form feedback. The explicitly authorized User/Admin selector allows promotion and demotion of other users while preserving separate K4 Bids grants. Self-role changes, super-admin promotion and changes to existing super-admin roles are blocked. Existing email-verification and active/grant controls remain available.

Problem addressed: Seniority and shared bid administration previously required the crew workspace; role changes required commands. Admins now have dedicated resource screens while portal login remains necessary to verify employee ownership. Shared source data and private bid lists are preserved when unlinking identities or changing roles.

Validation: All 27 focused Filament resource/user cases passed (246 assertions), plus seven focused login and user/panel authorization regressions (39 assertions). Coverage includes import success/failure feedback, malformed input, roster search and actual pagination, snapshot filtering/choice order/refresh context, escaped source names, guest/regular/inactive/unverified access rejection, protected role boundaries, promotion/demotion access behavior, disabled identity fields and targeted unlink preservation. Pint, the single Larastan pass (zero errors), and whitespace checks passed. All database-backed tests used explicit testing variables, guarded in-memory SQLite and factory records. No migrations, imports, account changes or other local database writes were executed. No full suite, frontend asset changes or manual browser review was performed. Concurrent edits, staging and commits were preserved.

Commit message: `feat: manage crew bid seniority and access in Filament`

## [x] Completed: Import seniority and personalize bid predictions

Goal: Manually ingest a supplied seniority PDF link, associate each account with its verified employee number, and show predictions only for that account without a name selector or long per-line bidder expansion.

Current implementation (2026-11-16): Added the admin-only Import seniority PDF action under Manage package, accepting a supplied direct Kalitta PDF link with bounded HTTPS downloads and no redirects. The supplied July 15, 2026 PDF parsed all 987 pilots; all 205 employee IDs in the supplied View All capture join to that roster. Only employee number, name and seniority persist in an encrypted shared current roster with dated provenance and import attribution. Identical imports are unchanged; older lists, invalid rows and failed downloads preserve the previous roster. A one-time read-only portal login verifies the current account's employee number before saving an encrypted, serialization-hidden link. Predictions use that saved identity and imported ranks automatically; missing roster coverage blocks projections explicitly. Both name selectors and the per-line bidder accordion are removed. Line cards show compact personal availability/remaining capacity and the likely hold. The global choices dataset and exports remain shared, while private lists and submission status remain separate. CREW_BIDS.md records the operating flow and limitations.

Problem: A manually selected person can differ from the logged-in user, and large bidder lists obscure the user's own outcome. Do not infer account ownership from a name match or retain dates of birth and other unrelated PDF columns.

Validation: The focused login, authorization, import, account-link and prediction pass passed 49 tests (257 assertions). The focused authentication-directory, portal reader/snapshot and user-model pass passed 89 tests (310 assertions), including overlapping authentication cases. Coverage includes app login/logout with a retained employee link, invalid app passwords, rejected/expired portal logins, mismatched authenticated accounts, guest/unverified/inactive/ungranted access, safe URL validation, complete multi-page parsing, atomic import preservation, and URL target isolation. Pint and the production Vite build passed. The single Larastan run found one impossible intersection of closed array shapes in the new return annotation; it was replaced with the concrete returned shape without a second analysis run, following AGENTS.md. No full suite or manual browser review was run. Concurrent edits and staging were retained. Temporary PDF/text inspection downloads were removed.

Local database incident: The initial `sail artisan test --env=testing` invocation retained the local `.env` database setting and reset the development SQLite database through RefreshDatabase. This caused the reported logout and missing account. The user was informed immediately after confirmation. PHPUnit now forces test environment/database values, and TestCase refuses application setup before database refresh unless it uses testing plus in-memory SQLite. A repeat unsafe invocation was blocked by this guard; subsequent focused runs explicitly passed the isolated environment. The overwritten database was preserved privately, and one workspace's encrypted selections/preferences/context was recovered into a private manifest from existing profiling records. No password hash or full account/database backup was found. The user declined account recreation and restoration; no recovery, initial seniority import or further database rollout was applied. The user created a new Filament account and was provided the email-verification Artisan command. No passwords, tokens or personal recovery values were added to committed files.

Commit message: `feat: import pilot seniority and personalize bid predictions`

## [x] Completed: View other employees bids

Goal: Share the global ViewAllBids dataset with every authorized K4 Bids user and predict unavailable lines and the likely hold from seniority, ordered choices and CA/FO capacity.

Current implementation (2026-11-15): Updated the documented contract after the user confirmed global sharing, lower-number seniority priority and CA/FO crew counts as capacity. A complete View All read publishes an encrypted shared snapshot per month/fleet/position; failed refreshes retain the previous dataset. The report reader supports the captured 84-page report, empty-choice employee groups and page continuations. Original choice ranks and repeated line numbers are retained. Counts show unique bidders and their first choice rank for that line. Shared reads and bidder selection require no portal login; explicit refreshes and verified PDF/Excel exports use discarded operation-local credentials. The workspace checks shared state every 60 seconds while visible, exposes per-line demand/projected senior holders/remaining positions, and models the chosen bidder's seniority against the app user's own order (or the chosen bidder's submitted order when the local list is empty). Private workspaces, preferences, submission status and BidSubmission records stay unchanged.

Problem addressed: The isolated employee viewer could not share global bids or estimate line availability. Projections now allocate each senior bidder's highest available choice once, accounting for multiple positions and choice cascades; listing a line alone does not consume its capacity. Unknown/ambiguous senior choices stop personalized projections, and stale snapshots are labelled. Results are estimates, not official awards. Refreshing uses read-only account/package context instead of requiring personal bid-entry fields. Selector posts use verified control names and a transport allowlist; unexecuted browser handler text supplies no actions or arguments. The portal's report-area and export rendering sessions may differ while their widget/control and report stack match. The captured report does not print its own month/fleet; authenticated context is checked before/after and local/portal revision schemes remain distinct.

Validation: The focused integration pass covered 106 cases (495 assertions); the final snapshot/prediction/controller pass covered 37 cases (208 assertions). Production build and Pint passed. The single Larastan run reported four redundant/nullsafe or DOM-loop inference findings; the nullsafe access was simplified and report rows now cross a typed collection boundary. Analysis was not repeated under the one-run rule. The supplied unselected, selected-employee and View All captures pass page/control discovery without copying personal values or live tokens into fixtures. Automated tests use isolated HTTP fakes and in-memory SQLite. Credential routes remain excluded from Debugbar; the protected-route issue search returned no stored requests. No full suite or browser review was run.

Commit message: `feat: share global crew bids and project seniority-based line holds`

## Completed: Integrate save status into My bid list

Completed (2026-11-15): Goal: Save header space while keeping workspace save status discoverable. Current implementation: Replaced the standalone header Saved badge with an indicator before the cart icon inside My bid list. Confirmed saves show an emerald check; local preference edits, requests and failed saves show an amber outline; workspaces without a saved revision show a neutral outline. The hover tooltip and accessible status describe the actual last saved timestamp, refreshed on hover/focus and successful responses. Confirmed writes update the locked timestamp; reloads restore it and package changes reset it appropriately. Existing list opening and focus restoration remain intact. Problem addressed: A separate text badge consumed toolbar space and did not reflect pending local preference changes.

Validation: Elevated Sail was available. All five focused PHP save/preferences/order/conflict tests passed (53 assertions), and the timestamp regression passed again after formatting (21 assertions). All 48 crew-bid JavaScript tests and the production Vite build passed. Pint passed. The single Larastan pass reported two redundant null-safe accesses in the new timestamp assignment; both were corrected without a second analysis run, following AGENTS.md. Unrelated working-tree edits and staging were preserved. No full suite or manual browser review was run.

Commit message: `ui: integrate save status into bid list button`

## Completed: Move Pay adjustments into the user menu

Completed (2026-11-15): Goal: Treat pay adjustments as account preferences and free workspace toolbar space for bidding and filtering. Current implementation: Moved Pay adjustments beside Profile in the desktop user dropdown and mobile user settings menu for users with K4 Bids access. With an active bid package, the menu opens the existing pay editor without navigating away, closes the menu and focuses its trigger for dialog focus restoration. Elsewhere, or without a package, the link opens the K4 Bids profile settings. Modified clicks retain native link behavior. Removed the workspace toolbar entry and updated the dialog's fallback focus target. Problem addressed: Profile-level pay controls cluttered the browsing toolbar.

Validation: Elevated Sail was available. All 24 focused pay, theme and authorized navigation tests passed (118 assertions) using isolated in-memory SQLite after the initial run encountered concurrent changes to the shared test database. All 36 crew-bid JavaScript tests passed, including nine new menu behavior cases. Pint, the production Vite build, the single Larastan pass and whitespace checks passed. Unrelated staged and ongoing edits were preserved. No full suite or manual browser/touch review was run.

Commit message: `ui: move pay adjustments into the user menu`

## Crew bidding

Sort and goal enums completed (2026-11-15): Replaced string sort modes with the string-backed CrewBidSort enum and introduced CrewBidGoal for ordered preferences. The browsing criteria and matcher now accept typed values, with exhaustive sort handling, enum-derived labels and enum validation. Livewire inputs and persisted preferences retain their existing string values. Invalid client inputs still render validation feedback. Preserved concurrent saved-indicator work and staging.

Validation: Elevated Sail was available. All 73 focused PHP tests passed (383 assertions); the 12 new enum/validation cases passed again after formatting (39 assertions). All 48 crew-bid JavaScript tests, Pint, the production Vite build and whitespace checks passed. The single Larastan run reported two nullsafe-access warnings in concurrently edited saved-timestamp code and no enum findings. Those warnings were corrected concurrently; analysis was not rerun under the once-per-task rule. No full suite or manual browser review was run.

Commit message: `refactor: type crew bid sorts and goals with enums`

Maximize pay goal completed (2026-11-15): Goal: Include Maximize pay in the saved, ordered preferences alongside credit, days off and work blocks. Current implementation: All four goals can be selected, reordered and restored. Best match compares the same ready, final rounded values already shown as Estimated line value for carry-in lines or Estimated pay for other lines. The standalone pay sort and pay goal share the existing strict comparator, so lower goals only break ties and natural Line ID resolves remaining ties. Missing/incomplete estimates remain unavailable rather than using known minimums as complete totals. The goal selector explains that distinction, and Why this matched shows the pay basis/value in priority order. Manual sorts override goals as before; private ranks remain independent and bulk-add follows the displayed order. Updated the existing CREW_BIDS.md contract. Problem addressed: Saved goals rejected pay and allowed only three entries, while pay was available solely as a standalone sort.

Validation: Elevated Sail was available. New regressions first reproduced the three-goal limit and rejection of pay. All 59 focused PHP tests passed (321 assertions), covering four-goal persistence/reordering, strict primary and secondary pay priorities, natural ties, incomplete high minimums, combined carry-in value, manual-sort override, bulk-add, existing pay/default/source behavior and view models. All 36 crew-bid JavaScript tests, Pint, the production Vite build, the single Larastan pass and whitespace checks passed. Concurrent changes and staging were preserved. No full suite or manual browser/touch review was run.

Commit message: `feat: add maximize pay to ordered bid preferences`

Preferences/sort grouping and apply-on-Done completed (2026-11-15): Goal: Make the relationship between ranking preferences and the selected sort clear. Current implementation: Docked the preferences pill immediately before Sort inside one bordered control group. Best match highlights it; manual sorts immediately dim and strike through the ranking goals, including during hover, while keeping the control editable and its focus ring visible. At the user's request, there is no Paused badge. Dates off and start city remain outside the strikethrough and continue filtering lines. A tooltip and accessible status explain the distinction. The dialog explains that Done applies Best match; successful saves switch to that sort, while validation failures and workspace conflicts retain the manual sort and open dialog. Private bid ranks are preserved. Preferences remain accessible but inactive in Trips view. Problem addressed: Hover previously restored full opacity, and successful preference saves left manual sorts overriding the goals. The compact view switcher beside the count is retained.

Validation: Elevated Sail was available. The new save regression first reproduced Done leaving Highest credit selected. All seven focused PHP preference/save/validation/conflict/ranking tests passed (56 assertions). All 36 crew-bid JavaScript tests passed, including immediate dimming and strikethrough for all five manual sorts, restoration for Best match and inactive Trips state. Pint, the final production Vite build, the single Larastan pass and whitespace checks passed. Concurrent pay-menu work and staging were preserved. No full suite or manual browser/touch review was run.

Commit message: `fix: mark overridden ranking goals and apply Best match on save`

Compact view switcher completed (2026-11-15): Goal: Keep Lines as the default and make Trips/Reserve accessible without a wide view-button group beside the primary preferences. Current implementation: Replaced the three navigation buttons with a labelled native dropdown beside the matching count, placed Preferences first, and kept the count and view selector together when the toolbar wraps. The dropdown uses the existing `tab` state with immediate change updates, a loading-disabled state, keyboard support, visible focus and dark-mode styling. Trips shows its imported-trip count; Lines and Reserve retain matching/total counts. Problem addressed: The view buttons consumed prominent horizontal toolbar space.

Validation: Elevated Sail was available. All seven focused workspace/preferences/page-data tests passed (73 assertions), along with Pint, the production Vite build, the single Larastan pass and whitespace checks. This presentation change required no new tests. No full suite or manual browser/touch review was run.

Commit message: `ui: compact crew bid views beside the matching count`

Crew-bids integration completed (2026-11-15): Goal: Bring `crew-bids-pay-estimate` up to date with `crew-bids` while retaining its three pay-estimate commits. Current implementation: Merged the 14 incoming commits through `f2dc90f`, including portal submission, per-user access, package imports, drawer behavior and the position-dropdown fix. Resolved the user-model conflict by retaining both `crew_bids_access` and encrypted, serialization-hidden `crew_pay_settings`, updated cast metadata and the existing fillable expectation, and combined both branches' task outcomes without duplicating the rendering-refactor entry. Problem addressed: The pay-estimate branch had diverged from current crew-bidding functionality.

Validation: Elevated Sail was available. All 188 focused PHP integration tests passed (1,187 assertions), covering bid workspace/access, private pay/defaults/source recovery, page data, profiles, package imports, submission and view models. All 26 crew-bid JavaScript tests, Pint, the production Vite build and the single Larastan pass passed. Source whitespace checks passed; imported PDF fixtures retain their fixed-width cross-reference rows. No full suite or manual browser review was run. The merge is local; no remote push was performed.

Commit message: `Merge crew-bids into crew-bids-pay-estimate`

Published guarantee floor enforcement completed (2026-11-03): F55's verified source guarantee was correctly stored as 78:10, but the matching private scenario had custom assumptions enabled with line_guarantee=false. This excluded the published floor and displayed 64 × $166.63 = $10,664.32 despite the known 78:10 × $166.63 = $13,024.91 minimum. Saved profile contexts now always include published line Credit, and writes normalize the legacy field to true. Removed its advanced checkbox so this required minimum cannot be disabled again. Qualified/full-period and unsupported-scenario gates remain intact; missing occupied-day details still withhold a complete total. No personal scenario rows, source packages or bid ranks were rewritten.

Validation: Elevated Sail was available. A new Livewire regression first reproduced the F55 failure, then passed with the fix, exercising legacy custom exclusions, the displayed incomplete minimum, checkbox removal and an attempted false value on save. All 30 focused tests passed (131 assertions) across defaults, private pay editing/privacy/concurrency and view-model presentation. Pay-edit fixtures now explicitly include published guarantees through a factory state while existing legacy fixtures retain missing-source behavior. Pint passed, the single Larastan run passed with zero errors, and staged/unstaged whitespace checks passed. No full suite or browser review was run; frontend assets were unchanged. Updated the existing Estimated pay contract in CREW_BIDS.md.

Commit message: `fix: enforce published credit as the crew pay minimum`

Carry-in line value and included occupied-day resolution completed (2026-11-03): Implemented the user's next-month excess-day planning assumption. Outgoing carry-in pays one whole 1.5× credited-hour payment at each segment's earning/departure UTC-day rate, additional to originating-period base; passenger deadhead first credits 50% of scheduled travel, giving 0.75 paid hours per travel hour. Complete source duty groups and exact reconciliation to published pairing credit are required. Entire transition duties allocate to their receiving compensation month, including January–March exceptions and year rollover, without splitting segments or counting ordinary credit twice. Unreconciled or incomplete sources still show a known minimum rather than a fabricated complete estimate.

Carry-in headlines now say Estimated line value and combine originating-period scheduled gross with the receiving-period excess-day payment. The expanded breakdown shows those allocations separately, and Highest estimated line value sorts on the combined amount. The receiving month's unrelated line pay/guarantee is excluded. Source parser v4 preserves Show Time/Debriefing duty groups; verified enrichment upgrades legacy pay metadata without changing published payloads or bid rankings. Its repeatable included-occupied-day option records date-specific confirmations and preserves matching prior confirmations. Applied verified enrichment to all 104 November 777 lines and recorded the user's F49 November 15 confirmation that its obligation is already included in 89:00; no general waiver for other unlinked occupied days was added.

At FO step 3 ($166.63/h; adjusted longevity 2024-07-29), F83's 90:15 guarantee gives a $15,038.36 November base, with $1,812.10 December excess-day deadhead payment and $16,850.46 combined line value after final-only rounding. F49's 89:00 produces $14,830.07 without adding its already-included occupied day. Updated CREW_BIDS.md with these confirmed policies, worked values, source gates and their status as user-confirmed planning assumptions. The user confirmed the F83 guarantee is now working in the application.

Validation: Elevated Sail was available. Focused pay/default/source/display/sort/page-data integration passed 98 tests (368 assertions); the parser-inclusive focused run passed 68 tests (309 assertions), overlapping arithmetic/source coverage. Regression cases include one-hour deadhead at 0.75 pay, whole-duty allocation with different earning-day rates, January/February/year-end boundaries, duplicate/unreconciled source rejection, pre-midnight report dates, scoped occupied-day confirmations, combined sorting and monthly breakdowns without private setup. Pint passed, the single Larastan run passed with zero errors, and the production Vite build passed. Staged and unstaged whitespace checks passed. No full suite or agent browser review was run. Preserved previous changes and staging.

Commit message: `fix: calculate combined carry-in line value and resolve included duty days`

Estimated pay simplification and source recovery completed (2026-11-03): F-prefixed lines now use hard-line rules and Q-prefixed lines use reserve rules automatically. Qualified hourly Flight Pay, full-period availability and published line Credit as the pay minimum default to yes; deliberate exceptions remain under optional advanced adjustments. Compact pay is shown automatically, routine per-line review/category inputs were removed, and personal longevity/Check Airman settings remain in the profile.

Resolved repeated $10,664.32 minimums in the November 2026 package: its legacy payload lacked contractual source-credit and pairing-credit fields. Added an encrypted, serialization-hidden pay-source supplement and a recovery command that verifies exact original-file fingerprints and package identity before attaching parsed pay facts, without replacing the published payload, selections or ranks. Applied the migration and recovered all 104 lines from the matching original Lines, Trips and Overview files. With FO longevity 2024-07-29, F1 legitimately remains $10,664.32 (64 × $166.63), while F8 calculates $12,761.08 and F9 $14,399.61. Incomplete cases retain a labelled known minimum separately from a complete total; equal minimums are not artificially differentiated.

Removed repeated encrypted-payload reads, reused compensation-period/rate calculations, and deferred detailed pay presentation until its disclosure opens. The 300-line regression read the encrypted payload once and calculated in approximately 0.9 seconds; browsing/calculation for the actual 104-line package measured approximately 0.41 seconds after recovery. These are service measurements, not whole-page/browser timings.

Validation: Elevated Sail was available. The final focused integration run passed 151 tests (752 assertions), covering pay arithmetic, source verification/conflict rejection, defaults, privacy, lazy presentation, sorting and existing browsing behavior. Pint and the production Vite build passed. The single Larastan run found one redundant category `isset()` guard introduced by automatic category derivation; removed the redundant check without suppressions. Larastan was not rerun under the one-pass rule, so no clean post-correction static-analysis result is claimed. After that correction and removing the unused category-choice view-model property, all 23 focused pay validation/presentation tests passed again (98 assertions), followed by Pint. Staged and unstaged whitespace checks passed. No full suite or manual browser review was run. Preserved unrelated changes and existing staging.

Commit message: `fix: simplify and accelerate per-line pay estimates`

Moved personal pay settings to the user profile (2026-11-03): Adjusted pay-longevity date (UTC) and Check Airman override now live under Profile → User preferences → K4 Bids, stored together as encrypted, serialization-hidden user data. The owner-only profile update validates the date/flag, normalizes the checkbox to a boolean, permits clearing longevity to disable totals and preserves values when pay fields are omitted. Estimated pay reads both fields exclusively from the owning user's profile. The comparison editor shows their saved values and links to the profile; its write allow-list rejects the former longevity/override inputs. Package/seat eligibility, display options and line adjustments remain comparison-specific. Removing comparison inputs retains the two personal settings and existing bids.

The migration transfers the old encrypted longevity and consistent package Check Airman choices, removes their old copies and increments comparison revisions. Conflicting flags are retained privately and require a profile choice before complete totals are available. Rollback restores personal values into legacy encrypted settings/scenarios; unresolved conflicts recover their original per-context flags. Updated CREW_BIDS.md to reflect the new settings location and migration behavior. Preserved pre-existing feature changes and staging.

Validation: Elevated Sail was available. All 165 focused tests passed (861 assertions) across profile updates/privacy, pay editing/recalculation, migration/rollback, pay presentation, page-data assembly, workspace behavior and pure pay arithmetic. The migration is confirmed applied locally. Pint, production Vite build and staged/unstaged whitespace checks passed. The single Larastan run reported two findings because User.casts() lacked the exact encrypted-array return metadata used by sibling models; documented the actual cast map to resolve that inference, without suppressions or changing runtime casting. Larastan was not rerun, following the one-pass rule, so a clean post-correction static-analysis result is not claimed. No full suite or manual browser review was run.

Commit message: `feat: move crew pay settings into user profile`

Estimated pay implementation completed (2026-11-03): Delivered the qualified hourly Flight Pay / full-period scheduled gross USD scope. Added version/source/applicability metadata to the unchanged CA/FO rates, adjusted-longevity step resolution with anniversary/leap-day rules, UTC compensation-period boundaries, exact rational calculation with final-only half-up rounding, separate greater-of base candidates and supported additional compensation. Carried credits retain their earning/departure-day rate. Open Time flight on an SDFDO uses the confirmed whole 1.5× additional payment without a separate ordinary-credit payment. Preserved contractual source guarantee independently of adjusted comparison Credit; incomplete source coverage never falls back to a complete guarantee-only estimate.

Added an encrypted owner-only pay profile with package/revision/seat/line scenarios, validated private credit inputs, correction/removal, and optimistic concurrency protection. Complete source pairing totals are deduplicated and accepted only within covered constant-rate compensation periods; reserve/composite, boundary-crossing or incomplete cases require reviewed dated private inputs. Supported entitlements include passenger deadhead, reserve/vacation/short training, taxi-only, tested/CQ CBT, random tests, maintenance started-hour units, minimum Check Airman override and non-Check-Airman simulator instruction. Unsupported applicable branches explicitly prevent complete totals. Assignment de-duplication preserves distinct separately payable rules while excluding repeated ordinary credit and ordinary/Open Time overlap.

Added Pay context and per-line private input editing, optional compact amounts, expanded breakdowns and Highest estimated pay ordering. `CrewBidPayViewModel` prepares pay labels, component amounts and rate intervals; `CrewBidPayEditorViewModel` prepares editor choices, rows and period context. The main view retains its existing page-data factory/DTO path. Saved scenario amounts remain distinct from unsaved edits; validation, concurrency and connection failures retain drafts. Pay sorting and bulk-add share owner context; unknown values sort last with natural Line ID ties. Filters, selected bids, manual ranks and numbered-copy schema are preserved. Updated the existing Estimated pay section and rule/coverage matrix in CREW_BIDS.md, retaining the historical Sections 2/5 review and labelled user-confirmed assumptions.

Validation: Elevated Sail was available. The focused integration run passed 157 tests (872 assertions) across pay calculation/presentation/profile behavior and the affected comparison/page-data/line-view-model regressions. After the final de-duplication refinement, all 58 pay tests passed (171 assertions), and the two affected assignment tests passed again (11 assertions). This represents 158 distinct focused tests across the runs; no full suite was run. Pint, production Vite build, staged/unstaged whitespace checks passed. The single Larastan run passed with zero errors; it was not repeated after the final validation refinement. Sail migration status confirms the new private-pay table migration is applied locally. No dependencies were added. Manual browser layout, native modal/focus/keyboard interaction and network-failure recovery remain unverified. Preserved unrelated working-tree edits and existing staging.

Commit message: `feat: add private scheduled pay estimates and comparisons`

Estimated pay Sections 2/5 review completed (2026-11-03): Read the supplied CBA's complete Section 2 (printed pp. 11–15 / PDF pp. 23–27) and Section 5 including Appendix 5-A (printed pp. 25–32 / PDF pp. 37–44), comparing them with the current pay plan. Added EP1–EP8 under Estimated pay in `CREW_BIDS.md` with clause/page evidence and required implementation gates. Distinguished unverified user-confirmed longevity/anniversary/leap-day/proration/rounding assumptions from express pay branches: new-hire weekly pay/first observation Flight Pay, qualification-date pay-scale changes, off-day clause interactions, taxi/training/miscellaneous pay, overrides, entitlement-unit rules, eligibility cross-references, and biweekly deposit timing. Preserved approved scenario choices while removing the implication that product decisions alone establish CBA correctness. Documented consistent UTC/Month, published-guarantee, greater-of, and transition-allocation structure. No new compensation formula was silently substituted.

Validation: Extracted and read the local PDF pages using Sail's installed PDF parser, checked clause references and plan consistency, and ran documentation whitespace checks. This review changed documentation only; no application tests, Pint, or Larastan were required. Preserved unrelated working-tree and staged changes.

Commit message: `docs: review estimated pay plan against CBA sections 2 and 5`

Bid-list position dropdown fixed (2026-11-15): Goal: Move a choice to the selected position in My bid list and retain the saved order. Problem: Livewire 4 contextualized the dropdown's `Number(...)` call as `$wire.Number(...)`, producing a captured HTTP 500 with `MethodNotFoundException`. Current implementation: Pass `$event.target.value` directly to the existing typed, bounds-checked `move` action. Added a JavaScript regression executing the dropdown expression through the installed Livewire contextualizer, plus a feature regression proving numeric-string positions save in both directions and survive reload.

Validation: The JavaScript regression reproduced the invalid `Number` method call before the fix. Through elevated Sail, all 26 crew-bid JavaScript tests and three focused PHP tests passed (25 PHP assertions). Pint passed; the single Larastan run passed with zero errors; whitespace checks passed. No full suite or manual browser review was run. Preserved concurrent documentation changes.

Commit message: `fix: repair bid list position dropdown`

Submitted-order sort planning completed (2026-11-15): The user confirmed that the new sort should follow their last Kalitta-submitted order. Expanded the existing task in CREW_BIDS.md with the confirmed source, current code boundaries, owner/context scoping, report-match availability rules, ranked-first/natural-ID ordering, separate submitted versus editable ranks, shared bulk-add behavior and focused acceptance coverage. The initial source reuses existing matched submission records; uncaptured manual portal bids require separate read-only retrieval. Preserved unrelated document edits. This was a documentation-only checkpoint; implementation is now recorded in the completed submitted-order task below.

Commit message: `docs: plan sorting by submitted Kalitta bid order`

Reviewed Kalitta submission implemented for local testing (2026-11-15): Added Submit to Kalitta from Review bid list, native transient credential forms, a read-only comparison against a fresh Submitted Bid Lines PDF, and deliberate confirmation before one Update postback. The review binds the owning workspace revision, ordered choices, local package fingerprint/payload, authenticated account hash, portal package ID/revision/month/fleet/seat, eligibility rules, current report and relevant settings. Fresh Choice fields, Web Forms state and unrelated controls are preserved; old trailing choices are cleared. Encrypted, serialization-hidden submission reviews expire after ten minutes. Transactional claims prevent replay and supersede older reviews. An interrupted or contradictory update remains unknown and blocks another submission until a separate authenticated read-only reconciliation. A matching report is labelled as such; receipt email and award verification remain separate. No background job submits bids.

At the user's request, `CREW_BIDS_SUBMISSION_ENABLED=true` is enabled in the private local environment; the committed example/default remains false. The additive submission-table migration is confirmed applied. The supplied live table shows a December bid period and Open status, without a bid-close time zone or cutoff; the app checks fresh Open status and does not invent a deadline. The user reached the live comparison and final stale-state check. A changing `ctl00$tb_usersLoggedIn` display field reproduced their false rejection; it is now excluded from the comparison hash while its fresh value is still posted. Actual bid/settings/context changes remain guarded. Credential fields now start blank, suppress password-manager autofill, clear on page restoration and require focus before entry. Earlier comparison pages need a new comparison after this correction.

Validation: Elevated Sail was available. Focused integration passed 170 tests with 780 assertions and one existing unavailable private-report fixture skipped. The final submission/credential regression run passed all 32 tests (169 assertions), including the reproduced counter failure, fresh-state serialization, retained settings, clearing trailing choices, context/report changes, cross-owner denial, revoked access, expired/stale reviews, duplicate clicks, timeout reconciliation, unsafe destinations and credential non-retention. Pint and the Vite build passed. The single Larastan run passed with zero errors before the final one-line counter normalization and credential-markup/test correction; it was not rerun under the once-only rule. Task-scoped whitespace checks passed. No automated test contacted the live portal, and the agent sent no live bid. Live acceptance and browser autofill behavior remain for the user's controlled test. Preserved concurrent documentation edits and staging.

Commit message: `feat: add reviewed Kalitta bid submission and report reconciliation`

Auto-bidding read-only comparison and receipt parsing (2026-11-13): Discovery with `--lines` reports additions, removals, and relative reorder against visible Bid Sheet form values without sending a bid. A supplied open-window capture established the Update postback shape and visible account/period/fleet/seat/revision/status labels. Discovery checks the account label without exposing it. An offline parser restores the printed Choice order in the supplied private Submitted Bid Lines PDF and rejects ambiguous reports. The user reports that Update leaves the Bid Sheet visibly unchanged, sends a complete Bid Received email, and the report then shows the same order. A supplied email excerpt has explicit confirmation wording, expected employee/fleet/seat fields, and ranked rows; a synthetic email parser now checks that format. Neither the email nor PDF contains a bid period, and the app does not authenticate or retrieve the receipt email or report. Fresh context-bound read-back remains needed for verified submission. This remains an offline aid, not a submission preflight. Integration: 89 focused tests, 88 passed and one pre-existing private-layout smoke test skipped (186 assertions); Pint passed; one Larastan run found zero errors; `git diff --check` passed.

Commit message: `feat: inspect crew bid portal context and parse submission evidence`

Auto-bidding discovery update (2026-11-13): A user-run read-only command observed 315 editable Choice/Line slots, 43 populated slots, an enabled `btn_update` control, and a Submitted Bid Lines link. This supports an apparently open Bid Sheet but does not verify the account, bid context, deadline, submit event, acceptance, or receipt. No bid was sent. CREW_BIDS.md now records this evidence; reviewed submission remains pending authenticated read-only protocol and report discovery.

Commit message: `docs: record open-window crew bid portal discovery`

Bid list drawer closing completed (2026-11-13): The desktop workspace backdrop now closes My bid list. Escape and the visible Close control use the same close path, with focus returned to the opener or the header list button if the opener was removed. The mobile full-screen panel retains its visible close control. Updated the interaction contract in CREW_BIDS.md. Focused JavaScript checks and the Vite build passed; Larastan passed at integration. Manual browser and touch review remain outstanding.

Commit message: `fix: close bid list drawer from workspace backdrop`

Staged Larastan findings resolved (2026-11-13): Split Livewire and Mockery test chains where intermediate assertions return a different static type, and expressed the database restriction test with an expected exception. The seven affected tests passed (46 assertions); Pint and staged-file Larastan passed at integration.

Commit message: `test: fix static analysis of bid package tests`

Filament portal fetch completed (2026-11-13): Added a “Fetch from CrewBids” action beside Import package on the Bid Packages list. An authorized admin can choose a bid month and B747/B777 fleet; the action runs the existing portal importer and reports published, held revision, unchanged, missing, or failed outcomes. Invalid month/fleet values are rejected before download. Four focused Filament tests passed (40 assertions); Pint and Larastan passed at integration.

Commit message: `feat: fetch portal bid packages from Filament`

Direct portal bid-package imports implemented (2026-11-13): Updated the automated-import plan in CREW_BIDS.md from email-first to the verified direct `/hyperlinks/` source. Added a Sail command and configurable UTC schedule for complete B747/B777 Lines, Trips, and Overview groups. The importer validates bounded HTTPS responses, parser identity, and linked-trip completeness; it uses a per-period/fleet lock and fingerprint deduplication, publishes a first complete package, and holds changed releases as drafts. The checked-in schedule is off by default; local Sail is enabled under the active admin owner, a scheduler worker is running, and `composer run dev` now starts one on future runs. A live December run left B777 unchanged and published B747 as a complete first release; the database now has one published December package for each fleet. Email intake, durable source receipts, and admin import-history/status remain later phases. Nine focused portal tests passed (35 assertions); Pint and Larastan passed at integration.

Commit message: `feat: import complete bid packages from public crew-bids links`

AI form prompt completed (2026-11-13): Review bid list now offers an AI form prompt popout with a copy button. It preserves the saved rank order, converts `F`/`Q`/`R` line IDs to numeric portal values, and withholds the prompt for unavailable, invalid, or repeated portal numbers. The existing numbered-list copy remains available; neither action submits a bid. Four focused PHP tests (44 assertions) and 23 focused JavaScript tests passed. Pint, Larastan (zero errors), and the Vite build passed.

Commit message: `feat: add AI form prompt to bid review`

December B777 portal retrieval completed (2026-11-13): Downloaded the public `Lines.pdf`, `Overview.xlsx`, and `Trips.pdf` links supplied for December 2026 into `storage/app/private/Bid_Packages`. All three returned HTTP 200 with expected document types; the existing focused Filament import test parsed them as one December 777 draft with complete source coverage (1 test, 12 assertions). This was a direct, manual download: the existing portal command only discovers the Bid Sheet, and no package downloader or automatic import was enabled. The running application's packages were not changed.

Commit message: `docs: record December 777 portal retrieval`

Branch integration completed (2026-11-13): Merged `origin/crew-bids` into `crew-bids`, retaining the local typed browsing refactor and dated Overview fix alongside the remote published-carry and per-user access changes. Resolved the TODO-only conflict by retaining the remote cleanup and concise local outcomes. Merge commit `0297a0e` contains both histories; the branch is no longer behind its remote. Preserved existing stashes and concurrent staging.

Validation: Elevated Sail was available. All 145 focused tests passed (1,177 assertions), covering imports including the supplied December files, package management, page-data assembly, browsing, carry precedence and user access. Pint passed, the single Larastan run passed with zero errors, and whitespace checks passed. No full suite or browser review was run.

Commit message: `docs: record crew-bids merge verification`

Dated Overview worksheet import completed (2026-11-13): The December B777 worksheet is named `777 December 2026 Overview`. The reader now accepts an optional month/year between either supported fleet and `Overview`, preserving existing names and cross-document validation. All 37 focused import/package-management tests passed (372 assertions), including the three supplied December files; Pint and the single Larastan run passed. Original documents remain unchanged.

Commit message: `fix: accept dated bid overview worksheet names`

CrewBids rendering refactor completed (2026-11-03): Added typed browsing/page-data DTOs, extracted `BrowseBidLines` and `CompareBidPackageRevision`, and introduced `CrewBidsPageDataFactory`. The Livewire component delegates browsing and page assembly while preserving filters, private rankings, eligibility and revision confirmation. The original focused integration passed 100 tests (704 assertions), Pint and Larastan.

Commit message: `refactor: extract crew bid browsing and typed page data`

### Goal

Prepare the K4 Bids branch for a pull request while keeping published package values, private bid lists, and user-visible source labels consistent.

### Current implementation

The branch includes the B747/B777 CA/FO workspace, private ranked lists and preferences, source imports, grouped browsing filters, read-only portal discovery, and Filament package management. The carry badge now uses a published carry count first, including zero; when it is absent, complete linked trips can supply a labelled calculated count of occupied UTC dates after month-end. The detailed product contract and remaining advanced comparison tasks live in CREW_BIDS.md.

### Problem addressed

The prior carry badge could replace a published value with a calculated value and did not identify its basis. Historical crew bidding outcomes also obscured the current PR scope in this file.

Outcome (2026-11-10): Corrected carry precedence and source labels without changing saved package payloads or bid ranks. Consolidated completed crew bidding notes below; the existing commits retain implementation history. The remaining duty/carry comparison filters, source discrepancy indicators, portal submission, and manual browser review are separate work.

Commit message: fix: prefer published carry counts in crew bids

## Unified upload
Currently: 2 tabs have 2 different upload points, user has to choose
Goal: Have one unified upload path. Service will determine if a schedule or flight plan has been uploaded.

Cached results: Keep extract schedule and flight plan brief tabs for now. There's not really a better way to render cached results for now.

## Schedule: Max file (screenshot) upload to config
Have setting in .env file as opposed to hard coded.
Currently:
max:5 set in validation
message validation: 'files.max' => 'You may upload up to five images.',

Goal:


References:
.env.example
app/Validation/ExtractValidationRules.php

## Plan: PEST architechure tests

### Goal

Add fast architecture tests for established naming conventions and dependency boundaries. Keep the existing PHPUnit unit and feature classes, and catch structural regressions without booting Laravel, accessing the database, or making network requests.

### Current implementation

- `composer.json` requires PHPUnit `^12.5.12`; the installed version is 12.5.34. Pest and its architecture plugin are not installed. The existing Composer allowance for `pestphp/pest-plugin` permits plugin execution but does not install Pest.
- `phpunit.xml` discovers `tests/Unit` and `tests/Feature`. CI runs `php artisan test --compact --parallel`; the installed Collision test command switches to Pest when Pest is available. Sail already supports `vendor/bin/sail pest`.
- The application has Actions, DTOs, Enums, Mappers, ValueObjects, domain services, infrastructure services, and View Models/Presenters. Existing model-convention and Eloquent-guardrail tests verify runtime behavior; they do not enforce namespace dependencies.
- Naming has intentional variations: Actions expose `handle()`, DTOs include `Flight`, `DutyEvent`, `AirportResolution`, and the abstract `ExtractedEventDTO`; View Models include a factory and `FlightPlanPageData`. DTOs are not uniformly final/readonly, while current ValueObjects are final/readonly.

### Problem

Naming and layer boundaries can drift without a test failure. A blanket preset would impose conventions the project does not follow, and overly broad dependency bans would reject legitimate PDF/OCR, airport lookup, and presentation behavior.

`AGENTS.md` currently says all tests must be PHPUnit classes and Pest tests must be converted. Its dependency rule also requires approval before adding packages. This planning task does not authorize installation or change those rules.

### Planning outcome: installation and coexistence

- **Does Pest need to be installed?** Yes, to use Pest's `arch()` API. For this PHPUnit 12 project, evaluate `pestphp/pest:^4`; its architecture plugin is included as a dependency, so a separate architecture-plugin requirement is unnecessary. Generic installation instructions now describe Pest 5; do not adopt that major without reviewing its PHPUnit and PHP requirements. [Pest installation](https://pestphp.com/docs/installation), [Pest 4 dependency metadata](https://github.com/pestphp/pest/blob/4.x/composer.json).
- **Can it run alongside the existing suite?** Yes. Pest builds on PHPUnit and can run existing PHPUnit classes; conversion is unnecessary. Use Pest for architecture tests and retain current behavioral test classes. Verify discovery and the existing parallel runner after installation. Plain PHPUnit is not the runner for Pest `arch()` files. [PHPUnit migration guide](https://pestphp.com/docs/migrating-from-phpunit-guide).
- **Version resolution needs review.** The inspected Pest 4 branch restricts its PHPUnit patch version to 12.5.33, below the installed 12.5.34. Released package constraints may differ. Inspect a Composer dry run and review any proposed downgrade or other package changes before installation; PHP/major-version compatibility alone is insufficient.
- **Prerequisite for implementation:** explicitly authorize the development dependency and a narrow exception permitting Pest architecture tests, while retaining PHPUnit class tests elsewhere. If retaining the current rules is preferred, use PHPUnit classes for these rules instead; naming checks can use reflection, while dependency checks need reliable source analysis. Pest is optional for the architectural goal.

### Proposed implementation

1. Confirm Sail is available. After the prerequisite decisions, resolve a compatible Pest 4 release through Sail with a Composer dry run and review the dependency delta. Preserve the PHPUnit requirement where compatible. Initialize only the necessary Pest configuration; do not convert existing tests or install Drift, browser, or Livewire plugins for static architecture checks.
2. Put `NamingTest.php` and `LayeringTest.php` under `tests/Unit/Architecture`, which the existing Unit suite already discovers. Keep architecture tests independent of `Tests\TestCase`, `RefreshDatabase`, and global Laravel hooks. Check any generated `tests/Pest.php` configuration so it does not rebind existing tests or boot Laravel for architecture files.
3. Audit each proposed rule against the current source, then implement the naming and dependency rules below. Use recursive namespace discovery so new classes are covered automatically. Keep exceptions limited to exact classes with a stated reason; report unexpected violations before deciding on production refactors.
4. Verify source analysis detects actual dependencies, including fully qualified references, type declarations, inheritance, and trait use; avoid import-only regex checks. Static class rules do not prove absence of dynamic container lookups, runtime I/O, or logic inside Blade. Keep behavioral tests and Larastan responsible for their existing concerns.
5. Confirm architecture files are included by the normal runner and existing CI command. Use the focused architecture command locally; avoid a second CI invocation that runs the same architecture tests twice. Update this entry with implementation outcomes and validation counts.

### Initial rules

| Scope | Rule | Existing allowances |
| --- | --- | --- |
| Application declarations | Namespace and declaration names match PSR-4 paths and case. Enums are enums; Models extend Eloquent Model; Controllers extend the application Controller except the base Controller itself. | Reuse current Laravel/Filament structure. |
| Names | Requests end in `Request`, Policies in `Policy`, Mappers in `Mapper`, and Flight Release Presenters in `Presenter`. Actions expose public `handle()`. ValueObjects remain final/readonly. | Policy concern traits are excluded from the class suffix rule. No universal DTO/View Model suffix, Action suffix, `__invoke()`, or application-wide final/readonly rule. |
| DTOs, ValueObjects, Enums | No dependencies on Actions, application Models, Services, Http, Livewire, Filament, or View namespaces. | Allow other data types, enums, exceptions, PHP interfaces, Carbon, and Laravel support utilities already in use. |
| Domain parsing/services, Mappers, Infrastructure | No dependency on Http Controllers/Requests, Livewire, Filament, or View classes. | Services may use Models, lookup clients, cache, logging, PDF/OCR, and framework contracts. Do not ban all framework dependencies. |
| Flight Plan parsing | Preserve the direction toward data types and extraction helpers; prevent presentation dependencies. | `FlightCrewExtractor` legitimately uses `Schedule\Extractor\CrewListParser`. PDF/text and route extractors have legitimate I/O/cache/lookup dependencies. Do not assume every extractor is pure. |
| View Models and Presenters | No dependency on Models, extraction Actions, Infrastructure, lookup clients, or document extractors. | Allow DTOs, ValueObjects, Enums, presentation helpers, and the existing `RoutePresenter` dependency on `FlightTypeClassifier`. Actions may build View Models, as `BuildFlightPlanPageData` already does. |

Use targeted expectations rather than blanket presets, strict-types requirements, line-count limits, or an invented repository layer. [Architecture expectations](https://pestphp.com/docs/arch-testing).

### Acceptance and validation for implementation

- Naming and layering violations identify the offending class and rule. Newly added classes are discovered, and a rule fails if its intended scope unexpectedly matches no declarations.
- Demonstrate failure for a deliberate naming violation and a forbidden dependency, then restore the valid source. Include a fully qualified dependency case to catch checks that only inspect imports. Keep any checker fixtures within the test suite and outside application discovery.
- Run `vendor/bin/sail pest --compact tests/Unit/Architecture`, then focused existing PHPUnit files through `vendor/bin/sail artisan test --compact`, including a DTO test and an existing Livewire lifecycle test. Verify the normal runner discovers the architecture files and review the parallel runner without running the entire suite at this checkpoint.
- Run Pint after PHP changes and Larastan once at final integration. Record results here. No packages, test files, configuration, or application code changed during planning; executable validation remains for implementation.

Planning commit message: `docs: plan Pest architecture tests and adoption constraints`

Implementation commit message: `test: enforce application naming and layer boundaries`

## Plan: Flight plan: Reserve fuel
### Goal

Show alternate-airport burn and calculated FMS reserve fuel as distinct values. Resolve the reserve additive from the aircraft fleet record, with explicit support for 747-400F, 777-F, and 777-300ERSF. Keep source fuel quantities separate from the derived calculation and label every unit.

### Current implementation

- `FlightFuelExtractor` already extracts `ALTN` into `alternate` and `RESERVE` into `final_reserve`. `FuelPlanData` preserves these as separate `FuelQuantity` values; `BuildFlightPlanData` and `BuildFlightPlanPageData` retain them through serialization and saved-result loading.
- `FuelPresenter::alternateReserve()` returns only `fuelPlan.alternate`. `FlightReleasePageViewModel::fmsFields()` labels that value `Alternate Airport Reserves`, so alternate burn is presented as reserve fuel without a calculation. Fuel Score already lists the source Alternate and Reserve separately.
- The `aircraft` table stores tail number, type, model, and weight limits, but has no reserve additive. `AircraftWeightLimitResolver` provides an existing pattern for resolving aircraft data by normalized tail number during extraction.
- `AircraftWeightSeeder` imports 74Y, 77V, and 77X aircraft as 747-400F, 777-300ERSF, and 777-F. The connected development database currently contains 22, 7, and 8 records respectively. The original task notes that the full fleet is implemented in production; production coverage was not independently verified. `AircraftSeeder` alone covers only the two 777 models.
- `AircraftFactory` chooses type and model independently and only generates 777 aircraft. It needs consistent, explicit fleet states for this calculation's tests.

### Problem

The FMS label conflates alternate burn with reserve fuel. There is no stored aircraft additive or confirmed calculation contract, and current fixtures cannot reliably distinguish the three fleet models. A guessed formula, missing additive treated as zero, or mixed pounds/kilograms could produce a misleading operational value.

### Calculation decisions needed before implementation

- Confirm the exact FMS reserve formula: which of alternate burn:
  - alternate + finalReserve
- Supply approved additive values and units for each model;
  - the two 777 variants share a value, while 747 differs
  - 777 = 8,000lbs additive
  - 747 = ?
- Define the no-alternate case: whether the confirmed formula permits calculation without alternate burn. A missing value is not an explicit zero.
- Confirm calculation/display rounding. Recommended storage is whole pounds per aircraft, conversion through `FuelQuantity` when the release uses kilograms, and rounding only for display; adjust storage precision if the supplied values require it.

### Implementation plan

1. Add a new reversible migration for a nullable `aircraft.reserve_fuel_additive_lb` column, subject to the confirmed units/precision. Use no zero default. Add the fillable attribute and integer cast to `Aircraft`; keep existing migrations unchanged. Expose an optional, non-negative integer field with an `lb` suffix in the existing Aircraft admin form and a clearly labeled table column.
2. Populate approved fleet values through an idempotent, narrowly scoped seeder, separate from the schema migration. Match the established model names, leave unsupported models unavailable, and avoid overwriting configured tail-specific values on reruns. Verify production fleet coverage and populate additives before expecting complete reserve calculations. Keep 747 fleet import work outside this task unless records are actually missing.
3. Add an aircraft reserve-additive resolver following the normalized-tail lookup pattern and a small calculation service that consumes typed source quantities plus the resolved additive. Resolve during `HandleFlightPlanExtraction`, pass the result into the builder, and keep database queries and arithmetic out of Blade and presenters. Convert all required inputs into one unit before applying the confirmed formula; preserve explicit zero and return unavailable when a required input or additive is missing or invalid.
4. Extend normalized fuel data with separate additive and calculated FMS reserve fields, retaining `alternate` and `finalReserve` as source values. Serialize the additive used and the calculated result with the saved release so later fleet edits do not silently change an existing brief. Update DTO array shapes, builders, and saved-result tests. Older payloads without the new fields should still load, with calculated reserve unavailable until re-upload.
5. Replace the ambiguous FMS field with distinct `Alternate airport burn` and `FMS reserve fuel` values. Keep source `RESERVE` labeled as `Release reserve` wherever needed to distinguish it from the derived value. Update the FMS section's source-only heading/evidence text to acknowledge the calculation, and distinguish missing release data from unavailable fleet configuration. Reuse the existing metric components and compact layout.
6. Add deterministic factory states for all three models with matching type/model values. Cover resolution, the approved formula, normalized tails, missing/unknown aircraft, unconfigured additives, explicit zero, invalid values, unit conversion, and the approved no-alternate behavior. Verify that calculated values survive save/reload and that older saved releases retain honest unavailable states.

### Validation for implementation

Run only affected PHPUnit tests through Sail: the new resolver/calculator tests, affected DTO/builders/serializer tests, `AircraftResourceTest`, additive-seeder tests, FMS view-model tests, and `tests/Feature/Livewire/FlightPlanBrief/Tasks/FmsTest.php`. Run the extractor test if its source-field contract changes. After PHP edits, run `vendor/bin/sail bin pint --dirty --format agent`, then Larastan once at the final integration checkpoint. Check the FMS display in both themes and the production Vite build if frontend assets change. Record actual outcomes here when implemented.

### Planning outcome

Traced the source-to-FMS data flow, confirmed Sail is running and the connected development database contains all three fleet models, and identified the misleading FMS label and missing additive storage. This is a documentation-only plan; application code and production data were not changed. Formula, additive values, no-alternate behavior, and rounding remain open business inputs.

Commit message for implementation: `feat: distinguish alternate burn from calculated FMS reserve fuel`

Commit message for this plan: `docs: plan aircraft-specific flight plan reserve fuel`

-------------------------------------------------------

## [ ] Codebase: Improve parsing logic and application boundaries

### Goal

Improve correctness at schedule merge, duty-calendar, and extraction lifecycle boundaries; make saved-result reconstruction and page rendering easier to maintain. Preserve source evidence, explicit UTC/local and fuel units, owner isolation, and existing successful-upload behavior.

### Current implementation

The application already separates extractors, normalization builders, DTOs, infrastructure services, and presenters. Schedule uploads support partial success and cached results; flight-plan results are encrypted and restored through `BuildFlightPlanPageData`. This review found specific weaknesses within those boundaries rather than a need to replace the architecture.

### Problem

Findings below are ordered by impact. They are static source observations; operational impact and fixes still need focused regression coverage.

1. [x] Completed: **High — Make duty-calendar date handling strict and aware of year boundaries.** (2026-11-03) [FlightDutyEvent.php](app/Services/Calendar/FlightDutyEvent.php) resolves yearless dates against the previous, current, and next years, choosing flight-local dates near their UTC endpoints and duty dates near the corresponding local flight boundary. Malformed text and impossible dates return unavailable; round-trip validation preserves valid padded and unpadded source days. Local flight timestamps must be within 14 hours of UTC, duty report cannot follow departure, and UTC flight duration must be positive. OCR duty-release correction advances at most one day, including across New Year, and the user-confirmed maximum duty span is 30 hours. Unit and duty-export feature coverage includes December/January in both directions, midnight crossings, leap days, different local clocks, invalid input, excessive intervals, and exact/over-limit boundaries. Source local-time metadata remains unchanged.

   Validation: Sail focused tests passed (`FlightDutyEventTest` and `ExportFlightDutyCalendarEventTest`: 67 tests, 158 assertions); Pint passed; one Larastan run passed with zero errors. `git diff HEAD --check` passed. Preserved unrelated working-tree changes and the existing staging state.

   Commit message: `fix: resolve duty calendar dates across year boundaries`

2. [x] Completed: **High — Define event identity independently of the full parsed payload.** (2026-11-03) Extracted [ScheduleResultMerger.php](app/Services/Schedule/ScheduleResultMerger.php) from `JcaScheduleProcessor`. Matching uses UTC bounds plus flight number/route and operating/deadhead type; other events use their type, assignment/station/title, and hotel where applicable. Unknown or incomplete identities remain separate, and repeated events within one source retain their multiplicity. Complementary metadata and source summaries merge independently of upload order. Conflicting values become unavailable with preserved alternatives; overlapping/shared-boundary observations of the same flight number with incompatible times/routes remain separate and produce visible, escaped warnings that survive cache reload. Source records retain filenames, original upload positions, MIME/document metadata, and original summaries; each event retains its original source observations. Partial-upload success and existing cache/export behavior are preserved.

   Validation: Sail focused tests passed (54 tests, 403 assertions): merger unit rules, schedule-upload feature tests, and three existing DTO cache/crew/ICS regressions. Coverage includes exact duplicates, metadata/key-order differences, conflicting observations, unrelated flights sharing a timestamp, later legs, reversed uploads, per-type identities, repeated/unknown events, and warning escaping. Pint and the frontend build passed; one Larastan run passed with zero errors. `git diff HEAD --check` passed. Preserved unrelated changes and existing staging state.

   Commit message: `fix: merge schedule events by semantic identity`

3. **High — Separate safe upload feedback from exception diagnostics.** [JcaScheduleProcessor.php](app/Services/Schedule/JcaScheduleProcessor.php), `fileFailure()`/`displayedFileFailure()`, passes arbitrary exception messages into `ScheduleExtractor::$failedFiles` and the results modal. Internal paths, parser details, or document fragments can therefore become user-visible when an unexpected exception contains them. Map expected failures to useful public messages/codes and retain unexpected diagnostics in server reporting with request/file-position context. Cover an exception containing a private path or document fragment and verify it is absent from Livewire state and rendered feedback while successful files remain available.

4. **Medium — Make extraction outcomes reflect partial success and persistence failures.** [HandleExtractExecution.php](app/Actions/Extract/HandleExtractExecution.php) ignores returned `failed_files` when completing the request; [ExtractRequestLogger.php](app/Services/Infrastructure/ExtractRequestLogger.php), `complete()`, always records `success`. Separately, [HandleFlightPlanExtraction.php](app/Actions/FlightPlan/HandleFlightPlanExtraction.php) marks success before [FlightPlanBrief.php](app/Livewire/FlightPlanBrief.php) saves the encrypted result. A multi-image partial failure appears fully successful, and a failed result save can leave a successful extraction record despite failed user delivery. Define processing versus delivery outcomes explicitly, retain partial-file counts without storing document text, and place lifecycle ownership at the action that coordinates the relevant persistence boundary. Cover partial success, all-file failure, result-save failure, and retention of the previous brief. Decide whether existing `partial` status denotes in-progress work or a terminal partial success before reusing it.

5. **Medium — Batch uncached airport lookups with bounded concurrency.** [AirportResolver.php](app/Services/Schedule/AirportResolver.php), `resolveMany()`, deduplicates codes but performs every cache miss synchronously in a loop. Each code has its own connection/read timeout and retries, so latency accumulates across a roster. [AirportLookupClient.php](app/Services/Clients/AirportLookupClient.php) already pools ICAO requests, while the schedule path supports both IATA and ICAO. Extend the existing client boundary to batch both code types with a concurrency limit, resolve cache hits first, and preserve per-code found/missing/unavailable outcomes and their current TTLs. Extend `AirportResolverTest` and client coverage for mixed codes, cached hits, one provider failure, and bounded batch size; use deterministic HTTP fakes rather than wall-clock timing assertions.

6. **Medium — Give persisted flight-plan hydration an explicit home and compatibility contract.** [FlightPlanResultSerializer.php](app/Services/FlightPlan/FlightPlanResultSerializer.php) only writes the payload wrapper; [BuildFlightPlanPageData.php](app/Actions/FlightPlan/BuildFlightPlanPageData.php) also reconstructs the full domain DTO, handles legacy aliases, and repeats field mapping present in [BuildFlightPlanData.php](app/Actions/FlightPlan/BuildFlightPlanData.php). New fields require coordinated changes across extraction, serialization, and page reconstruction. Move persisted-payload hydration into the existing serialization/domain boundary, reuse the established section builders, and keep page-data assembly focused on presentation input. Add an explicit schema version and supported legacy behavior if the persisted format evolves. Verify fresh-extraction/save/reload parity, legacy `envelope` handling, unknown preliminary status, malformed optional sections, and retention of valid source quantities/evidence. Do not collapse the different validation needs of extracted and persisted data into one permissive mapper.

7. **Low — Reuse the current brief and view model within one Livewire request.** [FlightPlanBrief.php](app/Livewire/FlightPlanBrief.php), `hydrate()`, task validation, and `render()` can independently load the same encrypted payload; `currentViewModel()` rebuilds the DTO and presenters on each call. Reuse one result/model per request, with explicit invalidation after upload, clear, or result-key changes. Preserve cross-request stale-tab checks and authorization. Use existing navigation/lifecycle tests to verify the rendered model changes immediately after replacement/clear and stale keys still redirect; add a focused dependency call-count assertion where repeated reconstruction is actually exercised. Keep the existing presenter boundaries rather than introducing a second presentation system.

### Review outcome (2026-10-26)

- Reviewed schedule input/OCR, multi-source processing, airport enrichment, extraction logging, flight-plan normalization/storage/restoration, Livewire lifecycle, calendar exports, and representative related tests and offline-fuel JavaScript. This was a targeted static review, not an exhaustive security audit or runtime performance measurement.
- Recorded seven improvement areas with concrete source references and regression expectations. No application code, dependencies, schema, or tests changed. Existing OCR configuration and architecture-test tasks remain separate; this task does not authorize dependency installation.
- `.ai/rules` is absent. Applied `AGENTS.md` and the Laravel best practices skill. Documentation-only scope uses the Sail prerequisite exception; tests, Pint, and Larastan were not run. `git diff --check -- TODO.md` passed.
- Preserved pre-existing changes in `TODO.md`, `CREW_BIDS.md`, and `.nvmrc`. Implementation remains pending; this review completes the requested documentation only.

Commit message for this review: `docs: record codebase logic and organization improvements`

## B. Make OCR resolution configurable
You're currently rendering at 200 DPI.
That's reasonable for many documents, but flight schedules can contain small text, especially aircraft registrations, flight numbers, and timestamps.
I would make DPI configurable, with 300 as an option for difficult documents.

## Don't force PSM 6 for every document
Your current command:
[
    $tesseract,
    $imagePath,
    'stdout',
    '--psm', '6',
]


PSM 6 assumes a single uniform block of text.
That can be effective for flight plan sections, but schedules with multiple columns or complex layouts may benefit from PSM 3 or 4.
I'd make page segmentation mode configurable per extraction call.

# Completed Tasks

-------------------------------------------------------

## [x] Completed: Polish cloud status and compact save badges

Goal: Make save/report status scannable while keeping the header compact on mobile.

Current implementation: The status button uses a cloud with a green check only when the local workspace is saved and the current order matches the confirmed Kalitta report. Local edits, pending requests and differing reports use a cloud-upload icon with amber feedback; local edits and requests pulse the dot with reduced-motion support. Unconfirmed reports remain neutral. Desktop shows a soft emerald Saved badge and a muted slate Uploaded badge for matching reports, with separate unsaved, differing and unconfirmed labels. Below the small breakpoint, only the cloud is visible; tapping or keyboard activation opens the existing diagnostic card. The card retains local-save and report timestamps, details and distinct colored badges. Bid-list access and popover dismissal/focus behavior are preserved.

Problem addressed: Plain status lines and document icons took header space and were harder to distinguish at a glance.

Validation: Elevated Sail was available. All three focused PHP rendering/status tests passed (34 assertions), including Uploaded only for a matching current order and its absence for differing or unconfirmed reports. All 57 crew-bid JavaScript tests, the final production Vite build, Pint and the single Larastan pass (zero errors) passed. Whitespace checks passed. Concurrent edits and staging were retained; the required dirty-file Pint runs also normalized imports/spacing in four concurrently edited employee-bid files. No full suite, manual browser/touch review or live portal operation was performed.

Commit message: `ui: polish cloud status with saved and uploaded badges`

## [x] Completed: Split bid list access from status details

Goal: Keep quick access to My bid list while making local-save and Kalitta report details available by click or keyboard, without relying on hover.

Current implementation: Divided the header control into two sibling buttons with a subtle divider. The left segment retains the cart, count, drawer opening and return-focus behavior. The right segment retains the local/Kalitta icons and labels, adds a disclosure chevron and opens a nonmodal status popover. It refreshes the relative local-save description, shows the exact recorded local timestamp in UTC and displays the validated report-check timestamp/status from the existing Kalitta snapshot. Missing timestamps remain unavailable. The panel supports keyboard activation, focus on opening, Escape/Close focus restoration, outside-click dismissal and dismissal when focus leaves the control. Opening the drawer closes the popover without stealing focus. Delayed focus is cancelled after closure or component destruction. Responsive widths, dark mode, visible focus and accessible status descriptions are retained. Opening status details sends no request and performs no save, report fetch or submission.

Problem addressed: The entire control previously opened the drawer, so clicking its status labels could not reveal report details independently.

Validation: Elevated Sail was available. All 56 crew-bid JavaScript tests passed, including independent actions, dismissal/focus behavior, delayed-focus cancellation, unavailable timestamps and timezone-to-UTC conversion. All four focused PHP rendering/status/save-timestamp tests passed (49 assertions), including the recorded report time inside the popover and unchanged saved revision/submission state. Pint, the production Vite build, the single Larastan run (zero errors) and staged/unstaged whitespace checks passed. Preserved unrelated changes and staging. No full suite, manual browser/touch review or live portal operation was performed.

Commit message: `ui: split bid list access from save and submission details`

## [x] Completed: Distinguish local saves from Kalitta submission status

Goal: Explain whether the bid list is saved locally and whether its order matches the last confirmed Kalitta report, with clear actions for incomplete states.

Current implementation: Replaced the anonymous status dot with document/sync/warning icons and a Local label, alongside a separate paper-plane icon and Kalitta label inside My bid list. Local saves turn green after confirmation. Kalitta is green only when the current ordered choices match the owner's validated report snapshot, amber when the list differs, and neutral when no usable report is available. The drawer explains automatic local saving, retry/recovery, the UTC report-check time and the Review / Copy → Submit to Kalitta path when submission is enabled. Tooltips and accessible announcements describe both states. The corrected shared unsaved-change helper reads the backend property with `$wire.$get('dirty')`, and review/copy/submission controls use it, including protection after failed requests. Saved choices, portal evidence and submissions retain their existing ownership and persistence rules.

Problem addressed: Livewire 4.4.8 reserves `$wire.dirty` as an alias for its built-in `$dirty` function. Treating that function as the application's boolean property made the indicator permanently amber and disabled review actions even after successful saves. The old dot also gave no visible distinction between local saving and Kalitta submission.

Validation: Elevated Sail was available. Recent Debugbar request data showed a successful crew-bids response with no server exceptions. A regression using the installed Livewire proxy first reproduced the saved-to-unsaved misclassification, then passed with the corrected access. All 49 crew-bid JavaScript checks passed, including enabled clean-state review actions and disabled unsaved/failed-state actions. All 55 focused PHP integration tests passed (269 assertions), covering local/report status, draft changes, uncertain attempts, source isolation, page data, save timestamps, failed saves, conflicts and submission UI. Pint, the production Vite build and whitespace checks passed. The single Larastan run found two annotations in the earlier resolver helper; removed that helper and documented the encrypted attribute's exception at its actual access boundary. All 39 affected tests passed afterward (169 assertions). Analysis was not rerun under AGENTS.md's once-per-task rule. Preserved unrelated changes and staging. No full suite, manual browser review or live portal operation was performed.

Commit message: `fix: clarify local save and Kalitta submission status`

## [x] Completed: Sort by user submitted bid order

Goal: Sort line cards by the user's last report-matched Kalitta bid order while keeping submitted and editable ranks distinct.

Current implementation: Added Submitted bid order to CrewBidSort and the dropdown. An owner/workspace/package/seat-scoped resolver validates the latest attempt's matched status, report-check time, authenticated context, local revision and ordered line/report-number mapping. New reviews also bind the package fingerprint and payload hash; existing matched reviews retain their context/revision binding. Newer uncertain or nonmatching attempts invalidate older matches, while unattempted reviews do not replace them. Submitted choices appear first by original rank, followed by natural Line ID order. Eligibility, filters and tabs preserve rank gaps without adding missing cards. Cards show Submitted #N separately from Selected #N, with a UTC report-check timestamp above the list. Missing or unreadable evidence disables the option; losing the source resets Best match with a notice. The dropdown width remains bounded. Rendering and Add all matching share the server-resolved order, and sorting leaves saved ranks, copy output, save timestamps and portal state unchanged. No migration, credentials or portal request is needed; uncaptured manual portal bids still require separate read-only retrieval.

Problem addressed: The editable saved list can differ from the submitted order, so its ranks cannot establish what Kalitta last received.

Validation: Elevated Sail was available. All 85 focused PHP integration tests passed (451 assertions), covering submitted/draft ranks, filtering, flying/reserve tabs, unavailable lines, natural ordering, ambiguous attempts, owner/context isolation, legacy reviews, context changes, bulk-add, existing sorts/pay goals and save status. The real reviewed-submission regression proves its matched record supplies the source. All 48 crew-bid JavaScript tests, the production Vite build, Pint and whitespace checks passed. The single Larastan run reported a redundant nullsafe access under null coalescing and missing decryption-exception documentation at the encrypted model boundary. Both were corrected, then all 37 affected tests passed again after formatting (151 assertions); analysis was not rerun under AGENTS.md's once-per-task rule. Preserved concurrent document edits, working-tree changes and staging. No full suite, manual browser review or live portal operation was performed.

Commit message: `feat: sort crew bid lines by last submitted Kalitta order`

## [x] Completed: Plan crew bid access roles

### Goal

Define who can see K4 Bids in navigation, open its route, save a private bid list, and manage shared packages.

### Current implementation

`User::canUseCrewBids()` grants access to verified admins or, when configured, all verified users. Both nav layouts use that method; `/crew-bids` also has the feature and authorization middleware. Package management is admin-only. The Users resource cannot currently assign a crew-bid-specific grant.

### Problem addressed

The existing all-users switch cannot give selected ordinary users access without opening the workspace to every verified user. The current crew-bid access check also does not consider `is_active`.

Outcome (2026-11-10): The user chose explicit per-user crew-bid grants plus existing admin access, with package management remaining admin-only. Documented the access matrix, route/nav behavior, grant management, rollout, and focused validation in CREW_BIDS.md. This was a documentation-only plan; no access behavior or account data changed. Implementation is recorded in the following task.

Commit message: `docs: plan explicit crew bid access grants`

## [x] Completed: Implement crew bid access roles

### Goal

Give selected ordinary users access to K4 Bids in navigation and at its direct route, while keeping package management with existing admins.

### Current implementation

Before this change, verified admins or every verified user under `features.crew_bids.for_all_users` could enter the workspace. The Users resource had no crew-bid grant control, and inactive accounts were not excluded by the crew-bid gate.

### Problem addressed

The all-users switch could not grant access to selected crew members. It also bypassed the intended explicit access decision when enabled.

Outcome (2026-11-10): Added a false-by-default `users.crew_bids_access` grant, managed and displayed in Filament Users. Active, verified grantees and existing admins can reach K4 Bids; only admins can manage packages. Removed the crew-bid all-users setting. The existing nav and route use the updated shared access check, and revocation blocks an open workspace without deleting its saved list. Deployment needs the new migration and deliberate grants for intended users; no existing accounts were auto-granted.

Verification: 24 focused tests passed (157 assertions), Pint passed, and one Larastan run passed with zero errors. No full suite or manual browser review was run.

Commit-hook follow-up (2026-11-10): The staged-PHP Larastan check exposed 13 type errors in `CrewBidsTest.php` that the earlier application-only analysis did not cover. Kept the test behavior and fixed Livewire helper inference by splitting assertion chains, used Livewire's public `getData()` for snapshot data, and narrowed date-picker nodes to `DOMElement`. The 25 affected focused tests passed (202 assertions), followed by 2 focused tests (15 assertions) after the final chain fix. Pint and file-scoped Larastan passed; the staged pre-commit check passed after this correction.

Commit message: `feat: grant crew bid access per user`

## [x] Completed: Upgrade bid card depth and header styling

### Goal

Give every shared bid line card more depth, clearer header hierarchy, and consistent 12px spacing.

### Current implementation

Added soft layered shadows, deeper hover shadows, and a 2px hover lift. Cards reserve a 3px left border that turns Aviation Blue when a disclosure is expanded, with a lighter blue accent in dark mode; its fixed width prevents content shifts. Headers use slate-50 in light mode and an opaque slate-800 surface in dark mode. Flying line IDs use bold, darker Slate text. Header, calendar, footer, and warning spacing now use 12px. Hover and state transitions share the disclosure's 240ms cubic-bezier curve, and reduced-motion preferences disable lift and animation.

### Problem addressed

The shared cards needed stronger separation from the background, a differentiated header, more internal space, and a visible expanded state. The view model and existing card/disclosure interactions remain intact.

Verification: Focused Sail tests passed (86 tests, 512 assertions). Pint, one Larastan run with zero errors, the production Vite build, and scoped diff checks passed. Synthetic F83/F55/reserve previews covered a 320px card container in light mode and 1440px desktop in dark mode with an expanded disclosure. Computed styles confirmed 12px padding, the fixed 3px left border, the expanded blue accent, contrasting headers, and no horizontal card overflow. No additional tests were added for this styling-only change. Prior working-tree edits were preserved.

Commit message: `ui: add depth and expanded accents to bid cards`

## [x] Completed: Polish bid card sections and interactions

### Goal

Separate the header, calendar, and footer with consistent spacing and give bid cards clearer hover and expansion feedback.

### Current implementation

Added a subtle header divider, dotted footer divider, 8px section padding, a minimum 80px ID column, and wrapping summary metrics. Cards have a light shadow and stronger border/shadow feedback on hover, with matching dark-theme styling. Card-background clicks toggle the native date disclosure; nested controls, disclosure summaries, prevented clicks, and text selection retain their existing behavior. Existing summary controls provide keyboard access. The view model, typography, colors, and calendar labels remain intact.

Disclosure content uses a 240ms intrinsic-height transition with a cubic-bezier curve and deferred content visibility. Reduced-motion preferences disable animation; browsers without the required CSS support retain native disclosure behavior. Cards now size to their content rather than using the former 150px maximum, accommodating the requested spacing and warnings without clipping.

### Problem addressed

The card sections lacked separation and hover feedback. Background clicks had no expansion behavior, and disclosure content appeared immediately.

Verification: Focused Sail feature tests passed (76 tests, 458 assertions), and all 15 crew-bidding JavaScript tests passed, including background toggling, independent cards, preserved controls, selected text, and prevented clicks. Pint, one Larastan run with zero errors, the production Vite build, and scoped diff checks passed. Synthetic Chrome previews covered a 320px card container, 1440px desktop, both themes, warnings, long credit values, and expanded date content. A real-time Chrome check confirmed intermediate card heights during opening (156 → 496.6 → 508px) and closing (508 → 171.3 → 156px). Physical touch and live authenticated interaction were not exercised. Existing staged work was preserved.

Commit message: `ui: refine bid card sections and disclosure feedback`

## [x] Completed: Extract a bid line view model while preserving the existing appearance

### Goal

Move line presentation decisions out of Blade while retaining the existing bidding layout and styling.

### Current implementation

Added the readonly CrewBidLineViewModel, built after matching and sorting. It prepares summary/detail metrics, flag icons and existing colors, calendar labels and descriptions, trip disclosure labels, credit explanations, warnings, and manual selection rank. The line component renders that contract rather than the original line array. Matching, credit calculations, source data, and bid ordering remain unchanged.

Per the user's follow-up, reverted the visual changes. Retained the original header layout, typography, badge styles, calendar colors, complete date labels at every width, carry-day badge beside the calendar, disclosure spacing, and 150px collapsed-card limit. Trip details and leg timeline templates match their prior versions. The rebuilt stylesheet matches the stylesheet from before the visual changes.

### Problem addressed

The line template chose icons, formatted calendar labels, selected detail metrics, and assembled credit explanations directly from a loosely structured array. These decisions now have a dedicated, testable presentation contract.

Verification: The focused Sail run passed 86 tests (512 assertions), covering missing versus zero values, second-precision credit, 28/29/30/31-day calendars with complete date labels, retained trip/rank data, credit-source explanations, and escaped metadata. Pint, one application-wide Larastan run with zero errors, and the production Vite build passed after reverting the visuals. Scoped diff checks passed. Unrelated CREW_BIDS.md edits were preserved.

Commit message: `refactor: extract bid line view model without visual changes`

## [x] Completed: Set preferences pop out

### Goal

Open K4 Bids preferences in view from any scroll position.

### Current implementation

Replaced the inline preferences section with a viewport overlay using the existing bid-list focus trap and scroll-lock pattern. The panel fills narrow screens and becomes a centered dialog on larger screens. Its form scrolls independently while Close and Done remain visible. Escape and the desktop backdrop dismiss it; reopening from the toolbar retains current preferences. Existing autosave, initial setup, and manual bid order remain intact. Save status, validation/conflict errors, and failed Done requests appear within the dialog; a failed save keeps it open for retry.

### Problem addressed

The sticky preferences control previously opened a section near the page top, requiring users farther down the line list to scroll back up.

Verification: Sail's focused CrewBids feature tests passed (64 tests, 409 assertions), including save/reopen behavior and a conflicting-tab error displayed inside the dialog. All 13 existing crew-bidding JavaScript tests passed. Pint, the production Vite build, and one Larastan run with zero errors passed. Task-scoped diff checks passed. Manual browser, touch, and keyboard review remains outstanding. The user's CREW_BIDS.md edits were preserved.

Commit message: `fix: open crew bid preferences in a responsive dialog`

## [x] Completed: Plan automated bid-package imports

### Goal

Plan monthly imports from forwarded work Outlook email or scheduled crew-bids website downloads.

### Current implementation

Recorded an email-first automation plan in CREW_BIDS.md, with shared receipt/staging/classification, coherent package grouping, deduplication, revision handling, privacy/retention, scheduler recovery, and phased acceptance criteria. Incorporated the user's policy: automatically publish complete validated first packages; hold revisions for review. Existing importer supports three source roles per group rather than the full ten-attachment email. Identified `/Pages/Documents.aspx` in saved portal navigation as a discovery lead, not a verified package source. Recorded the persistent-authentication decision needed before unattended portal downloads.

### Problem addressed

Monthly import currently requires manual file selection, while complete emails include unsupported and potentially personal documents. The plan prevents partial or mixed revisions from replacing packages and preserves private bid lists. No automation, forwarding rule, credentials, infrastructure, or application changes were introduced.

Verification: Inspected importer/parser/publication code, existing tests, scheduler configuration, and local source filenames. Confirmed Laravel 13.34.0 through Sail and checked Boost scheduler/queue documentation plus official forwarding/inbound-mail references. Connected Outlook is a personal account; the actual work-email manifest remains unverified. Documentation diff check passed; no application tests, Pint, or Larastan were needed for this plan.

Commit message: `docs: plan automated bid package ingestion and publication`

## [x] Completed: Review CREW_BIDS.md against the supplied CBA

### Goal

Check the crew-bidding document against the supplied 2021 CKS CBA, especially Part I Sections 5 and 17, and document discrepancies in CREW_BIDS.md.

### Current implementation

Added a dated source review with eight compensation findings and eight scheduling findings, each with clause and printed/PDF page references. Qualified the historical carry-credit formula and examples, corrected the guarantee's verification status, identified the rate arrays as Appendix 5-A's January 1, 2024 longevity-year 1–12 values, and distinguished contractual deadlines from pending live portal discovery. Recorded the agreement's renewal/effective-date provisions and the limits of applying the supplied text to 2026 packages. Application code and portal behavior were not changed.

### Problem addressed

The document called the carry-premium formula confirmed while leaving CBA-defined guarantees, rate provenance, and scheduling constraints unresolved. The review separates direct discrepancies, missing contractual qualifications, and product choices.

Follow-up clarification: Section 5.M establishes transition-credit allocation: all PCH for the transitioning duty is paid in the new bid period, and transition PCH is credited to the compensation Month of the last duty assignment. Added this confirmed rule beside the Carry-In Days definition and January–March compensation boundaries, and explicitly labeled the old departure-only formula as historical implementation. Remaining uncertainty concerns source coverage for applying the rule, additional premium eligibility, and current applicability; the allocation rule itself is defined by the supplied CBA.

User-confirmed application: Recorded one duty period, potentially containing multiple legs, as the Section 5.M duty-assignment boundary. Carry-in credit is the greater of awarded or actually worked credit for each Z day, summed after the daily comparisons; each flight segment's full credit belongs to its departure Z day. Replaced the earlier two-leg illustration with a daily comparison showing why comparing aggregate totals gives the wrong result. Distinguished daily valuation from Section 5.M pay-period allocation and awarded planning values from finalized pay. Attributed the daily greater-of rule to the user's clarification, without inventing a supporting CBA clause. The extra 50% remains conditional on qualifying excess days or scheduled-day-off work, with flight and non-flight treatment kept separate. Preserved the user's documentation-only scope.

Verification: Read both key chapters in full and checked related definitions, rest/deadhead/hostile-area clauses, duration, and implementation LOA through Sail PDF extraction. Compared both stored rate arrays with Appendix 5-A. Documentation-only change; application tests, Pint, and Larastan were not run. Final whitespace/diff review passed.

Commit message: `docs: review crew bidding rules against the 2021 CBA`

## [x] Completed: Seat link before header save status

### Goal

Swap the seat preference link and Saved indicator in the header.

### Current implementation

The First Officer/Captain preference link now precedes the save status, followed by My bid list.

### Problem addressed

The header previously displayed Saved before the seat link. Pint, one Larastan run with zero errors, and diff checks passed. Tests were not needed for this element-order-only change.

Commit message: `ui: place bid seat link before header save status`

## [x] Completed: Addition notice beside header save status

### Goal

Move the added-at-rank notice and Undo addition to the left of Saved in the header.

### Current implementation

The existing notice now appears immediately before the header save status, with its eight-second dismissal, Undo action, loading feedback, and polite status announcement preserved. The header can wrap on narrow screens. Save-recovery actions remain below the header without an empty notice row.

### Problem addressed

Addition feedback previously occupied a separate row below the toolbar. Two focused undo/save-recovery cases passed (10 assertions), along with Pint, one Larastan run with zero errors, and diff checks. No tests were added for this layout-only change.

Commit message: `ui: move bid addition notice beside header save status`

## [x] Completed: Back to lines icon

### Goal

Add an icon to the slide-over's Back to lines button.

### Current implementation

The button pairs the existing label with a decorative left-arrow Heroicon using the established icon sizing and inline spacing. Its immediate close action and focus restoration remain intact.

### Problem addressed

The return action previously had only text. The focused drawer feature test passed (nine assertions), alongside Pint, one Larastan run with zero errors, and diff checks. No tests were added for this visual-only change.

Commit message: `ui: add a back arrow to the bid list close button`

## [x] Completed: Responsive Add and crew bids controls review

### Goal

Improve Add responsiveness and review K4 Bids controls for appropriate JavaScript, debounce, and loading feedback.

### Current implementation

| Controls | Decision and resulting behavior |
| --- | --- |
| Add | Immediate client-side Adding feedback, a reduced-motion-aware spinner, and a guard against duplicate clicks. Selection still waits for server validation. Failures clear the pending state and show the existing request error. Loading disables Add during list mutations and context changes while leaving unrelated filter requests independent. |
| My bid list, Selected, and Back to lines | The drawer stays mounted and opens/closes locally, with reactive expanded state, focus trapping, initial focus, and focus restoration. |
| Manage package, preferences, filters, package info, trip tabs, and disclosures | Keep immediate local JavaScript or native interactions; no debounce or request is needed to open these views. |
| Sort, start airport, layover airport, and Selected only | Send discrete changes immediately on change instead of waiting 250ms. Layover quick filters update their pressed styling immediately while requesting results. |
| Goals, goal ordering, and UTC days off | Preserve immediate client feedback and the shared 250ms preference-save debounce. Done retains its 250ms delay to include pending edits and now shows save progress. |
| Minimum credit and minimum days off | Retain the 400ms typing debounce. Results show delayed loading feedback while filtering. |
| Date-range application, resets, retries, revision review, list moves/removal, and bulk list actions | Keep server validation and persistence, with scoped progress labels and duplicate-action disabling. Empty bulk actions are disabled. |
| Upload, preview, and publication | Show upload/preview/publication progress and prevent overlapping import operations. Keep server-side admin checks. |
| Review and copy | Keep review server-backed and clipboard copying local. Disable copying while requests or preference edits are pending, or when the list is empty. |

Captured Debugbar requests took 2.6–3.9 seconds; one 3.56-second request spent only 26ms on queries and reached its first Blade view after about 3.14 seconds. Code review found repeated decoding of the encrypted package payload for each eligible line. Rendering now decodes each package once and reuses it for line presentation, trips, and revision comparisons. No post-change live timing benchmark was performed.

### Problem addressed

Add previously had no progress label and was disabled by every component request. Several other server controls had no local progress feedback, while opening the drawer required a request. Focused Sail checks passed: 67 bids/filter cases (375 assertions), 13 JavaScript cases including immediate pending state, duplicate suppression, and failure/retry handling, two import cases (14 assertions), and four follow-up cases after the final payload-access correction (29 assertions). Production build, Pint, and diff checks passed. The single Larastan run reported one redundant null-safe expression; it was corrected and the affected paths passed focused tests. Larastan was not rerun under the one-run rule. Browser interaction/focus review remains outstanding. Existing changes and concurrent staging were preserved.

Commit message: `perf: improve crew bid controls and reuse decoded package data`

## [x] Completed: Open Manage package immediately

### Goal

Make the Manage package controls respond immediately.

### Current implementation

Both Manage package buttons toggle the client-side Livewire property directly and announce their expanded state. The admin-only import panel stays rendered with `wire:show` and `x-cloak`, retaining its file input while hidden. Import and publication actions keep their server-side permission checks.

### Problem addressed

Opening and closing the panel previously waited for a server-rendered response. Five focused Sail feature tests passed (32 assertions), covering import/publication, failed imports, access, metadata escaping, and withholding the form from non-admins even when they set the visibility property. Pint, one final Larastan run (zero errors), and diff checks passed. Browser interaction review remains outstanding.

Commit message: `perf: toggle package management without a server round trip`

## [x] Completed: Tool-based user preferences and saved K4 bid seat

### Goal

Move the bid seat selector into user preferences, grouped by tool.

### Current implementation

The Profile form now groups User preferences into Schedule Extractor (airline codes and flight tracker) and K4 Bids (bid seat). The existing profile Save action validates and persists First Officer/Captain for the authenticated user. Omitted seat values preserve the saved choice. K4 Bids loads that seat when opening the workspace and retains it during package changes; each seat keeps its existing private preferences and bid list. The toolbar displays the active seat as a link to the K4 Bids preference section, replacing its selector. Already-open workspaces retain their current seat until reopened.

The migration preserves each existing user's latest workspace seat, with deterministic ordering for tied update times, and defaults users without a workspace to First Officer. The new column and migration record are confirmed in the local database. Existing workspace records are preserved.

### Problem addressed

Seat selection previously lived among workspace controls instead of the user's tool settings. Focused Sail checks passed: 28 profile, migration, and seat-context cases (136 assertions), followed by 68 bids and package-import regression cases (474 assertions). Coverage includes invalid values, omitted preferences, per-user saves, saved-seat precedence over recent workspace history, seat-specific lists and eligibility, package changes, no-package initialization, migration defaults/backfill, and rollback preservation of workspaces. Pint, production build, one final Larastan run (zero errors), and diff checks passed. Browser visual review remains outstanding. Existing toolbar changes and concurrent staging were preserved.

Commit message: `feat: move bid seat into tool-based user preferences`

## [x] Completed: Two-tier crew bids toolbar

### Goal

Separate package/account setup from result navigation, preferences, sorting, and filtering.

### Current implementation

The sticky header now contains two distinct rows. The command bar groups the package selector, publication status, and admin-only Manage package cog in one pill, with Package details beside it and the seat selector and My bid list action on the right. The controls row places Lines/Trips/Reserve on the left, the matching count and preference summary together in the center-left, and sorting and Filters together on the right. Both rows wrap within narrow screens and retain existing theme styles, accessible names, focus treatments, Livewire bindings, and permission checks. The existing resize observer measures the full header for expanded-card action offsets.

### Problem addressed

Package administration and result controls previously competed for space, while the matching count and preferences were grouped with sorting toward the right. Focused Sail checks passed: 64 crew bids feature tests (357 assertions), 11 JavaScript tests, Pint, the production build, and one final Larastan run with zero errors. Diff checks passed. Browser visual review remains outstanding. No new tests were added for this layout-only change.

Commit message: `refactor: split crew bids toolbar into command and control rows`

## [x] Completed: Immediate goal selection and priority ordering

### Goal

Update chosen goals and their priority order immediately while debouncing preference saves.

### Current implementation

The priority list now renders from the client-side goals array. Checkbox changes, Move up, and drag ordering share one 250ms save debounce. Reordering updates that array immediately and uses the bundled Alpine Sort plugin; its DOM order is restored before the keyed loop applies the new order. The client-owned list stays intact during server renders. Native ordered-list markers replace manually repeated rank numbers. Existing server validation, strict ranking, saved goal order, bid-list order, and unsaved-change protection remain in place.

### Problem addressed

The previous goal list waited for a server render after checkbox changes, while Move up called the server immediately. Five focused Sail feature cases passed (42 assertions), covering exact client-order persistence/reload, strict ranking, invalid goals, date selection, and retained bid order. Eleven focused JavaScript tests passed, including immediate moves, drag-order reconciliation, invalid targets, and deferred-save protection. Pint, production build, one final Larastan run (zero errors), and changed-file diff checks passed.

Reviewed the actual Blade goal controls with bundled Alpine and synthetic state/save handling in headless Chrome. Rapid selection, repeated Move up, and checkbox off/on changes updated the DOM with zero immediate saves, then produced one final save after the debounce. A simulated drag callback kept DOM/state order aligned and saved once afterward; no JavaScript errors occurred. This checked client interaction independently of authenticated Livewire requests, which were covered by the focused feature tests; a physical drag gesture was not exercised. Existing source-document edits and concurrent staging/commits were preserved.

Commit message: `perf: update goal priorities instantly with debounced saves`

## [x] Completed: Open crew bid preferences immediately

### Goal

Show or hide the preferences editor immediately when its toolbar button is clicked.

### Current implementation

The toolbar button now toggles the client-side Livewire property directly, with a reactive expanded state. The editor stays rendered and uses `wire:show` to toggle visibility; `x-cloak` prevents an initial flash. Opening/closing no longer waits for a debounce or server response, and hiding the editor retains its inputs and pending selection timers. Preference-save and Done debounces remain in place.

### Problem addressed

The opener previously waited 250ms before requesting a server-rendered editor. Four focused Sail feature tests passed (34 assertions), covering preferences initialization/reload, calendar persistence, priorities, and escaped package metadata. Pint, one final Larastan run (zero errors), and changed-file diff checks passed. No new tests were added for framework visibility internals. Existing working-tree changes were preserved.

Commit message: `fix: open crew bid preferences without a server round trip`

## [x] Completed: Debounce Set preferences and the days-off calendar

### Goal

Reduce repeated preference saves while users choose several goals or UTC days off, with immediate selection feedback.

### Current implementation

Preference open/Done actions now use a 250ms debounce. Goal checkboxes and the days-off calendar each share a 250ms change debounce, sending their complete deferred selection after input settles. Calendar cells retain their layout using native keyboard-accessible checkboxes and immediate checked-state styling. Server-side model updates sort selected dates before saving; existing date validation, saved bid ordering, conflict visibility, clear/range actions, and package-month range defaults are preserved. Pending preference changes participate in the existing leave-page warning before their debounced request begins.

### Problem addressed

Individual day clicks previously sent immediate toggle actions, while each goal checkbox had its own timer. Grouping each selector's updates preserves the final selection when multiple dates are chosen or the same date is toggled repeatedly. Twelve focused Sail feature cases passed (104 assertions), including multi-date persistence/reload, deselection, conflict retention, invalid dates, clearing, and existing range/goal/list behavior. All eight focused JavaScript tests passed, including leave-page protection during the debounce interval. Pint, production build, and changed-file diff checks passed. Browser request timing was not measured. Earlier working-tree changes were preserved.

One final application-wide Larastan run passed with zero errors.

Commit message: `perf: debounce preference actions and batch days-off selection`

## [x] Completed: Start date ranges in the bid-package month

### Goal

Open range date pickers in the selected bid-package month instead of the current calendar month.

### Current implementation

Both From and Through default to the first day of the package month whenever a package/seat context loads. Picker minimum/maximum dates use that month's first/last UTC calendar dates, including leap years. Switching packages resets the range to the new first day. Defaults do not add requested days off or save preferences until the user adds the range; existing server-side date validation remains in place.

### Problem addressed

Empty range fields let the browser initially show the current month, forcing users to navigate to the bid month. Eight focused Sail tests passed (67 assertions), covering first-day defaults with a different current date, year boundaries, February/leap February, rendered picker limits, package switching, explicit range application, existing range/undo behavior, and rejection of out-of-package dates without saving. Pint, one final Larastan run (zero errors), and changed-file diff checks passed. Existing debounce and documentation changes were preserved.

Commit message: `fix: initialize bid date ranges from the package month`

## [x] Completed: Debounce crew bid filter updates

### Goal

Reduce redundant result refreshes during rapid filter and preference changes.

### Current implementation

Added Livewire's 250ms network debounce to sort, required start city, layover airport, goal checkboxes, and Selected only. Native input state still updates immediately. Minimum credit and minimum days off retain their existing 400ms debounce. Package/seat changes, action buttons, date selection, and local panel controls remain immediate.

### Problem addressed

These live controls previously sent a refresh for every change, including intermediate keyboard selections and repeated checkbox changes. Confirmed the installed Livewire 4.4.8 runtime applies explicit network debounce to selects and checkboxes. Four focused Sail feature tests passed (40 assertions), covering strict goals/start-city filtering, sorting, combined layover filters, reset/context changes, and saved order. Pint, one final Larastan run (zero errors), and changed-file diff checks passed. No new tests were added for framework debounce internals; browser request timing was not measured. Existing TODO outcomes were preserved.

Commit message: `perf: debounce crew bid filter and preference updates`

## [x] Completed: Trip leg and layover quick filters

### Goal

Find lines with extended layovers at a chosen airport and exclude short connections without changing the private bid order.

### Current implementation

Added combinable 24h+ and avoid-under-10h controls, a connected-layover airport selector, clear/reset actions, and gold outlines with text on matching timeline segments. Uses existing scheduled UTC connection calculations; source rest remains separate. Positive extended-layover evidence can match partial trip coverage, while avoiding short connections requires all linked trips and consistent connections. Selected lines remain in the bid drawer in their original order. Filters reset with the package/seat context.

### Problem addressed

Previously duration flags required inspecting each expanded pairing individually. The focused Sail regression run passed 11 tests (44 assertions), including exact thresholds, combined filters, absent/empty trips, inconsistent connections, reset/context changes, and preserved source payloads and saved choices.

Commit message: `feat: filter crew bid lines by layover duration and airport`

## [x] Completed: Crew complement and deadhead summary indicators

### Goal

Compare crew distribution and known deadhead legs without opening Details.

### Current implementation

Crew complement now appears below each line ID when present. The existing evidence-based DH indicator keeps its text visible on narrow screens. Route flags, accessible explanations, missing-data omission, and source payloads are preserved.

### Problem addressed

Crew distribution was only available in Details, and narrow screens hid the DH label. Eleven focused Sail cases passed (76 assertions), including collapsed-header content, incomplete trip coverage, unlinked deadheads, duplicate pairings, and missing metrics.

Commit message: `feat: surface crew complement and deadheads in bid summaries`

## [x] Completed: Compact crew bid controls and package metadata

### Goal

Reduce the space above line results while keeping package information, preferences, sorting, and quick filters accessible.

### Current implementation

Moved package details into a native modal opened by the info icon beside the package selector. Saved status now sits in the header; transient notices and save recovery still appear when needed. Lines/Trips/Reserve, match count, a preferences pill, sort, and Filters share one responsive control bar. Filters expands into horizontal layover chips, the airport selector, and existing numeric/selected controls; a gold indicator identifies active filters when collapsed. Preferences still opens the existing editor. Mobile wrapping keeps the bid-list action beside the title and the match count beside the view tabs.

### Problem addressed

Separate status, package metadata, preferences, sorting, and bordered layover rows pushed results down the page. The default view now keeps metadata and filters closed, with keyboard focus, pressed/expanded states, light/dark styling, and existing loading/error feedback.

### Final integration outcome (2026-10-28)

All 55 focused Crew Bids feature cases passed (278 assertions), and all seven focused JavaScript tests passed. After the visual review's final mobile spacing refinement, the two affected rendering/saved-order cases passed again (40 assertions). Pint and the final production build passed. One application-wide Larastan run passed with zero errors; the full test suite was not run.

Reviewed actual Blade markup and production CSS with synthetic, nonpersistent data in headless Chrome at 390px and 1440px, including light/dark appearance, collapsed/expanded filters, card summaries, and the package modal. Neither exact viewport had horizontal overflow. The mobile header/control bar measured 181px/105px, and desktop measured 69px/53px. Trusted Escape input closed the modal and returned focus to its info button without closing Filters; another Escape closed Filters and returned focus to its trigger. Live authenticated interaction and physical touch-device review remain untested. Existing source-document edits, pay-rate data, other working-tree changes, and concurrent staging were preserved.

Commit message: `style: consolidate crew bid controls and collapse secondary metadata`

## [x] Completed: Crew bid pay rates JSON

### Goal

Provide valid JSON pay-rate data for the crew bid tool.

### Current implementation

Converted `app/Data/pay_rates.json` from a JavaScript fragment to a JSON object with quoted `CA` and `FO` keys and numeric arrays. Preserved all 12 rates per seat in their original order.

### Problem addressed

Unquoted keys, `Object.freeze()` calls, and a trailing JavaScript closing wrapper prevented JSON parsing.

Validation: Sail JSON parsing and exact comparison passed for all 24 rates. One Larastan run passed with zero errors. No PHP files changed; Pint was not required. Unrelated working-tree changes preserved.

Commit message: `fix: convert crew bid pay rates to valid JSON`

### Estimated pay specification outcome (2026-10-28)

Updated the Estimated pay section in `CREW_BIDS.md` to reference `app/Data/pay_rates.json` and describe the supplied `CA`/`FO` arrays with 12 numeric rates each. Rate input is now available; rate management, dollar estimates, and pay sorting remain deferred. Currency, effective dates, fleet applicability, longevity mapping, guarantee treatment, and the pay formula still need confirmation.

Validation: Sail JSON parsing confirmed 12 entries per seat. Documentation-only update; checked the focused diff for whitespace errors. No PHP files changed; tests, Pint, and Larastan were not needed. Preserved unrelated working-tree changes.

Commit message: `docs: reference supplied rates in estimated pay specification`

## Completed: FP: Delay reporting: Departure UTC time format

- [x] Format delay reporting UTC timestamps as `2026-10-26 03:47z`.

### Goal

Use `YYYY-MM-DD HH:mmz` for UTC date/time displays in delay reporting.

### Current implementation

Scheduled departure, report timeline and delay endpoints, autosave status, and saved history timestamps now share the requested format. Departure timestamps are converted to UTC before display; missing or unreadable values show `Not recorded`. Stored snapshots and native datetime input values retain their existing formats.

### Problem addressed

Scheduled departure previously displayed raw ISO text such as `2026-10-25T01:00:00+00:00`, while other delay timestamps used inconsistent suffixes and precision.

Validation: Sail focused tests passed (38 tests, 217 assertions), Pint passed, and Larastan passed once with zero errors. Existing unrelated working-tree changes preserved.

Commit message: `fix: standardize delay reporting UTC timestamps`

## [x] Completed: Crew bidding

### Goal

Give eligible crew a private, reviewable workspace for monthly bid packages and ranked choices.

### Current implementation

The initial release imports supported Lines/Trips PDFs and Overview spreadsheets into encrypted shared packages. Crew can browse B747/B777 packages by seat, filter available lines, inspect UTC calendars and trip details, set strict ranking goals, and save a numbered bid list with conflict and revision review. Filament manages draft import/replacement, publication, archive/restore, and deletion under policy and database protections. Source files are not retained after import.

Read-only Kalitta portal discovery validates the observed login and Bid Sheet structure using transient credentials. It does not submit bids; the closed-window report and open-window submission/receipt contract remain unverified. CREW_BIDS.md is the product contract for implemented behavior and pending comparisons.

### Problem addressed

K4 Parser previously lacked a shared package workflow and owner-scoped bid ranking. This branch also established source-aware trip display, missing-data handling, keyboard interaction, responsive controls, and admin lifecycle operations.

Historical validation used focused Sail PHP and JavaScript tests, production asset builds, Pint, and Larastan. Private source samples and manual browser interactions have separate availability and verification limits. Commit history retains the individual implementation and documentation outcomes.

Commit message: feat: add crew bidding imports and private ranked bid workspace

## [x] Completed: Bug: Extracted crew list:
## [x] Completed: FP: Fuel Score: Include TOC, TOD
## [x] Completed: FP: Missing SIGWX and additional fuel notes

### Goal

Extract all five dispatcher notes from `storage/app/private/flight_releases/CKS024201RJGG.pdf`, including the government SIGWX forecast and additional holding fuel for destination thunderstorms.

### Current implementation

`DispatcherNotesExtractor` recognizes both missing headings through the existing bulleted-note parser. It retains source order, joins wrapped bullet text, preserves heading/bullet line breaks, and bounds extraction at the next note, boxed separator, MEL/CDL section, or end of text. Existing ENROUTE SIGWX notes reuse the same parser.

### Problem

The parser's supported heading patterns omitted `GOVT SIGWX PROG FORECASTS AREAS OF` and `ADDITIONAL FUEL ADDED`, silently returning only the runway, maintenance, and FSA notes. The supplied PDF and four synthetic layout regressions reproduced the omission before the fix.

### Validation outcome (2026-10-06)

Through Sail, all 12 `DispatcherNotesExtractorTest` cases passed, including the supplied PDF's exact five-note result and the existing RJAA, PANC, and KCVG release regressions. The focused extraction orchestration and Notes rendering checks also passed: 14 passing tests and 99 assertions total; one separate private-PDF characterization was skipped because `CKS025625KLAX.pdf` is unavailable. Pint passed, and one full application Larastan run passed with zero errors. Boost's version-specific documentation search succeeded. Unrelated working-tree changes and staging were preserved.

Previously saved releases require uploading/extracting the PDF again to populate the two omitted notes. No frontend rebuild is required.

Commit message: `fix: extract government SIGWX and additional fuel dispatcher notes`

## [x] Completed: Capture flight-plan test streams

### Goal

Keep Livewire flight-plan progress frames out of PHPUnit console output while preserving progress assertions.

### Current implementation

The shared flight-plan test case captures output with a callback buffer that absorbs Livewire's explicit flushes and closes in teardown. Extraction tests check captured success and failure progress plus an empty output buffer. Browser streaming remains unchanged.

### Problem

Livewire writes progress frames directly and flushes the buffer, leaking JSON into otherwise passing test output.

### Validation outcome (2026-10-06)

Through Sail, all 41 focused flight-plan Livewire tests passed with 977 assertions and no streamed JSON in console output. The updated extraction test file also passed individually. Pint passed and one full application Larastan run passed with zero errors. Unrelated TODO edits were preserved.

Commit message: `test: capture flight-plan progress streams during Livewire tests`

## [x] Completed: Schedule: R2 status determine

### Goal

Use Duty as the umbrella event type for reserve, training, and deadhead assignments, classify specific duties from assignment context, and replace generic Duty badges with R1, R2, R4, or Training where supported.

### Current implementation

- `DutyEventType` represents FlightDuty, Deadhead, Training, ReserveR1, ReserveR2, ReserveR4, and GeneralDuty. `ScheduleEventType` retains Flight, Duty, Layover, Off, 1in7, and Unknown; legacy deadhead/training values normalize to Duty.
- `DutyTypeResolver` recognizes reserve codes, training keywords, deadhead markers, and flight-duty context. `TripInformationParser` applies it to both tabular and date-range formats, keeps assignment activity codes out of flight-number metadata, and produces `R2 LAX` from the supplied noisy R2 text with LAX station context. Published-roster reserve assignments use the same resolver.
- Duty cards show subtype labels and colors with light/dark variants: amber R1, indigo R2, purple R4, blue Training, slate Deadhead/GeneralDuty, and green FlightDuty. Duty subtype metadata and typed duty DTOs survive serialization and download updates.
- Route-bearing deadheads retain their flight-card route, flight number, airline, timing, and calendar download details. Assignment classification excludes crew-list context; an operating flight remains a flight when a crew member is deadheading. Reserve and training duties remain separate from overlapping flights.
- Extraction and calendar filters include reserve, training, and deadheads under **Duties only**. **Flights only** includes operating flights. Existing legacy deadhead and training payloads still load; saved results missing assignment evidence require re-extraction to obtain newly supported classifications.

### Problem

R2 had only a generic Duty badge, while other reserve/training assignments could be parsed as flights. Activity codes were stored as flight numbers, and deadhead/training classifications were separate event types rather than duty subtypes.

### Validation outcome (2026-10-12)

Through Sail, all 161 focused resolver, parser, DTO, view-model, card-rendering, calendar-export, Livewire schedule, and extraction-logging tests passed with 1,050 assertions. Coverage includes the supplied noisy R2 text, training/reserve assignments in both trip-information formats, legacy payloads, serialization, overlapping duties, and extraction/export filtering. Pint completed successfully; one application-wide Larastan run passed with zero errors. Boost's version-specific documentation search succeeded.

The production Vite build passed after `npm ci --ignore-scripts --no-audit --no-fund` restored the existing lockfile's packages; installed Tailwind 3 had conflicted with the declared Tailwind 4 version. Dependency manifests and lockfiles were unchanged. Badge markup and light/dark classes were verified programmatically; no manual browser review was performed. `duty_extracted_example.json` was unavailable, so its quoted noisy text was covered by a regression fixture in the parser test. Other TODO sections were preserved.

Commit message: `feat: classify schedule duty subtypes and display reserve badges`

## [x] Completed: Repair Pint commit-hook failure

### Outcome (2026-10-12)

Reproduced Pint's broken SHA512 PHAR signature inside Sail and reinstalled only `laravel/pint` v1.30.5 with Composer, bypassing cache, scripts, plugins, and autoloader generation. Dependency manifests, lockfiles, application files, and the Git index were unchanged.

The repaired executable reports Pint 1.30.5. The existing pre-commit hook passed its focused staged-file Pint check and one staged-file Larastan run with zero errors. No application tests were needed for this vendor-only repair. Existing working-tree changes were preserved; no commit was created.

Commit message: `docs: record Pint installation repair and successful hook checks`

## [x] Completed: Schedule: Cleanup Schedule Notes
## [x] Completed: Schedule: Flight radar option
## Completed: Security email
## [x] Completed: Diagnose PDF OCR failures reported after the server migration
## [x] Completed: Include bug email if an error occurs on upload
## [x] Completed: Replace Imagick PDF page rendering with Poppler
## B. Make OCR resolution configurable
## Don't force PSM 6 for every document
## [x] Completed: FP: Envelope: Include flight date in task
## [x] Completed: FP: Delay reporting
## [x] Completed: FP: Prelim flight plans

### Goal

Detect explicitly preliminary flight plans, omit their weight/fuel presentation from Overview, and qualify planning values in dedicated tasks while preserving source values, units, and the document warning. An absent marker does not establish finality or operational approval.

### Current implementation

- Added the focused `ReleaseStatusExtractor`, `ReleaseStatus` enum, and readonly `ReleaseStatusData`. Detection requires the complete supported header warning at a line/asterisk boundary, tolerating case, whitespace/wrapping, and ordinary dash variants, including the supplied PDF's fused asterisk/header layout. The combined native/OCR text is searched; no page provenance is invented. Incidental preliminary prose, partial warnings, filenames, revisions, final-destination headings, and B043/B044 text do not establish preliminary/final status.
- The first matched warning fragment and status pass through parsed/normalized DTOs, builders, serialization, encrypted result storage, and saved-result rebuilding under `flight_plan_data.releaseStatus`. Duplicate headers do not accumulate document text. Missing/malformed metadata and preliminary metadata without a nonempty string warning load as `Unknown` without losing the brief. A completed detection without the supported marker yields `NotMarkedPreliminary`, with no final/green status badge.
- Added a shared `Preliminary flight plan` warning with `Payload estimated — Do not fuel aircraft` and compact section qualifiers for route, FMS, ETOPS, fuel, planned weights, and release context. Unknown results show a neutral message recommending re-upload. Display copy is derived separately; raw matched evidence stays out of Livewire properties, snapshots, and calculator JSON.
- Preliminary Overview omits Ramp Fuel and Weight & Balance alert cards, their values and accessible labels, and fuel/weight navigator indicators in both desktop and mobile markup. Task links remain available. Route and present ETOPS summaries retain preliminary labels. Unknown and unmarked results preserve existing Overview behavior.
- Dedicated tasks retain source values/units, missing-data labels, conflicts, applicability/availability, copying, and structural-limit alerts. Weight comparisons are qualified as preliminary while aircraft structural limits remain reference values. Ambiguous release-value headings now use source wording. Actual FOB inputs remain actual observations.
- Offline Fuel Score receives only derived status/warning display data. Its prepared HTML retains the warning; the page cache is now `pages-v2`, and activation removes obsolete prepared pages. Updated pages require online re-preparation. Existing owner/result-key isolation, calculator arithmetic, draft version, and draft source identity remain unchanged. An already offline browser receives the worker update only after reconnecting.
- Successful replacement uploads replace status and values together; failed replacements retain the previous result/status. Legacy results require re-upload for classification because their source PDF is no longer retained. No dependency, migration, backfill, or additional persistent model was added. The private PDF and crew details were not committed.

### Problem addressed

Previously preliminary planning figures could appear with the same presentation as another parsed release, and no preliminary metadata survived refresh. Explicit document evidence now persists independently of source-value agreement, task availability, ETOPS applicability, and B44 authorization.

### Validation outcome (2026-10-26)

- Elevated Sail access confirmed running application, MySQL, and Redis services. Installed versions included Laravel 13.34.0, Livewire 4.4.8, PHPUnit 12.5.37, and Smalot PDF Parser 2.12.5. Boost documentation transport closed; official Laravel/Livewire testing and Blade documentation were used as fallback. `.ai/rules` is absent.
- Main focused integration run: **199 passed, 2 skipped, 2,214 assertions**. The supplied private preliminary PDF regression passed; the two skips were existing unavailable private fixtures. Coverage includes header variants/negatives, appended and cached OCR text, encrypted extraction/save/reload, raw-evidence privacy, every direct task route, failed/successful replacement, pounds/kilograms, legacy metadata, preserved missing/conflicting values, and exceeded-limit alerts. The full suite was not run.
- Visual review found the remaining sidebar weight count on preliminary Overview. After suppressing fuel/weight navigator indicators, the preliminary feature and navigation tests passed in the follow-up run. The corrected final `OverviewTest` run passed **5 tests, 199 assertions**, checking the full rendered markup and accessible labels rather than visible text alone. Dedicated tasks retain their counters and B44 badge.
- `vendor/bin/sail node --test tests/JavaScript/offline-fuel-worker.test.js tests/JavaScript/offline-fuel-draft.test.js`: **27 passed**, including obsolete-cache removal, reconnect fallback, warning recovery after preparation/restart, and actual-input draft recovery.
- `vendor/bin/sail bin pint --dirty --format agent`: passed after PHP/test changes. One `vendor/bin/sail bin phpstan analyse --memory-limit=1G --no-progress` application run passed with zero errors. `vendor/bin/sail npm run build` passed. Task-scoped staged/unstaged `git diff --check` passed; unrelated concurrent CREW_BIDS.md whitespace was left untouched.
- Reviewed actual Blade components and production CSS in headless Chrome using synthetic, nonpersistent data: light/narrow Overview, dark/desktop Overview, dark/narrow Weight & Balance, and the corrected desktop navigator. Warning text and section qualifiers were readable, cards/indicators were omitted on Overview, and the dedicated exceeded-limit warning/reference limit remained visible. Reviewed widths were 500 and 1,440 pixels. Live authenticated interaction, physical-device offline reload, and actual scanner OCR were not manually exercised; offline/OCR contracts have deterministic automated coverage.
- Initial concurrent feature processes collided on the existing file-backed SQLite test database. Subsequent integration runs used one sequential process with an isolated in-memory database. The malformed disposable test file was preserved under `/tmp`, reset, and restored by `vendor/bin/sail artisan test --compact tests/Feature/FlightPlanResultStoreTest.php --filter=test_it_persists_an_encrypted_result_with_an_opaque_key` (1 passed, 4 assertions); SQLite integrity_check then returned `ok`. The application database was not migrated.

The main integration command was:

```bash
vendor/bin/sail exec -T -e DB_DATABASE=:memory: -e DB_CONNECTION=sqlite laravel.test php artisan test --compact \
  tests/Unit/ReleaseStatusExtractorTest.php \
  tests/Unit/DTOs/ReleaseStatusDataTest.php \
  tests/Unit/ExtractFlightPlanDataTest.php \
  tests/Unit/FlightPlanTextExtractorTest.php \
  tests/Unit/DTOs/FlightPlanDataTest.php \
  tests/Unit/Actions/FlightPlan/BuildFlightPlanDataTest.php \
  tests/Unit/Actions/FlightPlan/BuildFlightPlanPageDataTest.php \
  tests/Unit/FlightPlanResultSerializerTest.php \
  tests/Unit/View/Models/FlightReleasePageViewModelTest.php \
  tests/Feature/PreliminaryFlightPlanTest.php \
  tests/Feature/FlightPlanResultStoreTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Lifecycle/ExtractionTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Lifecycle/SavedResultTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Workspace/OverviewTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/FmsTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/EtopsTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/WeightAndBalanceTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/EnvelopeTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/FlightInitTest.php \
  tests/Feature/Livewire/FlightPlanBrief/Tasks/MaintenanceLogTest.php \
  tests/Feature/OfflineFuelScoreTest.php
```

Unrelated working-tree changes and concurrent staging were preserved.

Commit message: `feat: identify preliminary flight plans and label planning data`

## [x] Completed: FP: Remove page labels before field extraction

Goal: Remove printed page headers and footers before extracting any flight-plan fields.

Problem: Page-label removal was local to route, waypoint, maintenance-description, and dispatcher-note handling. The shared PDF text reader and extraction coordinator still passed printed labels into identity, fuel, and other field extractors. Weather also used the printed footer as a section boundary.

Current implementation: Added a shared text normalizer for branded, standalone, repeated, fused, and wrapped page labels. PDF text is normalized after native/OCR assembly and cache retrieval, including the uncached/document-text fallback. The coordinator normalizes direct text input before invoking any field extractor. Route, waypoint, and weather entrypoints use the same normalizer. Labels become form-feed page breaks so weather remains bounded; preliminary-warning and alternate-heading detection recognize those boundaries. Existing operational headers and route identifiers remain content. Existing text-cache entries are cleaned on read without requiring another OCR pass.

Regression coverage checks native/OCR/cached input, missing cache keys, label formats, idempotence, every coordinator dependency receiving cleaned text, and upload/save recovery of identity, fuel, primary waypoints, and bounded weather.

Verification (2026-11-01): The final focused Sail integration run passed 128 tests (780 assertions); one existing unavailable private route fixture was skipped. The supplied RJAA PDF regression passed. An earlier run hit the existing 30-second OCR timeout on page 93; the final run passed without changing OCR limits. Pint passed after formatting the coordinator test, and one Larastan run passed with zero errors. Staged and unstaged whitespace checks passed. No full suite or manual browser review was run.

Commit message: `fix: normalize flight plan page labels before field extraction`

## [x] Completed: FP: Fuel Score: Exclude alternate routing

Goal: Extract and present only departure-to-destination waypoints in Fuel Score. Alternate routes, their waypoints, and their TOC/TOD markers remain unsupported.

Problem: In `CKS024231RJAA.pdf`, page 4/5 footers separate the two primary computed-flight-plan header rows. The parser skipped that header and selected the complete header in the alternate section, presenting the VHHH-to-VMMC alternate as the primary route.

Current implementation: Remove page labels before matching waypoint headers and bound extraction before the alternate section before searching for a table. Missing or incomplete primary headers yield no waypoints instead of falling back to an alternate table. Alternate rows and phase markers are excluded from extracted waypoints, source fragments, saved results produced by new extraction, and Fuel Score calculator data. Existing alternate-airport identification and fuel figures retain their separate meanings.

Added a compact route-table fixture, header and alternate-heading regressions, a saved-calculator rendering regression, and an optional regression using the supplied private PDF. The private PDF and crew details are not included in the changes.

Verification (2026-11-01): All 33 focused waypoint-extractor and offline Fuel Score tests passed through elevated Sail access (251 assertions), including the supplied PDF. The regressions reproduced the alternate-table fallback before the fix. Primary waypoints begin with `35464N`, `ARIES`, and `A4L11`, end with `LIMES` and `VHHH`, and retain destination duration 04:12 and planned FOB 19,800 lb. Alternate waypoints and phase markers stay excluded. Pint passed; Larastan passed once with zero errors; `git diff --check` passed. No full suite or manual browser review was run.

Existing saved briefs and prepared offline calculator pages require re-uploading the PDF and preparing offline recovery again to replace previously extracted alternate waypoints; stored results lack the source section needed for safe automatic repair.

Commit message: `fix: exclude alternate routes from primary fuel score waypoints`

## [x] Completed: Preserve iPad multipart uploads outside the service worker

Goal: Allow flight release uploads from Chrome on iPad while retaining offline fuel calculator recovery and private-cache invalidation.

Problem: Production logs show one selected file followed by a multipart request with no parsed files on iPadOS 26.6 / Chrome 156.0.8078.54, from both On My iPad and company OneDrive. Mac Chrome succeeds against the same FPM settings. The worker reissued every same-origin request through fetch(event.request), matching the multipart body-loss path reported in [WebKit bug 319396, comment 7](https://bugs.webkit.org/show_bug.cgi?id=319396#c7). This is a likely trigger, pending confirmation on the affected iPad.

Current implementation: Multipart requests return from the worker's fetch listener without respondWith or fetch, leaving transmission to the browser. Other requests retain existing behavior, including JSON responses that invalidate cached private data after logout, account changes, release replacement, or clearing results. No PHP, parser, upload limit, dependency, or Vite bundle changes.

Verification: The new upload bypass regression failed before the fix. All 26 focused worker and offline-recovery JavaScript tests passed through Sail after the fix, including multipart headers with boundaries and mixed casing, offline recovery, and JSON mutation invalidation. Git diff whitespace checks passed. Pint and Larastan were not needed for this JavaScript-only change.

Production follow-up: Deploy the updated public/offline-fuel-worker.js through the normal release process. On the iPad, open the fuel calculator online to trigger worker registration/update, allow the update to activate, then reload Flight Plan Brief and select the PDF again. Existing skipWaiting/clients.claim activation is retained. Confirm a successful temporary-upload response with one file and one returned path, followed by completion_received. No production deployment or physical-iPad verification was performed here; if the failure persists with the updated worker active, investigate the broader WebKit file-body issue before changing upload limits.

Commit message: `fix: bypass service worker for multipart uploads`

## [x] Completed: Reject empty Livewire uploads and add safe diagnostics

Outcome: Reproduced Livewire 4.4.8 accepting missing/empty temporary-upload collections and returning empty paths, which its single-file completion handler indexes without checking. Added application middleware requiring a nonempty file list before storage, with a recoverable JSON 422 error keyed for Livewire's upload error handling. The endpoint retains signature validation, CSRF middleware, throttling, individual-file validation, and support for multiple files in other components. Flight Plan Brief rejects malformed single-file completions through the normal upload-error lifecycle while preserving signed-reference validation, saved results, and existing parsing.

Diagnostics: Missing/malformed collections and rejected completions emit bounded warnings. `LIVEWIRE_UPLOAD_DIAGNOSTICS=true` additionally logs upload starts, request status, file/path counts, property presence, numeric PHP upload errors, and effective request-process PHP settings. An `X-Upload-Request-Id` response header identifies request logs. Keyed reference hashes correlate successful temporary uploads with completion calls; repeated hashes for the same component identify repeated completion requests. These records exclude original filenames, PDF data, signed references/URLs, and authentication tokens. Normal lifecycle logging is disabled by default.

Verification: Through Sail, 39 distinct focused endpoint, upload lifecycle, extraction, and saved-result tests passed; one unavailable private fixture was skipped. Coverage includes missing/malformed requests and completions, rejected PDFs, PHP upload errors, signed-reference rejection, multi-file compatibility, successful single uploads, retry and repeated uploads, preserved saved results, and diagnostic privacy/correlation. The existing full-PDF fixture hit a 30-second OCR timeout on page 103 during one run, then passed in isolation without an OCR change. The single Larastan pass reported two related type diagnostics in the file-error logging callback: Laravel's file() annotation assumes a flat file list. Changed inspection to Symfony's file bag so malformed nested multipart entries remain representable, and reran 14 focused request/diagnostic tests including a new nested-entry regression (all passed, 55 assertions). Static analysis was not rerun, honoring the one-pass rule. Pint passed before this final correction; final formatting follows it.

Production follow-up: Deploy through the normal release procedure, rebuild configuration/route caches, and reload PHP-FPM. Temporarily enable diagnostics and refresh configuration to investigate the missing-file trigger. Verify successful PDF upload, invalid-file feedback, retry, and repeated upload in a fresh browser session. Empty requests should return 422 instead of successful empty paths; completion errors should release the loading state. Check the web-request `php_sapi` and limits in diagnostics, and identify whether K4's Nginx server/location uses the observed 20M or 30M limit. The supplied `sudo -u www-data php` check still used CLI. Production's intermittent file-loss trigger and real-browser recovery remain to be confirmed; no deployment or production configuration changes were performed here.

Commit message: `fix: reject empty Livewire uploads and add safe diagnostics`

## Completed: FP: Flight-plan block split across PDF pages

### Goal

Allow `CKS020426RKSI.pdf` to upload and extract its route successfully.

### Current implementation

Page labels are removed from the complete ICAO flight-plan block before matching its fields. Added a portable text regression and an upload test using the private supplied PDF when available.

### Problem addressed

The equipment field ends on page 2 and departure starts on page 3. Intervening page labels caused full flight-plan matching to fail before the existing route-only cleanup could run.

Validation (2026-10-26): The new text test reproduced the reported error before the fix. Focused Sail route tests and the supplied-PDF upload test passed: 33 passed, one unavailable private fixture skipped, 129 assertions. The supplied release completed processing and displayed RKSI/VHHH. Pint passed for changed PHP files; one Larastan run passed with zero errors. No full suite was run.

Commit message: `fix: parse flight-plan blocks split across PDF pages`

## Completed: FP: Delay reporting: Departure UTC time format

- [x] Format delay reporting UTC timestamps as `2026-10-26 03:47z`.

### Goal

Use `YYYY-MM-DD HH:mmz` for UTC date/time displays in delay reporting.

### Current implementation

Scheduled departure, report timeline and delay endpoints, autosave status, and saved history timestamps now share the requested format. Departure timestamps are converted to UTC before display; missing or unreadable values show `Not recorded`. Stored snapshots and native datetime input values retain their existing formats.

### Problem addressed

Scheduled departure previously displayed raw ISO text such as `2026-10-25T01:00:00+00:00`, while other delay timestamps used inconsistent suffixes and precision.

Validation: Sail focused tests passed (38 tests, 217 assertions), Pint passed, and Larastan passed once with zero errors. Existing unrelated working-tree changes preserved.

Commit message: `fix: standardize delay reporting UTC timestamps`
