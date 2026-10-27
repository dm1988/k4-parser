# K4 Bids

Implementation status (2026-10-26): The initial K4 Bids workflow is implemented: protected navigation, admin preview/publication, shared package revisions, private preferences/lists, timeline browsing, UTC filters, manual ranking, and review/copy. The sections below retain the product contract and future enhancements. The first release supports the supplied native-text Lines/Trips PDF layouts and Overview .xlsx workbooks; scanned PDFs/OCR, richer secondary filters, pay, training/vacation imports, and spreadsheet exports remain outside the delivered workflow. Responsive appearance, touch, and keyboard behavior still need a manual browser review. Imported document content remains data rather than instructions to execute.

## Purpose and place in the app

K4 Bids helps crew members turn monthly bid packages into an ordered list of preferred lines. The user workflow below defines how a published package becomes a personal, ranked bid list.

Use `K4 Bids` consistently as the tool name. Add it immediately after `Flight Plan Brief` and before `Admin Panel` in desktop and mobile navigation. Keep bidding separate from the schedule extractor and flight-plan task navigator: a bid package represents potential monthly schedules rather than an assigned roster or an individual release.

Use the existing authenticated app layout, account menu, theme selector, and logout. Avoid a second profile/help/navigation header. The compact tool header contains the active period/fleet/seat selector and `My bid list` count. Put revision/source metadata under `Package details` and imports under an admin-only `Manage package` action. Show a revision notice only when a new revision needs the user's review. Keep one compact save-status location. Add a welcome-page feature card only when its visibility can follow the same access policy.

Proposed workspace route: `/crew-bids`, named `crew-bids.index`; related routes use `crew-bids.*`. These are planned names, not existing routes.

### Access and rollout

