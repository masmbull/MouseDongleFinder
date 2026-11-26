# Contributing to MouseDongleFinder

Thank you for wanting to contribute! MouseDongleFinder is a focused, single-purpose Laravel + Vue app for matching wireless mouse USB dongles with their mice. Please keep contributions aligned with that narrow scope.

## Scope (read before contributing)

This app intentionally does **one thing**: connect → scan → identify → research → find software → physical test → confirm match.

Please do **not** propose or implement:

- Employee / HR management
- General IT asset management
- Ticketing, procurement, or finance
- Complex roles / permissions
- QR codes
- Enterprise reporting
- Background monitoring daemons

## How to contribute

1. Fork the repository on GitHub.
2. Create a feature branch from `main`: `git checkout -b feature/your-feature-name`
3. Make your changes following the coding conventions below.
4. Run the test suite and linter (see Verification).
5. Commit with a clear message and push to your fork.
6. Open a pull request against `main`.

## Coding conventions

### PHP (Laravel)

- PHP 8.3, Laravel 13.
- Use constructor property promotion.
- Declare explicit return types and parameter type hints on all methods.
- Use curly braces for all control structures, even single-line bodies.
- Prefer PHPDoc blocks over inline comments.
- Use `php artisan make:* --no-interaction` to generate files.
- Run `vendor/bin/pint --format agent` after modifying PHP files.
- Do not add new Composer dependencies without approval.
- No auth/roles system — this is a local single-user tool.

### Frontend (Vue 3 + Tailwind v4)

- Vue 3 Composition API with `<script setup>`.
- Tailwind CSS v4 (`@import "tailwindcss"`).
- Icons from `lucide-vue-next`.
- Axios with `baseURL = '/api'`.
- Reuse existing components in `resources/js/components/` before creating new ones.
- Run `npm run build` after frontend changes.

### Database / Migrations

- Migrations via `php artisan make:migration`.
- Seeders via `php artisan make:seeder`.
- Factories via `php artisan make:factory`.
- Seed data is development-only (`is_seed = true`) and never treated as real data.

## Verification

Before submitting a PR:

```powershell
php artisan test --compact
vendor/bin/pint --format agent
npm run build
```

All tests must pass. Do not claim a test passed unless you ran it.

## Pull request guidelines

- Keep PRs small and focused on one change.
- Update documentation (`README.md`, `API.md`, `DATABASE.md`, etc.) when behavior changes.
- Do not fabricate scan results, research results, OCR output, or confidence values.
- Never commit `.env`, secrets, or credentials.
- Never access production VPS or credentials belonging to another project.

## Questions?

Open an issue on GitHub for questions or discussion before starting a large change.