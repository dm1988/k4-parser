# Livewire Schedule Parser Audit

Audit date: 2026-07-21  
Branch at audit time: `refactor/livewire-schedule-parser`  
Scope: audit and preparation only; no application behavior, routes, or service contracts changed.

## Finding labels

- **Confirmed** means observed in repository code, installed package code, route output, or targeted tests.
- **Assumption** means a deployment/runtime detail not fixed by repository configuration.
- **Recommendation** means proposed future work and is not current behavior.

## 1. Relevant file inventory

### Request lifecycle and domain services

| File | Confirmed responsibility |
|---|---|
| `routes/web.php` | Defines both parser page routes, three parse POST routes, and three calendar export routes with auth, verification, feature, and gate middleware. |
| `app/Http/Controllers/ParserController.php` | Builds the page view model, accepts the three Form Requests, invokes parsing/logging, translates `ParseSourceResolutionException` into redirect errors, and delegates exports. |
| `app/Http/Requests/ParseRosterRequest.php` | Validates upload/text source and event filters. |
| `app/Http/Requests/ParseFlightRequest.php` | Validates required flight text. |
| `app/Http/Requests/ParseHotelRequest.php` | Validates required hotel text. |
| `app/Actions/HandleParseExecution.php` | Wraps a parser operation with parse-request logging and rethrows failures. |
| `app/Actions/BuildParserResult.php` | Creates `ParserResultData`, generates a parse ULID, and attaches stable event download ULIDs. |
| `app/Services/JcaScheduleParsingService.php` | Performs roster/flight/hotel parsing and writes successful results to cache. |
| `app/Services/ScheduleInputResolver.php` | Resolves pasted text, PDF text, or image OCR from an `UploadedFile` local path. |
| `app/Services/SchedulePdfExtractor.php` | Extracts PDF text from a local file path. |
| `app/Services/ScheduleFormatParser.php` | Dispatches resolved schedule text to the detected format parser. |
| `app/Services/TripInformationParser.php` | Parses trip text, flights, and hotels. |
| `app/Services/PublishedRosterParser.php` | Parses published-roster text. |
| `app/Services/ParserResultCache.php` | Normalizes and stores results; resolves latest/session/query-key results. |
| `app/Services/ParserCalendarExportService.php` | Filters/fetches cached events and returns ICS HTTP responses. |
| `app/Services/IcsCalendarService.php` | Serializes events into ICS content. |
| `app/Services/ParseRequestLogger.php` | Creates and updates `ParseRequest` audit records and application logs. |
| `app/Exceptions/ParseSourceResolutionException.php` | Wraps source-resolution failures with field-error messages. |
| `app/DTOs/ParserResultData.php` | Cache/result boundary DTO. |
| `app/Enums/ParserEventType.php` | Defines accepted filter values and display metadata. |
| `app/Enums/ScheduleDocumentType.php` | Defines supported PDF document types and parser types. |

### Page/view layer

| File | Confirmed responsibility |
|---|---|
| `resources/views/dashboard.blade.php` | Parser page composition and unavailable state. Both `/dashboard` and `/parse` render this view. |
| `app/View/Models/Parser/ParserPageViewModel.php` | Combines result state, old input, filter options, and feature availability. |
| `app/View/Models/Parser/ParserResultViewModel.php` | Builds renderable events, airport enrichment, summary values, raw JSON, and export URLs. |
| `app/View/Models/Parser/ParserEventViewModel.php` | Formats non-flight event display and per-event URL. |
| `app/View/Models/Parser/FlightCardViewModel.php` | Formats flight cards and authorization-sensitive duty-export URLs. |
| `resources/views/components/parser/form.blade.php` | Current roster upload form and Alpine progress/status UI. Contains a commented-out pasted-text fallback. |
| `resources/views/components/parser/result.blade.php` | Result summary, full export, event cards, errors, empty state, and admin raw JSON. |
| `resources/views/components/parser/event-card.blade.php` | Non-flight event rendering and event export link. |
| `resources/views/components/parser/flight-card.blade.php` | Flight rendering, individual export, and conditional duty export. |
| `resources/views/components/parser/flight-card/*.blade.php` | Accordion, dropdown, and airport popover components. |
| `resources/views/parser/partials/flight-card/*.blade.php` | Flight-card detail partials. |
| `resources/js/parser-form.js` | Current submit-state and timed status messages. |
| `resources/js/app.js` | Registers the `parserForm` Alpine component and starts Alpine. |
| `resources/views/layouts/app.blade.php` | Application layout; includes Vite assets but no explicit Livewire directives. |

