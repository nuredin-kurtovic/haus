<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useToastStore } from '../../stores/toast';
import { fetchBilling, refundInvoice, ApiError } from './api';

const toast = useToastStore();

const STATUS_OPTIONS = [
  { value: '', label: 'Sva stanja', countKey: 'ukupno' },
  { value: 'placeno', label: 'Plaćeno', countKey: 'placeno' },
  { value: 'nenaplaceno', label: 'Nenaplaćeno', countKey: 'nenaplaceno' },
  { value: 'bez_naplate', label: 'Bez naplate', countKey: 'bez_naplate' },
  { value: 'refundirano', label: 'Refundirano', countKey: 'refundirano' },
  { value: 'djelimicno_refundirano', label: 'Djelimično refundirano', countKey: 'djelimicno_refundirano' },
];

const CHIP_STYLE = {
  placeno: { border: 'var(--sand)', bg: 'var(--sand)', color: 'var(--ink)' },
  nenaplaceno: { border: 'var(--ink)', bg: 'var(--ink)', color: 'var(--ivory)' },
  bez_naplate: { border: 'var(--sand)', bg: 'var(--white)', color: 'var(--bark)' },
  refundirano: { border: 'var(--bark)', bg: 'var(--ivory)', color: 'var(--bark)' },
  djelimicno_refundirano: { border: 'var(--ink)', bg: 'var(--white)', color: 'var(--ink)' },
};

const rows = ref([]);
const meta = ref({ counts: {} });
const status = ref('');
const q = ref('');
const loading = ref(true);
let qDebounce = null;

const refundTarget = ref(null);
const refundAmount = ref('');
const refundSubmitting = ref(false);
const refundError = ref('');

async function load() {
  loading.value = true;
  try {
    const body = await fetchBilling({ status: status.value || undefined, q: q.value || undefined });
    rows.value = body.data;
    meta.value = body.meta;
  } finally {
    loading.value = false;
  }
}

function openRefund(invoice) {
  refundTarget.value = invoice;
  refundAmount.value = '';
  refundError.value = '';
}

function closeRefund() {
  refundTarget.value = null;
}

const maxRefundable = computed(() => {
  if (!refundTarget.value) return 0;
  return Number(refundTarget.value.total) - Number(refundTarget.value.refunded_amount || 0);
});

async function confirmRefund() {
  if (!refundTarget.value) return;
  refundSubmitting.value = true;
  refundError.value = '';
  try {
    const payload = {};
    if (refundAmount.value !== '') payload.amount = Number(refundAmount.value);
    const body = await refundInvoice(refundTarget.value.id, payload);
    toast.show(body.message || 'Povrat je proveden.');
    closeRefund();
    await load();
  } catch (error) {
    if (error instanceof ApiError) {
      refundError.value = error.message;
    } else {
      refundError.value = 'Povrat nije uspio. Provjerite internet konekciju.';
    }
  } finally {
    refundSubmitting.value = false;
  }
}

watch(status, load);
watch(q, () => {
  if (qDebounce) window.clearTimeout(qDebounce);
  qDebounce = window.setTimeout(load, 300);
});

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="admin-main">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:24px;gap:16px">
        <h1 class="admin-h1">Naplata</h1>
        <p style="font-size:14px;font-weight:400;color:var(--bark)">Popust ide samo na rad. Materijal je prolazna pozicija.</p>
      </div>

      <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;margin-bottom:20px;justify-content:space-between">
        <nav class="filter-chip-row" aria-label="Filter po stanju fakture">
          <button
            v-for="o in STATUS_OPTIONS"
            :key="o.value"
            type="button"
            class="filter-chip"
            :class="{ active: status === o.value }"
            @click="status = o.value"
          >{{ o.label }} · {{ meta.counts?.[o.countKey] ?? 0 }}</button>
        </nav>
        <input v-model="q" type="text" placeholder="Pretražite klijenta ili broj" style="border:1px solid var(--ink);background:var(--white);padding:11px 14px;font-size:15px;width:280px">
      </div>

      <table class="table-haus" style="border:1px solid var(--sand)">
        <thead>
          <tr>
            <th>Faktura</th>
            <th>Klijent</th>
            <th>Osnov</th>
            <th class="num" style="text-align:right">Rad</th>
            <th class="num" style="text-align:right">Materijal</th>
            <th class="num" style="text-align:right">Ukupno</th>
            <th>Stanje</th>
            <th>Datum</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && rows.length === 0">
            <td colspan="9" style="color:var(--bark)">Nema faktura za ovaj filter.</td>
          </tr>
          <tr v-for="(inv, i) in rows" :key="inv.id" :class="{ zebra: i % 2 === 1 }">
            <td class="num" style="font-weight:600">{{ inv.number }}</td>
            <td>{{ inv.client?.name }}</td>
            <td style="color:var(--bark)">{{ inv.job ? inv.job.number : (inv.subscription ? inv.subscription.package : inv.type) }}</td>
            <td class="num" style="text-align:right">{{ inv.labor_total }} KM</td>
            <td class="num" style="text-align:right;color:var(--bark)">{{ inv.material_total }} KM</td>
            <td class="num" style="text-align:right;font-weight:700">{{ inv.total }} KM</td>
            <td>
              <span
                class="num"
                style="display:inline-flex;align-items:center;padding:4px 9px;font-size:12px;font-weight:600;white-space:nowrap"
                :style="{ border: `1px solid ${CHIP_STYLE[inv.status]?.border}`, background: CHIP_STYLE[inv.status]?.bg, color: CHIP_STYLE[inv.status]?.color }"
              >{{ inv.status }}</span>
            </td>
            <td class="num">{{ inv.paid_at ? new Date(inv.paid_at).toLocaleDateString('bs-BA') : (inv.created_at ? new Date(inv.created_at).toLocaleDateString('bs-BA') : '') }}</td>
            <td>
              <button
                v-if="inv.status === 'placeno' || inv.status === 'djelimicno_refundirano'"
                type="button"
                class="row-action-btn"
                @click="openRefund(inv)"
              >Refundirajte</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="refundTarget" class="modal-overlay" @click.self="closeRefund">
      <div class="modal-card">
        <div class="modal-card-head">Refundirajte fakturu {{ refundTarget.number }}</div>
        <div class="modal-card-body">
          <p style="font-size:14px;color:var(--bark)">Uplaćeno {{ refundTarget.total }} KM. Već refundirano {{ refundTarget.refunded_amount || 0 }} KM. Maksimalno za povrat {{ maxRefundable }} KM.</p>
          <div class="field">
            <label class="field-label" for="refund-amount">Iznos povrata (prazno = pun povrat)</label>
            <input id="refund-amount" v-model="refundAmount" type="number" min="0" :max="maxRefundable" step="0.01" class="num" placeholder="Pun povrat">
          </div>
          <p v-if="refundError" class="error-panel">{{ refundError }}</p>
          <div style="display:flex;gap:10px">
            <button type="button" class="btn btn-ghost-ink" style="flex:1" @click="closeRefund">Odustanite</button>
            <button type="button" class="btn btn-ember" style="flex:1" :disabled="refundSubmitting" @click="confirmRefund">
              {{ refundSubmitting ? 'Slanje...' : 'Potvrdite povrat' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
