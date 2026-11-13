# K4 Bids

K4 Bids helps pilots compare monthly bid packages and prepare a private, ordered bid list. This document records the current product contract and remaining work. Completed implementation tasks, validation history, and commit messages are recorded in [TODO.md](TODO.md).

Status (2026-10-28): Package import/publication, browsing, preferences, manual ranking, review/copy, compact cards, interactive trip timelines, credit calculations, icons, and trip endpoint/deadhead flags are implemented. Auto bidding has read-only discovery and an offline adapter; reviewed submission and the enhancements below remain pending. Manual browser, touch, and keyboard review is still outstanding.

## CBA accuracy review — 2026-10-29

Reviewed against the supplied [2021 CKS CBA](storage/app/private/CBA/2021_CKS%20CBA_Final.pdf), primarily **Part I, Section 5, Compensation**, and **Part I, Section 17, Scheduling**, with related definitions and cross-references. References below give printed pages first and one-based PDF pages in parentheses; printed page 25 is PDF page 37. This is a document comparison, not validation of application code, package totals, live portal behavior, or actual payroll.

The findings below qualify the product contract and historical examples that follow. **The implemented carry-in credit formula is not established as CBA-correct.** C1's terminology correction is implemented as recorded below; the remaining calculation findings require separate implementation tasks. Product choices such as private ranking, filters, colors, and portal security are not themselves contractual requirements.

The supplied agreement's cover states March 1, 2021–March 1, 2025, but Section 28 provides for renewal subject to its notice provisions; do not describe it as automatically expired. Appendix 1, LOA #1 makes the ratification date the effective date, with Sections 5.B and 5.C effective January 1, 2021 subject to ratification. This review does not establish whether later agreements or interpretations change the rules for the October/November 2026 samples. Sources: Section 28, p. 207 (PDF 219); LOA #1, pp. 269–270 (PDF 281–282).

### Compensation discrepancies and missing qualifications

**C1 — Resolved terminology: distinguish carry-in credit subtotal from PCH.** The application previously labeled next-calendar-month leg credit as PCH. Section 5.C.1 uses PCH to mean **Pay Credit Hours**; Section 5 applies it to flying, reserve, deadhead, vacation, training, and other qualifying activities. The UI now calls the product metric **Carry credit subtotal** and explains that it is not total contractual PCH. New parser results use `carry_credit_seconds`; presentation uses `carry_credit_label`. Previously saved `pch_seconds` values are read as the legacy subtotal without rewriting package payloads. An explicitly present new field, including null or zero, takes precedence. Existing credit amounts, premium arithmetic, fingerprints, and saved rankings are preserved; total contractual PCH is not newly calculated. C2–C6 and S1 remain separate calculation/coverage findings. Sources: Sections 5.C–5.N, pp. 25–29 (PDF 37–41).

**C2 — The blanket extra 50% carry premium is unsupported.** The documented `base credit + 50% of carry credit` formula, including a premium on credited deadheads, is not established by Sections 5.M, 17.D.7, or 17.K. Section 5.L.1.b provides 1.5 times hours flown for an **Open Time duty assignment on a scheduled duty free day off (SDFDO)**, paid above MBPG. Section 5.L.2 specifies a different greater-of rule for non-flight duty on an SDFDO, including reserve, training, and deadhead. Section 17.K.2 permits an election to work excess days for Section 5 compensation, subject to its conditions. Crossing a month boundary alone does not establish these entitlements. The seven November examples below demonstrate the existing arithmetic only; they are not verified contractual credit or pay. Sources: Sections 5.L–5.M, pp. 27–28 (PDF 39–40); 17.D.7, p. 118 (PDF 130); 17.K, pp. 124–125 (PDF 136–137).

**C3 — Section 5.M defines carry-in credit allocation; the departure-only subtotal does not implement it.** Under 5.M.1, the crewmember completes a duty assignment that originates in the current bid period and is scheduled to transition into the new bid period, and **all PCH for that duty is paid in the new bid period**. Under 5.M.2, **all PCH accrued during a bid-period transition is credited to the Month in which the last duty assignment occurs**, for Monthly Pay under 5.D. These are established allocation rules in the supplied CBA. Section 5.E's segment departure-day rule does not replace this transition rule, and 17.D.7 also credits time flown in the subsequent period to that period. The current calculation that includes only next-calendar-month departures and excludes every earlier departure does not capture all PCH for the transitioning duty. Implementation must identify the covered duty assignments and apply the compensation Month boundaries in S1; whether the imported package supplies those boundaries is a data-coverage question, not an undefined contractual allocation rule. Sources: Sections 5.E and 5.M, pp. 26, 28 (PDF 38, 40); 17.D.7, p. 118 (PDF 130).

**C4 — Block, elapsed time, and deadhead credit are not interchangeable.** Operating flight pay is one PCH per hour of **Actual Block Time**, with block-to-block defined to include taxi and tug positioning. Section 5.H.1 credits air deadhead at 50% of **scheduled** block time on a passenger carrier or **actual** block time on a non-passenger carrier, segment by segment. A blanket 50% of elapsed time omits this distinction. Section 5.H.2 credits nonlocal surface deadhead at 50% of actual drive time, subject to Section 19.G.4's published/assessed time provisions; setting every ground transfer to zero is incorrect as a compensation rule. Section 19.G defines local transportation as 60 minutes or less unless the parties agree otherwise. Retain mode, carrier category, scheduled/actual basis, and applicable published surface time; package schedules can support estimates, not establish actual pay. Sources: Section 2, pp. 11–12 (PDF 23–24); Sections 5.E and 5.H, pp. 26–27 (PDF 38–39); 19.G, pp. 142–143 (PDF 154–155).

**C5 — The 64-hour guarantee is verified in this CBA, with important exceptions.** Section 5.C.1 sets MBPG at **64 PCH**, prorated for partial-period unavailability as provided in the agreement. Section 5.C.2 sets the composite-line minimum at **70 PCH**. Section 5.D.1 bases monthly pay on the applicable rate and overrides multiplied by the greatest of MBPG, accrued bid-month PCH, and Bid Line Guarantee; additional compensation is addressed separately in 5.D.2–6. Sections 5.F and 17.E impose eligibility/availability/qualification conditions on line guarantee, and 17.E excludes reserve lines from its published flight-hour-value requirement. A reserve day on a published flying line does not itself receive reserve-day value under 17.E. Section 17.P also provides voluntary No Duty Bid Lines with no Section 5 compensation. Thus neither a universal 64-hour floor nor adjusted trip credit alone is a complete pay formula. Sources: Sections 5.C–5.G, pp. 25–26 (PDF 37–38); 17.E, p. 119 (PDF 131); 17.P, p. 129 (PDF 141).

**C6 — Trip PDF totals are not established as the contractual line guarantee.** Giving complete Trips totals precedence over Lines/Overview totals is an import/display policy. Sections 5.F and 17.E require a Company-assigned line value before package distribution, subject to the stated exceptions; Section 5.D.1 treats that guarantee separately from accrued PCH. Retain the published line guarantee independently from summed pairing credit and flag discrepancies instead of assuming a trip sum supersedes it. Flight/deadhead totals also omit other credit: reserve under 5.G is the greater of four PCH per day or actual PCH performed; vacation under 5.I is four PCH per day; 5.N contains training and CBT credit rules; 5.D.6 pays four PCH for each Company-assigned one-in-seven beyond three unpaid ones per bid month. These are completeness gaps for future pay estimates, not a requirement to implement payroll now. Sources: Sections 5.D–5.I and 5.N, pp. 25–29 (PDF 37–41); 17.E, p. 119 (PDF 131).

**C7 — The existing rate arrays have an identifiable CBA source.** Every value in `app/Data/pay_rates.json` matches Appendix 5-A's **January 1, 2024** column: `CA` is Captain, `FO` is First Officer, and array positions 1–12 correspond to longevity years 1–12. The appendix displays dollar hourly rates and has separate columns effective January 1 of 2021, 2022, 2023, and 2024. It does not split these pilot tables by fleet. This resolves the source column and longevity ordering, but the JSON still lacks explicit currency/effective-date metadata and does not prove that these are the applicable 2026 rates. Section 5.B also permits a discretionary first-year rate increase within its stated limit. Sources: Section 5.B, p. 25 (PDF 37); Appendix 5-A, p. 32 (PDF 44).

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
- **Scope:** The carry-in calculation clarifications were documentation-only. The user subsequently authorized C1's code/UI terminology correction; changes to the calculation rules remain separate tasks.

Illustrative daily comparison for two covered carry-in Z days:

| Z day | Awarded credit | Actual worked credit | Payable daily credit |
| --- | --- | --- | --- |
| Day 1 | 5:00 | 3:00 | 5:00 |
| Day 2 | 2:00 | 4:00 | 4:00 |
| Total | 7:00 | 7:00 | **9:00** |

The daily comparison produces 9:00, even though both aggregate input totals are 7:00. A segment departing at 2300Z and arriving at 0200Z contributes its full applicable credit to the departure Z day; the arrival day receives no separate portion of that segment. These examples describe valuation and date attribution, before any separately eligible premium, and do not recalculate the historical package cases below. The earlier example that simply added two legs' credit has been replaced because it did not demonstrate the awarded-versus-actual daily comparison.

For bid comparison before work is performed, awarded credit can be shown as a planning value. Actual worked credit remains unknown until supported by actual records; absence of those records is not zero actual work or a finalized payable amount. Keep the awarded schedule and actual work records distinct, including any relevant work that replaces an awarded assignment.

### Review disposition

Confirmed from the supplied CBA: UTC Day and Carry-In Days definitions, compensation Month boundaries, Section 5.M's transition-credit allocation, the 64/70 PCH guarantees, the existing rate arrays' 2024 source/order, and the scheduling provisions cited above. The user confirmed the duty-period boundary, per-Z-day greater-of-awarded-or-actual calculation, full-segment departure-day attribution, and conditional premium eligibility as recorded above. The daily greater-of rule is a user-confirmed clarification; this review has not identified a separate clause expressly stating that daily comparison. Unresolved for any future implementation: source coverage identifying duty periods, awarded daily credit and actual work, assignment-specific evidence of qualifying excess days or scheduled-day-off work and the applicable compensation treatment, published line-guarantee provenance, later amendments/current applicability, and account-specific eligibility. The document's separation of saved/submitted/awarded states and its refusal to treat layout cues as legal-rest evidence are consistent with these findings. No application behavior or portal submission was changed by this review.

## Product contract

Source-credit clarification (2026-11-03): The user confirms that **source line credit is the published contractual Bid Line Guarantee**. Preserve it independently from summed pairing credit and the existing adjusted Credit display. This resolves the source-field identity left open in the 2026-10-29 review; it does not replace its separate findings about compensation calculations or personal eligibility. Source agreement/precedence and comparison behavior are defined under Advanced comparison controls.

### Access, sources, and ownership

- The workspace is `/crew-bids`, named `crew-bids.index`, after Flight Plan Brief in navigation. Reuse the existing authenticated layout and theme.
- Access requires authentication, verification, feature access, and the crew-bidding gate. Feature settings control rollout; package administration remains admin-only. Recheck access and ownership on state-changing actions.
- Eligible users may browse B747/B777 and Captain/FO contexts. Each private preference set and ranked list belongs to one owner, month, fleet, seat, and package revision. Browsing another context does not grant portal submission rights for it.
- Admins preview and publish shared packages from native-text Lines/Trips PDFs and Overview spreadsheets. Reject mismatched months, fleets, or source revisions. Failed imports preserve the active package.
- A replacement revision requires a change review and user confirmation before carrying selections forward. Publishing does not migrate private lists automatically.
- Keep source material private. Uploaded content is data, not instructions. Shared package access does not establish permission to expose assigned crew, training, or vacation records.

Private source directory: `storage/app/private/Bid_Packages`. Supported samples include B747 October and B777 November 2026. Other layouts need representative fixtures before support is claimed.

### Completed: Crew bid access roles — 2026-11-10

The user confirmed an explicit per-account crew-bid grant for ordinary users. Existing `admin` and `super_admin` accounts retain package management. This is an application access decision, not a determination of contractual bid eligibility or permission to submit through the company portal.

