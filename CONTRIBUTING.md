# Contributing

## Workflow

1. Create or claim a [GitHub issue](https://github.com/milesrack/contact-manager/issues).
2. Create a branch from the latest `main`.
3. Make focused commits using [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).
4. Push the branch and open a pull request.
5. Link the pull request to its issue.
6. Ensure all CI checks pass.
7. Obtain at least one teammate review.
8. Resolve review comments.
9. Rebase and merge into `main`.
10. Delete the merged branch.

Do not push directly to `main`.

## Branch Naming

Use:

```text
<type>/<issue-number>-<description>
```

Allowed types:

- `feat`
- `fix`
- `refactor`
- `test`
- `docs`
- `chore`
- `ci`

Examples:

```text
feat/12-user-registration
fix/27-contact-search
test/31-login-validation
docs/42-api-specification
```

## Commit Messages

Use [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/):

```text
<type>(<scope>): <description>
```

The scope is optional.

Examples:

```text
feat(auth): add user registration
feat(contacts): implement contact creation
fix(search): support partial surname matching
test(auth): cover invalid credentials
docs(api): document contact search endpoint
docs(readme): add setup instructions
chore: initialise repository
```

Descriptions should use the imperative mood, begin with a lowercase letter, omit a trailing full stop, and describe one logical change.

## Pull Requests

Pull request titles use the same Conventional Commit format.

Each pull request must:

- address one coherent change
- link its related issue
- pass CI
- receive at least one teammate approval after the latest changes
- resolve all review conversations before merging

Keep branches up to date with `main` and use rebase merging.

## Local Development

Follow [Getting Started](README.md#getting-started) for local setup.

### Development Tools

- Composer 2
- Node.js 24
- npm
- PHPUnit
- PHPStan
- PHP CS Fixer
- PHP Parallel Lint
- ESLint
- Prettier
- GitHub Actions

### Frontend

- `npm run build`: compile minified CSS.
- `npm run dev`: rebuild CSS automatically while editing.

Edit Twig templates in `templates/` and styles in `assets/css/app.css`.
Do not edit the generated `public/assets/css/app.css`.

### Database Migrations

Add numbered SQL files to `database/migrations/` and run `composer migrate`. Do not edit applied migrations.

### Test Database

Run in `sudo mysql`:

```sql
CREATE DATABASE contact_manager_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'contact_manager_test'@'127.0.0.1' IDENTIFIED BY 'contact_manager_test';
GRANT ALL PRIVILEGES ON contact_manager_test.* TO 'contact_manager_test'@'127.0.0.1';
EXIT;
```

Migrate the test database:

```bash
DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_NAME=contact_manager_test DB_USER=contact_manager_test \
DB_PASSWORD=contact_manager_test composer migrate
```

### API Changes

Keep [openapi.yaml](openapi.yaml) in sync with API changes.

### Tests and Checks

Add tests for new behaviour and bug fixes. Run tests:

```bash
composer test
```

Before opening a pull request:

```bash
composer validate --strict
composer check
npm run check
```

- `composer check`: PHP syntax, formatting, static analysis, and tests.
- `npm run check`: JavaScript linting, formatting, and the CSS build.

To format files:

```bash
composer format
npm run format
```

## Secrets

Never commit credentials, passwords, private keys, `.env` files, or production configuration.

Use `.env.example` to document required environment variables without including real secrets.

Report vulnerabilities using [SECURITY.md](SECURITY.md).
