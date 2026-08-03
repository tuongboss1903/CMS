# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Multi-tenant PHP CMS built on a self-written core — no framework (no Laravel/Symfony). PHP >=8.1, developed/CI'd against 8.2/8.3.

## Commands

- Install: `composer install`
- Test: `vendor/bin/phpunit` (no composer script wraps it — run directly). Config at `phpunit.xml`, testsuite "Core", tests live under `tests/`.
- Bootstrap a new site/tenant (one-time, guards on empty `users` table): `php bin/bootstrap.php <site_name> <domain> <admin_name> <admin_email> <admin_password>`
- No linter/formatter is configured — don't assume `phpcs`/`php-cs-fixer` exist.

## Architecture

- Entry point: `Application::bootstrap()->run()`; `public/index.php` is just 3 lines. `boot()` is idempotent.
- Request flow: `Request → Router → MiddlewarePipeline (Onion: Global→Group→Route→Controller) → ControllerResolver → Controller → Response`.
- Container: PSR-11, constructor-injection auto-wiring only, one Container per request.
- Database: PDO wrapper (mysql/sqlite), prepared statements only, identifier whitelisting, `forTenant()` helper for tenant-scoped queries.
- View: pure-PHP theme engine (no compiler), resolves `themes/{active}/views` → `themes/{default}/views`, no auto-escaping — escape explicitly in templates.
- Hook: WordPress-style Action/Filter system with priority/wildcard support and per-callback try/catch isolation.
- Modules (`modules/{Name}/`, namespace `Modules\Name`) and Plugins (`plugins/{Name}/`, namespace `Plugins\Name`) are discovered via `module.json`/`plugin.json`, topologically sorted, and boot in isolation loading their own `routes.php`/`Hooks.php`.
- Controllers are one-class-per-action (e.g. `PageCreateController`, `PageListController`), not grouped resource controllers — follow this convention for new controllers.
- Plugins register routes via the `plugin.routes.register` hook; tenant-level enable/disable is enforced at dispatch time via a per-plugin guard middleware, not at boot (boot runs before the tenant is resolved).
- Multi-tenancy: `TenantResolverMiddleware` resolves domain → tenant, fail-closed (404/403/503) on invalid/disabled sites. Current tenant is held in `TenantManager`, deliberately not in Session.
- Env vars are loaded by a custom minimal parser (`bin/load_env.php`), not `vlucas/phpdotenv`. See `.env.example` for the full var list.

## Gotchas

- **Module/plugin folder casing must exactly match the namespace** (e.g. `plugins/Ecommerce/` for `Plugins\Ecommerce`). Windows dev machines are case-insensitive so a mismatch won't fail locally — it only surfaces on Linux (production/CI) or via `composer dump-autoload -o`. Always double-check casing when adding or renaming a module/plugin.

## Conventions

- Branches: `feature/CMS-XXX-<description>` (ticket number from the tracker).
- Commits/PRs: prefix with the ticket, e.g. `CMS-055: <description> (PHASE 18)`.

For deeper architectural detail beyond what's summarized here, see `core-architecture.md`.
