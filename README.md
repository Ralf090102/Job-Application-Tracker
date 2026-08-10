# Job Application Tracker

A small full stack app for keeping track of job applications: what you applied to, where each one stands, and whether the posting itself had any red flags worth remembering.

## What it does

- Tracks each application through a pipeline: Saved, Applied, Interviewing, Offer, Rejected, Withdrawn.
- Paste a raw job posting and a local AI model extracts the company, role, salary range, location, and work mode for you, and flags anything that looks like a red flag (vague pay, unrealistic scope, pressure tactics) for you to review before saving.
- Search by company or role, filter by status, and sort the list.
- Single user login, so your data stays private on your own machine.

## Tech stack


| Layer         | Choice                                                                  |
| ------------- | ----------------------------------------------------------------------- |
| Backend       | Laravel 13 (PHP 8.4), SQLite                                            |
| Frontend      | React 19, Vite, Tailwind CSS 4                                          |
| Auth          | Laravel Sanctum, SPA session auth (no tokens in local storage)          |
| AI extraction | Ollama running locally (mistral by default), no external API key needed |


The backend and frontend are fully decoupled: the backend is a plain JSON API, and the frontend is a separate single page app that talks to it over HTTP. Nothing about the frontend depends on Laravel, and nothing about the backend depends on React. This trade a bit of setup convenience for skills that transfer to any stack.

## Running it locally

### 1. Backend (Laravel API)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

The API now runs at `http://localhost:8000`. The seeder creates one login user, check `database/seeders/DatabaseSeeder.php` for the credentials.

### 2. Frontend (React)

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

The app runs at `http://localhost:5173` and talks to the API above.

### 3. AI extraction (optional)

The "paste a job posting" feature calls a local Ollama model. Everything else in the app works without it.

```bash
ollama pull mistral
ollama serve
```



## Testing

```bash
php artisan test
```



## License

MIT, see [LICENSE](LICENSE).