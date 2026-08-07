# Handoff: HAUS, pretplata na održavanje doma

## Overview

HAUS is an annual home-maintenance subscription for Bosnia and Herzegovina. A household pays once a year and gets a fixed number of technician visits, a guaranteed response deadline, a discount on labour, and an annual inspection of installations. Everything runs through the web app and the mobile app: fault reports, appointments, notifications, dispatcher contact. There are **no phone calls and no cash payments**.

This bundle contains three design references and one build specification:

| File | What it is |
|---|---|
| `HAUS Prototip.dc.html` | Full desktop web prototype: public site, registration, client area, dispatcher admin |
| `HAUS Aplikacija.dc.html` | Mobile app prototype, 19 screens in a device frame: onboarding, registration, client, dispatcher |
| `HAUS Monri Zahtjev.dc.html` | Printable document requesting card-payment integration from Monri Payments |
| `HAUS-Build-Prompt.md` | **Read this first.** Complete domain logic, data model, and per-stack implementation brief |

## About the design files

The `.dc.html` files in this bundle are **design references created in HTML**. They are prototypes that show intended look, copy, and behaviour. They are **not production code to copy directly**.

The task is to **recreate these designs in the target codebase**:

- **Web:** Laravel 11 + PHP 8.3 + Vue 3 (Composition API) + Vite. Inertia.js or SPA with a REST API, team's choice.
- **Mobile:** React Native with the **React Native CLI** setup (not Expo), TypeScript.

Both clients talk to the same Laravel API, which is the single source of truth. `HAUS-Build-Prompt.md` carries the full brief for both, including the data model, the job lifecycle, the payment requirements, and the implementation order. Everything below is the visual and interaction reference that complements it.

The prototypes are built as single-file HTML components with a small runtime (`support.js`). Do not port that runtime. Read the markup and the logic class for structure, copy, and state, then rebuild in Vue and React Native.

## Fidelity

**High fidelity.** Colours, typography, spacing, copy, and interaction states are final and binding. The brand rules below are not suggestions: they come from the client's brandbook and are contractual. Recreate the UI faithfully using the target stack's own primitives.

The only deliberately unfinished parts:

- Photographs are drop targets (`<image-slot>`) in the prototype. Real photography is coming from the client. Placeholder copy in each slot says what belongs there.
- Package names and prices are live in the design but **not final**. They must be database rows editable from the admin panel, never hardcoded.

---

## Design tokens

### Colours

| Token | Hex | Use |
|---|---|---|
| ink | `#252422` | Body text, dark planes, admin header, footers |
| ember | `#FE5100` | Primary action, accent planes, active step markers |
| ember dark | `#D94500` | Ember hover only |
| ivory | `#FFFCF2` | Soft field, selected card background, text on dark |
| sand | `#CCC5B9` | Borders, dividers, disabled fill, inactive progress |
| bark | `#403D39` | Secondary text, muted labels |
| grey | `#8A857E` | Tertiary text on ink backgrounds only |
| white | `#FFFFFF` | Page background, card background |
| zebra | `#FDFCF8` | Alternating table rows |
| error | `#B03000` | Validation messages and error borders |

### Binding brand rules

These are hard constraints. Breaking them is a bug:

1. **No rounded corners anywhere in the UI.** `border-radius: 0` on every element. The only rounding in the mobile prototype is the simulated phone chassis, which is scaffolding, not product.
2. **Ember is a plane, never a thin line.** No 1px ember borders, no ember underlines as decoration, no ember icon strokes on light backgrounds. Ember appears as a filled area, a 3px to 4px state rule on a selected card, or a text-decoration accent under a link.
3. **Ember and ink never touch.** There is always a white or ivory field between them. The pattern used throughout: an ember block with 2px to 3px padding wrapping an ivory inner block.
4. **No light text on sand.** Sand is a border and a fill for disabled or completed states, with ink text on it.
5. **Small text never sits directly on ember.** Headlines at 26pt and above may sit on ember in ivory. Body copy goes in an ivory panel inside the ember plane.
6. **Poppins only**, weights 300, 400, 500, 600, 700. No second family, no icon font.
7. **Tabular numerals** on every number: prices, dates, times, counts, coordinates. `font-variant-numeric: tabular-nums`.
8. **No em dashes in any copy**, anywhere: UI strings, seeds, translations, table cells, bullet glyphs. Use periods, commas, colons, or restructure. En dashes in numeric ranges (`08:00–18:00`, `6–12`) are correct and expected.

