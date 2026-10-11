<script setup>
import { computed, ref } from 'vue';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import Meter from '../../components/Meter.vue';
import DebtForm from '../../components/finance/DebtForm.vue';
import PaymentForm from '../../components/finance/PaymentForm.vue';
import { fmtFull, isoDate } from '../../lib/dates';
import { dueLabel, daysUntil, fmtMoney, totalsByCurrency } from '../../lib/finance';
import { openForm as openContactForm, openDetail } from '../../stores/contacts';
import { finance, removeDebt, removePayment } from '../../stores/finance';
import { toast } from '../../stores/session';

const props = defineProps({ direction: { type: String, required: true } });
const today = isoDate();

const incoming = computed(() => props.direction === 'owed_to_me');
const showSettled = ref(false);
const formOpen = ref(false);
const editing = ref(null);
const payOpen = ref(false);
const paying = ref(null);
const expanded = ref(new Set());

const all = computed(() => finance.debts.filter((d) => d.direction === props.direction));
const open = computed(() => all.value.filter((d) => d.remaining > 0.004));
const settled = computed(() => all.value.filter((d) => d.remaining <= 0.004));
const shown = computed(() => (showSettled.value ? all.value : open.value));
const totals = computed(() => totalsByCurrency(open.value.map((d) => ({ amount: d.remaining, currency: d.currency }))));

const edit = (d = null) => {
    editing.value = d;
    formOpen.value = true;
};
const pay = (d) => {
    paying.value = d;
    payOpen.value = true;
};
const toggle = (id) => (expanded.value.has(id) ? expanded.value.delete(id) : expanded.value.add(id));

async function remove(d) {
    if (!confirm(`Supprimer « ${d.counterparty} » et ses règlements ? Les comptes déjà mis à jour ne sont pas modifiés.`)) return;
    try {
        await removeDebt(d.id);
        toast('Supprimé.');
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function undo(d, p) {
    if (!confirm(`Annuler le règlement de ${fmtMoney(p.amount, d.currency)} ? Le compte lié sera corrigé.`)) return;
    try {
        await removePayment(d.id, p.id);
        toast('Règlement annulé.');
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <div class="min-w-0">
                <div class="muted">{{ incoming ? 'Reste à recevoir' : 'Reste à rembourser' }}</div>
                <div class="flex flex-wrap gap-x-3 font-semibold">
                    <span v-for="t in totals" :key="t.currency">{{ fmtMoney(t.total, t.currency) }}</span>
                    <span v-if="!totals.length" class="text-slate-400">Rien</span>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-sm shrink-0" @click="edit()"><Icon name="plus" :size="16" /> Ajouter</button>
        </div>

        <label v-if="settled.length" class="muted flex items-center gap-2">
            <input v-model="showSettled" type="checkbox" class="size-4 rounded" /> Afficher les {{ settled.length }} soldée{{ settled.length > 1 ? 's' : '' }}
        </label>

        <EmptyState v-if="finance.loaded && !shown.length" :illustration="incoming ? 'contacts' : 'finance'" :title="incoming ? 'Personne ne te doit rien' : 'Aucune dette'" small>
            <p>{{ incoming ? 'Note ici l’argent que tu as prêté pour ne jamais l’oublier.' : 'Note ici ce que tu dois pour planifier tes remboursements.' }}</p>
        </EmptyState>

        <TransitionGroup name="list" tag="ul" class="relative space-y-3">
            <li v-for="(d, i) in shown" :key="d.id" class="card rise space-y-3" :style="{ '--i': Math.min(i, 8) }">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full text-white" :class="d.remaining <= 0.004 ? 'bg-emerald-500' : incoming ? 'bg-sky-500' : 'bg-rose-500'">
                        <Icon :name="d.remaining <= 0.004 ? 'check' : incoming ? 'left' : 'right'" :size="20" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold break-words">
                            <button v-if="d.contact_id" type="button" class="text-left underline decoration-dotted" @click="openDetail(d.contact_id)">{{ d.counterparty }}</button>
                            <template v-else>{{ d.counterparty }}</template>
                        </div>
                        <div v-if="d.reason" class="muted break-words">{{ d.reason }}</div>
                        <div class="muted">Depuis le {{ fmtFull(d.opened_on) }}</div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="font-bold">{{ fmtMoney(d.remaining, d.currency) }}</div>
                        <div v-if="d.paid > 0" class="muted text-xs">sur {{ fmtMoney(d.amount, d.currency) }}</div>
                    </div>
                </div>

                <Meter v-if="d.paid > 0" label="Réglé" :value="fmtMoney(d.paid, d.currency)" :ratio="d.paid / d.amount" tone="emerald" />

                <div class="flex flex-wrap items-center gap-2">
                    <span v-if="d.remaining <= 0.004" class="tag !bg-emerald-100 text-emerald-800 dark:!bg-emerald-950 dark:text-emerald-300">Soldée</span>
                    <span v-else-if="d.due_on" class="tag" :class="d.overdue ? '!bg-rose-100 text-rose-700 dark:!bg-rose-950 dark:text-rose-300' : ''">
                        {{ fmtFull(d.due_on) }} · {{ dueLabel(daysUntil(d.due_on, today)) }}
                    </span>
                    <span v-else class="tag">Sans échéance</span>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button v-if="d.remaining > 0.004" type="button" class="btn btn-primary btn-sm" @click="pay(d)">{{ incoming ? 'Remboursement reçu' : 'Rembourser' }}</button>
                    <button v-if="d.payments.length" type="button" class="btn btn-sm" :aria-expanded="expanded.has(d.id)" @click="toggle(d.id)">Règlements ({{ d.payments.length }})</button>
                    <button type="button" class="btn btn-sm" @click="edit(d)">Modifier</button>
                    <button type="button" class="btn btn-danger btn-sm" @click="remove(d)">Supprimer</button>
                </div>

                <Transition name="fade">
                    <ul v-if="expanded.has(d.id)" class="space-y-1.5">
                        <li v-for="p in d.payments" :key="p.id" class="flex items-center gap-2 rounded-xl bg-slate-50 p-2.5 text-sm dark:bg-slate-800">
                            <span class="min-w-0 flex-1"><b>{{ fmtMoney(p.amount, d.currency) }}</b> <span class="muted">le {{ fmtFull(p.date) }}<template v-if="p.note"> · {{ p.note }}</template></span></span>
                            <button type="button" class="btn btn-danger size-8 !p-0" :aria-label="`Annuler le règlement du ${fmtFull(p.date)}`" @click="undo(d, p)"><Icon name="x" :size="14" /></button>
                        </li>
                    </ul>
                </Transition>
            </li>
        </TransitionGroup>

        <DebtForm v-model="formOpen" :debt="editing" :direction="direction" />
        <PaymentForm v-model="payOpen" :debt="paying && finance.debts.find((x) => x.id === paying.id)" />
    </div>
</template>
