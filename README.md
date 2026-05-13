# dirty2data

A Laravel + Inertia + React monolith for data cleaning and analytics.

Workflow concept:

```
Upload → Profile → Clean → Analyze → Visualize
```

## Current architecture

- Laravel 13 + Inertia 3 + React 19 + Tailwind CSS v4 (single app, no external API service wired)
- Auth via Laravel Fortify
- Dataset routes live under `routes/web.php` (Inertia pages and controllers)
- `python-service/` is a FastAPI scaffold/contract only and is not wired into Laravel yet

## Dev commands

```bash
composer dev        # php artisan serve + queue:listen + Vite
composer setup      # install deps, create .env, key, migrate, build assets
composer test       # config:clear -> pint --test -> php artisan test
```

## Notes

- This repo is not a 3-tier React/Laravel/Python deployment yet; the Python service is a standalone scaffold.
- UI work should follow `design-system.md`.