### Typography

Poppins throughout, loaded from Google Fonts. Body weight is 300, which is unusual and intentional: the design reads light with heavy headlines.

**Web (desktop, 1240px content column):**

| Role | Size | Weight | Line height | Letter spacing |
|---|---|---|---|---|
| Hero headline | 66px | 700 | 1.02 | -0.02em |
| Page H1 | 60px | 700 | 1.05 | -0.02em |
| Registration H1 | 46px | 700 | 1.05 | -0.02em |
| Section H2 | 44px | 700 | 1.05 | -0.015em |
| Admin H1 | 36px | 700 | 1.0 | -0.01em |
| Card H3 | 24px to 26px | 700 | 1.15 to 1.2 | normal |
| Hero subline | 26px | 400 | 1.35 | normal |
| Lead paragraph | 20px to 22px | 400 | 1.45 to 1.5 | normal |
| Body | 15px to 17px | 400 | 1.5 to 1.65 | normal |
| Small print | 13px to 14px | 400 | 1.5 | normal |
| Eyebrow label | 12px to 15px | 600 | 1.0 | .12em, uppercase |
| Table header | 12px | 600 | 1.0 | .06em, uppercase |
| Field label | 12px to 13px | 500 or 600 | 1.0 | .06em to .08em, uppercase |

**Mobile (390 × 844 logical viewport):**

| Role | Size | Weight |
|---|---|---|
| Welcome headline | 35px | 700 |
| Screen H2 | 30px to 34px | 700 |
| Section H2 | 26px | 700 |
| Card H3 | 17px to 24px | 600 to 700 |
| Body | 15px to 17px | 400 |
| Meta / caption | 13px to 14px | 400 |
| Eyebrow | 11px to 12px | 600, .08em to .1em, uppercase |
| Tab label | 11px | 400 or 600 |

Minimum sizes: body never below 13px on mobile, 13px on web. Every touch target on mobile is **at least 44px tall**; primary buttons are 52px.

### Spacing

No formal scale. Observed rhythm, in px:

- Web section padding: 96px vertical, 20px horizontal inside a 1240px column.
- Web admin: 44px top, 80px bottom, 28px horizontal inside a 1440px column.
- Card padding: 26px to 36px.
- Form field gap: 18px to 20px; label to input 7px to 8px.
- Grid gutters: 1px for hairline-separated cell grids (background `#CCC5B9` showing through), 12px for choice tiles, 24px to 32px for cards, 48px to 80px for column layouts.
- Mobile screen padding: 22px to 26px horizontal.

### Borders, shadows, motion

- Borders: `1px solid #CCC5B9` for resting, `1px solid #252422` for emphasis or selected, `3px solid #252422` for a heavy top rule, `3px` or `4px` ember for an active state rule.
- **No shadows in the UI.** The only shadow in the prototypes is on the simulated phone chassis and on the toast, and neither is product chrome.
- Transitions: `.2s ease` on `background`, `color`, `border-color`, `opacity`. Nothing else animates. Honour `prefers-reduced-motion`.
- Focus: `outline: 2px solid #FE5100; outline-offset: 2px`.
- Links: underline in ember, `text-decoration-thickness: 1px`, offset 3px; on hover thickness 3px, colour unchanged.

---

## Screens: web (`HAUS Prototip.dc.html`)

A prototype-only surface switcher sits at the top: an ink bar, 46px tall, with Javno / Registracija / Klijent / Admin plus a link to an internal notes page. **This bar is scaffolding and must not ship.**

### Public surface

Sticky white header, 88px tall, 1px sand bottom border, sitting below the switcher. Contents left to right: logo (176 × 38, `logo-primary.svg`, no tagline), nav buttons (14px, weight 400, weight 600 when active, 2px ember bottom rule when active), then a right cluster with a ghost `Prijava` button (1px ink border, inverts on hover) and a solid ember `Pretplati se` button.

Footer: ink plane, 64px top padding. Four columns at `1.4fr 1fr 1fr 1fr`, 48px gap, ivory logo and a one-line descriptor in the first, three link columns after. A 1px `#403D39` divider, then a baseline row with the promise line at 19px ivory on the left and a legal line at 13px grey on the right.

