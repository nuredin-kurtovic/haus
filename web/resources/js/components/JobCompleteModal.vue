<script setup>
import { computed, onMounted, ref } from 'vue';
import { completeJob, fetchPriceList, ApiError } from '../pages/admin/api';

const props = defineProps({
  jobId: { type: [Number, String], required: true },
});
const emit = defineEmits(['close', 'completed']);

const findings = ref('');
const itemSearch = ref('');
const priceCategories = ref([]);
const selectedItems = ref([]);
const materials = ref([]);
const photosBefore = ref([]);
const photosAfter = ref([]);
const submitting = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});

const flatItems = computed(() => {
  const rows = [];
  priceCategories.value.forEach((cat) => {
    (cat.items || []).forEach((item) => {
      if (item.active === false) return;
      rows.push({ id: item.id, name: item.name, category: cat.name, base_price: item.base_price });
    });
  });
  return rows;
});

const filteredItems = computed(() => {
  const q = itemSearch.value.trim().toLowerCase();
  if (!q) return [];
  return flatItems.value.filter((i) => i.name.toLowerCase().includes(q) || i.category.toLowerCase().includes(q)).slice(0, 12);
});

function addItem(item) {
  if (selectedItems.value.some((i) => i.price_item_id === item.id)) return;
  selectedItems.value.push({ price_item_id: item.id, name: item.name, base_price: item.base_price, qty: 1 });
  itemSearch.value = '';
}

function removeItem(idx) {
  selectedItems.value.splice(idx, 1);
}

function addMaterialRow() {
  materials.value.push({ name: '', purchase_price: '', qty: 1 });
}

function removeMaterialRow(idx) {
  materials.value.splice(idx, 1);
}

function onPhotoChange(event, target) {
  const files = Array.from(event.target.files || []);
  if (target === 'prije') photosBefore.value = files;
  else photosAfter.value = files;
}

const canSubmit = computed(() => (
  findings.value.trim().length > 0 && photosBefore.value.length > 0 && photosAfter.value.length > 0 && !submitting.value
));

async function submit() {
  errorMessage.value = '';
  fieldErrors.value = {};
  if (photosBefore.value.length === 0 || photosAfter.value.length === 0) {
    errorMessage.value = 'Potrebna je najmanje jedna fotografija prije i jedna poslije.';
    return;
  }
  submitting.value = true;
  try {
    const formData = new FormData();
    formData.append('findings', findings.value);
    selectedItems.value.forEach((item, idx) => {
      formData.append(`items[${idx}][price_item_id]`, String(item.price_item_id));
      formData.append(`items[${idx}][qty]`, String(item.qty));
    });
    materials.value.forEach((mat, idx) => {
      formData.append(`materials[${idx}][name]`, mat.name);
      formData.append(`materials[${idx}][purchase_price]`, String(mat.purchase_price || 0));
      formData.append(`materials[${idx}][qty]`, String(mat.qty || 1));
    });
    photosBefore.value.forEach((file) => formData.append('photos_before[]', file));
    photosAfter.value.forEach((file) => formData.append('photos_after[]', file));

    const body = await completeJob(props.jobId, formData);
    emit('completed', body.data);
  } catch (error) {
    if (error instanceof ApiError) {
      errorMessage.value = error.message;
      fieldErrors.value = error.errors || {};
    } else {
      errorMessage.value = 'Završetak naloga nije uspio. Provjerite internet konekciju.';
    }
  } finally {
    submitting.value = false;
  }
}

onMounted(async () => {
  priceCategories.value = await fetchPriceList();
});
</script>

