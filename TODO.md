# Codex Usage Rules

Follow these rules for every remaining task:
4. Work on one numbered task at a time.
5. Run only focused tests while implementing a task.
6. Run Pint only when PHP files change.
7. Larastan once at the final integration checkpoint, not after every small edit.
9. Preserve unrelated working-tree changes.
10. Update this file with outcomes instead of adding another plan or duplicate checklist.

# Current focus: Bug: ICS export notes incorrect data
- Label Local leg shows UTC time
• Local start: 07-23 18:00 
• Local end: 07-23 19:25 
- Remove these 2 entries from the ics notes

## 1. Streamline the Upload Card Structure
Outcome: Completed. The upload flow is now a single flat, centered tool without separate hero or form cards. It includes a large drag-and-drop target, selected filename and file size feedback, a full-width stateful “Extract Schedule” button, and an accessible workflow-guide link.

## 2. Product and UI Polish

* Continue spelling out “Jeppesen Crew Access” before introducing the “JCA” abbreviation.

## 3. Naming and Architecture

* Clean up parser-to-extract naming.
* Find all parser references
* Decide how to hanlde to prefered extract verbage
* Rename 2 dtos: ParsedEventDTO, ParserResultData
* Rename Enum, Exceptions
* Rename ParserEventType Enum to ScheduleEventType.php
* Rename controller, policy
* Rename model
* Explore renaming DB table

## 4. Deferred Architecture Ideas

These are proposals, not approved work:

- Rename parser-centric types only as part of a separately scoped refactor.
- Any reorganization must preserve behavior, update namespaces atomically, and be covered by focused and full tests.
- Multi photo uploads feature

## 5. Refine Card Margins and Navbar Typography

- Spacing: Increase the vertical spacing (gap or margin-bottom) between the hero header card and the upload card if you keep them separate.

- The navbar items ("Parse Schedule", "Route Extractor", etc.) are quite close to the top edge of the viewport. Adding a bit more top and bottom padding to the navbar container will give the text room to breathe and look cleaner.


## 6. Use a descriptive footer disclaimer in Parse schedule tool:
- To safely clarify your tool's relationship to the platform, add a small, subtle line of text at the very bottom of your application page layout:

Disclaimer: This tool is an independent utility built for crew convenience and is not affiliated with, authorized, or endorsed by Jeppesen or Boeing.

## 7. One line status message? Can leave as title, sub title message
- In schedule extractor, shorten status codes
- System online: Ready to process
- 0.3 MB image selected, ready to upload

## 8. CKS flight codes strings should be extracted to env file and not in the codebase
