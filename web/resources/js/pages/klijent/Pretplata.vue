<script setup>
import { computed, onMounted, ref } from 'vue';
import KlijentLayout from '../../layouts/KlijentLayout.vue';
import { ApiError, cancelSubscription, fetchSubscription } from './api';
import { formatDate, invoiceStatusChipClass, invoiceStatusLabel, invoiceTypeLabel, propertyUseLabel } from './format';
import { mjeseci, sati } from '../../utils/format';
import { useToastStore } from '../../stores/toast';

const toast = useToastStore();

const loading = ref(true);
const error = ref('');
const notFound = ref(false);
const subscription = ref(null);
const cancelling = ref(false);

async function load() {
  error.value = '';
  notFound.value = false;
  try {
    subscription.value = await fetchSubscription();
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      notFound.value = true;
    } else {
      error.value = (e && e.message) || 'Greška pri učitavanju. Provjerite internet konekciju.';
    }
  } finally {
    loading.value = false;
  }
}
onMounted(load);

const pkg = computed(() => subscription.value?.package || null);
const isPro = computed(() => Boolean(pkg.value?.is_per_apartment));

const headerLinija = computed(() => {
  if (!subscription.value || !pkg.value) return '';
  const cijena = `${Math.round(pkg.value.price_year)} KM / god${isPro.value ? ' po stanu' : ''}`;
  const rok = subscription.value.ends_at ? ` · aktivna do ${formatDate(subscription.value.ends_at)}` : '';
  return `${cijena}${rok}`;
});

const rows = computed(() => {
  if (!subscription.value || !pkg.value) return [];
  const p = pkg.value;
  const list = [
    { k: 'Popust na rad iznad uključenog', v: `${p.labor_discount_pct}%` },
    { k: 'Popust na materijal', v: p.material_discount_pct > 0 ? `${p.material_discount_pct}%` : 'Nema' },
    { k: 'Rok izlaska', v: sati(p.deadline_hours) },
    { k: 'Hitno: poplava, struja, plin', v: p.emergency_included ? `${sati(p.emergency_deadline_hours)}, bez doplate` : `${sati(p.emergency_deadline_hours)}, uz doplatu` },
  ];
  if (p.inspections_per_year > 0) {
    list.push({ k: 'Godišnji pregled instalacija', v: `${p.inspections_per_year}× godišnje` });
  }
  list.push({ k: 'Garancija na rad', v: mjeseci(p.warranty_months) });

  const props = subscription.value.properties || [];
  props.forEach((prop, i) => {
    const oznaka = props.length > 1 ? ` ${i + 1}` : '';
    let vrijednost = `${prop.street}, ${prop.city}. ${propertyUseLabel(prop.use)}.`;
    if (isPro.value) {
      vrijednost += ` Izlasci preostalo ${prop.remaining_visits}, pregled preostalo ${prop.remaining_inspections}.`;
    } else {
      vrijednost += ' Nije prenosiva.';
    }
    list.push({ k: `Adresa${oznaka}`, v: vrijednost });
  });

  return list;
});

async function onCancel() {
  if (!window.confirm('Da li želite isključiti automatsku obnovu pretplate?')) return;
  cancelling.value = true;
  try {
    const body = await cancelSubscription();
    if (subscription.value) subscription.value.auto_renew = body.auto_renew;
    toast.show(body.message);
  } catch (e) {
    toast.show((e && e.message) || 'Otkazivanje nije uspjelo. Provjerite internet konekciju.');
  } finally {
    cancelling.value = false;
  }
}
</script>

