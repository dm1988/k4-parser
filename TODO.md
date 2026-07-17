# Current Task: 

## 🎯 Goal

- Refactor `ParserController` to use Form Request classes instead of inline validation and extract parsing/cache/logging responsibilities into smaller actions or services.
- Harden `AirportLookupClient` HTTP calls with `connectTimeout()` and appropriate `retry()` behavior for transient external API failures.
- Normalize Eloquent models to follow project best practices: add explicit relationship return types, typed scope signatures, and consistent cast/fillable patterns.
- Remove inline JavaScript from Blade views such as `resources/views/flight-release/index.blade.php` and third-party script injection from `resources/views/layouts/navigation.blade.php` by moving behavior into proper frontend assets/components.
- Enable development-time lazy loading protection in `AppServiceProvider` with `Model::preventLazyLoading()` in non-production environments.
- Fix airport popover Z index issues. Pop over card is hidden on small screens with large airport data.