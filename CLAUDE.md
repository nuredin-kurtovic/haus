# HAUS monorepo

Godišnja pretplata na održavanje doma za BiH. Tri obećanja koja sistem tehnički garantuje: poznata cijena, dogovoren rok, pisana garancija. Nema telefonskih poziva, nema gotovine.

## Layout

- `web/` — Laravel 12 (PHP 8.4) backend + Vue 3 SPA (Vite). Jedini izvor istine. REST API pod `/api/v1`.
- `mobile/` — React Native 0.86 (CLI setup, ne Expo), TypeScript. Ide na isti API.
- `design/` — dizajn handoff. `design/README.md` je binding vizuelna referenca (tokeni, ekrani, chip stilovi). `design/HAUS-Build-Prompt.md` je domen spec. Prototipovi `*.dc.html` su referenca, ne kod za kopiranje.
- `docs/` — ARCHITECTURE.md (odluke), API.md (REST ugovor). API.md je ugovor između weba i mobile: ne mijenjaj ga bez ažuriranja oba klijenta.

## Pravila koja su ugovorna (kršenje = bug)

- **Nikad em dash (—)** ni u jednom stringu: UI, seedovi, prijevodi, komentari koji idu korisniku. En dash u numeričkim rasponima (08:00–18:00) je dozvoljen.
- Jezik korisničkog teksta: bosanski, kratke rečenice, uvijek "Vi", brojevi umjesto prideva.
- Poppins jedini font. `border-radius: 0` svugdje. Ember `#FE5100` je ploha, nikad tanka linija. Ember i ink `#252422` se nikad ne dodiruju (između je bijelo ili ivory `#FFFCF2`). Nema svijetlog teksta na sandu `#CCC5B9`. Nema sitnog teksta direktno na emberu. Cifre uvijek `font-variant-numeric: tabular-nums`. Nema sjenki u UI-u.
- Sve cijene, paketi, gradovi, doplate, rokovi, predlošci obavještenja su **podaci u bazi**, uređivani iz admin panela. Nikad hardkodirano.
- Snižene cijene se **računaju** iz osnovne cijene i popusta paketa (zaokruženo na cijeli KM), nikad se ne upisuju i ne čuvaju. Mobile i web ih dobijaju izračunate sa API-ja.
- Grad je uvijek select aktivnih gradova; validacija i na frontendu i na backendu.

## Dev okruženje (macOS, lokalno)

- npm/node su iz nvm-a i NISU na PATH-u u non-interactive shellu. Svaka komanda koja treba node/npm/npx mora prvo: `export PATH="$HOME/.nvm/versions/node/v24.12.0/bin:$PATH"`
- MySQL 9.5 radi lokalno (brew services), baza `haus`, user `root` bez lozinke. Redis radi lokalno (queue + Horizon).
- Web: `cd web && php artisan serve` + `npm run dev`. Testovi: `php artisan test`.
- Mobile: `cd mobile && npm run ios` ili `npm run android`.
- Laravel je **12**, ne 11 kako kaže spec: sve 11.x verzije imaju otvorene security advisorije (11 je EOL od marta 2026). Odluka dokumentovana u docs/ARCHITECTURE.md.
