<script setup>
import { computed, ref } from 'vue';
import AnimatedNumber from '../../components/AnimatedNumber.vue';
import BarChart from '../../components/BarChart.vue';
import GoalForm from '../../components/GoalForm.vue';
import Icon from '../../components/Icon.vue';
import LineChart from '../../components/LineChart.vue';
import MeasurementForm from '../../components/MeasurementForm.vue';
import Meter from '../../components/Meter.vue';
import { addDays, fmtFull, fmtShort, isoDate, parseISO } from '../../lib/dates';
import { GOALS, adherence, recentAverages, weightStats } from '../../lib/health';
import { health, removeMeasurement } from '../../stores/health';
import { toast } from '../../stores/session';

const today = isoDate();
const fmt1 = (n) => n.toLocaleString('fr-FR', { maximumFractionDigits: 1, minimumFractionDigits: 1 });
const signed = (n) => (n > 0 ? '+' : n < 0 ? '−' : '') + fmt1(Math.abs(n));

const goalOpen = ref(false);
const measureOpen = ref(false);
const measureDate = ref(today);
const openMeasure = (date = today) => {
    measureDate.value = date;
    measureOpen.value = true;
};

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
        <section class="card rise space-y-3" aria-label="Poids">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Poids</h2>
                <button type="button" class="btn btn-primary btn-sm" @click="openMeasure()"><Icon name="plus" :size="16" /> Ajouter une mesure</button>
            </div>
            <template v-if="stats">
                <LineChart :points="weightPoints" :target="health.goal?.target_weight_kg ?? null" unit="kg" label="Évolution du poids" />
                <dl class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800">
                        <dt class="muted text-xs">Départ</dt>
                        <dd class="font-semibold"><AnimatedNumber :value="stats.first.weight_kg" :decimals="1" /> kg</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800">
                        <dt class="muted text-xs">Actuel</dt>
                        <dd class="font-semibold"><AnimatedNumber :value="stats.last.weight_kg" :decimals="1" /> kg</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800">
                        <dt class="muted text-xs">Évolution</dt>
                        <dd class="font-semibold">{{ stats.change > 0 ? '+' : stats.change < 0 ? '−' : '' }}<AnimatedNumber :value="Math.abs(stats.change)" :decimals="1" /> kg</dd>
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
            <p v-else class="muted">Ajoute ta première pesée pour voir ta courbe.</p>
        </section>

        <section class="card rise space-y-3" style="--i: 1" aria-label="Adhérence">
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

        <section class="card rise space-y-2" style="--i: 2" aria-label="Objectif">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Mon objectif</h2>
                <button type="button" class="btn btn-sm" @click="goalOpen = true">{{ health.goal ? 'Modifier' : 'Définir' }}</button>
            </div>
            <p v-if="health.goal" class="text-sm">
                {{ GOALS[health.goal.goal] }}<template v-if="health.goal.target_weight_kg"> · objectif {{ fmt1(health.goal.target_weight_kg) }} kg</template
                ><template v-if="health.goal.target_date"> avant le {{ fmtFull(health.goal.target_date) }}</template>
            </p>
            <p v-else class="muted">Définis un objectif pour suivre ta progression.</p>
        </section>

        <section v-if="history.length" aria-label="Historique">
            <h2 class="mb-2 font-semibold">Dernières mesures</h2>
            <ul class="space-y-2">
                <li v-for="(x, i) in history" :key="x.date" class="card rise flex items-center gap-3 !p-3" :style="{ '--i': i }">
                    <button type="button" class="min-w-0 flex-1 text-left text-sm" :aria-label="`Modifier la mesure du ${fmtFull(x.date)}`" @click="openMeasure(x.date)">
                        <div class="font-medium">{{ fmtFull(x.date) }}</div>
                        <div class="muted">
                            <template v-if="x.weight_kg != null">{{ fmt1(x.weight_kg) }} kg</template>
                            <template v-if="x.waist_cm != null"> · taille {{ fmt1(x.waist_cm) }} cm</template>
                            <template v-if="x.sleep_hours != null"> · sommeil {{ fmt1(x.sleep_hours) }} h</template>
                            <template v-if="x.energy != null"> · énergie {{ x.energy }}/5</template>
                            <template v-if="x.mood != null"> · humeur {{ x.mood }}/5</template>
                        </div>
                        <div v-if="x.notes" class="muted">{{ x.notes }}</div>
                    </button>
                    <button type="button" class="btn btn-danger size-10 !p-0" :aria-label="`Supprimer la mesure du ${fmtFull(x.date)}`" @click="remove(x.date)">
                        <Icon name="trash" :size="18" />
                    </button>
                </li>
            </ul>
        </section>
    
        <GoalForm v-model="goalOpen" />
        <MeasurementForm v-model="measureOpen" :date="measureDate" />
    </div>
</template>
