<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AnimatedNumber from '../../components/AnimatedNumber.vue';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import RatesForm from '../../components/finance/RatesForm.vue';
import { isoDate } from '../../lib/dates';
import { assetGaps, dueLabel, dueSoon, fmtMoney, netWorth, totalsByCurrency } from '../../lib/finance';
import { finance } from '../../stores/finance';

const today = isoDate();
const ratesOpen = ref(false);

const base = computed(() => finance.settings.base_currency);
const worth = computed(() => netWorth(finance, finance.settings));
const isEmpty = computed(() => finance.loaded && !finance.accounts.length && !finance.debts.length && !finance.assets.length);
const parts = computed(() => [
    { label: 'Fonds', value: worth.value.funds, color: 'bg-indigo-500', to: '/finances/fonds' },
    { label: 'On me doit', value: worth.value.receivables, color: 'bg-sky-500', to: '/finances/creances' },
    { label: 'Biens', value: worth.value.assets, color: 'bg-emerald-500', to: '/finances/biens' },
    { label: 'Je dois', value: -worth.value.debts, color: 'bg-rose-500', to: '/finances/dettes' },
]);
const positive = computed(() => parts.value.filter((p) => p.value > 0).reduce((s, p) => s + p.value, 0) || 1);
const perCurrency = computed(() => totalsByCurrency(finance.accounts.map((a) => ({ amount: a.balance, currency: a.currency }))));
const due = computed(() => dueSoon(finance.debts, today).slice(0, 5));
const missing = computed(() => assetGaps(finance.assets).filter((g) => g.state === 'missing'));
</script>

<template>
    <div class="space-y-4">
        <EmptyState v-if="isEmpty" illustration="finance" title="Commence ton patrimoine">
            <p>Ajoute un compte (banque, mobile money, espèces), ce qu’on te doit, ce que tu dois, et tes biens.</p>
            <template #actions>
                <RouterLink to="/finances/fonds" class="btn btn-primary">Ajouter un compte</RouterLink>
            </template>
        </EmptyState>

        <template v-else-if="finance.loaded">
            <section class="card rise space-y-3" aria-label="Patrimoine net">
                <div>
                    <h2 class="muted">Patrimoine net</h2>
                    <div class="text-3xl font-bold tracking-tight" :class="worth.net < 0 ? 'text-rose-600 dark:text-rose-400' : ''">
                        <AnimatedNumber :value="worth.net" :decimals="0" /> <span class="text-lg font-semibold">{{ base }}</span>
                    </div>
                    <p class="muted">Fonds + créances + biens − dettes, converti en {{ base }}.</p>
                </div>

                <div class="flex h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="img" aria-label="Répartition du patrimoine">
                    <div v-for="p in parts.filter((x) => x.value > 0)" :key="p.label" class="h-full transition-[width] duration-700" :class="p.color" :style="{ width: (p.value / positive) * 100 + '%' }" />
                </div>
                <dl class="grid grid-cols-2 gap-2">
                    <RouterLink v-for="p in parts" :key="p.label" :to="p.to" class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800">
                        <dt class="muted flex items-center gap-1.5 text-xs"><span class="size-2 rounded-full" :class="p.color" />{{ p.label }}</dt>
                        <dd class="font-semibold">{{ fmtMoney(Math.abs(p.value), base) }}</dd>
                    </RouterLink>
                </dl>

                <div v-if="worth.missing.length" class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200" role="status">
                    Il manque un taux de change pour <b>{{ worth.missing.join(', ') }}</b> : ces montants ne sont pas comptés.
                    <button type="button" class="ml-1 underline" @click="ratesOpen = true">Définir les taux</button>
                </div>
                <button v-else type="button" class="text-sm text-indigo-600 underline dark:text-indigo-400" @click="ratesOpen = true">Taux de change</button>
            </section>

            <section v-if="perCurrency.length" class="card rise space-y-2" style="--i: 1" aria-label="Fonds par devise">
                <h2 class="font-semibold">Fonds par devise</h2>
                <ul class="space-y-1.5">
                    <li v-for="c in perCurrency" :key="c.currency" class="flex items-center justify-between text-sm">
                        <span class="muted">{{ c.currency }}</span>
                        <span class="font-semibold">{{ fmtMoney(c.total, c.currency) }}</span>
                    </li>
                </ul>
            </section>

            <section v-if="due.length" class="card rise space-y-2" style="--i: 2" aria-label="Échéances">
                <h2 class="font-semibold">Échéances</h2>
                <ul class="space-y-2">
                    <li v-for="d in due" :key="d.id">
                        <RouterLink :to="d.direction === 'owed_to_me' ? '/finances/creances' : '/finances/dettes'" class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full text-white" :class="d.direction === 'owed_to_me' ? 'bg-sky-500' : 'bg-rose-500'">
                                <Icon :name="d.direction === 'owed_to_me' ? 'left' : 'right'" :size="18" />
                            </span>
                            <span class="min-w-0 flex-1 text-sm">
                                <span class="block truncate font-medium">{{ d.counterparty }}</span>
                                <span class="muted block">{{ d.direction === 'owed_to_me' ? 'te doit' : 'à rembourser' }} {{ fmtMoney(d.remaining, d.currency) }}</span>
                            </span>
                            <span class="shrink-0 text-xs font-semibold" :class="d.days < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'">{{ dueLabel(d.days) }}</span>
                        </RouterLink>
                    </li>
                </ul>
            </section>

            <section class="card rise space-y-2" style="--i: 3" aria-label="Types de biens manquants">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Types de biens que tu n’as pas encore</h2>
                    <RouterLink to="/finances/biens" class="text-sm text-indigo-600 underline dark:text-indigo-400">Voir</RouterLink>
                </div>
                <p v-if="!missing.length" class="muted">Tu possèdes déjà au moins un bien de chaque type, ou tu l’as en projet.</p>
                <div v-else class="flex flex-wrap gap-2">
                    <RouterLink v-for="g in missing.slice(0, 8)" :key="g.key" to="/finances/biens" class="chip"><span aria-hidden="true">{{ g.emoji }}</span> {{ g.label }}</RouterLink>
                    <span v-if="missing.length > 8" class="muted self-center">+ {{ missing.length - 8 }}</span>
                </div>
            </section>
        </template>

        <RatesForm v-model="ratesOpen" />
    </div>
</template>