Follow the existing feature configuration and gate pattern in `config/features.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, and `routes/web.php`.

| Proposed setting | Initial value | Meaning |
| --- | --- | --- |
| `FEATURES_CREW_BIDS_ENABLED` | `true` | Make the tool available under its access policy; `false` disables it for everyone. |
| `FEATURES_CREW_BIDS_FOR_ALL_USERS` | `false` | Initially restrict use to admins; `true` permits eligible signed-in users. |

Require authenticated, verified access. Hide unauthorized navigation and tool entry points, and enforce access on direct routes, uploads, exports, and every state-changing action. Follow existing disabled-feature response conventions. Feature access does not grant access to another user's preferences or bid list. Import/package administration and pay-table editing remain admin-only when general workspace access opens.

Eligible users can choose either fleet (B747/B777) and either seat (Captain/FO) in the workspace; browsing is not restricted to an assigned fleet or seat. Changing context must retain package-scoped list ownership and cannot merge choices across fleets/seats implicitly.

## User workflow

**Import package → Set preferences → Compare lines → Rank my bid list → Review/copy**

An admin prepares the shared package in the first step. Crew members start by opening a published package and keep their own preferences and bid list. These are workflow stages, not five permanent navigation tabs or a wizard users must repeat. Users can move between comparing, ranking, and reviewing without losing their work.

### 1. Import and open a package

**Admin:** Open `Manage package`, upload the supported Lines and Trips PDFs plus Overview spreadsheets, and review the detected period, fleet, seat applicability, revision, and file coverage. Resolve mismatched or failed files before publishing the usable package. A failed import preserves the previous published package.

**Crew member:** Open `K4 Bids` and choose the published period, fleet, and seat. Both B747/B777 and Captain/FO contexts are available to eligible users. Import controls appear only for admins. With no published package, show `No bid package available yet`; the user has no upload step to complete.

**Result:** The workspace is bound to one package revision and the user's private preferences/list. Package statistics and source metadata remain under `Package details` unless a problem affects the user's next action.

### 2. Set preferences

On the first visit to a package, offer a short guided setup with a visible Skip option. Let the user order goals such as maximum days off, maximum credit, and fewer work blocks. Each lower-priority goal only breaks ties in the goals above it.

Let the user select required UTC days off by tapping individual dates or using the optional range selector, and optionally choose a required start city. These are hard filters. Any occupied UTC date conflicts, including full layover/rest days between trip report and release. Returning users open results immediately with their saved choices; `Edit preferences` reopens the setup.

**Result:** Show one compact editable summary above the results. Preference changes update results immediately with brief loading feedback and autosave independently. Skipping setup allows browsing without requiring goals or exclusions.

### 3. Compare lines and build a shortlist

Browse `Lines` and `Reserve`; use `Trips` when pairing detail is needed. Start with compact rows showing Line ID, credit, days off, an occupied/off/unknown monthly timeline, and Add or the selected rank. Inspect a date for its activity details or expand rows for supporting information. Several rows can remain open for comparison. On phones, keep the full month visible.

Sort or refine filters as needed. Confirmed days-off conflicts and start-city mismatches disappear from available results. Lines whose dates cannot be fully established remain visible with `Dates incomplete; conflicts unknown`; never present them as verified matches. If nothing matches, explain that state and offer filter reset.

Select `Add` to append a line to the bid list. Show its rank and a brief Undo action while leaving the user browsing. Duplicate additions are prevented, and flying/reserve choices can share the same list. Changing browsing sort or filters does not reorder the user's choices. Already selected choices that acquire a conflict remain selected with a visible warning.

**Result:** The user has a saved shortlist scoped to the active month, fleet, seat, and revision. `My bid list (n)` remains within reach without taking permanent space beside the results.

### 4. Rank my bid list

Open `My bid list (n)`. Desktop uses a drawer; phones use a full-screen panel with `Back to lines`. Each entry shows rank, Line ID, credit, days off, and applicable warnings.

Drag choices into order or use the accessible Move controls. Remove unwanted choices deliberately. Rank 1 is the user's first preference, followed by a contiguous sequence. Automated match ranking helps browsing; the saved bid order remains manually controlled.

Changes autosave with clear saving/saved/failure feedback. A failed save retains edits and offers Retry. Returning to lines restores the prior browsing position and filters, so users can add more choices without restarting.

**Result:** A personal ordered list combining flying and reserve lines in the exact desired sequence. `Review / Copy` in the list footer leads to the final check.

### 5. Review and copy

Review the active month, fleet, seat, package revision, exact numbered Line IDs, and any conflict, incomplete-date, or unavailable-choice warnings. Return to preferences or the bid list to revise choices as needed. Keep package/source details expandable rather than repeating the browsing screen.

Confirm the displayed order is saved before treating it as the completed output. Keep unsaved/failed-save status visible and provide Retry; do not claim that unpersisted edits are saved. Copy the numbered Line ID list, show success/failure feedback, and offer selectable text for manual copying if clipboard access fails. Keep warnings visible in the review while excluding UI metadata from the copied numbered list.

**Result:** A reviewed, copyable bid order. Copying does not submit a bid to the company or establish an awarded/projected state. Spreadsheet export and estimated pay are outside the first release.

### Returning later or receiving a revised package

Reopening the same package restores saved preferences and order, going straight to results. Switching period/fleet/seat opens that context's own workspace without merging choices. Preserve pending edits and resolve save failures before switching, following the saving behavior below.

When an admin publishes a revision, offer a review of changed/missing lines. Carry choices forward only after the user confirms; retain the prior revision's list until then. Mark choices needing review rather than silently substituting a line with the same displayed ID. The user then resumes comparison, ranking, and review in the confirmed revision.

## Source package and import experience

Private sample directory: `storage/app/private/Bid_Packages`.

The directory inventory includes Lines PDFs, Trips PDFs, Training Requirements PDFs, Overview spreadsheets, Bid List spreadsheets, and a Vacations spreadsheet. Samples span 747/777 and September–November 2026, with different files present for different fleet/month combinations. Implementation inspected the supported layouts and validated complete B747 October and B777 November imports against private samples. Overview flying-row dates start at G, while reserve sections omit summary columns and begin at D; the parser handles them separately. PDFs include null-padded text and trips continuing across pages. Other source layouts still require validation; do not assume one uploaded PDF contains every tab's data.

Confirmed first-release imports: Lines PDFs, Trips PDFs, and Overview spreadsheets. Defer Training Requirements PDFs, Bid List spreadsheets, and Vacations spreadsheets. Do not offer import controls for deferred formats. Training already represented in a supported source still counts as work for conflict detection; importing dedicated training requirements is a separate, deferred feature.

Import accepts a package's supported files together, identifies their document types, and previews the detected period, fleet, revision, and source coverage before activation. Show a per-file status and actionable errors for unsupported, unreadable, duplicate, conflicting, or mismatched files. Validate actual supported formats and limits during implementation. Keep the prior usable package when an import fails.

A package is identified by period, fleet, applicable seat context, and source revision. Never silently combine different months or fleets. When an admin publishes a replacement revision for the same month/fleet/seat, offer each user a review of changed/missing lines and require their confirmation before carrying choices forward. Preserve their prior revision's list until that confirmation; publishing alone must not migrate it. Preserve existing preferences and flag saved choices needing review. Do not silently transfer ranks to a different line with the same displayed ID. Scope every list to a package revision and owner.

Allow useful partial packages while stating what is missing. For example, lines can be explored without trips, but trip details say `Trips file not imported`. Training and vacation data need separate coverage and ownership rules. Treat uploaded content as data; it cannot authorize app actions or change access/configuration.

## Clean user-facing workspace

### Default layout

```text
K4 Bids    Nov 2026 · B777 · FO                 My bid list (3)