#### Naslovna (home)

Three hero variants exist behind a prop; **variant `ritam` is the default and the one to build**. The other two (`ploha`, `cjenovnik`) are exploration and can be ignored unless asked for.

1. **Hero.** 1240px column, `padding: 96px 20px 0`, grid `1fr 440px`, 64px gap, `align-items: end`. Left: H1 at 66px in three short lines ("Pukla cijev. / Nestalo struje. / Vrata se ne zatvaraju."), a 26px subline ("Ne tražite majstora. Ne pregovarate cijenu. Ne čekate cijeli dan."), a button pair (solid ember `Pogledajte pakete`, ghost ink `Cjenovnik radova`), and a 14px bark line naming price, cities, and staffing. Right: a 560px-tall white cell holding the technician photograph, `object-position: bottom`, `margin-bottom: -1px` so the figure sits exactly on the boundary with the ember band below. No overflow clipping on the section.
2. **Promise band.** Full-bleed ember, 72px vertical padding. Inside the column, a 3-up grid with **1px gaps** so three ivory cards float on the ember field: this is the ember-and-ink separation rule made literal. Each card: 26px 700 heading, 15px bark body. Below the grid, the master promise at 34px 600 in ivory: "Jedna prijava. Poznata cijena. Dogovoren rok. Pisana garancija."
3. **Packages.** 44px H2 with a text link to the comparison on the right. Three cards at `1fr 1fr 1fr`, 24px gap, `align-items: start`. The middle card is the recommended one: ink border and ivory fill against sand border and white fill on the others. Card anatomy: name at 24px 700 with an uppercase qualifier on the right, a 14px bark audience line, a price block fenced by 1px sand rules top and bottom (46px 700 numeral, 16px unit), a feature list where each row is a `14px 1fr` grid with a **7px ember square** as the bullet (not a dash, not a glyph), and a footer button pinned with `margin-top: auto` so all three cards align.
4. **Coverage grid.** Eight cells in a 4-up grid, 1px gaps over a sand background, each 150px minimum, holding a 28px stroked SVG and a 17px label. The last cell inverts to ink with an ember icon stroke, reading "I još mnogo toga".
5. **Before / after.** Two 420px photo slots side by side with 1px gaps, then a full-width ivory caption panel explaining the HAUS Karton.
6. **Closing CTA.** Ink plane, grid `1fr 420px`, a 44px ivory headline and a bark supporting line on the left, two stacked buttons on the right.

#### Cijene (pricing)

Same three cards as the home page, then a comparison table. Table header is an ink row with 12px uppercase cells; the HAUS Plus column header is an ember cell, and the corresponding body cells are ivory with weight 500, which is how "recommended" is expressed without colour-coding whole rows. Row heads are `<th scope="row">`. Zebra striping alternates white and `#FDFCF8`. Below: two ivory notes, one on the Pro volume discount, one on scope.

#### Cjenovnik radova (public price list)

Searchable, category-filtered list of about 58 positions. A 360px search input and a row of category chips (active chip is ink fill with ivory text). A live count line. Then one table per category with four columns: position, price without subscription, HAUS Mini price, HAUS Plus price. The Plus column header is ember, its cells ivory and weight 600. **Discounted prices are computed from the base price, never stored.** An empty state explains what to do when a customer cannot name the fault. Then a surcharge table and a note on the annual inspection.

#### Gdje radimo (coverage map)

A real Leaflet map inside a 1px ink frame, 520px tall, wired to a small web component (`haus-map.js`) that reads a JSON city list. Active cities get an ember-filled 20px square pin with a 2px ink border; cities in preparation get an ivory pin. Each pin carries an ink label plate offset 27px to the right. Attribution is required. Under the map, a card per city with a state chip, then an ember CTA panel for people whose city is not covered.

**Never hand-draw geography.** Use real tile data and real coordinates.

#### Proizvod, Česta pitanja, Kontakt, Uslovi korištenja

- **Proizvod:** named service components in a 3-up hairline grid (two cells invert to ink), then an included-versus-excluded pair of panels, then an ember panel for the emergency channel.
- **Česta pitanja:** accordion, one item open at a time, `aria-expanded` maintained, plus / minus glyph in ember at 22px, answer padded `0 60px 26px 0`.
- **Kontakt:** emergency panel, a definition list of contact rows on a 180px / 1fr grid, and a form in an ivory card.
- **Uslovi korištenja:** sticky table of contents on a 260px column, numbered sections with `scroll-margin-top: 150px`. Reachable from the footer and from the registration checkbox, **not from the main nav.**

