# Dokploy Deployment

This project is a PHP 8.2 + MySQL app. Deploy it in Dokploy with the Dockerfile build type and a managed MySQL database.

## 1. Create MySQL

1. In Dokploy, create a MySQL database service.
2. Keep the internal database credentials ready:
   - Host
   - Port
   - Database name
   - Username
   - Password

Use the internal host/credentials from Dokploy for the app environment variables.

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

## 3. Environment Variables

Set these in the Dokploy application environment:

```env
APP_NAME=Kitchen Brush
APP_BASE_URL=https://yourdomain.com
APP_TIMEZONE=Asia/Dhaka
APP_DEBUG=false

DB_HOST=your-dokploy-mysql-internal-host
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
DB_CHARSET=utf8mb4

SESSION_NAME=sp_store_session
ADMIN_IDLE_TIMEOUT_MINUTES=60
LOGIN_MAX_ATTEMPTS=5
LOGIN_DECAY_MINUTES=15

STEADFAST_BASE_URL=https://portal.steadfast.com.bd/api/v1
STEADFAST_API_KEY=
STEADFAST_SECRET_KEY=
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

For security, remove or block `install.php` after the first setup before running ads.