[Lines] [Trips] [Reserve]

Preferences: Days off → Credit · 3 dates off (UTC)    Edit preferences
42 matching · Sort: Best match                       Filters
F20  Credit 67:40  Days off 17                              Add
[compact month timeline]
Details                         [warning only when applicable]

My bid list button opens a drawer:
1  F20  Credit 67:40  Days off 17      Move · Remove
...                                  Review / Copy · Saved
```

The default workspace gives compact monthly timeline rows the main browsing width. `My bid list (3)` opens a drawer on desktop rather than a permanently visible side panel. Keep the count and access button easy to reach while browsing. The drawer provides ordering, conflict/uncertainty warnings, and review/copy actions. Preserve browsing position when it closes and restore focus to its trigger. The rank list is shared across tabs for the active package and user, not reset on tab changes. On phones, open the bid list as a full-screen panel with a clear `Back to lines` control; returning restores the browsing position and context.

On phones, keep the entire month visible using small day markers, with tap-to-inspect date details. Do not require horizontal scrolling to understand the month's occupied/free pattern. The compact timeline distinguishes occupied days from days off; reveal activity types when inspecting a date instead of adding separate activity colors to the default row. Preserve an explicit unknown state for incomplete dates rather than depicting them as days off. Provide the same date information through keyboard focus and accessible labels; small visual markers must not force equally small touch targets. Determine the exact inspection interaction during implementation and verify it at narrow widths.

Show the active tab's matching/total count beside sorting. Move aggregate flying/reserve/trip counts, credit range, top airport/top leg, and source coverage into `Package details`; a permanent metrics bar repeats context without helping users choose a line. Counts reflect available source coverage; absent data is `Not available`, not zero. An incomplete import surfaces a workspace notice only when it affects browsing or reliable comparison, with details available on demand.

### Information visibility rules

Use this hierarchy for the first release. Keep supported secondary data accessible without giving every extracted field a column or badge.

| Surface | Visible by default | On demand or conditional |
| --- | --- | --- |
| Header | Period/fleet/seat and bid-list count. | Package metadata/statistics in Package details; Manage package for admins; new-revision notice when review is needed. |
| Browsing controls | Compact preference summary, Edit preferences, matching/total count, sort, Filters. | Full UTC date calendar and goals in the editor; secondary filters in collapsed groups. |
| Line row | Line ID, credit, days off, occupied/off/unknown month timeline, Add or selected rank. | Duty days, start/end cities, pairings, first report, crew complement, deadheads, layovers, carry-in/out, and match explanation under Details. |
| Date inspection | Selected date and its activity/occupancy details with time basis. | Further trip information through its related detail view. |
| Bid-list entry | Rank, Line ID, credit, days off, move/remove controls, applicable warnings. | Schedule details through the related line; bulk/destructive actions in a secondary menu. |
| Review | Package context, exact numbered list, applicable warnings, copy action and save status. | Revision/source details in an expandable section; copied text contains the agreed numbered Line IDs rather than UI metadata. |

An active goal/filter can make one related secondary value pertinent: for example, show work-block count when fewer blocks is a selected goal, or start city when inspecting a city-filter result. Reuse the detail value rather than adding a permanent column. Do not repeat the same date selection as a full calendar, long chip list, and prose summary simultaneously: show a compact count/range summary that opens the editor.

Conflict, incomplete-date, unavailable-choice, and failed-save messages stay visible where a user acts on the affected data. Use concise text with expandable explanation; never collapse the only warning into a tooltip. Normal successful matches do not need a badge on every row. Keep a single timeline legend and time-basis label per browsing surface, with accessible date labels carrying the same meaning.

Deferred features have no disabled toggles, empty columns, or Coming soon badges in the first release. Omit pay, hazard pay, fatigue scoring, Training imports/tab, award/projection controls, and spreadsheet export until supported. Keep their planning notes in this document rather than in the user's workflow. Assigned crew names are not primary comparison data; keep them out of default rows and introduce a detail display only after visibility policy is resolved.

Use Aviation Blue `#1B365D` for structure, Compass Gold `#C5A059` for primary actions, and the existing light/dark theme tokens. Use monospaced codes, times, credits, and ranks. Airport codes remain prominent; reuse the existing airport-detail popover where suitable. Use text labels as well as color for conflicts and status.

### Tabs and information hierarchy

| Tab | Primary purpose | Initial content |
| --- | --- | --- |
| Lines | Compare flying lines and add choices. | Compact rows, monthly timeline, preference matches, expandable details. |
| Trips | Inspect pairings referenced by lines. | Trip ID, route, dated legs, report/release times and rest when present; link back to related lines. |
| Reserve | Compare reserve lines. | Reserve pattern, duty/off days, credit when supplied, add-to-list action. |
| Training (later release) | Review imported training requirements. | Deferred with dedicated Training Requirements imports; omit this tab from the first release. |

