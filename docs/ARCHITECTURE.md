# HAUS arhitektura

## Stack odluke

| Odluka | Izbor | Zašto |
|---|---|---|
| Backend | Laravel 12, PHP 8.4 | Spec kaže 11, ali sve 11.x verzije imaju otvorene security advisorije (EOL mart 2026). Composer ih odbija instalirati. Laravel 12 je ista arhitektura, aktivno održavan. |
| Web frontend | Vue 3 SPA (Composition API) + vue-router + Pinia, Vite | Umjesto Inertie: mobile ionako treba REST API, pa SPA + REST znači da se svaki endpoint gradi jednom i služi oba klijenta. Jedan izvor istine bez dupliranja kontrolera. |
| API | REST pod `/api/v1`, JSON | Ugovor u docs/API.md. |
| Auth | Sanctum personal access tokens za oba klijenta | Jedan auth mehanizam za SPA i mobile. Token u localStorage (web) i react-native-keychain (mobile). |
| Uloge | spatie/laravel-permission: `klijent`, `majstor`, `dispecer` | Majstor (serviser) je punopravan MOBILNI korisnik u ovom buildu: svoji nalozi, "krenuo sam" (u_toku), nalaz, slike prije/poslije kamerom, pozicije iz cjenovnika i materijal, završetak naloga. Admin web je strana dispečera. Izvještaj klijentu se generiše iz majstorovog unosa. |
| Baza | MySQL 9.5 (lokalno, baza `haus`, root bez lozinke) | |
| Queue | Redis + Horizon. Database queue fallback tabela preimenovana u `queue_jobs` | Domenska tabela naloga se zove `jobs` po specu; kolizija sa Laravelovom queue tabelom riješena preimenovanjem queue tabele u config/queue.php. |
| Scheduler | `php artisan schedule:work` lokalno; cron u produkciji | Rokovi, obnove, podsjetnici. |
| Mape | Leaflet 1.9 + OpenStreetMap tiles, atribucija obavezna | Nikad ručno crtana geografija. Pinovi po design/haus-map.js referenci. |
| Plaćanje | `App\Contracts\PaymentGateway` interfejs + `MonriGateway` (HTTP) + `FakeGateway` za lokalni dev | Lokalno bez Monri kredencijala FakeGateway simulira 3DS redirect i webhook. Prebacivanje driverom `services.haus.payment_gateway` u config/services.php, binding u AppServiceProvider. Knjiženje ishoda je u `App\Services\Payments\PaymentProcessor` i idempotentno je. |
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
- **Obnova**: `App\Services\RenewalService`, dvije cron komande. Tok je razrađen niže u sekciji "Obnova pretplate".
- **Pro popust na količinu**: settings JSON tiers `[{min:2,max:4,pct:10},{min:5,max:9,pct:15}]`; 10+ stanova nema automatske naplate, registracija postaje zahtjev za ponudu (`subscriptions.status = ponuda`).
- **Predlošci obavještenja**: `settings` ključevi po template_key, uređivani u adminu, render sa placeholderima. Ton po specu 1.8, nikad em dash. Sva obavještenja idu kroz `App\Services\NotificationService::send()`: renderuje predložak, upiše red u `notifications_log` po kanalu (poštuje `users.notif_push` i `notif_email`) i queue-uje generički mailable. Push je za sada samo log, FCM dolazi kasnije.
- **Aktivacija pretplate**: registracija upisuje `cekanje_uplate` sa `starts_at`/`ends_at` NULL. Aktivira je isključivo uplata: webhook > `PaymentProcessor::approve()` postavi `aktivna`, `starts_at = now`, `ends_at = +1 godina`, `price_paid`, knjiži fakturu kao `placeno`, spremi token za MIT obnovu, pošalje račun i obavještenje `pretplata_aktivna`.

## Automatika (scheduler)

Registrovana u `routes/console.php`, lokalno kroz `php artisan schedule:work`, u produkciji jedan cron red na `schedule:run`. Sve tri komande su idempotentne, pa ponovljen prolaz poslije pada ne duplira ni kredite ni fakture.

| Komanda | Ritam | Šta radi |
|---|---|---|
| `haus:check-deadlines` | svake minute | Nalozi sa `deadline_at < now`, koji nisu `zavrseno` i nemaju `deadline_missed_at`: upiše `deadline_missed_at`, digne `subscriptions.free_interventions` za 1 i pošalje `rok_probijen`. Red se zaključava (`lockForUpdate`), pa isti nalog ne dobija dva kredita. |
| `haus:process-renewals` | dnevno 06:00 | Obnova pretplate, tok niže. |
| `haus:renewal-reminders` | dnevno 09:00 | Aktivne pretplate sa `auto_renew` i `ends_at` tačno za 60 dana: `obnova_podsjetnik` (mejl i push) i `renewal_reminder_sent_at = now`. |

Dvije odluke koje spec ne pokriva:

- **Nalog bez pretplate** (naplata po cjenovniku, bez godišnjeg paketa): probijen rok se označi da ga dispečer vidi, ali kredita nema i obavještenje se ne šalje. Kredit živi na pretplati, pa ga nemamo gdje upisati, a predložak bi obećao besplatnu intervenciju koju ne možemo ispuniti.
- **Podsjetnik ide samo pretplatama sa upaljenim `auto_renew`.** Predložak kaže da je obnova automatska i navodi iznos, pa bi klijentu koji je obnovu ugasio poslao netačnu informaciju.

