# Codex Usage Rules

Follow these rules for every remaining task:
1. Work on one task at a time marked by ##.
2. Run only focused tests while implementing a task.
3. Run Pint after PHP files change.
4. Larastan once at the final integration checkpoint, not after every small edit.
5. Preserve unrelated working-tree changes.
6. Update TODO.md with outcomes instead of adding another plan or duplicate checklist.
7. Create a commit message for each task


## Completed: Clean crew names and remove deadhead badge
* Outcome: Leading OCR markers such as `Xx` are removed from crew names, and crew rows retain the `DH` role without rendering a redundant `Deadhead` badge.
* Verification: 12 focused tests pass with 87 assertions; Pint passes for dirty PHP files.

## Completed: Configurable schedule airline codes
* Outcome: Schedule IATA/ICAO codes now come from environment-backed configuration, with validated per-user overrides available on the Profile page and used by both roster parsers.
* Verification: 20 focused tests pass with 125 assertions; Pint passes for dirty PHP files.

## Current focus: Airport Lookup Client address should be in .env with a fallback
- migrate localhost value into .env and config

## Flight accordion: 
* Convert FLIGHT TIMES (LOCAL) and DUTY TIMES (LOCAL) into standard 2-column grid cards just like the rest, then group them under a distinct sub-heading so the layout pattern stays consistent.

## Remove Observer from operating crew
* Change Crew List Parser Test assertion

## Duplicate Database Queries
Issue: 2 out of 5 SQL statements executed during this request are exact duplicates.

Details: The query select * from cache where key in ('dev-k4-parser-cache-sessions:...') is executed twice back-to-back (560µs and 410µs).

Fix: Check where this cache lookup is being invoked in your controller, service, or Livewire component lifecycle. Memoize the result in memory (e.g., storing it in a property or static variable) so it only hits the database once per request.

## Switch SESSION_DRIVER and CACHE_STORE to an in-memory store like Redis or Memcached 
* In production to prevent session updates (update sessions set payload = ...) from competing with application queries.

## Release 1
- All tests pass

## API

## Flight plan extractor improvements
* Extract ETOPs EENT, EEXP and ETP