**Confirmed:** there are no flight-parse or hotel-parse forms in `resources/views`; only their routes, controller methods, request classes, and tests exist. There is no `app/Livewire`, `resources/views/livewire`, or application-authored Livewire component. Existing Livewire usage is Filament and its PHPUnit tests.

### Configuration, middleware, and authorization

- `bootstrap/app.php`: aliases `feature` to `EnsureFeatureIsEnabled`.
- `app/Http/Middleware/EnsureFeatureIsEnabled.php`: returns 404 when a configured feature is disabled.
- `app/Providers/AppServiceProvider.php`: defines `use-schedule-parser` and `export-schedule-parser-duty` gates.
- `app/Models/User.php`: implements feature/gate capability methods.
- `config/features.php`: schedule-parser and duty-export feature settings.
- `config/filesystems.php`: default disk is locally configured unless overridden by the environment.
- No published `config/livewire.php` exists. Installed Livewire defaults therefore apply unless altered at runtime.

### Parser-focused tests

- Feature: `ParseUploadTest`, `RosterParserTest`, `ParserRequestValidationTest`, `FeatureRouteAuthorizationTest`, `ParserResultComponentTest`, `EventCardComponentTest`, `FlightCardComponentTest`, `ExportFlightDutyCalendarEventTest`, and the parser hydration case in `AdminNavigationTest`.
- Unit: `ParserResultCacheTest`, `BuildParserResultTest`, `ScheduleInputResolverTest`, `ScheduleFormatParserTest`, `TripInformationParserTest`, `PublishedRosterParserTest`, `IcsCalendarServiceTest`, `FlightDutyCalendarEventServiceTest`, parser DTO/view-model tests, and `ParserPageViewModelTest`.

## 2. Current request lifecycle

### Parser page

**Confirmed:** authenticated, verified users reach either `GET /dashboard` (`dashboard`) or `GET /parse` (`parse.index`). Both call `ParserController::parserPage()`, which reads `ParserResultCache::latest()`, reads `session()->getOldInput()`, builds `ParserPageViewModel`, and renders `dashboard`.

The page GET routes deliberately do not have the `feature:schedule_parser` or `can:use-schedule-parser` middleware. Instead, `ParserPageViewModel::$available` calls `User::canUseScheduleParser()`, and the Blade view renders an unavailable panel when false.

### Roster file upload

1. `resources/views/components/parser/form.blade.php` posts multipart data to `parse.roster`; accepted browser hints are PDF and `image/*`.
2. Route middleware runs in this order/context: `web`, then group `auth`, `verified`, then `feature:schedule_parser`, `can:use-schedule-parser`.
3. `ParseRosterRequest` validates file/text and filters.
4. The controller obtains `$request->file('file')`, categorizes its MIME as `pdf` only for `application/pdf`, otherwise `image`, and calls `HandleParseExecution`.
5. `ParseRequestLogger::start()` hashes and sizes the local upload before parsing.
6. `JcaScheduleParsingService::parseRoster()` asks `ScheduleInputResolver` to resolve the source. PDF extraction and OCR both use a real local path; the uploaded source itself is not permanently stored by this flow.
7. Format parsing, optional event filtering, result construction, event ULIDs, and cache writes occur.
8. The parse audit row is marked successful, then the controller redirects back.

### Pasted roster text

**Confirmed:** the HTTP endpoint supports pasted text through the same roster route. With no file, the controller labels the log source `pasted_text`; the resolver returns source `text`, and the rest of the successful flow is identical. The actual text textarea is currently inside a Blade comment, so this is endpoint/test-supported but not available in the rendered page UI.

### Flight parsing

`POST /parse/flight` validates required string `text`, logs it as `pasted_text`/`unknown`, extracts flight DTOs, builds a `flight`/`text` result, caches it, marks the parse request successful, and redirects back. There is no rendered flight form today.

### Hotel parsing

`POST /parse/hotel` follows the same flow, extracts only hotels/layovers, builds a `hotel`/`text` result, caches it, and redirects back. There is no rendered hotel form today.

### Successful parse

**Confirmed:** all three successful service methods call `ParserResultCache::put()` before returning. The cache service writes two TTL entries and updates `session('latest_parse_key')`. No `result` payload is flashed to session. Redirect-back issues a new GET, and the page rebuilds the complete result view model from cache.

An empty successful parse is still successful: it creates/caches a new result and replaces `latest_parse_key`. The result panel appears, `exportUrl` is null, and the view displays “No calendar events matched the current filters.”

