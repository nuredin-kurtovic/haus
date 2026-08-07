<script setup>
import PublicLayout from '../../layouts/PublicLayout.vue';

const sekcije = [
  { n: 1, t: 'Predmet', p: [
    'HAUS pruža uslugu održavanja doma na godišnju pretplatu u gradovima navedenim na stranici Gdje radimo. Pretplata se ugovara za jednu adresu, koja se upisuje pri registraciji.',
    'Pretplata sadrži uključene intervencije, popust na rad, garantovan rok izlaska i, zavisno od paketa, godišnji pregled instalacija.',
  ] },
  { n: 2, t: 'Šta je uključeno u intervenciju', p: [
    'Izlazak na adresu u roku iz paketa, dijagnostika kvara, do 45 minuta rada majstora, te pisana konstatacija stanja i ponuda ako je potreban veći rad.',
    'Rad iznad 45 minuta obračunava se po sniženoj satnici iz javnog cjenovnika.',
  ] },
  { n: 3, t: 'Šta nije uključeno', p: [
    'Materijal i rezervni dijelovi. Naplaćuju se po nabavnoj cijeni uvećanoj za 20%, uz popust po paketu.',
    'Adaptacije, renoviranje, ugradnja nove opreme, keramika i molovanje. To su ponude, a ne intervencije.',
    'Zajednički dijelovi zgrade: vertikale, krov, fasada, stubište. To je posao upravitelja.',
    'Radovi koji zahtijevaju građevinsku dozvolu, dizalicu ili skelu.',
    'Kvarovi na opremi pod garancijom proizvođača gdje bi naša intervencija ukinula tu garanciju.',
    'Posljedične štete. Popravljamo kvar, ne saniramo parket koji je nabujao.',
  ] },
  { n: 4, t: 'Ograničenja', p: [
    'Pretplata je vezana za jednu adresu i nije prenosiva na drugi stan ni na drugu osobu.',
    'Neiskorištene intervencije ne prenose se u sljedeću godinu.',
  ] },
  { n: 5, t: 'Cijena, rok i garancija', p: [
    'Cijena rada se kaže prije početka rada. Majstor otvara javni cjenovnik, pokazuje poziciju i čeka vašu potvrdu. Ako pozicija ne postoji u cjenovniku, majstor poziva dispečera pred vama.',
    'Ako ne ispunimo obećani rok iz vašeg paketa, sljedeća intervencija je besplatna. Upisuje se automatski, bez zahtjeva.',
    'Garancija na rad je 6 mjeseci za HAUS Mini i 12 mjeseci za HAUS Plus i HAUS Pro. U garantnom roku ponovni izlazak se ne naplaćuje.',
  ] },
  { n: 6, t: 'Doplate i radno vrijeme', p: [
    'Redovno radno vrijeme je radnim danima 08:00–18:00 i subotom 09:00–14:00.',
    'Doplate za rad izvan tog vremena, za hitnost i za izlazak izvan gradskog područja objavljene su u javnom cjenovniku i kažu se prije rada.',
  ] },
  { n: 7, t: 'Obnova i otkazivanje', p: [
    'Pretplata se obnavlja automatski. Podsjetnik šaljemo 60 dana prije isteka i obnovu možete otkazati u aplikaciji.',
    'Zadržavamo pravo odbijanja intervencije u objektima u stanju koje ugrožava sigurnost majstora, na ilegalnim instalacijama, i kod klijenata koji su prethodno odbili platiti materijal.',
  ] },
];
</script>

<template>
  <PublicLayout>
    <div class="container section-narrow" style="display:grid;grid-template-columns:260px 1fr;gap:80px;align-items:start">
      <nav aria-label="Sadržaj" style="position:sticky;top:150px">
        <h2 class="eyebrow" style="font-size:12px;margin-bottom:16px">Sadržaj</h2>
        <ul style="display:flex;flex-direction:column;gap:10px">
          <li v-for="s in sekcije" :key="s.n">
            <a :href="`#u${s.n}`" style="font-size:15px;font-weight:400;text-decoration:none;border-bottom:1px solid var(--sand);padding-bottom:2px">{{ s.n }}. {{ s.t }}</a>
          </li>
        </ul>
      </nav>
      <div style="max-width:720px">
        <h1 style="font-size:52px;font-weight:700;line-height:1.08;letter-spacing:-0.02em;margin-bottom:12px">Uslovi korištenja</h1>
        <p class="num" style="font-size:14px;font-weight:400;color:var(--bark);margin-bottom:56px">Verzija 1.0 · na snazi od 01.09.2026.</p>
        <section v-for="s in sekcije" :key="s.n" :id="`u${s.n}`" style="margin-bottom:48px;scroll-margin-top:150px">
          <h2 style="font-size:26px;font-weight:700;line-height:1.2;margin-bottom:16px">{{ s.n }}. {{ s.t }}</h2>
          <p v-for="(par, i) in s.p" :key="i" style="font-size:16px;font-weight:400;line-height:1.65;color:var(--ink);margin-bottom:14px">{{ par }}</p>
        </section>
      </div>
    </div>
  </PublicLayout>
</template>
