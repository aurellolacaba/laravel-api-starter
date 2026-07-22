# Laravel API Starter

An opinionated starter kit for building JSON APIs with Laravel 13. It ships with
token-based authentication, role & permission management, consistent JSON error
handling, and query-string filtering/sorting out of the box.

## Features

- **Authentication** — [Laravel Sanctum](https://laravel.com/docs/sanctum) access
  tokens paired with persisted, rotating, revocable refresh tokens.
- **Refresh-token rotation** with reuse detection: presenting an already-used
  token revokes the whole token family as a theft precaution.
- **Roles & permissions** via [spatie/laravel-permission](https://spatie.be/docs/laravel-permission).
- **Flexible querying** via [spatie/laravel-query-builder](https://spatie.be/docs/laravel-query-builder)
  for allow-listed filtering, sorting, and includes.
- **UUID primary keys** on all models.
- **Uniform API error responses** through a centralized `ApiExceptionHandler`.
- **Rate limiting** on authentication endpoints.

## Requirements

- PHP `^8.3`
- Composer
- SQLite (default) or any Laravel-supported database

## Installation

```bash
git clone <repo-url> laravel-api-starter
cd laravel-api-starter

composer install
cp .env.example .env
php artisan key:generate

# create the SQLite database file (default connection)
touch database/database.sqlite

php artisan migrate --seed
```

### Environment

Authentication is configured via `.env` (see `config/sanctum.php`):

| Variable              | Default | Description                                    |
| --------------------- | ------- | ---------------------------------------------- |
| `SANCTUM_ACCESS_TTL`  | `15`    | Access-token lifetime, in minutes.             |
| `SANCTUM_REFRESH_TTL` | `43200` | Refresh-token lifetime, in minutes (30 days).  |

## Running

```bash
php artisan serve
```

## Testing

```bash
php artisan test
```

## Authentication flow

Access tokens are short-lived Sanctum personal access tokens; refresh tokens are
stored (hashed), rotated on every use, and revocable. Send the access token as a
bearer token:

```
Authorization: Bearer <access_token>
```

1. **Login** with credentials to receive an access + refresh token pair.
2. Call protected endpoints with the access token.
3. When the access token expires, **refresh** to obtain a new pair (the old
   refresh token is rotated out).
4. **Logout** to revoke the refresh token and immediately delete the current
   access token.

## API endpoints

All routes are prefixed with `/api`.

| Method | Endpoint         | Auth | Description                                          |
| ------ | ---------------- | ---- | ---------------------------------------------------- |
| POST   | `/auth/login`    | —    | Authenticate and receive a token pair.               |
| POST   | `/auth/refresh`  | —    | Exchange a refresh token for a new token pair.        |
| POST   | `/auth/logout`   | ✓    | Revoke the refresh token and invalidate the access token. |
| GET    | `/auth/me`       | ✓    | Return the authenticated user.                       |
| POST   | `/users`         | ✓    | Create a user.                                       |
| GET    | `/users`         | ✓    | List users.                                          |

`/auth/login` and `/auth/refresh` are rate limited (10/min and 20/min).

### Example: login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Accept: application/json" \
  -d "email=user@example.com&password=secret"
```

```json
{
  "user": { "id": "...", "email": "user@example.com" },
  "roles": ["..."],
  "permissions": ["..."],
  "access_token": "<token>",
  "refresh_token": "<token>",
  "token_type": "Bearer",
  "expires_in": 900
}
```

## Error responses

Errors on `api/*` routes are normalized by `App\Exceptions\ApiExceptionHandler`
into a consistent shape:

```json
{
  "success": false,
  "message": "Unauthenticated.",
  "errors": null
}
```

Validation failures return `422` with an `errors` object keyed by field.

## License

Open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
