# HAUS REST API ugovor (v1)

Base: `/api/v1`. JSON. Auth: `Authorization: Bearer <sanctum token>`. Sve cijene stižu **izračunate sa servera**, u KM, zaokružene na cijeli broj gdje je izvedeno. Datumi ISO 8601. Greške: 422 `{message, errors: {polje: [poruke]}}`, poruke na bosanskom, bez em dasha.

## Public (bez auth-a)

| Metod | Ruta | Opis |
|---|---|---|
| GET | `/packages` | Aktivni paketi sa svim parametrima. Za Pro uključuje `volume_discount_tiers`. |
| GET | `/cities` | Svi gradovi: `{id, name, lat, lng, status}`. Registracija koristi samo `status=aktivan`. |
| GET | `/price-list?q=&category=` | Objavljeni cjenovnik: kategorije > pozicije. Svaka pozicija: `{id, name, base_price, prices: {mini: n, plus: n, pro: n}}` (izračunato). |
| GET | `/surcharges` | Doplate `{key, label, type: percent|per_km|flat, value}`. |
| GET | `/settings/public` | Radno vrijeme, satnice, verzija cjenovnika. |
| POST | `/auth/register` | Body: `{package_id, name, email, password, payment_method: uplatnica|kartica, properties: [{city_id, street, use?, contact_name?, contact_note?}]}`. Mini/Plus šalju tačno 1 property. Pro 2+; 10+ vraća `{status: ponuda}`. Odgovor 201: `{status, user, subscription, token, invoice?, payment?: {redirect_url, method, fields}}`. `invoice` izostaje kod ponude, `payment` samo za karticu. |
| POST | `/auth/login` | `{email, password}` > `{user, token, role}`. Pogrešni kredencijali: 422 na `errors.email`. |
| POST | `/webhooks/monri` | Status webhook gatewaya (signature verified, ne Bearer). Polja zavise od drivera, vidi "Webhook gatewaya" niže. Aktivira ili produžava pretplatu i knjiži uplatu mašinski, idempotentno. Loš potpis: 403. |

### Detalji registracije

- `status` u odgovoru je `subscriptions.status`: `cekanje_uplate` ili `ponuda`.
- `subscription` nosi `{id, status, package: {id, name, slug, is_per_apartment}, starts_at, ends_at, auto_renew, price, price_paid, free_interventions, remaining_visits, remaining_inspections, properties[]}`. `price` je izračunata godišnja cijena (Pro: cijena po stanu x broj stanova uz tier popust). `starts_at` i `ends_at` su `null` dok uplata ne legne.
- `token` stiže odmah, i dok pretplata čeka uplatu. Guard koji traži aktivnu pretplatu je na `/client` rutama.
- Uplatnica: server šalje mejl sa iznosom i pozivom na broj, bez `payment` objekta u odgovoru.
- Kartica: `payment.method` kaže kako otvoriti 3DS.
  - `GET` (FakeGateway lokalno): `payment.fields` je prazan, klijent samo ide na `payment.redirect_url`, tj. `/placanje/simulacija?ref={reference}`.
  - `POST` (Monri WebPay): klijent renderuje skrivenu formu na `payment.redirect_url` sa svim parovima iz `payment.fields` kao hidden inputima i odmah je submituje. Polja se ne mijenjaju, ne filtriraju i ne dopunjuju: `digest` je potpisan nad iznosom i brojem narudžbe. Nijedno polje nije tajna, ključ trgovca nikad ne napušta server.

### Webhook gatewaya

Ruta je ista za oba drivera, ulazna polja nisu. Server ih svede na zajednički oblik prije knjiženja.

| Driver | Potpis | Body |
|---|---|---|
| `fake` | zaglavlje `X-Fake-Signature` = `sha256(sirovo tijelo + tajna)` | `{reference, status: approved\|declined, masked_pan?, token?}` |
| `monri` | zaglavlje `Authorization: WP3-callback {digest} {timestamp}`, gdje je digest `sha512(key + timestamp + sirovo tijelo)`; alternativno polje `digest` u tijelu | `{order_number, status, amount?, currency?, masked_pan?, pan_token?, ...}` |

