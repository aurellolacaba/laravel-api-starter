# Coding Standards

Conventions for this project. They describe how the existing code is written —
follow them so the codebase stays consistent. When in doubt, match the
surrounding code.

## Tooling

- **PHP 8.3+.** Use modern syntax: constructor property promotion, readonly
  properties, enums, `match`, first-class callable syntax, nullsafe `?->`.
- **Formatting is enforced by [Laravel Pint](https://laravel.com/docs/pint)**
  (default preset). Run it before committing:

  ```bash
  ./vendor/bin/pint
  ```

- **Always declare types.** Every method has parameter and return types; every
  property is typed. Use PHPDoc only to add detail types can't express (array
  shapes, generics, `@mixin`).

## Project layout

- **Feature code goes in a module** under `Modules/` (see the README's *Modular
  architecture* section). A module owns its controllers, requests, resources,
  services, routes, and tests.
- **Shared building blocks stay in the app skeleton:**
  - Eloquent models → `app/Models`
  - Base controller → `app/Http/Controllers/Controller`
  - Cross-cutting concerns (e.g. `App\Exceptions\ApiExceptionHandler`) → `app/`
  - Seeders and factories → `database/`
- One class per file; the filename matches the class name.

## Naming

| Thing                | Convention            | Example                       |
| -------------------- | --------------------- | ----------------------------- |
| Classes / interfaces | `StudlyCase`          | `TokenService`, `UserResource`|
| Methods / variables  | `camelCase`           | `issueAccessToken()`          |
| Database columns      | `snake_case`          | `email_verified_at`           |
| Routes / URIs         | `kebab`/`snake` nouns | `/api/auth/refresh`           |
| Permissions           | `{resource}.{action}` | `users.create`                |
| Test methods          | `test_snake_case`     | `test_wrong_password_is_rejected` |

## Models

- Primary keys are **UUIDs** — use the `HasUuids` trait.
- Declare mass-assignment and hidden attributes with **PHP attributes**:

  ```php
  #[Fillable(['title', 'body', 'status'])]
  #[Hidden(['secret_column'])]
  class Post extends Model
  {
      use HasFactory, HasUuids;
  }
  ```

- Define casts with the `casts()` method (not the `$casts` property).
- Type relationships and document their generics:

  ```php
  /**
   * @return BelongsTo<User, $this>
   */
  public function author(): BelongsTo
  {
      return $this->belongsTo(User::class, 'user_id');
  }
  ```

- Put reusable query constraints in scopes (`scopeActive`, `scopePublished`).

## Controllers

- Keep controllers **thin**: validate via a FormRequest, delegate business logic
  to a service, shape output with a Resource.
- Type-hint FormRequests and return `JsonResponse` (or a `JsonResource`).
- Use HTTP status constants: `Symfony\Component\HttpFoundation\Response::HTTP_CREATED`.
- For list endpoints, use `Spatie\QueryBuilder\QueryBuilder` with **allow-listed**
  filters and sorts — never expose arbitrary query power:

  ```php
  $users = QueryBuilder::for(User::class)
      ->allowedFilters('first_name', 'email')
      ->allowedSorts('first_name', 'email')
      ->paginate(50)
      ->appends(request()->query());
  ```

## Validation & authorization (FormRequests)

- Every write endpoint has a FormRequest holding both **rules** and
  **authorization**.
- Authorize with the [spatie/laravel-permission](https://spatie.be/docs/laravel-permission)
  `can()` check, guarded against a null user:

  ```php
  public function authorize(): bool
  {
      return $this->user()?->can('users.create') ?? false;
  }
  ```

- Rules are arrays of rule objects/strings; use `Rule::` builders for
  `unique`/`exists`/`in`. Mark optional fields `nullable`; for partial updates
  use `sometimes`.

## API Resources

- Every response body is shaped by a `JsonResource`. Never return a raw model.
- Add `@mixin` so the underlying model's properties resolve in the IDE:

  ```php
  /**
   * @mixin User
   */
  class UserResource extends JsonResource { /* ... */ }
  ```

## Services

- Business logic that isn't trivially a single Eloquent call belongs in a
  service class under the module's `Services/` directory (e.g.
  `Modules\Auth\Services\TokenService`). Controllers receive services via
  constructor injection.

## Responses & errors

- Do **not** build ad-hoc error responses in controllers. Throw framework
  exceptions (`ValidationException`, `AuthorizationException`, `abort()`); the
  central `App\Exceptions\ApiExceptionHandler` normalizes them into:

  ```json
  { "success": false, "message": "...", "errors": null }
  ```

- Success payloads that need envelopes use the Resource `additional()` metadata
  (`success`, `message`).

## Permissions

- Name permissions `{resource}.{action}` with actions `view`, `create`,
  `update`, `delete`.
- Create roles and permissions under the **`api` guard** (see
  `RolesAndPermissionsSeeder`) — the API authenticates through the `api` guard,
  so authorization checks resolve against it.

## Routes

- Define routes in the owning **module's `routes/api.php`**. They are auto-prefixed
  with `/api`; don't add another `api`/`v1` prefix.
- Group protected routes behind `->middleware('auth:api')`.
- Rate-limit sensitive endpoints with `throttle:` (e.g. login, refresh).

## Testing

- Write **Feature tests** for every endpoint under the module's
  `tests/Feature/`, namespaced `Modules\<Module>\Tests\Feature`.
- Use `RefreshDatabase`, model **factories** for setup, and descriptive
  `test_snake_case` method names.
- Assert on JSON structure/paths and on persisted state
  (`assertDatabaseCount`, `assertJsonPath`).

## Commits & branches

- Short, imperative commit subjects with a type prefix, matching history:
  `feat:`, `fix:`, `chore:`, `refactor:`, `enhance:`.
- Do work on feature branches (`feature/<name>`); open PRs against `main`.