### Registration surface

Two states share one shell. Background is ivory. Grid `minmax(0,1fr) 420px`.

- **Left column:** `padding: 48px 56px 96px`, `justify-content: center`, inner column capped at 720px. Logo, then a step indicator, then the form.
- **Step indicator:** two steps, 36px gap, 22px bottom padding over a 1px sand rule. Each step is a 30px square with a 1px ink border (ink fill and ivory numeral when active, sand fill and a ✓ when completed) beside a 16px label.
- **Step 1 (paket i podaci):** a three-tile package chooser (each tile has a 3px top rule that turns ember when selected, a radio, the name, a 30px price, the unit, and a hairline-separated one-line summary), then name and email on a 2-up grid, then the address block.
  - **Address for Mini and Plus:** city `<select>` on a 220px column (active cities only) plus a street field.
  - **Address for Pro:** the tile switches the whole block. A stepper sets the number of apartments (46px square minus and plus buttons flanking a 22px tabular count), with a live line naming the volume discount. Then one hairline-separated card per apartment: index label, a remove link when more than one, city select, street, use (`Izdaje se` / `Prazan / dijaspora` / `Živim u njemu`), and an optional on-site contact. Validation is per apartment and marks the offending fields.
  - Then a terms checkbox linking to the terms page, a submit button, and a reassurance line that nothing is charged yet.
- **Step 2 (plaćanje):** two payment tiles, each with a 4px left state rule, a radio, an 18px title, and a 15px bark explanation. **Uplatnica na mejl** first, **Kartica** second. There is no cash option and there must never be one.
- **Right column (order summary):** ink plane, sticky at `top: 46px`, 48px 40px padding. Grey eyebrow, package name at 30px ivory, price at 40px ivory with a grey unit, then a hairline-separated summary list. For Pro the rows switch to apartment count and volume discount, and the price becomes the multiplied total or the word `Dogovor` at 10 or more apartments, in which case the submit button becomes a quote request. At the bottom, an ember-wrapped ivory panel naming what is **not** included, and a grey line about automatic renewal.
- **Prijava (login)** is a separate centred 440px column on ivory: logo, H1, email, password, error panel, submit, then a hairline-separated footer with a registration link.

### Client surface

Sticky white header, 80px, 1320px column: logo, nav, then a right cluster with the customer's name and package on two lines, a solid ember `Prijavi kvar` button, and a ghost `Odjava`.

- **Početna:** 44px greeting with the subscription expiry on the right. A 4-up hairline grid of status cells (each: uppercase eyebrow, 34px tabular value, 14px bark note). Then `1fr 400px`: on the left an ember-wrapped ivory CTA panel and a table of recent interventions with state chips; on the right the HAUS Karton card (1px ink border) and an inspection card (ivory).
- **Prijavi kvar:** two flows behind a prop. `koraci` is a three-step wizard (category tiles in a 3-up grid, then description plus photo slot plus an emergency checkbox that turns its container ember-bordered and ivory-filled, then appointment tiles and an access note). `jedan` is a single screen with a sticky 380px summary aside. Build the wizard unless told otherwise; keep the single-screen variant in mind, it exists for a reason.
- **Moje intervencije:** one article per job. Header row carries the job number, a state chip, the trade, a 22px title, and a meta line, with the warranty date right-aligned. Completed jobs expand into a `1fr 240px 240px` hairline grid: findings and an itemised invoice table on ivory, then before and after photo slots.
- **Moja pretplata:** an ink-headed panel with a definition list of entitlements, a payment history table, and a three-card aside (renewal in an ember wrapper, upgrade, cancellation).
- **Cjenovnik:** the public list with the base price struck through and the customer's price in a 600-weight ivory cell.
- **Profil:** address panel with a change-request button (address changes are a request, never a direct edit), contact fields, and three notification toggles.

### Admin surface (dispatcher)

Ink sticky header, 72px, 1440px column: ivory logo, a `Dispečer` label behind a 1px `#403D39` divider, nav in ivory, then a tabular timestamp and the operator name in a bordered chip.