Keep tab names stable. A supported tab with no imported source states what is unavailable; admins can access Manage package, while other users are told that the package awaits an admin update. Do not imply the package contains no trips or prompt ordinary users to upload. Omit deferred tabs rather than prompting for unsupported files. When introduced, Training is reference information; do not treat it as a selectable bid line without source evidence supporting that workflow.

Confirmed default line view: compact rows with a monthly timeline, expanding a row for details. Allow multiple rows to stay expanded for comparison; opening another row does not close earlier rows. Default row: **Line ID · Credit · Days off · Month timeline · Add or selected rank**, plus applicable warnings. Expand for duty days, start/end cities, pairing IDs, crew complement, carry-in/out dates, deadheads, layover/rest, first report time, match explanation, and source details. Assigned crew display awaits its visibility policy. Keep table/extra-column alternatives out of the default toolbar; introduce supported display options only if they add value beyond the confirmed timeline view. All views must share filters and selections.

### Preferences first

Start with plain-language controls rather than the complete filter catalog:

Offer guided preference setup on the first visit to a package, with a visible option to skip it and browse without chosen preferences. The guide covers ordered goals, requested UTC days off, and start-city filtering; keep advanced filters secondary. After setup, show a compact editable summary above results. Returning users go straight to results using saved preferences, with an `Edit preferences` button to reopen the guide. Do not rerun setup automatically on every visit or interpret skipping as an instruction to overwrite saved choices.

- **Goals, ordered by priority:** Maximize days off, Maximize credit, or Prefer fewer work blocks. Carry-in preference is available only after its comparison rule is defined.
- **Must match:** Specific UTC days off and, optionally, a specific start city. Keep these exclusions distinct from ranked goals.
- **Days to keep off:** A collapsible UTC monthly calendar supporting individual date taps and an optional date-range selector, with selected dates listed in text and a `Clear dates` action. Both selection methods edit the same saved date set and support keyboard use.

Users can select several goals and order them by priority. Compare goals strictly in that order: a lower-priority goal only breaks a tie in all higher-priority goals. For example, when days off precedes credit, 18 days off beats 17 regardless of credit. `Prefer a long work block` means grouping duty days together into fewer separate work blocks. Define the exact block-counting rules during source discovery, including month boundaries. Explain matches in ordinary language, such as `17 days off · avoids all 3 selected dates`. Preserve a stable Line ID final tie-break. Manual bid order always stays under the user's control when preferences or browsing sort change.

Selected days to keep off are hard exclusions: automatically exclude conflicting lines from available results. Normalize work intervals to UTC calendar dates before checking conflicts. The user confirmed that all supplied dates/times are UTC and blank Overview date cells mean days off. Any work on a selected UTC date counts, including reserve, training, deadheads, and carry-in/out work. Label the selection calendar and conflict dates as UTC. Use the single compact preference/filter summary with matching/total counts. Keep reset actions inside the relevant editor, surfacing a clear reset action in the no-matches state. Keep already selected lines in their existing order when newly chosen days conflict; visibly flag those conflicts and let the user remove the choices. Other preferences need their own inclusion/ranking definitions. Omit hazard-pay controls until eligible stations/dates and rules are supported.

Every UTC calendar date from trip report through release counts as occupied, including full layover/rest days without scheduled flight or duty. Those rest days neither satisfy a requested day off nor split a work block. Use this occupancy rule consistently in date conflicts and block grouping; establish exact timestamp endpoint handling and month-boundary counting during source discovery. Keep occupied dates distinguishable from flight/duty activity in the expanded schedule.

Selecting a specific start city is also a hard filter: exclude lines that do not start in that city, rather than lowering their rank. Present it as `Start city` in the filter controls so its exclusion behavior is clear. No selected city means no start-city restriction.

If occupied dates cannot be fully established, keep the line visible while days-off filters are active with the explicit warning `Dates incomplete; conflicts unknown`. Missing dates are not evidence of a match: do not label the line conflict-free or claim that it avoids the selected dates. Continue to exclude lines with a confirmed conflict, even if other dates remain incomplete. Carry the uncertainty warning into the bid list and review when such a line is selected.

### Advanced filters and display options

Keep secondary controls in grouped, collapsible sections. Generate values from the active package rather than fixed lists. Each inclusion filter offers `Any`, `Only`, and `Exclude` where meaningful; avoid ambiguous `Hidden` labels.

Update results immediately after each preference/filter change, with a brief loading indicator; there is no Apply step. Preserve focus and the control's input while results refresh. Prevent older responses from replacing newer filter results, and keep the bid list's manual order unchanged. Distinguish results loading from persistence status so updating matches does not imply preferences have finished saving.