### Validation failure

Form Request validation occurs before the controller. Laravel redirects to the previous URL, flashes validation errors and old input, and does not invoke parsing or cache writes. On the next GET, `ParserPageViewModel` restores `text` and valid selected event types from old input while independently restoring the previous latest cached result. File inputs cannot be restored by browser/session old input.

The upload form renders field errors for `file`, `text` (only in the currently commented block), `event_types`, and `event_types.*`. No shared top-level validation summary exists.

### `ParseSourceResolutionException`

`JcaScheduleParsingService::parseRoster()` catches any throwable from source resolution and wraps it. `HandleParseExecution` records a failed parse and rethrows it. The controller catches only this exception and returns `back()->withInput()->withErrors($exception->errors())`. The error key is always `file`, including pasted-text resolution failures. No result is cached by this failure path, so the previous latest result remains.

Other parser exceptions are logged by `HandleParseExecution` and rethrown to Laravel; the controller does not convert them into form errors.

### Full calendar export

The result view model builds `parse.export` with the result's `parse_key` and stored filter values. `GET /parse/export` is guarded by auth, verification, schedule-parser feature, and parser capability. `ParserResultCache::resolveForRequest()` prefers query `parse_key`, falls back to session latest, and the controller 404s for a missing result/events key. The export service reapplies requested event-type filters, 404s when no events remain, serializes ICS, and returns an attachment response.

### Individual event export

Each event gets a stable download ULID during result construction. Rendered URLs contain the event ID and parse key. The guarded endpoint resolves the cached result, locates the event by `download_id` rather than array index, 404s if absent, and returns a one-event ICS attachment.

### Flight-duty event export

The flight card shows this link only when `canExportScheduleParserDuty()` is true and all required event/local duty timestamps exist. Its route has auth and verification plus the schedule-parser feature and the more specific `can:export-schedule-parser-duty` gate; it does not use the general `can:use-schedule-parser` gate. The service finds the event, delegates to `ExportFlightDutyCalendarEvent`, 404s for an ineligible event, and returns a duty ICS attachment.

### Page refresh after success

A refresh performs only the page GET. The session's `latest_parse_key` selects the cached result until its configured TTL expires (default `cache.parsed_results_ttl` fallback: 60 minutes), the session changes, the key is overwritten by another successful parse, or the cache entry disappears. Refresh does not reparse and does not delete the result.

## 3. View-model dependency inventory

### Values used directly by `dashboard.blade.php` and `parser/form.blade.php`

| Property/method | Type and source | Shape | Livewire state assessment |
|---|---|---|---|
| `available` | `bool`; current authenticated user's `canUseScheduleParser()` | Scalar | Safe as a derived/render value, but should be recomputed/authorized server-side, not trusted as mutable public state. |
| `hasResult()` | `bool` method derived from nullable `result` | Scalar/derived | Safe to derive each render. |
| `result` | `?ParserResultViewModel`; built from cached `ParserResultData` | View-model object containing DTOs | Do not expose as mutable public Livewire state. Rebuild from `ParserResultCache` using a scalar parse key. |
| `selectedTypes` | `list<string>`; valid old `event_types`, else cached result filters, else empty | Array | Safe public form state after validation. |
| `filterOptions` | list of `{value,label,description}` from `ParserEventType::filterable()` | Array/enum-derived | Safe but preferably computed in `render()` rather than client-hydrated state. |
| `text` | `string`; session old input or empty | Scalar | Safe public form state. Note the only current binding is commented out. |

### Values used by `parser/result.blade.php` and child cards

| Property/method | Type and source | Shape | Livewire state assessment |
|---|---|---|---|
| `result.errorMessage`, `hasError()` | `?string` and derived bool; `ParserResultData::$error` | Scalar | Serializable, but current successful services never create error results. Prefer rebuild from cache. |
| `result.sourceLabel` | `string`; ucfirst of cached `source` | Scalar | Derived; rebuild. |
| `result.tripNumber` | `string`; cached `parsed.trip.trip_number`, default `Pending` | Scalar | Derived; rebuild. |
| `result.eventCount` | `int`; count of renderable mapped events | Scalar | Derived; rebuild. |
| `result.events` | list of `ParserEventViewModel`, `Flight`, or `DutyEvent`; mapped/enriched from cached `parsed.calendar_events` | Array of DTO/view-model objects | Not suitable as primary mutable public state. Rebuild from cache. It includes generated URLs and airport lookup enrichment. |
| `result.parseKey` | `?string`; cached result ULID | Scalar | Safe public/locked scalar if needed, but should be checked through cache lookup on each action. |
| `result.exportUrl` | `?string`; named route with filters and parse key; null for no renderable events or no key | Scalar/derived | Rebuild, so route/gate/filter context remains server-derived. |
| `result.rawJson` | `string`; JSON serialization of cached DTO | Scalar/derived, potentially large | Do not carry as public state; rebuild only for authorized admin rendering. |
| Flight objects | `App\DTOs\Flight` from cache mapping | DTO | Render-only object; rebuild. Child uses many fields plus `FlightCardViewModel` methods. |
| Non-flight objects | `ParserEventViewModel` or `DutyEvent` | View model/DTO | Render-only; rebuild. |
| Flight-card model | `FlightCardViewModel` created in Blade | View model holding `Flight` and auth-derived methods | Never public Livewire state; construct for rendering. |

