# Codex Usage Rules

Follow these rules for every remaining task:
1. Work on one task at a time marked by ##.
2. Run only focused tests while implementing a task.
3. Run Pint after PHP files change.
4. Larastan once at the final integration checkpoint, not after every small edit.
5. Preserve unrelated working-tree changes.
6. Update TODO.md with outcomes instead of adding another plan or duplicate checklist.
7. Create a commit message for each task


## Completed: Configurable Airport Lookup Client address
* Outcome: `AIRPORT_PROVIDER_URL` now controls the client base URL through service configuration, defaults to `http://localhost/api/v1`, and the client normalizes trailing slashes before requests.
* Verification: 8 focused tests pass with 28 assertions; Pint passes for dirty PHP files.

## Completed: Flight accordion local-time layout
* Outcome: Flight details use a reusable label/value card component, while flight and duty local times render in a gold-bordered, muted blue-gray responsive two-column group beneath a distinct Local Times subheading.
* Verification: 7 focused component tests pass with 29 assertions; Pint passes for dirty PHP files.

## Current focus: Remove Observer from operating crew count
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

## Dark mode