| Group | Planned controls |
| --- | --- |
| Credit and crew | Credit range/sort; below/at-or-above a configured guarantee; crew complement. Original examples include 1 CA/1 FO, 1 CA/2 FO, 2 CA/1 FO, 2 FO only, 4 CA/4 FO. |
| Work pattern | Duty days, days off, carry-in, split lines, deadheads, long stays, first report/start time. Original day thresholds: duty 14/15/16 and off 15/16/17; do not hardcode these. |
| Airports | Start city, end city, layover/rest city, and avoided airports, grouped by region when useful. |
| Display | Default compact monthly timelines with expandable details. Any later extra-column/view controls live in a secondary menu and expose only supported data. |
| Sort | Default Best match with configured goals; otherwise Line ID. Keep credit, days off, and Line ID readily available. Put supported duty-day, carry-in, start-date/time, deadhead, or rest-duration sorts in secondary options only when the underlying data and definitions are dependable. |

Retain fatigue-score sorting, breakfast-friendly departures, and long-stay detection as later features requiring explicit definitions and dependable inputs. Do not manufacture a score or assign a morning cutoff without a time basis. Distinguish package dates, UTC, and station-local times; show the relevant basis beside the calendar/times and handle month boundaries explicitly.

Original airport examples are discovery input, not authoritative region or eligibility data: ANC, ATL, BKK, BOG, BRU, CVG, EMA, GYD, HAN, HKG, HNL, ICN, JFK, LAX, LEJ, LGG, MIA, NGO, NRT, ONT, ORD, PVG, SCL, SDF, SIN, TLV, TPE, VCP, YHM, YYZ. Resolve airport details using existing airport reference data where possible.

## Ranking, saving, and review

Use `My bid list` for the user's ordered choices and `Available lines` for browsing. Selected lines remain recognizable in the browser. Show `Unavailable in this revision` only when source/revision evidence establishes that state; it is distinct from filtered out, unsupported, missing details, or not awarded.

Flying and reserve lines share one ordered bid list within the same month, fleet, seat, and package revision. Switching between Lines and Reserve preserves that shared ranking; do not create separate rankings by line type. Retain each choice's type and unique source identity even if displayed line numbers overlap.

Each bid-list entry shows **Rank · Line ID · Credit · Days off**, plus any conflict or incomplete-date warnings. Keep reorder/remove controls accessible without expanding details. The default entry does not need a miniature timeline; use the browsing row's expanded details for schedule inspection. Missing credit/day counts remain explicitly unavailable rather than zero.

Provide Add/Remove, drag-and-drop ranking, and equivalent `Move up`, `Move down`, and `Move to position` controls through one compact Move control per entry rather than displaying three separate buttons. Keep ranks contiguous and line choices unique. Sorting available lines never reorders the bid list. Put `Show selected`, `Add all matching lines`, and `Clear bid list` in a secondary actions menu. Bulk additions operate on an explicit scope, report how many are added, and skip duplicates. Confirm destructive clearing and offer undo where feasible.

Adding a line appends it to the end of the current bid list, shows its resulting rank and a brief Undo action, and keeps the user in the browsing view. Do not open the bid-list drawer automatically or ask for a rank before adding. Reflect selection and rank on the row, announce the outcome accessibly, and keep save status accurate. Undo reverses that addition through the same persistence flow without discarding subsequent unrelated choices.

Autosave selections, order, and preferences with `Saving…`, `Saved`, `Unsaved changes`, and `Save failed — Retry` feedback. Use one compact save-status location in the current surface, rather than repeating Saved badges per row. The browsing action is `My bid list (n)`; put `Review / Copy` in the drawer/full-screen list footer instead of a second floating browsing action. Save failures remain visible with Retry after a drawer closes. A sticky footer must not obscure rows, error messages, or keyboard focus. Do not show `Saved` until persistence succeeds.

Preserve unsaved edits after errors, flush pending saves before in-app package switching, and warn on close when edits remain unsaved. Detect conflicting edits from another tab/device rather than silently overwriting. Offline editing is outside the initial scope.

Review displays the package identity/revision, exact ordered Line IDs, conflicts, and save status. Confirmed first-release output: a numbered list users can review and copy, with a preview and manual-copy fallback. Spreadsheet export is deferred; a future company-compatible export needs an agreed schema and review of the Bid List workbook. `Submitted`, `Awarded`, and `Projected` require explicit imported evidence or a separately designed recording workflow. Copy/export does not establish that the company received a bid.

## Credit and estimated pay

Estimated pay is explicitly deferred from the first usable release, including pay tables, dollar amounts, and pay sorting. The first release focuses on credit, days off, preferences, and ranking. For the later feature, use `Estimated pay` in the interface; retain MoneyLine as a possible later product label. Keep pay optional and secondary to credit and days off.

