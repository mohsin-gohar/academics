# Operations

Local XAMPP and production use the same PHP application. Only the environment changes.

## Database

```bash
php database/migrate.php up
php database/migrate.php status
```

`database/migrate.php` refuses to run from a browser. Do not import `database/seed.sql` on a database whose name contains `prod`, `live`, or `production`. Seed data is a lab reset and forces a password change.

Create the first real platform administrator with:

```bash
php database/create_super_admin.php
```

## Accounts

- School registration (`register.php`) creates the school admin with the password they chose.
- Staff, students, and extra users created by an administrator do not get a password on screen.
- The app emails a one-time link to `/auth/reset_password.php`. The link expires in one hour and works once.
- The raw token is not returned in the HTTP response and is not written to a log file.
- `users.must_change_password` sends an existing session to `profile.php` until a password that meets the policy is saved.
- Changing a password increments `users.session_version` (migration `0012`) so other sessions are signed out.

## Email

Required for invitations and password reset:

| Variable | Example |
| :--- | :--- |
| `APP_URL` | `https://school.example.com` |
| `APP_ENV` | `production` |
| `MAIL_HOST` | SMTP hostname |
| `MAIL_PORT` | `587` |
| `MAIL_ENCRYPTION` | `tls`, `ssl`, or `none` |
| `MAIL_USERNAME` | SMTP user |
| `MAIL_PASSWORD` | SMTP password |
| `MAIL_FROM_ADDRESS` | `noreply@example.com` |
| `MAIL_FROM_NAME` | `Academics` |

Without `APP_URL` (or `APP_DOMAIN`) in production, reset mail is not sent. Without SMTP credentials, PHP `mail()` is tried once. If both fail, the user is told to use Forgot password. No password is displayed.

## Production checklist

- `APP_ENV=production` and a non-empty `DB_PASS`.
- `ALLOW_DEMO_ACCOUNTS` unset.
- HTTPS in front of the app so the session cookie is marked secure.
- Run migrations before opening the site.
- Do not expose `database/`, `tests/`, or `storage/logs/` as public downloads. Migration scripts already exit unless they are run from the CLI.
