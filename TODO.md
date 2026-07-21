# Goal

Replace the current request → controller → redirect parser workflow with a single Livewire-powered page that transitions between:

1. An upload/input view
2. A parsed-results view

Use Livewire for server state, validation, parsing, and rendering. Use Alpine only for small browser-side interactions within each view.

# Current Task

## 2. Refactor Schedule Parser into a Single-Page Livewire Component

Implement this refactor incrementally. Each phase must leave the parser functional and testable.

Audit reference:

```text
.ai/refactors/livewire-schedule-parser-audit.md
```

The audit is complete. Treat its confirmed findings as the baseline for this plan. Resolve its blockers before changing cache ownership, upload storage, flight/hotel consumers, or zero-event replacement behavior.

## Confirmed baseline

* Laravel 13.15 and Livewire 4.3 are installed.
* The repository has no application-authored Livewire or Volt component; use a class-based component under `App\Livewire` and PHPUnit `Livewire::test(...)` conventions.
* Both `/dashboard` and `/parse` currently restore the latest cached parser result and render the same `dashboard` view.
* Validation failures and `ParseSourceResolutionException` leave the previous successful cached result intact.
* A successful parse with zero events currently replaces the latest result and renders the empty-result state.
* `TemporaryUploadedFile` extends `Illuminate\Http\UploadedFile`, but the parser requires a real local path. Local temporary uploads are compatible; S3/non-local temporary uploads are not compatible without an explicit adapter or service change.
* Flight and hotel POST endpoints exist and are tested, but no flight or hotel form exists in the rendered parser Blade views.
* Parser results are session-latest, but exports can fall back to a global parse-key cache entry. Parse keys currently behave as bearer identifiers and are not checked against user ownership.
* Two tabs in one browser session share `latest_parse_key`; existing export links remain parse-scoped because they include `parse_key`.

---

# Implementation Rules

## Livewire responsibilities

Livewire owns:

* Active page view
* Uploaded file
* Pasted text
* Selected event types
* Form validation
* Parse actions
* Parse errors
* Parsed-result state
* Loading state
* Switching between upload and results views

## Alpine responsibilities

Alpine may be used for:

* Accordions
* Dropdowns
* Copy-to-clipboard feedback
* Small transitions
* Temporary presentational state

Alpine must not own:

* The active upload/results page state
* Parsed data
* Form submission
* Server validation
* Whether a successful parse exists

## Rendering rule

Use Livewire/Blade conditional rendering for the two substantial page sections:

```blade
@if ($view === 'upload')
    <x-parser.upload-form />
@elseif ($view === 'results')
    <x-parser.results :view-model="$viewModel" />
@endif
```

Do not use `x-show` to switch the entire upload and results sections.

This prevents both substantial views from remaining rendered in the DOM and keeps the server as the source of truth.

---

# Phase 1: Prepare the Existing Blade Page

Do not change the existing request lifecycle during this phase.

## Tasks

1. Use the completed audit inventory instead of repeating discovery.
2. Add the missing baseline and characterization tests listed below.
3. Extract parser validation rules and messages into an HTTP-independent shared provider while keeping all three Form Requests as HTTP adapters.
4. Split the existing markup into separate partials or Blade components:

   * Upload form
   * Results display
   * Shared parser messages, when applicable
5. Continue rendering both partials through the existing controller-provided view model.
6. Preserve all current form actions, routes, validation output, old-input behavior, cache restoration, and export links.

Suggested structure:

```text
resources/views/
├── dashboard.blade.php
└── components/parser/
    ├── form.blade.php
    ├── result.blade.php
    └── status-messages.blade.php
```

The existing `components/parser/form.blade.php` and `components/parser/result.blade.php` are already coherent boundaries. Do not rename or split the nested event/flight card components merely to satisfy this phase. Extract shared status markup only when the change is mechanical and behavior-neutral.

## View-model inventory

Determine how the current page uses:

* Parsed roster data
* Calendar events
* Flights
* Hotels
* Warnings
* Source information
* Selected event types
* Old input
* Export URLs
* Empty-result states
* Validation errors
* Parse failures

Do not redesign `ParserPageViewModel` in this phase unless splitting the view exposes an existing defect.

## Tests

Before changing behavior, add or confirm feature coverage for:

