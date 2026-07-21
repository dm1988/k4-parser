# Goal

Replace the current request → controller → redirect parser workflow with a single Livewire-powered page that transitions between:

1. An upload/input view
2. A parsed-results view

Use Livewire for server state, validation, parsing, and rendering. Use Alpine only for small browser-side interactions within each view.

# Current Task

## 2. Refactor Schedule Parser into a Single-Page Livewire Component

Implement this refactor incrementally. Each phase must leave the parser functional and testable.

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

1. Inspect `dashboard.blade.php`.
2. Document every value it reads from `ParserPageViewModel`.
3. Split the existing markup into separate partials or Blade components:

   * Upload form
   * Results display
   * Shared parser messages, when applicable
4. Continue rendering both partials through the existing controller-provided view model.
5. Preserve all current form actions, routes, validation output, and export links.

Suggested structure:

```text
resources/views/
├── dashboard.blade.php
└── components/parser/
    ├── upload-form.blade.php
    └── results.blade.php
```

Adapt the location to existing project conventions.

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

* PDF roster submission
* Image roster submission
* Pasted roster submission
* Invalid or empty submission
* Parse-source resolution failure
* Successful result rendering
* Full-calendar export
* Individual-event export
* Flight-duty export
* Flight parse submission
* Hotel parse submission

## Phase 1 completion criteria

* The parser behaves exactly as it did before.
* Upload and results markup are separated.
* Existing POST parsing routes remain active.
* Existing controller methods remain active.
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

public string $text = [];

/** @var array<int, string> */
public array $eventTypes = [];
```

Correct the `text` property to a string during implementation:

```php
public string $text = '';
```

Supported view values:

```php
'upload'
'results'
```

Prefer constants or an enum only when that matches existing project conventions. Do not introduce an enum solely for two internal values.

## Parsed-result state

Before storing `ParserPageViewModel` as a public Livewire property, inspect whether it is safely serializable.

Do not place arbitrary service objects, response objects, collections with unsupported values, or non-Wireable DTOs in public Livewire state.

Use one of these approaches:

1. Rebuild the view model from `ParserResultCache` during rendering.
2. Store only simple arrays and scalar identifiers in Livewire state.
3. Make the DTO explicitly Livewire-compatible if the project already follows that pattern.

Prefer rebuilding the display data from the cache unless there is a clear performance reason not to.

## Initialization behavior

On component mount:

* Read the latest cached result.
* Default to the upload view unless the existing page intentionally restores the latest results.
* Preserve existing old-input behavior only where still relevant.
* Do not clear a previous successful result merely by visiting or refreshing the page.

Codex must inspect current behavior before choosing whether an existing cached result initially opens the results view.

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

Do not duplicate parsing, logging, execution tracking, or cache-storage logic inside the Livewire component.

## Validation

Start with validation rules equivalent to `ParseRosterRequest`.

Prefer sharing validation rules between the Form Request and Livewire rather than maintaining two independent rule sets.

A shared rules object, validator class, or static rules method may be introduced when it improves clarity. Do not make the Livewire component instantiate or depend directly on an HTTP Form Request.

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

* Restore default event-type selections
* Preserve the user’s most recent selections

Do not silently reset event types to an empty array unless that is the existing default.

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
* Rendering warnings
* Rendering export URLs
* “Extract another roster” returning to upload
* Form state resetting correctly
* Previous cached result surviving form reset
* Failed second parse not destroying the previous successful result
* Authorization and feature middleware behavior

## Phase 2 completion criteria

* Roster parsing works without a full-page reload.
* Successful roster parsing displays the results view.
* Failed parsing keeps the upload view visible.
* Export links still return downloadable responses.
* Flight and hotel parsing still use existing controller routes.
* Existing controller behavior remains available until the Livewire roster flow is verified.
* All existing and new tests pass.

---

# Phase 3: Migrate Flight and Hotel Parsing

Only begin after Phase 2 is stable.

## Tasks

Move these controller actions into the Livewire component:

```php
parseFlight()
parseHotel()
```

Each Livewire action must preserve the behavior currently provided by its corresponding Form Request and controller action.

Refactor shared execution logic only after all three Livewire actions are visible and duplication is clear.

A private method may centralize:

* Calling `HandleParseExecution`
* Handling `ParseSourceResolutionException`
* Refreshing cached result state
* Switching to the results view

Do not create an overly generic parser abstraction that obscures the differences between roster, flight, and hotel inputs.

## Phase 3 completion criteria

* Roster, flight, and hotel parsing work through Livewire.
* None of the three workflows performs a full-page redirect.
* All three workflows use existing application services.
* All three workflows have equivalent or improved test coverage.

---

# Phase 4: Remove Obsolete Controller Actions and Routes

Only begin after all Livewire parsing tests pass.

## Remove controller actions

Remove:

```php
ParserController::parseRoster()
ParserController::parseFlight()
ParserController::parseHotel()
```

Remove private controller helpers only when no remaining controller action uses them, including:

```php
handleParseAction()
```

Do not remove helpers still required by export actions or page rendering.

## Remove POST routes

Remove:

```php
Route::post('/parse/roster', ...);
Route::post('/parse/flight', ...);
Route::post('/parse/hotel', ...);
```

## Keep export routes

Keep calendar exports as standard GET controller routes:

```text
GET /parse/export
GET /parse/export/event/{eventId}
GET /parse/export/flight-duty/{eventId}
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
* Continue scoping cache access according to the current user/session rules.
* Preserve existing authorization and cross-user isolation.

Add a test for two browser sessions or users when the cache implementation could allow result leakage.

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
