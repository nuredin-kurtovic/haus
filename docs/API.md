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
| POST | `/auth/register` | Body: `{package_id, name, email, password, payment_method: uplatnica|kartica, properties: [{city_id, street, use?, contact?}]}`. Mini/Plus šalju tačno 1 property. Pro 2+; 10+ vraća `{status: ponuda}`. Odgovor: `{user, subscription, token, payment?: {redirect_url}}` (redirect samo za karticu). |
| POST | `/auth/login` | `{email, password}` > `{user, token, role}` |
| POST | `/webhooks/monri` | Monri status webhook (signature verified, ne Bearer). Aktivira pretplatu / knjiži uplatu mašinski. |

## Auth zajedničko

| GET | `/me` | User + uloga + aktivna pretplata (sažetak). |
| POST | `/auth/logout` | Revoke token. |
| POST | `/devices` | `{fcm_token, platform}` registracija za push. |

## Klijent (uloga: klijent)

| GET | `/client/dashboard` | `{subscription: {package, ends_at, remaining_visits, free_interventions, remaining_inspections}, active_job: {id, number, status, steps}, recent_jobs: []}` |
| POST | `/client/jobs` | `{price_category_id, description, is_emergency, preferred_window, subscription_property_id?}` + opciono multipart `photo`. Odgovor: `{job: {id, number, deadline_at}}`. Odbija ako pretplata nije aktivna. |
| GET | `/client/jobs` | Lista: `{id, number, status, type, category, title, technician_name, scheduled_window, warranty_until}` |
| GET | `/client/jobs/{id}` | Detalj + `findings`, `photos: [{type, url}]`, `invoice: {labor_items[], materials[], labor_total, material_total, total}` |
| GET | `/client/subscription` | Paket, prava, iskorišteno, historija plaćanja, `auto_renew`. |
| POST | `/client/subscription/cancel` | Jedan klik, gasi auto_renew. |
| GET | `/client/price-list` | Kao public, plus `my_price` po poziciji. |
| GET/PUT | `/client/profile` | Kontakt polja + `notifications: {push, email, marketing}`. Adresa se NE mijenja ovdje. |
| POST | `/client/address-change-request` | `{message}` > zahtjev dispečeru. |

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

## Konvencije

- Broj naloga: `HAUS-{godina}-{redni:04d}`, generiše server.
- State chip mapping (oba klijenta, iz design/README.md): novo=ink/white, zakazano=bark/ivory, u_toku=ember/ivory tekst, zavrseno=sand/ink, garancija=ink fill/ivory tekst.
- Paginacija: Laravel standard `{data, links, meta}` na listama gdje treba.
- Sve mutacije koje mijenjaju stanje naloga upisuju red u `notifications_log` (šta je poslano, kome, kojim kanalom). Tabela se zove `notifications_log` jer Laravel rezerviše `notifications`.
- `prices` mapa u cjenovniku je keyed po slugu paketa bez prefiksa (`mini`, `plus`, `pro`); klijenti čitaju mapu dinamički, ne fiksna tri ključa. JSON brojevi mogu stići kao int ili float, bez strict type checka.
