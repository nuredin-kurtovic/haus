# HAUS: prompt za implementaciju

Dva zadatka, jedan domen. Web je **Laravel 11 + Vue 3** (Inertia ili SPA + REST, izbor je na timu). Mobilna aplikacija je **React Native, React Native CLI setup** (ne Expo). Backend je isti za oba: Laravel API je jedini izvor istine.

Prvo pročitajte dio 1 (domen). Bez njega ni web ni mobile prompt ne znače ništa.

---

## 1. Domen i logika (vrijedi za oba)

### 1.1 Šta je proizvod

HAUS je **godišnja pretplata na održavanje doma**. Klijent plati unaprijed i dobije pravo na određen broj izlazaka majstora, garantovan rok izlaska, popust na rad i pregled instalacija. Prijava kvara ide isključivo kroz aplikaciju ili web. **Nema telefonskih poziva i nema plaćanja gotovinom.**

Tri obećanja koja sistem mora tehnički garantovati:

1. **Poznata cijena.** Cjenovnik je javan i isti za sve. Majstor otvori poziciju iz cjenovnika, klijent je potvrdi, tek onda ide rad.
2. **Dogovoren rok.** Rok izlaska ide iz paketa. Ako rok padne, sistem **automatski** upisuje jednu besplatnu intervenciju i obavještava klijenta prije nego on pita.
3. **Pisana garancija.** Poslije svake intervencije, u roku od 24 sata, klijent dobija izvještaj sa slikama prije i poslije i datumom do kojeg traje garancija.

### 1.2 Paketi

| Paket | Cijena/god | Izlasci | Rok | Hitno | Popust na rad | Popust na materijal | Pregled | Garancija |
|---|---|---|---|---|---|---|---|---|
| HAUS Mini | 59 KM | 1 | 72 h | 24 h uz doplatu | 15% | Nema | Nema | 6 mjeseci |
| HAUS Plus | 169 KM | 3 | 48 h | 12 h bez doplate | 25% | 5% | 1× | 12 mjeseci |
| HAUS Pro | 390 KM po stanu | 5 po stanu | 24 h | 6 h bez doplate | 25% | 5% | 2× po stanu | 12 mjeseci |

Pravila:

- Cijene i sadržaj paketa **nisu konačni**. Sve mora biti u bazi i uređivano iz admin panela, nikad hardkodirano.
- **Nema karence.** Pretplata radi od momenta uplate. Prva prijava kvara može odmah.
- Neiskorišteni izlasci se **ne prenose** u sljedeću godinu.
- Pretplata je vezana za **jednu adresu** i nije prenosiva. HAUS Pro je izuzetak: jedan ugovor, više stanova, svaki stan ima svoje izlaske i svoj pregled.
- HAUS Pro popust na količinu: 2 do 4 stana 10%, 5 do 9 stanova 15%, 10 i više po dogovoru (tada nema automatske naplate, ide zahtjev za ponudu).
- Obnova je automatska. Podsjetnik ide 60 dana prije isteka. Otkazivanje je jedan klik u profilu.

### 1.3 Cjenovnik i naplata rada

- Cjenovnik ima kategorije (Vodoinstalacije, Elektroinstalacije, Grijanje, Klima uređaji, Bravarija i stolarija, Sitni poslovi) i pozicije sa cijenom rada bez pretplate.
- Cijena za pretplatnika se **računa iz osnovne cijene i popusta paketa**, nikad se ne upisuje ručno. Zaokruživanje na cijeli broj.
- Uključeno u jedan izlazak: dolazak, dijagnostika, do 45 minuta rada, pisana konstatacija stanja.
- Nije uključeno: materijal (nabavna cijena + 20%, uz popust paketa), rad iznad 45 minuta (snižena satnica), adaptacije i renoviranje, zajednički dijelovi zgrade, radovi koji traže dozvolu ili skelu, oprema pod garancijom proizvođača, posljedične štete.
- Satnice: 40 KM/h redovan rad, 70 KM/h hitno i van radnog vremena. Izlazak bez pretplate 35 KM.
- Doplate su procentualne na rad i moraju se moći uređivati: hitno u toku radnog dana +30%, radnim danom 18:00 do 22:00 +40%, subota poslije 14:00 +40%, nedjelja i praznik +70%, noć 22:00 do 07:00 +100%, izvan gradskog područja 1,20 KM po kilometru, uzaludan izlazak 35 KM.
- Radno vrijeme: radnim danima 08:00 do 18:00, subotom 09:00 do 14:00. Nedjelja samo hitno.

