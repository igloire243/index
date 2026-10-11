<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { isoDate } from '../../lib/dates';
import { fmtMoney } from '../../lib/finance';
import { addPayment, finance } from '../../stores/finance';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';

const props = defineProps({ modelValue: Boolean, debt: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const today = isoDate();
const form = reactive({ amount: '', date: today, account_id: '', note: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => isOpen && Object.assign(form, { amount: '', date: today, account_id: '', note: '' }),
);

const received = computed(() => props.debt?.direction === 'owed_to_me');
const accounts = computed(() => finance.accounts.filter((a) => a.currency === props.debt?.currency));
const amount = computed(() => (form.amount === '' ? NaN : Number(form.amount)));
const steps = computed(() => [
    { key: 'amount', title: 'Montant', valid: amount.value > 0 && amount.value <= (props.debt?.remaining ?? 0) + 0.004, hint: `Indique un montant entre 0 et ${props.debt ? fmtMoney(props.debt.remaining, props.debt.currency) : ''}.` },
    { key: 'account', title: 'Compte' },
]);
const target = computed(() => accounts.value.find((a) => a.id === Number(form.account_id)));

async function submit() {
    busy.value = true;
    try {
        await addPayment(props.debt.id, { amount: amount.value, date: form.date || today, account_id: form.account_id ? Number(form.account_id) : null, note: form.note || null });
        open.value = false;
        toast(received.value ? 'Remboursement reçu enregistré.' : 'Remboursement enregistré.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="debt ? (received ? `Remboursement de ${debt.counterparty}` : `Remboursement à ${debt.counterparty}`) : 'Remboursement'">
        <Wizard v-if="debt" :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'amount'">
                    <p class="muted">Reste à régler : <b class="text-slate-900 dark:text-slate-100">{{ fmtMoney(debt.remaining, debt.currency) }}</b></p>
                    <div>
                        <label class="label" for="pay-amount">Montant ({{ debt.currency }})</label>
                        <input id="pay-amount" v-model="form.amount" class="input" type="number" inputmode="decimal" step="0.01" min="0" :max="debt.remaining" />
                        <button type="button" class="mt-2 text-sm text-indigo-600 underline dark:text-indigo-400" @click="form.amount = String(debt.remaining)">Tout régler</button>
                    </div>
                    <div>
                        <label class="label" for="pay-date">Date</label>
                        <input id="pay-date" v-model="form.date" class="input" type="date" :max="today" />
                    </div>
                </template>
                <template v-else>
                    <div>
                        <label class="label" for="pay-acc">{{ received ? 'Sur quel compte l’argent est-il arrivé ?' : 'De quel compte l’argent est-il parti ?' }}</label>
                        <select id="pay-acc" v-model="form.account_id" class="input">
                            <option value="">Aucun : seulement noter le règlement</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }} — {{ fmtMoney(a.balance, a.currency) }}</option>
                        </select>
                        <p class="muted mt-1">Le solde du compte est mis à jour automatiquement. Seuls les comptes en {{ debt.currency }} sont proposés.</p>
                    </div>
                    <div>
                        <label class="label" for="pay-note">Note</label>
                        <input id="pay-note" v-model="form.note" class="input" maxlength="200" autocomplete="off" />
                    </div>
                    <p v-if="target" class="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        {{ target.name }} : {{ fmtMoney(target.balance, target.currency) }} → <b>{{ fmtMoney(target.balance + (received ? 1 : -1) * amount, target.currency) }}</b>
                    </p>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
