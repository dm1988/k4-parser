# Integration TODO

## Objective

Reconcile the schedule-extractor work into `main` with the fewest Codex passes and without repeating work already completed on either branch.

## Branch Status

### `main`

Current integration base.

Already contains the former `parser-enrich-airport-info` work, including airport metadata, flight-card presentation, and `AirportLookupClient`. The branch `parser-enrich-airport-info` is an ancestor of `main` and has no commits that need merging.

Do not merge, cherry-pick, or reimplement `parser-enrich-airport-info`.

### `refactor/livewire-schedule-parser`

The only feature branch still ahead of `main`.

It contains:

- Shared parser validation rules.
- The class-based `ScheduleExtractor` Livewire component.
- Livewire upload, validation, parsing, loading, error, and upload/results state.
- Authentication, verified-email, feature, and gate enforcement for Livewire actions.
- Cache/lifecycle characterization tests.
- Removal of obsolete flight and hotel POST endpoints.
- Upload-render optimization and stable Livewire keys.
- Minimal “Extract another roster” reset behavior.

## Codex Usage Rules

Follow these rules for every remaining task:

1. Do not repeat the completed parser audit.
2. Check the branch diff and existing tests before writing code.
3. Treat the “Already Complete” section below as authoritative.
4. Work on one numbered task at a time.
5. Run only focused tests while implementing a task.
6. Run Pint only when PHP files change.
7. Run the full suite and Larastan once at the final integration checkpoint, not after every small edit.
8. Do not reorganize services, rename domains, install packages, or redesign UI unless that item is explicitly activated.
9. Preserve unrelated working-tree changes.
10. Update this file with outcomes instead of adding another plan or duplicate checklist.

## Task 1: Merge the Livewire Branch

Merge `refactor/livewire-schedule-parser` into `main` as one integration task.

### Merge guidance

- Keep this reconciled `TODO.md`; do not restore the feature branch’s older roadmap.
- Preserve the airport enrichment and flight-card behavior already on `main`.
- Preserve the current branding/title behavior on `main`.
- Resolve code conflicts by combining main’s airport/UI behavior with the branch’s Livewire lifecycle.
- Do not add flight or hotel Livewire actions; those endpoints were intentionally removed.
- Keep calendar exports as controller-backed GET routes.

### Focused verification

Run these once after conflict resolution:

- `tests/Feature/Livewire/ScheduleExtractorTest.php`
- `tests/Feature/ParserLifecycleBaselineTest.php`
- `tests/Feature/ParserCacheIsolationTest.php`
- Parser upload and request-validation tests.
- Feature-route authorization tests.
- Full-calendar, event, and duty-export tests.
- `vendor/bin/sail artisan route:list --path=parse --except-vendor`

Expected route state:

- Keep `POST /parse/roster` temporarily as the rollback path.
- Remove flight and hotel parsing POST routes.
- Keep all three calendar export GET routes.

### Done when

- The Livewire upload/results workflow works on `main`.
- Main’s airport metadata UI still renders.
- No obsolete flight/hotel parsing endpoints exist.
- Focused tests and Pint pass.

## Task 2: Remove Airport HTTP Calls from Rendering

Start only after Task 1 is merged and stable.

This is not a second airport-enrichment feature. Main already has airport enrichment. This task only moves optional provider calls out of the request rendering path.

### Confirmed problem

`ParserResultViewModel::fromData()` performs synchronous origin/destination lookups. Seven flights can produce fourteen sequential requests. This explains the observed approximately 1.2-minute dashboard response and 10-second Livewire update.

### Required change

- Make result/page view models, Livewire `render()`, and Blade network-free.
- Normalize and deduplicate airport codes once per successful parse.
- Resolve only unique uncached codes through the existing `AirportLookupClient`.
- Cache successful and missing results distinctly.
- Attach airport metadata before writing the completed parser result to `ParserResultCache`.
- Preserve a safe code-only fallback when provider data is unavailable.

Suggested separation:

- `AirportLookupClient`: existing provider HTTP client; do not duplicate it.
- `AirportCodeCache`: positive and negative cache entries.
- `AirportResolver`: normalization, deduplication, and cache-first orchestration.

### Focused tests

- Duplicate codes cause one lookup per unique uncached code.
- Cached positive and negative results do not call the client.
- Provider failure does not fail roster parsing.
- Cached parser results contain the metadata needed by flight cards.
- Strict mocks prove view-model construction, initial render, refresh, upload render, and reset make no airport calls.

Do not change the provider, UI design, queues, or timeout/retry policy in this task. Measure again after rendering is network-free.

## Task 3: Remove the Roster HTTP Rollback Path

Start after the Livewire flow has passed its rollback period and supported consumers have been checked.

- Confirm no supported client uses `POST /parse/roster`.
- Remove `ParserController::parseRoster()` and the `parse.roster` route.
- Remove `ParseRosterRequest` if unused.
- Remove `handleParseAction()` and related imports if unused.
- Remove transitional old-input and parser-specific session-error handling from `ScheduleExtractor::mount()`.
- Preserve calendar export controller actions and routes.
- Update route, lifecycle, authentication, and authorization tests.

## Task 4: Final Integration Verification

Run once after Tasks 1–3 that are selected for this integration are complete:

1. Relevant focused tests.
2. `vendor/bin/sail bin pint --dirty --format agent`.
3. `vendor/bin/sail php vendor/bin/phpstan analyse --no-progress`.
4. `vendor/bin/sail artisan test --compact`.
5. Frontend production build if Blade, JavaScript, or Tailwind output changed.

Known issue to resolve or explicitly record:

- The Livewire branch previously produced one `UserModelTest` failure because the test expected an unverified user to access the parser while `User::canUseScheduleParser()` required verified email. Decide the intended contract once and update only the incorrect side.

Do not create a Larastan baseline or blanket ignores. Fix only findings present after the merged code is analyzed; do not separately fix the same finding on both branches.

## Already Complete — Do Not Repeat

### On `main`

- Airport lookup client and airport metadata presentation.
- Airport detail/popover regression work.
- Parser/extractor branding and current title wording.
- Existing calendar export behavior.
- Previously completed critical, high, and medium Larastan fixes.
- Existing authentication and verified-route middleware.

### On `refactor/livewire-schedule-parser`

- Parser workflow audit and baseline inventory.
- Shared `ParserValidationRules`.
- Livewire roster extraction and local temporary uploads.
- Locked server-owned view and parse-key state.
- Validation normalization and error-field mapping.
- Source-resolution and stale-parse-key handling.
- Minimal form reset that preserves filters and cached success.
- Upload-only rendering without a page result view model.
- Removal of flight/hotel controller actions, routes, requests, and endpoint-only tests.
- Focused Livewire, lifecycle, isolation, upload, authorization, and export coverage.

## Behavioral Invariants

- Livewire owns upload/results state, validation, parsing, loading, and errors.
- Alpine is limited to small browser-only interactions.
- Upload and results are conditionally rendered; do not keep both in the DOM with `x-show`.
- Failed validation, parser errors, and zero-event parses do not replace the latest successful result.
- “Extract another roster” clears only file, text, and validation state; it preserves filters and cached success.
- Local temporary uploads are supported; non-local temporary storage requires an explicit adapter.
- Calendar exports remain ordinary controller GET responses.
- Authentication, verified-email, feature-flag, and gate behavior must remain intact.
- Rendering must perform no external HTTP requests after Task 2.

## Deferred Backlog

Do not combine these with branch reconciliation:

- Parse-key ownership and cross-user export authorization decision.
- Duplicate Alpine-instance warning investigation.
- Upload-card, filter, navbar, status-copy, and disclaimer polish.
- Laravel Debugbar installation; dependency changes require approval.
- Parser-to-schedule naming cleanup.
- Broad service/domain directory reorganization.

Activate deferred work as separate tasks only after integration and performance work are complete.
