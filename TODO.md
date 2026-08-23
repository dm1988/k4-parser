# Refactor welcome page for use with new features

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

# Implement CrewCompass tie ins, branding, and marketing

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

# feat: Track schedule upload count
- For multiple file uploads within each user request

# Complete: Chore: update laravel
- Updated to laravel framework 13.26.1
- Required package updates