**Confirmed warning/source behavior:** there is no dedicated warnings property in `ParserPageViewModel`, `ParserResultViewModel`, `ParserResultData`, or the parser result Blade. Source information exposed in the result is only `sourceLabel`; `documentType`, MIME, and metadata remain available in cached/raw JSON but have no normal result UI field.

**Confirmed flights/hotels behavior:** no separate page-level flights or hotels collection exists. They are entries within `parsed.calendar_events`, mapped into the heterogeneous `result.events` list. Hotels normally render as non-flight event view models.

**Recommendation:** keep only form inputs, transient UI status, and a locked/current parse-key scalar as Livewire public state. Resolve `ParserResultData` from `ParserResultCache` and rebuild `ParserPageViewModel`/`ParserResultViewModel` on render. This avoids hydrating heterogeneous service/view objects through the browser and preserves current route-generated export links.

## 4. Service compatibility findings

| Service | Confirmed HTTP/framework dependencies | Livewire compatibility finding |
|---|---|---|
| `HandleParseExecution` | `Illuminate\Http\UploadedFile`; no Request, Form Request, redirect, session flash, view, route helper, or response dependency. Persists a `ParseRequest` model and writes logs through `ParseRequestLogger`. | Callable directly after Livewire validation if the uploaded object satisfies the existing `UploadedFile` type. |
| `ParserResultCache` | `Illuminate\Http\Request` only in `resolveForRequest()`; session helper in `put()`, `latest()`, namespace generation; Cache facade. No redirects/views/responses. | `put()`, `get()`, and `latest()` are usable. A component should not fabricate a Request; use `get(parseKey)`/`latest()`. |
| `JcaScheduleParsingService` | `UploadedFile` type on roster parsing; no Request/Form Request/controller/redirect/view/route/response dependency. Calls cache/session indirectly through `ParserResultCache`. | Flight/hotel paths are directly compatible. Roster upload is compatible only under the local-file conditions below. |
| `ParserCalendarExportService` | Returns `Illuminate\Http\Response`, uses `response()` and `abort(404)`; no Request/Form Request/session/view/controller/route helper dependency. | Keep existing HTTP export endpoints/links in the first Livewire phase. It is intentionally response-oriented and need not move into component state. |

Additional confirmed dependencies:

- `ScheduleInputResolver` and `ParseRequestLogger` require `UploadedFile` and immediately inspect a real local path.
- `ParserResultViewModel`, `ParserEventViewModel`, and `FlightCardViewModel` use `route()`; the result model also resolves mapper/services with `app()` and performs airport lookups.
- `ParserResultCache::put()` is the only successful-result session mutation (`latest_parse_key`, and lazily `parsed_results_namespace`).
- None of the four requested services calls `redirect()`, `back()`, flashes errors/status, or renders a view.

## 5. Upload compatibility findings

**Confirmed from installed Livewire 4.3.3 code:** `Livewire\Features\SupportFileUploads\TemporaryUploadedFile` extends `Illuminate\Http\UploadedFile`. It implements `getMimeType()`, `getSize()`, and `getRealPath()`. Therefore it satisfies the existing controller/action/service type declarations without changing those declarations.

**Confirmed repository condition:** no published Livewire config overrides the temporary disk. Livewire defaults its temporary disk to the application default, and `config/filesystems.php` defaults that disk to local storage. Under that repository-default local configuration, the temporary file has a real local path and can pass through `HandleParseExecution` → `ParseRequestLogger` → `JcaScheduleParsingService` → `ScheduleInputResolver` during the same Livewire action.

