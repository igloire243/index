<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { copyText, patternFromString, TYPES } from '../../lib/secrets';
import { getSecret, removeSecret, setFavorite } from '../../stores/secrets';
import { toast } from '../../stores/session';
import Icon from '../Icon.vue';
import Sheet from '../Sheet.vue';
import PatternPad from './PatternPad.vue';
import SecretForm from './SecretForm.vue';

const REVEAL_MS = 20_000; // un secret affiché se remasque tout seul
const CLEAR_MS = 30_000; // et le presse-papiers est vidé

const props = defineProps({ modelValue: Boolean, id: { type: Number, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const item = ref(null);
const loading = ref(false);
const revealed = ref(new Set()); // 'secret', 'notes', 'f0', 'f1'…
const editing = ref(false);
const timers = new Map();
let clipTimer;

const meta = computed(() => TYPES[item.value?.type]);

function hideAll() {
    revealed.value = new Set();
    timers.forEach(clearTimeout);
    timers.clear();
}

async function load() {
    hideAll();
    item.value = null;
    if (!props.modelValue || props.id == null) return;
    loading.value = true;
    try {
        item.value = await getSecret(props.id);
    } catch (e) {
        if (e.code !== 'vault_locked') toast(e.message, 'error');
        open.value = false;
    } finally {
        loading.value = false;
    }
}
watch(() => [props.modelValue, props.id], load, { immediate: true });
onBeforeUnmount(() => {
    hideAll();
    clearTimeout(clipTimer);
});

function toggle(key) {
    const next = new Set(revealed.value);
    clearTimeout(timers.get(key));
    if (next.has(key)) next.delete(key);
    else {
        next.add(key);
        timers.set(key, setTimeout(() => {
            const after = new Set(revealed.value);
            after.delete(key);
            revealed.value = after;
        }, REVEAL_MS));
    }
    revealed.value = next;
}

async function copy(text, label) {
    if (!(await copyText(text))) return toast('Copie impossible sur cet appareil.', 'error');
    toast(`${label} copié ; le presse-papiers sera vidé dans 30 s.`);
    clearTimeout(clipTimer);
    clipTimer = setTimeout(() => navigator.clipboard?.writeText('').catch(() => {}), CLEAR_MS);
}

async function favorite() {
    try {
        const saved = await setFavorite(item.value.id, !item.value.favorite);
        item.value = { ...item.value, favorite: saved.favorite };
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function remove() {
    if (!confirm(`Supprimer définitivement « ${item.value.title} » ?`)) return;
    try {
        await removeSecret(item.value.id);
        open.value = false;
        toast('Fiche supprimée.');
    } catch (e) {
        toast(e.message, 'error');
    }
}

const dots = computed(() => (item.value?.type === 'pattern' ? patternFromString(item.value.secret) : []));
const mask = (s) => '•'.repeat(Math.min(Math.max(String(s ?? '').length, 6), 16));
</script>

<template>
    <Sheet v-model="open" :title="undefined">
        <div v-if="loading" class="muted py-8 text-center">Ouverture…</div>
        <div v-else-if="item" class="space-y-4">
            <div class="flex items-start gap-3">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300"><Icon :name="meta.icon" /></span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold break-words">{{ item.title }}</h2>
                    <p class="muted">{{ meta.label }}</p>
                </div>
                <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full" :class="item.favorite ? 'text-amber-500' : 'text-slate-400'" :aria-label="item.favorite ? 'Retirer des favoris' : 'Ajouter aux favoris'" :aria-pressed="item.favorite" @click="favorite">
                    <Icon name="star" :size="22" />
                </button>
            </div>

            <dl class="selectable space-y-3 text-sm">
                <div v-if="item.username">
                    <dt class="muted">{{ meta.userLabel }}</dt>
                    <dd class="flex items-center justify-between gap-2">
                        <span class="break-all">{{ item.username }}</span>
                        <button type="button" class="btn btn-sm shrink-0" @click="copy(item.username, 'Identifiant')"><Icon name="copy" :size="14" /> Copier</button>
                    </dd>
                </div>
                <div v-if="item.url">
                    <dt class="muted">{{ meta.urlLabel }}</dt>
                    <dd class="break-all">
                        <a v-if="/^https?:\/\//i.test(item.url)" :href="item.url" target="_blank" rel="noopener noreferrer" class="text-indigo-600 underline dark:text-indigo-400">{{ item.url }}</a>
                        <span v-else>{{ item.url }}</span>
                    </dd>
                </div>

                <div v-if="item.secret">
                    <dt class="muted">{{ meta.secretLabel }}</dt>
                    <dd v-if="item.type === 'pattern'" class="space-y-2">
                        <PatternPad v-if="revealed.has('secret')" :model-value="dots" readonly />
                        <button type="button" class="btn btn-sm" @click="toggle('secret')"><Icon :name="revealed.has('secret') ? 'eyeoff' : 'eye'" :size="14" /> {{ revealed.has('secret') ? 'Masquer' : 'Afficher le schéma' }}</button>
                    </dd>
                    <dd v-else class="flex items-center justify-between gap-2">
                        <span class="font-mono text-base break-all" :aria-label="revealed.has('secret') ? undefined : 'Secret masqué'">{{ revealed.has('secret') ? item.secret : mask(item.secret) }}</span>
                        <span class="flex shrink-0 gap-2">
                            <button type="button" class="btn btn-sm px-2.5" :aria-label="revealed.has('secret') ? 'Masquer' : 'Afficher'" @click="toggle('secret')"><Icon :name="revealed.has('secret') ? 'eyeoff' : 'eye'" :size="16" /></button>
                            <button type="button" class="btn btn-sm" @click="copy(item.secret, 'Secret')"><Icon name="copy" :size="14" /> Copier</button>
                        </span>
                    </dd>
                </div>

                <div v-for="(f, i) in item.fields" :key="i">
                    <dt class="muted">{{ f.label }}</dt>
                    <dd class="flex items-center justify-between gap-2">
                        <span class="font-mono break-all">{{ f.hidden && !revealed.has('f' + i) ? mask(f.value) : f.value }}</span>
                        <span class="flex shrink-0 gap-2">
                            <button v-if="f.hidden" type="button" class="btn btn-sm px-2.5" :aria-label="revealed.has('f' + i) ? 'Masquer' : 'Afficher'" @click="toggle('f' + i)"><Icon :name="revealed.has('f' + i) ? 'eyeoff' : 'eye'" :size="16" /></button>
                            <button type="button" class="btn btn-sm" @click="copy(f.value ?? '', f.label)"><Icon name="copy" :size="14" /> Copier</button>
                        </span>
                    </dd>
                </div>

                <div v-if="item.notes">
                    <dt class="muted">{{ item.type === 'note' ? 'Note' : 'Notes' }}</dt>
                    <dd v-if="item.type === 'note' && !revealed.has('notes')"><button type="button" class="btn btn-sm" @click="toggle('notes')"><Icon name="eye" :size="14" /> Afficher la note</button></dd>
                    <dd v-else class="whitespace-pre-wrap">{{ item.notes }}</dd>
                </div>
            </dl>

            <div class="flex flex-wrap justify-between gap-2 pt-2">
                <button type="button" class="btn btn-danger" @click="remove">Supprimer</button>
                <span class="flex gap-2">
                    <button type="button" class="btn" @click="editing = true">Modifier</button>
                    <button type="button" class="btn" @click="open = false">Fermer</button>
                </span>
            </div>
        </div>
    </Sheet>
    <SecretForm v-model="editing" :secret="item" @saved="(s) => (item = s)" />
</template>
