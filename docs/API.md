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
| POST | `/auth/register` | Body: `{package_id, name, email, password, payment_method: uplatnica|kartica, properties: [{city_id, street, use?, contact_name?, contact_note?}]}`. Mini/Plus šalju tačno 1 property. Pro 2+; 10+ vraća `{status: ponuda}`. Odgovor 201: `{status, user, subscription, token, invoice?, payment?: {redirect_url}}`. `invoice` izostaje kod ponude, `payment` samo za karticu. |
| POST | `/auth/login` | `{email, password}` > `{user, token, role}`. Pogrešni kredencijali: 422 na `errors.email`. |
| POST | `/webhooks/monri` | Monri status webhook (signature verified, ne Bearer). Body `{reference, status: approved|declined, masked_pan?, token?}`. Aktivira pretplatu / knjiži uplatu mašinski, idempotentno. Loš potpis: 403. |

### Detalji registracije

- `status` u odgovoru je `subscriptions.status`: `cekanje_uplate` ili `ponuda`.
- `subscription` nosi `{id, status, package: {id, name, slug, is_per_apartment}, starts_at, ends_at, auto_renew, price, price_paid, free_interventions, remaining_visits, remaining_inspections, properties[]}`. `price` je izračunata godišnja cijena (Pro: cijena po stanu x broj stanova uz tier popust). `starts_at` i `ends_at` su `null` dok uplata ne legne.
- `token` stiže odmah, i dok pretplata čeka uplatu. Guard koji traži aktivnu pretplatu je na `/client` rutama.
- Uplatnica: server šalje mejl sa iznosom i pozivom na broj, bez `payment` objekta u odgovoru.
- Kartica: `payment.redirect_url` vodi na 3DS. Lokalno FakeGateway vodi na `/placanje/simulacija?ref={reference}`.

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

## Dispečer (uloga: dispecer)

| GET | `/admin/dashboard` | KPI, rokovi koji padaju danas, raspored po prozorima, obnove uskoro. |
| GET | `/admin/jobs?status=&q=` | Lista sa brojačima po stanju. |
| GET | `/admin/jobs/{id}` | Detalj + klijent + pretplata + historija. |
| PATCH | `/admin/jobs/{id}` | Dodjela i tranzicije: `{technician_id?, scheduled_window_start?, scheduled_window_end?, status?}`. Prozor je tačno 2h. Validne tranzicije: novo>zakazano (traži majstora i prozor), zakazano>u_toku, u_toku>zavrseno (traži nalaz; stavke idu posebnim endpointom). Svaka tranzicija šalje obavještenje klijentu po predlošku. |
| GET | `/admin/jobs/{id}/notification-preview?status=` | Tačan tekst obavještenja koje bi klijent dobio za tu tranziciju. |
| POST | `/admin/jobs/{id}/complete` | `{findings, items: [{price_item_id, qty}], materials: [{name, purchase_price, qty}]}` + multipart `photos_before[]`, `photos_after[]`. Server računa račun, garanciju, troši kredit/izlazak, šalje izvještaj. |
| POST | `/admin/jobs/{id}/warranty-job` | Otvara garancijski nalog vezan na original. |
| GET | `/admin/clients`, GET `/admin/clients/{id}` | Klijenti + karton doma. |
| GET | `/admin/subscriptions` | Iskorištenost, obnove; filteri. |
| GET | `/admin/billing` | Fakture, rad i materijal razdvojeni, state chipovi. |
| POST | `/admin/invoices/{id}/refund` | `{amount?}` pun ili djelimičan, ide kroz PaymentService. |
| GET | `/admin/price-list` | Draft + objavljeno + dirty flag po redu. |
| PUT | `/admin/price-list/items/{id}` | `{draft_base_price}`. |
| POST | `/admin/price-list/publish` | Objavi sve draftove odjednom (web + mobile istovremeno). |
| CRUD | `/admin/cities` | POST validira BiH bounding box (lat 42–46, lon 15–20), novi grad je `u_pripremi`. PATCH `{status}`. DELETE samo bez pretplata. |
| GET/PUT | `/admin/settings` | Radno vrijeme, satnice, doplate, tiers, predlošci obavještenja. |
| CRUD | `/admin/technicians` | Ime, zanat, aktivan. |

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