**Blocker/conditional:** `ScheduleInputResolver` requires `is_file($file->getRealPath())`, and PDF/OCR libraries consume that local path. A Livewire temporary upload on S3/another non-local disk is not confirmed compatible; Livewire file validation may work, but this parser flow cannot be called safely without first materializing a local file or changing the service boundary. Environment values were intentionally not inspected, so production disk selection remains an assumption requiring deployment confirmation.

**Temporary lifetime:** Livewire's installed default accepts uploads for five minutes and cleans uploads older than 24 hours. The parser consumes the file synchronously during the action and does not store the original file (`ScheduleInputResolver` returns `file => null` in current branches). OCR creates a separate temporary optimized image and deletes it in `finally`. No parser result depends on the upload after the action completes.

**Validation interaction:** installed Livewire defaults globally validate temporary files as required files up to 12 MB. The roster request also permits up to 12 MB and restricts extensions/MIME to PDF, JPG/JPEG, PNG, BMP, TIFF, and WebP. The component still needs the roster-specific validation; the global upload rule alone does not enforce the allowed formats.

## 6. Validation-sharing recommendation

### Confirmed comparison

| Input | Rules | Custom messages | Attributes/conditionals |
|---|---|---|---|
| Roster `file` | `nullable`, `file`, `mimes:pdf,jpg,jpeg,png,bmp,tif,tiff,webp`, `max:12288`, `required_without:text` | `file.required_without`: provide roster text or file | Cross-field `required_without`; no custom attributes. |
| Roster `text` | `nullable`, `string`, `required_without:file` | `text.required_without`: same source message | Cross-field `required_without`; no custom attributes. |
| Roster `event_types` | `nullable`, `array` | none | Optional array. |
| Roster `event_types.*` | `Rule::in(ParserEventType::filterValues())` | invalid selected event type | Enum-derived allowed set; no custom attributes. |
| Flight `text` | `required`, `string` | required text message | No conditional rule or custom attributes. |
| Hotel `text` | `required`, `string` | same required text message | Identical to flight. |

No request overrides `attributes()` or `authorize()`; default Form Request authorization therefore applies.

### Recommendation

Extract the smallest shared, HTTP-independent rule definition, for example `app/Validation/ParserValidationRules.php`, with static methods returning roster rules/messages and text-parser rules/messages. Keep all three Form Requests as thin HTTP adapters delegating to it. The future Livewire form object or component should call the same provider and translate property prefixes only if separate nested form objects are chosen.

This is smaller than making Livewire instantiate Form Requests, avoids HTTP lifecycle coupling, preserves exact messages, and prevents divergent MIME/size/event rules. A single Livewire form object is reasonable only if the UI truly contains all three modes; otherwise use clearly named state (`rosterText`, `flightText`, `hotelText`) and scoped validation methods to prevent all forms sharing the error key `text`. The current code has no typed Livewire form-object convention to follow, so introducing form objects is a recommendation, not a confirmed repository convention.

## 7. Cache and session behavior

### Confirmed storage and resolution

- `BuildParserResult` generates a new parse ULID and stable download ULIDs for each result/event.
- `ParserResultCache::put()` recursively normalizes DTOs/JSON-serializable objects/enums to arrays/scalars.
- Each successful result is stored for `config('cache.parsed_results_ttl', 60)` minutes under both:
  - `sessions:{parsed_results_namespace}:parsed_results:{parseKey}`
  - `parsed_results:{parseKey}`
- The session records `latest_parse_key`; it also receives a random ULID `parsed_results_namespace` when first needed.
- `latest()` uses the session latest key, then `get()` tries the session-namespaced entry and falls back to the global parse-key entry.
- Export resolution prefers a query-string `parse_key`; otherwise it uses the session latest key.

### Isolation and concurrency

**Confirmed:** the session-prefixed copy is isolated by a random per-session namespace, not user ID. Multiple tabs in the same browser session share `latest_parse_key`, so the last successful parse in either tab becomes the result shown by refresh in both tabs. Older tabs' rendered export links remain stable because they include their own parse key.

**Confirmed security risk:** the second cache copy is globally keyed only by parse ULID. `get(parseKey)` falls back to it with no session/user ownership check, and export URLs accept `parse_key`. Tests explicitly confirm exports still work after session invalidation. Thus any authenticated/verified user who also passes the relevant feature/gate and knows another valid parse key can resolve that result. ULIDs are high-entropy but are acting as bearer identifiers; repository code does not enforce user ownership.

Two users do not normally overwrite one another because parse keys are newly generated ULIDs. They could access the same result if a valid parse key is disclosed. There is no user identifier stored in `ParserResultData` to validate ownership.

### Replacement and failure behavior