- **Pregled:** a 5-up KPI grid, each cell with an uppercase label, a 32px value, a trend delta, a target line, and a 4px progress bar (ink, ember only for the "cijena prije rada" metric). Then `1fr 420px`: today's schedule table and a monthly sales grid on the left; on the right an **ember-headed alert panel** for deadlines falling today and an ivory renewals card.
- **Zahtjevi:** state filter chips with live counts, then a seven-column table whose rows are clickable. The grid is `1fr {detail}`, where the detail column is `420px` when a job is selected and `0px` otherwise. The selected row is filled ivory. The detail aside is sticky, ink-headed, and holds a definition list, a technician assignment list, a state chip row, and **a live preview of the notification the customer will receive**, which changes with the state. That preview is the point of the screen: the dispatcher sees the customer's experience before committing.
- **Klijenti / Pretplate / Naplata:** dense zebra tables. Subscriptions show usage and renewal, with urgent renewals in weight 600 ink. Billing splits labour and material and totals in weight 700, with paid, unpaid, and no-charge state chips.
- **Cjenovnik (uređivanje):** editable base-price number inputs. Changed rows turn ivory with an ember input border and read `Nije objavljeno`; a header chip counts them; the publish button is ember when dirty and sand when clean. Derived discount columns are read-only. Publishing pushes to the website and to technicians' phones **at the same time**, never one only.
- **Gradovi:** the same Leaflet component at 340px, a table with coordinates and state, per-row activate / pause and remove actions, and a sticky ink-headed form to add a city with name and decimal coordinates, validated to Bosnia's bounding box (lat 42 to 46, lon 15 to 20). New cities land in `U pripremi`, so they show on the map but cannot be subscribed to.

### Shared web behaviour

- **Toast:** fixed, bottom centre, ink plane, ivory text, a dismiss button, auto-clearing after 6 seconds, `role="status"`.
- **Validation:** inline, under the field, 13px weight 500 in `#B03000`, with the field border switching to the same colour. Never a modal, never a summary at the top.
- **Empty states** always say what the user should do next, not that nothing was found.

---

## Screens: mobile (`HAUS Aplikacija.dc.html`)

The prototype presents 19 screens in a simulated device: an ink chassis with a 56px radius holding a 390 × 844 screen with a 44px radius, a status bar with a dynamic-island plate, a scrolling content area, a tab bar, and a home indicator. **All of that framing is scaffolding.** Build the screens; the OS supplies the chrome.

Screen inventory, in flow order:

**Onboarding:** 01 Welcome, 02 Kako radi.
**Auth and registration:** 03 Prijava, 04 Izbor paketa, 05 Podaci i adresa, 06 Plaćanje, 07 Pretplata aktivna.
**Client:** 08 Početna, 09 Prijavi kvar, 10 Prijava primljena, 11 Moje intervencije, 12 Nalog i nalaz, 13 Moja pretplata, 14 Cjenovnik, 15 Profil.
**Dispatcher:** 16 Danas, 17 Nalozi, 18 Nalog i dodjela, 19 Gradovi.

Screen notes worth carrying over verbatim:

- **01 Welcome** names the problem instead of explaining the product. `padding: 30px 26px 0`, logo at 140 × 30, a 35px headline in four short lines, a 16px bark subline, then the photograph filling the remaining height with `align-items: flex-end` and `overflow: hidden`, then an ember-wrapped ivory footer with two 52px buttons: `Pretplatite se` and `Imam pretplatu`.
- **02 Kako radi** is three sequential screens sharing one shell: a step counter and a `Preskoči` button on one row, a 6px-gapped progress bar of rectangular segments (ember for reached, sand for the rest), a 34px headline, a 17px paragraph, and a bulleted list using 8px ember squares. Back and forward buttons at the bottom, the last step's forward button reading `Pretplatite se`.
- **03 Prijava** takes email and password. **No phone field.** A discreet `Prijava za dispečera` text button sits at the bottom.
- **04 to 06** mirror the web registration: package tiles with a 4px left state rule, then the data form where the city is a `<select>` of active cities, then payment with a summary panel above the two method tiles. For HAUS Pro, screen 05 swaps the single address for a stepper plus one card per apartment (city select and street), and screen 06 shows the multiplied price or `Dogovor`.
- **07 Pretplata aktivna** opens with an ember plane carrying an ivory chip and a 36px ivory headline, then states plainly that the first fault report can be sent now, then the entitlement rows, then a single button into the app.
- **08 Početna** leads with the ember-wrapped report CTA, then a 2 × 2 hairline status grid, then a job-in-progress card whose steps are a hairline list with 10px squares (ember filled when done, white when pending), then recent jobs as tappable rows with state chips.
- **09 Prijavi kvar** is three steps, one question per screen: category (2-up tiles, 64px minimum, last option "Ne znam kako se zove"), description plus a photo slot plus the emergency checkbox, then appointment tiles. Forward buttons are disabled and sand-filled until the step is answered.
- **10 Prijava primljena** opens with an ink plane: an ember chip, the job number in a 34px ivory headline, and the promise about the two-hour window. Then the summary rows and a note about remaining visits. A `Vidite nalog kod dispečera` button exists **only in the prototype** to demonstrate the round trip.
- **13 Moja pretplata** opens with an ink plane holding the package name and price, then entitlement rows, an ember-wrapped inspection card, then payment history.
- **16 Danas** opens with an ink header carrying the ivory logo and a `Dispečer` label, then a 2 × 2 KPI grid, an ember-headed panel for deadlines falling today, then the schedule as an `82px 1fr` list.
- **17 Nalozi** uses filter chips with counts; each row carries a 4px left rule that turns ember for emergencies, a state chip, the job number, an optional `HITNO` marker, the description, and two tabular meta lines.
- **18 Nalog i dodjela** mirrors the web detail aside, including the live customer-notification preview.
- **19 Gradovi** lists cities with a tappable state chip and a form to add one, which lands in `U pripremi`.

### Mobile-specific rules

- Bottom tabs, ink and bark only, with a 3px ember top rule and weight 600 on the active tab. Client tabs: Početna, Prijavi, Nalozi, Pretplata, Profil. Dispatcher tabs: Danas, Nalozi, Gradovi. Onboarding and registration have **no tab bar**.
- Icons are 21px stroked SVG paths, `stroke-linecap: square`, stroke 1.4 resting and 2 active. No icon font, no filled icons.
- Detail screens use a `‹` back button at 22px with a 44px minimum target, never a swipe-only affordance.
- Toast is absolutely positioned 96px from the bottom, inset 16px, ink plane, with an `OK` dismiss.
- Offline: the job list and the price list must read from cache. Reporting a fault requires the network and must say so.

---

## Interactions and behaviour

### Navigation

Web has four surfaces (public, registration, client, admin) with independent routes. The prototype's surface switcher is scaffolding. Mobile is a stack per flow plus bottom tabs, with onboarding and registration outside the tab bar.

### The one interaction that matters most

Reporting a fault as a client creates a real job that appears at the top of the dispatcher's list in state `Novo`. Assigning a technician moves it to `Zakazano` and **rewrites the notification text the customer will receive**. Both prototypes implement this round trip end to end. Preserve it: it is the product's core loop, and the notification preview is what keeps the dispatcher honest.

### Job lifecycle

`novo` → `zakazano` → `u_toku` → `zavrseno`, with `garancija` as a separate non-billable type.

State chip styling, which must stay consistent across both clients:

| State | Border | Fill | Text |
|---|---|---|---|
| Novo | ink | white | ink |
| Zakazano | bark | ivory | bark |
| U toku | ember | ember | ivory |
| Završeno | sand | sand | ink |
| Garancija | ink | ink | ivory |

Server-side automation the design assumes:

- `deadline_at` is computed at creation from the package and the emergency flag, and does not change afterwards.
- A scheduled job watches deadlines. When one passes on an unfinished job, the system **automatically** credits a free intervention and notifies the customer before they ask.
- The remaining-visit counter decrements on transition to `zavrseno`, not at report time.
- A customer with no visits left can still report; the work is billed at the package's discount.
- After completion, the findings report with before and after photos goes out **within 24 hours**.
- Card payments send the invoice by email immediately; the bank-slip route sends the slip and the invoice together.

### Forms

Inline validation on submit, per field, in `#B03000`. City is always a select of active cities and validated server-side too. Pro apartment validation is per row. The emergency checkbox restyles its whole container. Steppers clamp at 1 and disable the minus button with a sand fill.

### Price computation

Every discounted price is derived from the base price at render time and rounded to a whole number. Nothing derived is ever stored or hand-entered. **The client never computes a discount locally on mobile; prices arrive computed from the API.**

