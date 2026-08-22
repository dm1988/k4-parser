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