### 1.4 Gradovi

Gradovi su **podatak u bazi**, ne lista u kodu. Stanja: `aktivan` i `u_pripremi`.

- Aktivan grad se pojavljuje u listi pri registraciji i na javnoj mapi kao ispunjen pin.
- Grad u pripremi se vidi na mapi ali se u njemu **ne može pretplatiti**.
- Registracija prihvata samo adrese u aktivnim gradovima. Grad je select, nikad slobodan unos.
- Trenutno aktivni: Sarajevo, Travnik. Ostali gradovi su u pripremi.

### 1.5 Životni ciklus naloga (intervencije)

Stanja: `novo` → `zakazano` → `u_toku` → `zavrseno`, plus `garancija` kao poseban tip naloga koji se ne naplaćuje.

1. **Novo.** Klijent prijavi kvar: kategorija, opis, opciona fotografija, oznaka hitno, željeni termin. Sistem izračuna `rok_do` iz paketa i oznake hitnosti i zapiše ga na nalog.
2. **Zakazano.** Dispečer dodijeli majstora i potvrdi termin. Termin je **prozor od dva sata**, nikad "poslije podne". Klijent dobija obavještenje.
3. **U toku.** Majstor je krenuo. Klijent dobija obavještenje.
4. **Završeno.** Majstor upiše nalaz, dodaje slike prije i poslije, izabere pozicije iz cjenovnika i materijal. Sistem izračuna račun, upiše datum garancije i **u roku od 24 sata** pošalje izvještaj na mejl.
5. **Garancija.** Ako se isti kvar ponovi u garantnom roku, nalog se otvara kao garancijski i ne naplaćuje se.

Automatika koju backend mora imati:

- **Cron koji prati rokove.** Kad `rok_do` prođe a nalog nije završen, sistem upisuje klijentu jednu besplatnu intervenciju i šalje obavještenje. Bez ljudske intervencije.
- Brojač preostalih izlazaka se umanjuje kad nalog pređe u `zavrseno`, ne pri prijavi.
- Ako klijent nema preostalih izlazaka, nalog se ipak prima, ali se naplaćuje po cjenovniku sa popustom paketa.

### 1.6 Plaćanje (Monri)

Dva načina, ništa drugo:

1. **Uplatnica na mejl.** Uplatnicu i račun šaljemo na mejl. Pretplata je aktivna kad uplata legne.
2. **Kartica preko Monri.** Aktivacija odmah, račun na mejl istog trenutka.

Zahtjevi:

- Tokenizacija kartice. Podaci o kartici **nikad ne ulaze** u našu bazu, čuvamo samo token.
- Naplata obnove po tokenu, bez prisustva klijenta (MIT), na godišnjem ciklusu.
- 3-D Secure 2 za prvu transakciju.
- Webhook o statusu transakcije. Pretplata se aktivira mašinski, u sekundi. Bez webhooka se obećanje "prva prijava odmah" ne može držati.
- Refund preko API-ja, puni i djelimični, iniciran iz admin panela.
- Svaka transakcija se veže na naš `subscription_id` ili `job_id` zbog knjiženja.
- Podrška za kartice izdate izvan BiH (dijaspora).

### 1.7 Uloge

