# HAUS arhitektura

## Stack odluke

| Odluka | Izbor | Zašto |
|---|---|---|
| Backend | Laravel 12, PHP 8.4 | Spec kaže 11, ali sve 11.x verzije imaju otvorene security advisorije (EOL mart 2026). Composer ih odbija instalirati. Laravel 12 je ista arhitektura, aktivno održavan. |
| Web frontend | Vue 3 SPA (Composition API) + vue-router + Pinia, Vite | Umjesto Inertie: mobile ionako treba REST API, pa SPA + REST znači da se svaki endpoint gradi jednom i služi oba klijenta. Jedan izvor istine bez dupliranja kontrolera. |
| API | REST pod `/api/v1`, JSON | Ugovor u docs/API.md. |
| Auth | Sanctum personal access tokens za oba klijenta | Jedan auth mehanizam za SPA i mobile. Token u localStorage (web) i react-native-keychain (mobile). |
| Uloge | spatie/laravel-permission: `klijent`, `majstor`, `dispecer` | Majstor je faza 2, ali uloga i model postoje od prvog dana. |
| Baza | MySQL 9.5 (lokalno, baza `haus`, root bez lozinke) | |
| Queue | Redis + Horizon. Database queue fallback tabela preimenovana u `queue_jobs` | Domenska tabela naloga se zove `jobs` po specu; kolizija sa Laravelovom queue tabelom riješena preimenovanjem queue tabele u config/queue.php. |
| Scheduler | `php artisan schedule:work` lokalno; cron u produkciji | Rokovi, obnove, podsjetnici. |
| Mape | Leaflet 1.9 + OpenStreetMap tiles, atribucija obavezna | Nikad ručno crtana geografija. Pinovi po design/haus-map.js referenci. |
| Plaćanje | `App\Contracts\PaymentGateway` interfejs + `MonriGateway` (HTTP, faza 6) + `FakeGateway` za lokalni dev | Lokalno bez Monri kredencijala FakeGateway simulira 3DS redirect i webhook. Prebacivanje driverom `services.haus.payment_gateway` u config/services.php, binding u AppServiceProvider. Knjiženje ishoda je u `App\Services\Payments\PaymentProcessor` i idempotentno je. |
| Slike | Laravel Storage (public disk lokalno), multipart upload | job_photos čuva path + tip (prije/poslije). |
| Mobile | React Native 0.86.2, CLI setup, TypeScript, @react-navigation (native-stack + bottom-tabs), TanStack Query, Zustand, RHF + Zod, react-native-keychain, FCM + notifee | Po specu. |

## Ključna domenska pravila (gdje žive)

- **Izračun cijena**: `App\Services\PriceCalculator`. Cijena za pretplatnika = `round(osnovna * (1 - popust_rada/100))` na cijeli KM. Materijal = `nabavna * 1.20`, pa popust paketa na materijal. Ništa izračunato se ne čuva u bazi.
- **Rok naloga**: `deadline_at` se računa pri kreiranju iz paketa (`deadline_hours` ili `emergency_deadline_hours` ako je hitno) i **nikad se ne mijenja**.
- **Brojač izlazaka**: svaka pretplata ima 1+ redova u `subscription_properties` (Mini/Plus tačno jedan, Pro po stanu). `remaining_visits` živi na property redu i umanjuje se na prelazu naloga u `zavrseno`, ne pri prijavi.
- **Besplatna intervencija**: `subscriptions.free_interventions` brojač. Cron `haus:check-deadlines` (svake minute) nađe naloge sa `deadline_at < now` koji nisu završeni i nisu već kreditirani, upiše kredit, označi nalog (`deadline_missed_at`), pošalje obavještenje. Pri završetku naloga redoslijed trošenja: kredit > preostali izlazak > naplata po cjenovniku sa popustom paketa.
- **Garancijski nalozi**: `type = garancija`, ne naplaćuju se i ne troše izlaske.
- **Izvještaj u 24h**: prelaz u `zavrseno` dispečuje queued mail (nalaz, slike prije/poslije, datum garancije) odmah.
- **Objava cjenovnika**: `price_items` ima `draft_base_price` i `base_price` (objavljena). Publish kopira draft u objavljeno u jednoj transakciji i digne `price_list_version` u settings. Web i mobile čitaju samo objavljeno, pa objava pogađa oba istovremeno.
- **Obnova**: cron dnevno; podsjetnik 60 dana prije isteka; na dan isteka MIT naplata po tokenu ako auto_renew i token postoje, inače uplatnica na mejl.
- **Pro popust na količinu**: settings JSON tiers `[{min:2,max:4,pct:10},{min:5,max:9,pct:15}]`; 10+ stanova nema automatske naplate, registracija postaje zahtjev za ponudu (`subscriptions.status = ponuda`).
- **Predlošci obavještenja**: `settings` ključevi po template_key, uređivani u adminu, render sa placeholderima. Ton po specu 1.8, nikad em dash. Sva obavještenja idu kroz `App\Services\NotificationService::send()`: renderuje predložak, upiše red u `notifications_log` po kanalu (poštuje `users.notif_push` i `notif_email`) i queue-uje generički mailable. Push je za sada samo log, FCM dolazi kasnije.
- **Aktivacija pretplate**: registracija upisuje `cekanje_uplate` sa `starts_at`/`ends_at` NULL. Aktivira je isključivo uplata: webhook > `PaymentProcessor::approve()` postavi `aktivna`, `starts_at = now`, `ends_at = +1 godina`, `price_paid`, knjiži fakturu kao `placeno`, spremi token za MIT obnovu, pošalje račun i obavještenje `pretplata_aktivna`.

## Stanja

- Nalog (`jobs.status`): `novo` > `zakazano` > `u_toku` > `zavrseno`. `jobs.type`: `redovno` | `garancija` | `pregled`.
- Pretplata (`subscriptions.status`): `cekanje_uplate` | `aktivna` | `istekla` | `otkazana` | `ponuda`.
- Grad (`cities.status`): `aktivan` | `u_pripremi`.
- Faktura (`invoices.status`): `nenaplaceno` | `placeno` | `refundirano` | `djelimicno_refundirano` | `bez_naplate`.
