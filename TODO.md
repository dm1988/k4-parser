# Codex Usage Rules

Follow these rules for every remaining task:
1. Work on one task at a time marked by ##.
2. Run only focused tests while implementing a task.
3. Run Pint after PHP files change.
4. Larastan once at the final integration checkpoint, not after every small edit.
5. Preserve unrelated working-tree changes.
6. Update TODO.md with outcomes instead of adding another plan or duplicate checklist.
7. Create a commit message for each task

## Completed: Multi photo uploads feature
* Constraint: Allow multiple images, but only one PDF.
* Outcome: Implemented multi-image selection (maximum five), single-PDF enforcement, source conflict validation, per-image OCR/parsing, merged/deduplicated/date-sorted events, aggregate request logging, file-list UI, and updated focused tests.
* Verification: 37 focused Sail tests pass with 304 assertions; Pint passes for dirty PHP files.

## Completed: Prefer explicit tail ID during roster parsing
* Outcome: Explicit `Tail id` values now take precedence over generic registration-like OCR tokens, so `ETDIIG` no longer overrides `N772CK`.
* Verification: 13 focused roster parser tests pass with 145 assertions; Pint passes for dirty PHP files.

## Completed: Clean crew names and remove deadhead badge
* Outcome: Leading OCR markers such as `Xx` are removed from crew names, and crew rows retain the `DH` role without rendering a redundant `Deadhead` badge.
* Verification: 12 focused tests pass with 87 assertions; Pint passes for dirty PHP files.

## CKS flight codes strings should be extracted to env file and not in the codebase
- add to user preferences

## Airport Lookup Client address should be in .env with a fallback
- migrate localhost value into .env and config

## Flight accordion: 
* Convert FLIGHT TIMES (LOCAL) and DUTY TIMES (LOCAL) into standard 2-column grid cards just like the rest, then group them under a distinct sub-heading so the layout pattern stays consistent.

## Remove Observer from operating crew
* Change Crew List Parser Test assertion

## Release 1
- All tests pass

## API

## Flight plan extractor improvements
* Extract ETOPs EENT, EEXP and ETP