- **Gost.** Javne stranice, cjenovnik, mapa gradova, registracija.
- **Klijent.** Prijava kvara, svoje intervencije, karton doma, pretplata, cjenovnik sa svojim cijenama, profil.
- **Majstor.** Svoji nalozi, nalaz, slike, pozicije iz cjenovnika. (Faza 2, ali model to mora predvidjeti.)
- **Dispečer.** Svi nalozi, dodjela majstora, promjena stanja, klijenti, pretplate, naplata, cjenovnik, gradovi, postavke.

### 1.8 Ton komunikacije

Kratke rečenice. Uvijek "Vi". Konkretno vrijeme, nikad "uskoro". Brojevi umjesto pridjeva. Loše vijesti šaljemo mi, prvi, i uz rješenje. **Nikad em dash u tekstu.**

Primjeri obavještenja:

- Termin: `HAUS: Termin potvrđen. Srijeda 06.08, između 10:00 i 12:00. Majstor: Damir. Otkazivanje u aplikaciji.`
- Kašnjenje: `HAUS: Kasnimo 40 min, prethodni nalog se otegao. Novo vrijeme: 15:20. Izvinjavamo se.`
- Rok probijen: `HAUS: Nismo ispunili obećani rok. Vaša sljedeća intervencija je besplatna, već je upisana.`
- Poslije rada: `HAUS: Sređeno. Garancija na rad do 05.08.2027. Nalaz i fotografije su u vašem kartonu.`

### 1.9 Vizuelna pravila (binding, oba klijenta)

- Font: **Poppins**, ništa drugo.
- Boje: ink `#252422`, ember `#FE5100`, ivory `#FFFCF2`, sand `#CCC5B9`, bark `#403D39`, bijela `#FFFFFF`.
- **Nema zaobljenih uglova nigdje u UI-u.** `border-radius: 0`.
- Ember je **ploha**, nikad tanka linija.
- Ember i ink se **nikad ne dodiruju**. Između njih je bijelo ili ivory polje.
- Nema svijetlog teksta na sandu. Nema sitnog teksta direktno na emberu.
- Cifre uvijek `font-variant-numeric: tabular-nums`.
- Logo: `logo-primary.svg` na svijetloj podlozi, `logo-ivory.svg` na ink podlozi. Bez taglinea.

---

## 2. Prompt: web (Laravel 11 + Vue 3)

Napravi web aplikaciju za HAUS po domenu iz dijela 1.

### Stack

- Laravel 11, PHP 8.3, MySQL ili PostgreSQL.
- Vue 3 (Composition API) + Vite. Inertia.js za javni i klijentski dio, ili SPA sa REST API-jem ako tim tako odluči. Odluku dokumentuj u README.
- Autentifikacija: Laravel Breeze ili Fortify. Uloge preko `spatie/laravel-permission`.
- Queue: Redis + Horizon. Scheduler za cron zadatke.
- Mape: Leaflet + OpenStreetMap tiles. Nikad ručno crtana geografija.
- Plaćanje: Monri SDK ili HTTP integracija, u zasebnom `PaymentService`.

### Struktura ekrana

**Javno:** Naslovna, Proizvod (kako radi), Cijene, Cjenovnik radova (pretraživ, po kategorijama), Gdje radimo (Leaflet mapa sa pinovima gradova), Česta pitanja, Kontakt. Uslovi korištenja su u podnožju, ne u glavnom meniju.

**Registracija:** tok u dva koraka. Korak 1: paket, ime, mejl, grad (select samo aktivnih), ulica. Za HAUS Pro se umjesto jedne adrese unosi **lista stanova** sa stepperom za broj stanova, i po stanu grad, ulica, namjena, kontakt na adresi. Korak 2: način plaćanja. Sažetak pretplate stoji u ink koloni sa strane cijelo vrijeme i prikazuje izračunatu cijenu, uključujući popust na količinu za Pro.

