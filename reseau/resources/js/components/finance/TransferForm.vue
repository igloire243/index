<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { isoDate } from '../../lib/dates';
import { convert, fmtMoney } from '../../lib/finance';
import { finance, transfer } from '../../stores/finance';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';

const props = defineProps({ modelValue: Boolean, from: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const today = isoDate();
const form = reactive({ from: null, to: null, amount: '', received: '', date: today, note: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const a = finance.accounts;
        const from = props.from?.id ?? a[0]?.id ?? null;
        Object.assign(form, { from, to: a.find((x) => x.id !== from)?.id ?? null, amount: '', received: '', date: today, note: '' });
    },
);

// Si l'origine devient identique à la destination, la destination passe automatiquement à un autre compte.
watch(
    () => form.from,
    (from) => {
        if (form.to === from) form.to = finance.accounts.find((a) => a.id !== from)?.id ?? null;
    },
);

const src = computed(() => finance.accounts.find((a) => a.id === form.from));
const dst = computed(() => finance.accounts.find((a) => a.id === form.to));
const sameCurrency = computed(() => src.value && dst.value && src.value.currency === dst.value.currency);
const amount = computed(() => (form.amount === '' ? NaN : Number(form.amount)));
// Montant suggéré d'après les taux de change, que l'utilisateur peut corriger.
const suggested = computed(() => (src.value && dst.value && amount.value > 0 ? convert(amount.value, src.value.currency, dst.value.currency, finance.settings) : null));

const steps = computed(() => [
    { key: 'accounts', title: 'Comptes', valid: !!form.from && !!form.to && form.from !== form.to, hint: 'Choisis deux comptes différents.' },
    {
        key: 'amount',
        title: 'Montant',
        valid: amount.value > 0 && (sameCurrency.value || Number(form.received) > 0),
        hint: sameCurrency.value ? 'Indique un montant supérieur à zéro.' : `Indique le montant envoyé et le montant reçu en ${dst.value?.currency}.`,
    },
]);

async function submit() {
    busy.value = true;
    try {
        await transfer({
            from_account_id: form.from, to_account_id: form.to, amount: amount.value,
            received_amount: sameCurrency.value ? null : Number(form.received), date: form.date || today, note: form.note || null,
        });
        open.value = false;
        toast('Virement enregistré.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" title="Virement entre comptes">
        <Wizard :steps="steps" :busy="busy" submit-label="Effectuer le virement" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'accounts'">
                    <div>
                        <label class="label" for="tr-from">Depuis</label>
                        <select id="tr-from" v-model="form.from" class="input">
                            <option v-for="a in finance.accounts" :key="a.id" :value="a.id">{{ a.name }} — {{ fmtMoney(a.balance, a.currency) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="tr-to">Vers</label>
                        <select id="tr-to" v-model="form.to" class="input">
                            <option v-for="a in finance.accounts.filter((x) => x.id !== form.from)" :key="a.id" :value="a.id">{{ a.name }} — {{ fmtMoney(a.balance, a.currency) }}</option>
                        </select>
                    </div>
                </template>
                <template v-else>
                    <div>
                        <label class="label" for="tr-amount">Montant envoyé ({{ src?.currency }})</label>
                        <input id="tr-amount" v-model="form.amount" class="input" type="number" inputmode="decimal" step="0.01" min="0" />
                    </div>
                    <div v-if="!sameCurrency">
                        <label class="label" for="tr-recv">Montant reçu ({{ dst?.currency }})</label>
                        <input id="tr-recv" v-model="form.received" class="input" type="number" inputmode="decimal" step="0.01" min="0" />
                        <button v-if="suggested != null" type="button" class="mt-2 text-sm text-indigo-600 underline dark:text-indigo-400" @click="form.received = String(Math.round(suggested * 100) / 100)">
                            Utiliser le taux enregistré : {{ fmtMoney(suggested, dst.currency) }}
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="tr-date">Date</label>
                            <input id="tr-date" v-model="form.date" class="input" type="date" :max="today" />
                        </div>
                        <div>
                            <label class="label" for="tr-note">Note</label>
                            <input id="tr-note" v-model="form.note" class="input" maxlength="200" autocomplete="off" />
                        </div>
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
