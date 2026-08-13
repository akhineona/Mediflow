# Packaging QA Report

## Completed in the build environment

- All 38 PHP source files passed `php -l` after the final patches.
- The JavaScript bundle passed `node --check`.
- The project verifier confirmed all required files and writable runtime directories.
- The static database-call scanner reviewed 236 database calls and 184 literal prepare/execute pairs without detecting parameter-count or obvious unsafe interpolation problems.
- Literal `INSERT` and `UPDATE` column names were checked against the packaged schema.
- All static `route_url()` references were checked against the front-controller route map.
- No empty source files or unresolved development markers were found.
- The uninstalled application returned an HTTP 302 redirect from `index.php` to `setup.php`.
- The setup wizard returned HTTP 200, rendered the replacement confirmation, and handled an unavailable database driver without creating a partial config or setup lock.
- Schema parsing found 63 marker-separated SQL statements, including 27 tables and three views.
- The installer validates its inputs, verifies a setup CSRF token and password confirmation, refuses silent replacement of an occupied database, writes the runtime config only after successful schema/user setup, and creates a lock after successful installation.

## Environment limitation

The packaging container included PHP and PDO but did not include the PDO MySQL driver or a running MySQL/MariaDB server. Therefore, a real database installation and authenticated browser workflow could not be executed inside the packaging environment. The application is targeted at XAMPP, where PDO MySQL and MySQL/MariaDB are available. A full live-database acceptance checklist is provided in `TESTING.md` and should be run after local setup.