- Every cache write represents a completed service parse and updates `latest_parse_key`, including a successful parse with zero events.
- Validation failures never enter the service and do not change cache/session latest state.
- Source-resolution failures occur before result construction/cache put and do not change latest state.
- Other exceptions before `put()` also leave latest unchanged. An exception after `put()` but before `HandleParseExecution::success()` is not present in the observed method order, although infrastructure failure timing cannot be ruled out.
- Merely returning to either page GET does not clear the latest result.

The intended rule—“Keep the latest successful cached result until another parse succeeds. Returning to the upload form or encountering a failed parse must not delete it.”—is compatible with and already matches the normal current implementation. Clarify whether a zero-event parse counts as success; current code says yes and replaces the latest result. Retention is also bounded by cache TTL/session lifetime.

## 8. Proposed Blade component boundaries

Do not split files during the audit. The repository already favors anonymous Blade components under `resources/views/components/{domain}`; the future Livewire component should preserve those render-only components.

Recommended boundaries:

| Boundary | Proposed path | Exact markup responsibility |
|---|---|---|
| Livewire page/component root | `resources/views/livewire/schedule-parser.blade.php` | The current available parser grid, page heading/guide, transient processing status, and composition of forms/results. Must have one stable root element. |
| Roster upload/paste form | `resources/views/components/parser/forms/roster.blade.php` | Current `parser/form.blade.php` fields/filter details and the restored pasted-text control, converted to explicit Livewire bindings/actions in Phase 2. |
| Shared validation/status | `resources/views/components/parser/status-messages.blade.php` | Upload/progress/parse status and a form-scoped error summary. Field-level errors should remain beside fields. |
| Flight form | `resources/views/components/parser/forms/flight.blade.php` | New, minimal flight-text form backed by the existing endpoint/service behavior. Since none exists now, its UX/placement requires product confirmation. |
| Hotel form | `resources/views/components/parser/forms/hotel.blade.php` | New, minimal hotel-text form backed by existing behavior; same product confirmation required. |
| Results | Keep `resources/views/components/parser/result.blade.php` initially | Existing result summary, empty/error state, full export, cards, and admin JSON. It already forms a coherent render-only boundary. |

**Recommendation:** initially keep `event-card.blade.php`, `flight-card.blade.php`, nested components, and partials unchanged. Replacing the `@include` detail partials is unrelated to the request lifecycle and would be a broad refactor.

## 9. Current test coverage

### Covered

- Roster pasted text: end-to-end controller/service/cache/result hydration and detailed parser behavior.
- Image parsing: noisy OCR-output parser cases at feature level; image preprocessing, OCR cache, cleanup, failure, and preserved-upload behavior at resolver unit level.
- PDF parsing: resolver unit test with a fake PDF and mocked extractor; controller/service dispatch tests for published-roster and trip-information document types (these route tests mock source resolution and submit text rather than an actual HTTP PDF upload).
- Flight parsing: POST endpoint filters/stores flight events.
- Hotel parsing: POST endpoint filters/stores layover events.
- Validation: flight/hotel missing text, roster missing source, unsupported extension/MIME, invalid event type.
- Parser exceptions: source resolver exception is logged as failure without raw input; resolver-visible validation error paths have unit coverage.
- Result rendering: composition, summary/export button, empty/error view-model behavior, cards, airport enrichment, admin raw JSON visibility.
- Exports: full ICS, individual event ICS, stable event IDs, nested crew metadata, old result links, post-session parse-key fallback, duty event and duty authorization variants.
- Authorization/feature gates: disabled feature 404, missing capability 403, specific duty capability, page unavailable state, auth/verified coverage through route groups and existing auth tests.
- Cache normalization/resolution: dual cache writes, DTO dehydration, query parse key precedence, latest-result page hydration, older-result export.

### Not fully covered / nuance

- There is no actual browser-level or HTTP feature test that sends a supported image to `parse.roster` through the complete controller flow; image/OCR layers are split across parser feature tests and resolver unit tests.
- There is no actual HTTP supported-PDF upload test that exercises Form Request → controller MIME categorization → resolver; existing dispatch tests use mocked resolution and text input.
- There is no Livewire parser component yet, so no Livewire parser tests, upload tests, or no-redirect assertions exist.

## 10. Missing baseline tests

Add these before changing the lifecycle:

1. A successful supported PDF upload through `parse.roster`, with extraction mocked at the lowest practical boundary, asserting source classification, logging metadata, cache update, and redirect.
2. A successful supported image upload through `parse.roster`, asserting image source classification and cache update while mocking OCR infrastructure.
3. Explicit `ParseSourceResolutionException` response assertions: redirect target, `file` error text, old text/event filters, and retention of the previous `latest_parse_key`/cached result.
4. Form Request validation failure retention: previous result remains visible; old text and filters are restored; invalid filters are discarded by the view model as currently designed.
5. A non-source parser exception baseline (expected 500/rethrow and previous cache retention).
6. Two-tab/session behavior: a second success changes page `latest`, while a first tab's parse-key export URL still resolves the older result.
7. Two-session/user cache access baseline documenting the current global parse-key fallback. Mark it as a security characterization test; decide desired behavior before changing it.
8. Missing/expired/unknown parse key and unknown event ID return 404 for all export variants.
9. Empty successful parse replaces latest and renders the empty state; this locks in or challenges the current meaning of “successful.”
10. Authentication and email-verification assertions specifically for parser POST and export endpoints (not just relying on route-group inspection).
11. Result retention on plain `/dashboard` and `/parse` GET navigation with no old input.
12. A baseline that confirms no rendered flight/hotel forms, if preserving that absence during Phase 1 is intentional; otherwise capture approved new UX only in Phase 2 tests.

## 11. Risks and blockers

### Livewire project conventions

- **Confirmed version:** Livewire 4.3.3 from Laravel Boost application info and installed package code.
- **Default namespace/path:** installed defaults are `App\Livewire` and `resources/views/livewire`; neither path currently exists in application code.
- **Component style:** no first-party parser/application Livewire component and no Volt package/component was found. Filament supplies class-based Livewire components and tests use `Livewire::test(ClassName::class)` with PHPUnit.
- **Uploads:** no existing application Livewire file-upload example. Installed Livewire supports `WithFileUploads` and Laravel fake uploads in component tests.
- **Tests:** PHPUnit classes are the repository convention; existing Filament tests import `Livewire\Livewire` and test class components.
- **Alpine:** Alpine 3 is explicitly imported and started in `resources/js/app.js`; parser submit UI is registered as `Alpine.data('parserForm', parserForm)`.
- **Assets:** app layout has no `@livewireStyles`/`@livewireScripts`, but installed Livewire default `inject_assets` is true. With a Livewire component on the page, automatic injection is expected unless configuration is later published/overridden.
- **Public properties/forms:** no application convention for typed Livewire public properties or Livewire form objects was found. Other application PHP uses explicit types heavily.

**Recommendation:** use a class-based `App\Livewire\ScheduleParser` component, PHPUnit `Livewire::test(ScheduleParser::class)`, `WithFileUploads`, typed scalar/array public properties where Livewire supports them, and constructor/method dependency injection rather than service objects in public properties. Retain Alpine only for presentation that Livewire loading/upload state does not cover cleanly; prevent duplicate Alpine startup assumptions from entering the component.

### Confirmed risks

1. Global parse-key cache fallback is bearer-key access, not user/session authorization.
2. Multiple tabs share `latest_parse_key` and can change one another's refresh result.
3. Parser upload services require a local real path; S3 Livewire temporary uploads are incompatible as written.
4. Result state is heterogeneous and service-enriched; exposing it as public Livewire state would increase serialization/tampering risk.
5. Current error field name is always `file` for source resolution, including pasted-text failure.
6. Flight/hotel UI does not exist, so “convert forms” is not a mechanical migration and needs approved UX scope.
7. Old-input/error-bag behavior changes when redirects are removed and needs explicit parity tests.
8. Long synchronous OCR/PDF work currently depends on ordinary HTTP timeout behavior; Livewire adds an AJAX request and temporary-upload stage but does not make parsing asynchronous.
9. Automatic Livewire asset injection is package-default behavior, not explicit layout configuration.

### Assumptions requiring confirmation

- Production uses a local Livewire temporary upload disk. Repository defaults do, but environment overrides were not inspected.
- A zero-event parse should count as a successful replacement of the previous latest result.
- Cross-session export by disclosed parse key is intentional. Current tests require it after session invalidation, but no ownership policy is documented.
- The desired “single page” means no redirect for parse actions while retaining HTTP download endpoints and existing page routes.

### Blockers before implementation

- Decide whether exports must be session/user-owned or whether parse keys intentionally remain bearer tokens. This can affect cache keys, DTO contents, export tests, and migration compatibility.
- Confirm production Livewire temporary disk/local-path availability.
- Decide whether Phase 2 should introduce visible roster text, flight, and hotel forms, since those controls are absent today.
- Decide whether zero-event parses replace the latest successful non-empty result.