The original proposal requests separate Captain and First Officer pay tables, a 64-hour guarantee, and carry-in paid at 1.5 times the rate. These are unverified product assumptions, not confirmed compensation rules. Do not implement or advertise resulting dollar amounts until source rules and effective dates are supplied and validated.

Plan effective-dated fleet/seat/longevity rate entries managed by admins, with user-selected pay context kept private. Show the rate, currency, effective period, eligible credit, guarantee treatment, carry-in basis, and any premium separately. Store credit as integer minutes and format consistently as `67:40`, never decimal `67.40`. Use exact monetary arithmetic and an agreed rounding policy.

The proposed display is `Line estimate + Carry-in estimate = Total estimate`, available only when both components can be calculated without double counting. Determine whether carry-in is already in line credit, what the multiplier applies to, guarantee interaction, reserve/training treatment, and whether hazard pay is additive. Missing rates or unresolved rules yield `Estimate unavailable`, not a zero or invented estimate. Explain which taxes, deductions, and other pay items are excluded once the formula is defined.

## App organization for implementation

Reuse the existing Laravel/Livewire and Blade architecture. These are responsibility boundaries to fit existing directories, not a requirement to introduce every abstraction in advance:

| Responsibility | Proposed home and boundary |
| --- | --- |
| Feature policy | Existing feature config, User access method, application gate, and protected route group. All entry points use the same decision. |
| Workspace interaction | A focused `app/Livewire/CrewBids.php` entry component with extracted child components when needed. Pass view data to Blade; keep authorization, parsing, and calculations outside templates. |
| Import pipeline | Focused actions under `app/Actions/CrewBids` and source-specific parsers under `app/Services/CrewBids`; reuse existing text/OCR infrastructure after compatibility review. |
| Normalized data | Typed DTOs under the existing `app/DTOs` structure for package context, lines, trips, source coverage, and warnings. Preserve source references internally. |
| Preferences and ranking | Server-side filtering/ranking actions with deterministic outputs, separate from the user's saved manual order. |
| Saved workspace | Package/revision records plus owner-scoped preferences and ordered line selections. Final schema follows inspection of source documents and existing model conventions. |
| Presentation | `resources/views/livewire/crew-bids.blade.php` and reusable components under `resources/views/components/crew-bids`, using existing layout, buttons, popovers, and theme patterns. |
| Pay preview | A separate calculation service and managed rates, added only after compensation rules are validated. |

Keep source documents private and define retention before enabling uploads. Scope access to imported crew/vacation information deliberately; general tool access does not automatically make personal records public. Public Livewire state should contain only necessary UI data and validated identifiers, not full raw documents. Validate identifiers, ownership, source context, and feature access again when saving or exporting.

## Delivery sequence

1. **Source discovery and access foundation.** Confirm Sail before code changes, inspect installed versions and applicable rules, then inspect representative Lines PDFs, Trips PDFs, and Overview spreadsheets privately. Record field maps, fleet/month boundaries, time bases, revision behavior, and supported/missing source coverage. Establish the feature policy and empty workspace. Leave deferred document discovery for its corresponding enhancement.
2. **Import and browse.** Implement validated imports for Lines/Trips PDFs and Overview spreadsheets, normalized package data, summary counts, Lines/Trips/Reserve states, compact line rows, and expandable source details. The first usable release must support B747 and B777, for both Captains and First Officers. Validate representative packages for both fleets and both seats before release.
3. **Preferences and saved bid list.** Add primary goals, date conflicts, advanced filters, deterministic explanations, accessible ordering, owner-scoped autosave, revision reconciliation, and review/copy.
4. **Validated enhancements.** Add pay preview after approved rules/rates, company-compatible exports after format review, and supported training/vacation conflict integrations. Add fatigue, breakfast-friendly, hazard-pay, award/projection, and detailed operational analytics only when their definitions and sources are established.

The initial useful release includes browsing, preferences, a persistent ordered bid list, and review/copy. Pay estimates and speculative scoring do not block that workflow.

## Acceptance criteria for implementation

