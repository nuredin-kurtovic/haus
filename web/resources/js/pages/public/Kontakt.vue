<script setup>
import { onMounted, ref, computed, reactive } from 'vue';
import PublicLayout from '../../layouts/PublicLayout.vue';
import { fetchCities, fetchSurcharges, fetchSettings } from '../../api/catalog';
import { nabrojiGradove } from '../../utils/format';
import { useToastStore } from '../../stores/toast';

const toast = useToastStore();

const cities = ref([]);
const surcharges = ref([]);
const settings = ref(null);

onMounted(async () => {
  const [c, s, st] = await Promise.all([fetchCities(), fetchSurcharges(), fetchSettings()]);
  cities.value = c;
  surcharges.value = s;
  settings.value = st;
});

const dispecerLinija = computed(() => {
  if (!settings.value) return '';
  const rv = settings.value.radno_vrijeme;
  if (!rv || !rv.pon_pet || !rv.subota) return '';
  return `U aplikaciji, radnim danima ${rv.pon_pet.od}–${rv.pon_pet.do}, subotom ${rv.subota.od}–${rv.subota.do}. Hitno 24 sata.`;
});

const podrucjeLinija = computed(() => {
  if (cities.value.length === 0) return '';
  const aktivni = cities.value.filter((c) => c.status === 'aktivan').map((c) => c.name);
  const pripremi = cities.value.filter((c) => c.status !== 'aktivan').map((c) => c.name);
  const izvanGrada = surcharges.value.find((s) => s.key === 'izvan_grada');
  let text = `${nabrojiGradove(aktivni)}.`;
  if (pripremi.length > 0) {
    text += ` ${nabrojiGradove(pripremi)} ${pripremi.length === 1 ? 'je' : 'su'} u pripremi.`;
  }
  if (izvanGrada) {
    text += ` Izvan gradskog područja ${izvanGrada.value.toFixed(2).replace('.', ',')} KM po kilometru u jednom smjeru.`;
  }
  return text;
});

const kontaktPodaci = computed(() => [
  { k: 'Prijava kvara', v: 'HAUS aplikacija: prijava, termin, fotografije i status naloga na jednom mjestu' },
  { k: 'Dispečer', v: dispecerLinija.value },
  { k: 'E-mail', v: 'pretplata@haus.ba' },
  { k: 'Područje rada', v: podrucjeLinija.value },
  { k: 'Sjedište', v: 'HAUS d.o.o., Sarajevo' },
].filter((row) => row.v));

const form = reactive({ ime: '', email: '', tekst: '' });

function posaljiUpit() {
  const subject = encodeURIComponent('Upit sa sajta HAUS');
  const body = encodeURIComponent(`Ime i prezime: ${form.ime}\nE-mail: ${form.email}\n\n${form.tekst}`);
  window.location.href = `mailto:pretplata@haus.ba?subject=${subject}&body=${body}`;
  toast.show('Otvaramo vaš mejl program sa pripremljenom porukom.');
}
</script>

<template>
  <PublicLayout>
    <div class="container section-narrow">
      <h1 class="page-h1" style="margin-bottom:56px">Kontakt</h1>
      <div style="display:grid;grid-template-columns:1fr 440px;gap:80px;align-items:start">
        <div>
          <div style="background:var(--ember);padding:32px;margin-bottom:40px;display:flex;gap:2px;align-items:stretch;flex-wrap:wrap">
            <div style="background:var(--ivory);padding:22px 26px;flex:1;min-width:220px">
              <h2 style="font-size:22px;font-weight:700;margin-bottom:8px">Hitno: poplava, struja, plin</h2>
              <p style="font-size:15px;font-weight:400;line-height:1.55">Zatvorite ventil ako možete. Prijava u aplikaciji ide u poseban kanal i majstor kreće.</p>
            </div>
            <div style="background:var(--ink);padding:22px 26px;display:flex;flex-direction:column;justify-content:center">
              <span style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--sand)">Kanal</span>
              <span style="font-size:22px;font-weight:700;color:var(--ivory);line-height:1.25">HAUS aplikacija<br>Hitno</span>
            </div>
          </div>
          <dl style="display:grid;grid-template-columns:180px 1fr;gap:0;border-top:1px solid var(--ink)">
            <div v-for="k in kontaktPodaci" :key="k.k" style="grid-column:1 / -1;display:grid;grid-template-columns:180px 1fr;border-bottom:1px solid var(--sand);padding:18px 0">
              <dt style="font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--bark)">{{ k.k }}</dt>
              <dd class="num" style="margin:0;font-size:16px;font-weight:400;line-height:1.5">{{ k.v }}</dd>
            </div>
          </dl>
        </div>
        <form style="border:1px solid var(--sand);background:var(--ivory);padding:32px;display:flex;flex-direction:column;gap:20px" @submit.prevent="posaljiUpit">
          <h2 style="font-size:24px;font-weight:700">Pošaljite pitanje</h2>
          <div class="field">
            <label for="k-ime" class="field-label">Ime i prezime <span style="font-weight:400;text-transform:none;letter-spacing:0">(obavezno)</span></label>
            <input id="k-ime" v-model="form.ime" type="text" autocomplete="name" required>
          </div>
          <div class="field">
            <label for="k-mail" class="field-label">E-mail <span style="font-weight:400;text-transform:none;letter-spacing:0">(obavezno)</span></label>
            <input id="k-mail" v-model="form.email" type="email" autocomplete="email" required>
          </div>
          <div class="field">
            <label for="k-tekst" class="field-label">Pitanje</label>
            <textarea id="k-tekst" v-model="form.tekst" rows="5" style="resize:vertical"></textarea>
          </div>
          <button type="submit" class="btn btn-ember btn-block">Pošaljite</button>
          <p class="small-print">Odgovaramo isti radni dan. Za kvar u toku prijavu pošaljite u aplikaciji. Forma otvara vaš mejl program, to vam kažemo unaprijed.</p>
        </form>
      </div>
    </div>
  </PublicLayout>
</template>
