# Review welcome page for use with new features

Audit outcome:

- The page is positioned as a Jeppesen Crew Access Schedule Extractor rather than Crew Compass's K4 Extractor product entry point. Its title, header, hero, benefits, screenshot, primary CTA, and account-security copy all describe only the Schedule Extractor.
- The visual treatment relies on indigo, emerald, and amber accents instead of the documented Aviation Blue, Compass Gold, Cloud White, Midnight, and Steel Gray palette, despite reusable `cc-*` styles and the Crew Compass logo already existing.
- The header and hero each use an `h1`, the screenshot has generic alternative text, and interactive elements need consistent keyboard-focus treatment.
- The public route is static, while authenticated feature access is already centralized in `User::canUseScheduleExtractor()` and `User::canUseFlightRelease()`. The welcome page can remain presentation-only and use those existing decisions for authenticated CTAs without adding a new backend layer or querying from Blade.

Refactor plan:

1. Reframe the metadata and header around the brand hierarchy: Crew Compass as the umbrella brand, K4 Extractor as the application, and one descriptive page `h1`. Reuse the existing Crew Compass logo and theme selector, and retain login, registration, dashboard, privacy, feedback, and independence links.
2. Replace the Schedule-only hero with concise product-level copy based on the shared promise: turn operational documents into reviewable information without manual re-entry. Keep Jeppesen Crew Access as supported Schedule Extractor context rather than the page's identity, and include the operational-verification disclaimer required by the brand voice.
3. Add a responsive two-tool section using a reusable Blade feature-card component. Give Schedule Extractor and Flight Plan Extractor equal visual hierarchy, crew-familiar descriptions, suitable icons, and a `Demo` badge on Flight Plan Extractor while that status applies. Move the existing phone screenshot into Schedule-specific supporting content instead of using it as the product-wide hero; do not invent a Flight Plan screenshot.
4. Make calls to action access-aware. Guests receive registration and login paths; authenticated users see direct links only for tools allowed by the existing entitlement methods, with a clear unavailable state otherwise. Keep hidden navigation from being treated as authorization and preserve all route middleware and gates.
5. Restyle the page with existing `cc-*` utilities and supported Tailwind CSS 3 classes, adding narrowly scoped reusable marketing styles only where repetition warrants it. Apply Aviation Blue to structure, Compass Gold to emphasis and CTAs, Cloud White/Midnight surfaces, Steel Gray secondary copy, matching dark mode, responsive spacing, visible focus states, semantic landmarks, and specific image alternative text.
6. Update focused PHPUnit feature coverage for Crew Compass/K4 Extractor identity, both tool summaries, guest and authenticated CTA states, feature-disabled states, the demo badge, theme controls, disclaimer/footer content, and removal of Schedule-only assumptions. During implementation, run the focused welcome/theme tests, Pint after PHP or Blade changes, a production Vite build for Tailwind validation, then Larastan once at the final integration checkpoint.

Proposed commit message: `refactor: make welcome page a branded product hub`

# Identify places to incorporate Crew Compass

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

# Completed: Flight Plan Extractor copy messages after repeated copies

Outcome: Copy-status messages now remain fully visible for two seconds before fading over 300 milliseconds. Reusing Departure, Destination, Alternate, Route, or detail copy controls cancels their prior timers and visibly restores the status instead of immediately fading it again. Added a JavaScript regression that exercises Departure, Destination, Alternate, and a fourth copy using a previously used control, plus a focused Blade rendering assertion for the corrected transition state.

Focused verification: 1 focused PHPUnit test passed with 32 assertions, all 7 JavaScript tests passed, Pint passed, Larastan passed with no errors, and the production Vite/Tailwind build completed successfully.

Commit message: `fix: keep repeated copy messages visible`

Static-analysis follow-up: Added a typed `expectOnce()` boundary for the test file's Mockery expectations, replaced the dynamically inferred log spy assertion with explicit `error` and `warning` facade expectations, and added a PHPStan stub for Mockery's runtime `CompositeExpectation` fluent methods. This preserves strict staged-test analysis without ignores, baselines, excluded tests, or a new dependency.

Follow-up verification: All 13 Flight Release controller tests passed with 147 assertions, Pint passed, the staged-file pre-commit hook passed, and direct Larastan analysis—including the registered Mockery stub—completed with no errors.