* Actual supported PDF upload through the roster HTTP endpoint
* Actual supported image upload through the roster HTTP endpoint
* Pasted roster submission
* Invalid or empty submission
* Parse-source resolution failure, old-input restoration, and prior-result retention
* Non-source parser exception and prior-result retention
* Successful result rendering
* Successful zero-event result replacement and empty-state rendering
* Full-calendar export
* Individual-event export
* Flight-duty export
* Flight parse submission
* Hotel parse submission
* Missing/expired parse keys and unknown event IDs returning 404
* Authentication and email verification for parser POST and export routes
* Two-tab latest-result behavior
* Two-session/user global parse-key access as a security characterization test

## Phase 1 completion criteria

* The parser behaves exactly as it did before.
* Upload and results markup are separated.
* Existing POST parsing routes remain active.
* Existing controller methods remain active.
* The three Form Requests and the future Livewire component can consume one shared validation definition without the component depending on an HTTP Form Request.
* Existing tests pass.

---

# Phase 2: Add Livewire Roster Parsing

Migrate only the main roster parser in this phase.

Do not migrate flight or hotel parsing yet.

## Component

Create one Livewire component for the parser page.

Use project naming conventions. A suitable name would be:

```text
App\Livewire\ScheduleParser
```

The page should render the Livewire component from the existing dashboard route.

## Required component state

At minimum:

```php
public string $view = 'upload';

public $file = null;

public string $text = '';

/** @var array<int, string> */
public array $eventTypes = [];

public ?string $parseKey = null;
```

Supported view values:

```php
'upload'
'results'
```

Prefer constants or an enum only when that matches existing project conventions. Do not introduce an enum solely for two internal values.

## Parsed-result state

Do not place arbitrary service objects, response objects, collections with unsupported values, or non-Wireable DTOs in public Livewire state.

Rebuild the display data from `ParserResultCache` during rendering. Store only simple arrays and scalar identifiers in Livewire state.

The audit confirmed that `ParserResultViewModel::$events` is a heterogeneous list of DTOs/view models, export URLs are route-derived, airport information is service-enriched, and raw JSON may be large. Do not expose `ParserPageViewModel`, `ParserResultViewModel`, event DTOs, mapper objects, service objects, or raw JSON as mutable public Livewire state.

Store only simple form values, transient status, the active view, and a locked/current parse-key scalar. Resolve `ParserResultData` through `ParserResultCache` and rebuild render-only view models on the server.

## Initialization behavior

On component mount:

* Read the latest cached result.
* Open the results view when the current session has a restorable latest cached result, matching current page behavior.
* Otherwise open the upload view.
* Initialize selected event types from the cached result filters when no Livewire form state exists, matching the current view-model fallback.
* Do not clear a previous successful result merely by visiting or refreshing the page.

## Roster parse action

Move the behavior currently handled by:

```php
ParserController::parseRoster()
```

into a Livewire action such as:

```php
public function parseRoster(): void
```

The Livewire action must:

1. Validate the input.
2. Resolve whether the source is:

   * `pasted_text`
   * `pdf`
   * `image`
3. Resolve the parser type using the current rules.
4. Call `HandleParseExecution`.
5. Call `JcaScheduleParsingService::parseRoster()` through the execution service.
6. Preserve current user attribution.
7. Preserve current event-type handling.
8. Convert `ParseSourceResolutionException` errors into Livewire validation errors.
9. Keep `$view` set to `upload` when validation or parsing fails.
10. Change `$view` to `results` only after parsing and cache persistence succeed.
11. Keep the previous successful parse key/result available when a later validation or parsing attempt fails.
12. Preserve the current behavior that a completed zero-event parse is a successful replacement unless that product decision is explicitly changed before implementation.

Do not duplicate parsing, logging, execution tracking, or cache-storage logic inside the Livewire component.

## Validation

Start with validation rules equivalent to `ParseRosterRequest`.

Prefer sharing validation rules between the Form Request and Livewire rather than maintaining two independent rule sets.

A shared rules object, validator class, or static rules method may be introduced when it improves clarity. Do not make the Livewire component instantiate or depend directly on an HTTP Form Request.

Preferred Phase 1 location:

```text
app/Validation/ParserValidationRules.php
```

Preserve:

* Field names
* Validation messages
* File-size limits
* MIME restrictions
* Event-type validation
* Requirement that either a file or pasted text be provided

## File uploads

Use Livewire file-upload support.

Confirm that:

* Temporary uploads work with `HandleParseExecution`.
* The parsing service receives an upload object compatible with its current parameter type.
* File MIME detection remains reliable.
* Existing stored-file and cleanup behavior remains unchanged.
* Upload limits match PHP, web-server, Laravel, and Livewire configuration.

Do not permanently store the same file twice.

Before implementation, confirm the production value of the Livewire temporary upload disk. The repository default is local and compatible. If production uses S3 or another non-local disk, stop and revise the plan: `ScheduleInputResolver` and `ParseRequestLogger` require `getRealPath()` to identify an actual local file.

Do not change the existing upload-related service contracts as part of the basic Livewire migration.

## Results rendering

After a successful roster parse:

* Read the successful result from `ParserResultCache`.
* Build the same result data currently supplied by `ParserPageViewModel`.
* Render the existing results component.
* Preserve all export links as normal browser GET URLs.
* Do not download ICS content through a Livewire action.

## Extract another roster

Add a primary button labeled:

```text
Extract another roster
```

Its Livewire action should:

* Set `$view` to `upload`
* Reset the uploaded file
* Reset pasted text
* Clear validation errors
* Clear component-level parsing errors
* Reset temporary upload state
* Leave the previous successful cached result intact

For event-type selections, preserve the existing defaults. Codex should inspect current behavior and choose one of these explicitly:

* Preserve the latest successful result's filters when returning to upload, matching the current page fallback.
* Use an empty array only when there is no prior result/filter state.

Do not silently reset event types to an empty array when a cached result has filters.

Returning to the upload view must not delete the previous cached result. The cache should be replaced only after a later parse succeeds.

## Controller and route behavior during Phase 2

Keep the existing controller POST route temporarily:

```php
POST /parse/roster
```

It may remain as a rollback path until Livewire roster tests pass.

Do not remove:

```php
ParserController::parseFlight()
ParserController::parseHotel()
```

Do not remove their POST routes.

Keep all calendar export methods in `ParserController`.

## Phase 2 tests

Add Livewire tests for:

* Initial upload view
* File validation
* Text validation
* PDF roster parse
* Image roster parse
* Pasted roster parse
* Parse exception display
* Remaining on upload after failure
* Switching to results after success
* Rendering expected parsed data
* Rendering source, event summary, empty state, flights, and hotels
* Rendering export URLs
* “Extract another roster” returning to upload
* Form state resetting correctly
* Previous cached result surviving form reset
* Failed second parse not destroying the previous successful result
* Authorization and feature middleware behavior
* Component action authorization, including direct Livewire action calls
* Local `TemporaryUploadedFile` compatibility

## Phase 2 completion criteria

* Roster parsing works without a full-page reload.
* Successful roster parsing displays the results view.
* Failed parsing keeps the upload view visible.
* Export links still return downloadable responses.
* Flight and hotel parsing still use existing controller routes.
* Existing controller behavior remains available until the Livewire roster flow is verified.
* All existing and new tests pass.

---

# Phase 3: Remove Obsolete Flight and Hotel Parsing Endpoints

Only begin after Phase 2 is stable.

The flight and hotel POST endpoints have been reviewed and confirmed to have no external or programmatic consumers. They are not used by Blade, JavaScript, Alpine, Livewire, Apple Shortcuts, or supported external clients.

Because no active UI workflow or external integration depends on them, they should be removed rather than migrated into the `ScheduleParser` Livewire component.

## Tasks

Remove the following controller actions:

```php
ParserController::parseFlight()
ParserController::parseHotel()
```

Remove their corresponding POST routes:

```php
Route::post('/parse/flight', [ParserController::class, 'parseFlight'])
    ->name('parse.flight');

Route::post('/parse/hotel', [ParserController::class, 'parseHotel'])
    ->name('parse.hotel');
```

Remove the corresponding Form Request classes when they are no longer referenced:

```php
ParseFlightRequest
ParseHotelRequest
```

Remove tests that only verify the obsolete endpoints.

Before deleting shared code, confirm that it is not used by roster parsing, exports, or other parser workflows.

Review whether these removals make any controller helpers unused, including:

```php
handleParseAction()
```

Do not remove `handleParseAction()` during this phase if `parseRoster()` still depends on it. Its final removal should occur only after roster parsing has fully moved to Livewire.

Remove unused imports, route-name references, documentation, and dead code associated with the deleted endpoints.

Do not add replacement Livewire actions or new flight and hotel forms.

