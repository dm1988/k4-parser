# Codex Usage Rules

Follow these rules for every remaining task:
1. Do not start work unless sail is available
2. Work on one task at a time marked by ##.
3. Run only focused tests while implementing a task.
4. Run Pint after PHP files change.
5. Larastan once at the final integration checkpoint, not after every small edit.
6. Preserve unrelated working-tree changes.
7. Update TODO.md with outcomes instead of adding another plan or duplicate checklist.
8. Create a commit message for each ## task

## 1. Completed: Flight Plan extract request log to database

## 2. Completed: System alert email notifications

## 3. Completed: Dark Mode Implementation

- Enabled Tailwind class-based dark mode with persisted `light`, `dark`, and `system` preferences.
- Added a synchronous, reusable head initializer to prevent theme flashing and a dedicated JavaScript module for selector synchronization and live operating-system theme changes.
- Added accessible native theme selectors to the guest layout and authenticated desktop/mobile navigation.
- Added dark variants to shared Blade controls, layouts, auth/profile screens, schedule extraction views, flight cards, and flight-plan extraction views.
- Updated the Welcome and Privacy Policy pages to conform to the persisted light, dark, and system preference.
- Refined dark-mode contrast for the schedule upload surface, disabled extract action, navigation coffee link, and flight-plan header.
- Verified that Filament v5 dark mode is enabled and uses the same `localStorage.theme` values, so preferences persist between the application and admin panel.
- Added focused PHPUnit coverage for guest, authenticated, marketing/legal layouts, and Filament configuration.
- Verified 22 focused view/controller tests with 227 assertions and a production Vite build. Manual cross-browser visual QA remains a release-checkpoint task.

Commit message: `feat: add persistent light dark and system themes`

## 2. Context engineering
- Add CONTEXT.md
- Identify branding

## 3. Identify places to incorporate Crew Compass

Reference figma make plan

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

------------------------------------------------

# Completed

## Completed: Filament Extract Requests table source filter and columns

Outcome: The Source filter now includes image-based extraction requests. Request, Status, Source, Parser, and Created remain always visible; User, Duration, Events, Flights, Hotels, and Error are toggleable and visible by default; Pages, File Size, File Hash, App Version, and Extractor Version remain toggleable and hidden by default.

Focused verification: 8 Extract Requests resource tests passed with 59 assertions covering the Image filter option and behavior, required columns, visible optional columns, and hidden forensic columns. Pint completed successfully.

Commit message: `Add image filtering and toggleable extract request metrics`

## Completed: GitHub Actions Pint concurrency

## Completed: Flight Plan extract request log to database

## Completed: System alert email notifications
