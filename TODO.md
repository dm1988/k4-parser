# Codex Usage Rules

Follow these rules for every remaining task:
1. Work on one task at a time marked by ##.
2. Run only focused tests while implementing a task.
3. Run Pint after PHP files change.
4. Larastan once at the final integration checkpoint, not after every small edit.
5. Preserve unrelated working-tree changes.
6. Update TODO.md with outcomes instead of adding another plan or duplicate checklist.
7. Create a commit message for each task

## Completed: Align User fillable attribute test
* Outcome: The expected fillable order now matches the User model, with airline IATA and ICAO preferences immediately after email.
* Verification: 2 focused model tests pass with 9 assertions; Pint passes.

## Completed: Remove Observer from operating crew count
* Outcome: Observer crew remain in the crew list and total crew count, but are excluded from the operating crew count without being classified as deadheading.
* Verification: 15 focused parser tests pass with 99 assertions; Pint passes.

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
