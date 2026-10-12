<script setup>
import { computed, onMounted, ref } from 'vue';
import AchievementForm from '../components/cv/AchievementForm.vue';
import ExperienceForm from '../components/cv/ExperienceForm.vue';
import LanguageForm from '../components/cv/LanguageForm.vue';
import SkillForm from '../components/cv/SkillForm.vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import { experiencePeriod, groupSkills, LANGUAGE_LEVELS } from '../lib/cv';
import { cv, loadCv, removeAchievement, removeExperience, removeLanguage, removeSkill } from '../stores/cv';
import { toast } from '../stores/session';

onMounted(() => loadCv().catch((e) => toast(e.message, 'error')));

const groups = computed(() => groupSkills(cv.skills));
const isEmpty = computed(() => cv.loaded && !cv.skills.length && !cv.languages.length && !cv.experiences.length && !cv.achievements.length);

const skillForm = ref({ open: false, item: null, type: 'competence' });
const addSkill = (type) => (skillForm.value = { open: true, item: null, type });
const editSkill = (item) => (skillForm.value = { open: true, item, type: item.type });
async function deleteSkill(item) {
    if (!confirm(`Supprimer « ${item.name} » ?`)) return;
    try {
        await removeSkill(item.id);
    } catch (e) {
        toast(e.message, 'error');
    }
}

const languageForm = ref({ open: false, item: null });
const addLanguage = () => (languageForm.value = { open: true, item: null });
const editLanguage = (item) => (languageForm.value = { open: true, item });
async function deleteLanguage(item) {
    if (!confirm(`Supprimer « ${item.name} » ?`)) return;
    try {
        await removeLanguage(item.id);
    } catch (e) {
        toast(e.message, 'error');
    }
}

const experienceForm = ref({ open: false, item: null });
const addExperience = () => (experienceForm.value = { open: true, item: null });
const editExperience = (item) => (experienceForm.value = { open: true, item });
async function deleteExperience(item) {
    if (!confirm(`Supprimer « ${item.title} » et ses réalisations liées ?`)) return;
    try {
        await removeExperience(item.id);
    } catch (e) {
        toast(e.message, 'error');
    }
}

