<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { generatePassword, generatePin, patternFromString, patternToString, patternValid, strength, TYPES } from '../../lib/secrets';
import { saveSecret } from '../../stores/secrets';
import { toast } from '../../stores/session';
import Icon from '../Icon.vue';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';
import PatternPad from './PatternPad.vue';

const props = defineProps({ modelValue: Boolean, secret: { type: Object, default: null } }); // secret : fiche complète à modifier
const emit = defineEmits(['update:modelValue', 'saved']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ type: 'site', title: '', username: '', url: '', secret: '', notes: '', favorite: false, fields: [] });
const dots = ref([]);
const show = ref(false);
const gen = reactive({ length: 20, upper: true, digits: true, symbols: true, pin: 4 });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const s = props.secret;
        Object.assign(form, {
            type: s?.type ?? 'site', title: s?.title ?? '', username: s?.username ?? '', url: s?.url ?? '', secret: s?.secret ?? '',
            notes: s?.notes ?? '', favorite: s?.favorite ?? false, fields: (s?.fields ?? []).map((f) => ({ label: f.label, value: f.value ?? '', hidden: !!f.hidden })),
        });
        dots.value = s?.type === 'pattern' ? patternFromString(s.secret) : [];
        show.value = !s;
    },
);

const meta = computed(() => TYPES[form.type]);
const isPattern = computed(() => form.type === 'pattern');
const isNote = computed(() => form.type === 'note');
const level = computed(() => (isPattern.value || form.type === 'pin' || form.type === 'card' ? null : strength(form.secret)));

const steps = computed(() => [
    { key: 'type', title: 'Type' },
    { key: 'details', title: 'Détails', valid: form.title.trim().length > 0, hint: 'Donne un nom à cette fiche pour continuer.' },
    ...(isNote.value ? [] : [{ key: 'secret', title: 'Secret', valid: isPattern.value ? patternValid(dots.value) : form.secret.length > 0, hint: isPattern.value ? 'Dessine un schéma d’au moins 4 points.' : 'Saisis le mot de passe ou le code.' }]),
    { key: 'more', title: 'Plus', valid: !isNote.value || form.notes.trim().length > 0, hint: 'Écris le contenu de la note.' },
]);

const regenerate = () => {
    form.secret = meta.value.generate === 'pin' ? generatePin(gen.pin) : generatePassword({ length: gen.length, upper: gen.upper, digits: gen.digits, symbols: gen.symbols });
    show.value = true;
};
const addField = () => form.fields.push({ label: '', value: '', hidden: true });

