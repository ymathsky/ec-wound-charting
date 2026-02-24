# Deployment Checklist for EC Project

## 1. Database Migration
The most reliable way to update your hosting database is to export your **current local working database** rather than relying on old backup files.

1. Open **phpMyAdmin** locally (http://localhost/phpmyadmin).
2. Select database `ec_wound`.
3. Click **Export** > **Quick** > **Go**.
4. Save the file (e.g., `ec_wound_latest_deploy.sql`).
5. Go to your **Hosting Control Panel** > **phpMyAdmin**.
6. Select your hosting database (e.g., `ecwound1_ecwound`).
7. **Import** the file you just saved.

## 2. Configuration (`db_connect.php`)
On hosting, your database credentials will differ.

**Option A: Environment Variables (Recommended)**
Ensure your hosting environment supports `.env` files or environment variables. Update the `.env` file on the server:

```ini
DB_SERVER=localhost
DB_USERNAME=ecwound1_user
DB_PASSWORD=your_hosting_password
DB_NAME=ecwound1_ecwound
```

**Option B: Manual Config**
If `.env` issues occur, edit `db_connect.php` directly on the server to hardcode credentials (temporary fix):

```php
$servername = "localhost";
$username = "ecwound1_user";
$password = "your_actual_password";
$dbname = "ecwound1_ecwound";
```

## 3. Verify Key Tables
Ensure these tables exist in your hosting database (required for AI & Chat):
- `visit_drafts`
- `wounds`
- `wound_assessments`
- `chat_messages`
- `users`

## 4. File Permissions
Ensure the `uploads/` directory on your server is writable (755 or 777 permissions) so images can be saved.
