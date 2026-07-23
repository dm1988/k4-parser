# Codex Usage Rules

Follow these rules for every remaining task:
4. Work on one numbered task at a time.
5. Run only focused tests while implementing a task.
6. Run Pint only when PHP files change.
7. Larastan once at the final integration checkpoint, not after every small edit.
9. Preserve unrelated working-tree changes.
10. Update this file with outcomes instead of adding another plan or duplicate checklist.

## Current focus: Product and UI Polish

* Continue spelling out “Jeppesen Crew Access” before introducing the “JCA” abbreviation.

## Naming and Architecture

* Clean up parser-to-extract naming.
* Find all parser references
* Decide how to hanlde to prefered extract verbage
* Rename 2 dtos: ParsedEventDTO, ParserResultData
* Rename Enum, Exceptions
* Rename ParserEventType Enum to ScheduleEventType.php
* Rename controller, policy
* Rename model
* Explore renaming DB table
- Any reorganization must preserve behavior, update namespaces atomically, and be covered by focused and full tests.

## Multi photo uploads feature

## Refine Card Margins and Navbar Typography

- Spacing: Increase the vertical spacing (gap or margin-bottom) between the hero header card and the upload card if you keep them separate.

- The navbar items ("Parse Schedule", "Route Extractor", etc.) are quite close to the top edge of the viewport. Adding a bit more top and bottom padding to the navbar container will give the text room to breathe and look cleaner.

## CKS flight codes strings should be extracted to env file and not in the codebase

## Release 1
- All tests pass
