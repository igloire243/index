<script setup>
import { computed, reactive, ref, watch } from 'vue';
import BarChart from '../../components/BarChart.vue';
import Icon from '../../components/Icon.vue';
import LineChart from '../../components/LineChart.vue';
import Meter from '../../components/Meter.vue';
import { addDays, fmtFull, fmtShort, isoDate, parseISO } from '../../lib/dates';
import { GOALS, adherence, recentAverages, weightStats } from '../../lib/health';
import { health, removeMeasurement, saveGoal, saveMeasurement } from '../../stores/health';
import { toast } from '../../stores/session';

const today = isoDate();
const fmt1 = (n) => n.toLocaleString('fr-FR', { maximumFractionDigits: 1, minimumFractionDigits: 1 });
const signed = (n) => (n > 0 ? '+' : n < 0 ? '−' : '') + fmt1(Math.abs(n));

/* Objectif */
const goal = reactive({ goal: 'maintain', target_weight_kg: '', target_date: '' });
watch(
    () => health.goal,
    (g) => {
        goal.goal = g?.goal ?? 'maintain';
        goal.target_weight_kg = g?.target_weight_kg ?? '';
        goal.target_date = g?.target_date ?? '';
    },
    { immediate: true },
);

async function submitGoal() {
    try {
        await saveGoal({
            goal: goal.goal,
            target_weight_kg: goal.target_weight_kg === '' ? null : Number(goal.target_weight_kg),
            target_date: goal.target_date || null,
        });
        toast('Objectif enregistré.');
    } catch (e) {
        toast(e.message, 'error');
    }
}