const achievementForm = ref({ open: false, item: null, experienceId: null });
const addAchievement = (experienceId = null) => (achievementForm.value = { open: true, item: null, experienceId });
const editAchievement = (item, experienceId = null) => (achievementForm.value = { open: true, item, experienceId });
async function deleteAchievement(item, experienceId = null) {
    if (!confirm(`Supprimer « ${item.title} » ?`)) return;
    try {
        await removeAchievement(item.id, experienceId);
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h1 class="text-xl font-bold">Mon CV</h1>
                <p class="muted text-sm">Compétences, langues, expériences et réalisations.</p>
            </div>
            <a href="/api/cv/export" class="btn btn-sm shrink-0" download><Icon name="download" :size="16" /> PDF</a>
        </div>

        <EmptyState v-if="isEmpty" illustration="coach" title="Ton CV est vide">
            <p>Note tes compétences, les langues que tu parles, et les étapes de ton parcours, même informelles.</p>
            <template #actions><button type="button" class="btn btn-primary" @click="addExperience()">Ajouter une expérience</button></template>
        </EmptyState>

        <template v-else-if="cv.loaded">
            <section class="card space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Langues</h2>
                    <button type="button" class="btn btn-sm" @click="addLanguage()"><Icon name="plus" :size="14" /> Ajouter</button>
                </div>
                <p v-if="!cv.languages.length" class="muted text-sm">Aucune langue notée.</p>
                <ul v-else class="flex flex-wrap gap-2">
                    <li v-for="l in cv.languages" :key="l.id">
                        <button type="button" class="tag" @click="editLanguage(l)">{{ l.name }} · {{ LANGUAGE_LEVELS[l.level] }}</button>
                    </li>
                </ul>
            </section>

            <section v-for="g in groups" :key="g.type" class="card space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">{{ g.label }}s</h2>
                    <button type="button" class="btn btn-sm" @click="addSkill(g.type)"><Icon name="plus" :size="14" /> Ajouter</button>
                </div>
                <p v-if="!g.items.length" class="muted text-sm">Aucune entrée.</p>
                <ul v-else class="flex flex-wrap gap-2">
                    <li v-for="s in g.items" :key="s.id">
                        <button type="button" class="tag" @click="editSkill(s)">{{ s.name }}<template v-if="s.level"> ({{ s.level }}/5)</template></button>
                    </li>
                </ul>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Expériences</h2>
                    <button type="button" class="btn btn-sm" @click="addExperience()"><Icon name="plus" :size="14" /> Ajouter</button>
                </div>
                <p v-if="!cv.experiences.length" class="muted text-sm">Aucune expérience notée.</p>
                <ul v-else class="space-y-2">
                    <li v-for="e in cv.experiences" :key="e.id" class="card space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold break-words">{{ e.title }}<template v-if="e.organization"> — {{ e.organization }}</template></p>
                                <p class="muted text-sm">{{ experiencePeriod(e) }}<template v-if="e.location"> · {{ e.location }}</template></p>
                            </div>
                            <span class="flex shrink-0 gap-1.5">
                                <button type="button" class="btn btn-sm" @click="editExperience(e)">Modifier</button>
                                <button type="button" class="btn btn-danger btn-sm" @click="deleteExperience(e)">Supprimer</button>
                            </span>
                        </div>
                        <p v-if="e.description" class="selectable text-sm whitespace-pre-wrap">{{ e.description }}</p>

                        <ul v-if="e.achievements.length" class="space-y-1 border-t border-slate-100 pt-2 dark:border-slate-800">
                            <li v-for="a in e.achievements" :key="a.id" class="flex items-center justify-between gap-2 text-sm">
                                <button type="button" class="min-w-0 truncate text-left" @click="editAchievement(a, e.id)">
                                    <Icon name="star" :size="12" class="mr-1 inline text-amber-500" />{{ a.title }}
                                </button>
                                <button type="button" class="muted shrink-0" aria-label="Supprimer" @click="deleteAchievement(a, e.id)"><Icon name="x" :size="14" /></button>
                            </li>
                        </ul>
                        <button type="button" class="text-sm text-indigo-600 underline dark:text-indigo-400" @click="addAchievement(e.id)">+ Réalisation liée</button>
                    </li>
                </ul>
            </section>

            <section class="card space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Autres réalisations</h2>
                    <button type="button" class="btn btn-sm" @click="addAchievement()"><Icon name="plus" :size="14" /> Ajouter</button>
                </div>
                <p v-if="!cv.achievements.length" class="muted text-sm">Aucune réalisation sans expérience liée.</p>
                <ul v-else class="space-y-1">
                    <li v-for="a in cv.achievements" :key="a.id" class="flex items-center justify-between gap-2 text-sm">
                        <button type="button" class="min-w-0 truncate text-left" @click="editAchievement(a)">
                            <Icon name="star" :size="12" class="mr-1 inline text-amber-500" />{{ a.title }}
                        </button>
                        <button type="button" class="muted shrink-0" aria-label="Supprimer" @click="deleteAchievement(a)"><Icon name="x" :size="14" /></button>
                    </li>
                </ul>
            </section>
        </template>

        <div v-else class="muted py-10 text-center">Chargement…</div>

        <SkillForm v-model="skillForm.open" :skill="skillForm.item" :type="skillForm.type" />
        <LanguageForm v-model="languageForm.open" :language="languageForm.item" />
        <ExperienceForm v-model="experienceForm.open" :experience="experienceForm.item" />
        <AchievementForm v-model="achievementForm.open" :achievement="achievementForm.item" :experience-id="achievementForm.experienceId" />
    </div>
</template>
