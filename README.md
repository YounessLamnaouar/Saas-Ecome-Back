<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Store operations

- Apply schema changes with `php artisan migrate` before deploying the frontend.
- Order placement creates a durable `order_email_deliveries` record and attempts a Brevo notification after committing the order. It uses `BREVO_API_KEY`, `BREVO_CONTACT_SENDER`, `BREVO_CONTACT_SENDER_NAME` and `CONTACT_RECIPIENT_EMAIL`. A provider failure does not roll back checkout or stock.
- Run Laravel's scheduler for automatic retries: `php artisan schedule:work` during local development, or invoke `php artisan schedule:run` every minute in production. Pending messages are retried every five minutes. `php artisan orders:retry-store-emails` also runs a manual retry. The dashboard reports pending notifications. Brevo acceptance is recorded; inbox delivery still depends on the provider.
- The dashboard aggregates the complete local database, including any retained demo orders. Revenue counts delivered orders only; open order value is shown separately. Inventory value uses list prices, not cost or profit.
- Reviews require a signed-in customer with a delivered order containing the product. Each customer has one editable review per product and can delete only their own. Imported demo ratings are no longer displayed or used for rating sort; no customer reviews are fabricated.
- Refresh stock reads the current database values without changing inventory quantities. Actual restocking remains an admin product edit.

Order-status emails are queued for the customer's account email. Keep `php artisan queue:work --tries=3 --timeout=30` running alongside the API (and restart it after code/config changes). The scheduler handles failed store-order retries separately. Brevo's API key must allow **Transactional email: All**, while retaining the newsletter Contacts permission. Permission failures are shown on the admin dashboard; successful checkout means the order is saved, not that an email has reached the inbox.

## Order invoices
Authenticated customers can download an invoice from checkout confirmation, My Orders, and order details. Administrators can download it from the order dialog. PDFs are generated server-side from saved order-item prices/names, including items removed from the catalog. Customer downloads are restricted by ownership; admin downloads require administrator access. Responses are private/no-store. Bills use the store's existing USD currency and cash-on-delivery method; no tax registration or payment confirmation is invented. TCPDF supplies Unicode fonts and automatic pagination.