- Eligible admins see K4 Bids after Flight Plan Brief on desktop/mobile; unauthorized users cannot see entry points or use direct/action routes. Disabling the feature blocks everyone; general access still isolates private lists and admin-only management.
- Imports identify fleet/month/source coverage, reject mixed or invalid files clearly, preserve the active workspace on failure, and flag affected selections when a revision changes.
- A new package revision offers a change review and requires user confirmation before carrying choices forward; publishing does not silently migrate the prior list.
- With days-off filters active, confirmed conflicts are excluded, while lines with incomplete dates remain visible with `Dates incomplete; conflicts unknown`. Selected uncertain lines retain that warning in the bid list and review.
- Flying and reserve choices can be interleaved in one saved ranking for the same month/fleet/seat/revision, with unique identities preserved across line types.
- With no published package, admins see `Import a bid package` under Manage package, while ordinary users see `No bid package available yet` with no upload action. Processing has progress feedback; missing sources explain affected functionality and offer admin-only management; no matches offers filter reset; an empty list explains how to add a line.
- Default rows contain only Line ID, credit, days off, timeline, Add/selected rank, and applicable warnings. Supporting data stays in Details, with one relevant metric optionally surfaced for an active goal/filter. Package statistics and revision/source metadata stay under Package details; deferred controls are absent.
- Compact rows, calendars, optional details, sticky headers/filters, and bid-list controls work at mobile and desktop widths in both themes without overlapping content. Keyboard users can select dates, switch tabs, expand details, add/remove, and reorder. Announce save/reorder outcomes and preserve visible focus.
- Filtering and sorting never change manual ranks. Duplicate choices are prevented, hidden selections remain in the list, package/owner boundaries hold, and reload restores saved order/preferences. Failed saves and conflicting edits do not claim success or lose edits.
- Credit, dates, day counts, and summary metrics have documented definitions. Missing values stay distinguishable from zero. Pay and source-backed statuses appear only with sufficient inputs/evidence.
- Review/copy matches the persisted ordered list and active package revision, with clear feedback and a selectable fallback if clipboard access fails.
- Run focused regression coverage for import failures, normalization, time/month boundaries, filtering/ranking, persistence/conflicts, ownership, and feature policy. Test pay calculations only when implemented. Verify responsive and keyboard behavior manually; run Pint after PHP edits and Larastan once at final integration. Record actual results in the existing TODO entry.

## Confirmed planning decisions

Confirmed by the user on 2026-10-26:

- Admins import shared bid packages. Each user keeps private preferences and bid lists. Shared package visibility does not by itself settle access to personal crew/vacation/training records.
- The first usable release supports B747 and B777, for both Captains and First Officers.
- Selecting days to keep off automatically excludes conflicting lines from available results, rather than merely reducing their rank.
- Already selected choices remain in place with conflict flags when days-off selections change; removal is the user's choice.
- Users can order several goals by priority. Each lower-priority goal only breaks ties in the goals above it.
- Convert all work intervals to UTC calendar dates for days-off conflicts. Any work on a selected UTC date conflicts, including reserve, training, deadheads, and carry-in/out work.
- A preferred long work block means duty days grouped together with fewer separate work blocks.
- Every UTC date from trip report through release is occupied, including full layover/rest days; those days count for days-off conflicts and do not split work blocks.
- Eligible users may browse both fleets and seats using workspace selectors.
- A selected start city excludes nonmatching lines; it is a hard filter.
- First-release imports are Lines and Trips PDFs plus Overview spreadsheets. Other document types are deferred.
- First-release output is a numbered list for review and copying; spreadsheet export is deferred.
- Estimated pay is deferred; the first release delivers credit, days off, preferences, and ranking.
- Package revision migration requires a review of changes and user confirmation before carrying choices forward.
- Lines with incomplete occupied dates remain in results while days-off filters are active, clearly labeled `Dates incomplete; conflicts unknown`; known conflicts still exclude them.
- Flying and reserve lines share one ranking within the same month, fleet, seat, and revision.
- Default comparison uses compact rows with monthly timelines and expandable details.
- On desktop, `My bid list` opens a drawer; there is no permanently visible bid-list panel.
- Offer skippable guided setup on a package's first visit, then an editable summary. Returning users open results immediately with saved preferences and an Edit button.
- Mobile timelines keep the full month visible with small day markers and tap-to-inspect details.
- UTC days-off selection uses individual date taps with an optional date-range selector.
- Multiple line rows may stay expanded at the same time for comparison.
- Adding a line appends it, shows its rank and a brief Undo action, and leaves the user browsing.
- The mobile bid list opens as a full-screen panel with a clear `Back to lines` control.
- Preferences and filters update results immediately after each change with a brief loading indicator, without an Apply step.
- Compact timelines show occupied days versus days off; activity types appear on date inspection, and incomplete dates retain an unknown state.
- Reserve line IDs and known occupied-day markers use orange; flying-line occupancy stays blue. Off days use a light neutral treatment and unknown dates use a visible `?` marker with a distinct background. The shared UTC legend and accessible line-type labels explain these meanings in both themes.
- Bid-list entries show rank, Line ID, credit, days off, and any conflict or uncertainty warnings by default.
- All supplied package dates/times are UTC; a blank Overview date cell means a day off, as confirmed during implementation.

## Decisions to resolve during discovery