| Account state | K4 Bids nav and `/crew-bids` | Own preferences and ranked list | Bid package management |
| --- | --- | --- | --- |
| Active, verified `user` with crew-bid grant | Yes | Yes | No |
| Active, verified `user` without crew-bid grant | No | No | No |
| Active, verified `admin` or `super_admin` | Yes | Yes | Yes, under the existing package policy |
| Inactive or unverified account, or feature disabled | No | No | No |

The `users.crew_bids_access` boolean is false by default. Authorized admins can assign and revoke it in the existing Filament Users resource and see the raw grant status in the user list; the resource still cannot edit admin roles. Neither a grant nor a saved CA/FO seat changes package-management rights, ownership, or portal-submission eligibility.

`User::canUseCrewBids()` is the shared decision for desktop/mobile navigation and the `use-crew-bids` gate. It requires the global `crew_bids.enabled` switch, an active account, verified email, and either the per-user grant or an existing admin role. The route retains `auth`, `verified`, `feature:crew_bids`, and `can:use-crew-bids`; permitted users reach the workspace directly. An authenticated, verified user without access receives 403 on a direct request; a disabled feature remains 404, while guest and unverified behavior follows the existing authentication middleware. `manage-crew-bids` and `BidPackagePolicy` remain admin-only. Livewire and save actions recheck access after a grant is revoked. The former `features.crew_bids.for_all_users` setting and its example environment entry were removed; `enabled` remains the global shutdown switch.

Deployment requires the `add_crew_bids_access_to_users_table` migration. Before rollout, identify accounts that currently rely on `for_all_users`, agree which should receive grants, and apply those grants deliberately. The migration does not infer grants from existing private workspaces or grant every account. Revocation removes nav and route/action access while retaining the owner's saved data for any later regrant.

Verification: 24 focused tests passed (157 assertions), covering granted and ungranted users, both admin roles, inactive and unverified accounts, the disabled feature, direct route and both nav layouts, admin grant/revoke, package-management denial, owner isolation, and revocation/regrant during an open workspace. No company-portal submission behavior was added. Pint passed after PHP edits, and one Larastan run passed with zero errors. No full suite or manual browser review was run.

### Bid package management — implemented 2026-11-03

Admins manage packages in **Filament → Bid Packages** (`/admin/bid-packages`). The K4 Bids **Manage package** control opens this resource. Access uses the existing `manage-crew-bids` gate and a `BidPackagePolicy`; disabling crew bidding also disables package management. The list includes drafts, published packages, and archived packages, with month/fleet/revision, importer, line/trip counts, saved-workspace count, and UTC publication time. Search month/revision and filter fleet/status. The preview shows identity, timestamps, counts, and source coverage without displaying users' private preferences or ranked selections.

Manual workflow:

1. **Import package:** upload up to three sources, at most one Lines PDF, one Trips PDF, and one Overview XLSX, each at most 12 MiB. Native-text PDFs and supported XLSX layouts remain required. Review the resulting draft's identity, counts, and coverage. Partial manual packages remain supported, with missing sources labelled explicitly.
2. **Replace draft sources:** the importing admin can regenerate an unused, unpublished draft from a complete replacement file set. Replacement does not merge with old sources, which are no longer retained. Identity and contents are derived from the new sources; there is no free-form metadata or payload editor. Validation failure preserves the draft. If the replacement files belong to another package, open that package instead; the existing draft is preserved.
3. **Publish shared package:** the importing admin confirms publication after reviewing the preview. Publication does not migrate private rankings. Published contents cannot be edited through package management; corrections are imported as a new revision, retaining the existing crew review/confirmation workflow.
4. **Archive / Restore package:** any authorized admin can archive a published package or restore its availability. Archiving hides it from new selections and newer-revision notices. Existing saved workspaces remain accessible and editable by their owners in the seat for which they were saved; archiving does not create access to another seat or migrate/delete lists. Restore makes the package selectable again.
5. **Delete draft:** only the importing admin can delete an unused, unpublished draft, after confirmation. Published packages and drafts with saved workspaces cannot be deleted; bulk deletion is unavailable. A restrictive database foreign key also prevents deleting any package with dependent workspaces.

Both the Filament importer and the retained Livewire import entry point use `ImportBidPackage`, with shared file validation, a five-attempts-per-minute limit per admin, parser-version/content fingerprint deduplication, and temporary-source cleanup. Identical imports reuse the existing package; importing an existing published package opens it without publishing a duplicate or restoring an archived package. Another admin's unpublished draft can be viewed, but not replaced, published, or deleted. Publication, replacement, deletion, and archive/restore recheck authorization; draft mutations lock/recheck the record inside a transaction. Unexpected import failures are reported with a recovery notification and leave stored packages unchanged. Normalized payloads remain encrypted; source files are not retained for download or later reprocessing.

Deployment requires the `add_package_management_to_bid_packages` migration, which adds nullable `archived_at` and changes the workspace package foreign key from cascading deletion to restrictive deletion. Rolling it back discards archive timestamps and restores the former cascade behavior. Automated email/portal intake, receipt history, retained source manifests, and automatic publication remain planned separately below.

### Preferences, scheduling, and ranking

- Goals use strict priority: maximize days off, maximize credit, or prefer fewer work blocks. A lower goal only breaks ties in higher goals; Line ID is the stable final tie-break.
- Requested UTC days off and selected start city are hard filters. Confirmed conflicts disappear from available results. Already selected choices remain in their manual order with warnings.
- All supplied package dates/times are UTC. Blank Overview date cells mean days off. Every UTC calendar date from trip report through release is occupied, including full layover/rest days, deadheads, reserve, training, and carry-in/out work.
- Days off and contiguous work-block counts apply to the selected month. Source duty days are a separate metric. Published carry counts retain their source scope; the calculated fallback counts unique occupied UTC dates after month-end.
- Incomplete dates remain unknown, with `Dates incomplete; conflicts unknown`; missing data never proves a conflict-free match or a day off.
- Preference/filter edits refresh results immediately and save independently. Browsing sort and filters never change the saved manual ranking.
- Flying and reserve choices share one unique, contiguous ordered list for the active context. Add appends a choice and offers Undo; drag/Move controls change rank.
- Autosave failures retain edits and offer Retry. Concurrent edits require reconciliation. Review/copy uses the persisted order and does not establish Submitted, Awarded, or Projected status.

### Workspace and presentation

- Returning users open saved results; first-time preference setup is skippable. Keep package statistics and source metadata under Package details and admin imports under Manage package.
- My bid list opens a desktop drawer or mobile full-screen panel. The close button and Escape key dismiss either layout; clicking the workspace backdrop also closes the desktop drawer. Closing preserves browsing context and returns keyboard focus to the control that opened the list, or to the header list button if that control has disappeared.
- Collapsed line cards fit within 150px without a card scrollbar: metrics/action, full-month calendar, then footer controls. Line and Trip sit inline on the left; Details and the question-mark match disclosure stay on the right. Expanded content may grow.
- Compact flags beneath the line ID show a common return airport, a common start/end route, or mixed trip endpoints, plus known deadhead presence. Endpoint flags require complete linked-trip endpoint coverage; positive deadhead evidence may still appear with partial coverage. Use icons on narrow screens, labels on larger screens, and full explanations in Details. These flags describe trips, not an assigned crew base.
- Use one interactive chevron timeline per trip. Exact dated UTC times, block basis, elapsed duration, and available credit/rest information expand at the selected segment. Avoid a duplicate vertical leg list.
- Keep the workspace header sticky and the expanded card's Add/Selected bar below it. Retain independent previews and keyboard-accessible Timeline / Legs, Layovers & Rest, and Pairing Metadata tabs.
- Scheduled connections under three hours are grouped separately. Layover chips are amber below 10 hours and blue at 24 hours or more. Exactly 10 hours is not short; exactly 24 hours is extended. Source rest is separate. These cues do not establish legal rest or international status.
- Omit missing metrics together with their labels, while preserving real zeros, trip identifiers, conflict/incomplete-date warnings, inconsistent-connection evidence, and import diagnostics.
- Keep airport codes and times prominent. Use existing airport-detail popovers where applicable and reuse the brand palette: Aviation Blue `#1B365D`, Compass Gold `#C5A059`, and existing light/dark tokens.
- Use the existing cart, plus/check, calendar, airplane, document, cog, and question-mark icons with accessible names. Reserve occupancy is orange, flying occupancy blue, off dates neutral, and unknown dates marked `?`. Do not rely on color alone.

### Block, credit, and PCH definitions

**CBA definitions and user-confirmed calculation rules:**

- Source line credit is the user-confirmed published contractual Bid Line Guarantee (2026-11-03). The importer retains this original value as `source_credit`; complete Trips totals may replace the working `credit`/`base_credit` values but must not replace the published guarantee. Keep the published guarantee, base pairing credit, and adjusted display credit separately labelled.
- PCH means Pay Credit Hours. Carry-In Days (also called Transition Days) are duty days continuing from the current bid period into the subsequent bid period.
- Section 5.M.1 pays all PCH for a duty assignment originating in the current period and scheduled to transition into the new period in the new period. Section 5.M.2 credits all PCH accrued during the transition to the Month of the last duty assignment for Monthly Pay under 5.D.
- The user identifies the transitioning duty assignment as one duty period, potentially containing multiple legs. Calculate its carry-in credit per Z day: assign each flight segment's full credit to its departure Z day, compare awarded and actually worked credit separately for each covered day, and sum the greater daily amounts. Preserve daily attribution separately from the receiving compensation period under Section 5.M.
- The daily comparison is `sum(max(awarded credit for day, actual worked credit for day))`, not `max(total awarded credit, total actual worked credit)`. Before actual work is known, awarded credit is a planning value, not a finalized payable amount. This daily comparison is user-confirmed; a separate supporting CBA clause has not been identified in this review.
- Compensation Months follow calendar months except January 1–30, January 31–March 1 for February, and March 2–31 for March. Thus January 31 and March 1 both belong to the February compensation Month.
- This allocation determines which period receives the credit. It does not itself prescribe an additional 50% premium. Sources: Section 2, pp. 13–14 (PDF 25–26); Section 5.M, p. 28 (PDF 40).
- The user confirmed that premium eligibility depends on qualifying excess days or scheduled-day-off work. Apply the relevant compensation provision to the eligible assignment; a blanket 50% addition to carry credit remains incorrect.

**Current carry-credit metric — calculation discrepancies remain:** C1's terminology is corrected. The paragraphs and examples below describe the existing arithmetic, not a complete contractual compensation calculation. Findings C2–C6 and S1 identify unsupported premiums, missing credit categories, and allocation/guarantee distinctions. The next-month subtotal is not total contractual PCH or the full allocation required by Section 5.M.

Block and adjusted Credit display and sort independently. Complete Trips PDF Block Time and Credit Time totals take priority over Lines/Overview totals. Preserve source values and the calculation breakdown in Details. Deduplicate pairing references; incomplete linked totals retain recorded line values without a partial premium.

The **Carry credit subtotal** includes only legs departing in the next calendar month in UTC: operating flights at 100% of elapsed time, deadheads at 50%, and ground transfers at zero. A flight departing before calendar month-end contributes nothing to this subtotal even if it arrives next month. The existing formula assumes full Trips base credit already includes underlying flying/deadhead credit, then adds an extra 50% of the subtotal, including credited deadheads, to that base. These are the implementation assumptions under review, not contractual requirements. The terminology correction preserves this arithmetic and does not implement the daily awarded/actual comparison or evaluate premium eligibility. Preserve exact seconds when fractional minutes result; do not use decimal-hour notation.

November B777 historical implementation cases (arithmetic examples; not CBA-validated entitlements):

| Line | Block | Base credit | Carry credit subtotal | Extra 50% | Adjusted credit |
| --- | --- | --- | --- | --- | --- |
| F83 | 90:15 | 97:30 | 7:15 | 3:37:30 | 101:07:30 |
| F61 | 96:50 | 96:50 | 14:30 | 7:15 | 104:05 |
| F46 | 83:05 | 87:52 | 7:30 | 3:45 | 91:37 |
| F55 | 85:40 | 85:40 | 7:30 | 3:45 | 89:25 |
| F38 | 91:10 | 91:40 | 0:00 | 0:00 | 91:40 |
| F26 | 89:45 | 89:45 | 0:00 | 0:00 | 89:45 |
| F23 | 74:35 | 75:05 | 2:00 | 1:00 | 76:05 |