## Phase 3 Completion Criteria

* `parseFlight()` and `parseHotel()` are removed from `ParserController`.
* The `parse.flight` and `parse.hotel` POST routes are removed.
* `ParseFlightRequest` and `ParseHotelRequest` are removed when no longer referenced.
* Endpoint-specific tests are removed or updated.
* No Livewire replacement actions are introduced.
* Roster parsing continues to work through Livewire.
* Calendar export routes and actions remain unchanged.
* Shared parser services remain intact unless confirmed unused.
* The route list contains no obsolete flight or hotel parsing endpoints.
* The full test suite passes.
* Formatting and configured static-analysis checks pass.

---

# Phase 4: Remove Obsolete Controller Actions and Routes

Only begin after all applicable Livewire parsing tests pass and Phase 3 records an explicit decision for the roster, flight, and hotel endpoints independently.

## Remove controller actions

Remove the roster controller action only after the Livewire roster flow has passed its rollback period and no supported programmatic consumer requires it:

```php
ParserController::parseRoster()
```

Remove `ParserController::parseFlight()` or `ParserController::parseHotel()` only when Phase 3 explicitly classifies that endpoint as obsolete or confirms an equivalent Livewire migration with no external/programmatic consumer. Preserve controller endpoints selected for continued HTTP support.

Remove private controller helpers only when no remaining controller action uses them, including:

```php
handleParseAction()
```

Do not remove helpers still required by export actions or page rendering.

## Remove POST routes

Remove only the POST routes corresponding to controller actions approved for removal:

```php
Route::post('/parse/roster', ...);
```

The flight and hotel POST routes are conditional on the Phase 3 consumer decision. Do not remove them merely because there is no Blade form.

## Keep export routes

Keep calendar exports as standard GET controller routes:

```text
GET /parse/export
GET /parse/export/event/{eventId}
GET /parse/export/event/{eventId}/duty
```

Use the project’s actual route path and route name for the flight-duty export.

The controller remains responsible for:

* Full-calendar export
* Individual-event export
* Flight-duty event export
* Resolving cached events for download
* Returning downloadable HTTP responses

## Final cleanup

After route removal:

* Remove unused imports.
* Remove unused Form Requests only if no other code uses them.
* Remove obsolete old-input handling.
* Remove obsolete session-flash handling.
* Update route tests.
* Update architecture or feature documentation.
* Run formatting and static analysis.
* Run the full test suite.

---

# Service Review

Before moving actions, inspect:

```text
HandleParseExecution
ParserResultCache
JcaScheduleParsingService
ParserCalendarExportService
```

Confirm that core service behavior does not rely on:

* `redirect()`
* `back()`
* Controller instances
* Route names
* Direct `Request` access
* Session flash messages
* View rendering
* HTTP responses, except inside the export service
* Form Request instances

Review uploaded-file assumptions, especially whether methods require:

```php
Illuminate\Http\UploadedFile
```

or can also accept Livewire temporary uploaded files.

Do not change service contracts speculatively. Make the smallest compatibility change required and cover it with tests.

`ParserCalendarExportService` may legitimately return HTTP responses because exports remain controller-driven.

---

# Cache Rules

Use the following behavior:

* Keep the latest successful parsed result in the cache.
* Do not clear it when “Extract another roster” is selected.
* Do not replace it when validation fails.
* Do not replace it when parsing throws an exception.
* Replace it only after a new parse completes successfully.
* Treat a completed zero-event parse as successful under current behavior unless product requirements explicitly change this before implementation.
* Preserve the session-scoped latest-result behavior during the UI migration.
* Do not claim or assume cross-user isolation: the current global `parsed_results:{parseKey}` fallback has no user ownership check.
* Keep cache ownership remediation separate from the request-lifecycle refactor unless explicitly approved.

Add a two-session/user characterization test before implementation. Decide whether parse keys are intentional bearer identifiers or whether exports must be session/user-owned. If ownership changes are approved, revise the cache DTO/key/export plan and tests before Phase 2.

# Pre-implementation decisions

Do not begin Phase 2 until these decisions are recorded:

- [x] Confirm the production Livewire temporary upload disk is local - it is local
- [x] Decide whether global parse-key export access must enforce session/user ownership.
- [x] Confirm that successful zero-event parses continue replacing the latest successful result. - it should not
- [x] Decide whether pasted-roster text should be restored as a visible control in Phase 2. - It should not
- [x] Identify any external/programmatic consumers of flight and hotel POST endpoints before adding, migrating, or removing their UI/actions. - No external consumers. Can be removed