## Obnova pretplate

`haus:process-renewals` uzima pretplate `aktivna` sa `ends_at <= kraj današnjeg dana` i za svaku ide jednim od četiri puta:

1. **`auto_renew = false`**: pretplata prelazi u `istekla`. Nema fakture, nema mejla.
2. **`auto_renew = true`, postoji aktivan `payment_token`**: kreira se faktura `pretplata` za novi period, pa MIT naplata `PaymentGateway::chargeToken()`. Cijena se računa `PriceCalculator::subscriptionTotal()` po **trenutnom** stanju paketa i broju adresa, nikad se ne prepisuje stari `price_paid`.
   - **approved** > `PaymentProcessor::approve()`, isti kod put kao webhook: faktura `placeno`, pretplata `aktivna`, `starts_at = stari ends_at`, `ends_at = starts_at + 1 godina`, `remaining_visits` i `remaining_inspections` po adresama resetovani na paket, `renewal_reminder_sent_at = null`, račun na mejl i obavještenje `pretplata_aktivna`.
   - **declined** (ili gateway nedostupan, što se knjiži kao declined uz log): pretplata `istekla`, faktura ostaje `nenaplaceno`, klijent dobija `UplatnicaMail` kao rezervni put.
3. **`auto_renew = true`, nema aktivnog tokena**: faktura `pretplata` + `UplatnicaMail`, pretplata `istekla`.
4. Pretplata koja u međuvremenu više nije `aktivna` se preskače.

Novo stanje pretplate se **ne uvodi**. Pretplata koja čeka uplatu obnove je `istekla` sa nenaplaćenom fakturom. Kad uplata legne (webhook ili uplatnica), `PaymentProcessor` je vraća u `aktivna`. Procesor razlikuje prvu uplatu od obnove po tome da li pretplata već ima `ends_at`:

- `ends_at` je NULL > prva aktivacija, period počinje danas.
- `ends_at` postoji > obnova, period nastavlja na stari `ends_at` da klijent ne izgubi dane dok uplata putuje, i resetuje prava po adresama. Ako je uplata kasnila više od godinu dana, stari kraj više nema smisla i period počinje danas.

Rok pretplate mijenja samo faktura tipa `pretplata`. Faktura tipa `rad` ne dira period, ma u kojem stanju pretplata bila.

Krediti (`free_interventions`) preživljavaju obnovu: to je obećanje koje smo već dali.

Idempotencija: faktura obnove se ne duplira jer se prvo traži postojeća `pretplata` faktura u stanju `nenaplaceno` i ona se ponovo koristi. Uspješna obnova ionako gura `ends_at` u budućnost, pa sljedeći prolaz pretplatu ne vidi.

## Monri integracija (faza 6)

`App\Services\Payments\MonriGateway` je jedini dodir sa Monrijem. Monri dokumentacija u trenutku implementacije nije bila dostupna, pa su sve pretpostavke izolovane: konfiguracija u `config/services.php` (`services.monri`), a mjesta koja traže potvrdu nose `TODO MONRI` komentar.

- **`initiate()`**: WebPay hosted stranica, POST forme. `order_number` = broj fakture (ujedno `payments.gateway_reference`), `amount` u feninzima (cijeli broj, KM x 100), `currency = BAM`, `language = bs`, `tokenize_pan_offered = 1` (bez toga nema MIT obnove), `digest = sha512(key + order_number + amount + currency)`. Vraća `PaymentInitiation` sa `method = POST` i poljima forme, pa klijent renderuje skrivenu formu. Fake driver ostaje GET redirect sa praznim poljima.
- **`verifyWebhookSignature()`**: bez potpisa nema prolaza. Priznaju se dva oblika: zaglavlje `authorization: WP3-callback {digest} {timestamp}` sa `sha512(key + timestamp + sirovo tijelo)`, i polje `digest` u tijelu sa `sha512(key + order_number + amount + currency)`. Kad se potvrdi koji oblik Monri stvarno šalje, drugi se briše.
- **`refund()`**: HTTP POST na `services.monri.api_base`, pun i djelimičan, iznos u feninzima.
- **`chargeToken()`**: MIT naplata za obnovu, `pan_token` uz `moto = true`, bez 3DS koraka.
- API pozivi nose zaglavlje `Authorization: WP3-v2 {authenticity_token} {timestamp} {digest}` i imaju connect i read timeout. `RequestException` i `ConnectionException` se mapiraju u `App\Exceptions\PaymentGatewayException`, koja nikad ne ide korisniku sirova: povrat je 422 sa porukom na bosanskom, obnova je tretira kao odbijenicu i šalje uplatnicu.

Otvorena pitanja za Monri onboarding: tačne putanje (`/v2/form`, `/v2/transaction`, refund), oblik potpisa callbacka, naziv polja za token kartice, koje polje nosi MIT oznaku, i da li refund ide na zasebnu putanju ili kao `transaction_type = refund`.

## Stanja

- Nalog (`jobs.status`): `novo` > `zakazano` > `u_toku` > `zavrseno`. `jobs.type`: `redovno` | `garancija` | `pregled`.
- Pretplata (`subscriptions.status`): `cekanje_uplate` | `aktivna` | `istekla` | `otkazana` | `ponuda`.
- Grad (`cities.status`): `aktivan` | `u_pripremi`.
- Faktura (`invoices.status`): `nenaplaceno` | `placeno` | `refundirano` | `djelimicno_refundirano` | `bez_naplate`.
