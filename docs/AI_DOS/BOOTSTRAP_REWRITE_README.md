# Kingsway bootstrap rewrite

Rewritten files:
- home.php: single ordered bootstrap, per-file filemtime versioning
- index.php: supplied public homepage preserved
- service-worker.js: no API/PHP/JS caching, safe offline fallback
- js/core/app_bootstrap.js: sole manager initializer
- js/core/error_reporter.js: local-only by default, bounded queues
- js/core/speculative_loader.js: disabled by default, one-shot only
- js/utils/academic_context.js: idempotent, no auto-init
- js/core/bfcache_handler.js: no auto-init
- js/core/push_notification_manager.js: no auto-init
- js/main.js: lightweight idempotent utilities

Deployment:
1. Back up current files.
2. Copy files preserving paths.
3. Clear old service-worker caches once.
4. Confirm all core files are initialized only by app_bootstrap.js.
5. Keep telemetry remote submission disabled until routes are verified.