References: `storage/app/private/Bid_Packages/777 November 2026 Lines.pdf` and `storage/app/private/Bid_Packages/777 November 2026 Trips.pdf`.

## Plan: Automated bid-package imports — 2026-10-29

### Goal and publication policy

Receive the monthly package without manually downloading and selecting its attachments. The user also receives a work Outlook email with approximately ten PDF/Excel attachments. **Automatically publish complete, validated first packages; hold revisions for admin review.** Direct public HTTPS links for the December B777 Lines, Trips, and Overview sources were verified on 2026-11-13. Portal retrieval is the first implementation path; email intake remains a later adapter pending a real work-email manifest and forwarding/provider decisions.

The portal import uses a configurable admin owner and needs no stored portal credentials for the verified direct links. It does not submit a bid, migrate saved lists, or correct the compensation calculations identified in the CBA review. The checked-in schedule is off by default until an operator enables it with an active, verified admin ID; the local Sail environment was enabled with admin ID 1 after the December import and focused checks.

### Evidence and existing integration points

- Filament package management and `CrewBids::previewImport()` share `ImportBidPackage`, allowing one to three PDF/XLSX files, at most 12 MiB each. `ParseBidDocuments` accepts at most one Overview XLSX, one Lines PDF, and one Trips PDF for a matching month/fleet/revision. It allows partial source coverage for manual imports. Passing all ten attachments directly to this parser would fail.
- Local samples include Lines, Trips, Overview, Training Requirements, Bid List, and Vacations files across B747/B777 and multiple months. These establish likely document roles, not the complete manifest of any one email. Dedicated training, vacation, and bid-list imports remain deferred.
- Package payloads are encrypted; the parser fingerprint incorporates sorted source hashes and parser version. Manual import deletes uploaded source files after parsing. Automation needs a short-lived, private staging lifecycle and delivery provenance in addition to the existing package fingerprint.
- `PublishBidPackage` authorizes `manage-crew-bids`, checks draft ownership through `imported_by`, and publishes in a transaction. Queued automation has no interactive authenticated user; a dedicated source authorization path is required. It must not simply call this action without an actor or bypass authorization.
- The December B777 files `777 December 2026 Lines.pdf`, `Trips.pdf`, and `Overview.xlsx` returned HTTP 200 with expected document types from `/hyperlinks/` without login. The existing focused Filament import test parsed all three as one complete December package. The corresponding B747 names returned HTTP 200 with expected types; the new command parsed and published the complete December B747 group under admin ID 1, while the existing B777 release was unchanged. Direct links establish these releases' source sets; they do not prove a directory listing, a stable naming contract for every month, or a revision marker in the Overview workbook. `/Pages/Documents.aspx` remains uninspected and the bid-submission discovery command remains separate.
- The connected Outlook account is personal, not the work mailbox. The actual package message, sender rule, attachment count/size, forwarding behavior, and correction-email format have not been verified. No personal mailbox content is needed for implementation.
- Laravel 13.34.0 was confirmed through Sail. Existing scheduled work is registered in `routes/console.php`; the project already uses single-server and overlap protection. Mailgun transport/configuration exists, but this does not prove production inbound-mail support is configured.

### Source options

| Source | Plan | Prerequisites and tradeoffs |
| --- | --- | --- |
| Scheduled crew-bids download | First adapter: construct only the expected `/hyperlinks/{fleet} {month} {year} {Lines.pdf, Trips.pdf, Overview.xlsx}` URLs, validate all three files, parse, deduplicate, publish a first release, and hold changes. | Direct access is verified for December B747 and B777. Future naming, correction behavior, and source revision metadata must be observed. A renamed or unavailable file holds the group; it is never inferred from an old release. Configure an accountable admin before enabling the schedule. |
| Outlook forwarding → inbound email provider → Laravel | Later adapter. Match verified sender/subject and correction patterns, classify all attachments, and feed the same package rules. Retain originals in Outlook. | Verify the actual manifest, allowed external forwarding, provider, payload sizes, and receiving domain. Microsoft 365 can block external forwarding. No work-mailbox password is needed in Laravel. |
| Microsoft Graph mailbox reader | Fallback if forwarding is blocked and the organization permits a mailbox integration. Read a dedicated folder using approved OAuth access and a resumable checkpoint. | Requires work-account app registration/consent and token lifecycle management. The personal Outlook connector is not a production Laravel integration. Do not start with this additional integration unless forwarding and portal access are unsuitable. |

