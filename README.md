# Pizza Place Sales

A REST API and a dashboard over a year of sales from a fictitious pizza place.
The API is Laravel, the dashboard is a Vue 3 single-page application that reads
everything through the API, and the whole stack runs in Docker.

This README is filled in as the application is built.

## Running it locally

The whole stack runs in Docker; the host needs only Docker with Compose v2.
Composer, artisan and npm run inside the containers.

```bash
git clone git@github.com:ijay-bm/pizza-place-sales.git
cd pizza-place-sales
cp .env.example .env
cp apps/api/.env.example apps/api/.env
docker compose -f docker-compose.local.yml up -d --build
docker compose -f docker-compose.local.yml exec -u app php php artisan key:generate
docker compose -f docker-compose.local.yml exec -u app php php artisan migrate --seed --seeder="Database\Seeders\Local\DemoSeeder"
```

The application is then at <http://localhost:8080>, and the API's health check
at <http://localhost:8080/api/up>.

Log in as `demo@pizza-place-sales.test` with the password `password`; the local
demo seeder creates that account.

- The root `.env` is read by Docker Compose alone: the host ports and the
  database account. Its `DB_*` values must equal the `DB_*` lines in
  `apps/api/.env`, which is Laravel's.
- If your user id is not 1000 (`id -u`), set `UID` and `GID` in the root `.env`
  before the first `up`, so files the containers write stay yours.
- The first `up` installs the PHP and Node dependencies, which takes a minute;
  `docker compose -f docker-compose.local.yml logs -f node` shows when Vite is
  ready.

The API's documentation, generated from the code by Scramble, is at
<http://localhost:8080/docs/api> while the app runs locally. Requests sent from it
use the session of the dashboard open in the same browser.

| Service | What it runs |
|---|---|
| `nginx` | the only published port: `/api` and `/sanctum` go to PHP, everything else to Vite |
| `php` | php-fpm 8.5 serving Laravel from `apps/api` |
| `composer` | `composer install`, once, before `php` starts |
| `node` | the Vite dev server for the Vue app in `apps/web` |
| `mysql` | MySQL 8.4, the application's database |
| `mysql-test` | MySQL 8.4 for the test suite, held in memory |

Tests run against `mysql-test`:

```bash
docker compose -f docker-compose.local.yml exec -u app php php artisan test
```

## Formatting

Prettier formats the TypeScript, Vue and CSS, and the PHP through
`@prettier/plugin-php`; Pint runs after it on the PHP and has the last word,
so the PHP follows Laravel's style. Run both, in this order:

```bash
docker compose -f docker-compose.local.yml exec node npm run format
docker compose -f docker-compose.local.yml exec -u app php vendor/bin/pint
```

The checks are `npm run format:check` for the front end and
`vendor/bin/pint --test` for the PHP.
