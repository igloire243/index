<script setup>
import { computed, ref } from 'vue';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import AssetForm from '../../components/finance/AssetForm.vue';
import { assetGaps, categoryOf, fmtMoney, ownedAssets, sumBase } from '../../lib/finance';
import { finance, removeAsset } from '../../stores/finance';
import { toast } from '../../stores/session';

const formOpen = ref(false);
const editing = ref(null);
const preset = ref({ category: null, status: 'owned' });

const gaps = computed(() => assetGaps(finance.assets));
const owned = computed(() => ownedAssets(finance.assets));
const wanted = computed(() => finance.assets.filter((a) => a.status === 'wanted'));
const missingCount = computed(() => gaps.value.filter((g) => g.state === 'missing').length);
const total = computed(() => sumBase(owned.value.filter((a) => a.value != null).map((a) => ({ amount: a.value, currency: a.currency })), finance.settings));

const add = (category = null, status = 'owned') => {
    editing.value = null;
    preset.value = { category, status };
    formOpen.value = true;
};
const edit = (a) => {
    editing.value = a;
    formOpen.value = true;
};

async function remove(a) {
    if (!confirm(`Supprimer « ${a.name} » ?`)) return;
    try {
        await removeAsset(a.id);
        toast('Bien supprimé.');
    } catch (e) {
        toast(e.message, 'error');
    }
}

const stateText = (g) => (g.state === 'owned' ? `${g.owned.length} bien${g.owned.length > 1 ? 's' : ''}` : g.state === 'wanted' ? 'En projet' : 'Pas encore');
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <div>
                <div class="muted">Valeur de mes biens</div>
                <div class="text-lg font-bold">{{ fmtMoney(total.total, finance.settings.base_currency) }}</div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" @click="add()"><Icon name="plus" :size="16" /> Bien</button>
        </div>
        <p v-if="total.missing.length" class="muted">Taux manquant pour {{ total.missing.join(', ') }} : ces biens ne sont pas comptés dans le total.</p>

        <section aria-label="Types de biens">
            <h2 class="mb-1 font-semibold">Mes types de biens</h2>
            <p class="muted mb-2">{{ missingCount ? `${missingCount} type${missingCount > 1 ? 's' : ''} de biens que tu n’as pas encore. Touche-en un pour l’ajouter ou le planifier.` : 'Tu as couvert tous les types de biens.' }}</p>
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="(g, i) in gaps"
                    :key="g.key"
                    type="button"
                    class="rise rounded-2xl border p-3 text-left transition-colors active:scale-[.98]"
                    :class="{
                        'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40': g.state === 'owned',
                        'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40': g.state === 'wanted',
                        'border-dashed border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-900': g.state === 'missing',
                    }"
                    :style="{ '--i': Math.min(i, 12) }"
                    :aria-label="`${g.label} : ${stateText(g)}`"
                    @click="add(g.key, g.state === 'owned' ? 'owned' : 'wanted')"
                >
                    <span class="text-xl" aria-hidden="true">{{ g.emoji }}</span>
                    <span class="mt-1 block text-sm leading-tight font-medium">{{ g.label }}</span>
                    <span class="muted mt-1 block text-xs" :class="{ 'font-semibold text-emerald-700 dark:text-emerald-400': g.state === 'owned' }">{{ stateText(g) }}</span>
                </button>
            </div>
        </section>

        <EmptyState v-if="finance.loaded && !finance.assets.length" illustration="assets" title="Aucun bien enregistré" small>
            <p>Recense ce que tu possèdes, et note ce que tu veux acquérir.</p>
        </EmptyState>

        <section v-if="owned.length" aria-label="Mes biens">
            <h2 class="mb-2 font-semibold">Mes biens</h2>
            <TransitionGroup name="list" tag="ul" class="relative space-y-2">
                <li v-for="a in owned" :key="a.id" class="card flex items-center gap-3 !p-3">
                    <span class="text-2xl" aria-hidden="true">{{ categoryOf(a.category).emoji }}</span>
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-medium break-words">{{ a.name }}</div>
                        <div class="muted">{{ [categoryOf(a.category).label, a.location].filter(Boolean).join(' · ') }}</div>
                    </div>
                    <div class="shrink-0 text-right text-sm font-semibold">{{ a.value != null ? fmtMoney(a.value, a.currency) : '—' }}</div>
                    <button type="button" class="btn size-9 !p-0" :aria-label="`Modifier ${a.name}`" @click="edit(a)"><Icon name="edit" :size="16" /></button>
                    <button type="button" class="btn btn-danger size-9 !p-0" :aria-label="`Supprimer ${a.name}`" @click="remove(a)"><Icon name="trash" :size="16" /></button>
                </li>
            </TransitionGroup>
        </section>

        <section v-if="wanted.length" aria-label="Mes envies">
            <h2 class="mb-2 font-semibold">Ce que je veux acquérir</h2>
            <TransitionGroup name="list" tag="ul" class="relative space-y-2">
                <li v-for="a in wanted" :key="a.id" class="card flex items-center gap-3 !p-3">
                    <span class="text-2xl" aria-hidden="true">{{ categoryOf(a.category).emoji }}</span>
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-medium break-words">{{ a.name }}</div>
                        <div class="muted">{{ [categoryOf(a.category).label, 'objectif ' + (a.value != null ? fmtMoney(a.value, a.currency) : 'à définir')].join(' · ') }}</div>
                    </div>
                    <button type="button" class="btn size-9 !p-0" :aria-label="`Modifier ${a.name}`" @click="edit(a)"><Icon name="edit" :size="16" /></button>
                    <button type="button" class="btn btn-danger size-9 !p-0" :aria-label="`Supprimer ${a.name}`" @click="remove(a)"><Icon name="trash" :size="16" /></button>
                </li>
            </TransitionGroup>
        </section>

        <AssetForm v-model="formOpen" :asset="editing" :category="preset.category" :status="preset.status" />
    </div>
</template>
