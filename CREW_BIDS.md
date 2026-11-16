# K4 Bids: remaining work

This document tracks remaining features, operational verification and unresolved source/rule decisions as of 2026-11-16. Completed implementation tasks, validation history and commit messages belong in [TODO.md](TODO.md). Existing application behavior is documented by its code and focused tests; completed plans are removed from this backlog.
Please add a multi-select and bulk actions feature to the "My Bid List" slide-over drawer (`My bid list (43)`).

### My bid list: multi select / bulk actions
Requirements:

1. **Select / Edit Mode & Draggable Area Replacement:**
   * Add a toggle button at the top of the slide-over drawer (e.g., "Edit Mode" or "Select").
   * When Edit Mode is active, replace the standard drag handle / reordering grips on each line item row with clean selection checkboxes.

2. **Fixed Sticky Toolbar & Selection Count:**
   * Introduce a fixed sticky toolbar that appears (or activates) inside the drawer when items are selected.
   * Clearly display the total count of currently selected items (e.g., "5 selected") in the toolbar.

3. **Persistent Selection Behavior:**
   * After executing a bulk action (such as removing items or moving them), keep the selected items checked so the user can easily perform secondary follow-up actions without re-selecting.

4. **Bulk Actions Included:**
   * **Remove Selected:** Batch delete checked items from the bid list.
   * **Move to Top / Bottom:** Quickly shift the selected block of lines to the very top or bottom of the sequence.

5. **Styling & Tech Stack:**
   * Maintain consistency with the existing Livewire/Alpine.js architecture and Tailwind CSS dark theme aesthetic (`bg-slate-800`, `slate-900`, clean borders, and smooth transitions).

## Advanced comparison controls

The filter groups and definition review are complete. The remaining controls below use the agreed comparison contract. These are browsing comparisons; they must preserve private manual bid order and must not imply legal compliance or payroll correctness.

### Shared comparison contract

**Confirmed comparison rules:**

- Advanced controls are hard filters on available results; saved selections stay in manual order with mismatch/unknown explanations. Best match keeps its existing strict goal priorities. Do not silently turn filters into weighted scoring. The user confirmed this behavior.
- `Any` imposes no condition. For a Boolean property, `Only` requires positive evidence and `Exclude` requires complete evidence of absence. Unknown is neither false nor zero. For partially known data, a known positive match can satisfy `Only`; a known prohibited occurrence fails `Exclude`. An unknown result cannot satisfy a strict filter. Existing days-off handling of unknown dates remains as already agreed.
- For categorical sets, use labelled inclusion/exclusion choices: selected values match with OR within a control, different controls combine with AND. `Only selected crew complements` means one of the exact published line CA/FO count pairs. The user selected the line complement as authoritative for this filter; show differing trip complements separately. Lines/Overview disagreement on the published complement blocks publication under the existing import policy.
- Numeric ranges use inclusive lower/upper bounds, allow either bound to be empty, preserve zero, and reject inverted ranges. Use hour/minute entry where appropriate and preserve underlying seconds. No arbitrary example cutoffs become business rules.
- Derive categorical choices and coverage from the active package and eligible seat before other filters narrow results, so selected options do not disappear while filtering. Inspect Flying/Reserve availability and explain unsupported metrics. Clear or explicitly reconcile invalid values on context/revision changes.
- Keep personal calendar availability and split-block detection on UTC dates clipped to the displayed calendar month. Use complete linked pairings for pairing credit, report/end-airport comparisons, routes, and scheduled connections, including dates before month-start or after month-end. Published duty/carry counts retain their published scope. Contractual compensation Months and carry-in duty-day rules remain separate from these browsing scopes.
- Keep advanced browsing filters temporary, as existing quick filters are; preserve them during browsing and disclosure changes, and clear them on package/seat context changes. The user confirmed that filters do not need to persist across return visits. Existing saved preferences and bid lists retain their persistence behavior.

**Confirmed source agreement and guarantee comparison:**

- The user identifies both Lines PDF `Credit time` and Overview `Credit` as published contractual Bid Line Guarantee. Accept either when it supplies a value; require exact agreement when both supply values. A missing value is not a conflicting zero. Preserve the source value, package/revision, and contributing source labels. A disagreement blocks publication and preserves the active published package; do not average values or choose a source silently.
- Keep `source_credit` as the published guarantee. Complete Trips Credit Time totals supply **Base pairing credit**. They must not overwrite the guarantee or silently become a personal pay calculation. If a legacy payload lacks trustworthy original source credit, its recalculated `credit` cannot establish the guarantee; recover it through a reviewed source import/revision.
- The user selected `difference = base pairing credit − published line guarantee`. Offer Below / At / Above guarantee using the sign of that difference and show the signed duration. Require both values and complete pairing-credit coverage, preserve known zeros, and compare exact normalized durations before display rounding. A signed difference range can use the same value if introduced in subtask 5.
- This is comparison of published package values. Account-specific eligibility, proration, current agreement amendments, MBPG/composite minima, actual accrued PCH, and payable compensation remain in the separate compensation work. No universal 64/70-hour floor is substituted for a missing published line guarantee.
- Use source Duty Days as published, without clipping or recomputing it from occupied dates. The user confirmed that conflicting numeric Lines/Overview duty totals block publication; an absent source value must not erase the other source's known value.

**Metric definitions and source coverage:**