---

# Non-Goals

This task does not include:

* Migrating the application to React or Inertia
* Redesigning the parser UI
* Rewriting parsing algorithms
* Replacing the cache implementation
* Changing calendar file formats
* Changing authorization policy
* Moving export downloads into Livewire
* Introducing a client-side global store
* Converting all Blade interactions to Alpine
* Refactoring unrelated dashboard functionality

---

# Final Acceptance Criteria

* The parser page contains an upload state and a results state.
* Livewire conditionally renders the substantial upload and results sections.
* Parsing does not cause a full-page reload.
* Successful parsing automatically renders the results view.
* Validation and parsing failures leave the user on the upload view.
* Existing validation messages and constraints remain intact.
* “Extract another roster” returns to a clean upload form.
* The latest successful cached result remains available until another parse succeeds.
* Failed subsequent parses do not destroy the previous successful result.
* Full-calendar and event export URLs continue to work.
* Calendar exports remain standard GET controller responses.
* Parsing, tracking, cache, and export services remain the source of business logic.
* Alpine is limited to small browser-only interactions.
* Authorization and feature-gate behavior remain unchanged.
* Obsolete POST routes are removed only after equivalent Livewire tests pass.
* The full test suite, formatter, and configured static-analysis tools pass.



## 3. ✅ Spell out Jeppesen Crew Access
- Should use JCA acryonm only after spelling out Jeppesen Crew Access
- Reference:
<header className="mb-10 text-center">
  <span className="text-xs font-bold tracking-widest uppercase text-[#C5A059] block mb-2">
    Jeppesen Crew Access
  </span>
  <h1 className="text-4xl md:text-5xl font-black tracking-tight text-[#1B365D] mb-4">
    Schedule Extractor
  </h1>
  <p className="text-base text-[#4A5568] max-w-md mx-auto leading-relaxed">
    Upload a roster screenshot or trip PDF to instantly convert your schedule into calendar-ready events.
  </p>
</header>

## 4. Streamline the Upload Card Structure
- The file upload input and the "Parse" button feel somewhat detached because they are aligned horizontally with wide gaps.

- Fix: Consider stacking the input elements or tightening the container width. 

- Alternatively, transform the upload zone into a larger, centered drag-and-drop target box with an icon, placing a full-width or cleanly aligned "Parse" button directly beneath it.

## 5. Refine Card Hierarchy and Margins
 - The main blue header card ("Flight Deck") and the white upload card are stacked close together with identical widths, creating a rigid block appearance.

 - Fix: Nest the file upload and filters section inside a single, unified container card, where the dark blue header serves as the hero header of that card. This removes the double-card stacking look and groups the context ("what this tool does") directly with the action ("upload your file").

- Spacing: Increase the vertical spacing (gap or margin-bottom) between the hero header card and the upload card if you keep them separate.

## 6. Improve Grid/Flex Alignment
- Filters Section: The "Filters" label and the "Show options" dropdown toggle are pushed to the extreme edges of the container. If a user expands "Show options," the checkboxes will likely appear far away from the initial visual anchor. Aligning these elements or placing the filter options directly in a collapsible accordion that spans a more readable, centered width would feel more cohesive.

- Alignment: Ensure the text inside the "Choose File" button box vertically aligns perfectly with the text baseline of the "Parse" button.

## 7. Navbar Typography
- The navbar items ("Parse Schedule", "Route Extractor", etc.) are quite close to the top edge of the viewport. Adding a bit more top and bottom padding to the navbar container will give the text room to breathe and look cleaner.

## 8. Install laravel debug bar

## Prep for rename
- Rename ParserEventType Enum to ScheduleEventType.php
- Add 2 folders in Enum: Schedule and 

## 9. Organize services
- Create directory framework
  cd app/Services
  mkdir -p Services/{Schedule/ Extractor,FlightPlan/ Extractor,Calendar,Infrastructure}
