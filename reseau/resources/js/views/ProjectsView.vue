<script setup>
import { computed, onMounted, ref } from 'vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import ProjectForm from '../components/ProjectForm.vue';
import { filterProjects, progress, progressRatio, STATUSES } from '../lib/projects';
import { loadContacts } from '../stores/contacts';
import { loadProjects, projects } from '../stores/projects';
import { toast } from '../stores/session';

const query = ref('');
const status = ref('');
const domain = ref('');
const formOpen = ref(false);
const editing = ref(null);

onMounted(() => {
    loadProjects().catch((e) => toast(e.message, 'error'));
    loadContacts().catch(() => {});
});

const shown = computed(() => filterProjects(projects.list, { query: query.value, status: status.value, domain: domain.value }));
const presentStatuses = computed(() => Object.keys(STATUSES).filter((s) => projects.list.some((p) => p.status === s)));
const presentDomains = computed(() => [...new Set(projects.list.map((p) => p.domain).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'fr')));

const edit = (p = null) => {
    editing.value = p;
    formOpen.value = true;
};

const fmtDate = (d) => (d ? new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) : null);
const overdue = (p) => p.target_date && p.status !== 'done' && p.status !== 'abandoned' && p.target_date < new Date().toISOString().slice(0, 10);
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <input v-model="query" class="input" type="search" placeholder="Rechercher un projet…" aria-label="Rechercher un projet" />
            <button type="button" class="btn btn-primary shrink-0" @click="edit()"><Icon name="plus" :size="18" /> Projet</button>
        </div>

        <div v-if="presentStatuses.length > 1" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Filtrer par statut">
            <button type="button" class="chip" :class="{ 'chip-on': !status }" @click="status = ''">Tout</button>
            <button v-for="s in presentStatuses" :key="s" type="button" class="chip" :class="{ 'chip-on': status === s }" @click="status = status === s ? '' : s">{{ STATUSES[s].label }}</button>
        </div>

        <div v-if="presentDomains.length > 1" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Filtrer par domaine">
            <button type="button" class="chip" :class="{ 'chip-on': !domain }" @click="domain = ''">Tous domaines</button>
            <button v-for="d in presentDomains" :key="d" type="button" class="chip" :class="{ 'chip-on': domain === d }" @click="domain = domain === d ? '' : d">{{ d }}</button>
        </div>

        <EmptyState v-if="projects.loaded && !shown.length" illustration="coach" :title="projects.list.length ? 'Aucun résultat' : 'Aucun projet pour l’instant'">
            <p v-if="!projects.list.length">Note une idée, même vague : tu pourras la détailler et avancer pas à pas plus tard.</p>
            <template v-if="!projects.list.length" #actions><button type="button" class="btn btn-primary" @click="edit()">Ajouter un projet</button></template>
        </EmptyState>

        <TransitionGroup name="list" tag="ul" class="relative space-y-3">
            <li v-for="(p, i) in shown" :key="p.id" class="rise" :style="{ '--i': Math.min(i, 8) }">
                <RouterLink :to="`/coach/projets/${p.id}`" class="card flex items-start gap-3 transition-transform active:scale-[.99]">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300"><Icon :name="STATUSES[p.status].icon" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="min-w-0 truncate font-semibold">{{ p.title }}</span>
                            <span v-if="p.status !== 'idea'" class="tag shrink-0">{{ STATUSES[p.status].label }}</span>
                            <span v-if="p.domain" class="tag shrink-0">{{ p.domain }}</span>
                        </span>
                        <span v-if="progress(p)" class="muted block">{{ progress(p).done }}/{{ progress(p).total }} étape{{ progress(p).total > 1 ? 's' : '' }}</span>
                        <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" v-if="progress(p)">
                            <span class="block h-full rounded-full bg-indigo-500 transition-[width]" :style="{ width: progressRatio(p) * 100 + '%' }" />
                        </span>
                        <span v-if="p.target_date" class="muted mt-0.5 flex items-center gap-1 text-xs" :class="{ 'text-amber-700 dark:text-amber-400': overdue(p) }">
                            <Icon name="calendar" :size="12" /> {{ overdue(p) ? 'Échéance dépassée' : 'Pour le' }} {{ fmtDate(p.target_date) }}
                        </span>
                    </span>
                </RouterLink>
            </li>
        </TransitionGroup>

        <ProjectForm v-model="formOpen" :project="editing" @saved="(p) => (editing = null)" />
    </div>
</template>