| Metric / control | Source and definition | Scope and completeness | Current coverage / implementation gate |
| --- | --- | --- | --- |
| Published line guarantee | Original Lines `Credit time` and/or Overview `Credit`, retained as `source_credit`; duration in minutes. | Published line value; Trips not required. Known zero is valid; both absent means unavailable. Both present must agree. | Imported separately from trip totals. Existing conflict checking needs missing-versus-present handling reviewed before relying on it for all partial-source combinations. Guarantee labelling/comparison belongs to subtask 5. |
| Base pairing credit range | Sum of complete linked Trips `Credit Time` totals, excluding the current added carry premium. | Full pairings across calendar boundaries. Require every linked pairing total; source line guarantee cannot fill a missing trip total. Repeated calendar references to the same pairing do not add its credit again. | `base_credit` alone is insufficient: it can contain source line credit when Trips totals are incomplete. Require `credit_basis = trip_totals` and verified linked-pairing coverage in subtask 1. No pairings does not prove zero flying credit for an unsupported line category. |
| Crew complement | Exact published line Captain/FO count pair from Lines/Overview; trip `Crew` remains separate evidence. | Active package and eligible seat; filter describes the published line, including FO-only complements. | Source counts are imported for Flying/Reserve rows. Reject contradictory published complements; show trip differences without relabelling the line. subtask 1 adds the filter. |
| Published duty days | Lines `Duty days` and Overview Flying summary column D. | Use the published total as-is. Unknown is not zero; source totals may differ from calendar occupancy and summed trip duty counts. | Overview Reserve rows omit the summary columns. Lines may independently supply duty days. Current Lines parsing can overwrite an Overview duty value with null; subtask 2 must preserve known values and reject conflicting numeric totals. |
| Carry comparison | Published Overview Flying summary column F when present; otherwise calculated unique occupied UTC dates after month-end for linked trips continuing from the displayed month. | Preserve a published zero and its scope. Calculated fallback requires complete linked-trip date spans and counts each date once from the next calendar month's first date through release, inclusively. | Published-first precedence and the `Published carry count` / `Calculated occupied days after month-end` labels are implemented. Missing Trips/dates leaves fallback unknown. Reserve summary omission does not prove zero carry. subtask 2 still adds comparison controls, source discrepancy indicators, and separate contractual Transition Days handling. |
| Days off / occupied blocks | Validated Overview calendar plus trip report-through-release occupancy, including full layover/rest dates. | Displayed UTC calendar month only; report and release calendar dates are included. Unknown dates remain distinct. | Existing date filters retain incomplete dates with the agreed warning. Published duty days and contractual SDFDO are separate metrics. Unsupported/missing calendar coverage is never inferred to be off. |
| Split line | At least two known occupied blocks separated by at least one complete, confirmed off-date block within the displayed month. | No qualifying gap at the beginning/end alone; additional work blocks still satisfy presence. Unknown dates cannot create an off gap or prove no split. | Calendar data can support the personal preference definition. This is not a contractual construction/compliance test. subtask 3 adds the control. |
| Deadhead presence / avoided airports | Explicit deadhead leg markers; avoided airports inspect every linked leg origin/destination, including deadheads and ground-transfer endpoints. | Full linked trips. A known prohibited leg proves a conflict; proving absence requires complete relevant leg/route coverage. | Existing positive deadhead flags and leg routes supply evidence. Missing trips, unparsed legs, and incomplete routes cannot establish absence. Presence filtering is defined; count/duration sorts remain deferred. |
| First report | Earliest dated linked-trip report in UTC. Sort chronologically before taking the time of day; never substitute first departure for missing report. | Full linked trips, including a report before month-start. All potentially earlier reports must be known. Both window bounds are inclusive; lower greater than upper matches times at/after the lower bound OR at/before the upper bound. Equal bounds mean that exact time; an empty bound is open. | Trips supplies report clock and inferred report date. Verify dated reconstruction/rollover and malformed/missing values in subtask 3. A later known report cannot prove the first report when an earlier linked trip is missing. |
| End airport | Endpoint of the linked trip with the latest dated release/end timestamp, including an endpoint after month-end. | Full linked trips, independent of source list order. Missing potentially last trip, invalid chronology, or tied final timestamps with different airports means unknown. | Trip endpoints/release data exist; subtask 4 must validate chronology. Existing common/mixed endpoint flags describe a different property and must not supply this filter by shortcut. |
| Scheduled layover airport/duration | Connected same-airport arrival to next departure within a trip; duration from dated UTC timestamps. Source `Rest` is separately labelled. | Full linked trips; airport and duration must match on the same connection. Negative gaps and airport changes are inconsistent connections, not valid same-airport layovers. | Existing layover filters use scheduled gaps, with complete connection coverage required to prove avoidance. Source Rest does not establish the same interval or legal rest. Existing 10h/24h visual/quick-filter rules do not define a new long-stay control. |
| Country grouping | Existing airport reference country associated with package airport codes. | Group available airport choices by country, derived before other filters narrow results. Keep unresolved codes selectable without a guessed country. | `AirportData` has city/state/country, but no time zone or broader-region taxonomy. Country grouping is defined; broader geographic regions remain deferred. |

**Coverage and normalization requirements for dependent tasks:**

Package-level `coverage` currently records which document types were imported. It does not prove metric-level completeness. A present Trips file may still lack a linked pairing, credit total, report, or valid route. Evaluate availability per metric and distinguish a supported value, missing source data, incomplete data, conflicting sources, and an unsupported format/category. Keep unavailable controls explained and apply the confirmed unknown-value rules when only some lines have coverage.

Treat repeated calendar cells referencing one pairing as one pairing, leg set, credit total, and date span. If future layouts reuse the same pairing ID for distinct dated occurrences, require an occurrence-aware import format before claiming complete totals; do not guess whether to collapse or multiply them. Source disagreements and absence must remain visible to import review. Do not retrofit these definitions by silently mutating an existing published package or a private ranked list.

### 1. Add credit ranges and exact crew-complement filters

**Goal:** Compare credit bands and staffing arrangements using explicit labels.

**Problem:** There is no upper credit bound or exact-complement filter; the existing minimum uses a different credit basis from the newly agreed range.

The user selected **Base pairing credit** for the range. Use complete pairing Credit Time totals, label the range explicitly, and replace the existing quick minimum instead of keeping contradictory minimum controls. Source line credit is the published contractual guarantee and cannot substitute for base pairing credit; show unavailable when complete pairing totals cannot be established. Do not silently change the current Credit sort or goal. Derive complement options from exact published line count pairs for the eligible seat. The user confirmed that the filter describes the line complement; expose differing trip complements separately. Apply the source and completeness rules settled in the comparison contract.

**Acceptance:** Inclusive boundaries, fractional durations/seconds, missing credit versus zero, inverted ranges, eligible-seat options, absent/mixed complements, combined filters, and retained selections are covered. Guarantee comparison remains subtask 5.

Depends on the comparison contract and existing filter groups.

### 2. Add source duty-day and carry-in comparisons

**Goal:** Compare workload and month transitions without conflating occupancy, duty, and compensation.

**Problem:** The labelled carry value does not yet expose source discrepancies, duty-day comparisons, contractual Transition Days, or obligations entering from a previous award.

Add a labelled source-duty-day range where supported. The user confirmed published duty totals without clipping/recalculation, preservation of a known value when the other source omits it, and blocked publication for conflicting numeric Lines/Overview totals. The user selected **published source carry-in count, or work continuing into the next month when no count is published**. Preserve a published zero; fall back only when absent, not when it fails a filter. Invalid/conflicting published data is not absence and must not trigger a quiet fallback. For the fallback, use the existing calendar-occupancy meaning: unique occupied UTC dates after month-end for trips continuing from the selected month, with complete linked-trip coverage required. The existing display labels the basis `Published carry count` or `Calculated occupied days after month-end`; use those labels in the comparison explanation. Keep both raw values and flag discrepancies when available; contractual Transition Days remain distinct. Provide Any/Only/Exclude presence plus an optional count range if useful. Incoming obligations require evidence from the previous award and are unavailable from a prospective package alone. Source duty-day totals retain their source scope; the fallback alone counts dates beyond the displayed calendar month. No compensation-month boundary or premium inference applies to this calendar fallback.

