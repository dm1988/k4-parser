# K4 Bids

K4 Bids helps pilots compare monthly bid packages and prepare a private, ordered bid list. This document records the current product contract and remaining work. Completed implementation tasks, validation history, and commit messages are recorded in [TODO.md](TODO.md).

Status (2026-10-28): Package import/publication, browsing, preferences, manual ranking, review/copy, compact cards, interactive trip timelines, credit calculations, icons, and trip endpoint/deadhead flags are implemented. Auto bidding has read-only discovery and an offline adapter; reviewed submission and the enhancements below remain pending. Manual browser, touch, and keyboard review is still outstanding.
## Set preferences pop out
Problem: if a user is not at the top of the page, the Set preferences opens when a user is out of view. The user needs to scroll up to set preferences. Perfer a pop out or full page preferences view

Element:
<button type="button" @click="$wire.showPreferences = ! $wire.showPreferences" :aria-expanded="$wire.showPreferences" aria-controls="bid-preferences" class="inline-flex min-h-11 min-w-0 max-w-full items-center gap-1.5 rounded-full bg-slate-200/70 px-3 text-slate-700 focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-800 dark:text-slate-200 lg:max-w-80" aria-expanded="false">
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"></path>
</svg><span class="sr-only">Edit preferences: </span><span class="truncate"><!--[if BLOCK]><![endif]-->Maximize days off → Maximize credit<!--[if ENDBLOCK]><![endif]--> <!--[if BLOCK]><![endif]--> · 8 dates off (UTC)<!--[if ENDBLOCK]><![endif]--> <!--[if BLOCK]><![endif]--><!--[if ENDBLOCK]><![endif]--></span>
                    </button>
## CBA accuracy review — 2026-10-29

Reviewed against the supplied [2021 CKS CBA](storage/app/private/CBA/2021_CKS%20CBA_Final.pdf), primarily **Part I, Section 5, Compensation**, and **Part I, Section 17, Scheduling**, with related definitions and cross-references. References below give printed pages first and one-based PDF pages in parentheses; printed page 25 is PDF page 37. This is a document comparison, not validation of application code, package totals, live portal behavior, or actual payroll.

The findings below qualify the product contract and historical examples that follow. **The implemented carry-credit formula is not established as CBA-correct.** C1's terminology correction is implemented as recorded below; the remaining calculation findings require separate implementation tasks. Product choices such as private ranking, filters, colors, and portal security are not themselves contractual requirements.

The supplied agreement's cover states March 1, 2021–March 1, 2025, but Section 28 provides for renewal subject to its notice provisions; do not describe it as automatically expired. Appendix 1, LOA #1 makes the ratification date the effective date, with Sections 5.B and 5.C effective January 1, 2021 subject to ratification. This review does not establish whether later agreements or interpretations change the rules for the October/November 2026 samples. Sources: Section 28, p. 207 (PDF 219); LOA #1, pp. 269–270 (PDF 281–282).

### Compensation discrepancies and missing qualifications

**C1 — Resolved terminology: distinguish carry credit subtotal from PCH.** The application previously labeled next-calendar-month leg credit as PCH. Section 5.C.1 uses PCH to mean **Pay Credit Hours**; Section 5 applies it to flying, reserve, deadhead, vacation, training, and other qualifying activities. The UI now calls the product metric **Carry credit subtotal** and explains that it is not total contractual PCH. New parser results use `carry_credit_seconds`; presentation uses `carry_credit_label`. Previously saved `pch_seconds` values are read as the legacy subtotal without rewriting package payloads. An explicitly present new field, including null or zero, takes precedence. Existing credit amounts, premium arithmetic, fingerprints, and saved rankings are preserved; total contractual PCH is not newly calculated. C2–C6 and S1 remain separate calculation/coverage findings. Sources: Sections 5.C–5.N, pp. 25–29 (PDF 37–41).

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

**CBA definitions and user-confirmed calculation rules:**

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

