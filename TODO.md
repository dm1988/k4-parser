# 1. Plan: Release flight extractor trial

Goal: release the flight extractor as a clearly labeled demo, validate usage, and then gate it behind its own Stripe subscription at $5 per year with a one-time two-month trial per user.

## Current focus: Phase 1: Demo release and measurement

Implementation outcome:

- Added a reusable, dark-mode-compatible `Demo` badge with default, info, and success variants, custom slot content, and safe fallback styling to both flight-plan navigation links without changing their existing feature gate.
- Demo access now explicitly requires a verified email. Verified non-admin users receive access when the environment-specific demo override is enabled; admins retain access; unverified users remain blocked.
- The disabled master feature continues to return 404 and hide navigation access for every user.
- Added a flight-plan-specific Filament dashboard widget for request volume, failure count and rate, average processing time, and distinct-user adoption using existing `extract_requests` data.
- Added focused navigation, authorization, extraction logging, model entitlement, dashboard layout, and metric tests covering enabled, disabled, admin, verified-user, and unverified-user behavior.
- Kept `.env.example` defaulted to `FEATURES_FLIGHT_RELEASE_FOR_ALL_USERS=false`. Deployment action remaining: set this value to `true` in the target environment and refresh cached configuration when the demo is released.

Focused verification: 34 tests passed with 226 assertions. Pint and Larastan completed successfully.

Commit message: `feat: release and measure flight plan demo access`

## Phase 2: Stripe and Cashier foundation

1. Create a Stripe product with a recurring annual price of $5; store only its Stripe price ID in billing configuration and environment variables.
2. Add Cashier's `Billable` trait to `User`, cast `trial_ends_at` as a datetime, and verify the existing Cashier customer/subscription migrations match Cashier v16 requirements.
3. Use a dedicated subscription name such as `flight-release` instead of `default`, keeping this entitlement independent from future paid feature tiers.
4. Configure test/live Stripe keys, webhook signing secrets, currency, and Cashier path without committing secrets.

## Phase 3: Checkout and one-time trial

1. Add an authenticated billing page showing the annual price, trial terms, current subscription state, renewal date, and cancellation or grace-period state.
2. Start the `flight-release` subscription through Stripe Checkout with `trialUntil(now()->addMonths(2))`, collecting a payment method so billing can begin automatically after the trial.
3. Prevent repeat trials by checking retained subscription history before offering trial terms; returning subscribers start paid access immediately.
4. Add named routes and thin controllers for checkout, success, cancellation, and Stripe's billing portal. Handle incomplete/SCA payments through Cashier's payment-confirmation flow.
5. Make checkout creation idempotent so repeated submissions cannot create duplicate subscriptions.

## Phase 4: Entitlement and lifecycle handling

1. Centralize flight-extractor entitlement in `User::canUseFlightRelease()`: the master feature must be enabled, admins retain access, the demo override grants temporary access, and regular users otherwise need an active, trialing, or grace-period `flight-release` subscription.
2. Keep the existing gate and route middleware as the single authorization boundary, and use the same method to decide whether navigation is rendered.
3. Configure Cashier's signed webhook endpoint and CSRF exclusion, then verify subscription creation, updates, cancellation, payment failure, and deletion synchronize locally.
4. Show actionable billing states for trialing, active, incomplete, past-due, canceled-on-grace-period, and ended subscriptions; never grant access to incomplete or past-due subscriptions.
5. Turn `FEATURES_FLIGHT_RELEASE_FOR_ALL_USERS` back to `false` only after checkout, webhooks, and entitlement behavior are verified in production test mode.

## Phase 5: Verification and launch

1. Add PHPUnit coverage for configuration, gates, middleware, checkout authorization, one-time trial eligibility, subscription states, admin bypass, demo override, and canceled/grace-period access.
2. Test webhook signature rejection and representative Stripe lifecycle payloads without making normal unit and feature tests depend on live Stripe network access.
3. Complete Stripe test-mode smoke tests for successful checkout, 3DS/SCA, declined payment, trial conversion, cancellation, portal return, and webhook replay.
4. Run focused tests and Pint during implementation, then run the full PHPUnit suite, Larastan, and a production Vite build at the final integration checkpoint.
5. Document the production rollout checklist in this section: Stripe product/price IDs, webhook URL and events, secrets, demo-flag cutoff, monitoring window, and rollback by restoring the demo override.

Open decisions before implementation:

- “two months” means two calendar months
- Existing demo users receive the full trial when billing launches
- Confirm tax handling and the customer-facing refund/cancellation policy before enabling live charges.

# 2. Context engineering
- Add CONTEXT.md
- Identify branding

# 3. Identify places to incorporate Crew Compass

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

# Buy me a coffee modal
- If a user has more than 7 extract requests, show a pop up modal every other request with a `buy me a coffee` link. 
- Don’t do this for users that have already bought me a coffee
- Way to track: manually for now

# Review welcome page for use with new features
------------------------------------------------

# Completed

## Completed: Dark mode preference behavior coverage

Outcome: Added dependency-free JavaScript tests against the production theme module. The tests emulate both light and dark operating-system preferences, verify that `system` mode resolves to the matching document theme, and confirm that explicit light and dark selections persist across reloads while overriding the operating-system preference. The focused theme tests now run in the frontend CI job before the production build.

Focused verification: The JavaScript theme test passes all 6 tests (2 top-level behaviors and 4 preference scenarios), the existing Theme Layout feature test passes 5 tests with 29 assertions, and Larastan passes with no errors.

Commit message: `test: cover persisted dark mode preferences`

## Completed: Filament Extract Requests table source filter and columns

Outcome: The Source filter now includes image-based extraction requests. Request, Status, Source, Parser, and Created remain always visible; User, Duration, Events, Flights, Hotels, and Error are toggleable and visible by default; Pages, File Size, File Hash, App Version, and Extractor Version remain toggleable and hidden by default.

Focused verification: 8 Extract Requests resource tests passed with 59 assertions covering the Image filter option and behavior, required columns, visible optional columns, and hidden forensic columns. Pint completed successfully.

Commit message: `Add image filtering and toggleable extract request metrics`

## Completed: GitHub Actions Pint concurrency

## Completed: Flight Plan extract request log to database

## Completed: System alert email notifications

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

# Completed: bug: from route http://127.0.0.1/flight-route-extractor menu drop downs do not work
- Mobile hamburger nav doesn't open
- Full size user profile doesn't open
- Investigation outcome: both controls depend on Alpine directives in the shared navigation, but `resources/js/app.js` no longer imports or starts Alpine. The removal occurred in merge commit `d1fc39d`; the current Vite bundle also contains no Alpine runtime. This affects every authenticated page using the application layout, not only the flight route extractor.
- Fixed by restoring Alpine initialization in the application JavaScript entry point. Added focused regression coverage for the shared navigation runtime and rebuilt the production Vite assets successfully.
- Fixed unreadable flight-route tokens in dark mode by adding accessible dark variants for fixes, airways, direct markers, and speed/altitude tokens, with focused classification coverage.
- Fixed all flight-plan section borders and column dividers in dark mode with the shared slate-700 color.
