# Running the baseline tests

Use PHP 8.4.1 or later and Composer. The tests use a separate local MySQL database, `roost_testing`, and a dedicated account, `roost_test@localhost`.

## First-time local setup

With a MySQL administrator connection in DBeaver, run the following after replacing the password placeholder with your own unique local test password. This account must be new; if it already exists, inspect its grants before reusing it.

```sql
CREATE DATABASE IF NOT EXISTS roost_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'roost_test'@'localhost' IDENTIFIED BY 'REPLACE_WITH_LOCAL_TEST_PASSWORD';
GRANT ALL PRIVILEGES ON roost_testing.* TO 'roost_test'@'localhost';
SHOW GRANTS FOR 'roost_test'@'localhost';
```

Copy `.env.testing.example` to `.env.testing` only if `.env.testing` does not already exist. Set its `DB_PASSWORD` to the chosen test password. Keep this file private; Git ignores it.

```powershell
composer install
php artisan key:generate --env=testing
php artisan config:clear
php vendor/phpunit/phpunit/phpunit
```

PHPUnit forces the test environment, database name, account name, local host/port, empty DB_URL, and in-memory session/cache drivers. The test base class checks both configuration and the actual connected database/account before Laravel runs `RefreshDatabase`. The dedicated MySQL account must have no privileges on the application's `roost` database.

`RefreshDatabase` may recreate tables in `roost_testing`; never store data you need there. It does not use the application database. Do not remove the guard to work around a connection error: fix the test credentials instead.

Run the database guard tests alone without a database connection:

```powershell
php vendor/phpunit/phpunit/phpunit --testsuite Unit
```

The feature suite covers login page rendering, dashboard access protection, host/guest registration with hashed passwords, both dashboards, login/logout, and rejection of incorrect credentials. It deliberately creates fixtures directly rather than requiring Faker or factories.

If Herd currently selects PHP 8.5, existing application code emits PDO constant deprecation notices. The separately installed PHP 8.4 can run this baseline without modifying unrelated application code:

```powershell
& "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" vendor/phpunit/phpunit/phpunit
```
