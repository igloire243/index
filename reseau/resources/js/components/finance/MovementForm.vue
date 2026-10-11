<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { fmtFull, isoDate } from '../../lib/dates';
import { fmtMoney } from '../../lib/finance';
import { addMovement, adjustAccount } from '../../stores/finance';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';

const props = defineProps({ modelValue: Boolean, account: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const today = isoDate();
const form = reactive({ kind: 'deposit', amount: '', date: today, note: '' });
const busy = ref(false);

const KINDS = [
    { key: 'deposit', title: 'Entrée d’argent', text: 'Salaire, vente, remboursement reçu…', emoji: '⬇️' },
    { key: 'withdrawal', title: 'Sortie d’argent', text: 'Dépense, retrait, paiement…', emoji: '⬆️' },
    { key: 'adjust', title: 'Corriger le solde', text: 'Remettre le solde réel constaté.', emoji: '🎯' },
];

watch(
    () => props.modelValue,
    (isOpen) => isOpen && Object.assign(form, { kind: 'deposit', amount: '', date: today, note: '' }),
);

const amount = computed(() => (form.amount === '' ? NaN : Number(form.amount)));
const newBalance = computed(() => {
    if (!props.account || Number.isNaN(amount.value)) return null;
    if (form.kind === 'adjust') return amount.value;
    return props.account.balance + (form.kind === 'deposit' ? amount.value : -amount.value);
});
const steps = computed(() => [
    { key: 'kind', title: 'Opération' },
    {
        key: 'details',
        title: 'Détails',
        valid: form.kind === 'adjust' ? !Number.isNaN(amount.value) && Math.abs(amount.value - (props.account?.balance ?? 0)) > 0.004 : amount.value > 0,
        hint: form.kind === 'adjust' ? 'Indique un solde différent du solde actuel.' : 'Indique un montant supérieur à zéro.',
    },
]);

async function submit() {
    busy.value = true;
    try {
        const common = { date: form.date || today, note: form.note || null };
        if (form.kind === 'adjust') await adjustAccount(props.account.id, { ...common, balance: amount.value });
        else await addMovement(props.account.id, { ...common, kind: form.kind, amount: amount.value });
        open.value = false;
        toast('Mouvement enregistré.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="account ? `Mouvement · ${account.name}` : 'Mouvement'">
        <Wizard v-if="account" :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <div v-if="step.key === 'kind'" class="space-y-2" role="radiogroup" aria-label="Type d’opération">
                    <button
                        v-for="k in KINDS"
                        :key="k.key"
                        type="button"
                        role="radio"
                        :aria-checked="form.kind === k.key"
                        data-autofocus
                        class="w-full rounded-2xl border-2 p-3 text-left transition-colors"
                        :class="form.kind === k.key ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'"
                        @click="form.kind = k.key"
                    >
                        <span class="block font-semibold"><span aria-hidden="true">{{ k.emoji }}</span> {{ k.title }}</span>
                        <span class="muted block">{{ k.text }}</span>
                    </button>
                </div>
                <template v-else>
                    <div>
                        <label class="label" for="mv-amount">{{ form.kind === 'adjust' ? `Solde réel (${account.currency})` : `Montant (${account.currency})` }}</label>
                        <input id="mv-amount" v-model="form.amount" class="input" type="number" inputmode="decimal" step="0.01" min="0" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="mv-date">Date</label>
                            <input id="mv-date" v-model="form.date" class="input" type="date" :max="today" />
                        </div>
                        <div>
                            <label class="label" for="mv-note">Note</label>
                            <input id="mv-note" v-model="form.note" class="input" maxlength="200" autocomplete="off" />
                        </div>
                    </div>
                    <p v-if="newBalance != null" class="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        Solde actuel {{ fmtMoney(account.balance, account.currency) }} → <b>{{ fmtMoney(newBalance, account.currency) }}</b>
                        <span class="muted"> le {{ fmtFull(form.date || today) }}</span>
                    </p>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