- Which package periods/layout revisions must the first release accept within the confirmed Lines/Trips PDF and Overview spreadsheet formats?
- What is the intended visibility of assigned crew, vacations, and training records within shared packages?
- For richer secondary filters, confirm carry-in, split-line, long-stay, and availability definitions. The initial workflow distinguishes source duty counts from UTC occupied dates and counts contiguous occupied blocks within the selected month. Additional layouts need validation against that rule, including report/release and year-boundary behavior.
- Beyond the confirmed days-off and start-city exclusions, which preferences are hard exclusions?
- For the deferred pay feature: what are the verified pay tables, longevity rules, guarantee/premium interactions, and rounding rules?
- For deferred spreadsheet export and status features: what exact bid output does the company accept, and what evidence can establish submitted/awarded/projected state?

These decisions constrain dependent implementation. The proposed layout and delivery sequence remain reviewable without inventing their answers.

## Follow up #1
### [x] Render carry in days. 
These are days that extend past the calendar month. Add a box to the right with a count of carry in days preceded with a +. I.e. `+3`

### [x] Integrate trip details into lines
Provide trip info along with the line. Display start and end airports and layover airports. Render below calendar date. Display full trip details when the `details` accordion is expanded

### Live Matching & Scoring Breakdown
What's missing: You have a sorting filter for "Best match" and preferences like Maximize credit and 3 dates off.

Improvement: Add a subtle "Why this matched" score tooltip or badge (e.g., displaying a percentage or point breakdown) next to the line ID (like F38). Showing how closely a line aligns with the user's specific priorities (credit vs. days off vs. preferred start city) gives pilots more confidence in automated rankings.

## Follow up #2
### [x] Sticky header
Make sticky:
K4 Bids
Bid package
2026-11 · B777 · LINES_4 / 94.PUBLISH
Seat
First Officer
My bid list (1)
Manage package

### [x] `Why this matched` move to right
Display order:
1. Line number F83· Flying line
2. Credit 90:15
3. Days off 16
4. Why this matched

### [x] Surface Missing Flight Leg Metrics (Block Times & Layovers)
The Gap: The trip breakdown currently shows generic placeholder text ("Block (h:mm): Not present in imported trip data", "Layover airports: Not present...").

The Improvement:

Parse and display individual Block Times per leg (e.g., 11:50 block) so pilots can instantly gauge heavy-leg distribution.

Clearly compute and list Layover Durations between connecting flights (e.g., CVG Layover: 28h 15m) rather than just showing the airport sequence. Knowing exact rest lengths at stops like NRT or ANC is critical for pairing evaluation.

### Introduce a Visual "Leg Timeline" Graphic
The Gap: The route string (MIA–CVG–NRT–YHM...) and text blocks are dense to read sequentially.

The Improvement: Replace or supplement the text-heavy leg list with a horizontal Gantt-style timeline or flight chevron across the days of the trip. Color-code active flying legs (solid blue), deadheads (striped/grey, like the NGO → CVG · Deadhead seen in other trips), and layover rest periods to make multi-leg rotations scannable at a glance.

### Compact Card Header with Quick-Glance Flags
The Gap: The main card headers (F38 · Flying line) give credit and total days off, but require expanding the trip to see major operational constraints.

The Improvement: Add compact badges directly to the main line card header for:

Start/End Hub Consistency: (e.g., MIA Base).

Deadhead Indicator: A subtle icon if the trip contains deadheads.

Time-Zone / Fatigue Index: A quick tag for heavy circumnavigations (e.g., Westbound vs. Eastbound around-the-world pairings) to help pilots filter out brutal body-clock shifts instantly.

### Sticky Action Bar on Expanded Trips
The Gap: When viewing an expansive 15-leg trip like Trip 749, the [Selected #1] or [Add] button sits at the top of the card. If a pilot scrolls down to inspect the final legs of the trip, the action button goes out of view.

The Improvement: Make the line's action bar (or a floating mini-header) sticky when a card is expanded, allowing the pilot to quickly click [Add] or assign a bid preference rank the moment they finish reviewing the itinerary details.

## Follow up #3
### Consolodate details

## Follow up #4
### Conflict and Rest Rule Warnings
What's missing: The interface shows carry-in trips and UTC day blocks, but doesn't explicitly flag potential rest-period crunches or FAA/company duty-time margin alerts at a glance.

Improvement: Introduce automated warning badges (e.g., a small indicator for tight legal rest or back-to-back heavy time-zone shifts) so pilots can instantly weed out lines that technically fit the minimum days off but carry high fatigue risks.

### Custom Line Tagging & Notes
What's missing: Pilots often want to categorize lines into tiers (e.g., "Top Pick", "Backup", "Avoid due to layover") before finalizing their official bid list.

Improvement: Add a quick-tagging or color-coding system (or custom notes per line) alongside the [Add] / [Selected #1] buttons, making it easier to curate and sort through the 44 matching lines before export.