<template>
  <KlijentLayout>
    <div style="width:1320px;max-width:100%;margin:0 auto;padding:56px 28px 96px">
      <h1 style="font-size:44px;font-weight:700;line-height:1.05;letter-spacing:-0.015em;margin-bottom:40px">Moja pretplata</h1>

      <div v-if="loading" style="padding:60px 0;text-align:center;color:var(--bark)">Učitavanje...</div>

      <div v-else-if="notFound" style="border:1px solid var(--sand);background:var(--ivory);padding:40px;max-width:640px">
        <h2 style="font-size:22px;font-weight:600;margin-bottom:10px">Nemate pretplatu</h2>
        <p style="font-size:16px;font-weight:400;color:var(--bark);line-height:1.55;margin-bottom:20px">Odaberite paket da biste imali termine, garanciju i cjenovnik sa popustom.</p>
        <RouterLink to="/cjenovnik" class="btn btn-ember">Pogledajte pakete</RouterLink>
      </div>

      <div v-else-if="error && !subscription" class="error-panel" style="max-width:560px">
        <p style="margin-bottom:16px">{{ error }}</p>
        <button type="button" class="btn btn-ghost-ink" @click="load">Pokušajte ponovo</button>
      </div>

      <div v-else style="display:grid;grid-template-columns:1fr 400px;gap:48px;align-items:start">
        <div>
          <p v-if="error" class="error-panel" style="margin-bottom:24px">{{ error }} Prikazujemo zadnje učitane podatke.</p>

          <div style="border:1px solid var(--ink);margin-bottom:32px">
            <div style="background:var(--ink);padding:24px 28px;display:flex;align-items:baseline;justify-content:space-between;gap:16px;flex-wrap:wrap">
              <span style="font-size:28px;font-weight:700;color:var(--ivory);letter-spacing:.04em">{{ pkg.name }}</span>
              <span class="num" style="font-size:16px;font-weight:400;color:var(--sand)">{{ headerLinija }}</span>
            </div>
            <dl style="margin:0">
              <div v-for="(r, i) in rows" :key="r.k" style="display:grid;grid-template-columns:280px 1fr" :style="{ background: i % 2 === 1 ? 'var(--zebra)' : 'var(--white)', borderBottom: '1px solid var(--sand)' }">
                <dt style="padding:16px 28px;font-size:15px;font-weight:500">{{ r.k }}</dt>
                <dd class="num" style="margin:0;padding:16px 28px;font-size:15px;font-weight:400;color:var(--bark)">{{ r.v }}</dd>
              </div>
            </dl>
          </div>

          <h2 style="font-size:15px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--bark);margin-bottom:16px">Historija plaćanja</h2>
          <div v-if="(subscription.payments || []).length" style="overflow-x:auto">
            <table class="table-haus" style="border:1px solid var(--sand)">
              <thead>
                <tr><th>Broj</th><th>Tip</th><th class="num" style="text-align:right">Iznos</th><th>Stanje</th><th>Datum</th></tr>
              </thead>
              <tbody>
                <tr v-for="(p, i) in subscription.payments" :key="p.number" :class="{ zebra: i % 2 === 1 }">
                  <td class="num">{{ p.number }}</td>
                  <td>{{ invoiceTypeLabel(p.type) }}</td>
                  <td class="num" style="text-align:right;font-weight:600">{{ Math.round(p.total) }} KM</td>
                  <td><span class="chip" :class="invoiceStatusChipClass(p.status)">{{ invoiceStatusLabel(p.status) }}</span></td>
                  <td class="num" style="color:var(--bark)">{{ formatDate(p.paid_at || p.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else style="font-size:15px;font-weight:400;color:var(--bark);border:1px solid var(--sand);background:var(--ivory);padding:24px">Nema plaćanja na vašem računu.</p>
        </div>

        <aside style="display:flex;flex-direction:column;gap:24px">
          <div style="background:var(--ember);padding:2px">
            <div style="background:var(--ivory);padding:26px">
              <h2 style="font-size:20px;font-weight:700;margin-bottom:10px">Obnova</h2>
              <p style="font-size:15px;font-weight:400;line-height:1.55">
                {{ subscription.auto_renew ? 'Automatska obnova je uključena. Podsjetnik šaljemo 60 dana prije isteka.' : 'Automatska obnova je isključena. Pretplata vrijedi do isteka i neće se naplatiti ponovo.' }}
              </p>
            </div>
          </div>
          <div style="border:1px solid var(--sand);background:var(--ivory);padding:26px">
            <h2 style="font-size:18px;font-weight:600;margin-bottom:10px">Promjena paketa</h2>
            <p style="font-size:15px;font-weight:400;line-height:1.55;color:var(--bark)">Paket se mijenja pri obnovi pretplate. Za promjenu prije isteka obratite se dispečeru u aplikaciji.</p>
          </div>
          <div style="border:1px solid var(--sand);padding:26px">
            <h2 style="font-size:18px;font-weight:600;margin-bottom:10px">Otkazivanje</h2>
            <p style="font-size:15px;font-weight:400;line-height:1.55;color:var(--bark);margin-bottom:16px">Automatsku obnovu možete isključiti ovdje ili u profilu. Neiskorištene intervencije se ne prenose u sljedeću godinu.</p>
            <button type="button" class="btn btn-ghost-ink" style="width:100%" :disabled="cancelling || !subscription.auto_renew" @click="onCancel">
              {{ subscription.auto_renew ? (cancelling ? 'Isključujemo...' : 'Isključite automatsku obnovu') : 'Automatska obnova je isključena' }}
            </button>
          </div>
        </aside>
      </div>
    </div>
  </KlijentLayout>
</template>
