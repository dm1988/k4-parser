# Schedule Extractor Roadmap

## Goal

Complete refactor branch, move pending work and fixes to main

## Remove the Roster HTTP Rollback Path — complete

Completed 2026-07-22.

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

Implementation notes:

- Repository-wide usage confirmed that `POST /parse/roster` had no remaining supported application consumer; the upload form already submits through Livewire.
- Removed `ParserController::parseRoster()`, `handleParseAction()`, the named POST route, and `ParseRosterRequest`.
- Removed transitional flashed old-input and session-error handling from `ScheduleExtractor::mount()` and `ParserPageViewModel`.
- Preserved the parser page and all three controller-backed calendar export GET routes with their existing middleware and authorization.
- Migrated endpoint-oriented parsing coverage to Livewire or direct parsing-service coverage and added an explicit 404 regression test for `POST /parse/roster`.
- Focused verification: 59 tests and 461 assertions passed.
- Full verification: 261 of 262 tests passed with 1,481 assertions. The sole failure is the pre-existing `UserModelTest::it_resolves_feature_access_from_config_and_role` contract mismatch documented below.
- Pint passed. Larastan reports only the five pre-existing findings documented below; this work adds no findings.


## Known Issues and Technical Debt

### Test contract mismatch

The full suite currently has one unrelated failure:

`UserModelTest::it_resolves_feature_access_from_config_and_role`

The test expects an unverified user to access the schedule parser, while `User::canUseScheduleParser()` requires verified email. Resolve the intended contract, then update the incorrect side. Recent full run: 261 of 262 tests passed with 1,481 assertions.

### Larastan

Five pre-existing level-5 findings remain:

- Remove redundant `array_values()` calls in `ParsedEventDTO`, `DutyEventMapper`, `FlightMapper`, and `TripInformationParser`.
- Remove `TripInformationParser::firstMatchingLine()` if repository-wide usage confirms it is dead.

Do not add a baseline or blanket ignores. Run:

```text
vendor/bin/sail php vendor/bin/phpstan analyse --no-progress
```

### Parse-key ownership

Session-latest results can fall back to global `parsed_results:{parseKey}` cache entries. Parse keys currently behave as bearer identifiers and are not checked against user ownership. User ownership should be determined and held with parse key.

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