- Mapiranje Monri polja: `order_number` > referenca uplate (to je broj fakture), `pan_token` > token za MIT obnovu, `masked_pan` > maska kartice. Status `approved` je odobreno, **sve ostalo** (`declined`, `invalid`, `error`) je odbijeno.
- Odgovor je isti za oba: `{status: uspjesan|neuspjesan, processed: bool}`. `processed: false` znači da je isti webhook već bio proknjižen.
- Bez potpisa ili sa pogrešnim potpisom: 403, zahtjev ne dolazi ni do baze. Nepoznata referenca: 404. Nedostaje `order_number` (Monri) ili `reference` (fake): 422.
- Ista ruta prima i uplatu obnove. Pretplata koja čeka obnovu je u stanju `istekla` sa nenaplaćenom fakturom tipa `pretplata`; uplata je vraća u `aktivna`, produžava period i resetuje brojače izlazaka. Detalji u docs/ARCHITECTURE.md, sekcija "Obnova pretplate".

## Auth zajedničko

| GET | `/me` | `{user: {id, name, email, notif_push, notif_email, notif_marketing}, role, subscription}`. `subscription` je sažetak **aktivne** pretplate `{id, status, package: {id, name, slug}, starts_at, ends_at, remaining_visits, remaining_inspections, free_interventions, properties_count}` ili `null`. Izlasci su zbir preko svih adresa. |
| POST | `/auth/logout` | Revoke token. |
| POST | `/devices` | `{fcm_token, platform: ios|android}` registracija za push, upsert po (korisnik, token). |

Bez tokena: 401 `{message: "Niste prijavljeni."}`. Pogrešna uloga: 403 `{message: "Nemate pristup ovom dijelu aplikacije."}`.

## Klijent (uloga: klijent)

| GET | `/client/dashboard` | `{subscription, active_job, recent_jobs}`, detalji ispod. |
| POST | `/client/jobs` | multipart `{price_category_id, description, is_emergency, preferred_window?, subscription_property_id?, photo?}`. 201 `{job: {id, number, deadline_at}}`. |
| GET | `/client/jobs` | `{data: []}`, najnoviji prvi. |
| GET | `/client/jobs/{id}` | `{data}` sa `findings`, `steps`, `photos`, `invoice`. Tuđi nalog: 404. |
| GET | `/client/subscription` | Paket, prava po adresi, historija faktura, `auto_renew`. |
| POST | `/client/subscription/cancel` | Jedan klik, gasi `auto_renew`. Idempotentno. |
| GET | `/client/price-list` | Kao public, plus `my_price` po poziciji. |
| GET/PUT | `/client/profile` | `{name, email, notifications: {push, email, marketing}}`. Mejl je read-only, adresa se NE mijenja ovdje. |
| POST | `/client/address-change-request` | `{message}` > zahtjev dispečeru. |

### Detalji klijentskih odgovora

**`GET /client/dashboard`**

```
{
  subscription: {id, package: {name, slug}, status, ends_at, remaining_visits,
                 free_interventions, remaining_inspections} | null,
  active_job: {id, number, status, category, deadline_at, scheduled_window_start,
               scheduled_window_end, technician: {name}|null,
               steps: [{key, label, done}]} | null,
  recent_jobs: [{id, number, status, type, category, created_at}]   // zadnjih 5
}
```

- `subscription` je aktivna pretplata, a ako je nema, zadnja upisana. Tako klijent koji čeka uplatu i dalje vidi svoje stanje. `remaining_visits` i `remaining_inspections` su zbir preko svih adresa.
- `active_job` je najnoviji nalog koji nije `zavrseno`, inače `null`.
- `steps` su uvijek ista četiri koraca istim redom: `prijava_primljena` (uvijek `done`), `majstor_dodijeljen` (`technician_id` postavljen), `termin_potvrdjen` (`scheduled_window_start` postavljen), `majstor_krenuo` (status `u_toku`). `label` je bosanski tekst sa servera, klijenti ga ne prevode.

**`POST /client/jobs`**

- Traži pretplatu u stanju `aktivna`. Inače 403 `{message, subscription_status}`, poruka po stanju: `cekanje_uplate` > "Vaša pretplata još nije aktivna. Prijava kvara je moguća čim uplata legne."
- `description` min 10 znakova. `photo` je slika do 8 MB, upisuje se kao `job_photos.type = prije` jer je kontekst kvara.
- `subscription_property_id` je obavezan kad pretplata ima više adresa (Pro), inače se uzima jedina adresa. Tuđa adresa: 422.
- `deadline_at` se računa iz paketa (`deadline_hours`, odnosno `emergency_deadline_hours` kad je `is_emergency`) i nikad se ne mijenja.
- Nalog se prima i kad je `remaining_visits` nula. Šta se naplaćuje odlučuje se pri završetku, ne pri prijavi.
- Prijava šalje obavještenje `prijava_primljena` sa `{broj}` i `{rok}`.

