<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { isoDate } from '../../lib/dates';
import { DIRECTIONS, fmtMoney } from '../../lib/finance';
import { contacts } from '../../stores/contacts';
import { finance, saveDebt } from '../../stores/finance';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';
import CurrencyInput from './CurrencyInput.vue';

const props = defineProps({ modelValue: Boolean, debt: { type: Object, default: null }, direction: { type: String, default: 'owed_to_me' } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const today = isoDate();
const form = reactive({ direction: 'owed_to_me', counterparty: '', contact_id: '', amount: '', currency: 'USD', reason: '', opened_on: today, due_on: '', notes: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const d = props.debt;
        Object.assign(form, {
            direction: d?.direction ?? props.direction, counterparty: d?.counterparty ?? '', contact_id: d?.contact_id ?? '',
            amount: d?.amount ?? '', currency: d?.currency ?? finance.settings.base_currency, reason: d?.reason ?? '',
            opened_on: d?.opened_on ?? today, due_on: d?.due_on ?? '', notes: d?.notes ?? '',
        });
    },
);

const used = computed(() => [...new Set(finance.debts.map((d) => d.currency))]);
const amount = computed(() => (form.amount === '' ? NaN : Number(form.amount)));
const steps = computed(() => [
    { key: 'who', title: 'Qui', valid: form.counterparty.trim().length > 0 || !!form.contact_id, hint: 'Indique la personne concernée, ou choisis un contact.' },
    { key: 'amount', title: 'Montant', valid: amount.value > 0 && /^[A-Z]{3,6}$/.test(form.currency), hint: 'Indique un montant supérieur à zéro et une devise.' },
    { key: 'dates', title: 'Dates', valid: !form.due_on || form.due_on >= form.opened_on, hint: 'L’échéance ne peut pas précéder la date de départ.' },
]);

function onContact() {
    const c = contacts.list.find((x) => x.id === Number(form.contact_id));
    if (c && !form.counterparty.trim()) form.counterparty = c.name;
}

async function submit() {
    busy.value = true;
    try {
        await saveDebt(
            {
                direction: form.direction, counterparty: form.counterparty.trim() || null, contact_id: form.contact_id ? Number(form.contact_id) : null,
                amount: amount.value, currency: form.currency, reason: form.reason || null, opened_on: form.opened_on || today,
                due_on: form.due_on || null, notes: form.notes || null,
            },
            props.debt?.id ?? null,
        );
        open.value = false;
        toast(props.debt ? 'Mise à jour enregistrée.' : 'Enregistré.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="debt ? 'Modifier' : form.direction === 'owed_to_me' ? 'Nouvelle créance' : 'Nouvelle dette'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'who'">
                    <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Sens">
                        <button
                            v-for="(label, key) in DIRECTIONS"
                            :key="key"
                            type="button"
                            role="radio"
                            :aria-checked="form.direction === key"
                            class="rounded-2xl border-2 p-3 text-center font-medium transition-colors"
                            :class="form.direction === key ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'"
                            @click="form.direction = key"
                        >
                            {{ label }}
                        </button>
                    </div>
                    <div>
                        <label class="label" for="db-who">{{ form.direction === 'owed_to_me' ? 'Qui te doit de l’argent ?' : 'À qui dois-tu de l’argent ?' }}</label>
                        <input id="db-who" v-model="form.counterparty" class="input" maxlength="120" autocomplete="off" />
                    </div>
                    <div v-if="contacts.list.length">
                        <label class="label" for="db-contact">Ou choisis un contact <span class="muted">(facultatif)</span></label>
                        <select id="db-contact" v-model="form.contact_id" class="input" @change="onContact">
                            <option value="">Aucun</option>
                            <option v-for="c in contacts.list" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </template>

                <template v-else-if="step.key === 'amount'">
                    <div>
                        <label class="label" for="db-amount">Montant</label>
                        <input id="db-amount" v-model="form.amount" class="input" type="number" inputmode="decimal" step="0.01" min="0" />
                    </div>
                    <div>
                        <label class="label" for="db-cur">Devise</label>
                        <CurrencyInput id="db-cur" v-model="form.currency" :extra="used" :disabled="!!debt && debt.payments?.length > 0" />
                    </div>
                    <div>
                        <label class="label" for="db-reason">Pour quoi ? <span class="muted">(facultatif)</span></label>
                        <input id="db-reason" v-model="form.reason" class="input" maxlength="200" placeholder="Prêt, avance, marchandise…" autocomplete="off" />
                    </div>
                </template>

                <template v-else>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="db-open">Date de départ</label>
                            <input id="db-open" v-model="form.opened_on" class="input" type="date" />
                        </div>
                        <div>
                            <label class="label" for="db-due">Échéance</label>
                            <input id="db-due" v-model="form.due_on" class="input" type="date" :min="form.opened_on" />
                        </div>
                    </div>
                    <div>
                        <label class="label" for="db-notes">Notes</label>
                        <textarea id="db-notes" v-model="form.notes" class="input" rows="2" maxlength="1000" />
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <div class="font-semibold">{{ DIRECTIONS[form.direction] }} · {{ form.counterparty || contacts.list.find((c) => c.id === Number(form.contact_id))?.name || '—' }}</div>
                        <div class="muted">{{ fmtMoney(amount, form.currency || 'USD') }}<template v-if="form.reason"> · {{ form.reason }}</template><template v-if="form.due_on"> · avant le {{ form.due_on }}</template></div>
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
