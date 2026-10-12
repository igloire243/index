<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import Icon from '../components/Icon.vue';
import Meter from '../components/Meter.vue';
import { contactMeters } from '../lib/coach';
import { addDays, isoDate } from '../lib/dates';
import { fmtMoney, netWorth } from '../lib/finance';
import { adherence, weightStats } from '../lib/health';
import { projectStats } from '../lib/projects';
import { contacts, loadContacts } from '../stores/contacts';
import { finance, loadFinance } from '../stores/finance';
import { health, loadHealth } from '../stores/health';
import { projects, loadProjects } from '../stores/projects';
import { toast } from '../stores/session';

const today = isoDate();

onMounted(() => {
    Promise.all([loadContacts(), loadHealth(), loadFinance(), loadProjects()]).catch((e) => toast(e.message, 'error'));
});

const meters = computed(() => contactMeters(contacts.list));
const weekAdherence = computed(() => adherence(health.habits, health.logs, addDays(today, -6), today));
const weight = computed(() => weightStats(health.measurements, health.goal));
const worth = computed(() => netWorth(finance, finance.settings));
const pStats = computed(() => projectStats(projects.list, today));

const ready = computed(() => contacts.loaded && health.loaded && finance.loaded && projects.loaded);
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-xl font-bold">Comment ça va ?</h1>
        <p class="muted">Un coup d’œil sur chaque facette de ta vie, à partir de ce que tu as déjà noté.</p>

        <div v-if="!ready" class="muted py-10 text-center">Chargement…</div>

        <template v-else>
            <RouterLink to="/contacts" class="card block space-y-2">
                <h2 class="flex items-center gap-1.5 font-semibold"><Icon name="users" :size="18" /> Réseau</h2>
                <Meter v-for="m in meters" :key="m.label" :label="m.label" :value="m.value" :ratio="m.ratio" />
            </RouterLink>

            <RouterLink to="/sante" class="card block space-y-2">
                <h2 class="flex items-center gap-1.5 font-semibold"><Icon name="heart" :size="18" /> Santé</h2>
                <Meter label="Assiduité (7 derniers jours)" :value="weekAdherence.due ? `${weekAdherence.done} / ${weekAdherence.due}` : 'Aucune habitude prévue'" :ratio="weekAdherence.ratio ?? 0" />
                <p v-if="weight" class="muted text-sm">
                    Poids : {{ weight.last.weight_kg }} kg
                    <span v-if="weight.change">({{ weight.change > 0 ? '+' : '' }}{{ weight.change.toFixed(1) }} kg depuis le premier relevé)</span>
                </p>
                <p v-else class="muted text-sm">Aucun relevé de poids encore.</p>
            </RouterLink>

            <RouterLink to="/finances" class="card block space-y-2">
                <h2 class="flex items-center gap-1.5 font-semibold"><Icon name="wallet" :size="18" /> Finances</h2>
                <p class="text-2xl font-bold tracking-tight" :class="worth.net < 0 ? 'text-rose-600 dark:text-rose-400' : ''">
                    {{ fmtMoney(worth.net, finance.settings.base_currency) }}
                </p>
                <p class="muted text-sm">Patrimoine net (fonds + créances + biens − dettes)</p>
            </RouterLink>

            <RouterLink to="/coach/projets" class="card block space-y-2">
                <h2 class="flex items-center gap-1.5 font-semibold"><Icon name="target" :size="18" /> Projets</h2>
                <p class="text-sm">
                    <b>{{ pStats.active }}</b> en cours · <b>{{ pStats.doneThisMonth }}</b> terminé{{ pStats.doneThisMonth > 1 ? 's' : '' }} ce mois-ci · {{ pStats.total }} au total
                </p>
            </RouterLink>
        </template>
    </div>
</template>