<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-card" style="max-width:640px">
      <div class="modal-card-head" style="display:flex;align-items:center;justify-content:space-between;gap:16px">
        <span>Završite nalog</span>
        <button type="button" class="btn btn-ghost-ivory" style="padding:6px 12px;font-size:12px" @click="$emit('close')">Zatvorite</button>
      </div>
      <div class="modal-card-body">
        <p v-if="errorMessage" class="error-panel">{{ errorMessage }}</p>

        <div class="field">
          <label class="field-label" for="findings">Nalaz</label>
          <textarea id="findings" v-model="findings" rows="4" placeholder="Šta je urađeno, šta je pronađeno na terenu."></textarea>
          <span v-if="fieldErrors.findings" class="field-error-text">{{ fieldErrors.findings[0] }}</span>
        </div>

        <div>
          <span class="field-label" style="display:block;margin-bottom:8px">Stavke rada</span>
          <input
            v-model="itemSearch"
            type="text"
            placeholder="Pretražite pozicije iz cjenovnika"
            style="border:1px solid var(--ink);background:var(--white);padding:11px 12px;font-size:15px;width:100%;margin-bottom:6px"
          >
          <div v-if="filteredItems.length > 0" style="border:1px solid var(--sand);margin-bottom:10px;max-height:180px;overflow-y:auto">
            <button
              v-for="item in filteredItems"
              :key="item.id"
              type="button"
              style="display:flex;justify-content:space-between;gap:10px;width:100%;border:0;border-bottom:1px solid var(--sand);background:var(--white);padding:9px 12px;font-size:14px;text-align:left;cursor:pointer"
              @click="addItem(item)"
            >
              <span>{{ item.name }}</span>
              <span class="num" style="color:var(--bark)">{{ item.base_price }} KM</span>
            </button>
          </div>
          <div v-if="selectedItems.length === 0" style="font-size:13px;color:var(--bark);margin-bottom:8px">Nema dodanih stavki. Pretražite i dodajte poziciju iz cjenovnika.</div>
          <div v-for="(item, idx) in selectedItems" :key="item.price_item_id" style="display:grid;grid-template-columns:1fr 80px 32px;gap:8px;align-items:center;margin-bottom:8px">
            <span style="font-size:14px">{{ item.name }}</span>
            <input v-model.number="item.qty" type="number" min="1" class="num" style="border:1px solid var(--sand);padding:8px;text-align:right">
            <button type="button" aria-label="Uklonite stavku" style="border:1px solid var(--sand);background:transparent;cursor:pointer;height:36px" @click="removeItem(idx)">×</button>
          </div>
          <span v-if="fieldErrors.items" class="field-error-text">{{ fieldErrors.items[0] }}</span>
        </div>

        <div>
          <span class="field-label" style="display:block;margin-bottom:8px">Materijal</span>
          <div v-for="(mat, idx) in materials" :key="idx" style="display:grid;grid-template-columns:1fr 90px 60px 32px;gap:8px;align-items:center;margin-bottom:8px">
            <input v-model="mat.name" type="text" placeholder="Naziv materijala" style="border:1px solid var(--sand);padding:8px">
            <input v-model.number="mat.purchase_price" type="number" min="0" step="0.01" placeholder="Nabavna" class="num" style="border:1px solid var(--sand);padding:8px;text-align:right">
            <input v-model.number="mat.qty" type="number" min="1" placeholder="Kom" class="num" style="border:1px solid var(--sand);padding:8px;text-align:right">
            <button type="button" aria-label="Uklonite materijal" style="border:1px solid var(--sand);background:transparent;cursor:pointer;height:36px" @click="removeMaterialRow(idx)">×</button>
          </div>
          <button type="button" class="btn btn-ghost-ink" style="padding:9px 14px;font-size:13px" @click="addMaterialRow">Dodajte materijal</button>
          <span v-if="fieldErrors.materials" class="field-error-text" style="display:block;margin-top:6px">{{ fieldErrors.materials[0] }}</span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="field">
            <label class="field-label" for="photos-before">Fotografije prije (najmanje 1)</label>
            <input id="photos-before" type="file" accept="image/*" multiple @change="onPhotoChange($event, 'prije')">
            <span style="font-size:13px;color:var(--bark)">{{ photosBefore.length }} odabrano</span>
            <span v-if="fieldErrors.photos_before" class="field-error-text">{{ fieldErrors.photos_before[0] }}</span>
          </div>
          <div class="field">
            <label class="field-label" for="photos-after">Fotografije poslije (najmanje 1)</label>
            <input id="photos-after" type="file" accept="image/*" multiple @change="onPhotoChange($event, 'poslije')">
            <span style="font-size:13px;color:var(--bark)">{{ photosAfter.length }} odabrano</span>
            <span v-if="fieldErrors.photos_after" class="field-error-text">{{ fieldErrors.photos_after[0] }}</span>
          </div>
        </div>

        <button type="button" class="btn btn-ember btn-block" :disabled="!canSubmit" @click="submit">
          {{ submitting ? 'Slanje...' : 'Završite nalog' }}
        </button>
      </div>
    </div>
  </div>
</template>
