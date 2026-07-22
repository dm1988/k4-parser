# Goal

Complete refactor branch, move pending work and fixes to main

# Known Issues and Technical Debt

## Test contract mismatch — complete

Completed 2026-07-22.

The failing test was:

`UserModelTest::it_resolves_feature_access_from_config_and_role`

The production contract was correct: parser routes require verified email, and Livewire independently enforces the same requirement because component actions can execute outside route middleware. The unit test now constructs verified users for successful access assertions and explicitly covers denial for unverified administrators and users.

Verification: focused model tests passed with 2 tests and 9 assertions; the full suite passed with 262 tests and 1,485 assertions.

## Current focus: Larastan

Five pre-existing level-5 findings remain:

- Remove redundant `array_values()` calls in `ParsedEventDTO`, `DutyEventMapper`, `FlightMapper`, and `TripInformationParser`.
- Remove `TripInformationParser::firstMatchingLine()` if repository-wide usage confirms it is dead.

Do not add a baseline or blanket ignores. Run:

```text
vendor/bin/sail php vendor/bin/phpstan analyse --no-progress
```

## Parse-key ownership

Session-latest results can fall back to global `parsed_results:{parseKey}` cache entries. Parse keys currently behave as bearer identifiers and are not checked against user ownership. User ownership should be determined and held with parse key.

## Duplicate Alpine warning

Investigate the current browser warning that multiple Alpine instances are running. Confirm whether Alpine is bundled by both the application and Livewire before changing frontend initialization.

# Product and UI Backlog

- Improve the upload target and button alignment.
- Refine hero/upload card hierarchy and spacing.
- Improve filter accordion alignment.
- Add more navbar vertical padding.
- Shorten upload status copy.
- Add the independent-tool disclaimer for Jeppesen/Boeing affiliation.
- Continue spelling out “Jeppesen Crew Access” before using “JCA.”
- Consider Laravel Debugbar only with explicit dependency approval and development-only configuration.

# Deferred Architecture Ideas

These are proposals, not approved work:

- Rename parser-centric types only as part of a separately scoped refactor.
- Organize services by Schedule, Flight Plan, Calendar, Clients, and Infrastructure domains.
- Avoid broad file moves while lifecycle and rendering performance work is active.
- Any reorganization must preserve behavior, update namespaces atomically, and be covered by focused and full tests.