Receive the monthly package without manually downloading and selecting its attachments. The user receives a work Outlook email with approximately ten PDF/Excel attachments and suspects the same package is available on the crew-bids website. **Automatically publish complete, validated first packages; hold revisions for admin review.** This is a plan only: no forwarding rule, inbound endpoint, scheduled download, credential storage, or automatic publication has been enabled.

Recommend **email forwarding first**, with a shared ingestion flow that can later accept portal downloads. Email is the confirmed delivery source. Website retrieval remains conditional on authenticated discovery of actual package files and a sustainable authentication method. Importing a package remains separate from submitting a bid, migrating saved lists, or correcting the credit calculations identified in the CBA review.

### Evidence and existing integration points

- `CrewBids::previewImport()` currently allows one to three PDF/XLSX files, at most 12 MiB each. `ParseBidDocuments` accepts at most one Overview XLSX, one Lines PDF, and one Trips PDF for a matching month/fleet/revision. It allows partial source coverage for manual imports. Passing all ten attachments directly to this parser would fail.
- Local samples include Lines, Trips, Overview, Training Requirements, Bid List, and Vacations files across B747/B777 and multiple months. These establish likely document roles, not the complete manifest of any one email. Dedicated training, vacation, and bid-list imports remain deferred.
- Package payloads are encrypted; the parser fingerprint incorporates sorted source hashes and parser version. Manual import deletes uploaded source files after parsing. Automation needs a short-lived, private staging lifecycle and delivery provenance in addition to the existing package fingerprint.
- `PublishBidPackage` authorizes `manage-crew-bids`, checks draft ownership through `imported_by`, and publishes in a transaction. Queued automation has no interactive authenticated user; a dedicated source authorization path is required. It must not simply call this action without an actor or bypass authorization.
- The saved Bid Sheet navigation contains `/Pages/Documents.aspx`. Its contents, download links, available fleets, release identifiers, and whether it contains all email attachments remain unverified. The current `DiscoverBidPortal` operation reaches Bid Sheet only and does not download packages. The public [portal login](https://crewbids.kalittaair.com/) establishes neither package availability nor a download API.
- The connected Outlook account is personal, not the work mailbox. The actual package message, sender rule, attachment count/size, forwarding behavior, and correction-email format have not been verified. No personal mailbox content is needed for implementation.
- Laravel 13.34.0 was confirmed through Sail. Existing scheduled work is registered in `routes/console.php`; the project already uses single-server and overlap protection. Mailgun transport/configuration exists, but this does not prove production inbound-mail support is configured.

### Source options

| Source | Plan | Prerequisites and tradeoffs |
| --- | --- | --- |
| Outlook forwarding → inbound email provider → Laravel | Recommended first delivery adapter. Match the verified package sender and subject pattern, including corrected/revised releases, and retain the original in Outlook. Forward to a dedicated application-controlled recipient. | Verify external forwarding is allowed and that all attachments survive delivery. Microsoft 365 organization policies can block automatic external forwarding even when a mailbox rule exists. No work-mailbox password needs to be stored in Laravel. |
| Scheduled crew-bids download | Discover Documents through an interactive authenticated session; then add a narrowly scoped package-download adapter feeding the same pipeline. | Actual download availability and revision metadata are unknown. Unattended authenticated checks need an approved persistent credential/token mechanism; this conflicts with the current portal feature's non-storage policy unless a separate import integration policy is explicitly adopted. |
| Microsoft Graph mailbox reader | Fallback if forwarding is blocked and the organization permits a mailbox integration. Read a dedicated folder using approved OAuth access and a resumable checkpoint. | Requires work-account app registration/consent and token lifecycle management. The personal Outlook connector is not a production Laravel integration. Do not start with this additional integration unless forwarding and portal access are unsuitable. |

Microsoft documents organization-level [external-forwarding controls](https://learn.microsoft.com/en-us/defender-office-365/outbound-spam-policies-external-email-forwarding). For inbound delivery, first check the existing Mailgun account/domain: [Routes can POST received messages and attachments](https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/receive-http), with [signature verification](https://documentation.mailgun.com/docs/mailgun/user-manual/webhooks/securing-webhooks). Select and test one supported payload format; signature locations vary by format. If Mailgun receiving is unavailable, [SES receiving](https://docs.aws.amazon.com/ses/latest/dg/receiving-email.html) with private object storage is an alternative, subject to domain/region setup. Choose the provider after checking existing production facilities, message-size limits, and receiving costs; do not add dependencies or infrastructure merely because both alternatives are listed.

### Shared intake and package validation

The flow is: **receive → stage privately → classify attachments → group package sources → validate → create draft → publish first release or hold revision**. Record each source delivery separately from the package it produces so email and portal delivery of identical files converge on the same package.

1. **Receive durably.** Verify the provider signature, freshness/replay rules, configured recipient, and registered forwarding-source policy before accepting an email. Provider authentication proves who delivered the webhook, not that a visible From header is genuine; forwarding provenance needs a sample-based policy using provider authentication results and expected sender/forwarder information. Persist the receipt and bounded attachment data before acknowledging success; retry delivery safely after temporary failures. Queue parsing after the receipt transaction commits. Jobs carry receipt IDs, not email bodies, credentials, or attachment bytes.
2. **Inventory every attachment.** Record private filename, byte size, content hash, detected format, classification, and reason for exclusion/hold. Classify using PDF content and workbook structure as well as names. Ignore proven inline signature images, but flag unexpected document attachments. Do not equate “ten attachments” with completeness. Detect truncated, encrypted, malformed, oversized, or unsupported files. Bound ZIP expansion/XML processing for XLSX, avoid external references and macros, and never execute document content. `.xls`, `.xlsm`, and scanned PDFs need explicit support decisions; they are not silently treated as supported XLSX/native PDFs.
3. **Build coherent groups.** Separate sources by period, fleet, revision, and seat where source documents actually distinguish seats; the present package model is period/fleet-based and carries crew complements. Associate each supported group with its Lines, Trips, and Overview. Classify Training Requirements, Bid List, and Vacations without adding their personal contents to shared package payloads. Hold unknown roles for review. Do not combine a new revision's Lines with an earlier revision's Trips or Overview, even when filenames match. A date in an email subject is a hint, not package identity.
4. **Define a complete automated import.** Proposed initial profile: one native Lines PDF, one native Trips PDF, and one supported Overview XLSX per period/fleet group. Confirm that profile from one full delivery before enabling it. Require matching identity, supported schema, successful parse, expected line/trip references, consistent coverage, and existing source-consistency checks. An Overview may lack a revision marker; it must have coherent delivery provenance and pass cross-file validation rather than being assigned an invented revision. A parser success with partial coverage does not qualify for automatic publication. Extra known unsupported document roles are explicitly excluded; an unexplained missing required role holds that group. Independent fleet groups may publish independently if each is complete; never publish a partially validated group.
5. **Deduplicate and order revisions.** Use a durable unique receipt identity for delivery retries, attachment hashes, and the existing parser-version/package fingerprint for normalized drafts. Add database uniqueness and per-context locking for simultaneous email/portal arrivals; queue uniqueness alone is insufficient. Identical content is a no-op with additional provenance. Changed content for a previously published period/fleet is a revision even if its printed revision label is unchanged. Different labels or conflicting contents hold for review; receipt time and lexicographic labels do not establish a newer revision. Never infer deletions or replace good files from an incomplete delivery. A partial correction can reference an earlier manifest only through a reviewed, explicit merge.
6. **Apply publication policy atomically.** With the context locked, recheck completion, registered-source authorization, duplicate status, and whether a published package already exists. A first complete validated package may publish automatically. Any changed package in that context becomes a reviewable draft showing source differences, affected lines, and validation results. Reuse publication behavior through an action with explicit automated-source authority and an accountable configured admin owner; recheck authorization if that owner/source is disabled. Keep interactive ownership and gate checks intact. Publishing a revision must not migrate users' selections or change their manual order; preserve the existing migration review and confirmation.

Proposed durable records are an import receipt with state/source/attempt details, a private attachment manifest, and links from each resulting package to its receipts. Keep normalized `BidPackage` payloads in the existing model. Reuse `ParseBidDocuments`, `ReadBidOverview`, and the publication behavior; extract reusable draft creation from Livewire rather than invoking UI methods from a worker. Database schema and migration details belong to implementation after inspecting the schema.

### Email delivery implementation

Create a dedicated receiving subdomain/recipient without replacing the organization's work-mail MX records. Confirm provider support and the largest complete sample message, including encoding overhead, against provider, web-server, PHP, and storage limits. Start with a controlled manual forward into the intake flow, inspect its manifest, then configure the narrow Outlook rule once that works. Include revised-package subjects and provide manual forwarding as recovery.

Prefer a signed multipart attachment webhook if the existing inbound provider supports the actual message sizes. If messages exceed safe request limits, use authenticated provider storage retrieval or private object storage with a small event notification. Download only from verified provider destinations; do not follow arbitrary URLs in email bodies. Treat forwarded-as-attachment `.eml`/`.msg` and cloud-file links as explicit cases: support the observed format or hold with a clear reason rather than pretending there were no attachments.

Proposed staging retention is seven days for raw messages and attachments, including failed/revision-held deliveries, with an expiry notice and a way to supply the source again. Keep only necessary normalized package data and minimal provenance afterward; configure provider-side retention too. Restricted raw training/vacation/bid-list documents remain admin-only during staging. Logs and status notifications contain IDs, counts, and failure categories, not full messages, personal records, credentials, or signed download URLs. Confirm this retention choice before implementation.

### Portal discovery and scheduled checking

First inspect `/Pages/Documents.aspx` using a user-run transient login, without saving the password or submitting any bid. Record redacted navigation, package period/fleet labels, actual download destinations, whether files are direct downloads or Web Forms postbacks, publication/revision metadata, and the completeness of a known package. Compare downloaded bytes and document identities with a known email release. The current destination allowlist would need a reviewed extension for verified document endpoints; do not broadly allow all portal links.

If downloads require authentication, unattended checking cannot use the current interactively entered credentials after they are discarded. Choose a supported persistent authentication mechanism or keep retrieval interactive. Any later stored secret must have an explicit import-specific policy, restricted access, rotation/revocation, and no inclusion in job payloads or logs. Do not weaken the bid-submission feature's transient-credential contract implicitly.

After discovery, propose configurable UTC checks hourly on days 8–16 and every six hours otherwise, including after the first release so corrections are detected. These are polling defaults, not contractual publication dates or verified portal rate limits. Use conditional requests only when supported; verify downloaded hashes regardless. A portal with no coherent manifest/revision markers should create held drafts until consistency can be established, rather than publishing a mixture downloaded during an update.

The Laravel schedule dispatches bounded download work. Apply `onOneServer()` and `withoutOverlapping()` with the deployment's shared lock store, plus worker-level per-source/context locks because scheduler locks do not cover a queued job's full lifetime. Use timeouts, bounded retries/backoff for temporary GET failures, rate-limit handling, and a pause on rejected authentication or changed page structure. A login redirect or HTML error response is a failed download, never a valid PDF. Record last successful check separately from last new package; retain a manual retry and upload path. Laravel documents [scheduler overlap/single-server controls](https://laravel.com/docs/13.x/scheduling) and [queue uniqueness and retries](https://laravel.com/docs/13.x/queues).

### Delivery sequence and acceptance

| Phase | Result and acceptance |
| --- | --- |
| 1. Confirm source manifest | Inspect one complete work-email delivery and one correction if available. Establish sender/subject, attachment roles, sizes, fleets, revision evidence, and forwarding feasibility. Discover portal Documents separately without treating it as a confirmed source. |
| 2. Shared intake and classification | Ingest synthetic and representative local files into private staging, build complete groups, deduplicate receipts, and produce actionable holds. Existing manual imports continue to work. No partial group replaces a published package. |
| 3. Email adapter and publication | Verify a controlled forward end to end; publish a first complete validated package automatically, leave identical deliveries unchanged, and hold changed releases for admin review. Enable the narrow Outlook rule after this delivery path is verified. |
| 4. Operational rollout | Display import history, source, period/fleet, counts, missing roles, last success, revision differences, and retry/review actions in Manage package. Add in-app failure/late-arrival status; email alerts require a separately configured recipient. Verify worker/scheduler operation and staging expiry. |
| 5. Optional portal adapter | Proceed only with proven download coverage and the chosen authentication policy. Feed the same receipt/validation/publication flow; duplicate email and portal deliveries must produce one package. |

For late-arrival monitoring, use the supplied CBA's 2359Z on the 12th publication deadline as an initial configurable expectation, tied to the target bid period. Show a missing-package warning after it passes and an actionable pre-bid-close warning before the expected 16th close. Confirm current company timing and notification lead time. Neither a failed poll nor a missing email proves that the company has not published a package.

Focused acceptance coverage should include the ten-attachment classification case; multiple fleets; partial/mismatched revisions; misleading filenames; unsupported Excel/PDF formats; missing required roles; corrupted and oversized files; spoofed or replayed intake; provider retries and concurrent arrivals; identical content across sources; older/out-of-order corrections; first-release automatic publication versus revision hold; disabled source/admin authority; privacy of excluded personal documents; worker failure/recovery; expiry; and preservation of published packages and saved rankings. Portal-specific tests use fake HTTP responses for login expiry, throttling, changed HTML, and interrupted downloads. Follow the testing-best-practices skill, use synthetic fixtures for personal data, and never hit live bid-write endpoints. At implementation integration run focused tests, Pint after PHP edits, and Larastan once.

Before implementation, resolve the real attachment manifest, permitted forwarding and receiving domain/provider, staging retention, and accountable import admin. Portal authentication/download discovery is a prerequisite only for the portal adapter. The user has already selected automatic publication for complete first releases and review for revisions. Existing CBA credit discrepancies remain documented; import validation does not certify compensation calculations as correct.

## Current focus: Auto bidding feature

Implementation status (2026-10-28): Read-only command discovery and offline Choice/Line preparation are implemented with regression coverage. User-run authenticated discovery now reaches the closed-window Bid Sheet; full context/protocol discovery and reviewed submission remain pending. The user confirmed that this feature means submitting their reviewed bid list to the airline's bidding system, using employee number/password without MFA, CAPTCHA, or SSO. It does not mean automatically generating preferences or submitting on a schedule. The existing review/copy workflow remains available.

The user confirmed that bidding is currently closed, explaining the supplied readonly inputs, and that portal numbers are unique: F1 → 1, Q99 → 99, R70 (also written R 70) → 70. The adapter accepts F/Q/R plus an optional space before a positive number, rejects numeric collisions across prefixes, and uses the displayed Choice values to order paired fields rather than DOM order. Runtime slot count, maximum line, and training rules constrain an offline candidate; it includes blank trailing line fields without changing unrelated settings. This does not prove account/seat eligibility, the bid window, or submission acceptance.

Run `vendor/bin/sail artisan crew-bids:discover-portal` interactively for hidden employee-number/password prompts. Optional `--lines='F1,Q99,R70'` checks a candidate against observed numeric/training rules without posting it. The command sends only the login POST and allowlisted page GETs, never a Bid Sheet POST. It uses a separate HTTP factory without application HTTP events/global middleware, an operation-local memory cookie jar cleared on exit, explicit timeouts, and no retries. Output contains counts, supported control names, and a report-link flag; it omits credentials, cookies, raw page/state values, account labels, and actual bid choices. It neither fetches the submitted report nor verifies context, deadlines, receipt semantics, or account matching. It is a local discovery tool, not an application submission flow.

The public root form's observed `pages/Login.aspx` action and redirected `/Pages/Login.aspx` login forms are supported. When login redirects to the user-confirmed `/Redirect.aspx`, discovery visits that landing page with the same cookie jar before opening Bid Sheet. Repeated radio-button names, such as CBT settings, are accepted without including their values in the candidate; duplicate hidden, text, and mixed-type fields remain rejected. Unexpected failures identify the operation step and exception type, with only a numeric cURL code or PHP source basename/line when available. Raw upstream error messages, URLs, full paths, and previous exceptions remain excluded. An authenticated failure still requires the user-run diagnostic to establish its cause; a successful public GET alone does not verify login.

The user-run command returned 315 choice slots, zero editable slots, 21 populated slots, no submission-control names, and a Submitted Bid Lines link. No candidate list was supplied. This confirms access to the closed-window Bid Sheet and its observed structure; it does not verify submission, the report's contents, or account/context binding.

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

Read retry policies and write retry policies must be separate. Verify the live portal deadline and its displayed time zone against the contractual UTC deadlines in finding S6; package timestamps alone do not establish the portal's displayed time basis or actual open window.

### Bid-award navigation and data discovery

The supplied Bid Sheet navigation includes `Bid Awarded` → `/Pages/BidSheetAward.aspx` and `All Bids Awarded` → `/Pages/AllBidAwards.aspx`. It also links `Submitted Bid Lines` → `/Pages/BidLineDataOutput.aspx` and `Days Off and Comp Lines` → `/Pages/BidCommentsOutput.aspx`. Menu labels and destinations are observed; their contents and update behavior are not yet verified.

The supplied Submitted Bid Lines source confirms a ReportViewer form posting back to `./BidLineDataOutput.aspx`, with Excel and Acrobat (PDF) export choices. The actual report body is outside the saved top-level source: an iframe uses `/Reserved.ReportViewerWebControl.axd` with `OpType=DocMapReport` and a nested report URL using `OpType=ReportArea`. Session-dependent report/control identifiers must come from a fresh authenticated operation, never from this capture. The wrapper does not establish report columns, personal-versus-all-crew scope, complete choice order, receipt semantics, or context/owner matching. Obtain a redacted Excel/PDF export or the rendered report-frame content before implementing a read-back parser; missing report data must not be treated as an empty bid list. Award-page contents remain undiscovered.

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

Remaining decisions are the live authentication/submission protocol, accepted rank serialization, account/context binding, deadline/time-zone semantics, receipt/read-back evidence, and award schema/retention. The prefix-to-number mapping is user-confirmed. Obtain an open-window Bid Sheet form and a redacted Submitted Bid Lines report to continue discovery; do not enable submission based on the closed-window or historical HTML alone.

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

Dollar estimates, rate management, and pay sorting remain deferred. Use Estimated pay as the eventual interface label; MoneyLine is only a possible later product name.

Rate-table input is available in [app/Data/pay_rates.json](app/Data/pay_rates.json): `CA` (Captain) and `FO` (First Officer) each contain 12 numeric rates. Comparison with Appendix 5-A identifies these as the January 1, 2024 dollar hourly rates in longevity-year order 1–12, without separate fleet columns. The JSON itself carries no effective-date or currency metadata, and applicable 2026 rates remain unverified (finding C7).

Section 5.C verifies a 64-PCH MBPG and a 70-PCH composite-line minimum in the supplied CBA, subject to the qualifications in finding C5. Apply the greater-of monthly-pay structure and separate additional compensation rather than treating adjusted trip totals as pay. Confirm current applicability, source eligibility, premium interactions, hostile-area designations, and monetary rounding before implementation. The historical carry-credit calculation above is not CBA-validated; findings C1–C8 record the discrepancies and rules already established by the supplied text.

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

