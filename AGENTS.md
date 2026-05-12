# AGENTS.md

## Stack

Laravel 13 (PHP 8.3+) + React 19 + Inertia 3 + Tailwind CSS v4 + shadcn/ui (new-york).
Authentication via Laravel Fortify.
Pest for PHP testing, Pint (Laravel preset) for PHP linting, ESLint + Prettier for JS/TS.

## MANDATORY

Always refer to [design-system.md](./design-system.md) for UI/UX standards.
Context files in `.opencode/context/` provide additional workflow and code-quality guidance.

## Commands

```bash
composer dev              # Starts PHP server + queue worker + Vite (concurrently)
composer test             # Runs pint --test, then php artisan test
composer ci:check         # Full CI: lint:check + format:check + types:check + test
./vendor/bin/pest         # Run tests directly (skip lint)
./vendor/bin/pest --filter=SomeTest   # Run a single test
composer lint             # Pint --parallel (auto-fix PHP)
composer lint:check       # Pint --parallel --test (dry-run PHP)
npm run lint              # ESLint --fix
npm run lint:check        # ESLint (no fix)
npm run format            # Prettier --write
npm run format:check      # Prettier --check
npm run types:check       # tsc --noEmit
npm run dev               # Vite dev server only
npm run build             # Vite production build
```

## Testing

- **Pest** is the test framework (not PHPUnit, even though phpunit.xml exists as config file).
- Only **Feature** tests use `RefreshDatabase` automatically (set in `tests/Pest.php`). Unit tests do not.
- DB is in-memory SQLite during tests (configured in `phpunit.xml`).
- Tests require no special services — everything runs in-process.

## CI

GitHub Actions run on push/PR to `develop`, `main`, `master`, and `workos` branches.
- **lint.yml**: Pint auto-fix + Prettier + ESLint.
- **tests.yml**: Pest across PHP 8.3, 8.4, 8.5 with xdebug coverage. Node 22 required for frontend build step.

## Architecture

- **Frontend entry:** `resources/js/app.tsx` — Inertia app with layout routing based on page name prefix.
- **Backend entry:** `routes/web.php` and `routes/settings.php`. All routes are Inertia-based; no API routes exist yet.
- **`@/` alias** maps to `resources/js/` (TypeScript path alias).
- **shadcn/ui** components live in `resources/js/components/ui/`. These are generated, auto-ignored by ESLint and Prettier. Do not hand-edit them.
- **Wayfinder** generates route type definitions in `resources/js/wayfinder/`. Auto-ignored by ESLint.
- Generated route files in `resources/js/routes/` are also auto-ignored by ESLint.
- **Server actions** are in `resources/js/actions/`. Auto-ignored by ESLint.
- `babel-plugin-react-compiler` is active via Vite config (React compiler optimizations).

## Key conventions

- Prettier tabWidth is **4 spaces** for most files, **2 spaces** for `.yml`/`.yaml`.
- ESLint enforces `type-imports` with `separate-type-imports` (use `import type { ... }`).
- ESLint enforces blank lines around control statements (`if`, `return`, `for`, `try`, etc.).
- ESLint enforces `curly: all` — braces required even for single-line control flow.
- ESLint ignores `vite.config.ts` and `tailwind.config.js`.
- `.npmrc` sets `ignore-scripts=true` — no postinstall scripts run.
- `.env` uses SQLite by default for local dev. No external database needed.
- `composer setup` runs the full first-time setup (install deps, key generate, migrate, npm build).

## README vs reality

The README describes a 3-tier architecture with a Python/FastAPI data processing engine. This Python service **does not exist yet** in the codebase. The current system is a Laravel+React monolith with user auth, team management, and dashboard scaffolding. The data cleaning/analytics features are planned but not implemented.
