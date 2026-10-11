<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { extensionFor, fmtClock, recorderMime } from '../../lib/vault';
import Icon from '../Icon.vue';
import Sheet from '../Sheet.vue';

const props = defineProps({ modelValue: Boolean });
const emit = defineEmits(['update:modelValue', 'recorded']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const state = ref('idle'); // idle | recording | done
const seconds = ref(0);
const error = ref('');
const previewUrl = ref('');
let recorder = null;
let stream = null;
let chunks = [];
let tick;
let result = null;

const supported = typeof window !== 'undefined' && window.isSecureContext && !!navigator.mediaDevices?.getUserMedia && recorderMime() !== null;

function cleanup() {
    clearInterval(tick);
    stream?.getTracks().forEach((t) => t.stop());
    stream = null;
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = '';
}

function reset() {
    if (recorder && recorder.state !== 'inactive') {
        recorder.onstop = null;
        recorder.stop();
    }
    cleanup();
    recorder = null;
    chunks = [];
    result = null;
    state.value = 'idle';
    seconds.value = 0;
    error.value = '';
}

watch(() => props.modelValue, (isOpen) => !isOpen && reset());
onBeforeUnmount(reset);

async function start() {
    error.value = '';
    try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (e) {
        error.value = e?.name === 'NotAllowedError' ? 'Le micro est refusé : autorise-le dans les réglages du navigateur.' : 'Aucun micro utilisable.';
        return;
    }
    const mime = recorderMime();
    recorder = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
    chunks = [];
    recorder.ondataavailable = (e) => e.data.size && chunks.push(e.data);
    recorder.onstop = () => {
        const type = (recorder.mimeType || mime || 'audio/webm').split(';')[0];
        result = new File(chunks, `enregistrement-${new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-')}.${extensionFor(type)}`, { type });
        previewUrl.value = URL.createObjectURL(result);
        stream?.getTracks().forEach((t) => t.stop());
        clearInterval(tick);
        state.value = 'done';
    };
    recorder.start(1000);
    seconds.value = 0;
    tick = setInterval(() => seconds.value++, 1000);
    state.value = 'recording';
}

const stop = () => recorder?.state === 'recording' && recorder.stop();

function save() {
    emit('recorded', result);
    open.value = false;
}
</script>

<template>
    <Sheet v-model="open" title="Enregistrement audio">
        <div class="space-y-4 text-center">
            <p v-if="!supported" class="muted">Ce navigateur ne permet pas d’enregistrer ici (il faut une connexion HTTPS et un micro). Tu peux importer un fichier audio à la place.</p>
            <template v-else>
                <div class="text-4xl font-bold tabular-nums" aria-live="off">{{ fmtClock(seconds) }}</div>
                <div class="flex justify-center">
                    <span v-if="state === 'recording'" class="inline-flex items-center gap-2 text-sm text-red-600 dark:text-red-400"><span class="size-2.5 animate-pulse rounded-full bg-red-500" /> Enregistrement en cours</span>
                </div>
                <audio v-if="state === 'done'" :src="previewUrl" controls class="w-full" />
                <p v-if="error" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ error }}</p>

                <div class="flex justify-center gap-2">
                    <button v-if="state === 'idle'" type="button" class="btn btn-primary" @click="start"><Icon name="mic" :size="18" /> Commencer</button>
                    <button v-else-if="state === 'recording'" type="button" class="btn btn-danger" @click="stop"><Icon name="stop" :size="18" /> Arrêter</button>
                    <template v-else>
                        <button type="button" class="btn" @click="reset">Recommencer</button>
                        <button type="button" class="btn btn-primary" @click="save">Ajouter au dossier</button>
                    </template>
                </div>
            </template>
            <button type="button" class="btn w-full" @click="open = false">Fermer</button>
        </div>
    </Sheet>
</template>
