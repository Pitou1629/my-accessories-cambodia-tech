# Laravel backend

The Express API has been replaced with Laravel 12. Its `/api` routes retain the
response shapes consumed by the React frontend. The application uses the
existing SQLite database at `backend/data/store.sqlite`; its migrations add
Laravel metadata and bearer-token storage without replacing the store tables.

## Setup

From the repository root, install the frontend dependencies and prepare the
Laravel application:

```sh
npm install
cd backend
composer setup
```

Before using the admin login or deploying the app, set `ADMIN_PASSWORD` in
`backend/.env` to a private value. `backend/.env.example` contains the
development default for compatibility with the existing local setup.

## Run locally

From the repository root, start Laravel and Vite together:

```sh
npm run dev
```

Vite proxies `/api` requests to Laravel on port 4000. To run only the API, use
`npm run dev:server` from the root or `php artisan serve --port=4000` from
`backend`.

## Build and test

`npm run build` builds the frontend into `backend/public/app`, which Laravel
serves at `/`. From `backend`, run `composer test` for the API tests.