**`GET /client/jobs`** > `{data: [red]}`, red:

```
{id, number, status, type, category, title, description, is_emergency,
 technician: {name}|null, scheduled_window_start, scheduled_window_end,
 warranty_until, created_at, deadline_at, deadline_missed_at}
```

`title` je prvih 60 znakova opisa, `category` je naziv kategorije cjenovnika.

**`GET /client/jobs/{id}`** > `{data}`: sva polja reda iz liste, plus

```
{preferred_window, completed_at, findings, steps: [{key, label, done}],
 property: {id, city, street} | null,
 photos: [{type: prije|poslije, url}],
 invoice: {id, number, status, labor_items: [{name, qty, line_total}],
           materials: [{name, qty, line_total}], labor_total, material_total,
           total, paid_at} | null}
```

**`GET /client/subscription`**

```
{package: {puni paket kao na /packages}, status, starts_at, ends_at, auto_renew,
 price_paid, free_interventions,
 properties: [{id, city, street, use, remaining_visits, remaining_inspections}],
 payments: [{number, type, total, status, paid_at, created_at}]}
```

`payments` je historija faktura korisnika, najnovija prva. Bez ijedne pretplate: 404.

**`POST /client/subscription/cancel`** > `{message, auto_renew: false, ends_at}`. Pretplata ostaje `aktivna` do isteka, gasi se samo obnova. Ponovljen poziv vraća isti odgovor.

**`GET /client/price-list`** > isti oblik kao javni, uz `my_price` (cijeli KM) na svakoj poziciji i `meta.my_package: {name, slug, labor_discount_pct, material_discount_pct}`. Klijent bez pretplate dobija osnovnu cijenu kao svoju.

**`GET/PUT /client/profile`** > `{data: {name, email, notifications: {push, email, marketing}}}`. PUT prima `{name, notifications}`; `email` se ignoriše. Prekidači se mapiraju na `users.notif_push`, `notif_email`, `notif_marketing`.

**`POST /client/address-change-request`** `{message min 10, subscription_property_id?}` > 201 `{message}`. Upisuje `home_records` red tipa `napomena` sa naslovom "Zahtjev za promjenu adrese" na klijentovu adresu i šalje mejl dispečeru (`settings.dispecer_email`, fallback `config services.haus.dispatcher_email`). Bez izbora ide prva adresa pretplate.

## Serviser (uloga: majstor)

Serviser je mobilni korisnik. Nalog završava serviser kroz mobilnu aplikaciju; izvještaj klijentu se generiše iz njegovog unosa.

| GET | `/technician/jobs?status=&date=` | Samo nalozi dodijeljeni tom serviseru: `{id, number, status, type, is_emergency, category, description, client: {name}, address: {city, street}, scheduled_window_start/end, deadline_at}`. Default sortiranje po prozoru. |
| GET | `/technician/jobs/{id}` | Detalj (samo svoj, inače 404): + klijentova fotografija prijave, napomena o pristupu, preostali izlasci klijenta (da serviser zna ide li na naplatu). |
| POST | `/technician/jobs/{id}/start` | zakazano > u_toku. Šalje klijentu predložak majstor_krenuo. Idempotentno unutar u_toku. |
| GET | `/technician/price-list` | Objavljene pozicije po kategorijama (za izbor stavki), bez izračuna po paketima. |
| POST | `/technician/jobs/{id}/complete` | `{findings, items: [{price_item_id, qty}], materials: [{name, purchase_price, qty}]}` + multipart `photos_before[]`, `photos_after[]` (min 1 prije i 1 poslije). Ide kroz isti JobCompletionService kao admin complete. |

Serviserski nalog traži red u `technicians` vezan na korisnika (`user_id`). Bez te veze, ili kad je majstor isključen: 403 sa porukom. Seed pravi četiri naloga: `damir@haus.ba`, `emir@haus.ba`, `adnan@haus.ba`, `senad@haus.ba`, lozinka `haus1234`.

**`GET /technician/jobs/{id}`** > `{data}`: sva polja reda iz liste, plus

```
{preferred_window, findings, package,
 contact: {name, note},
 entitlements: {remaining_visits, remaining_inspections, free_interventions, ide_na_naplatu},
 photos: [{type: prije|poslije, url}]}
```

