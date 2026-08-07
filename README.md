# HAUS

Godišnja pretplata na održavanje doma. Tri obećanja koja sistem tehnički garantuje: **poznata cijena** (javni cjenovnik, snižene cijene se računaju, nikad upisuju), **dogovoren rok** (rok iz paketa; probijen rok automatski upisuje besplatnu intervenciju), **pisana garancija** (izvještaj sa slikama prije i poslije u roku od 24 sata). Nema telefonskih poziva i nema gotovine.

## Struktura

| Folder | Šta je |
|---|---|
| `web/` | Laravel 12 backend (REST API `/api/v1`, jedini izvor istine) + Vue 3 SPA: javni sajt, registracija, klijentski dio, admin (dispečer) |
| `mobile/` | React Native 0.86 (CLI, TypeScript): klijent, serviser (majstor) i dispečer na istom API-ju |
| `design/` | Dizajn handoff: binding tokeni i pravila (`design/README.md`), prototipovi, brand assets |
| `docs/` | `ARCHITECTURE.md` (odluke i domenska pravila), `API.md` (REST ugovor između weba i mobile) |

## Lokalno pokretanje

Preduslovi: PHP 8.4 + Composer, MySQL (baza `haus`), Redis, Node 24 (nvm), za mobile Xcode / Android SDK.

```
# backend + web
cd web
composer install && npm install
cp .env.example .env && php artisan key:generate   # podesi DB_DATABASE=haus
php artisan migrate:fresh --seed
php artisan serve          # API + SPA na :8000
npm run dev                # Vite
php artisan queue:work     # mejlovi i obavjestenja
php artisan schedule:work  # rokovi, obnove, podsjetnici

# mobile
cd mobile
npm install && (cd ios && bundle install && bundle exec pod install)
npm run ios      # ili: npm run android
```

Detalji: `web/README.md`, sekcija "HAUS: lokalno pokretanje".

## Test korisnici (seed, lozinka `haus1234`)

| Uloga | Mejl |
|---|---|
| Dispečer | dispecer@haus.ba |
| Klijent (Plus, aktivna) | klijent@haus.ba |
| Klijent (Pro, 3 stana) | pro@haus.ba |
| Serviser | damir@haus.ba (i emir@, adnan@, senad@) |

## Plaćanje lokalno

Lokalni gateway je `fake` (`services.haus.payment_gateway`): kartična registracija vodi na `/placanje/simulacija`, dugme "Uspješna uplata" prolazi kroz isti kod put kao pravi Monri webhook (aktivacija u sekundi). Pravi `MonriGateway` postoji; pretpostavke označene `TODO MONRI` u kodu treba potvrditi sa Monri onboarding dokumentacijom prije produkcije. **Faza plaćanja je uslov za produkciju.**

## Testovi

```
cd web && php artisan test     # 265 testova
cd mobile && npx tsc --noEmit  # + metro bundle smoke
```