---

## State

Client-side state observed in the prototypes, useful as a checklist:

- Current surface and route, selected detail id.
- Registration draft: package, name, email, city, street, terms flag, and for Pro an apartment array of `{city, street, use, contact}`.
- Per-field error maps for registration, fault reporting, and city creation.
- Payment method and a processing flag with a simulated 900ms settle.
- Fault report draft: category, description, emergency flag, appointment, step index.
- Job list, which grows when a report is submitted.
- Price-list search query and category filter.
- Admin state filter, price-list draft versus published maps, dirty-row derivation.
- City list with per-city state, plus a new-city draft.
- Notification preference toggles.
- A single toast string with a 5 to 6 second auto-clear.

Server-side, the data model in `HAUS-Build-Prompt.md` is authoritative. Minimum tables: `users`, `roles`, `cities`, `packages`, `subscriptions`, `subscription_properties`, `price_categories`, `price_items`, `surcharges`, `jobs`, `job_items`, `job_materials`, `job_photos`, `technicians`, `invoices`, `payments`, `payment_tokens`, `notifications`, `settings`, `home_records`.

---

## Payments (Monri)

Two methods only: bank slip by email, and card via Monri. **No cash.** `HAUS Monri Zahtjev.dc.html` is the request document sent to the provider and states the requirements: card tokenisation with no card data in our database, merchant-initiated renewal charges on an annual cycle, 3-D Secure 2 on the first transaction, a status webhook so activation is machine-driven and instant, full and partial refunds via API from the admin panel, transaction references tied to our subscription or job id, and support for cards issued outside Bosnia.

The instant-activation promise ("first report can be sent now") depends entirely on that webhook. Without it, the promise cannot be kept.

---

## Assets

| File | What it is |
|---|---|
| `logo-primary.svg` | Ember symbol with ink wordmark. Light backgrounds. 328 × 71 native. No tagline. |
| `logo-ivory.svg` | All-ivory version. Ink backgrounds only. |
| `majstor.png` | Technician photograph, transparent background, used bottom-anchored in both heroes. |
| `haus-map.js` | Leaflet wrapper web component reading a JSON city list. Reference for pin styling; reimplement natively. |
| `image-slot.js` | Prototype-only photo drop target. Replace with real uploads and real photography. |
| `doc-page.js` | Print shell for the Monri document. Only needed if you keep a printable version. |
| `support.js` | Prototype runtime. **Do not port.** |

Leaflet 1.9.4 and OpenStreetMap tiles are loaded from CDN with SRI hashes in the prototype. Attribution is mandatory.

All photography slots are unfilled. The brandbook forbids stock photography: use the client's own images, shot in natural light, real locations, no flash.

---

## Files in this bundle

- `HAUS-Build-Prompt.md` — domain logic and per-stack implementation brief. Start here.
- `HAUS Prototip.dc.html` — desktop web prototype, all four surfaces.
- `HAUS Aplikacija.dc.html` — mobile prototype, 19 screens.
- `HAUS Monri Zahtjev.dc.html` — printable payment-integration request.
- `CLAUDE.md` — standing project constraints, including the em-dash ban and the decided product facts.
- `logo-primary.svg`, `logo-ivory.svg`, `majstor.png` — brand assets.
- `haus-map.js`, `image-slot.js`, `doc-page.js`, `support.js` — prototype support files, reference only.

To view a prototype, open its `.dc.html` file in a browser. Both are self-contained apart from the sibling support files, which must sit in the same folder.

## Implementation order

1. Database, models, seeds for packages, price list, cities, surcharges.
2. Auth, roles, registration without card payment (bank slip only).
3. Public web: home, pricing, price list, city map.
4. Client web: fault reporting and job list.
5. Admin: jobs, assignment, states, cities, price list with publishing.
6. Monri: card, webhook, tokenisation, renewal, refunds.
7. Deadline cron with the automatic free intervention, and the 24-hour report generation.
8. Mobile app on the same API.

Step 6 is a precondition for the business model. Do not ship without it.

## Open questions for the client

- Real photography for the hero, the before-and-after pair, and the job findings.
- Final package names and prices, though these must stay database-editable regardless.
- Whether the technician role ships in phase 1 or phase 2. The data model provides for it either way.
- Whether the single-screen fault report variant should ship alongside the wizard.
