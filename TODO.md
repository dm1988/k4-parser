# Codex Usage Rules

Follow these rules for every remaining task:
1. Work on one task at a time marked by ##.
2. Run only focused tests while implementing a task.
3. Run Pint after PHP files change.
4. Larastan once at the final integration checkpoint, not after every small edit.
5. Preserve unrelated working-tree changes.
6. Update TODO.md with outcomes instead of adding another plan or duplicate checklist.
7. Create a commit message for each task


## Dark mode

* Configure Tailwind CSS v3 with `darkMode: 'class'`.
* Define a three-state theme preference: `light`, `dark`, or `system`.
* Add an inline `<head>` initialization script to apply the saved preference before the page renders and prevent a flash of the wrong theme.
* Persist the preference in `localStorage`; when `system` is selected, respond to changes from `prefers-color-scheme`.
* Add an accessible theme control to the desktop and mobile navigation with clear labels, keyboard support, and the current state exposed to assistive technology.
* Apply the theme initialization and controls consistently to the authenticated and guest layouts.
* Add semantic light/dark styles to the shared component classes in `resources/css/app.css`, including cards, headers, buttons, badges, and file inputs.
* Add `dark:` variants throughout shared Blade components before updating individual pages, covering backgrounds, text, borders, shadows, form controls, validation states, dropdowns, modals, and focus rings.
* Update the dashboard, schedule extractor, flight-plan extractor, profile, and authentication pages to use the shared dark-mode patterns.
* Decide whether the already-dark welcome and privacy-policy pages should remain fixed-dark or honor the saved theme, then make their behavior consistent with that decision.
* Verify Filament's admin-panel theme setting separately so it follows the same default and does not conflict with the application preference.
* Add tests that assert the theme initializer and accessible control are rendered in both layouts.
* Manually verify light, dark, and system modes at mobile and desktop breakpoints, including refresh behavior and live operating-system theme changes.
* Run the focused PHPUnit tests and `vendor/bin/sail npm run build` to confirm the Blade and Tailwind changes compile successfully.


## Cron email alerts
- Low disk space available
    - Warning: Less than 20%
    - Critical: Less than 10%
- High number of user signups
    - Signups in the last 24 hours
    - Warning: Review 30 day max and add 500 percent (30-day max * 6)
- High volume of extract requests
    - Warning: Review 30 day max and add 500 percent (30-day max * 6)
- Implement alert throttling 
    - warning once every 12–24 hours
    - critical once every 1–2 hours until resolved
    

## Flight Plan extract request log to database

* Record the authenticated user, `source_type = pdf`, and `parser_type = flight_plan`.
* Create the row immediately before extraction with `status = partial`.
* Reuse the existing request logger to capture the UUID, file hash, file size, duration, and application/extractor versions.
* Mark the request `success` after extraction.
* Mark the request `failed` with `FlightRouteNotFoundException` or another exception type when extraction fails.
* Keep the current redirect, validation message, temporary-file deletion, and application logging behavior unchanged.
* Add `Flight Plan` to the parser filter in the Filament Extract Requests table.
* Generalize the schedule-specific logger into an `ExtractRequestLogger` with a completion method that accepts explicit counts.
* For a successful flight-plan extraction, record:
  * `detected_event_count = 1`
  * `detected_flight_count = 1`
  * `detected_hotel_count = 0`
  * `page_count = null`, unless PDF page counting is added
* Add tests covering successful extraction, recognized extraction failure, unexpected exceptions, file cleanup, correct user/hash/size metadata, and confirmation that invalid uploads do not create rows.

## Context engineering
- Add CONTEXT.md
- Identify branding

## Identify places to incorporate Crew Compass

Audit outcome:

- Airport info is complete in flight cards and the flight-route extractor.
- Primary placement: show Crew Compass content on each layover card, below the hotel details. Display the resolved city, whether a layover guide is available, the number of available places, and links to the guide/city when available.
- Secondary placement: add the same compact city summary to origin and destination airport popovers. Do not duplicate it in the expanded airport-details accordion.
- Data gap: airport enrichment currently handles flight origins and destinations only. Layover events expose a station code but are not resolved to a canonical Crew Compass city.

Simple plan:

1. Extend the Crew Compass airport provider response with a canonical city identifier/slug, guide availability and URL, places count, and city URL. Resolve by airport/station code rather than city name.
2. Extend schedule enrichment to include unique layover station codes and attach the city summary to layover metadata, reusing the existing cached airport-resolution flow and avoiding requests from Blade views.
3. Expose typed city-summary data through the event and flight-card view models, then render a reusable Crew Compass city-summary component on layover cards and airport popovers.
4. Add focused provider, enrichment, view-model, and Blade component tests for available, unavailable, zero-place, duplicate-city, and provider-failure cases.
