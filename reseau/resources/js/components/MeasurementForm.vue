<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { fmtFull, isoDate } from '../lib/dates';
import { health, saveMeasurement } from '../stores/health';
import { toast } from '../stores/session';
import Sheet from './Sheet.vue';
import Wizard from './Wizard.vue';

const props = defineProps({ modelValue: Boolean, date: { type: String, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const today = isoDate();
const form = reactive({ date: today, weight_kg: '', waist_cm: '', sleep_hours: '', energy: null, mood: null, notes: '' });
const busy = ref(false);

const LEVELS = ['Très bas', 'Bas', 'Moyen', 'Bon', 'Excellent'];

function load(date) {
    const m = health.measurements.find((x) => x.date === date);
    Object.assign(form, {
        date,
        weight_kg: m?.weight_kg ?? '',
        waist_cm: m?.waist_cm ?? '',
        sleep_hours: m?.sleep_hours ?? '',
        energy: m?.energy ?? null,
        mood: m?.mood ?? null,
        notes: m?.notes ?? '',
    });
}

watch(
    () => props.modelValue,
    (isOpen) => isOpen && load(props.date || today),
);
// Changer la date recharge la mesure déjà saisie ce jour-là, s'il y en a une.
watch(
    () => form.date,
    (d, old) => open.value && old && d && d !== old && load(d),
);

const num = (v) => (v === '' || v == null ? null : Number(v));
const hasValue = computed(() => [form.weight_kg, form.waist_cm, form.sleep_hours].some((v) => v !== '' && v != null) || form.energy != null || form.mood != null);

const steps = computed(() => [
    { key: 'weight', title: 'Pesée', valid: !!form.date, hint: 'Choisis une date.' },
    { key: 'body', title: 'Corps & sommeil' },
    { key: 'feel', title: 'Ressenti', valid: hasValue.value, hint: 'Renseigne au moins une valeur (poids, tour de taille, sommeil, énergie ou humeur).' },
]);

async function submit() {
    busy.value = true;
    try {
        await saveMeasurement(form.date, {
            weight_kg: num(form.weight_kg),
            waist_cm: num(form.waist_cm),
            sleep_hours: num(form.sleep_hours),
            energy: form.energy,
            mood: form.mood,
            notes: form.notes || null,
        });
        open.value = false;
        toast('Mesure enregistrée.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" title="Mon état">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'weight'">
                    <div>
                        <label class="label" for="m-date">Date</label>
                        <input id="m-date" v-model="form.date" class="input" type="date" :max="today" required />
                    </div>
                    <div>
                        <label class="label" for="m-weight">Poids (kg)</label>
                        <input id="m-weight" v-model="form.weight_kg" class="input" type="number" inputmode="decimal" step="0.1" min="20" max="400" placeholder="70,5" />
                        <p class="muted mt-1">Le matin, à jeun, dans les mêmes conditions, c’est plus comparable.</p>
                    </div>
                </template>

                <template v-else-if="step.key === 'body'">
                    <div>
                        <label class="label" for="m-waist">Tour de taille (cm)</label>
                        <input id="m-waist" v-model="form.waist_cm" class="input" type="number" inputmode="decimal" step="0.5" min="30" max="250" />
                    </div>
                    <div>
                        <label class="label" for="m-sleep">Sommeil de la nuit (heures)</label>
                        <input id="m-sleep" v-model="form.sleep_hours" class="input" type="number" inputmode="decimal" step="0.5" min="0" max="24" placeholder="7,5" />
                    </div>
                </template>

                <template v-else>
                    <fieldset v-for="f in [{ key: 'energy', label: 'Énergie' }, { key: 'mood', label: 'Humeur' }]" :key="f.key">
                        <legend class="label">{{ f.label }}</legend>
                        <div class="flex gap-2" role="radiogroup" :aria-label="f.label">
                            <button
                                v-for="n in 5"
                                :key="n"
                                type="button"
                                role="radio"
                                :aria-checked="form[f.key] === n"
                                :aria-label="`${f.label} ${n} sur 5 : ${LEVELS[n - 1]}`"
                                class="btn flex-1 !px-0 transition-all"
                                :class="{ 'btn-primary scale-105': form[f.key] === n }"
                                @click="form[f.key] = form[f.key] === n ? null : n"
                            >
                                {{ n }}
                            </button>
                        </div>
                        <p class="muted mt-1 h-5">{{ form[f.key] ? LEVELS[form[f.key] - 1] : '' }}</p>
                    </fieldset>
                    <div>
                        <label class="label" for="m-notes">Notes</label>
                        <input id="m-notes" v-model="form.notes" class="input" maxlength="500" placeholder="Comment tu te sens, ce qui a changé…" />
                    </div>
                    <p class="muted">Mesure du {{ fmtFull(form.date) }}.</p>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