**Klijent:** Početna (stanje pretplate, veliko dugme za prijavu kvara, nalog u toku sa koracima, zadnje intervencije), Prijavi kvar (tok u tri koraka, ili jedan ekran, oba su prihvatljiva), Moje intervencije, Nalog sa nalazom i slikama, Moja pretplata, Cjenovnik sa svojim cijenama, Profil.

**Admin (dispečer):** Danas (rokovi koji padaju, raspored po prozorima), Nalozi (filter po stanju, detalj sa dodjelom majstora i promjenom stanja, prikaz obavještenja koje ide klijentu), Klijenti, Pretplate, Naplata (rad i materijal razdvojeni), Cjenovnik sa uređivanjem i stanjem "nije objavljeno" do objave, Gradovi (mapa, lista, dodavanje, prebacivanje aktivan/u pripremi), Postavke (rokovi po paketu, radno vrijeme, doplate, predlošci obavještenja).

### Model podataka (minimum)

`users`, `roles`, `cities`, `packages`, `subscriptions`, `subscription_properties` (za Pro), `price_categories`, `price_items`, `surcharges`, `jobs`, `job_items`, `job_materials`, `job_photos`, `technicians`, `invoices`, `payments`, `payment_tokens`, `notifications`, `settings`, `home_records` (HAUS Karton).

Ključna pravila u modelu:

- `packages` nosi sve parametre (cijena, broj izlazaka, rok u satima, hitni rok, popust na rad, popust na materijal, broj pregleda, mjeseci garancije). Promjena paketa je promjena reda u bazi.
- `price_items` čuva samo osnovnu cijenu. Cijena za pretplatnika je izračunata vrijednost.
- `jobs.deadline_at` se izračuna pri kreiranju i ne mijenja se kad se mijenja paket.
- `subscriptions.remaining_visits` se umanjuje na prelazu u `zavrseno`.

### Posebno paziti

- Cjenovnik na webu i cjenovnik na telefonu majstora se objavljuju **u isto vrijeme**, nikad odvojeno.
- Sve cijene u KM, sa PDV-om, tabularne cifre.
- Nema em dasha u seedovima, prijevodima ni u UI tekstu.
- Validacija adrese odbija grad koji nije aktivan, i na frontendu i na backendu.

---

## 3. Prompt: mobilna aplikacija (React Native CLI)

Napravi mobilnu aplikaciju za HAUS po domenu iz dijela 1. Aplikacija je **glavni kanal** za klijenta.

### Stack

- React Native, **React Native CLI** setup (`npx @react-native-community/cli init`), ne Expo. Objasni u README kako se pokreće iOS i Android.
- TypeScript.
- Navigacija: `@react-navigation/native`, native-stack + bottom-tabs.
- Server state: TanStack Query. Lokalni state: Zustand ili Context, bez Reduxa.
- Forme: React Hook Form + Zod.
- Slike: `react-native-image-picker` za kameru i galeriju, upload kao multipart.
- Push: Firebase Cloud Messaging preko `@react-native-firebase/messaging`, plus `notifee` za prikaz.
- Sigurno čuvanje tokena: `react-native-keychain`.
- Mapa (ako treba u aplikaciji): `react-native-maps`.
- Plaćanje: Monri SDK ako postoji za RN. Ako ne postoji, siguran web tok u `react-native-webview` sa deep link povratkom, nikad izlazak u vanjski pretraživač.
- Font Poppins ugrađen u `android/app/src/main/assets/fonts` i preko `Info.plist` za iOS.

### Ekrani i tokovi

**Prvi kontakt (bez prijave):**

1. `Welcome`. Imenuje problem, ne objašnjava proizvod. Naslov u tri kratka reda, jedna rečenica pod njim, fotografija majstora poravnata na dno ekrana, dva dugmeta: `Pretplatite se` i `Imam pretplatu`.
2. `Kako radi`. Tri koraka, jedan po ekranu, sa trakom napretka. Korak 1 prijava, korak 2 termin i cijena, korak 3 trag i garancija. Dugme `Preskoči` uvijek dostupno.
3. `Prijava`. Mejl i lozinka. Nema unosa telefona. Diskretan ulaz za dispečera na dnu.

