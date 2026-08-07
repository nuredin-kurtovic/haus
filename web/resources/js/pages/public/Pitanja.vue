<script setup>
import { ref } from 'vue';
import PublicLayout from '../../layouts/PublicLayout.vue';

const faq = [
  { q: 'Skupo mi je 169 KM godišnje.', a: 'Jedan izlazak bez pretplate je 35 KM plus rad. Zamjena baterije na sudoperi je 55 KM. Sa HAUS Plus dobijate tri izlaska, pregled instalacija i 25% na sve ostalo. Pokvari se dvaput i već ste na plusu.' },
  { q: 'A ako mi se ništa ne pokvari?', a: 'Onda ste dobili godišnji pregled sa nalazom i fotografijama, i znate šta vam dolazi na red sljedeće godine. To je jedini način da kvar ne bude iznenađenje.' },
  { q: 'Imam ja svog majstora.', a: 'Odlično, zadržite ga. Pitanje je samo javlja li se u nedjelju u sedam i šta radite kad je na godišnjem. HAUS je tu za to.' },
  { q: 'Doći ćete i reći da treba veliki posao.', a: 'Nalaz dobijate napismeno, sa fotografijama i procjenom cijene. Nije obavezujući. Možete ga odnijeti kome hoćete. Mnogi to i urade.' },
  { q: 'Kako znam da nećete dići cijene?', a: 'Cjenovnik je javan i isti za sve. Cijena se kaže prije rada i vi je potvrđujete. Ako pozicija nije u cjenovniku, majstor zove kancelariju pred vama.' },
  { q: 'Ne upisujem karticu na internet.', a: 'Ne morate. Uplatnicu šaljemo na mejl, platite je kako vam odgovara.' },
  { q: 'Šta ako ne ispunite obećani rok?', a: 'Sljedeća intervencija je besplatna, automatski, bez da je tražite. Zato rokove postavljamo tako da ih možemo držati u sedmici prvog mraza, ne u prosječnoj sedmici.' },
];

const open = ref(0);

function toggle(i) {
  open.value = open.value === i ? -1 : i;
}
</script>

<template>
  <PublicLayout>
    <div class="container section-narrow" style="display:grid;grid-template-columns:1fr 380px;gap:80px;align-items:start">
      <div>
        <h1 class="page-h1" style="margin-bottom:20px">Česta pitanja</h1>
        <p class="lead" style="margin-bottom:48px">Ovo su pitanja koja nam ljudi stvarno postavljaju. Odgovori su isti u aplikaciji i na vratima.</p>
        <div style="border-top:1px solid var(--ink)">
          <div v-for="(f, i) in faq" :key="f.q" style="border-bottom:1px solid var(--sand)">
            <button
              type="button"
              :aria-expanded="open === i ? 'true' : 'false'"
              style="width:100%;display:flex;align-items:baseline;justify-content:space-between;gap:24px;text-align:left;border:0;background:transparent;padding:22px 0;cursor:pointer"
              @click="toggle(i)"
            >
              <span :style="{ fontSize: '20px', fontWeight: open === i ? 600 : 400, lineHeight: 1.35 }">{{ f.q }}</span>
              <span style="font-size:22px;font-weight:600;color:var(--ember);line-height:1.2;flex:none">{{ open === i ? '–' : '+' }}</span>
            </button>
            <p v-if="open === i" style="font-size:16px;font-weight:400;line-height:1.6;color:var(--bark);padding:0 60px 26px 0;max-width:640px">{{ f.a }}</p>
          </div>
        </div>
      </div>
      <aside style="border:1px solid var(--sand);background:var(--ivory);padding:28px;display:flex;flex-direction:column;gap:16px">
        <h2 style="font-size:20px;font-weight:600;line-height:1.3">Nema odgovora na vaše pitanje?</h2>
        <p style="font-size:15px;font-weight:400;line-height:1.55;color:var(--bark)">Pitanje pošaljite formom ili iz aplikacije. Odgovaramo isti radni dan. Hitno se prijavljuje u aplikaciji, 24 sata.</p>
        <RouterLink to="/kontakt" class="btn btn-ember" style="padding:15px 20px;font-size:15px">Pošaljite pitanje</RouterLink>
      </aside>
    </div>
  </PublicLayout>
</template>
