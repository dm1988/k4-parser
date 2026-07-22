# Schedule Extractor Roadmap

## Goal

Provide one Livewire-powered schedule extractor that transitions between upload and results views without a full-page reload.

Livewire owns form state, validation, parsing, errors, loading state, parsed-result state, and view transitions. Alpine is limited to small browser-only interactions such as accordions, dropdowns, copy feedback, and transitions.

## Remove Airport Lookups from Rendering — complete

Completed 2026-07-22.

### Problem

`ParserResultViewModel::fromData()` currently performs synchronous airport-provider requests while building flight cards. A roster with seven flights can trigger up to fourteen sequential origin/destination lookups. With the existing five-second request timeout, this explains the observed behavior:

- Initial dashboard document: approximately 1.2 minutes.
- Livewire results update: approximately 10 seconds in one trace.
- Application log: confirmed SSL timeout calling the airport provider.

The upload-view rendering optimization is complete, but results rendering still depends on optional network data.

### Required outcome

- View models, Livewire `render()`, and Blade rendering perform no HTTP requests.
- Airport codes are normalized and deduplicated once per parse.
- Only unique, uncached codes reach `AirportLookupClient`.
- Successful and missing results are cached distinctly.
- Resolved airport metadata is attached before the parser result is cached.
- Provider failures never fail parsing or hide calendar exports.
- Missing details fall back to the airport code and an unavailable-details state.

### Proposed design

Keep responsibilities separate:

- `AirportLookupClient`: existing provider HTTP behavior.
- `AirportCodeCache`: positive and negative cache entries.
- `AirportResolver`: normalization, deduplication, cache-first resolution, and orchestration.
- `AirportResolutionStatus`: explicit found, missing, and unavailable states.
- `AirportResolution`: serializable resolution DTO.
- `AirportResolutionException`: invalid resolution-state errors.

Use versioned keys such as `airport:v1:iata:AUS`. Start with a long TTL for successful records and a shorter TTL for missing/unavailable records. Do not represent both “uncached” and “known missing” as plain `null`.

Preferred lifecycle:

1. Resolve and parse the schedule source.
2. Collect unique airport codes from parsed events.
3. Resolve codes through the cache-first `AirportResolver->resolveMany($codes)`.
4. Attach resolved metadata to the parsed result.
5. Store the completed result in `ParserResultCache`.
6. Build network-free view models from stored data.

### Tests

- Resolver normalizes and deduplicates codes.
- Invalid codes are ignored.
- Cached results do not call the client.
- Successful and missing responses are cached correctly.
- One failed lookup does not stop other resolutions.
- Repeated event codes cause one client lookup per unique uncached code.
- Cached parser results contain the airport data required by the UI.
- Strict mocks prove no airport calls occur during view-model creation, initial results rendering, refresh, upload rendering, or “Extract another roster.”

### Completion criteria

- Network-free rendering is enforced by tests.
- Rendering timing no longer scales with flight count or provider latency.
- Parsing remains successful when airport enrichment is unavailable.
- Focused tests, full tests, Pint, and Larastan have been run.

Do not change provider, queues, timeout/retry policy, card design, or export architecture during the initial refactor. Measure enrichment after removing it from rendering, then decide whether the existing `connectTimeout(2)`, `timeout(5)`, and retry policy also need adjustment.

Implementation notes:

- Airport enrichment now runs once in `JcaScheduleParsingService`, after filtering and before `ParserResultCache::put()`.
- `AirportResolver` normalizes and deduplicates codes, checks versioned positive/negative cache entries, and isolates provider failures.
- `ParserResultViewModel`, Livewire rendering, and Blade consume cached metadata without resolving `AirportLookupClient`.
- Missing and unavailable details render the airport code with an unavailable-details state; calendar export data remains intact.
- Focused verification: 34 tests and 217 assertions passed.
- Full verification: 260 of 261 tests passed with 1,491 assertions. The sole failure is the pre-existing `UserModelTest::it_resolves_feature_access_from_config_and_role` contract mismatch documented below.
- Pint passed. Larastan reports only the five pre-existing findings documented below; this work adds no findings.

## Current Priority: Remove the Roster HTTP Rollback Path

The Livewire roster workflow is stable, but the old controller POST remains as a rollback path.

Before removal, confirm no supported external or programmatic consumer uses `POST /parse/roster`.

Then:

- Remove `ParserController::parseRoster()`.
- Remove the `parse.roster` route.
- Remove `ParseRosterRequest` if it is no longer referenced.
- Remove `handleParseAction()` and its imports if no controller action uses it.
- Remove transitional old-input and parser-specific session-error handling from `ScheduleExtractor::mount()`.
- Preserve all calendar export controller actions and GET routes.
- Update route, authentication, authorization, lifecycle, and Livewire tests.

The following export routes remain controller-backed:

- `GET /parse/export`
- `GET /parse/export/event/{eventId}`
- `GET /parse/export/event/{eventId}/duty`

## Completed Work

### Phase 1: Blade preparation — complete

Completed 2026-07-21.

- Confirmed the parser form and result Blade components were already coherent boundaries.
- Added shared, HTTP-independent `ParserValidationRules`.
- Added upload, lifecycle, empty-result, export, authentication, verification, tab, and parse-key characterization coverage.
- Preserved the original request/controller/redirect behavior during this phase.
- Verification at completion: 75 tests, 521 assertions.