**Acceptance:** Calendar month/year transitions, compensation-period exceptions where used, duplicate dates, missing pairings, complete zero carry-in, partial coverage, and source/calculated disagreement have focused coverage. No automatic schedule adjustment or premium entitlement is inferred.

Depends on the comparison contract and existing filter groups.

### 3. Add defined work-pattern controls

**Goal:** Filter split lines, deadheads, first report, and scheduled long stays using dependable evidence.

**Problem:** More than one occupied block does not by itself establish a contractual split line; report time differs from first departure; long-stay and rest values require explicit aggregation.

The user defined a **split line** as a work block, an off block, and another work block, and confirmed detection within the displayed month using known occupied blocks separated by at least one confirmed off day. Provide Any/Only/Exclude for that personal comparison definition. Unknown dates do not establish an off block or prove the absence of a split. Do not impose the CBA's contractual split-line construction requirements on this preference or label the result a compliance check.

For deadheads, begin with Any/Only/Exclude presence based on explicit leg markers; absence requires complete linked-leg coverage. Counts/duration can follow only with agreed aggregation. The user selected the **first chronological trip report in UTC across full linked trips**, including reports before month-start; do not use first departure or apply the window to every trip. Preserve the full dated timestamp to establish chronology before comparing time of day. Both time-window bounds are inclusive. A window crossing midnight matches times at/after the lower bound OR at/before the upper bound; equal bounds mean that exact time, and an omitted bound is open. Validate report date reconstruction and coverage under the rules in the comparison contract. Missing report must not silently become departure time. The user deferred long stays until a duration is defined. Keep scheduled gaps, source Rest, legal rest, and fatigue distinct. Do not reuse visual chip thresholds as a new long-stay definition without agreement. Any future city-plus-duration condition must match both on the same stay.

**Acceptance:** Exact duration bounds, midnight/year crossings, first-trip chronology independent of source order, split occupancy around month boundaries, ground transfers, incomplete legs, overlaps, and airport changes produce defined results. Cases needing unresolved definitions stay deferred.

Depends on the comparison contract and existing filter groups and the relevant definitions.

### 4. Add airport inclusion, avoidance, and geographic groups

**Goal:** Compare trip endpoints and stays with package-derived airport choices.

**Problem:** End city could mean the last trip's endpoint or every trip's endpoint; avoided airports could cover stays only or every leg endpoint. Region names have no agreed mapping.

The user selected **the final chronological trip endpoint across full linked trips**, including an endpoint after month-end, **all leg origins/destinations including deadheads** for avoided airports, and **countries** for geographic grouping. Establish the final trip from dated chronology under the comparison contract, with unavailable status if missing trips could change that endpoint. Extend the existing layover selector rather than duplicate it; distinguish scheduled connections from source-rest locations. Avoidance requires complete route coverage to prove no prohibited endpoint is visited. Keep airport codes primary and identify airports sharing a city individually. Derive country groups from existing reference data for airports present in the package; preserve unresolved codes without assigning a guessed country. State/continent/custom-region groupings are outside the agreed first pass. Reuse airport resolution/cache services without a network lookup per rendered row.

**Acceptance:** Mixed endpoints, city/duration correlation, avoided deadhead endpoints, multiple selected airports, unresolved codes, reference-service unavailability, incomplete routes, and package changes have defined behavior.

Depends on the comparison contract and existing filter groups and airport-scope decisions.

### 5. Display the published guarantee and add guarantee-relative comparisons

**Goal:** Compare complete base pairing credit with the published contractual line guarantee.

**Problem:** Pairing totals and adjusted Credit can differ from published guarantee, and incomplete or legacy source data cannot establish every comparison.

Show the bid month’s published guarantee independently of carry-in premiums and overtime, using preserved `source_credit`. Implement the agreed `base pairing credit − published line guarantee` difference, with Below / At / Above guarantee and a signed duration. Require complete pairing totals and a trustworthy published value, compare exact normalized durations, and preserve source/revision evidence. A signed range, if added, uses the same difference. Known zeros are valid; missing values are unavailable. Validate the source-field mapping and conflict handling described in the comparison contract, including partial-source and legacy payloads; never substitute MBPG, composite minima, adjusted Credit, or a trip sum for a missing guarantee. Comparing these package values does not calculate actual accrued PCH, personal payable compensation, or carry-in premiums. Effective compensation rules and personal eligibility remain prerequisites for Estimated pay, not additional unanswered questions about this agreed arithmetic comparison.

**Acceptance:** Positive/zero/negative differences, exact equality and duration precision, conflicting sources, missing/partial values, preserved published zeros, legacy payloads, and line categories with no complete pairing totals are covered. A category with no dependable numerator or guarantee remains unavailable rather than receiving a guessed value.

Depends on the comparison contract and reliable source inputs; independent filters can ship first.

### 6. Add dependable optional sorts

**Goal:** Make the defined comparison metrics available for browsing order.

**Problem:** New sorts need a specific metric, direction, aggregation, and completeness policy.

Add only supported source duty days, chosen carry-in metric, first report, deadhead count/duration, and chosen rest/stay duration. Confirm ascending/descending choices and line-level aggregation, such as shortest scheduled stay versus longest stay; source Rest must have a distinct label. Unknown values remain last in either direction, with natural Line ID as the final tie-break. Partial observations cannot establish a reliable maximum/minimum or zero. Keep existing sorts available, Best match priorities intact, and manual bid order independent.

**Acceptance:** Equal values, zero/missing/partial values, both directions, stable ties, and combined filtering are covered. Do not expose a sort merely because some rows contain the field.

Depends on the corresponding completed metric subtasks.

### 7. Evaluate optional comparison columns or views

**Goal:** Add a display option only when it answers a comparison need beyond the compact timeline.

**Problem:** A second display could duplicate information or lose active browsing state.

The user confirmed **retain the compact timeline and defer extra views**. This subtask remains deferred until users identify fields needing side-by-side comparison. Prefer a small set of optional metrics in the existing layout; add a table/view only if that solves the stated need. Use the same filtered/sorted results, selected lines, add/remove actions, and warnings. Retain filters and selections across any future view changes, preserve the compact default and mobile usability, and define owner-scoped display persistence separately from filter persistence.

**Acceptance:** View changes preserve filter/sort values and manual rank; missing values remain explicit. Review narrow/desktop layouts, both themes, keyboard interactions, and header/action overlap. If no additional value is established, record that outcome without adding a view.

Depends on delivered metrics and a demonstrated display need.

### 8. Define later scoring separately

**Goal:** Establish evidence and definitions for later breakfast, fatigue, long-stay detection, and hazard-pay features.