`ide_na_naplatu` je `true` kad su i kredit i izlasci potrošeni, pa rad ide na račun. Za tip garancija i pregled je uvijek `false`.

**`POST /technician/jobs/{id}/start`** > `{data, message}`. Iz stanja `novo`: 422 "Izlazak se pokreće samo na zakazanom nalogu." Ponovljen poziv u `u_toku` vraća 200 i ne šalje obavještenje drugi put.

**`POST /technician/jobs/{id}/complete`** > `{data: {id, number, status, completed_at, warranty_until, visit_source, invoice: {id, number, status, labor_total, material_total, total}}, message}`. Multipart; `items` i `materials` mogu stići i kao JSON tekst. Zatvaranje je moguće samo iz `u_toku`, inače 422.

### Završetak naloga (JobCompletionService, isti za servisera i dispečera)

- **Stavke rada**: snapshot iz cjenovnika u trenutku zatvaranja (`name`, `base_price`, `discount_pct` = `labor_discount_pct` klijentovog paketa, `line_total` = zaokruženo na cijeli KM x količina). Kasnija objava cjenovnika ne mijenja zatvoren nalog.
- **Materijal**: `nabavna x (1 + settings.materijal_marza_pct/100)` pa `material_discount_pct` paketa, na dvije decimale.
- **Redoslijed trošenja** (`jobs.visit_source`): `subscriptions.free_interventions` > `subscription_properties.remaining_visits` > naplata.
- **Šta se naplaćuje**:

  | tip / izvor | rad na fakturi | materijal na fakturi | troši |
  |---|---|---|---|
  | redovno, `kredit` | 0 | puni iznos | 1 besplatnu intervenciju |
  | redovno, `izlazak` | 0 | puni iznos | 1 izlazak na toj adresi |
  | redovno, `naplata` | puni iznos | puni iznos | ništa (nema prava) |
  | `garancija` | 0 | 0 | ništa |
  | `pregled` | 0 | puni iznos | 1 pregled na toj adresi |

  Kredit i izlazak pokrivaju rad u cijelosti. Stavke rada se svejedno upisuju sa svojim iznosom, da klijent vidi vrijednost koju je dobio; na fakturi je `labor_total` tada 0.
- **Faktura** tipa `rad` se pravi uvijek, i kad je iznos 0. Status je `nenaplaceno` kad je `total > 0`, inače `bez_naplate`.
- **Garancija**: `warranty_until = completed_at + package.warranty_months`. Nalog tipa `garancija` nasljeđuje `warranty_until` originala (`parent_job_id`), ne otvara novu.
- **Trag**: `jobs.status = zavrseno`, `completed_at`, red u `home_records` (tip `pregled` za pregled, inače `intervencija`) na adresi naloga, obavještenje `zavrseno` sa `{broj}` i `{garancija_datum}` (dd.mm.gggg) i queued izvještaj na mejl (nalaz, stavke, materijal, linkovi na slike prije i poslije, datum garancije).
- Sve upisano ide u jednoj transakciji; fotografije, obavještenje i mejl idu tek nakon commita.

## Dispečer (uloga: dispecer)

| GET | `/admin/dashboard` | KPI, rokovi koji padaju danas, raspored po prozorima, obnove uskoro. |
| GET | `/admin/jobs?status=&q=&per_page=` | Lista sa brojačima po stanju. |
| GET | `/admin/jobs/{id}` | Detalj + klijent + pretplata + stavke + račun + historija obavještenja. |
| PATCH | `/admin/jobs/{id}` | Dodjela i tranzicije: `{technician_id?, scheduled_window_start?, scheduled_window_end?, status?}`. |
| GET | `/admin/jobs/{id}/notification-preview?status=&technician_id=&scheduled_window_start=&scheduled_window_end=` | Tačan tekst obavještenja koje bi tranzicija poslala, bez slanja. |
| POST | `/admin/jobs/{id}/complete` | Isti ulaz i isti servis kao serviserov complete (dispečer unosi u ime majstora). |
| POST | `/admin/jobs/{id}/warranty-job` | `{description?, price_category_id?}`. Otvara garancijski nalog vezan na original. |
| GET | `/admin/clients?q=&per_page=`, GET `/admin/clients/{id}` | Klijenti + karton doma. |
| GET | `/admin/subscriptions?status=&q=` | Iskorištenost prava, obnove. |
| GET | `/admin/billing?status=&type=&q=` | Fakture, rad i materijal razdvojeni, brojači po stanju. |
| POST | `/admin/invoices/{id}/refund` | `{amount?}` pun ili djelimičan, ide kroz `PaymentGateway::refund`. |
| GET | `/admin/price-list` | Draft + objavljeno + `dirty` po redu. |
| PUT | `/admin/price-list/items/{id}` | `{draft_base_price}`. |
| POST | `/admin/price-list/publish` | Objavi sve draftove odjednom (web + mobile istovremeno). |
| CRUD | `/admin/cities` | POST validira BiH bounding box (lat 42–46, lng 15–20), novi grad je `u_pripremi`. PATCH `{status?, name?, lat?, lng?}`. DELETE 422 ako grad ima adrese. |
| GET/PUT | `/admin/settings` | Radno vrijeme, satnice, tiers, predlošci obavještenja. |
| GET/PUT | `/admin/surcharges`, `/admin/surcharges/{id}` | `{value, active}`. |
| CRUD | `/admin/technicians` | `{name, trade, active, email?, password?}`. |