/* Nouvelle mesure */
const m = reactive({ date: today, weight_kg: '', waist_cm: '', sleep_hours: '', energy: null, mood: null, notes: '' });
async function submitMeasurement() {
    const num = (v) => (v === '' || v == null ? null : Number(v));
    try {
        await saveMeasurement(m.date, {
            weight_kg: num(m.weight_kg),
            waist_cm: num(m.waist_cm),
            sleep_hours: num(m.sleep_hours),
            energy: m.energy,
            mood: m.mood,
            notes: m.notes || null,
        });
        Object.assign(m, { weight_kg: '', waist_cm: '', sleep_hours: '', energy: null, mood: null, notes: '' });
        toast('Mesure enregistrée.');
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function remove(date) {
    if (!confirm(`Supprimer la mesure du ${fmtFull(date)} ?`)) return;
    try {
        await removeMeasurement(date);
    } catch (e) {
        toast(e.message, 'error');
    }
}

/* Calculs */
const stats = computed(() => weightStats(health.measurements, health.goal));
const weightPoints = computed(() => health.measurements.filter((x) => x.weight_kg != null).map((x) => ({ date: x.date, value: x.weight_kg })));
const averages = computed(() => recentAverages(health.measurements, today));
const history = computed(() => [...health.measurements].reverse().slice(0, 10));

const week = computed(() => adherence(health.habits, health.logs, addDays(today, -6), today));
const month = computed(() => adherence(health.habits, health.logs, addDays(today, -29), today));
const bars = computed(() =>
    adherence(health.habits, health.logs, addDays(today, -13), today).days.map((d) => ({
        label: String(parseISO(d.date).getDate()),
        ratio: d.due ? d.done / d.due : null,
        title: `${fmtShort(d.date)} : ${d.done}/${d.due}`,
    })),
);
const pct = (r) => (r == null ? '–' : Math.round(r * 100) + ' %');
</script>

<template>
    <div class="space-y-4">
        <section class="card space-y-3" aria-label="Poids">
            <h2 class="font-semibold">Poids</h2>
            <template v-if="stats">
                <LineChart :points="weightPoints" :target="health.goal?.target_weight_kg ?? null" unit="kg" label="Évolution du poids" />
                <dl class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800">
                        <dt class="muted text-xs">Départ</dt>
                        <dd class="font-semibold">{{ fmt1(stats.first.weight_kg) }} kg</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800">
                        <dt class="muted text-xs">Actuel</dt>
                        <dd class="font-semibold">{{ fmt1(stats.last.weight_kg) }} kg</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800">
                        <dt class="muted text-xs">Évolution</dt>
                        <dd class="font-semibold">{{ signed(stats.change) }} kg</dd>
                    </div>
                </dl>
                <Meter v-if="stats.progress != null" label="Objectif atteint" :value="`${Math.round(stats.progress * 100)} %`" :ratio="stats.progress" tone="emerald" />
                <p v-if="stats.remaining != null" class="muted">
                    {{ Math.abs(stats.remaining) < 0.05 ? 'Objectif atteint !' : `Il reste ${fmt1(Math.abs(stats.remaining))} kg ${stats.remaining > 0 ? 'à prendre' : 'à perdre'}.` }}
                </p>
                <p v-if="stats.weeklyRate != null" class="muted">Rythme récent : {{ signed(stats.weeklyRate) }} kg par semaine.</p>
                <p v-if="stats.tooFast" class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200" role="alert">
                    Ce rythme est rapide (plus de 1 kg par semaine). Parles-en à un professionnel de santé pour t’assurer que tout va bien.
                </p>
            </template>
            <p v-else class="muted">Ajoute ta première pesée ci-dessous pour voir ta courbe.</p>
        </section>

        <section class="card space-y-3" aria-label="Adhérence">
            <h2 class="font-semibold">Régularité</h2>
            <BarChart :bars="bars" label="Habitudes réalisées sur les 14 derniers jours" />
            <div class="grid grid-cols-2 gap-3">
                <Meter label="7 derniers jours" :value="pct(week.ratio)" :ratio="week.ratio ?? 0" tone="emerald" />
                <Meter label="30 derniers jours" :value="pct(month.ratio)" :ratio="month.ratio ?? 0" tone="emerald" />
            </div>
            <p v-if="averages.sleep != null || averages.energy != null || averages.mood != null" class="muted">
                Moyennes sur 7 jours :
                <template v-if="averages.sleep != null">sommeil {{ fmt1(averages.sleep) }} h</template
                ><template v-if="averages.energy != null"> · énergie {{ fmt1(averages.energy) }}/5</template
                ><template v-if="averages.mood != null"> · humeur {{ fmt1(averages.mood) }}/5</template>.
            </p>
        </section>

        <section class="card" aria-label="Objectif">
            <h2 class="mb-3 font-semibold">Mon objectif</h2>
            <form class="space-y-3" @submit.prevent="submitGoal">
                <div>
                    <label class="label" for="g-goal">Je veux</label>
                    <select id="g-goal" v-model="goal.goal" class="input">
                        <option v-for="(label, key) in GOALS" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="g-weight">Poids visé (kg)</label>
                        <input id="g-weight" v-model="goal.target_weight_kg" class="input" type="number" inputmode="decimal" step="0.1" min="20" max="400" />
                    </div>
                    <div>
                        <label class="label" for="g-date">Pour le</label>
                        <input id="g-date" v-model="goal.target_date" class="input" type="date" />
                    </div>
                </div>
                <button class="btn btn-primary w-full">Enregistrer l’objectif</button>
            </form>
        </section>

        <section class="card" aria-label="Nouvelle mesure">
            <h2 class="mb-3 font-semibold">Ajouter une mesure</h2>
            <form class="space-y-3" @submit.prevent="submitMeasurement">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="m-date">Date</label>
                        <input id="m-date" v-model="m.date" class="input" type="date" :max="today" required />
                    </div>
                    <div>
                        <label class="label" for="m-weight">Poids (kg)</label>
                        <input id="m-weight" v-model="m.weight_kg" class="input" type="number" inputmode="decimal" step="0.1" min="20" max="400" />
                    </div>
                    <div>
                        <label class="label" for="m-waist">Tour de taille (cm)</label>
                        <input id="m-waist" v-model="m.waist_cm" class="input" type="number" inputmode="decimal" step="0.5" min="30" max="250" />
                    </div>
                    <div>
                        <label class="label" for="m-sleep">Sommeil (h)</label>
                        <input id="m-sleep" v-model="m.sleep_hours" class="input" type="number" inputmode="decimal" step="0.5" min="0" max="24" />
                    </div>
                </div>
                <fieldset v-for="f in [{ key: 'energy', label: 'Énergie (1 à 5)' }, { key: 'mood', label: 'Humeur (1 à 5)' }]" :key="f.key">
                    <legend class="label">{{ f.label }}</legend>
                    <div class="flex gap-2" role="radiogroup" :aria-label="f.label">
                        <button
                            v-for="n in 5"
                            :key="n"
                            type="button"
                            role="radio"
                            :aria-checked="m[f.key] === n"
                            class="btn flex-1 !px-0"
                            :class="{ 'btn-primary': m[f.key] === n }"
                            @click="m[f.key] = m[f.key] === n ? null : n"
                        >
                            {{ n }}
                        </button>
                    </div>
                </fieldset>
                <div>
                    <label class="label" for="m-notes">Notes</label>
                    <input id="m-notes" v-model="m.notes" class="input" maxlength="500" placeholder="Comment tu te sens, ce qui a changé…" />
                </div>
                <button class="btn btn-primary w-full">Enregistrer la mesure</button>
            </form>
        </section>

        <section v-if="history.length" aria-label="Historique">
            <h2 class="mb-2 font-semibold">Dernières mesures</h2>
            <ul class="space-y-2">
                <li v-for="x in history" :key="x.date" class="card flex items-center gap-3 !p-3">
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-medium">{{ fmtFull(x.date) }}</div>
                        <div class="muted">
                            <template v-if="x.weight_kg != null">{{ fmt1(x.weight_kg) }} kg</template>
                            <template v-if="x.waist_cm != null"> · taille {{ fmt1(x.waist_cm) }} cm</template>
                            <template v-if="x.sleep_hours != null"> · sommeil {{ fmt1(x.sleep_hours) }} h</template>
                            <template v-if="x.energy != null"> · énergie {{ x.energy }}/5</template>
                            <template v-if="x.mood != null"> · humeur {{ x.mood }}/5</template>
                        </div>
                        <div v-if="x.notes" class="muted">{{ x.notes }}</div>
                    </div>
                    <button type="button" class="btn btn-danger size-10 !p-0" :aria-label="`Supprimer la mesure du ${fmtFull(x.date)}`" @click="remove(x.date)">
                        <Icon name="trash" :size="18" />
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