Follow-up commit message: `test: make flight release mocks larastan-safe`

# Track schedule upload count
- For multiple file uploads within each user request

# Feature: flight plan extractor: Extract MELs - Number and Name

# Confidence score for extraction

# flight plan extractor: Progress after submit
- Parse on file select ... processing
- with progress spinner or text

# flight plan extractor: Extract new route: don't show upload again
- Use an extractor another button, 2 page
- Hides the upload on the results page
- Extract new route: don't show upload again

# Feat: Look into offline use

# Feat: Fuel score

# Feat: Crew Rest

# Chore: update laravel

# Plan: Release flight extractor trial
Goal: release the flight extractor as a clearly labeled demo, validate usage, and then gate it behind its own Stripe subscription at $5 per year with a one-time two-month trial per user.

## Phase 1: Demo release and measurement

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

1. Create a Stripe product with a recurring annual price of $6; store only its Stripe price ID in billing configuration and environment variables.
- Sandbox Product ID: prod_V5e95Fh4fVJrkH
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

------------------------------------------------

# Completed

## Completed: Buy me a coffee modal

Outcome: Added a recurring, accessible coffee prompt after a user's 8th and each later even-numbered successful non-empty extraction. Schedule and flight-plan workflows share one eligibility action and configured support URL; cached results, reloads, failures, empty results, and users marked as purchasers are suppressed. Filament now exposes the manual purchase toggle and table status.

Focused verification: 49 focused PHPUnit tests passed with 409 assertions, Pint passed, Larastan passed with no errors, and the production Vite/Tailwind build completed successfully. Browser automation was unavailable for screenshot-based viewport and theme inspection.

Follow-up: Restored the modal panel's permanent transform stacking context so it remains above the backdrop after its transition and links stay clickable. Removed the standalone Alpine bootstrap in favor of Livewire 4's single bundled Alpine runtime on authenticated and guest layouts, eliminating duplicate initialization without breaking guest OTP controls.

Follow-up verification: 28 focused PHPUnit tests passed with 207 assertions, all 6 JavaScript tests passed, Pint passed, Larastan passed with no errors, and the production frontend build completed successfully.

Configuration follow-up: Moved the prompt threshold and recurrence interval to `services.buy_me_a_coffee`, backed by `BUY_ME_A_COFFEE_PROMPT_AFTER_EXTRACTIONS` and `BUY_ME_A_COFFEE_PROMPT_INTERVAL`. Non-positive intervals safely disable automatic prompts.

Configuration verification: All 5 focused coffee prompt tests passed with 27 assertions, Pint passed, Larastan passed with no errors, and Laravel resolved the local values as 7 and 2.

Commit message: `feat: add recurring buy me a coffee prompt`

Follow-up commit message: `fix: keep coffee modal interactive`

Configuration commit message: `refactor: configure coffee prompt cadence`

# Completed: Expose registration on the login page

Outcome: The login page now shows a guarded, dark-mode-compatible “New user? Register” prompt that links to the named registration route and includes visible keyboard-focus styling.

Focused verification: All 4 authentication tests passed with 11 assertions, Pint passed, Larastan passed with no errors, and the production Vite/Tailwind build completed successfully.

Commit message: `feat: add registration link to login page`

## Completed: Small-screen local times overflow

Outcome: Local flight and duty time cards now shrink within their nested grids and wrap long time labels on narrow screens instead of overflowing their container. The fix uses Tailwind utilities in the existing Blade markup without adding custom CSS.

Focused verification: All 7 flight card component tests passed with 31 assertions, Pint passed, Larastan passed with no errors, and the production Vite/Tailwind build completed successfully.

Commit message: `fix: prevent local times overflowing on small screens`

# Complete: Context engineering

Outcome: Added a root-level `CONTEXT.md` that defines the product identity, user needs, extractor workflows, aviation vocabulary, architecture and access boundaries, Crew Compass brand system, technical baseline, and context-maintenance rules. Clarified that K4 Parser is the application while Crew Compass is the umbrella customer-facing brand.

Documentation verification: Confirmed the file is non-empty, its required context sections are present, and the Markdown diff contains no whitespace errors.

Commit message: `docs: add shared project context`