### Detalji dispečerskih odgovora

**`GET /admin/dashboard`**

```
{kpi: {novi_danas, aktivni_nalozi, rokovi_danas, prosjek_zavrsetka_h, aktivne_pretplate},
 deadlines_today: [red naloga],
 schedule_today: [{window: "10:00 do 12:00", starts_at, ends_at, jobs: [red naloga]}],
 renewals_soon: [{id, client: {id, name, email}, package, ends_at, auto_renew, dana_do_isteka}]}
```

`prosjek_zavrsetka_h` je prosjek sati od prijave do završetka za naloge zatvorene u zadnjih 30 dana, `null` kad ih nema. `deadlines_today` su nezavršeni nalozi kojima `deadline_at` pada danas. `renewals_soon` su aktivne pretplate koje ističu u sljedećih 60 dana.

**`GET /admin/jobs`** > `{data: [red], meta: {counts: {novo, zakazano, u_toku, zavrseno, ukupno}, total, per_page, current_page, last_page}}`. Brojači prate pretragu `q`, ne filter `status`. `q` traži po broju naloga, opisu, imenu i mejlu klijenta i ulici. Red:

```
{id, number, status, type, is_emergency, category, title, client: {id, name, email},
 address: {city, street}, technician: {id, name}|null, scheduled_window_start,
 scheduled_window_end, deadline_at, deadline_missed_at, completed_at, created_at}
```

**`GET /admin/jobs/{id}`** > `{data}`: sva polja reda, plus `description`, `preferred_window`, `findings`, `warranty_until`, `visit_source`, `parent_job`, `contact: {name, note}`, `subscription: {id, status, package, ends_at, free_interventions, remaining_visits, remaining_inspections}`, `items[]`, `materials[]`, `photos[]`, `invoice`, `notifications: [{id, channel, template_key, body, sent_at}]`.

**`PATCH /admin/jobs/{id}`** > `{data: red naloga, notifications_sent: [template_key], message}`.

- Prozor stiže u paru i traje **tačno 2 sata**, u istom danu. Radno vrijeme dolazi iz `settings.radno_vrijeme`: pon-pet 08:00–18:00, subota 09:00–14:00. Nedjelja se odbija osim kad je `is_emergency`. Greška ide na `errors.scheduled_window_start`.
- `novo > zakazano` traži majstora i prozor. Šalje `termin_potvrdjen` sa `{dan}` (bosanski naziv dana), `{datum}` (dd.mm.gggg), `{od}`, `{do}`, `{majstor}`, `{broj}`.
- Novi prozor ili novi majstor na već zakazanom nalogu je dozvoljen i ponovo šalje `termin_potvrdjen`.
- `zakazano > u_toku` je dozvoljen (dispečer može umjesto majstora) i šalje `majstor_krenuo`, osim ako ga je majstor već poslao kroz `start`.
- `u_toku > zavrseno` **ne ide ovuda**: 422 "Nalog se zatvara nalazom. Koristite završetak naloga."
- Povratak u `novo` i izmjena završenog naloga: 422.
- Dodjela majstora bez stanja je dozvoljena i ne šalje ništa (korak `majstor_dodijeljen` u klijentskom prikazu).

**`GET /admin/jobs/{id}/notification-preview`** > `{data: {template_key, body}}`. `status` je obavezan. Za `zakazano` se koriste proslijeđeni `technician_id` i prozor, a što nije poslano uzima se sa naloga. Stanje bez obavještenja (`novo`): 422.

