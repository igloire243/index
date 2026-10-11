<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { finance, saveSettings } from '../../stores/finance';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';
import CurrencyInput from './CurrencyInput.vue';

const props = defineProps({ modelValue: Boolean });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ base: 'USD', rates: {}, extra: '' });
const busy = ref(false);

// Toutes les devises présentes dans les données, plus celles déjà configurées.
const allCurrencies = computed(() => {
    const set = new Set([...finance.accounts.map((a) => a.currency), ...finance.debts.map((d) => d.currency), ...finance.assets.map((a) => a.currency), ...Object.keys(form.rates)]);
    if (/^[A-Z]{3,6}$/.test(form.extra)) set.add(form.extra);
    set.delete(form.base);
    return [...set].sort();
});

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        form.base = finance.settings.base_currency;
        form.rates = Object.fromEntries(Object.entries(finance.settings.rates).map(([k, v]) => [k, String(v)]));
        form.extra = '';
    },
);

const steps = computed(() => [
    { key: 'base', title: 'Référence', valid: /^[A-Z]{3,6}$/.test(form.base), hint: 'Indique la devise de référence (3 à 6 lettres).' },
    { key: 'rates', title: 'Taux' },
]);

async function submit() {
    busy.value = true;
    try {
        const rates = {};
        for (const c of allCurrencies.value) {
            const n = Number(form.rates[c]);
            if (n > 0) rates[c] = n;
        }
        await saveSettings({ base_currency: form.base, rates });
        open.value = false;
        toast('Taux enregistrés.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" title="Taux de change">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer les taux" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'base'">
                    <p class="muted">Pour additionner des comptes en devises différentes, tout est converti dans une devise de référence. Les taux sont ceux que tu saisis : ils ne se mettent pas à jour tout seuls.</p>
                    <div>
                        <label class="label" for="rt-base">Devise de référence</label>
                        <CurrencyInput id="rt-base" v-model="form.base" />
                    </div>
                </template>
                <template v-else>
                    <p v-if="!allCurrencies.length" class="muted">Tu n’utilises aucune autre devise pour l’instant.</p>
                    <div v-for="c in allCurrencies" :key="c">
                        <label class="label" :for="`rt-${c}`">1 {{ form.base }} =</label>
                        <div class="flex items-center gap-2">
                            <input :id="`rt-${c}`" v-model="form.rates[c]" class="input" type="number" inputmode="decimal" step="any" min="0" placeholder="Taux" />
                            <span class="w-14 shrink-0 font-medium">{{ c }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="rt-extra">Ajouter une autre devise</label>
                        <CurrencyInput id="rt-extra" v-model="form.extra" />
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
