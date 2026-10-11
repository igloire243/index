<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { ACCOUNT_TYPES, fmtMoney } from '../../lib/finance';
import { finance, saveAccount } from '../../stores/finance';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';
import CurrencyInput from './CurrencyInput.vue';

const props = defineProps({ modelValue: Boolean, account: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ name: '', type: 'bank', institution: '', location: '', source: '', notes: '', currency: 'USD', balance: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const a = props.account;
        Object.assign(form, {
            name: a?.name ?? '', type: a?.type ?? 'bank', institution: a?.institution ?? '', location: a?.location ?? '', source: a?.source ?? '',
            notes: a?.notes ?? '', currency: a?.currency ?? finance.settings.base_currency, balance: '',
        });
    },
);

const usedCurrencies = computed(() => [...new Set(finance.accounts.map((a) => a.currency))]);
const steps = computed(() => [
    { key: 'account', title: 'Compte', valid: form.name.trim().length > 0, hint: 'Donne un nom à ce compte pour continuer.' },
    { key: 'where', title: 'Où ?' },
    { key: 'balance', title: 'Solde', valid: /^[A-Z]{3,6}$/.test(form.currency), hint: 'Indique une devise (3 à 6 lettres, par exemple USD ou CDF).' },
]);

async function submit() {
    busy.value = true;
    try {
        const body = {
            name: form.name, type: form.type, institution: form.institution || null, location: form.location || null,
            source: form.source || null, notes: form.notes || null, currency: form.currency,
        };
        if (!props.account) body.balance = form.balance === '' ? 0 : Number(form.balance);
        await saveAccount(body, props.account?.id ?? null);
        open.value = false;
        toast(props.account ? 'Compte mis à jour.' : 'Compte ajouté.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="account ? 'Modifier le compte' : 'Nouveau compte'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer le compte" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'account'">
                    <div>
                        <label class="label" for="ac-name">Nom *</label>
                        <input id="ac-name" v-model="form.name" class="input" maxlength="120" placeholder="Compte courant, Mobile money pro…" autocomplete="off" />
                    </div>
                    <div>
                        <span class="label">Type</span>
                        <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Type de compte">
                            <button
                                v-for="(t, key) in ACCOUNT_TYPES"
                                :key="key"
                                type="button"
                                role="radio"
                                :aria-checked="form.type === key"
                                class="rounded-2xl border-2 p-3 text-left text-sm transition-colors"
                                :class="form.type === key ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'"
                                @click="form.type = key"
                            >
                                <span class="mr-1" aria-hidden="true">{{ t.emoji }}</span>{{ t.label }}
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="step.key === 'where'">
                    <div>
                        <label class="label" for="ac-inst">Banque, opérateur ou organisme</label>
                        <input id="ac-inst" v-model="form.institution" class="input" maxlength="120" placeholder="Rawbank, M-Pesa, Airtel Money…" autocomplete="off" />
                    </div>
                    <div>
                        <label class="label" for="ac-loc">Lieu où c’est conservé</label>
                        <input id="ac-loc" v-model="form.location" class="input" maxlength="120" placeholder="Agence de Gombe, coffre à la maison…" autocomplete="off" />
                    </div>
                    <div>
                        <label class="label" for="ac-src">D’où vient cet argent ?</label>
                        <input id="ac-src" v-model="form.source" class="input" maxlength="120" placeholder="Salaire, commerce, loyers, don…" autocomplete="off" />
                    </div>
                    <div>
                        <label class="label" for="ac-notes">Notes</label>
                        <textarea id="ac-notes" v-model="form.notes" class="input" rows="2" maxlength="1000" />
                    </div>
                </template>

                <template v-else>
                    <div>
                        <label class="label" for="ac-cur">Devise</label>
                        <CurrencyInput id="ac-cur" v-model="form.currency" :disabled="!!account" :extra="usedCurrencies" />
                        <p v-if="account" class="muted mt-1">La devise d’un compte ne change pas : crée un autre compte si besoin.</p>
                    </div>
                    <div v-if="!account">
                        <label class="label" for="ac-bal">Solde actuel</label>
                        <input id="ac-bal" v-model="form.balance" class="input" type="number" inputmode="decimal" step="0.01" placeholder="0" />
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <div class="font-semibold">{{ ACCOUNT_TYPES[form.type].emoji }} {{ form.name || 'Sans nom' }}</div>
                        <div class="muted">{{ [ACCOUNT_TYPES[form.type].label, form.institution, form.location].filter(Boolean).join(' · ') }}</div>
                        <div v-if="!account && form.balance !== ''" class="mt-1 font-semibold">{{ fmtMoney(Number(form.balance), form.currency || 'USD') }}</div>
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