- Move files:
  - Schedule Domain
  git mv Parsers/JcaScheduleParsingService.php Schedule/JcaScheduleProcessor.php
  git mv ScheduleInputResolver.php Schedule/ScheduleInputResolver.php
  git mv Extractors/SchedulePdfExtractor.php Schedule/PdfTextExtractor.php
  git mv Parsers/CrewListParser.php Schedule/ Extractor/CrewListParser.php
  git mv Parsers/PublishedRosterParser.php Schedule/Extractor/PublishedRosterParser.php
  git mv Parsers/ScheduleFormatParser.php Schedule/Extractor/ScheduleFormatParser.php
  git mv Parsers/TripInformationParser.php Schedule/Extractor/TripInformationParser.php

  - FlightPlan Domain
  git mv Extractors/FlightRouteExtractor.php FlightPlan/Extractor/FlightRouteExtractor.php
  - Note: Create your FlightReleaseProcessor.php entry point here if it's new

  - Calendar Domain
  git mv Calendar/IcsCalendarService.php Calendar/IcsGenerator.php
  git mv Calendar/FlightDutyCalendarEventService.php Calendar/FlightDutyEvent.php
  git mv Calendar/ParserCalendarExportService.php Calendar/ExportPayload.php

  - Clients (Keep folder, fix name)
  git mv Clients/AirlineCodeLookup.php Clients/AirlineCodeLookupClient.php

  - Infrastructure
  git mv Infrastructure/ParseRequestLogger.php Infrastructure/ScheduleRequestLogger.php
  git mv Infrastructure/ParserResultCache.php Infrastructure/EngineResultCache.php

- Clean up legacy directories
  - find Parsers Extractors -type f
  - rmdir Parsers Extractors
- Update namespaces and references
- Fix service-provider bindings and controllers
- Run a global search in your IDE for App\Services\. Update any locations where these classes were typed, imported (use), or bound in your AppServiceProvider.php.
- Flush cache
- Test

app/Services/
├── Schedule/
│   ├── JcaScheduleProcessor.php      # (Was JcaScheduleParsingService) Coordinates
│   ├── ScheduleInputResolver.php   # Deals directly with raw inputs/requests
│   └── Extractor/                    # Internal text sub-parsers used by the engine
│       ├── CrewListParser.php
│       ├── PdfTextExtractor.php   # (Was SchedulePdfExtractor) Low-level PDF tool
│       ├── PublishedRosterParser.php
│       ├── ScheduleFormatParser.php
│       └── TripInformationParser.php
│
├── FlightPlan/
│   ├── FlightReleaseProcessor.php      # Main entry point for the route engine
│   └──  Extractor/
│       └── FlightRouteExtractor.php       # text parser
│
├── Calendar/
│   ├── IcsGenerator.php  # (Was IcsCalendarService) Handles raw .ics payload syntax
│   ├── FlightDutyEvent.php   # Was FlightDutyCalendarEventService
│   └── ExportPayload.php        # (Was ParserCalendarExportService) Wraps data ready for client delivery
│
├── Clients/
│   ├── AirportLookupClient.php
│   └── AirlineCodeLookupClient.php     # Fixed name consistency (Added "Client")
│
└── Infrastructure/
    ├── ScheduleRequestLogger.php              # (Was ParseRequestLogger)
    └── EngineResultCache.php           # (Was ParserResultCache)

## 10. Use a descriptive footer disclaimer in Parse schedule tool:
- To safely clarify your tool's relationship to the platform, add a small, subtle line of text at the very bottom of your application page layout:

Disclaimer: This tool is an independent utility built for crew convenience and is not affiliated with, authorized, or endorsed by Jeppesen or Boeing.

## 11. One line status message
- In schedule extractor, shorten status codes
- System online: Ready to process
- 0.3 MB image selected, ready to upload

## 12. Resolve Larastan findings (5 errors remaining at level 5)

Run with `vendor/bin/sail bin phpstan analyse --no-progress`. Keep Larastan in `require-dev` and fix root causes rather than adding a baseline or blanket `ignoreErrors` entries.

### Low — safe cleanup after contract fixes

- [ ] Remove redundant `array_values()` calls on values already typed as lists in `app/DTOs/ParsedEventDTO.php:165`, `app/Mappers/DutyEventMapper.php:124`, `app/Mappers/FlightMapper.php:182`, and `app/Services/RosterParser.php:622` (4 errors).
- [ ] Remove `app/Services/RosterParser.php:751::firstMatchingLine()` if repository-wide usage confirms it is dead code (1 error).

After each cluster, run the focused PHPUnit tests and Larastan again. Finish with `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail bin phpstan analyse --no-progress`, and the relevant parser/auth/view-model test files.
