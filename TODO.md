# Goal

Complete refactor branch, move pending work and fixes to main

# Known Issues and Technical Debt

## Current focus: Parse-key ownership

Session-latest results can fall back to global `parsed_results:{parseKey}` cache entries. Parse keys currently behave as bearer identifiers and are not checked against user ownership. User ownership should be determined and checked

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