### Phase 2: Livewire roster extractor — complete

Completed 2026-07-21.

- Added class-based `App\Livewire\ScheduleExtractor`.
- Moved visible roster state, validation, parsing, errors, loading, cache restoration, and upload/results transitions to Livewire.
- Kept public state limited to form values, view state, and a locked parse key.
- Added explicit authentication, verification, feature, and gate enforcement.
- Preserved calendar downloads as controller-backed GET responses.
- Added “Extract another roster” while retaining selected filters and the latest successful cached result.
- Empty parses stay on upload and do not replace the latest successful result.
- Removed the obsolete Alpine parser-submit module.
- Verification at completion: full suite passed with 258 tests and 1,463 assertions; no new Larastan findings.

### Phase 3: Remove flight and hotel POST endpoints — complete

Completed 2026-07-21.

- Removed `parseFlight()` and `parseHotel()` from `ParserController`.
- Removed `parse.flight` and `parse.hotel` routes.
- Removed `ParseFlightRequest` and `ParseHotelRequest`.
- Removed endpoint-only tests and retained feature-middleware coverage on roster parsing.
- Kept shared parsing, cache, execution, and export services.
- Kept `handleParseAction()` because roster rollback still uses it.
- Focused verification: 41 tests, 328 assertions.

### Rendering and reset cleanup — complete

Completed 2026-07-21.

- Upload mode no longer constructs `ParserPageViewModel` or resolves a cached result.
- The upload form reads its values from Livewire state and receives only lightweight filter options.
- Added stable keys for upload and parse-specific results sections.
- `extractAnotherRoster()` resets only file, text, and validation state; it preserves selected event types, the parse key, and the cached successful result.
- Focused verification: 17 Livewire tests, 128 assertions.

## Behavioral Invariants

### Rendering

- Use Blade conditional rendering for the substantial upload and results sections.
- Do not use `x-show` to keep both page states in the DOM.
- Livewire is the source of truth for whether upload or results is active.
- Rendering must not perform external HTTP requests.

### Cache and failure behavior

- Keep the latest successful parsed result.
- Do not clear it when starting another extraction.
- Do not replace it after validation failure, parser failure, or a zero-event parse.
- Replace it only after a successful parse containing events.
- Preserve parse-key-specific export URLs.

### Uploads

- Local Livewire temporary uploads are supported.
- The parser requires a real local path.
- S3/non-local Livewire temporary uploads require an explicit adapter or service change.

### Exports

- Calendar exports remain normal controller-backed GET downloads.
- Export services may return HTTP responses; parsing and rendering services should not.

### Authorization

- Preserve authentication, verified-email, feature-flag, and gate checks.
- Do not broaden parser or duty-export access during lifecycle refactors.

## Known Issues and Technical Debt

### Test contract mismatch

The full suite currently has one unrelated failure:

`UserModelTest::it_resolves_feature_access_from_config_and_role`

The test expects an unverified user to access the schedule parser, while `User::canUseScheduleParser()` requires verified email. Resolve the intended contract, then update the incorrect side. Recent full run: 258 of 259 tests passed with 1,477 assertions.

### Larastan

Five pre-existing level-5 findings remain:

- Remove redundant `array_values()` calls in `ParsedEventDTO`, `DutyEventMapper`, `FlightMapper`, and `TripInformationParser`.
- Remove `TripInformationParser::firstMatchingLine()` if repository-wide usage confirms it is dead.

Do not add a baseline or blanket ignores. Run:

```text
vendor/bin/sail php vendor/bin/phpstan analyse --no-progress
```

### Parse-key ownership

Session-latest results can fall back to global `parsed_results:{parseKey}` cache entries. Parse keys currently behave as bearer identifiers and are not checked against user ownership. Decide explicitly whether this is intentional before changing cache ownership or export authorization.

### Duplicate Alpine warning

Investigate the current browser warning that multiple Alpine instances are running. Confirm whether Alpine is bundled by both the application and Livewire before changing frontend initialization.

## Product and UI Backlog

- Improve the upload target and button alignment.
- Refine hero/upload card hierarchy and spacing.
- Improve filter accordion alignment.
- Add more navbar vertical padding.
- Shorten upload status copy.
- Add the independent-tool disclaimer for Jeppesen/Boeing affiliation.
- Continue spelling out “Jeppesen Crew Access” before using “JCA.”
- Consider Laravel Debugbar only with explicit dependency approval and development-only configuration.

## Deferred Architecture Ideas

These are proposals, not approved work:

- Rename parser-centric types only as part of a separately scoped refactor.
- Organize services by Schedule, Flight Plan, Calendar, Clients, and Infrastructure domains.
- Avoid broad file moves while lifecycle and rendering performance work is active.
- Any reorganization must preserve behavior, update namespaces atomically, and be covered by focused and full tests.

## Definition of Done for Parser Migration

- Upload and results are substantial, conditionally rendered Livewire states.
- Parsing does not cause a full-page reload.
- Failures remain on upload without destroying the previous successful result.
- “Extract another roster” resets only transient form state.
- Rendering is network-free.
- Calendar exports continue to work through controller GET routes.
- Obsolete parsing POST routes and controller helpers are removed after consumer confirmation.
- Authorization and feature-gate behavior remain unchanged.
- Focused tests, full tests, Pint, frontend build when relevant, and Larastan have been run.
