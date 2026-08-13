# MediFlow Installation on XAMPP

## Requirements

- Windows, Linux or macOS
- XAMPP with Apache and MySQL/MariaDB
- PHP 8.1+
- `pdo_mysql` and `fileinfo` enabled

## Installation

1. Stop any application already using Apache ports 80/443 or MySQL port 3306.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Copy the extracted project folder to:
   - Windows: `C:\xampp\htdocs\mediflow`
   - Linux XAMPP: `/opt/lampp/htdocs/mediflow`
4. Open `http://localhost/mediflow/setup.php`.
5. Use these common XAMPP values:
   - Host: `127.0.0.1`
   - Port: `3306`
   - Database: `mediflow`
   - User: `root`
   - Password: blank, unless your MySQL root password is configured
6. Confirm the detected application URL.
7. Enter a strong administrator password.
8. Select demo data for a ready-to-present system.
9. Use a dedicated database. If the chosen database already contains tables, review the warning and explicitly confirm replacement. The installer will not proceed on an occupied database without this confirmation, and existing MediFlow tables with matching names will be recreated.
10. Click **Install MediFlow**.
11. Save the displayed credentials and open the application.

## Setup behavior

`setup.php` performs the following operations:

- checks PHP extensions and writable directories;
- creates the database when the database user has permission;
- detects an occupied target database and requires an explicit replacement confirmation before destructive schema setup;
- creates 27 related tables and three database views;
- creates the five roles;
- creates the administrator;
- optionally creates realistic demo accounts and reference data;
- writes `config/config.php`;
- creates `storage/setup.lock` to prevent web-based reinstallation.

## Reinstallation

Reinstallation is intentionally locked after a successful setup. To reset an academic demo installation:

1. Back up any required data.
2. Stop Apache access to the application.
3. Delete `storage/setup.lock`.
4. Delete `config/config.php`.
5. Open `setup.php` again.
6. Tick the replacement confirmation only after verifying the selected database and backup.

The setup schema drops and recreates MediFlow tables, so reinstalling erases application data. The installer refuses to continue on an occupied database until the replacement checkbox is selected.

## Troubleshooting

### PDO MySQL extension missing

Open the XAMPP `php.ini` file and ensure the PDO MySQL extension is enabled. Restart Apache afterward.

### Access denied for MySQL

Verify the MySQL user and password in the setup form. The default XAMPP root account often has a blank password, but this may differ.

### Apache cannot write config or uploads

Give the web-server process write permission to:

- `config/`
- `storage/`
- `uploads/`

### Application URL is wrong

Edit `config/config.php` and correct `app.base_url`, for example:

`http://localhost/mediflow`

### Blank page or application error

Review `storage/logs/app.log`. Set `app.debug` to `true` temporarily in `config/config.php` only during local debugging.
