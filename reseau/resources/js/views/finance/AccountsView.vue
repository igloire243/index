<script setup>
import { computed, ref } from 'vue';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import AccountForm from '../../components/finance/AccountForm.vue';
import MovementForm from '../../components/finance/MovementForm.vue';
import MovementsSheet from '../../components/finance/MovementsSheet.vue';
import TransferForm from '../../components/finance/TransferForm.vue';
import { ACCOUNT_TYPES, fmtMoney, totalsByCurrency } from '../../lib/finance';
import { archiveAccount, finance } from '../../stores/finance';
import { toast } from '../../stores/session';

const formOpen = ref(false);
const editing = ref(null);
const moveOpen = ref(false);
const historyOpen = ref(false);
const transferOpen = ref(false);
const selected = ref(null);

const totals = computed(() => totalsByCurrency(finance.accounts.map((a) => ({ amount: a.balance, currency: a.currency }))));
const accounts = computed(() => [...finance.accounts].sort((a, b) => a.type.localeCompare(b.type) || a.name.localeCompare(b.name, 'fr')));

const edit = (a = null) => {
    editing.value = a;
    formOpen.value = true;
};
const act = (a, which) => {
    selected.value = a;
    if (which === 'move') moveOpen.value = true;
    else if (which === 'history') historyOpen.value = true;
    else if (which === 'transfer') transferOpen.value = true;
};

async function archive(a) {
    if (!confirm(`Archiver « ${a.name} » ? Il disparaît de la liste mais son historique est conservé.`)) return;
    try {
        await archiveAccount(a.id);
        toast('Compte archivé.');
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <div class="flex flex-wrap gap-2">
                <span v-for="t in totals" :key="t.currency" class="chip font-semibold">{{ fmtMoney(t.total, t.currency) }}</span>
            </div>
            <div class="flex shrink-0 gap-2">
                <button v-if="finance.accounts.length > 1" type="button" class="btn btn-sm" @click="act(null, 'transfer')">Virement</button>
                <button type="button" class="btn btn-primary btn-sm" @click="edit()"><Icon name="plus" :size="16" /> Compte</button>
            </div>
        </div>

        <EmptyState v-if="finance.loaded && !accounts.length" illustration="finance" title="Aucun compte pour l’instant">
            <p>Ajoute chaque endroit où tu gardes de l’argent : banque, mobile money, espèces, épargne, tontine…</p>
            <template #actions><button type="button" class="btn btn-primary" @click="edit()">Ajouter un compte</button></template>
        </EmptyState>

        <TransitionGroup name="list" tag="ul" class="relative space-y-3">
            <li v-for="(a, i) in accounts" :key="a.id" class="card rise space-y-3" :style="{ '--i': Math.min(i, 8) }">
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-slate-100 text-xl dark:bg-slate-800" aria-hidden="true">{{ ACCOUNT_TYPES[a.type]?.emoji }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold break-words">{{ a.name }}</div>
                        <div class="muted">{{ [ACCOUNT_TYPES[a.type]?.label, a.institution, a.location].filter(Boolean).join(' · ') }}</div>
                        <span v-if="a.source" class="tag mt-1">Source : {{ a.source }}</span>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-lg font-bold" :class="a.balance < 0 ? 'text-rose-600 dark:text-rose-400' : ''">{{ fmtMoney(a.balance, a.currency) }}</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm" @click="act(a, 'move')">Mouvement</button>
                    <button type="button" class="btn btn-sm" @click="act(a, 'history')">Historique</button>
                    <button type="button" class="btn btn-sm" @click="edit(a)">Modifier</button>
                    <button type="button" class="btn btn-danger btn-sm" @click="archive(a)">Archiver</button>
                </div>
            </li>
        </TransitionGroup>

        <AccountForm v-model="formOpen" :account="editing" />
        <MovementForm v-model="moveOpen" :account="selected" />
        <MovementsSheet v-model="historyOpen" :account="selected && finance.accounts.find((x) => x.id === selected.id)" />
        <TransferForm v-model="transferOpen" :from="selected" />
    </div>
</template>
