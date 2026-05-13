# AGENTS.md

## High-signal basics

- UI standards live in `design-system.md` and are required when touching UI.
- Frontend entry: `resources/js/app.tsx` (Inertia layout chosen by page name prefix).
- Backend routes: `routes/web.php` + `routes/settings.php` (Inertia routes; no API routes wired).
- TS alias `@/` -> `resources/js/` (from `tsconfig.json`).

## Commands worth knowing

```bash
composer dev        # php artisan serve + queue:listen + npm dev (concurrently)
composer setup      # install, .env, key, migrate, npm install/build
composer test       # config:clear -> pint --test -> php artisan test
composer ci:check   # npm lint:check -> format:check -> types:check -> test
./vendor/bin/pest --filter=SomeTest
npm run dev | build | lint | lint:check | format | format:check | types:check
```

## Lint/format quirks

- Prettier tabWidth 4 (2 for *.yml). Tailwind classes sorted via plugin.
- ESLint requires type-only imports (`import type { ... }`), padding around control statements, and `curly: all`.
- ESLint ignores generated/auto-owned paths: `resources/js/components/ui/`, `resources/js/routes/`, `resources/js/wayfinder/`, `resources/js/actions/`, plus `vite.config.ts` and `tailwind.config.js`.
- `.npmrc` sets `ignore-scripts=true` (no postinstall scripts).

## Testing notes

- Pest is the test runner; Feature tests auto-use `RefreshDatabase` (Unit tests do not).
- Tests use in-memory SQLite via `phpunit.xml`.

## CI reality

- `lint.yml` runs Pint + Prettier + ESLint with auto-fix (not check-only).
- `tests.yml` runs on PHP 8.3/8.4 + Node 22, builds assets before Pest.

## Generated/codegen

- shadcn/ui components in `resources/js/components/ui/` are generated; don’t hand-edit.
- Wayfinder generates routes/types under `resources/js/wayfinder/` and `resources/js/routes/`.
- React compiler is enabled via `babel-plugin-react-compiler` in `vite.config.ts`.

## README vs codebase

- Root `README.md` still describes a 3-tier React/Laravel/Python stack, but the actual app is a Laravel + Inertia + React monolith.
- `python-service/` exists as a FastAPI scaffold/contract only and is not wired into Laravel yet.