async function submit() {
    busy.value = true;
    try {
        const body = {
            type: form.type,
            favorite: form.favorite,
            title: form.title.trim(),
            username: form.username.trim() || null,
            url: form.url.trim() || null,
            secret: isNote.value ? null : isPattern.value ? patternToString(dots.value) : form.secret,
            notes: form.notes.trim() || null,
            fields: form.fields.filter((f) => f.label.trim()).map((f) => ({ label: f.label.trim(), value: f.value || null, hidden: f.hidden })),
        };
        const saved = await saveSecret(body, props.secret?.id ?? null);
        open.value = false;
        toast(props.secret ? 'Fiche mise à jour.' : 'Fiche ajoutée.');
        emit('saved', saved);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="secret ? 'Modifier la fiche' : 'Nouvelle fiche'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'type'">
                    <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Type de fiche">
                        <button
                            v-for="(t, key) in TYPES"
                            :key="key"
                            type="button"
                            role="radio"
                            :aria-checked="form.type === key"
                            class="flex items-center gap-2 rounded-2xl border-2 p-3 text-left text-sm transition-colors"
                            :class="form.type === key ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'"
                            @click="form.type = key"
                        >
                            <Icon :name="t.icon" :size="18" /> {{ t.label }}
                        </button>
                    </div>
                </template>

                <template v-else-if="step.key === 'details'">
                    <div>
                        <label class="label" for="sf-title">Nom de la fiche *</label>
                        <input id="sf-title" v-model="form.title" class="input" maxlength="160" autocomplete="off" :placeholder="meta.label" />
                    </div>
                    <div v-if="meta.userLabel">
                        <label class="label" for="sf-user">{{ meta.userLabel }}</label>
                        <input id="sf-user" v-model="form.username" class="input" maxlength="255" autocomplete="off" autocapitalize="off" />
                    </div>
                    <div v-if="meta.urlLabel">
                        <label class="label" for="sf-url">{{ meta.urlLabel }}</label>
                        <input id="sf-url" v-model="form.url" class="input" maxlength="500" inputmode="url" autocomplete="off" autocapitalize="off" placeholder="https://" />
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.favorite" type="checkbox" class="size-5" /> Marquer comme favori</label>
                </template>

                <template v-else-if="step.key === 'secret'">
                    <template v-if="isPattern">
                        <p class="muted">Touche ou glisse sur les points, dans l’ordre de ton schéma.</p>
                        <PatternPad v-model="dots" />
                    </template>
                    <template v-else>
                        <div>
                            <label class="label" for="sf-secret">{{ meta.secretLabel }} *</label>
                            <div class="flex gap-2">
                                <input id="sf-secret" v-model="form.secret" class="input font-mono" :type="show ? 'text' : 'password'" maxlength="2000" autocomplete="new-password" autocapitalize="off" spellcheck="false" :inputmode="meta.generate === 'pin' ? 'numeric' : undefined" />
                                <button type="button" class="btn shrink-0 px-3" :aria-label="show ? 'Masquer' : 'Afficher'" @click="show = !show"><Icon :name="show ? 'eyeoff' : 'eye'" :size="18" /></button>
                            </div>
                        </div>
                        <div v-if="level && form.secret" class="space-y-1" aria-live="polite">
                            <div class="flex gap-1" aria-hidden="true">
                                <span v-for="i in 4" :key="i" class="h-1.5 flex-1 rounded-full transition-colors" :class="i <= level.level ? ['', 'bg-red-500', 'bg-amber-500', 'bg-lime-500', 'bg-emerald-500'][level.level] : 'bg-slate-200 dark:bg-slate-700'" />
                            </div>
                            <p class="muted text-xs">Robustesse : {{ level.label }}</p>
                        </div>
                        <div v-if="meta.generate" class="space-y-2 rounded-2xl bg-slate-50 p-3 dark:bg-slate-800">
                            <template v-if="meta.generate === 'password'">
                                <label class="label" for="sf-len">Longueur : {{ gen.length }}</label>
                                <input id="sf-len" v-model.number="gen.length" type="range" min="8" max="48" class="w-full" />
                                <div class="flex flex-wrap gap-3 text-sm">
                                    <label class="flex items-center gap-1.5"><input v-model="gen.upper" type="checkbox" class="size-4" /> Majuscules</label>
                                    <label class="flex items-center gap-1.5"><input v-model="gen.digits" type="checkbox" class="size-4" /> Chiffres</label>
                                    <label class="flex items-center gap-1.5"><input v-model="gen.symbols" type="checkbox" class="size-4" /> Symboles</label>
                                </div>
                            </template>
                            <template v-else>
                                <label class="label" for="sf-pin">Nombre de chiffres : {{ gen.pin }}</label>
                                <input id="sf-pin" v-model.number="gen.pin" type="range" min="4" max="8" class="w-full" />
                            </template>
                            <button type="button" class="btn w-full" @click="regenerate">Générer {{ meta.generate === 'pin' ? 'un code' : 'un mot de passe' }}</button>
                        </div>
                    </template>
                </template>

                <template v-else>
                    <div>
                        <label class="label" for="sf-notes">{{ isNote ? 'Contenu de la note *' : 'Notes' }}</label>
                        <textarea id="sf-notes" v-model="form.notes" class="input" :rows="isNote ? 7 : 3" maxlength="10000" :placeholder="isNote ? '' : 'Question secrète, conseils, date d’expiration…'" />
                    </div>
                    <div v-for="(f, i) in form.fields" :key="i" class="space-y-2 rounded-2xl border border-slate-200 p-3 dark:border-slate-700">
                        <input v-model="f.label" class="input" maxlength="80" placeholder="Intitulé (code de secours, CVV…)" :aria-label="`Intitulé du champ ${i + 1}`" />
                        <input v-model="f.value" class="input font-mono" :type="f.hidden ? 'password' : 'text'" maxlength="2000" placeholder="Valeur" autocomplete="off" :aria-label="`Valeur du champ ${i + 1}`" />
                        <div class="flex items-center justify-between text-sm">
                            <label class="flex items-center gap-1.5"><input v-model="f.hidden" type="checkbox" class="size-4" /> Masquer</label>
                            <button type="button" class="btn btn-danger btn-sm" @click="form.fields.splice(i, 1)">Retirer</button>
                        </div>
                    </div>
                    <button v-if="form.fields.length < 20" type="button" class="btn w-full" @click="addField"><Icon name="plus" :size="16" /> Ajouter un champ</button>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
