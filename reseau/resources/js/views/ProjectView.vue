<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import ProjectForm from '../components/ProjectForm.vue';
import { progress, progressRatio, STATUSES } from '../lib/projects';
import { contacts } from '../stores/contacts';
import { addStep, loadProject, removeProject, removeStep, reorderSteps, saveProject, toggleStep } from '../stores/projects';
import { toast } from '../stores/session';

const route = useRoute();
const router = useRouter();
const project = ref(null);
const loading = ref(true);
const notFound = ref(false);
const formOpen = ref(false);
const newStep = ref('');
const addingStep = ref(false);

const id = computed(() => Number(route.params.id));
const contactName = computed(() => contacts.list.find((c) => c.id === project.value?.contact_id)?.name);

async function load() {
    loading.value = true;
    try {
        project.value = await loadProject(id.value);
        notFound.value = false;
    } catch (e) {
        if (e.status === 404) notFound.value = true;
        else toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}
onMounted(load);
watch(id, load);

async function addNewStep() {
    const title = newStep.value.trim();
    if (!title) return;
    addingStep.value = true;
    try {
        project.value = await addStep(id.value, title);
        newStep.value = '';
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        addingStep.value = false;
    }
}

async function toggle(step) {
    const before = project.value;
    try {
        project.value = await toggleStep(step.id, !step.done);
    } catch (e) {
        project.value = before;
        toast(e.message, 'error');
    }
}

async function deleteStep(step) {
    try {
        project.value = await removeStep(step.id);
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function move(index, dir) {
    const steps = [...project.value.steps];
    const j = index + dir;
    if (j < 0 || j >= steps.length) return;
    [steps[index], steps[j]] = [steps[j], steps[index]];
    try {
        project.value = await reorderSteps(id.value, steps.map((s) => s.id));
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function setStatus(status) {
    try {
        project.value = await saveProject({ status }, id.value);
        toast(status === 'done' ? 'Projet marqué terminé, bravo !' : 'Statut mis à jour.');
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function removeThis() {
    if (!confirm(`Supprimer le projet « ${project.value.title} » ?`)) return;
    try {
        await removeProject(id.value);
        toast('Projet supprimé.');
        router.replace('/coach/projets');
    } catch (e) {
        toast(e.message, 'error');
    }
}

const allDone = computed(() => progress(project.value ?? {}) && progress(project.value).done === progress(project.value).total);
</script>

<template>
    <div class="space-y-4">
        <RouterLink to="/coach/projets" class="muted inline-flex items-center gap-1"><Icon name="left" :size="16" /> Tous les projets</RouterLink>

        <EmptyState v-if="notFound" illustration="coach" title="Projet introuvable">
            <template #actions><RouterLink to="/coach/projets" class="btn btn-primary">Retour aux projets</RouterLink></template>
        </EmptyState>

        <template v-else-if="project">
            <section class="card space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold break-words">{{ project.title }}</h2>
                            <span class="tag">{{ STATUSES[project.status].label }}</span>
                        </div>
                        <p v-if="contactName" class="muted">Peut aider : {{ contactName }}</p>
                        <p v-if="project.target_date" class="muted">Échéance : {{ new Date(project.target_date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) }}</p>
                    </div>
                    <span class="flex shrink-0 gap-2">
                        <button type="button" class="btn btn-sm" @click="formOpen = true">Modifier</button>
                        <button type="button" class="btn btn-danger btn-sm" @click="removeThis">Supprimer</button>
                    </span>
                </div>
                <p v-if="project.description" class="selectable text-sm whitespace-pre-wrap">{{ project.description }}</p>

                <div v-if="progress(project)" class="space-y-1">
                    <div class="flex justify-between text-sm"><span>Progression</span><span class="muted">{{ progress(project).done }}/{{ progress(project).total }}</span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-indigo-500 transition-[width] duration-500" :style="{ width: progressRatio(project) * 100 + '%' }" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <button v-if="project.status === 'idea'" type="button" class="btn btn-sm" @click="setStatus('in_progress')"><Icon name="target" :size="14" /> Je m’y mets</button>
                    <button v-if="project.status !== 'done' && project.status !== 'abandoned'" type="button" class="btn btn-sm" @click="setStatus('done')"><Icon name="check" :size="14" /> Marquer terminé</button>
                    <button v-if="project.status !== 'abandoned' && project.status !== 'done'" type="button" class="btn btn-sm" @click="setStatus('abandoned')">Abandonner</button>
                    <button v-if="project.status === 'done' || project.status === 'abandoned'" type="button" class="btn btn-sm" @click="setStatus('in_progress')">Reprendre</button>
                </div>
                <p v-if="allDone && project.status !== 'done'" class="text-sm text-emerald-700 dark:text-emerald-400">Toutes les étapes sont faites — il ne reste qu’à le marquer terminé !</p>
            </section>

            <section class="space-y-2">
                <h3 class="font-semibold">Étapes</h3>
                <form class="flex gap-2" @submit.prevent="addNewStep">
                    <input v-model="newStep" class="input" placeholder="Ajouter une étape…" aria-label="Nouvelle étape" maxlength="200" />
                    <button type="submit" class="btn btn-primary shrink-0" :disabled="!newStep.trim() || addingStep"><Icon name="plus" :size="18" /></button>
                </form>

                <EmptyState v-if="!project.steps.length" illustration="coach" small title="Aucune étape">
                    <p>Découpe ton projet en petites étapes : plus facile à avancer, plus satisfaisant à cocher.</p>
                </EmptyState>

                <ul v-else class="space-y-2">
                    <li v-for="(s, i) in project.steps" :key="s.id" class="card flex items-center gap-2 py-2.5">
                        <button type="button" class="grid size-7 shrink-0 place-items-center rounded-lg border-2" :class="s.done ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 dark:border-slate-600'" :aria-pressed="s.done" :aria-label="s.done ? 'Marquer non faite' : 'Marquer faite'" @click="toggle(s)">
                            <Icon v-if="s.done" name="check" :size="14" />
                        </button>
                        <span class="min-w-0 flex-1 text-sm" :class="{ 'text-slate-400 line-through dark:text-slate-500': s.done }">{{ s.title }}</span>
                        <span class="flex shrink-0 gap-0.5">
                            <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 disabled:opacity-30" :disabled="i === 0" aria-label="Monter" @click="move(i, -1)"><Icon name="left" :size="14" class="rotate-90" /></button>
                            <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 disabled:opacity-30" :disabled="i === project.steps.length - 1" aria-label="Descendre" @click="move(i, 1)"><Icon name="right" :size="14" class="rotate-90" /></button>
                            <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400" aria-label="Supprimer l’étape" @click="deleteStep(s)"><Icon name="x" :size="14" /></button>
                        </span>
                    </li>
                </ul>
            </section>

            <ProjectForm v-model="formOpen" :project="project" @saved="(p) => (project = { ...p, steps: project.steps })" />
        </template>

        <div v-else-if="loading" class="muted py-10 text-center">Chargement…</div>
    </div>
</template>
