# Performance Audit – Phase 5

## Findings
- Repeated catalogue lookups (extras and meeting points) executed uncached SQL queries on every admin and frontend request, resulting in redundant database work when rendering forms, schedules, and booking flows.
- Meeting point data was fetched without leveraging WordPress object caching, and translation rendering recalculated every time, adding additional processing under multilingual setups.

## Remediations
- Added persistent and in-memory caching to `ExtraManager::getAllExtras()` and `ExtraManager::getExtra()` with automatic invalidation on create/update/delete operations, reducing duplicate reads across booking, checkout, and admin pages.
- Introduced shared list and per-record caches for meeting points, including safe invalidation hooks and clone-based translation handling to avoid mutating cached payloads.
- Ensured CRUD operations flush cached payloads so subsequent requests receive fresh data immediately after changes.

## Impact
- Eliminates dozens of repeated `SELECT` queries per request for popular admin screens and booking API calls, improving response time and lowering database load.
- Multilingual environments avoid re-running translation logic for unchanged meeting point data thanks to cache reuse, speeding up REST and frontend responses.
- Provides a consistent caching abstraction ready for future catalogue data, establishing a pattern for object cache-aware data access in the plugin.