Microsoft documents organization-level [external-forwarding controls](https://learn.microsoft.com/en-us/defender-office-365/outbound-spam-policies-external-email-forwarding). For inbound delivery, first check the existing Mailgun account/domain: [Routes can POST received messages and attachments](https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/receive-http), with [signature verification](https://documentation.mailgun.com/docs/mailgun/user-manual/webhooks/securing-webhooks). Select and test one supported payload format; signature locations vary by format. If Mailgun receiving is unavailable, [SES receiving](https://docs.aws.amazon.com/ses/latest/dg/receiving-email.html) with private object storage is an alternative, subject to domain/region setup. Choose the provider after checking existing production facilities, message-size limits, and receiving costs; do not add dependencies or infrastructure merely because both alternatives are listed.

### Shared intake and package validation

The target shared flow is: **receive → stage privately → classify attachments → group package sources → validate → create draft → publish first release or hold revision**. The first portal adapter handles one known three-file group at a time: it downloads to private temporary files, validates response type and size, reuses `ParseBidDocuments`, and deletes the temporary files. It uses the existing unique fingerprint and a per-period/fleet lock, then publishes a first package or holds changed content. Durable delivery receipts, cross-source provenance, an admin import-history view, and email-specific classification remain in the next shared-intake phase.

1. **Receive durably.** Verify the provider signature, freshness/replay rules, configured recipient, and registered forwarding-source policy before accepting an email. Provider authentication proves who delivered the webhook, not that a visible From header is genuine; forwarding provenance needs a sample-based policy using provider authentication results and expected sender/forwarder information. Persist the receipt and bounded attachment data before acknowledging success; retry delivery safely after temporary failures. Queue parsing after the receipt transaction commits. Jobs carry receipt IDs, not email bodies, credentials, or attachment bytes.
2. **Inventory every attachment.** Record private filename, byte size, content hash, detected format, classification, and reason for exclusion/hold. Classify using PDF content and workbook structure as well as names. Ignore proven inline signature images, but flag unexpected document attachments. Do not equate “ten attachments” with completeness. Detect truncated, encrypted, malformed, oversized, or unsupported files. Bound ZIP expansion/XML processing for XLSX, avoid external references and macros, and never execute document content. `.xls`, `.xlsm`, and scanned PDFs need explicit support decisions; they are not silently treated as supported XLSX/native PDFs.
3. **Build coherent groups.** Separate sources by period, fleet, revision, and seat where source documents actually distinguish seats; the present package model is period/fleet-based and carries crew complements. Associate each supported group with its Lines, Trips, and Overview. Classify Training Requirements, Bid List, and Vacations without adding their personal contents to shared package payloads. Hold unknown roles for review. Do not combine a new revision's Lines with an earlier revision's Trips or Overview, even when filenames match. A date in an email subject is a hint, not package identity.
4. **Define a complete automated import.** Proposed initial profile: one native Lines PDF, one native Trips PDF, and one supported Overview XLSX per period/fleet group. Confirm that profile from one full delivery before enabling it. Require matching identity, supported schema, successful parse, expected line/trip references, consistent coverage, and existing source-consistency checks. An Overview may lack a revision marker; it must have coherent delivery provenance and pass cross-file validation rather than being assigned an invented revision. A parser success with partial coverage does not qualify for automatic publication. Extra known unsupported document roles are explicitly excluded; an unexplained missing required role holds that group. Independent fleet groups may publish independently if each is complete; never publish a partially validated group.
5. **Deduplicate and order revisions.** Use a durable unique receipt identity for delivery retries, attachment hashes, and the existing parser-version/package fingerprint for normalized drafts. Add database uniqueness and per-context locking for simultaneous email/portal arrivals; queue uniqueness alone is insufficient. Identical content is a no-op with additional provenance. Changed content for a previously published period/fleet is a revision even if its printed revision label is unchanged. Different labels or conflicting contents hold for review; receipt time and lexicographic labels do not establish a newer revision. Never infer deletions or replace good files from an incomplete delivery. A partial correction can reference an earlier manifest only through a reviewed, explicit merge.
6. **Apply publication policy atomically.** With the context locked, recheck completion, registered-source authorization, duplicate status, and whether a published package already exists. A first complete validated package may publish automatically. Any changed package in that context becomes a reviewable draft showing source differences, affected lines, and validation results. Reuse publication behavior through an action with explicit automated-source authority and an accountable configured admin owner; recheck authorization if that owner/source is disabled. Keep interactive ownership and gate checks intact. Publishing a revision must not migrate users' selections or change their manual order; preserve the existing migration review and confirmation.

Proposed durable records are an import receipt with state/source/attempt details, a private attachment manifest, and links from each resulting package to its receipts. Keep normalized `BidPackage` payloads in the existing model. Reuse `ParseBidDocuments`, `ReadBidOverview`, and the publication behavior. Manual draft creation now lives in `ImportBidPackage`; automation still needs its own explicit source authority and staging lifecycle rather than invoking UI methods or assuming an authenticated admin. Database schema and migration details belong to implementation after inspecting the schema.

### Email delivery implementation

Create a dedicated receiving subdomain/recipient without replacing the organization's work-mail MX records. Confirm provider support and the largest complete sample message, including encoding overhead, against provider, web-server, PHP, and storage limits. Start with a controlled manual forward into the intake flow, inspect its manifest, then configure the narrow Outlook rule once that works. Include revised-package subjects and provide manual forwarding as recovery.

Prefer a signed multipart attachment webhook if the existing inbound provider supports the actual message sizes. If messages exceed safe request limits, use authenticated provider storage retrieval or private object storage with a small event notification. Download only from verified provider destinations; do not follow arbitrary URLs in email bodies. Treat forwarded-as-attachment `.eml`/`.msg` and cloud-file links as explicit cases: support the observed format or hold with a clear reason rather than pretending there were no attachments.

Proposed staging retention is seven days for raw messages and attachments, including failed/revision-held deliveries, with an expiry notice and a way to supply the source again. Keep only necessary normalized package data and minimal provenance afterward; configure provider-side retention too. Restricted raw training/vacation/bid-list documents remain admin-only during staging. Logs and status notifications contain IDs, counts, and failure categories, not full messages, personal records, credentials, or signed download URLs. Confirm this retention choice before implementation.

### Portal discovery and scheduled checking

The first adapter uses only the verified host and constructed `/hyperlinks/` filenames; it does not follow redirects or crawl portal links. `crew-bids:import-portal` defaults to the next UTC bid month and both fleets, with `--period`, `--fleet`, and `--admin-id` for a bounded operator run. A 404 for any required source means “not yet complete”; login HTML, redirects, bad type, oversized content, and mismatched identities fail closed. Compare a future correction with its work-email copy when available, and inspect `/Pages/Documents.aspx` separately to learn whether there is a reliable manifest or revision signal.

If future downloads require authentication, unattended checking cannot use the bid-submission feature's transient credentials. Choose an import-specific authentication policy or keep those releases manual. The current adapter stores no portal secret and never posts to the bidding portal.

The configured UTC schedule checks hourly on days 8–16 and every six hours otherwise, including after the first release so corrections are detected. It is disabled by default until `CREW_BIDS_PORTAL_IMPORT_ENABLED=true` and `CREW_BIDS_PORTAL_IMPORT_ADMIN_ID` names an active, verified admin. These are polling defaults, not contractual publication dates or verified portal rate limits. The parser requires coherent PDF identity and source coverage; a changed fingerprint for an existing period/fleet is held for admin review even if its printed revision label is unchanged.

The current command runs synchronously under `onOneServer()` and `withoutOverlapping()` with a second per-period/fleet cache lock. GET requests have connection/response timeouts and bounded transient retries. The Filament Bid Packages page also provides an authorized “Fetch from CrewBids” action for a selected month and fleet; it reports published, revision-held, unchanged, missing, and failed outcomes. The manual upload path remains available. Local `composer run dev` now includes `schedule:work`; a separate scheduler worker was started for the current Sail session. A deployed environment still needs its normal once-per-minute `schedule:run` service. The next operational phase must persist last successful check versus last new package, expose failures/retries, and add source history; scheduler locks do not replace those records. Laravel documents [scheduler overlap/single-server controls](https://laravel.com/docs/13.x/scheduling) and [queue uniqueness and retries](https://laravel.com/docs/13.x/queues).

### Delivery sequence and acceptance

| Phase | Result and acceptance |
| --- | --- |
| 1. Direct portal adapter — implemented | Check known three-file groups, reject incomplete/invalid responses, parse matching identity, use a fingerprint for idempotency, publish a first complete release, and hold changed releases as drafts. Keep scheduled use off until an admin owner is configured. |
| 2. Confirm release and email manifests | Inspect a complete work-email delivery and a correction; establish sender, attachment roles, fleets, revision evidence, and forwarding feasibility. Inspect portal Documents for a stable manifest and confirm naming beyond December B777. |
| 3. Shared intake and classification | Add durable receipts, source hashes/provenance, short-lived staging, complete grouping, and actionable holds. Existing manual imports continue to work. No partial group replaces a published package. |
| 4. Email adapter and publication | Verify a controlled forward end to end; classify all attachments and converge identical portal/email content on one package. Enable the narrow Outlook rule only after verification. |
| 5. Operational rollout | Show import history, last successful check, source, missing roles, revision differences, and retry/review actions in Manage package. Add late-arrival status and verify scheduler/worker operation. |

For late-arrival monitoring, use the supplied CBA's 2359Z on the 12th publication deadline as an initial configurable expectation, tied to the target bid period. Show a missing-package warning after it passes and an actionable pre-bid-close warning before the expected 16th close. Confirm current company timing and notification lead time. Neither a failed poll nor a missing email proves that the company has not published a package.

Focused acceptance coverage should include the ten-attachment classification case; multiple fleets; partial/mismatched revisions; misleading filenames; unsupported Excel/PDF formats; missing required roles; corrupted and oversized files; spoofed or replayed intake; provider retries and concurrent arrivals; identical content across sources; older/out-of-order corrections; first-release automatic publication versus revision hold; disabled source/admin authority; privacy of excluded personal documents; worker failure/recovery; expiry; and preservation of published packages and saved rankings. Portal-specific tests use fake HTTP responses for login expiry, throttling, changed HTML, and interrupted downloads. Follow the testing-best-practices skill, use synthetic fixtures for personal data, and never hit live bid-write endpoints. At implementation integration run focused tests, Pint after PHP edits, and Larastan once.

Before enabling scheduled portal checks, configure an accountable active bid admin and verify the deployed cache store supports shared locks. Before email implementation, resolve the real attachment manifest, permitted forwarding and receiving provider/domain, and staging retention. The user has selected automatic publication for complete first releases and review for revisions. Existing CBA credit discrepancies remain documented; import validation does not certify compensation calculations as correct.

## Current focus: Auto bidding feature

Implementation status (2026-11-13): Read-only command discovery, offline Choice/Line preparation, and offline Submitted Bid Lines PDF and Bid Received email parsers are implemented with regression coverage. User-run authenticated discovery has reached both a closed-window and an apparently open-window Bid Sheet; live report retrieval, source authentication, and reviewed submission remain pending. The user confirmed that this feature means submitting their reviewed bid list to the airline's bidding system, using employee number/password without MFA, CAPTCHA, or SSO. It does not mean automatically generating preferences or submitting on a schedule. The existing review/copy workflow remains available.

The user previously confirmed that bidding was closed when the earlier readonly inputs were observed, and that portal numbers are unique: F1 → 1, Q99 → 99, R70 (also written R 70) → 70. The adapter accepts F/Q/R plus an optional space before a positive number, rejects numeric collisions across prefixes, and uses the displayed Choice values to order paired fields rather than DOM order. Runtime slot count, maximum line, and training rules constrain an offline candidate; it includes blank trailing line fields without changing unrelated settings. This does not prove account/seat eligibility, the bid window, or submission acceptance.

Run `vendor/bin/sail artisan crew-bids:discover-portal` interactively for hidden employee-number/password prompts. Optional `--lines='F1,Q99,R70'` checks a candidate against observed numeric/training rules without posting it. It also compares the candidate with the Bid Sheet's visible form values and reports only added/removed counts and whether retained choices changed relative order. Those values are not a verified submitted order. Duplicate existing form numbers make the comparison unavailable. The command sends only the login POST and allowlisted page GETs, never a Bid Sheet POST. It uses a separate HTTP factory without application HTTP events/global middleware, an operation-local memory cookie jar cleared on exit, explicit timeouts, and no retries. Output contains counts, supported control names, and a report-link flag; it omits credentials, cookies, raw page/state values, account labels, and actual bid choices. It neither fetches the submitted report nor verifies context, deadlines, receipt semantics, or account matching. It is a local discovery tool, not an application submission flow.

The public root form's observed `pages/Login.aspx` action and redirected `/Pages/Login.aspx` login forms are supported. When login redirects to the user-confirmed `/Redirect.aspx`, discovery visits that landing page with the same cookie jar before opening Bid Sheet. Repeated radio-button names, such as CBT settings, are accepted without including their values in the candidate; duplicate hidden, text, and mixed-type fields remain rejected. Unexpected failures identify the operation step and exception type, with only a numeric cURL code or PHP source basename/line when available. Raw upstream error messages, URLs, full paths, and previous exceptions remain excluded. An authenticated failure still requires the user-run diagnostic to establish its cause; a successful public GET alone does not verify login.

An earlier user-run command returned 315 choice slots, zero editable slots, 21 populated slots, no submission-control names, and a Submitted Bid Lines link. A subsequent user-run command on 2026-11-13 returned 315 choice slots, 315 editable slots, 43 populated slots, the enabled control name `ctl00$ContentPlaceHolder1$btn_update`, and the same report-link flag. Neither run supplied a candidate list. The later structure is consistent with an open bid window, but the command did not inspect the window or deadline and did not press the control. The control name is an observed candidate for a submit/update action, not evidence of its event, accepted payload, or receipt. The 43 populated slots are counts only; they do not establish the complete submitted order or its account/context. No bid was submitted by discovery.

A later supplied open-window page capture shows one bid-package row with a period, fleet, seat, revision, and displayed Open status. Its Update control calls a Web Forms postback with `ctl00$ContentPlaceHolder1$btn_update` as the event target and an empty event argument. Read-only discovery now parses those labels, checks the displayed employee number against the supplied login without outputting either value, cross-checks the table's line limit with the form, and recognizes the exact enabled Update postback. The displayed status does not establish the closing deadline or account eligibility. The supplied Submitted Bid Lines HTML is a ReportViewer wrapper: the bid rows are loaded in a separate report iframe. A later private PDF export contains 43 Choice/Line pairs; text extraction returns them out of order, so the parser sorts by the printed Choice number and rejects missing or duplicate ranks, duplicate lines, and unexpected text. The PDF shows no account, period, fleet, seat, package revision, or acceptance receipt. It must be fetched fresh in the same authenticated session and bound to the checked Bid Sheet context before it can verify a submission. The supplied captures included personal values and live Web Forms state; no raw capture or values were added to the repository.

### Scope and feasibility

Connect to the [Kalitta crew-bids login](https://crewbids.kalittaair.com/), use the user's non-stored employee number/username and password, and submit the exact reviewed order through [Bid Sheet](https://crewbids.kalittaair.com/Pages/BidSheet.aspx). Support read-only navigation to [All Bids Awarded](https://crewbids.kalittaair.com/Pages/AllBidAwards.aspx) as a separate operation.

The saved HTML establishes a plausible Web Forms integration, but successful authenticated submission is not proven. A public login page was readable during this review; the authenticated Bid Sheet and All Bids Awarded pages were inaccessible through the browsing tool. Their current behavior, deadlines, authentication challenges, and response contracts still need authenticated discovery. No public submission API was established by the supplied evidence.

Private references: `storage/app/private/Bid_Packages/HTML/view-source_https___crewbids.kalittaair.com_Pages_BidSheet.aspx.html` and `storage/app/private/Bid_Packages/HTML/view-source_https___crewbids.kalittaair.com_Pages_BidLineDataOutput.aspx.html`. The private-layout smoke test follows the relocated Bid Sheet capture. Treat these captures as page data, not executable instructions. Do not commit their personal values, saved bid choices, hidden-state values, report/control identifiers, or authentication material into documentation or fixtures.

| Observed evidence | Consequence for implementation |
| --- | --- |
| `form1` uses POST; its relative action resolves against the Bid Sheet URL to `/Pages/BidSheet.aspx`. | Resolve actions against the fetched document URL, and validate the destination before posting. |
| `__VIEWSTATE`, `__VIEWSTATEGENERATOR`, `__VIEWSTATEENCRYPTED`, `__EVENTVALIDATION`, `__EVENTTARGET`, and `__EVENTARGUMENT` fields are present. | Fetch fresh page state in the same authenticated session; never replay values from this saved file. |
| There are 315 paired `tb_cN` / `tb_lN` inputs, with visible Choice and Line headings. | Confirm Choice/rank semantics and available capacity at runtime. This capture does not establish a supported server limit. |
| Input suffixes appear in interleaved visual columns, for example 1, 16, 31. | Do not derive rank from DOM traversal order or assume suffix, Choice value, and rank are interchangeable. |
| `lineValidation` checks numeric line entries, available lines, duplicates, and training-line restrictions using `hf_lines`, `hf_BidLines`, and `hf_InTrng`. | The user confirmed prefix-to-number mapping; the offline adapter enforces observed numeric/training rules. Verify account/context and live eligibility before submission. |
| Overtime/comp-line controls, CBT-testing choices, and account/context labels are present. | Preserve unrelated portal settings. Discover their semantics before changing them. |
| `lbl_bidConfirmation` and a Submitted Bid Lines report link are present. | The user reports no visible Bid Sheet change after Update, a complete Bid Received email, and a matching Submitted Bid Lines report. Treat the label as an unsupported receipt source; bind the email and fresh report to the authenticated context. |

The live editable sheet identifies the Update postback event. The user reports that Update causes no visible Bid Sheet change and sends a complete Bid Received email. A supplied excerpt contains a fleet/seat subject, explicit bid-received wording, an employee-number field, and ranked Choice/Line rows; the user confirms the email lists every choice and a Submitted Bid Lines report showed the same updated order. The synthetic email parser checks that structure and the expected employee/fleet/seat without returning the name or account number. The email lacks a bid period and the sender/recipient delivery path has not been authenticated in the app. Do not infer success from the existence of a form, a confirmation-label ID, a redirect, or HTTP 200. Prefer the application's existing HTTP capabilities if an authenticated spike proves the complete protocol. Use browser automation only if the verified login/submission flow requires it; dependency changes need a separate implementation decision.

### User workflow

1. From My bid list, review the saved ordered list, package revision, month, fleet, seat, and existing warnings. Submission uses this exact sequence, independently of browsing sort or the active Lines/Reserve tab. Block unsaved changes and unresolved revision migration.
2. Choose a future `Submit to Kalitta` action and enter credentials in a transient form. A read-only preflight establishes the authenticated account, active bid context, window/deadline, current submitted choices, valid line mappings, and eligibility. Keep viewing other fleets/seats separate from permission to submit for the authenticated account.
3. Compare the portal's existing order with the proposed replacement. Show additions, removals, reordering, total choices, and any context discrepancies. Reject unknown mappings, duplicate choices, unsupported eligibility, or excess capacity; never silently truncate a local list, which can currently contain up to 1,000 choices.
4. Require a deliberate final Submit action for the exact account/context and numbered replacement shown. Bind that review to the local workspace revision and list hash and to a normalized portal-context/current-order hash. The implementation must establish how portal package changes are detected; matching the month alone does not verify the source revision.
5. On submission, log in/fetch fresh state, recheck those bindings, and send the verified submit action once. Set each Choice/Line pair according to the established mapping. Clear superseded trailing choices only as part of the reviewed replacement; preserve unrelated portal settings. Changed state returns the user to review before any write.
6. Fetch a fresh report in the authenticated session and reconcile the complete ordered list and context. A Bid Received email can provide separate receipt evidence when its source, expected employee/fleet/seat, and full list are verified. Show `Submitted — verified` only when the established evidence proves acceptance and the stored order matches. Keep the local saved list available on every failure. A submitted bid and an awarded line remain separate states.

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
| Authenticated Bid Received evidence and fresh context-bound read-back both match | Record the verified outcome and show the receipt details actually available. An unchanged Bid Sheet alone is insufficient. |
| Timeout/disconnection after a potentially sent submission, or contradictory read-back | Show `Submission status unknown`; reconcile through a new read-only operation before offering another submission. Never automatically retry the write. |
| Repeated click or replayed request | Use a per-owner/context operation lock and single-use local attempt token; reconcile any prior attempt before sending another write. A local token does not guarantee remote idempotency. |

Read retry policies and write retry policies must be separate. Verify the live portal deadline and its displayed time zone against the contractual UTC deadlines in finding S6; package timestamps alone do not establish the portal's displayed time basis or actual open window.

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

### Delivery sequence and acceptance

| Phase | Reviewable result |
| --- | --- |
| 1. Authenticated read-only discovery | Verified login/challenge flow, account/context selectors, Choice/Line mapping, submit event, receipt/read-back contract, deadline basis, and actual award-page inventory. No bid write. |
| 2. Offline adapter and preflight | Redacted synthetic fixtures, fresh-state parsing, complete rank mapping, eligibility/capacity checks, context comparison, credential-redaction checks, and read-only preview. No bid write. |
| 3. Reviewed submission | Explicit final confirmation, stale-state protection, one write attempt, receipt/read-back reconciliation, and unknown-outcome recovery. Validate against a test endpoint or a separately authorized controlled live submission. |
| 4. Award integration decision | Choose the smallest owner-scoped read-only award feature supported by the discovered data; keep wider crew analytics outside the submission feature. |

Implementation coverage must include F/Q/R mapping and unsupported identifiers, interleaved input order, mixed flying/reserve ranks, zero/maximum/excess choices, removal of trailing old choices, training restrictions, preservation of other portal settings, expired page state, login/context mismatch, changed local/portal revisions, cross-owner isolation, double clicks, timeout-after-send reconciliation, and credential/cookie/state redaction. Use fake transport and synthetic responses for automated tests; tests must never contact the live submit endpoint. Run focused tests, Pint after PHP changes, and Larastan once at implementation integration.

Remaining decisions are fresh authenticated report retrieval, source/recipient authentication for the Bid Received email, period binding for both receipt and report, deadline/time-zone semantics, and award schema/retention. The prefix-to-number mapping, unchanged Bid Sheet plus complete email behavior, and matching post-Update report are user-reported; the open-window form, email excerpt, and offline Submitted Bid Lines PDF layout have been observed. Do not enable submission until a controlled Update and fresh, context-bound read-back demonstrate the full protocol.

## Remaining enhancements

### Browser and accessibility review

Verify narrow/desktop widths, both themes, full-month visibility, the 150px collapsed layout, independent disclosures, sticky action/header overlap, and mobile drawer return behavior. Exercise keyboard date inspection, tabs, add/remove, ranking, focus restoration, save announcements, and visible warnings. Check long routes, large credits with seconds, many pairings, missing metrics, and conflicting/unknown dates.

### Duty, legal-rest, and fatigue warnings

Define applicable FAA/company rules, effective dates, duty/rest boundaries, time-zone basis, and required source coverage before adding legal-rest or duty-margin warnings. Time-zone/circumnavigation and fatigue tags also need agreed definitions and dependable inputs. Keep any future fatigue or heavy time-zone-shift cue distinct from a legal determination. The existing scheduled-layover chips do not complete this feature.

### Private line tags and notes

Add owner-scoped tags such as Top Pick, Backup, or Avoid, with optional notes accessible beside selection actions. Define persistence and package-revision migration behavior before implementation; preserve manual rank and other users' privacy.

### Advanced comparison controls

Planning review and user decisions: 2026-11-03. Implement one subtask at a time after its definitions are settled. Tasks blocked on a source or contractual definition do not block independent controls. The user confirmed hard filters with selected bids retained, base pairing credit, published carry count with a calculated fallback, UTC report times and split detection as described below, final-trip end city, avoidance across all leg endpoints, country grouping, temporary filters, and deferral of long stays and extra views.

#### 1. [x] Completed: Agree on comparison definitions and source coverage

**Goal:** Give each control a documented value, time basis, source, completeness rule, and matching rule.

**Current implementation:** Definition and source-coverage review completed 2026-11-03. Audited `CrewBids`, `BidLineMatcher`, `ParseBidDocuments`, `ReadBidOverview`, `AirportData`, and existing import/matching tests. The user confirmed the comparison rules below, including source line credit as the published contractual guarantee. Existing import tests passed for the B747 October and B777 November native-text packages. Existing runtime controls remain as implemented in subtask 2; this task defines the source contract for subsequent controls and records current normalization gaps rather than changing application behavior.

**Problem:** Some requested controls describe ambiguous or incomplete values. Current displayed Credit includes a carry premium with discrepancies recorded in C2–C6. The user has now confirmed source line credit as the published contractual guarantee; pairing totals and adjusted Credit remain separate. Scheduled connection duration and source Rest are also separate values.

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
- The user selected `difference = base pairing credit − published line guarantee`. Offer Below / At / Above guarantee using the sign of that difference and show the signed duration. Require both values and complete pairing-credit coverage, preserve known zeros, and compare exact normalized durations before display rounding. A signed difference range can use the same value if introduced in subtask 7.
- This is comparison of published package values. Account-specific eligibility, proration, current agreement amendments, MBPG/composite minima, actual accrued PCH, and payable compensation remain in the separate compensation work. No universal 64/70-hour floor is substituted for a missing published line guarantee.
- Use source Duty Days as published, without clipping or recomputing it from occupied dates. The user confirmed that conflicting numeric Lines/Overview duty totals block publication; an absent source value must not erase the other source's known value.

**Metric definitions and source coverage:**

| Metric / control | Source and definition | Scope and completeness | Current coverage / implementation gate |
| --- | --- | --- | --- |
| Published line guarantee | Original Lines `Credit time` and/or Overview `Credit`, retained as `source_credit`; duration in minutes. | Published line value; Trips not required. Known zero is valid; both absent means unavailable. Both present must agree. | Imported separately from trip totals. Existing conflict checking needs missing-versus-present handling reviewed before relying on it for all partial-source combinations. Guarantee labelling/comparison belongs to subtask 7. |
| Base pairing credit range | Sum of complete linked Trips `Credit Time` totals, excluding the current added carry premium. | Full pairings across calendar boundaries. Require every linked pairing total; source line guarantee cannot fill a missing trip total. Repeated calendar references to the same pairing do not add its credit again. | `base_credit` alone is insufficient: it can contain source line credit when Trips totals are incomplete. Require `credit_basis = trip_totals` and verified linked-pairing coverage in subtask 3. No pairings does not prove zero flying credit for an unsupported line category. |
| Crew complement | Exact published line Captain/FO count pair from Lines/Overview; trip `Crew` remains separate evidence. | Active package and eligible seat; filter describes the published line, including FO-only complements. | Source counts are imported for Flying/Reserve rows. Reject contradictory published complements; show trip differences without relabelling the line. Subtask 3 adds the filter. |
| Published duty days | Lines `Duty days` and Overview Flying summary column D. | Use the published total as-is. Unknown is not zero; source totals may differ from calendar occupancy and summed trip duty counts. | Overview Reserve rows omit the summary columns. Lines may independently supply duty days. Current Lines parsing can overwrite an Overview duty value with null; subtask 4 must preserve known values and reject conflicting numeric totals. |
| Carry comparison | Published Overview Flying summary column F when present; otherwise calculated unique occupied UTC dates after month-end for linked trips continuing from the displayed month. | Preserve a published zero and its scope. Calculated fallback requires complete linked-trip date spans and counts each date once from the next calendar month's first date through release, inclusively. | Published-first precedence and the `Published carry count` / `Calculated occupied days after month-end` labels are implemented. Missing Trips/dates leaves fallback unknown. Reserve summary omission does not prove zero carry. Subtask 4 still adds comparison controls, source discrepancy indicators, and separate contractual Transition Days handling. |
| Days off / occupied blocks | Validated Overview calendar plus trip report-through-release occupancy, including full layover/rest dates. | Displayed UTC calendar month only; report and release calendar dates are included. Unknown dates remain distinct. | Existing date filters retain incomplete dates with the agreed warning. Published duty days and contractual SDFDO are separate metrics. Unsupported/missing calendar coverage is never inferred to be off. |
| Split line | At least two known occupied blocks separated by at least one complete, confirmed off-date block within the displayed month. | No qualifying gap at the beginning/end alone; additional work blocks still satisfy presence. Unknown dates cannot create an off gap or prove no split. | Calendar data can support the personal preference definition. This is not a contractual construction/compliance test. Subtask 5 adds the control. |
| Deadhead presence / avoided airports | Explicit deadhead leg markers; avoided airports inspect every linked leg origin/destination, including deadheads and ground-transfer endpoints. | Full linked trips. A known prohibited leg proves a conflict; proving absence requires complete relevant leg/route coverage. | Existing positive deadhead flags and leg routes supply evidence. Missing trips, unparsed legs, and incomplete routes cannot establish absence. Presence filtering is defined; count/duration sorts remain deferred. |
| First report | Earliest dated linked-trip report in UTC. Sort chronologically before taking the time of day; never substitute first departure for missing report. | Full linked trips, including a report before month-start. All potentially earlier reports must be known. Both window bounds are inclusive; lower greater than upper matches times at/after the lower bound OR at/before the upper bound. Equal bounds mean that exact time; an empty bound is open. | Trips supplies report clock and inferred report date. Verify dated reconstruction/rollover and malformed/missing values in subtask 5. A later known report cannot prove the first report when an earlier linked trip is missing. |
| End airport | Endpoint of the linked trip with the latest dated release/end timestamp, including an endpoint after month-end. | Full linked trips, independent of source list order. Missing potentially last trip, invalid chronology, or tied final timestamps with different airports means unknown. | Trip endpoints/release data exist; subtask 6 must validate chronology. Existing common/mixed endpoint flags describe a different property and must not supply this filter by shortcut. |
| Scheduled layover airport/duration | Connected same-airport arrival to next departure within a trip; duration from dated UTC timestamps. Source `Rest` is separately labelled. | Full linked trips; airport and duration must match on the same connection. Negative gaps and airport changes are inconsistent connections, not valid same-airport layovers. | Existing layover filters use scheduled gaps, with complete connection coverage required to prove avoidance. Source Rest does not establish the same interval or legal rest. Existing 10h/24h visual/quick-filter rules do not define a new long-stay control. |
| Country grouping | Existing airport reference country associated with package airport codes. | Group available airport choices by country, derived before other filters narrow results. Keep unresolved codes selectable without a guessed country. | `AirportData` has city/state/country, but no time zone or broader-region taxonomy. Country grouping is defined; broader geographic regions remain deferred. |

**Coverage and normalization requirements for dependent tasks:**

Package-level `coverage` currently records which document types were imported. It does not prove metric-level completeness. A present Trips file may still lack a linked pairing, credit total, report, or valid route. Evaluate availability per metric and distinguish a supported value, missing source data, incomplete data, conflicting sources, and an unsupported format/category. Keep unavailable controls explained and apply the confirmed unknown-value rules when only some lines have coverage.

Treat repeated calendar cells referencing one pairing as one pairing, leg set, credit total, and date span. If future layouts reuse the same pairing ID for distinct dated occurrences, require an occurrence-aware import format before claiming complete totals; do not guess whether to collapse or multiply them. Source disagreements and absence must remain visible to import review. Do not retrofit these definitions by silently mutating an existing published package or a private ranked list.

The private B747 October 2026 and B777 November 2026 imports, partial/missing Trips totals, FO-only/Reserve normalization, and UTC/year transitions are exercised by `BidPackageImportTest`. The 2026-11-03 focused run passed all 12 tests with 151 assertions and no skipped samples. This confirms compatibility of those fixtures and preserved original credit; it does not certify every metric on every line or additional source layouts. Existing code/tests were read for zero and incomplete-data behavior; the gaps above remain explicit work in subtasks 3–8.

**Deferred definitions:** Long stays, extra views, deadhead count/duration and rest aggregation sorts, broader regions, breakfast/fatigue scoring, and hazard-pay eligibility remain deferred as specified in subtasks 5 and 8–10. Available source duty/carry/report sorts may use the definitions above with explicit direction, unknown values last, and natural Line ID tie-breaking when subtask 8 is implemented. Existing Credit, Block, Days off, Best match, and Line ID sorts remain available.

**Acceptance outcome:** Definitions, source agreement, missing-data semantics, units/time scopes, Flying/Reserve limitations, and downstream normalization gaps are recorded. All questions for this definition task were answered. Remaining deferred features have explicit gates and do not block completion of this documentation task. No compensation arithmetic or application behavior changed.

Commit message: `docs: define advanced bid comparison semantics and coverage`

#### 2. [x] Completed: Group advanced controls and share filter state

**Goal:** Keep the compact browsing workspace clear as controls grow.

**Current implementation:** Completed 2026-11-03. The filter area has independent native Credit & crew, Work pattern, and Airports disclosures, active browsing-filter counts/summary, per-group clear actions, inline validation, and Reset filters. Existing Livewire properties remain the shared state; closing disclosures retains values, and package changes clear temporary browsing filters. Selected only and sorting remain outside the groups. Credit quick-entry buttons set 64h, 70h, or 80h; days-off buttons set 14, 15, or 16 days, with the active value highlighted and manual entry retained. Existing credit arithmetic and matching rules remain unchanged; later metrics stay in their own subtasks. Validation outcomes are recorded in TODO.md; manual browser/touch/keyboard review remains pending.

**Problem:** Adding every field to the existing quick-filter row would make browsing harder to scan.

Group secondary controls into collapsible Credit & crew, Work pattern, and Airports sections. Show an active-filter count/summary, per-group clear, and Reset filters. Closing a section must retain its values. Reuse the existing matching, Livewire state, validation, loading/empty/error feedback, and selection flow; keep parsing and airport lookups out of Blade. Keep sort/display choices outside filter groups. Wire only controls whose definitions are ready.

**Acceptance:** Keyboard access, focus and disclosure state, mobile layout, live result changes, reset behavior, and context switching work without changing selected ranks. Add behavior coverage for shared state and validation; review styling/accessibility manually.

Depends on subtask 1. Commit message: `feat: group advanced bid comparison controls`

#### 3. Add credit ranges and exact crew-complement filters

**Goal:** Compare credit bands and staffing arrangements using explicit labels.

**Current implementation:** Minimum Credit uses the existing adjusted value. Lines import Captain/FO counts and retain source/base credit separately.

**Problem:** There is no upper credit bound or exact-complement filter; the existing minimum uses a different credit basis from the newly agreed range.

The user selected **Base pairing credit** for the range. Use complete pairing Credit Time totals, label the range explicitly, and replace the existing quick minimum instead of keeping contradictory minimum controls. Source line credit is the published contractual guarantee and cannot substitute for base pairing credit; show unavailable when complete pairing totals cannot be established. Do not silently change the current Credit sort or goal. Derive complement options from exact published line count pairs for the eligible seat. The user confirmed that the filter describes the line complement; expose differing trip complements separately. Apply the source and completeness rules settled in subtask 1.

**Acceptance:** Inclusive boundaries, fractional durations/seconds, missing credit versus zero, inverted ranges, eligible-seat options, absent/mixed complements, combined filters, and retained selections are covered. Guarantee comparison remains subtask 7.

Depends on subtasks 1–2. Commit message: `feat: add bid credit ranges and crew filters`

#### 4. Add source duty-day and carry-in comparisons

**Goal:** Compare workload and month transitions without conflating occupancy, duty, and compensation.

**Current implementation:** Imported `duty_days` and `carry_in` coexist with a calculated count of unique occupied UTC dates after calendar month-end. The carry display uses the published count, including zero, when present; otherwise it shows a labelled calculated fallback when linked trips are complete.

**Problem:** The labelled carry value does not yet expose source discrepancies, duty-day comparisons, contractual Transition Days, or obligations entering from a previous award.

Add a labelled source-duty-day range where supported. The user confirmed published duty totals without clipping/recalculation, preservation of a known value when the other source omits it, and blocked publication for conflicting numeric Lines/Overview totals. The user selected **published source carry-in count, or work continuing into the next month when no count is published**. Preserve a published zero; fall back only when absent, not when it fails a filter. Invalid/conflicting published data is not absence and must not trigger a quiet fallback. For the fallback, use the existing calendar-occupancy meaning: unique occupied UTC dates after month-end for trips continuing from the selected month, with complete linked-trip coverage required. The existing display labels the basis `Published carry count` or `Calculated occupied days after month-end`; use those labels in the comparison explanation. Keep both raw values and flag discrepancies when available; contractual Transition Days remain distinct. Provide Any/Only/Exclude presence plus an optional count range if useful. Incoming obligations require evidence from the previous award and are unavailable from a prospective package alone. Source duty-day totals retain their source scope; the fallback alone counts dates beyond the displayed calendar month. No compensation-month boundary or premium inference applies to this calendar fallback.

**Acceptance:** Calendar month/year transitions, compensation-period exceptions where used, duplicate dates, missing pairings, complete zero carry-in, partial coverage, and source/calculated disagreement have focused coverage. No automatic schedule adjustment or premium entitlement is inferred.

Depends on subtasks 1–2. Commit message: `feat: add duty-day and carry-in comparisons`

#### 5. Add defined work-pattern controls

**Goal:** Filter split lines, deadheads, first report, and scheduled long stays using dependable evidence.

**Current implementation:** Occupied work blocks, report/release values, deadhead legs, source Rest, and scheduled same-airport arrival-to-next-departure gaps are available to varying degrees. Existing 10h/24h chips are presentation rules.

**Problem:** More than one occupied block does not by itself establish a contractual split line; report time differs from first departure; long-stay and rest values require explicit aggregation.

The user defined a **split line** as a work block, an off block, and another work block, and confirmed detection within the displayed month using known occupied blocks separated by at least one confirmed off day. Provide Any/Only/Exclude for that personal comparison definition. Unknown dates do not establish an off block or prove the absence of a split. Do not impose the CBA's contractual split-line construction requirements on this preference or label the result a compliance check.

For deadheads, begin with Any/Only/Exclude presence based on explicit leg markers; absence requires complete linked-leg coverage. Counts/duration can follow only with agreed aggregation. The user selected the **first chronological trip report in UTC across full linked trips**, including reports before month-start; do not use first departure or apply the window to every trip. Preserve the full dated timestamp to establish chronology before comparing time of day. Both time-window bounds are inclusive. A window crossing midnight matches times at/after the lower bound OR at/before the upper bound; equal bounds mean that exact time, and an omitted bound is open. Validate report date reconstruction and coverage under the rules in subtask 1. Missing report must not silently become departure time. The user deferred long stays until a duration is defined. Keep scheduled gaps, source Rest, legal rest, and fatigue distinct. Do not reuse visual chip thresholds as a new long-stay definition without agreement. Any future city-plus-duration condition must match both on the same stay.

**Acceptance:** Exact duration bounds, midnight/year crossings, first-trip chronology independent of source order, split occupancy around month boundaries, ground transfers, incomplete legs, overlaps, and airport changes produce defined results. Cases needing unresolved definitions stay deferred.

Depends on subtasks 1–2 and the relevant definitions. Commit message: `feat: add defined bid work-pattern filters`

#### 6. Add airport inclusion, avoidance, and geographic groups

**Goal:** Compare trip endpoints and stays with package-derived airport choices.

**Current implementation:** Required start city and a connected-layover airport selector exist. Trips retain individual endpoints and legs; mixed endpoints are already identified. Airport reference data can enrich codes with city/state/country.

**Problem:** End city could mean the last trip's endpoint or every trip's endpoint; avoided airports could cover stays only or every leg endpoint. Region names have no agreed mapping.

The user selected **the final chronological trip endpoint across full linked trips**, including an endpoint after month-end, **all leg origins/destinations including deadheads** for avoided airports, and **countries** for geographic grouping. Establish the final trip from dated chronology under subtask 1's definition, with unavailable status if missing trips could change that endpoint. Extend the existing layover selector rather than duplicate it; distinguish scheduled connections from source-rest locations. Avoidance requires complete route coverage to prove no prohibited endpoint is visited. Keep airport codes primary and identify airports sharing a city individually. Derive country groups from existing reference data for airports present in the package; preserve unresolved codes without assigning a guessed country. State/continent/custom-region groupings are outside the agreed first pass. Reuse airport resolution/cache services without a network lookup per rendered row.

**Acceptance:** Mixed endpoints, city/duration correlation, avoided deadhead endpoints, multiple selected airports, unresolved codes, reference-service unavailability, incomplete routes, and package changes have defined behavior.

Depends on subtasks 1–2 and airport-scope decisions. Commit message: `feat: extend bid airport comparison filters`

#### 7. Verify and add guarantee-relative comparisons

**Goal:** Compare complete base pairing credit with the published contractual line guarantee.

**Current implementation:** Source line credit is retained independently in `source_credit`. On 2026-11-03 the user confirmed that Lines PDF `Credit time` and Overview `Credit` are the published contractual guarantee, accepted either source subject to agreement when both contain values, and selected the signed difference from base pairing credit. Personal compensation eligibility remains outside this descriptive comparison.

**Problem:** Pairing totals and adjusted Credit can differ from published guarantee, and incomplete or legacy source data cannot establish every comparison.

Implement the agreed `base pairing credit − published line guarantee` difference, with Below / At / Above guarantee and a signed duration. Require complete pairing totals and a trustworthy published value, compare exact normalized durations, and preserve source/revision evidence. A signed range, if added, uses the same difference. Known zeros are valid; missing values are unavailable. Validate the source-field mapping and conflict handling described in subtask 1, including partial-source and legacy payloads; never substitute MBPG, composite minima, adjusted Credit, or a trip sum for a missing guarantee. Comparing these package values does not calculate actual accrued PCH, personal payable compensation, or carry-in premiums. Effective compensation rules and personal eligibility remain prerequisites for Estimated pay, not additional unanswered questions about this agreed arithmetic comparison.

**Acceptance:** Positive/zero/negative differences, exact equality and duration precision, conflicting sources, missing/partial values, preserved published zeros, legacy payloads, and line categories with no complete pairing totals are covered. A category with no dependable numerator or guarantee remains unavailable rather than receiving a guessed value.

Depends on subtask 1's completed definitions and reliable source inputs; independent filters can ship first. Commit message: `feat: add verified guarantee-relative bid comparisons`

#### 8. Add dependable optional sorts

**Goal:** Make the defined comparison metrics available for browsing order.

**Current implementation:** Credit, Block, Days off, Best match, and Line ID sorts are available, with natural Line ID tie-breaking and unknown numeric values last.

**Problem:** New sorts need a specific metric, direction, aggregation, and completeness policy.

Add only supported source duty days, chosen carry-in metric, first report, deadhead count/duration, and chosen rest/stay duration. Confirm ascending/descending choices and line-level aggregation, such as shortest scheduled stay versus longest stay; source Rest must have a distinct label. Unknown values remain last in either direction, with natural Line ID as the final tie-break. Partial observations cannot establish a reliable maximum/minimum or zero. Keep existing sorts available, Best match priorities intact, and manual bid order independent.

**Acceptance:** Equal values, zero/missing/partial values, both directions, stable ties, and combined filtering are covered. Do not expose a sort merely because some rows contain the field.

Depends on the corresponding completed metric subtasks. Commit message: `feat: add supported bid comparison sorts`

#### 9. Evaluate optional comparison columns or views

**Goal:** Add a display option only when it answers a comparison need beyond the compact timeline.

**Current implementation:** Compact monthly timelines and expandable details already show route, trip, credit, and stay information.

**Problem:** A second display could duplicate information or lose active browsing state.

The user confirmed **retain the compact timeline and defer extra views**. This subtask remains deferred until users identify fields needing side-by-side comparison. Prefer a small set of optional metrics in the existing layout; add a table/view only if that solves the stated need. Use the same filtered/sorted results, selected lines, add/remove actions, and warnings. Retain filters and selections across any future view changes, preserve the compact default and mobile usability, and define owner-scoped display persistence separately from filter persistence.

**Acceptance:** View changes preserve filter/sort values and manual rank; missing values remain explicit. Review narrow/desktop layouts, both themes, keyboard interactions, and header/action overlap. If no additional value is established, record that outcome without adding a view.

Depends on delivered metrics and a demonstrated display need. Commit message if implemented: `feat: add optional bid comparison display`

#### 10. Define later scoring separately

**Goal:** Establish evidence and definitions for later breakfast, fatigue, long-stay detection, and hazard-pay features.

**Current implementation:** No agreed breakfast window, fatigue model, or general long-stay detector exists. C8 records supplied hostile-area provisions; an airport's region or international status does not prove eligibility.

**Problem:** These features require more than package-derived categorical filters.

Define breakfast location/window, relevant departure/report event, local time zones and DST, plus whether all or some departures qualify. Define fatigue inputs, time basis, model, aggregation, missing-data behavior, and any ranking integration separately. A scheduled-duration filter from subtask 5 does not establish fatigue or a contractual long-stay category. Hazard-pay work needs applicable designation dates, qualifying assignments/layover days, governing amendments, and verified compensation rules. Keep undefined scoring and eligibility unavailable; do not infer them from region grouping or the existing chip colors.

**Acceptance:** A separately reviewed definition and source-coverage decision precedes each implementation task. This is deferred definition work, not a promise to add scoring in the first advanced-controls release.

Commit message: `docs: define prerequisites for later bid scoring`

#### Remaining implementation and deferred-feature gates

- Subtask 1's source semantics are settled: published duty/carry-in counts retain source scope; published line credit is contractual guarantee; conflicting published guarantee/complement/duty values block publication. Dependent implementation tasks must preserve known values and distinguish omitted data, actual zeros, and conflicts.
- Validate metric-level coverage and report/end timestamp reconstruction for each supported format. First/last-trip scope includes complete linked trips across calendar boundaries; inclusive and midnight-wrapping time-window behavior is defined above.
- Subtask 7 compares complete base pairing credit with the independently preserved published guarantee. Source mapping, legacy-data handling, and coverage remain implementation checks. Personal pay eligibility and current compensation rules remain separate Estimated pay prerequisites.
- Define deadhead count/duration and rest aggregation/direction before exposing those optional sorts. Presence filtering can ship independently.
- Long stays, extra views, breakfast/fatigue scoring, broader regions, and hazard-pay eligibility remain deferred with their definition requirements above.

**Integration checkpoint for implementation:** Require Sail availability before code changes. Confirm installed package versions and use Boost documentation for version-sensitive APIs. Run only focused tests for the delivered subtasks, Pint after PHP edits, and Larastan once at final integration. Cover changed matching decisions, boundary/missing-data failures, context/revision transitions, and private persistence if introduced. Record actual outcomes and a commit message for each completed task in TODO.md. This definition review changes documentation only. Its focused import-test result is recorded above; subsequent implementation requires its own focused behavior coverage.

### Additional sources and exports

Scanned PDFs/OCR, dedicated Training Requirements PDFs, Bid List spreadsheets, Vacations spreadsheets, and spreadsheet export remain deferred. Confirm retention, ownership, visibility, normalization, and representative layouts before enabling each source. Training already present in supported documents still counts as occupied time; a dedicated Training tab is a separate feature.

A company-compatible spreadsheet export needs an agreed schema and review of the supplied Bid List workbook. Exporting or copying does not submit a company bid. Offline editing remains outside the current scope.

### Estimated pay

Planning review completed: 2026-11-03. **Feasible as a scheduled-pay planning feature**, with the rule/source work below completed before releasing dollar totals. Use **Estimated pay** as the interface label. The product questions asked during this planning review have been answered. This section is an implementation plan; no pay calculator, personal pay profile, or pay sort has been implemented by this planning task.

**User-confirmed direction (2026-11-03):** The first release should estimate **scheduled gross contractual pay in USD before taxes/deductions, with supported credits and premiums, excluding per diem and expense reimbursements**. A guarantee-only preview is not the selected first-release scope. The existing `app/Data/pay_rates.json` rates remain applicable. Automatic pay steps use **a separate adjusted pay-longevity date**, rather than assuming hire date, and **the new rate starts on the anniversary date**, including an anniversary within a compensation period. Owners may supply **private planning inputs for missing facts, with assumptions shown**.

**Confirmed longevity, proration, and rounding:**

- Step 1 covers the first year from the adjusted pay-longevity date. Each anniversary increases the step by one, capped permanently at step 12. A date before the longevity start cannot resolve an eligible pay step and must not default to a zero rate.
- For a February 29 longevity date, the anniversary is March 1 in non-leap years and February 29 in leap years. Count actual anniversary boundaries rather than dividing elapsed days by 365 or allowing a date library to choose February 28 automatically.
- An anniversary within the compensation period creates old/new rate intervals. The user confirmed that each applicable MBPG/published line guarantee is weighted by the UTC days before and on/after the anniversary, divided by the **actual number of UTC days in that compensation period**. The new rate starts at **0000Z on the anniversary date**, so that entire date belongs to the new rate. Derive the denominator from the compensation-period boundaries, including the recorded January–March exceptions; do not substitute calendar-month length or a fixed 30-day divisor.
- Keep exact amounts through component calculations and round **the final USD total once**, to the nearest cent using **half-up** rounding. Do not round each payable component before summing. Component amounts formatted for display must not become calculation inputs; explain any difference between their formatted sum and the rounded final total in the breakdown.

For an applicable guarantee of `G` PCH, old/new rates `r_old`/`r_new` in USD per hour, `d_old`/`d_new` UTC days in those rate intervals, and `D` actual UTC days in the compensation period, the confirmed guarantee valuation is:

`guarantee USD = G × (d_old × r_old + d_new × r_new) / D`, where `d_old + d_new = D`.

Apply the same day weighting across all applicable rate intervals if more than one change occurs. Determine the applicable guarantee/eligibility first; this anniversary valuation rule does not define how leave or partial availability changes the guarantee itself. Keep each eligible guarantee candidate separate from earned/scheduled-credit valuation and any separately payable premiums.

#### Feasibility and existing evidence

The application already imports shared Lines/Trips/Overview packages, preserves original line credit as `source_credit`, supports private owner-scoped workspaces, and displays per-line credit breakdowns. The user previously confirmed that source line credit is the published contractual Bid Line Guarantee. These provide a starting point for rates, package comparison, and private context; they do not establish all payable credit or premium eligibility.

`app/Data/pay_rates.json` contains CA and FO arrays with 12 numeric values each. The earlier CBA review identified their source as Appendix 5-A's January 1, 2024 column, ordered by longevity years 1–12 and without separate fleet tables. The user's 2026-11-03 confirmation resolves whether these supplied rates remain applicable for the intended current packages and confirms USD as the estimate currency. Preserve that confirmation as provenance alongside the original table/effective date; do not imply a newly published 2026 rate table or unlimited future applicability. Add metadata for currency, validity, the confirmed step/anniversary policy, and final-only half-up rounding.

The reviewed Section 5.D structure uses the greatest of applicable MBPG, accrued bid-month PCH, and published Bid Line Guarantee, with relevant rate/overrides and separately addressed additional compensation. The 64-PCH MBPG and 70-PCH composite minimum have qualifications recorded in C5. For planning, an eligible scheduled-credit estimate may stand in for future accrued credit only with its scope and assumptions labelled. Never equate the current adjusted Credit field with total PCH, multiply its blanket carry premium into dollars, or treat every line as having a universal 64/70-hour floor. Findings C2–C6 and S1 remain dependencies of the scheduled-pay calculation.

| Input | Available now | Requirement before use in Estimated pay |
| --- | --- | --- |
| Hourly rates | CA/FO table, 12 entries each; user confirms continued applicability and USD currency. | Add table identity/source, effective/approved applicability, confirmed currency, pay-step semantics, and precision metadata. |
| Personal pay context | Bid seat preference exists; no pay-longevity/eligibility profile is implemented. | Private adjusted pay-longevity date, rate changes on anniversary, relevant availability/eligibility, and private planning inputs with visible assumptions. |
| Published line guarantee | Original Lines/Overview credit retained separately from pairing totals. | Trustworthy source value and revision, agreement of published sources, and personal applicability; no substitution from recalculated/legacy credit. |
| Pairing/scheduled credit | Trips credit/block totals and scheduled legs, with variable completeness. | Normalize payable categories and compensation-period allocation; complete pairing totals alone do not establish every bid-period PCH component. |
| Carry-in credit | Scheduled legs plus the current departure-only subtotal/calculation. | Duty-period boundaries, daily awarded valuation, receiving compensation period, and independently supported premium eligibility. Actual worked amounts remain unknown before work occurs. |
| Other credits/premiums | Some reserve/training indicators; limited assignment evidence. | Category-specific sources or user-permitted private planning inputs, each labelled with origin/assumptions. Eligibility, above-guarantee treatment, and interaction/deduplication must be defined. |
| Gross/net/expenses | No implemented deductions, tax, benefits, or per-diem model. | User selected USD gross contractual pay before taxes/deductions. Per diem and expense reimbursements are excluded; no take-home calculation is included. |

#### 1. Set the pay contract and supported first-release cases

**Goal:** Define exactly what scheduled gross pay includes and when the estimate is available.

**Current implementation:** Compensation review C1–C8 and scheduling review S1 record source rules and gaps; the new first-release direction includes supported credits and premiums.

**Problem:** A package line does not by itself establish personal eligibility, all payable activities, or actual work.

Use the confirmed USD gross-pay scope, adjusted pay-longevity date with rate changes at 0000Z on anniversaries, steps 1–12, March 1 leap-day treatment, guarantee weighting by actual UTC days in the compensation period, final-only half-up rounding, and private planning-input policy. Settle the remaining category/source coverage and rate attribution for cross-date/transitioned credit in this implementation subtask. Create a rule/coverage matrix for base flying credit, deadheads, reserve, vacation/training, carry-in allocation, and additional duty/off-day/other premiums. Each included component needs a rule reference, source or planning input, date/rate basis, eligibility predicate, and included-versus-above-guarantee treatment. Explicitly defer components that cannot yet be supported; do not silently set them to zero or claim a complete total when an applicable component is unresolved. The first delivery remains scheduled gross pay, even if unsupported personal scenarios must show unavailable.

**Acceptance:** User decisions and rule evidence are recorded; supported scenarios and their required inputs are finite and testable. No guessed guarantee floor, tax amount, hostile-area designation, or premium entitlement enters the calculation.

Commit message: `docs: define scheduled gross pay inputs and supported rules`

#### 2. Version the applicable rates and longevity rules

**Goal:** Resolve an explainable hourly rate for each supported earning interval.

**Current implementation:** The rate JSON is valid but has only seat arrays and decimal numeric values without rate metadata.

**Problem:** There is no table identity, currency/effective metadata, automatic pay-step rule, or handling of an anniversary/rate-table change inside a period.

Start from the existing approved USD values and add explicit metadata without changing them. Represent monetary rates as exact decimal/minor-unit values during calculation; avoid binary floating-point multiplication. Resolve by seat, applicable date, and pay step derived from the private **adjusted pay-longevity date**: start at step 1, add one for each anniversary reached, and cap at 12. Apply the confirmed March 1 anniversary in non-leap years for a February 29 start. The new rate becomes effective at 0000Z on its anniversary date, so construct multiple dated rate intervals when an anniversary falls inside the compensation period. Do not select one rate only at period-start or postpone the change to the next period. Verify fleet applicability and reject unavailable pre-longevity-date rates explicitly. Cross-date/transitioned-credit rate attribution remains a rule-matrix requirement in subtasks 1 and 4. Preserve the original rate source and the user's applicability confirmation. Published rate versions must remain identifiable for explanations/recalculation; do not silently overwrite the meaning of an old estimate. Rate maintenance is admin-only. Use the existing JSON catalog initially if it meets the agreed update workflow; add a Filament maintenance interface only when that workflow requires it, rather than making an unrelated admin system a prerequisite.

**Acceptance:** First/last steps, invalid/future longevity dates, exact anniversary boundaries, leap-day anniversaries, effective-date gaps/overlaps, seat changes, and any agreed mid-period rate transition have meaningful focused coverage. Missing rate coverage means unavailable.

Depends on subtask 1. Commit message: `feat: version crew pay rates and resolve longevity steps`

#### 3. Add private pay context and planning inputs

**Goal:** Let the owner provide the personal facts required for the supported scenarios.

**Current implementation:** Workspaces already use owner/context isolation and encrypted private preferences; profile settings include bid seat. No pay profile exists.

**Problem:** Shared package data cannot supply longevity, availability/proration, or individual premium elections/eligibility.

Store the adjusted pay-longevity date privately using the application's encrypted-data conventions; do not substitute hire date. Separate enduring personal context from period/line-specific planning adjustments. Scope every read/write by owner and appropriate period/seat; treat another seat as an explicit comparison scenario rather than proof of qualification. The user permits private planning inputs for missing facts: validate dates, units, eligibility choices, and supported manual amounts, show their assumptions, and retain their origin separately from imported evidence. A manual input supplies a scenario fact, not a new compensation formula or shared source record. If it contradicts imported evidence, retain both origins and require an explicit scenario choice rather than silently replacing the package value. Support correction/removal and keep these data out of package administration, shared payloads, copied numbered bids, and unrelated exports. Reuse existing action/autosave/concurrent-edit patterns without silently expanding the workspace preference allow-list or resetting rankings.

**Acceptance:** Owner isolation, authorization, invalid/incomplete inputs, changed seat/period, save recovery, and concurrent edits are covered. No other package viewer gains access to a pay profile or individualized total.

Depends on subtask 1 and rate-input definitions. Commit message: `feat: add private crew pay context`

#### 4. Normalize payable credit and compensation periods

**Goal:** Supply the calculator with credit components that have defined period and rule bases.

**Current implementation:** Imports retain source guarantee, complete pairing totals where available, and scheduled leg timestamps. The existing carry formula has known discrepancies.

**Problem:** Trip occupancy, elapsed time, pairing credit, contractual PCH, and personal compensation are different quantities; calendar boundaries do not always match compensation Months.

Keep the published guarantee independent. Build normalized planning components with assignment identity, covered Z day, receiving compensation period, credit duration, category, source, and completeness. Deduplicate repeated pairing references. Preserve full-segment departure-Z-day attribution and the user-confirmed daily greater-of-awarded-or-actual rule for covered carry-in duties, while recognizing that future actual work is unavailable. Before actual work exists, explicitly use supported awarded/scheduled planning values; do not call missing actual work zero. Apply the recorded January–March compensation-period exceptions and duty-level transition allocation separately from calendar occupancy.

Support each included category under its verified basis. Air/surface deadhead mode and scheduled/actual credit rules, reserve/vacation/training credit, and premiums need their own inputs. Do not reuse the blanket 50% carry increment or automatically value ground transfers at zero. Correct compensation inputs in a focused path while preserving the current comparison display until a separately reviewed change replaces it. Published packages remain fixed; source corrections/reimports follow revision review.

**Acceptance:** Multi-leg duties, departure across midnight, month/year and January–March boundaries, duplicate references, missing duties/awarded credit, actual-versus-scheduled distinction, and carry credit already included in base amounts are covered. Unknown coverage stays unknown.

Depends on the supported rule matrix in subtask 1. Commit message: `feat: normalize scheduled pay credits and period allocation`

#### 5. Calculate scheduled gross pay and supported premiums

**Goal:** Produce an exact, explainable amount for a supported personal scenario.

**Current implementation:** There is no pay calculator. Existing adjusted Credit is a comparison metric with known limitations.

**Problem:** Applying a rate to that metric would duplicate or misallocate credits and can award unsupported premiums.

Use a focused calculation service with explicit inputs and a structured result. Resolve the applicable base-pay candidates under the reviewed greater-of structure, then add supported compensation that is payable separately. Apply overrides and premium multipliers only to their eligible components. Each component must state whether it contributes to the greater-of base or is added above it; enforce one contribution per assignment/rule rather than adding the same carry/deadhead credit again. Value each applicable MBPG/published-guarantee candidate with the confirmed UTC-day weighting across rate intervals and actual compensation-period denominator. An anniversary takes effect at 0000Z, including that day at the new rate. Scheduled-credit rate attribution, including transitioned credit, follows the category rules established in subtasks 1 and 4; do not apply `max(hours) × one rate` to a mixed-rate period by shortcut.

Keep duration precision and exact monetary arithmetic through the final sum, then round the final USD total once to the nearest cent with half-up rounding. Include fractional proration and premium amounts without intermediate monetary rounding. Return currency, exact/display component subtotals, final rounded amount, selected base candidate, hourly rate/pay step, compensation dates, source and rule versions, assumptions, exclusions, and missing inputs. Explain any display-rounding reconciliation without changing the calculation. Distinguish ready, incomplete, and unsupported scenarios. A known-components subtotal can be shown only with that limited label; unresolved applicable compensation must not be presented as a complete gross total. A genuine zero requires supporting data, not a default.

**Acceptance:** Each greater-of candidate winning/tie case, missing versus zero, exact fractional durations, premium stacking/exclusions, above-guarantee treatment, duplicated carry credit, proration/eligibility where supported, and final-only half-up rounding have focused tests based on agreed examples. Cover anniversaries at period-start/end and inside the period, old/new rate days summing to the actual denominator, leap-day anniversaries, capped rates, and January–March compensation-period boundaries. No payroll/tax or actual-work claim is inferred from a scheduled estimate.

Depends on subtasks 1–4. Commit message: `feat: calculate scheduled gross crew pay`

#### 6. Present the estimate in the existing comparison workspace

**Goal:** Add useful pay comparison without crowding the compact timeline or exposing personal context.

**Current implementation:** Lines have expandable details, shared filters, independent sorts, and a manually ordered bid list.

**Problem:** A single dollar value can hide unresolved coverage or imply an actual paycheck; a new view could duplicate controls or reorder bids.

The user selected an **optional compact Estimated pay amount, expanded breakdown, and Highest estimated pay sort for the first release**. Keep the compact timeline default and let the owner enable the pay metric. Put the detailed breakdown in existing line details. Provide a private Edit pay context entry showing the resolved pay step/rate intervals, compensation period, and key assumptions. Show source/rule details on demand, and distinguish missing inputs from supported zero. Refresh estimates when their inputs change with existing loading/save/error feedback; a failed recalculation must not leave a stale amount looking current. Retain filters, selections, and manual ranking. Exact placement can follow the existing compact metric/disclosure conventions during implementation.

**Acceptance:** Valid/missing/partial/unsupported states, rate/profile updates, both themes, narrow screens, keyboard use, focus restoration, and owner-specific rendering are checked. Numbered bid copy retains its agreed line-only schema.

Depends on subtask 5. Commit message: `feat: display private scheduled pay estimates`

#### 7. Add pay sorting only for comparable estimates

**Goal:** Make scheduled-pay comparison available without changing manual bid rank or Best match semantics.

**Current implementation:** Credit, Block, Days off, Best match, and Line ID sorts already exist.

**Problem:** Ordering an incomplete subtotal beside a complete estimate would imply comparable coverage.

Include **Highest estimated pay** in the first release, as confirmed by the user. Sort ready estimates descending by the final rounded USD total in minor units, with the same personal scenario, compensation period, and supported scope. Equal displayed totals use natural Line ID as the stable final tie-break. Place missing/incomplete/unsupported values after ready estimates and visibly identify their status; never treat them as zero or compare different currencies without a separately approved conversion feature. Keep manual rank and the existing strict-priority goals intact; adding a pay ranking goal would require a separate decision.

**Acceptance:** Exact ties, unknown values, changed personal context, inconsistent coverage, and selection/rank preservation are covered. All existing sorts remain available.

Depends on subtask 5; first-release inclusion is confirmed. Commit message: `feat: add comparable estimated pay sorting`

#### 8. Integrate and release the supported scheduled-pay scope

**Goal:** Deliver the selected scheduled gross-pay release with evidence for each supported component.

**Current implementation:** Package import/comparison tests and private workspace patterns provide existing regression coverage to extend.

**Problem:** Correct arithmetic alone cannot establish source completeness, owner privacy, or a clear explanation of what is estimated.

Review agreed representative Flying/Reserve and any supported composite/training cases, source disagreements, and deliberately incomplete packages. Verify that every visible amount traces to its inputs, versions, and scope, and that unavailable cases explain what is needed. Add calculator unit coverage for pure arithmetic and focused feature coverage for profile ownership, rate maintenance authorization, Livewire state, package revisions, and rendering. Use synthetic fixtures for private scenarios; do not add real personal dates to the repository. Review browser behavior manually. Require Sail before code changes, use installed APIs, run only focused tests, Pint after PHP edits, and Larastan once at final integration. Update TODO.md with actual outcomes and each completed task's commit message.

**Acceptance:** The agreed gross-pay cases calculate with supported credit/premium rules; unresolved cases remain explicit. The feature does not silently fall back to the unselected guarantee-only release. No new dependencies, automatic payments, external payroll integration, or tax engine are included in this plan.

Depends on the implemented release scope above. Commit message: `feat: release supported scheduled pay comparisons`

#### Confirmed plan and implementation gates

Confirmed: scheduled gross contractual pay in USD before taxes/deductions, excluding per diem and expense reimbursements; current applicability of the supplied rate values; a separate adjusted pay-longevity date; step 1 for the first year, annual increases capped at 12, March 1 non-leap-year anniversaries for a February 29 start; rate changes at 0000Z on the anniversary date; MBPG/line guarantees weighted by old/new rate UTC days divided by actual days in the compensation period; exact calculations with one final nearest-cent half-up rounding; private planning inputs with assumptions shown; and an optional compact amount, expanded breakdown, and Highest estimated pay sort in the first release.

All product questions asked during this planning review are answered. Subtasks 1 and 4 must still establish source-backed category coverage, eligibility/proration cases, and rate attribution for work/credit crossing an anniversary or allocated to a different compensation period. Those are explicit implementation prerequisites for affected scenarios; unresolved rules remain unavailable rather than guessed. They do not reopen the confirmed longevity, guarantee-weighting, currency, rounding, input, or presentation decisions. The eight-subtask plan and planning documentation are complete; implementation remains pending.

### Personal-data visibility

Resolve access to assigned crew, vacations, and training records within shared packages before displaying or importing them more broadly. General K4 Bids access must not expose other users' private preferences, notes, credentials, or bid lists.

## Open decisions

- Which additional source periods/layout revisions need support beyond the validated native-text samples?
- Advanced comparison definitions are settled in subtask 1, including published line guarantee, signed base-credit comparison, source conflicts, crew authority, UTC/month scope, and missing-data rules. Remaining gates are implementation coverage/normalization checks and explicitly deferred sort, long-stay/scoring/display definitions.
- What source evidence and effective rules support legal-rest/fatigue indicators and compensation calculations?
- What visibility, retention, and revision behavior apply to personal records, tags, and notes?
- What spreadsheet schema and external evidence can establish exported, submitted, awarded, or projected states?

Portal-specific decisions and delivery phases are recorded under Auto bidding feature. Resolve dependent definitions before implementing the corresponding enhancement.

## Verification for future changes

Use existing Laravel/Livewire, action/service, Blade, and owner-scoped persistence conventions. Do not introduce abstractions or dependencies solely because they appeared in an older proposal.

Run focused regression coverage for the changed behavior: source normalization and failures, UTC/month/year boundaries, exact credit arithmetic, filtering/ranking, persistence/concurrent edits, revision migration, access isolation, and missing versus zero values. Keep live portal writes out of automated tests. Run Pint after PHP edits and Larastan once at integration, and record actual outcomes in TODO.md. Manual browser review remains necessary for layout and interaction claims.

## Completed

---------------------
## [x] Completed: Set preferences pop out
Problem: if a user is not at the top of the page, the Set preferences opens when a user is out of view. The user needs to scroll up to set preferences. Perfer a pop out or full page preferences view

Element:
```html
<button type="button" @click="$wire.showPreferences = ! $wire.showPreferences" :aria-expanded="$wire.showPreferences" aria-controls="bid-preferences" class="inline-flex min-h-11 min-w-0 max-w-full items-center gap-1.5 rounded-full bg-slate-200/70 px-3 text-slate-700 focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-800 dark:text-slate-200 lg:max-w-80" aria-expanded="false">
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"></path>
</svg><span class="sr-only">Edit preferences: </span><span class="truncate"><!--[if BLOCK]><![endif]-->Maximize days off → Maximize credit<!--[if ENDBLOCK]><![endif]--> <!--[if BLOCK]><![endif]--> · 8 dates off (UTC)<!--[if ENDBLOCK]><![endif]--> <!--[if BLOCK]><![endif]--><!--[if ENDBLOCK]><![endif]--></span>
                    </button>
```
