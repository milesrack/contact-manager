# Contributing

## Workflow

1. Create or claim a GitHub issue.
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
- receive at least one teammate approval
- resolve all review conversations before merging

All pull requests must use rebase merging to preserve commit history.

## Checks

Before opening a pull request:

```bash
composer check
npm run check
```

To format files:

```bash
composer format
npm run format
```

## Testing

Add or update tests for new behaviour where necessary.

## Secrets

Never commit credentials, passwords, private keys, `.env` files, or production configuration.

Use `.env.example` to document required environment variables without including real secrets.
