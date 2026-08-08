# HAUS staging deploy

## Šta serveru treba

- PHP 8.3+ (radimo na 8.4) sa ekstenzijama: pdo_mysql, mbstring, xml, curl, gd ili imagick (slike), redis ili predis radi (predis je composer paket, ne traži ekstenziju)
- MySQL 8+ (baza `haus`), Redis
- Composer 2, Node 20+ (samo za build assets; može i build lokalno pa upload gotovog `public/build`)
- Nginx ili Apache: document root je `web/public`, sve rute na `index.php`
- HTTPS (i zbog Monri callbacka kasnije)

## Procesi (systemd ili supervisor)

| Proces | Komanda |
|---|---|
| Queue | `php artisan queue:work --tries=3` (ili Horizon: `php artisan horizon`) |
| Scheduler | cron red: `* * * * * php /putanja/artisan schedule:run >> /dev/null 2>&1` |

Bez queue workera ne idu mejlovi ni obavještenja. Bez schedulera ne radi obećanje roka (besplatna intervencija) ni obnove.

## Env za staging (vidi .env.staging.example)

Ključno: `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://staging-domena`, DB/Redis kredencijali, `MAIL_MAILER` (log dok nema SMTP-a), `HAUS_PAYMENT_GATEWAY=fake` (Monri tek uz prave kredencijale; dev/fake-payment ruta radi samo uz `APP_DEBUG=true`, pa je na stagingu sa debug=false kartični tok zatvoren do Monrija; uplatnica tok radi normalno).

## Koraci deploya (radi i rsync bez gita)

```
rsync -az --delete --exclude vendor --exclude node_modules --exclude .env --exclude storage web/ user@server:/var/www/haus/
ssh user@server 'cd /var/www/haus && composer install --no-dev --optimize-autoloader \
  && php artisan migrate --force \
  && php artisan db:seed --force        # samo prvi put
  && php artisan storage:link \
  && php artisan config:cache && php artisan route:cache && php artisan view:cache \
  && php artisan queue:restart'
```

Assets: `npm run build` lokalno pa rsync uključi `public/build` (server ne mora imati Node).

## Poslije deploya provjeriti

1. `GET /api/v1/packages` vraća 200 sa 3 paketa.
2. Registracija uplatnicom kroz `/registracija` prolazi i mejl završi u logu.
3. `php artisan schedule:list` prikazuje 3 haus komande.
4. `php artisan queue:work --once` ne baca grešku.
