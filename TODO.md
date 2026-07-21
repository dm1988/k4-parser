# Goal

Replace the current request → controller → redirect parser workflow with a single Livewire-powered page that transitions between:

1. An upload/input view
2. A parsed-results view

Use Livewire for server state, validation, parsing, and rendering. Use Alpine only for small browser-side interactions within each view.

# Current Task - init Phase 3

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

## Status: Complete

Completed on 2026-07-21.

* Confirmed the existing `components/parser/form.blade.php` and `components/parser/result.blade.php` files already provide the required upload/results separation; no markup move was necessary.
* Added `ParserValidationRules` as the shared, HTTP-independent validation definition used by all three existing Form Requests.
* Added HTTP upload, lifecycle retention, empty-result, export failure, authentication, verification, tab behavior, and cross-user parse-key characterization coverage.
* Preserved all controller actions, routes, redirects, old-input behavior, cache behavior, export links, and Blade markup.
* Verified with Pint and the parser-filtered test suite: 75 tests passed with 521 assertions.


# Phase 2: Add Livewire Roster Extracting

Migrate only the main roster parser in this phase.

Do not migrate flight or hotel parsing yet.

## Status: Complete

Completed on 2026-07-21.

* Added the class-based `App\Livewire\ScheduleExtractor` component with local temporary upload support.
* Moved visible roster validation, parsing, loading state, error rendering, cache restoration, and upload/results transitions into Livewire.
* Kept public component state limited to form values, view state, and a locked parse key; result view models are rebuilt from `ParserResultCache` during rendering.
* Added explicit authentication, verification, feature, and gate enforcement to component actions.
* Preserved the roster controller POST route as a rollback path and left flight, hotel, and calendar export routes unchanged.
* Kept calendar downloads as normal controller-backed GET links.
* Added “Extract another roster” without clearing the latest successful cached result or selected filters.
* Empty parses now stay on upload with a visible error and do not replace the latest successful result.
* Removed the obsolete Alpine parser submit-state module after replacing it with Livewire upload/action loading state.
* Verified Pint, the frontend production build, 75 parser regression tests with 521 assertions, and the full suite with 258 tests and 1,463 assertions.
* Larastan reports only the five pre-existing findings already tracked under “Resolve Larastan findings”; no new Phase 2 finding was introduced.

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

## ScheduleExtractor.php: mount() depends on session errors

This logic:

$this->view = $result !== null && ! session()->has('errors')
    ? self::VIEW_RESULTS
    : self::VIEW_UPLOAD;

This makes sense during the transitional phase while the old controller POST route still redirects back. But after the controller workflow is removed, Livewire validation errors do not require remounting the component.

That means this is transitional compatibility code and should be marked for Phase 4 cleanup.

Also, session()->has('errors') can reflect unrelated page validation errors. It would be better to inspect whether the error bag belongs to this parser form, if possible.

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

- Fix: Transform the upload zone into a larger, centered drag-and-drop target box with an icon, placing a full-width or cleanly aligned "Parse" button directly beneath it.

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