**Problem:** These features require more than package-derived categorical filters.

Define breakfast location/window, relevant departure/report event, local time zones and DST, plus whether all or some departures qualify. Define fatigue inputs, time basis, model, aggregation, missing-data behavior, and any ranking integration separately. A scheduled-duration filter from subtask 3 does not establish fatigue or a contractual long-stay category. Hazard-pay work needs applicable designation dates, qualifying assignments/layover days, governing amendments, and verified compensation rules. Keep undefined scoring and eligibility unavailable; do not infer them from region grouping or the existing chip colors.

**Acceptance:** A separately reviewed definition and source-coverage decision precedes each implementation task. This is deferred definition work, not a promise to add scoring in the first advanced-controls release.

## Email intake and import operations

The direct portal downloader, configurable schedule and Filament fetch action are implemented. Remaining work concerns reliable manifests, durable intake, email delivery and operational visibility. Preserve the agreed policy: automatically publish complete validated first releases and hold changed releases for admin review.

### Release manifests and delivery decisions

- Inspect a complete work-email package and a correction: establish the real sender, forwarding path, attachment roles and sizes, fleets, revision evidence and organization policy. A connected personal Outlook account does not establish work-mailbox access.
- Inspect the portal Documents page for a reliable manifest or revision signal. Verify naming and correction behavior beyond the observed December 2026 B747/B777 direct links. An unavailable or renamed required file holds the group; an older release is not a substitute.
- Choose Outlook forwarding to a receiving provider only after confirming permitted forwarding, provider/domain support and message-size limits. Use Microsoft Graph as a fallback only if portal retrieval/forwarding are unsuitable and organization-approved OAuth access is available.
- If future portal downloads require authentication, decide an import-specific policy or keep those releases manual. Submission credentials remain transient and are not an unattended-import credential store.

### Durable receipts, classification and cross-source provenance

1. **Receive durably.** Verify the provider signature, freshness/replay rules, configured recipient, and registered forwarding-source policy before accepting an email. Provider authentication proves who delivered the webhook, not that a visible From header is genuine; forwarding provenance needs a sample-based policy using provider authentication results and expected sender/forwarder information. Persist the receipt and bounded attachment data before acknowledging success; retry delivery safely after temporary failures. Queue parsing after the receipt transaction commits. Jobs carry receipt IDs, not email bodies, credentials, or attachment bytes.
2. **Inventory every attachment.** Record private filename, byte size, content hash, detected format, classification, and reason for exclusion/hold. Classify using PDF content and workbook structure as well as names. Ignore proven inline signature images, but flag unexpected document attachments. Do not equate “ten attachments” with completeness. Detect truncated, encrypted, malformed, oversized, or unsupported files. Bound ZIP expansion/XML processing for XLSX, avoid external references and macros, and never execute document content. `.xls`, `.xlsm`, and scanned PDFs need explicit support decisions; they are not silently treated as supported XLSX/native PDFs.
3. **Build coherent groups.** Separate sources by period, fleet, revision, and seat where source documents actually distinguish seats; the present package model is period/fleet-based and carries crew complements. Associate each supported group with its Lines, Trips, and Overview. Classify Training Requirements, Bid List, and Vacations without adding their personal contents to shared package payloads. Hold unknown roles for review. Do not combine a new revision's Lines with an earlier revision's Trips or Overview, even when filenames match. A date in an email subject is a hint, not package identity.
4. **Define a complete automated import.** Proposed initial profile: one native Lines PDF, one native Trips PDF, and one supported Overview XLSX per period/fleet group. Confirm that profile from one full delivery before enabling it. Require matching identity, supported schema, successful parse, expected line/trip references, consistent coverage, and existing source-consistency checks. An Overview may lack a revision marker; it must have coherent delivery provenance and pass cross-file validation rather than being assigned an invented revision. A parser success with partial coverage does not qualify for automatic publication. Extra known unsupported document roles are explicitly excluded; an unexplained missing required role holds that group. Independent fleet groups may publish independently if each is complete; never publish a partially validated group.
5. **Deduplicate and order revisions.** Use a durable unique receipt identity for delivery retries, attachment hashes, and the existing parser-version/package fingerprint for normalized drafts. Add database uniqueness and per-context locking for simultaneous email/portal arrivals; queue uniqueness alone is insufficient. Identical content is a no-op with additional provenance. Changed content for a previously published period/fleet is a revision even if its printed revision label is unchanged. Different labels or conflicting contents hold for review; receipt time and lexicographic labels do not establish a newer revision. Never infer deletions or replace good files from an incomplete delivery. A partial correction can reference an earlier manifest only through a reviewed, explicit merge.
6. **Apply publication policy atomically.** With the context locked, recheck completion, registered-source authorization, duplicate status, and whether a published package already exists. A first complete validated package may publish automatically. Any changed package in that context becomes a reviewable draft showing source differences, affected lines, and validation results. Reuse publication behavior through an action with explicit automated-source authority and an accountable configured admin owner; recheck authorization if that owner/source is disabled. Keep interactive ownership and gate checks intact. Publishing a revision must not migrate users' selections or change their manual order; preserve the existing migration review and confirmation.

Proposed durable records are an import receipt with state/source/attempt details, a private attachment manifest, and links from each resulting package to its receipts. Keep normalized `BidPackage` payloads in the existing model. Reuse `ParseBidDocuments`, `ReadBidOverview`, and the publication behavior. Reuse the existing automated-source authority and staging behavior in `ImportPortalBidPackage`; extend it for durable email intake rather than invoking UI methods or assuming an interactive admin. Database schema and migration details belong to implementation after inspecting the schema.

### Email delivery implementation

Create a dedicated receiving subdomain/recipient without replacing the organization's work-mail MX records. Confirm provider support and the largest complete sample message, including encoding overhead, against provider, web-server, PHP, and storage limits. Start with a controlled manual forward into the intake flow, inspect its manifest, then configure the narrow Outlook rule once that works. Include revised-package subjects and provide manual forwarding as recovery.

Prefer a signed multipart attachment webhook if the existing inbound provider supports the actual message sizes. If messages exceed safe request limits, use authenticated provider storage retrieval or private object storage with a small event notification. Download only from verified provider destinations; do not follow arbitrary URLs in email bodies. Treat forwarded-as-attachment `.eml`/`.msg` and cloud-file links as explicit cases: support the observed format or hold with a clear reason rather than pretending there were no attachments.

Proposed staging retention is seven days for raw messages and attachments, including failed/revision-held deliveries, with an expiry notice and a way to supply the source again. Keep only necessary normalized package data and minimal provenance afterward; configure provider-side retention too. Restricted raw training/vacation/bid-list documents remain admin-only during staging. Logs and status notifications contain IDs, counts, and failure categories, not full messages, personal records, credentials, or signed download URLs. Confirm this retention choice before implementation.