**Registracija (tri koraka, sa trakom napretka):** izbor paketa (kartice sa cijenom i jednom rečenicom), podaci i adresa (grad je select samo aktivnih gradova, za Pro stepper broja stanova i po stanu grad i ulica), plaćanje (dva načina, sažetak iznad). Poslije uspjeha ekran `Pretplata je aktivna` koji odmah kaže da prva prijava može sada.

**Klijent (bottom tabs: Početna, Prijavi, Nalozi, Pretplata, Profil):**

- `Početna`: pozdrav, četiri kartice stanja pretplate, veliko dugme za prijavu kvara, nalog u toku sa koracima (prijava primljena, majstor dodijeljen, termin potvrđen, majstor krenuo), lista zadnjih intervencija.
- `Prijavi kvar`: tri koraka. Korak 1 kategorija (zadnja opcija je "Ne znam kako se zove"). Korak 2 opis, fotografija, checkbox hitno. Korak 3 termin. Poslije toga ekran sa brojem naloga i rokom.
- `Nalozi`: lista sa stanjem, datumom, zanatom i majstorom. Detalj nosi nalaz, slike prije i poslije, i račun razdvojen na rad i materijal.
- `Pretplata`: paket, šta je uključeno, godišnji pregled, historija plaćanja, obnova.
- `Profil`: adresa sa zahtjevom za promjenu, tri prekidača za obavještenja, odjava.

**Dispečer (bottom tabs: Danas, Nalozi, Gradovi):** dnevni raspored i rokovi koji padaju, lista naloga sa filterom po stanju, detalj sa dodjelom majstora i promjenom stanja koji prikazuje tekst obavještenja koje klijent dobija, lista gradova sa prebacivanjem stanja i dodavanjem novog grada u stanje "u pripremi".

### UI pravila za mobile

- Svaki dodirni element minimalno **44 px** visine.
- Nema zaobljenja u UI-u. Zaobljeno je samo kućište telefona, što nije naša briga.
- Tekst nikad manji od 13 px, tijelo 15 do 17 px.
- Trake napretka su pravougaone plohe, ember za pređene korake, sand za ostatak.
- Ember plohe se koriste za jedan poziv na akciju po ekranu, ne više.
- Ink zaglavlja samo na ekranima koji nose potvrdu ili sumu (pretplata aktivna, prijava primljena, dispečer Danas).
- Offline: lista naloga i cjenovnik moraju biti čitljivi iz keša. Prijava kvara zahtijeva mrežu i to jasno kaže.

### Posebno paziti

- Push obavještenja idu iz backenda, po predlošcima iz dijela 1.8. Aplikacija ih samo prikazuje.
- Deep link iz obavještenja vodi direktno na nalog.
- Sve cijene se dobijaju sa servera već izračunate. Klijent nikad ne računa popust lokalno.
- Nema em dasha u stringovima ni u prijevodima.

---

## 4. Redoslijed implementacije

1. Baza, modeli, seed paketa, cjenovnika, gradova i doplata.
2. Auth, uloge, registracija bez plaćanja (uplatnica na mejl).
3. Javni web: naslovna, cijene, cjenovnik, mapa gradova.
4. Klijentski web: prijava kvara i nalozi.
5. Admin: nalozi, dodjela, stanja, gradovi, cjenovnik sa objavom.
6. Monri: kartica, webhook, tokenizacija, obnova, refund.
7. Cron za rokove i besplatnu intervenciju, generisanje izvještaja u roku od 24 sata.
8. Mobilna aplikacija na istom API-ju.

Faza 6 je uslov da model radi. Ne pušta se u produkciju bez nje.
