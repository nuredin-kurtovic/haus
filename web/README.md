<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## HAUS: lokalno pokretanje

Cetiri procesa, svaki u svom terminalu. Prva dva su dovoljna za klikanje po
aplikaciji, druga dva trebaju cim se dodirne mejl, obavjestenje ili obnova.

```bash
cp .env.example .env && php artisan key:generate   # samo prvi put
php artisan migrate:fresh --seed                   # baza haus, MySQL

php artisan serve        # 1. API i SPA na http://127.0.0.1:8000
npm run dev              # 2. Vite (node je iz nvm-a, vidi CLAUDE.md)
php artisan queue:work   # 3. mejlovi i izvjestaji (queue je Redis)
php artisan schedule:work # 4. rokovi, podsjetnici i obnove
```

Bez `queue:work` mejlovi ostaju u redu i nikad se ne posalju: racun, uplatnica,
izvjestaj u 24 sata i sva obavjestenja idu kroz red. Mailable klase pokusavaju
tri puta, sa razmakom od minute, pet i petnaest minuta.

Bez `schedule:work` ne rade tri komande koje se inace vrte same:

| Komanda | Kada | Sta radi |
|---|---|---|
| `haus:check-deadlines` | svake minute | Probijen rok: upise besplatnu intervenciju i javi klijentu. |
| `haus:process-renewals` | svaki dan u 06:00 | Naplata obnove po spremljenoj kartici ili uplatnica. |
| `haus:renewal-reminders` | svaki dan u 09:00 | Podsjetnik 60 dana prije isteka pretplate. |

Svaka od njih se moze pokrenuti i rucno (`php artisan haus:check-deadlines`),
sve tri su idempotentne. U produkciji ide jedan cron red koji svake minute zove
`php artisan schedule:run`.

Testovi: `php artisan test`. Formatiranje: `./vendor/bin/pint`.

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