### Operational rollout and acceptance

Add an admin import-history view showing source, last successful check separately from last new package, validation holds, missing roles, revision differences and retry/review actions. Persist receipt/attempt outcomes; existing scheduler/cache locks do not replace durable history. Verify deployed scheduler/worker operation and recovery.

For late-arrival monitoring, retain the supplied CBA's 2359Z-on-the-12th publication expectation as configurable, subject to current company timing. Define the notification lead time before the expected 16th close. A failed poll or missing email alone does not establish that the company has not published a package.

Acceptance must cover complete and ten-attachment deliveries, multiple fleets, excluded personal documents, partial/mixed revisions, misleading names, unsupported/corrupt/oversized files, signature/replay failures, retries/concurrent arrivals, identical cross-source content, out-of-order corrections, first-release publication versus revision hold, disabled admin/source authority, worker recovery, staging expiry and preservation of published packages/private rankings.

Reference material retained from the intake plan: [Microsoft forwarding controls](https://learn.microsoft.com/en-us/defender-office-365/outbound-spam-policies-external-email-forwarding), [Mailgun message delivery](https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/receive-http), [Mailgun signature verification](https://documentation.mailgun.com/docs/mailgun/user-manual/webhooks/securing-webhooks), and [SES receiving](https://docs.aws.amazon.com/ses/latest/dg/receiving-email.html). Provider/domain choice and the proposed seven-day staging retention still need confirmation.

## Portal verification and award discovery

Reviewed submission, read-back reconciliation and shared-bid refresh are implemented. The user confirmed a successful complete shared-bids refresh on 2026-11-16; that does not establish a successful bid Update, authenticated email receipt or award.

### Remaining live submission verification

- Confirm a separately authorized controlled live Update, the complete fresh submitted order and browser/keyboard behavior. Reaching comparison or final review alone is not portal acceptance.
- Authenticate the Bid Received email's source/recipient delivery path and bind its expected employee/fleet/seat and complete ordered choices. Its observed content lacks a bid period; a parsed excerpt alone is not authenticated receipt evidence.
- Verify the submitted report's account/period scope and actual live deadline/time-zone behavior. The observed package starting/ending dates are not the submission cutoff. Preserve the distinction between a context-bound report match, receipt acceptance and an awarded line.
- Keep production submission disabled by default pending controlled verification; the existing local test authorization does not establish production rollout approval. Timeouts after a possible write require read-only reconciliation before another deliberate attempt; never automatically retry the write.

### Bid-award navigation and data discovery

The supplied Bid Sheet navigation includes `Bid Awarded` → `/Pages/BidSheetAward.aspx` and `All Bids Awarded` → `/Pages/AllBidAwards.aspx`. It also links `Submitted Bid Lines` → `/Pages/BidLineDataOutput.aspx` and `Days Off and Comp Lines` → `/Pages/BidCommentsOutput.aspx`. Menu labels and destinations are observed; their contents and update behavior are not yet verified.

The supplied Submitted Bid Lines source confirms a ReportViewer form posting back to `./BidLineDataOutput.aspx`, with Excel and Acrobat (PDF) export choices. The actual report body is outside the saved top-level source: an iframe uses `/Reserved.ReportViewerWebControl.axd` with `OpType=DocMapReport` and a nested report URL using `OpType=ReportArea`. Session-dependent report/control identifiers must come from a fresh authenticated operation, never from this capture. A supplied private PDF export establishes a two-column report containing printed Choice and Line numbers. The offline parser reconstructs the ordered list from those ranks; it does not establish personal-versus-all-crew scope, a report owner, period, receipt semantics, or whether the report reflects the latest Update. Missing report data must not be treated as an empty bid list. Award-page contents remain undiscovered.

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

## Remaining compensation and scheduling review

Reviewed against the supplied [2021 CKS CBA](storage/app/private/CBA/2021_CKS%20CBA_Final.pdf), primarily **Part I, Section 5, Compensation**, and **Part I, Section 17, Scheduling**, with related definitions and cross-references. References below give printed pages first and one-based PDF pages in parentheses; printed page 25 is PDF page 37. This is a document comparison, not validation of application code, package totals, live portal behavior, or actual payroll.

The remaining findings qualify future compensation and scheduling work. The legacy adjusted-Credit comparison formula remains distinct from the separate scheduled-pay engine. Implemented terminology, rate maintenance, pay estimates and UI work are not repeated as tasks here. Product choices such as private ranking, filters, colors and portal security are not themselves contractual requirements.

The supplied agreement's cover states March 1, 2021–March 1, 2025, but Section 28 provides for renewal subject to its notice provisions; do not describe it as automatically expired. Appendix 1, LOA #1 makes the ratification date the effective date, with Sections 5.B and 5.C effective January 1, 2021 subject to ratification. This review does not establish whether later agreements or interpretations change the rules for the October/November 2026 samples. Sources: Section 28, p. 207 (PDF 219); LOA #1, pp. 269–270 (PDF 281–282).

### Legacy credit and unsupported compensation findings

**C2 — The blanket extra 50% carry premium is unsupported.** The documented `base credit + 50% of carry credit` formula, including a premium on credited deadheads, is not established by Sections 5.M, 17.D.7, or 17.K. Section 5.L.1.b provides 1.5 times hours flown for an **Open Time duty assignment on a scheduled duty free day off (SDFDO)**, paid above MBPG. Section 5.L.2 specifies a different greater-of rule for non-flight duty on an SDFDO, including reserve, training, and deadhead. Section 17.K.2 permits an election to work excess days for Section 5 compensation, subject to its conditions. Crossing a month boundary alone does not establish these entitlements. The legacy adjusted-Credit arithmetic is not verified contractual credit or pay. Sources: Sections 5.L–5.M, pp. 27–28 (PDF 39–40); 17.D.7, p. 118 (PDF 130); 17.K, pp. 124–125 (PDF 136–137).

**C3 — Section 5.M defines carry-in credit allocation; the departure-only subtotal does not implement it.** Under 5.M.1, the crewmember completes a duty assignment that originates in the current bid period and is scheduled to transition into the new bid period, and **all PCH for that duty is paid in the new bid period**. Under 5.M.2, **all PCH accrued during a bid-period transition is credited to the Month in which the last duty assignment occurs**, for Monthly Pay under 5.D. These are established allocation rules in the supplied CBA. Section 5.E's segment departure-day rule does not replace this transition rule, and 17.D.7 also credits time flown in the subsequent period to that period. The current calculation that includes only next-calendar-month departures and excludes every earlier departure does not capture all PCH for the transitioning duty. Implementation must identify the covered duty assignments and apply the compensation Month boundaries in S1; whether the imported package supplies those boundaries is a data-coverage question, not an undefined contractual allocation rule. Sources: Sections 5.E and 5.M, pp. 26, 28 (PDF 38, 40); 17.D.7, p. 118 (PDF 130).

**C4 — Block, elapsed time, and deadhead credit are not interchangeable.** Operating flight pay is one PCH per hour of **Actual Block Time**, with block-to-block defined to include taxi and tug positioning. Section 5.H.1 credits air deadhead at 50% of **scheduled** block time on a passenger carrier or **actual** block time on a non-passenger carrier, segment by segment. A blanket 50% of elapsed time omits this distinction. Section 5.H.2 credits nonlocal surface deadhead at 50% of actual drive time, subject to Section 19.G.4's published/assessed time provisions; setting every ground transfer to zero is incorrect as a compensation rule. Section 19.G defines local transportation as 60 minutes or less unless the parties agree otherwise. Retain mode, carrier category, scheduled/actual basis, and applicable published surface time; package schedules can support estimates, not establish actual pay. Sources: Section 2, pp. 11–12 (PDF 23–24); Sections 5.E and 5.H, pp. 26–27 (PDF 38–39); 19.G, pp. 142–143 (PDF 154–155).

**C5 — The 64-hour guarantee is verified in this CBA, with important exceptions.** Section 5.C.1 sets MBPG at **64 PCH**, prorated for partial-period unavailability as provided in the agreement. Section 5.C.2 sets the composite-line minimum at **70 PCH**. Section 5.D.1 bases monthly pay on the applicable rate and overrides multiplied by the greatest of MBPG, accrued bid-month PCH, and Bid Line Guarantee; additional compensation is addressed separately in 5.D.2–6. Sections 5.F and 17.E impose eligibility/availability/qualification conditions on line guarantee, and 17.E excludes reserve lines from its published flight-hour-value requirement. A reserve day on a published flying line does not itself receive reserve-day value under 17.E. Section 17.P also provides voluntary No Duty Bid Lines with no Section 5 compensation. Thus neither a universal 64-hour floor nor adjusted trip credit alone is a complete pay formula. Sources: Sections 5.C–5.G, pp. 25–26 (PDF 37–38); 17.E, p. 119 (PDF 131); 17.P, p. 129 (PDF 141).

**C6 — Preserve the distinction between trip totals and published line guarantee as source coverage grows.** Giving complete Trips totals precedence over Lines/Overview totals is an import/display policy. Sections 5.F and 17.E require a Company-assigned line value before package distribution, subject to the stated exceptions; Section 5.D.1 treats that guarantee separately from accrued PCH. The existing model retains the published line guarantee independently from summed pairing credit. Further source support must preserve that distinction and flag discrepancies instead of assuming a trip sum supersedes it. Flight/deadhead totals also omit other credit: reserve under 5.G is the greater of four PCH per day or actual PCH performed; vacation under 5.I is four PCH per day; 5.N contains training and CBT credit rules; 5.D.6 pays four PCH for each Company-assigned one-in-seven beyond three unpaid ones per bid month. Some categories are already supported by the pay engine; the unsupported cases below remain separate scope. This finding is not a task to recreate implemented pay components. Sources: Sections 5.D–5.I and 5.N, pp. 25–29 (PDF 37–41); 17.E, p. 119 (PDF 131).

**C8 — Hazard-pay rules are partly specified, not wholly undefined.** Section 5.D.4 provides four hours at 150% of the hourly rate above guarantee for qualifying Hostile Area assignments; 5.D.5 provides four PCH in addition to MBPG for each Hostile Area layover day. Section 24.B defines Hostile Areas and the Company's maintained list and dispute process. An international airport, long layover, or generic region tag does not establish eligibility. Record these supplied rules while leaving the applicable designation/date and any later amendments unresolved. Sources: Section 5.D.4–5, p. 26 (PDF 38); 24.B, pp. 179–180 (PDF 191–192).

### Scheduling discrepancies and missing qualifications

**S1 — Calendar months are not always compensation months.** Section 2 defines a Day as 0000Z–2359Z, supporting UTC calendar occupancy. Its Month definition makes compensation exceptions: January is January 1–30; February is January 31–March 1; March is March 2–31. Section 17.B defines a Bid Period by reference to that Month definition. A calendar-month UI can remain useful, but calendar boundaries must not silently become compensation boundaries. Record both period identity and calendar occupancy, especially for January–March; do not assume every carry calculation starts on the first calendar day. Section 17.C also allows up to three bid periods in one package, excluding transition days; single-month import support is a product limitation. Sources: Section 2, pp. 12–13 (PDF 24–25); 17.B–17.C, p. 116 (PDF 128).

**S2 — Blank cells and trip occupancy do not establish contractual SDFDO.** Section 2 defines SDFDO by freedom from Company direction, normally at the Resident Airport unless the crewmember requests otherwise. A blank Overview cell can mean an off date under a validated source format, but the CBA does not define blank cells, and missing travel, carry-in, reserve, or training can invalidate that inference. Conversely, occupying a full layover date for personal availability does not prove that every hour is contractual duty. Section 17.D.8 permits layover assignment/contact and specifies notification for a layover to count as a one-in-seven. Keep calendar availability, duty, layover/rest, and contractual SDFDO separate. Trip report/release alone may omit resident travel, including travel on the last SDFDO under 17.A.6. Sources: Section 2, pp. 12–15 (PDF 24–27); 17.A.1 and 17.A.6, pp. 107–115 (PDF 119–127); 17.D.8, pp. 118–119 (PDF 130–131).

**S3 — Published-line construction limits are missing.** Section 17.D specifies no more than 16 consecutive Duty Days or 16 Duty Days in a bid month; at least 14 SDFDO in a 30-day month or 15 in a 31-day month; and required SDFDO in at most two blocks, with at least five days in a block except at the start/end of the period. The internal break in a split line remains subject to the five-day minimum. Section 17.D.7 limits constructed transition sequences to six days into the next period. Section 17.F.2.b provides an exception for 16 or more consecutive training days, and 17.K addresses carry-in adjustments/excess days. Add these as contractual comparison criteria with their exceptions and source-coverage limits; ranking by fewer work blocks or raw off-day count is not a compliance check. Sources: Sections 17.D.1–7, pp. 117–118 (PDF 129–130); 17.F.2.b, pp. 121–122 (PDF 133–134); 17.K, pp. 124–125 (PDF 136–137).

**S4 — Carry-In Days are defined duty days; excess days require an election.** Section 2 defines Transition Days or Carry-In Days as duty days continuing from the current bid period into the subsequent bid period. Their day count is distinct from the PCH allocated under Section 5.M. Counting next-calendar-month occupied dates alone does not capture this definition or obligations from the previous award. Section 17.K requires completion of the continuing trip; Crew Resources adjusts conflicting new-line days or replaces them with nonconflicting trips/reserve. If carry-in reduces SDFDO below the minimum, the crewmember may drop sufficient days **other than carry-in days**, or work the excess for Section 5 compensation. Failure to coordinate within 72 hours after award defaults to compensation. A requested day-off hard filter is a personal preference, not an automatic contractual schedule adjustment or election. Sources: Section 2, p. 14 (PDF 26); Sections 17.D.7 and 17.K, pp. 118, 124–125 (PDF 130, 136–137).

**S5 — Bid eligibility cannot be inferred from app access or prefix mapping.** Section 17.F.1 requires legal/current/qualified status in Type and Crew Class, expected availability for at least half the period, and its listed qualification, training, furlough, leave, and vacation conditions. Sections 17.F.2.a–c require CQ lines for specified CQ/non-current situations, address vacation and unavailable simulator slots, and require all-reserve bidding for 15 or fewer days of non-CQ training. Sixteen or more training days affect ordinary bid eligibility. Section 17.F.4 addresses taking or dropping awarded vacation. These restrictions already exist in the CBA even though account-specific inputs and portal enforcement remain undiscovered. Deferring dedicated training/vacation imports is acceptable; claiming submission eligibility without equivalent verified evidence is not. Sources: Sections 17.F.1–4, pp. 119–123 (PDF 131–135).

**S6 — The CBA supplies bid deadlines, award rules, and a submission fallback.** Section 17.H requires package email/posting by **2359Z on the 12th** before the bid period. Section 17.I specifies bid close **no later than 2100Z on the 16th** and award posting **no later than 2359Z on the 16th** before the bid month, with an exception for award-posting delays beyond Company control. Awards may be corrected for errors within 72 hours; there is no re-bid after awards are posted. Section 17.J awards in seniority order by Type and Crew Class and provides assignment after other awards for missing, insufficient, noncompliant, or illegal-only choices. Section 17.I.1 requires the approved intranet system, with email/fax/other approved electronic means to Crew Resources when it is inoperative, and permits receipt-confirmation requests. Live window/protocol verification remains necessary, particularly because “no later than” is not a promise that the portal remains open until 2100Z. Copying a list is still not submission, and these provisions do not authorize the app to send fallback messages. Sources: Sections 17.H–17.J, pp. 123–124 (PDF 135–136).

**S7 — Flying/reserve tabs and F/Q/R IDs are not a complete contractual line taxonomy.** Section 2 includes hard, reserve, CQ training, and composite lines. Section 17.D.5 allows a composite line to become a hard line when trips and its guarantee are published at least 48 hours before the start; otherwise it is considered a reserve line. Section 17.P separately permits voluntary No Duty Bid Lines. Keep contractual line type, source prefix, and portal numeric ID distinct, and flag unsupported categories rather than mapping them by inference. The CBA does not validate F/Q/R-to-number serialization or the captured 315-slot capacity. Sources: Section 2, p. 11 (PDF 23); 17.D.5–6, p. 118 (PDF 130); 17.P, p. 129 (PDF 141).

**S8 — The rest-warning deferral should identify the contractual baseline already available.** The existing disclaimer correctly keeps 10-hour/24-hour visual chips separate from legal rest. Section 17.L.1 starts rest at the earlier of an assignment after check-in or 45 minutes after Company check-in, never before a hotel room is assigned. Section 18.D measures minimum rest through duty report and specifies international minima of 11 hours through 18 hours of duty, 13 above 18, 14 above 20, and 18 above 24; Section 18.C.3.b also specifies 18-hour minimum rest for its four-or-more crew category. Section 18.D.3 refers wholly domestic pairings to applicable FARs, and 18.A requires the more restrictive applicable limitations. Do not turn scheduled arrival-to-departure gaps into legal-rest determinations; hotel/check-in, actual duty, crew complement, assignment changes, and applicable rules are required. Sources: Section 17.L.1, pp. 125–126 (PDF 137–138); Sections 18.A–18.D, pp. 131–135 (PDF 143–147).

### User-confirmed application of the carry-in rules — 2026-10-29

The user clarified the following for this documentation review. These clarifications establish the intended application of the cited clauses; they are recorded separately from the CBA's wording.

- **Duty-assignment boundary:** For Section 5.M, a duty assignment means **one duty period, potentially containing multiple legs**. Identify individual duty periods within a multi-day pairing. This identifies the duty covered by transition allocation; the carry-in amount is calculated per Z day as described below.
- **Daily carry-in amount:** Carry-in is paid at the greater of the credit awarded in the line or the credit actually flown/worked, calculated **separately for each Z day**. For each covered day, `daily carry-in credit = max(awarded credit, actual worked credit)`. Sum the resulting daily amounts; do not compare only the totals for the whole pairing or transition.
- **Segment date:** Assign each flight segment's **full credit to its departure Z day**, including a segment that arrives after 0000Z. A duty period with multiple legs can therefore contribute credit to multiple Z days. Do not split a segment at midnight or assign all its duty period's segments to the same date.
- **Daily valuation and pay-period allocation:** Preserve the departure Z day used for the awarded/actual comparison separately from the compensation period receiving the credit under Section 5.M. Apply that section to the covered transitioning duty after establishing its credit on the daily basis above; crossing the boundary does not duplicate the credit in both periods.
- **Premium eligibility:** The extra 50% is conditional on carry-in creating qualifying excess days or work on scheduled days off. Carry-in alone does not qualify. Determine the eligible assignment and applicable Section 5 compensation treatment separately from moving its credit into the receiving period. Preserve the distinction between flight-duty treatment under 5.L.1 and non-flight treatment under 5.L.2; this clarification does not authorize adding 50% to every deadhead or every PCH in a qualifying duty period.

Illustrative daily comparison for two covered carry-in Z days:

| Z day | Awarded credit | Actual worked credit | Payable daily credit |
| --- | --- | --- | --- |
| Day 1 | 5:00 | 3:00 | 5:00 |
| Day 2 | 2:00 | 4:00 | 4:00 |
| Total | 7:00 | 7:00 | **9:00** |

The daily comparison produces 9:00, even though both aggregate input totals are 7:00. A segment departing at 2300Z and arriving at 0200Z contributes its full applicable credit to the departure Z day; the arrival day receives no separate portion of that segment. These examples describe valuation and date attribution, before any separately eligible premium, and do not establish actual payroll.

For bid comparison before work is performed, awarded credit can be shown as a planning value. Actual worked credit remains unknown until supported by actual records; absence of those records is not zero actual work or a finalized payable amount. Keep the awarded schedule and actual work records distinct, including any relevant work that replaces an awarded assignment.

### Rules and coverage still needed beyond supported pay estimates

Scheduled gross estimates, source normalization, rate maintenance, private adjustments and pay sorting already exist. Extend them only for separately reviewed unsupported cases; do not repeat their completed implementation plan.

| Remaining case | Evidence or rule work required |
| --- | --- |
| New-hire training/weekly pay and discretionary first-year rate | Section 5.A/5.B, printed p. 25 (PDF 37): establish qualification/first observation dates and any authorized first-year rate within its stated limit. Longevity alone does not establish hourly Flight Pay eligibility. |
| Partial availability and Type/Crew Class qualification changes | Sections 5.C and 5.T.4, printed pp. 25, 31 (PDF 37, 43): establish unavailable dates, actual qualification-effective date, eligible scale and applicable proration. A browsed seat is not qualification evidence. |
| Non-flight or training SDFDO | Resolve 5.D.2/5.L.2/5.N.5 interactions and supported worked examples before a complete amount is available. Requested-off dates and calendar gaps do not establish contractual SDFDO. Printed pp. 25–29 (PDF 37–41). |
| Non-passenger air and nonlocal surface deadhead | Establish actual-block or applicable drive/published-time evidence under 5.H and 19.G.4; distinguish company-directed travel from personal commute and local transportation. Printed pp. 26–27, 142–143 (PDF 38–39, 154–155). |
| Training of 16 or more days | Implement the Monthly Guarantee branch under 5.N instead of multiplying the short-training daily rule. Printed pp. 28–29 (PDF 40–41). |
| Hostile-area pay and extra Company-assigned one-in-seven days | Obtain qualifying assignment/designation dates and the current Company list under 24.B; resolve 5.D.4–6 eligibility and separate additions. Printed pp. 26, 179–180 (PDF 38, 191–192). |
| Sick leave, jury duty and emergency leave | Review the cross-referenced 7.C.3, 12.I and 12.D rules; Sections 2/5 alone do not establish complete valuation. |
| FAR-limit/non-revenue special cases and higher negotiated overrides | Resolve 5.S and 5.Q scope and supporting facts. A duty-limit flag must not silently erase compensation; declared higher overrides require evidence. Printed pp. 29–30 (PDF 41–42). |

Preserve the following user-confirmed planning policies as assumptions rather than independently CBA-verified interpretations: adjusted pay-longevity date; steps 1–12 capped at 12; March 1 anniversaries for February 29 in non-leap years; the new rate effective at 0000Z on the anniversary; actual compensation-period-day weighting for guarantees; earning/departure-day rates for allocated credits; and one final nearest-cent half-up USD rounding after exact entitlement calculations.

For guarantee credit `G`, rate intervals `r_i` and UTC-day counts `d_i` across `D` compensation-period days, preserve `guarantee USD = G × sum(d_i × r_i) / D`. This approved anniversary policy does not define partial-availability or qualification-change proration. Section 2/5 did not independently verify the adjusted-date, leap-day, anniversary weighting or rounding choices; obtain governing interpretations before describing them as contractual payroll rules.

The approved Open Time flight off-day planning policy pays the entire additional `1.5 × hours flown × earning-day rate` above base, with the assignment excluded from ordinary credits. It does not resolve non-flight/training SDFDO pay. The outgoing carry-in line-value scenario assumes the next bid avoids those duties and adds a separately labelled receiving-period excess-day payment; passenger deadhead first credits 50% of scheduled travel, yielding 0.75× travel-hour pay. This scenario is not proof of actual excess-day entitlement, a universal SDFDO multiplier or the next month's complete pay.

The retained rate table's source is Appendix 5-A's January 1, 2024 CA/FO column, longevity years 1–12, in USD; the user confirmed its intended package applicability on 2026-11-03. Further effective dates/amendments require new provenance. Private scenario facts can fill supported missing inputs, not supply a missing contractual rule. Gross compensation-period estimates do not establish deposit timing, taxes, deductions, per diem or expense reimbursement.

## Other remaining enhancements

### Browser and accessibility review

Verify narrow/desktop widths, both themes, full-month visibility, the 150px collapsed layout, independent disclosures, sticky action/header overlap, and mobile drawer return behavior. Exercise keyboard date inspection, tabs, add/remove, ranking, focus restoration, save announcements, and visible warnings. Check long routes, large credits with seconds, many pairings, missing metrics, and conflicting/unknown dates.

### Duty, legal-rest, and fatigue warnings

Define applicable FAA/company rules, effective dates, duty/rest boundaries, time-zone basis, and required source coverage before adding legal-rest or duty-margin warnings. Time-zone/circumnavigation and fatigue tags also need agreed definitions and dependable inputs. Keep any future fatigue or heavy time-zone-shift cue distinct from a legal determination. The existing scheduled-layover chips do not complete this feature.

### Private line tags and notes

Add owner-scoped tags such as Top Pick, Backup, or Avoid, with optional notes accessible beside selection actions. Define persistence and package-revision migration behavior before implementation; preserve manual rank and other users' privacy.

### Additional sources and exports

Scanned PDFs/OCR, dedicated Training Requirements PDFs, Bid List spreadsheets, Vacations spreadsheets, and spreadsheet export remain deferred. Confirm retention, ownership, visibility, normalization, and representative layouts before enabling each source. Training already present in supported documents still counts as occupied time; a dedicated Training tab is a separate feature.

A company-compatible spreadsheet export needs an agreed schema and review of the supplied Bid List workbook. Exporting or copying does not submit a company bid. Offline editing remains outside the current scope.

### Personal-data visibility

Resolve access to assigned crew, vacations, and training records within shared packages before displaying or importing them more broadly. General K4 Bids access must not expose other users' private preferences, notes, credentials, or bid lists.

## Open decisions

- Which additional source periods and layouts need representative fixtures beyond validated samples?
- Which current agreement amendments, company interpretations and assignment evidence support the unresolved compensation and legal-rest cases above?
- What visibility, retention and package-revision behavior apply to personal crew, vacation, training, tags and notes?
- What schema should company-compatible spreadsheet exports use, and what explicit portal evidence establishes award status?
- What definitions support deferred long-stay thresholds, deadhead count/duration and rest aggregation, broader regions, extra comparison views and later breakfast/fatigue scoring?

## Verification for future changes

Follow [AGENTS.md](AGENTS.md) for execution and integration rules. Keep live portal writes out of automated tests. Match focused coverage to delivered behavior, including source failures, UTC/compensation boundaries, missing versus zero values, exact arithmetic, private-state isolation and revision handling. Manual browser/touch/keyboard review remains outstanding where stated above. Record completion outcomes and commit messages in [TODO.md](TODO.md), then remove completed tasks from this backlog.
