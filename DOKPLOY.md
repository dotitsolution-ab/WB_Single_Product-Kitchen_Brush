# Dokploy Deployment

This project is a PHP 8.2 + PostgreSQL app. Deploy it in Dokploy with the Dockerfile build type and point it to your external PostgreSQL database.

## 1. Prepare External PostgreSQL

Create the database with your PostgreSQL provider, then keep these values ready:

- Host
- Port, usually `5432`
- Database name
- Username
- Password
- SSL mode, often `require` for managed external databases

If your database provider has a firewall or trusted sources list, allow your Dokploy server IP before deploying.

## 2. Create Application

1. Create a new Application in Dokploy.
2. Select GitHub as the provider.
3. Use this repository:

```text
dotitsolution-ab/WB_Single_Product-Kitchen_Brush
```

4. Select branch:

```text
main
```

5. Set Build Type to Dockerfile.
6. Use port `80`.
7. If you will upload images from the Admin Media Library, mount a persistent volume to:

```text
/var/www/html/assets/images/uploads
```

## 3. Environment Variables

Set these in the Dokploy application environment:

```env
APP_NAME=Kitchen Brush
APP_BASE_URL=https://yourdomain.com
APP_TIMEZONE=Asia/Dhaka
APP_DEBUG=false
APP_INSTALL_DISABLED=false

DB_DRIVER=pgsql
DB_HOST=your-external-postgres-host
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
DB_CHARSET=utf8
DB_SSLMODE=require
DB_CONNECT_TIMEOUT=10

SESSION_NAME=sp_store_session
ADMIN_IDLE_TIMEOUT_MINUTES=60
LOGIN_MAX_ATTEMPTS=5
LOGIN_DECAY_MINUTES=15

STEADFAST_BASE_URL=https://portal.steadfast.com.bd/api/v1
STEADFAST_API_KEY=
STEADFAST_SECRET_KEY=
```

If your provider gives a single connection string, you can use this instead of the individual `DB_*` host/user/password variables:

```env
DATABASE_URL=postgresql://user:password@host:5432/database?sslmode=require
```

## 4. Domain

1. Add your domain in Dokploy Domains.
2. Point the domain DNS to your Dokploy server.
3. Enable SSL.
4. Make sure `APP_BASE_URL` matches the final HTTPS domain.

## 5. First Install

After the first deploy, open:

```text
https://yourdomain.com/install.php
```

Create the first admin user, then open:

```text
https://yourdomain.com/admin/login.php
```

After the first setup, change this environment variable and redeploy:

```env
APP_INSTALL_DISABLED=true
```

This blocks `install.php` before running ads.

## 6. Meta CAPI

After login, open `/admin/settings.php` and add:

- Facebook Pixel ID
- Meta CAPI Access Token
- Meta CAPI Test Event Code, only while testing in Events Manager

Orders will send server-side `Purchase` events through Meta Conversions API.
