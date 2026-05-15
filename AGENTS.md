# AGENTS.md

## High-signal basics

- UI standards live in `design-system.md` and are required for UI work.
- Frontend entry/layout router: `resources/js/app.tsx` (welcome -> no layout, `auth/*` -> `AuthLayout`, `settings/*` -> `[AppLayout, SettingsLayout]`, default `AppLayout`).
- Backend routes live in `routes/web.php` + `routes/settings.php` (Inertia pages; no API routes wired).
- TS alias `@/` -> `resources/js/` (from `tsconfig.json`).
- `python-service/` is a FastAPI scaffold only; not wired into Laravel yet.

## Commands worth knowing

```bash
composer dev        # php artisan serve + queue:listen + npm dev (concurrently)
composer setup      # install deps, .env, key, migrate, build assets
composer test       # config:clear -> pint --test -> php artisan test
composer ci:check   # npm lint:check -> format:check -> types:check -> test
./vendor/bin/pest --filter=SomeTest
npm run dev | build | lint | lint:check | format | format:check | types:check
```

## Lint/format quirks

- Prettier tabWidth 4 (2 for *.yml) + Tailwind class sorting; npm scripts target `resources/`.
- ESLint enforces type-only imports, padding around control statements, and `curly: all`.
- ESLint ignores generated/auto-owned paths: `resources/js/components/ui/`, `resources/js/routes/`, `resources/js/wayfinder/`, `resources/js/actions/`, plus `vite.config.ts` and `tailwind.config.js`.
- `.npmrc` sets `ignore-scripts=true` (no postinstall scripts).

## Generated/codegen

- shadcn/ui components in `resources/js/components/ui/` are generated; do not hand-edit.
- Wayfinder generates routes/types under `resources/js/wayfinder/` and `resources/js/routes/`.
- React compiler enabled via `babel-plugin-react-compiler` in `vite.config.ts`.

## Testing notes

- Pest is the test runner; Feature tests auto-use `RefreshDatabase` (see `tests/Pest.php`).
- Tests use in-memory SQLite via `phpunit.xml`.

## CI reality

- `lint.yml` runs Pint + Prettier + ESLint with auto-fix (not check-only).
- `tests.yml` runs on PHP 8.3/8.4 + Node 22, builds assets before Pest.

