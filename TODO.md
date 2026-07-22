## Branch Status

### `main`

## Codex Usage Rules

Follow these rules for every remaining task:
4. Work on one numbered task at a time.
5. Run only focused tests while implementing a task.
6. Run Pint only when PHP files change.
7. Larastan once at the final integration checkpoint, not after every small edit.
8. Do not reorganize services, rename domains, install packages, or redesign UI unless that item is explicitly activated.
9. Preserve unrelated working-tree changes.
10. Update this file with outcomes instead of adding another plan or duplicate checklist.

### On `main`

- Airport lookup client and airport metadata presentation.
- Airport detail/popover regression work.
- Parser/extractor branding and current title wording.
- Existing calendar export behavior.

# Deferred Product, UI, and Architecture Backlog

Do not combine these items with branch reconciliation. Activate them as separate tasks only after integration and performance work are complete.

### Product and UI Polish

* Improve the upload target and button alignment.
* Refine the hero and upload-card hierarchy, spacing, and visual balance.
* Improve filter accordion alignment.
* Add more vertical padding to the navbar.
* Shorten upload status copy.
* Add a disclaimer clarifying that the tool is independent and is not affiliated with Jeppesen or Boeing.
* Continue spelling out “Jeppesen Crew Access” before introducing the “JCA” abbreviation.


### Naming and Architecture

* Clean up parser-to-schedule naming.
* Service organization completed: schedule, flight-plan, calendar, client, and infrastructure services now live in explicit domain namespaces, with the backlog-specified class names applied.

### Development Tooling

* Consider installing Laravel Debugbar only with explicit approval for dependency changes.
* Configure Debugbar for development environments only.


# Deferred Architecture Ideas

These are proposals, not approved work:

- Rename parser-centric types only as part of a separately scoped refactor.
- Services are organized by Schedule, Flight Plan, Calendar, Clients, and Infrastructure domains.
- Avoid broad file moves while lifecycle and rendering performance work is active.
- Any reorganization must preserve behavior, update namespaces atomically, and be covered by focused and full tests.
- Multi photo uploads feature

# Bug: does not read multiple pages of a trip information pdf
- Only first page is extracted