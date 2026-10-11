<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { ASSET_CATEGORIES, categoryOf, fmtMoney } from '../../lib/finance';
import { finance, saveAsset } from '../../stores/finance';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';
import CurrencyInput from './CurrencyInput.vue';

const props = defineProps({ modelValue: Boolean, asset: { type: Object, default: null }, category: { type: String, default: null }, status: { type: String, default: 'owned' } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ category: 'residence', status: 'owned', name: '', location: '', acquired_on: '', purchase_price: '', value: '', currency: 'USD', notes: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const a = props.asset;
        Object.assign(form, {
            category: a?.category ?? props.category ?? 'residence', status: a?.status ?? props.status, name: a?.name ?? '', location: a?.location ?? '',
            acquired_on: a?.acquired_on ?? '', purchase_price: a?.purchase_price ?? '', value: a?.value ?? '', currency: a?.currency ?? finance.settings.base_currency, notes: a?.notes ?? '',
        });
    },
);

const used = computed(() => [...new Set(finance.assets.map((a) => a.currency))]);
const wanted = computed(() => form.status === 'wanted');
const steps = computed(() => [
    { key: 'type', title: 'Type' },
    { key: 'asset', title: 'Bien', valid: form.name.trim().length > 0, hint: 'Donne un nom à ce bien pour continuer.' },
    { key: 'value', title: 'Valeur', valid: /^[A-Z]{3,6}$/.test(form.currency), hint: 'Indique une devise (3 à 6 lettres).' },
]);
const num = (v) => (v === '' || v == null ? null : Number(v));

async function submit() {
    busy.value = true;
    try {
        await saveAsset(
            {
                name: form.name, category: form.category, status: form.status, value: num(form.value), currency: form.currency,
                acquired_on: wanted.value ? null : form.acquired_on || null, purchase_price: wanted.value ? null : num(form.purchase_price),
                location: form.location || null, notes: form.notes || null,
            },
            props.asset?.id ?? null,
        );
        open.value = false;
        toast(props.asset ? 'Bien mis à jour.' : wanted.value ? 'Envie ajoutée.' : 'Bien ajouté.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="asset ? 'Modifier le bien' : 'Nouveau bien'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <div v-if="step.key === 'type'" class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Type de bien">
                    <button
                        v-for="c in ASSET_CATEGORIES"
                        :key="c.key"
                        type="button"
                        role="radio"
                        :aria-checked="form.category === c.key"
                        class="rounded-2xl border-2 p-2.5 text-left text-sm transition-colors"
                        :class="form.category === c.key ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'"
                        @click="form.category = c.key"
                    >
                        <span class="mr-1" aria-hidden="true">{{ c.emoji }}</span>{{ c.label }}
                    </button>
                </div>

                <template v-else-if="step.key === 'asset'">
                    <p class="muted">{{ categoryOf(form.category).emoji }} {{ categoryOf(form.category).label }}</p>
                    <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Statut">
                        <button type="button" role="radio" :aria-checked="!wanted" class="rounded-2xl border-2 p-3 text-center text-sm font-medium transition-colors" :class="!wanted ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'" @click="form.status = 'owned'">Je le possède</button>
                        <button type="button" role="radio" :aria-checked="wanted" class="rounded-2xl border-2 p-3 text-center text-sm font-medium transition-colors" :class="wanted ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'" @click="form.status = 'wanted'">Je le veux</button>
                    </div>
                    <div>
                        <label class="label" for="as-name">Nom *</label>
                        <input id="as-name" v-model="form.name" class="input" maxlength="120" placeholder="Toyota Hilux, parcelle à Mont-Ngafula…" autocomplete="off" />
                    </div>
                    <div>
                        <label class="label" for="as-loc">Où se trouve-t-il ?</label>
                        <input id="as-loc" v-model="form.location" class="input" maxlength="120" autocomplete="off" />
                    </div>
                    <div v-if="!wanted" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="as-date">Acquis le</label>
                            <input id="as-date" v-model="form.acquired_on" class="input" type="date" />
                        </div>
                        <div>
                            <label class="label" for="as-price">Prix d’achat</label>
                            <input id="as-price" v-model="form.purchase_price" class="input" type="number" inputmode="decimal" step="0.01" min="0" />
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div>
                        <label class="label" for="as-val">{{ wanted ? 'Prix visé' : 'Valeur estimée aujourd’hui' }}</label>
                        <input id="as-val" v-model="form.value" class="input" type="number" inputmode="decimal" step="0.01" min="0" />
                    </div>
                    <div>
                        <label class="label" for="as-cur">Devise</label>
                        <CurrencyInput id="as-cur" v-model="form.currency" :extra="used" />
                    </div>
                    <div>
                        <label class="label" for="as-notes">Notes</label>
                        <textarea id="as-notes" v-model="form.notes" class="input" rows="2" maxlength="1000" />
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <div class="font-semibold">{{ categoryOf(form.category).emoji }} {{ form.name || 'Sans nom' }}</div>
                        <div class="muted">{{ wanted ? 'Envie' : 'Possédé' }}<template v-if="form.value !== ''"> · {{ fmtMoney(Number(form.value), form.currency || 'USD') }}</template><template v-if="form.location"> · {{ form.location }}</template></div>
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