## 12. Recommended implementation sequence

1. Add the missing baseline/characterization tests while leaving routes and behavior unchanged.
2. Extract shared parser validation rules/messages into an HTTP-independent provider; keep Form Requests as adapters and verify their existing tests unchanged.
3. Confirm cache ownership and temporary-upload deployment decisions. Address any required security/storage redesign as a separately approved scope, not implicitly inside the UI refactor.
4. Add a class-based `ScheduleParser` Livewire component with `WithFileUploads`, scalar/array form state, injected existing actions/services, and authorization checks in every parse action.
5. Mount/render by resolving the latest parse key and rebuilding view models from `ParserResultCache`; never make parser DTO/service/view-model graphs mutable public properties.
6. Convert the existing roster form to `wire:submit`, replace redirect old-input/error flow with scoped Livewire validation/error handling, and refresh only the component result after success.
7. Preserve the existing export routes and generated links. Do not move ICS responses into component state.
8. Add approved pasted-roster/flight/hotel forms only after their desired visibility/layout is confirmed.
9. Replace/retire `parser-form.js` only after equivalent upload/loading/status behavior has Livewire tests and browser verification.
10. Run targeted request, cache, export, authorization, view-model, and Livewire tests; then optionally run the full suite.

## 13. Exact files expected to change in Phase 1

Phase 1 is baseline preparation and should not alter runtime behavior.

### Expected modifications

- `app/Http/Requests/ParseRosterRequest.php`
- `app/Http/Requests/ParseFlightRequest.php`
- `app/Http/Requests/ParseHotelRequest.php`
- `tests/Feature/ParseUploadTest.php`
- `tests/Feature/ParserRequestValidationTest.php`
- `tests/Feature/FeatureRouteAuthorizationTest.php`
- `tests/Feature/RosterParserTest.php`
- `tests/Unit/ParserResultCacheTest.php`

### Expected additions

- `app/Validation/ParserValidationRules.php`

Tests may instead be grouped into new focused PHPUnit files such as `tests/Feature/ParserResultRetentionTest.php` and `tests/Feature/ParserCacheIsolationTest.php`; choose one organization before implementation to avoid duplicating fixtures. No route, Blade, controller, service-contract, JavaScript, or dependency change is expected in Phase 1.

## 14. Exact files expected to change in Phase 2

This list assumes approved visible roster/paste/flight/hotel controls, retained export endpoints, no cache ownership redesign, and local temporary uploads.

### Expected additions

- `app/Livewire/ScheduleParser.php`
- `resources/views/livewire/schedule-parser.blade.php`
- `resources/views/components/parser/forms/roster.blade.php`
- `resources/views/components/parser/forms/flight.blade.php`
- `resources/views/components/parser/forms/hotel.blade.php`
- `resources/views/components/parser/status-messages.blade.php`
- `tests/Feature/Livewire/ScheduleParserTest.php`

### Expected modifications

- `app/Http/Controllers/ParserController.php` (page composition only; retain existing POST/export actions for compatibility)
- `resources/views/dashboard.blade.php`
- `resources/views/components/parser/result.blade.php` only if its props need a mechanical adjustment for the component render boundary
- `resources/views/components/parser/form.blade.php` (retire or make a compatibility wrapper after the new roster component boundary is proven)
- `resources/js/app.js`
- `resources/js/parser-form.js` (remove registration/implementation only after equivalent behavior is proven; deletion requires explicit implementation-phase approval)
- `tests/Feature/ParseUploadTest.php`
- `tests/Feature/ParserResultComponentTest.php`

`routes/web.php`, the three Form Requests, `HandleParseExecution`, `JcaScheduleParsingService`, `ParserCalendarExportService`, and existing export routes should not need Phase 2 contract changes under these assumptions. If cache ownership or non-local upload support is approved, revise this file list before implementation because `ParserResultCache`, result DTOs, export resolution, and upload adaptation would enter scope.

## Verification performed for this audit

- Inspected installed package information through Laravel Boost: Laravel 13.15.0, Livewire 4.3.3, PHP 8.5.
- Consulted installed-version documentation for Livewire uploads, validation/form objects, testing, and asset injection.
- Inspected installed `TemporaryUploadedFile` inheritance/path implementation and Livewire default configuration.
- Inspected parser routes, services, requests, view models, Blade/JS, middleware/gates, and focused tests listed above.
- Ran the project's targeted parser tests and route listing after writing this report; results are recorded in the final handoff rather than embedded as generated logs here.