**`POST /admin/jobs/{id}/warranty-job`** > 201 `{data: red naloga, message}`. Original mora biti `zavrseno`, inače 422. Novi nalog nasljeđuje klijenta, pretplatu, adresu i kategoriju, tip je `garancija`, `parent_job_id` pokazuje na original, rok se računa iz paketa. Šalje `prijava_primljena`.

**`GET /admin/clients/{id}`** > `{data: {client, subscriptions[], properties[], jobs[], invoices[], home_records[]}}`. `home_records` je karton doma po svim adresama klijenta, najnoviji prvi.

**`GET /admin/subscriptions`** > red nosi `usage: {visits_total, visits_remaining, visits_used, inspections_total, inspections_remaining, inspections_used}`. Ukupno je pravo paketa pomnoženo brojem adresa, preostalo je zbir preko adresa.

**`POST /admin/invoices/{id}/refund`** > `{data: red fakture, message}`. Bez `amount` vraća cijeli još nevraćeni iznos. Faktura bez uspješne uplate: 422 na `errors.invoice`. Iznos veći od uplaćenog: 422 na `errors.amount`. Pun povrat: `invoices.status = refundirano` i uplata `refundiran`; djelimičan: `djelimicno_refundirano`, uplata ostaje `uspjesan`, `refunded_amount` se zbraja.

**`POST /admin/price-list/publish`** > `{data: {published, price_list_version}, message}`. `published` je broj redova kojima se cijena zaista promijenila. Publish kopira sve draftove u `base_price`, briše draftove i diže `price_list_version`.

**`GET/PUT /admin/settings`** > `{data}` sa ključevima `radno_vrijeme`, `satnica_redovna`, `satnica_hitna`, `izlazak_bez_pretplate`, `ukljuceno_minuta`, `materijal_marza_pct`, `pro_volume_tiers`, `notification_templates`, `dispecer_email`, plus `price_list_version` i `template_keys` (samo za čitanje). PUT je djelimičan: upisuje se samo ono što stigne. Kad stigne `notification_templates`, moraju biti prisutni svi ključevi iz `template_keys`, inače 422. Nijedan tekst ne smije sadržavati em dash.

**`CRUD /admin/technicians`** > red: `{id, name, trade, active, email, has_account, jobs_count, open_jobs_count}`. Uz `email` je obavezna i `password`; tada se pravi korisnik sa ulogom `majstor` i veže na majstora. DELETE je 422 kad majstor ima naloge; inače briše i vezani korisnički nalog.

## Dev (samo lokalno)

| POST | `/dev/fake-payment` | `{reference, outcome: approved\|declined}` > `{processed, subscription_status}` |

- Postoji samo kad je `services.haus.payment_gateway = fake` I `app.debug = true`. Inače 404, u produkciji je rute kao da nema.
- Bez auth-a i bez potpisa: stranica `/placanje/simulacija` je gađa odmah nakon 3DS redirecta, dok korisnik još nema aktivnu pretplatu.
- Sklapa isti payload koji šalje gateway (`approved` dodaje `masked_pan` "403940xxxxxx1881" i `token` "FAKE-TOKEN-{uuid}") i pušta ga kroz `PaymentProcessor`, isti kod put kao pravi webhook. Znači: aktivacija pretplate, knjiženje fakture, račun na mejl, obavještenje `pretplata_aktivna`, token za MIT obnovu.
- `processed` je `false` kad je ista uplata već proknjižena. Nepoznata referenca: 404.

## Konvencije

- Broj naloga: `HAUS-{godina}-{redni:04d}`, generiše server.
- Broj fakture: `{godina}{redni:06d}`, samo cifre, jer je ujedno poziv na broj na uplatnici. Generiše server.
- State chip mapping (oba klijenta, iz design/README.md): novo=ink/white, zakazano=bark/ivory, u_toku=ember/ivory tekst, zavrseno=sand/ink, garancija=ink fill/ivory tekst.
- Paginacija: Laravel standard `{data, links, meta}` na listama gdje treba.
- Sve mutacije koje mijenjaju stanje naloga upisuju red u `notifications_log` (šta je poslano, kome, kojim kanalom). Tabela se zove `notifications_log` jer Laravel rezerviše `notifications`.
- `prices` mapa u cjenovniku je keyed po slugu paketa bez prefiksa (`mini`, `plus`, `pro`); klijenti čitaju mapu dinamički, ne fiksna tri ključa. JSON brojevi mogu stići kao int ili float, bez strict type checka.
