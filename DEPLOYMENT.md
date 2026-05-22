# Deployment Runbook

## 1. Create Database

Create a PostgreSQL database with your hosting provider:

- PostgreSQL database
- PostgreSQL user
- Password
- Host and port, usually `5432`
- SSL mode if required by the provider

Keep these values ready for `config.php`.

## 2. Upload Files

Upload all project files to the domain document root, usually `public_html/`.

Do not upload a real `config.php` to a public repo. Create it directly on the server by copying `config.sample.php`.

## 3. Configure App

Create `config.php` from `config.sample.php` and update:

```php
'app' => [
    'base_url' => 'https://yourdomain.com',
],
'database' => [
    'driver' => 'pgsql',
    'host' => 'your-postgres-host',
    'port' => 5432,
    'name' => 'postgres_db_name',
    'user' => 'postgres_db_user',
    'pass' => 'postgres_db_password',
    'sslmode' => 'require',
],
```

For production, keep:

```php
'debug' => false,
```

## 4. Install

Open:

```text
https://yourdomain.com/install.php
```

Create the first admin user.

After successful install, delete or rename:

```text
install.php
```

## 5. Admin Setup

Open:

```text
https://yourdomain.com/admin/login.php
```

Then configure:

- Product name, price, stock, image, delivery charge
- Site name and support phone
- GTM ID
- GA4 measurement ID
- Facebook Pixel ID
- Google site verification
- Facebook domain verification
- Steadfast base URL, API key, and secret key

## 6. Required Server Extensions

Ask hosting support to enable these if unavailable:

- PDO PostgreSQL
- cURL
- JSON
- mbstring

## 7. Smoke Test

Before running ads:

- Place one COD order from mobile.
- Confirm thank-you page shows the order ID.
- Look up the order from My Account with phone + order ID.
- Open admin order details.
- Print invoice.
- Save manual courier details.
- Test Steadfast shipment creation with a real API key.
- Confirm GA4/Facebook purchase events from thank-you page.

## 8. Performance Checklist

- Use a WebP product image under 250 KB.
- Avoid third-party widgets beyond GTM/GA4/Pixel.
- Keep hosting PHP version at 8.2 or newer.
- Enable LiteSpeed Cache or server-level compression if available.
- Test with PageSpeed Insights after connecting the real domain.

## 9. Updating the Live Website

Recommended repeat workflow:

```text
Local project -> GitHub private repo -> Dokploy deploy -> live website
```

### First-Time Git Setup

1. Create a private GitHub repository.
2. Push this project to that repository.
3. In Dokploy, create an Application from the GitHub repository.
4. Set the build type to Dockerfile and the exposed port to `80`.
5. Keep production credentials in Dokploy environment variables. They should not be committed.

This project includes `.dockerignore`, which keeps local-only files out of the Docker image. It excludes:

- `.git`
- `config.php`
- local upload/log folders

### Normal Update Flow

After making changes locally:

```bash
git status
git add .
git commit -m "Update ecommerce site"
git push
```

Then in Dokploy:

1. Open the application.
2. Trigger a redeploy from the latest GitHub commit.
3. Check the build logs.

### Quick Live Check

After every deploy:

- Open homepage.
- Place a test order if checkout changed.
- Check thank-you page.
- Check My Account lookup.
- Check admin orders page.
- Check invoice page.
- If courier logic changed, test Steadfast on one order.

### Security Update Notes

After pulling the security hardening update:

- Open admin login once. The app will create security tables automatically if the database user has `CREATE` permission.
- If auto-create is disabled by hosting permissions, import `database/security_migration.sql` with your PostgreSQL admin tool.
- Confirm `/install.php` is deleted or renamed on the live server.
- Confirm `config.php` exists only on the server and is not committed to Git.
- Keep `APP_DEBUG=false` for production.

### Product Images

Upload product images to:

```text
/var/www/html/assets/images/
```

Suggested filenames:

```text
kitchen-brush-hero-drain.jpg
kitchen-brush-frypan-foam.jpg
kitchen-brush-pan-cleaning.jpg
kitchen-brush-hanging-storage.jpg
kitchen-brush-plate-demo.jpg
kitchen-brush-pan-close.jpg
```

These image files are committed with the project, so Dokploy will publish them with the rest of the site.

Images uploaded from Admin -> Media Library are saved in:

```text
/var/www/html/assets/images/uploads/
```

For persistent uploaded media, mount a Dokploy volume to `/var/www/html/assets/images/uploads`.

### Emergency Manual Update

If Git deployment is not available, rebuild the Docker image from the updated project files.

Do not overwrite live `config.php`.

For the latest `SQLSTATE[HY093]` fix, upload:

```text
includes/store.php
```
