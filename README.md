# Single Product Ecommerce

Core PHP + PostgreSQL ecommerce app for Docker/Dokploy hosting. It includes a fast landing page, COD checkout, thank-you page, customer order lookup, admin order management, printable invoices, settings for tracking pixels, and Steadfast shipment creation.

## Requirements

- PHP 8.2+
- PostgreSQL 13+
- PHP extensions: PDO PostgreSQL, cURL, JSON, mbstring
- Apache with `.htaccess` support, or Docker/Dokploy using the included `Dockerfile`

## Setup

1. Copy `config.sample.php` to `config.php`, or set environment variables in Dokploy.
2. Update PostgreSQL credentials.
3. Open `/install.php` in the browser and create the first admin user.
4. Delete or rename `install.php` after setup.
5. Log in at `/admin/login.php`.

For Dokploy production steps, follow `DOKPLOY.md`.

## Admin

- Manage product price, stock, images, and delivery charge.
- View/filter orders and update statuses.
- Print invoices from each order detail page.
- Add GTM, GA4, Facebook Pixel, verification meta tags, and Steadfast credentials in settings.
- Create a Steadfast shipment from an order detail page.

## Steadfast

The default API shape uses `POST /create_order` with `Api-Key` and `Secret-Key` headers. Confirm the base URL and credentials from your Steadfast merchant/API panel before going live.
