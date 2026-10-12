<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import AudioRecorder from '../../components/vault/AudioRecorder.vue';
import DossierForm from '../../components/vault/DossierForm.vue';
import FileViewer from '../../components/vault/FileViewer.vue';
import { categoryLabel, expiryStatus, isMergeable, mergeDocuments } from '../../lib/documents';
import { downloadBlob } from '../../lib/export';
import { contentUrl, fmtSize, KINDS, precheck, uploadFile } from '../../lib/vault';
import { toast } from '../../stores/session';
import { loadDossier, loadVaultStatus, removeDossier, touchVault, vault } from '../../stores/vault';

const route = useRoute();
const router = useRouter();
const dossier = ref(null);
const loading = ref(true);
const notFound = ref(false);
const formOpen = ref(false);
const recorderOpen = ref(false);
const viewing = ref(null);
const viewerOpen = ref(false);
const queue = reactive([]);
const inputs = { files: ref(null), photo: ref(null), video: ref(null) };
const selecting = ref(false);
const selected = reactive(new Set());
const merging = ref(false);
let controller = new AbortController();
let uid = 0;

const id = computed(() => Number(route.params.id));
const files = computed(() => dossier.value?.files ?? []);
const expiry = computed(() => (dossier.value ? expiryStatus(dossier.value) : null));

async function load() {
    loading.value = true;
    try {
        dossier.value = await loadDossier(id.value);
        notFound.value = false;
    } catch (e) {
        if (e.status === 404) notFound.value = true;
        else if (e.code !== 'vault_locked') toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}
onMounted(load);
watch(id, load);
onBeforeUnmount(() => controller.abort());

const pick = (which) => inputs[which].value?.click();

async function enqueue(list) {
    const picked = [...list];
    for (const file of picked) {
        const problem = precheck(file, { maxFile: vault.maxFile, quota: vault.quota, used: vault.used });
        const item = reactive({ key: ++uid, name: file.name, progress: 0, error: problem, file, started: false, final: !!problem });
        queue.push(item);
    }
    for (const item of queue.filter((q) => !q.final && !q.error && !q.started)) {
        await send(item);
    }
}

async function send(item) {
    item.started = true;
    item.error = null;
    item.progress = 0;
    try {
        const saved = await uploadFile(id.value, item.file, { onProgress: (p) => (item.progress = p), signal: controller.signal });
        dossier.value.files.push(saved);
        queue.splice(queue.indexOf(item), 1);
        await loadVaultStatus();
        touchVault();
    } catch (e) {
        if (e.code === 'aborted') return;
        item.error = e.message;
        item.started = false;
        item.final = e.status >= 400 && e.status < 500; // refus du serveur : réessayer ne changerait rien
    }
}

function onPicked(event) {
    const input = event.target;
    const list = [...input.files];
    input.value = ''; // permet de choisir deux fois le même fichier
    if (list.length) enqueue(list);
}

const retry = (item) => send(item);
const dismiss = (item) => queue.splice(queue.indexOf(item), 1);

function view(file) {
    if (selecting.value) {
        if (!isMergeable(file)) return;
        if (selected.has(file.id)) selected.delete(file.id);
        else selected.add(file.id);

        return;
    }
    viewing.value = file;
    viewerOpen.value = true;
}

function toggleSelecting() {
    selecting.value = !selecting.value;
    selected.clear();
}

async function mergeSelected() {
    merging.value = true;
    try {
        const { blob, name } = await mergeDocuments([...selected]);
        downloadBlob(blob, name);
        selecting.value = false;
        selected.clear();
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        merging.value = false;
    }
}
const onUpdated = (f) => {
    const i = dossier.value.files.findIndex((x) => x.id === f.id);
    if (i >= 0) dossier.value.files[i] = f;
    viewing.value = f;
};
const onDeleted = (fid) => (dossier.value.files = dossier.value.files.filter((f) => f.id !== fid));

async function removeThis() {
    const n = files.value.length;
    if (!confirm(`Supprimer le dossier « ${dossier.value.title} »${n ? ` et ses ${n} fichier${n > 1 ? 's' : ''}` : ''} ? Cette action est définitive.`)) return;
    try {
        await removeDossier(id.value);
        toast('Dossier supprimé.');
        router.replace('/coffre');
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <div class="space-y-4">
        <RouterLink to="/coffre" class="muted inline-flex items-center gap-1"><Icon name="left" :size="16" /> Tous les dossiers</RouterLink>

        <EmptyState v-if="notFound" illustration="vault" title="Dossier introuvable">
            <template #actions><RouterLink to="/coffre" class="btn btn-primary">Retour aux dossiers</RouterLink></template>
        </EmptyState>

        <template v-else-if="dossier">
            <section class="card space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold break-words">{{ dossier.title }}</h2>
                            <span v-if="dossier.category" class="tag">{{ categoryLabel(dossier.category) }}</span>
                            <span v-if="expiry === 'expired'" class="tag bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">Expiré</span>
                            <span v-else-if="expiry === 'soon'" class="tag bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">Expire bientôt</span>
                        </div>
                        <p v-if="dossier.subject" class="muted">{{ dossier.subject }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="btn btn-sm" @click="formOpen = true">Modifier</button>
                        <button type="button" class="btn btn-danger btn-sm" @click="removeThis">Supprimer</button>
                    </div>
                </div>
                <dl v-if="dossier.category" class="grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                    <template v-if="dossier.document_number">
                        <dt class="muted">N°</dt>
                        <dd class="selectable">{{ dossier.document_number }}</dd>
                    </template>
                    <template v-if="dossier.issued_by">
                        <dt class="muted">Émis par</dt>
                        <dd>{{ dossier.issued_by }}</dd>
                    </template>
                    <template v-if="dossier.issue_date">
                        <dt class="muted">Émission</dt>
                        <dd>{{ new Date(dossier.issue_date).toLocaleDateString('fr-FR') }}</dd>
                    </template>
                    <template v-if="dossier.expiry_date">
                        <dt class="muted">Expiration</dt>
                        <dd>{{ new Date(dossier.expiry_date).toLocaleDateString('fr-FR') }}</dd>
                    </template>
                </dl>
                <p v-if="dossier.summary" class="selectable text-sm whitespace-pre-wrap">{{ dossier.summary }}</p>
            </section>

            <section class="space-y-3">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <button type="button" class="btn" @click="pick('files')"><Icon name="upload" :size="18" /> Importer</button>
                    <button type="button" class="btn" @click="pick('photo')"><Icon name="camera" :size="18" /> Photo</button>
                    <button type="button" class="btn" @click="pick('video')"><Icon name="film" :size="18" /> Vidéo</button>
                    <button type="button" class="btn" @click="recorderOpen = true"><Icon name="mic" :size="18" /> Audio</button>
                </div>
                <input :ref="(el) => (inputs.files.value = el)" type="file" multiple class="hidden" aria-label="Importer des fichiers" @change="onPicked" />
                <input :ref="(el) => (inputs.photo.value = el)" type="file" accept="image/*" capture="environment" class="hidden" aria-label="Prendre une photo" @change="onPicked" />
                <input :ref="(el) => (inputs.video.value = el)" type="file" accept="video/*" capture="environment" class="hidden" aria-label="Filmer une vidéo" @change="onPicked" />

                <ul v-if="queue.length" class="space-y-2" aria-label="Envois en cours">
                    <li v-for="q in queue" :key="q.key" class="card space-y-1.5 py-3">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="min-w-0 truncate">{{ q.name }}</span>
                            <span v-if="!q.error" class="muted shrink-0">{{ Math.round(q.progress * 100) }} %</span>
                        </div>
                        <div v-if="!q.error" class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" :aria-valuenow="Math.round(q.progress * 100)" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-indigo-500 transition-[width] duration-300" :style="{ width: q.progress * 100 + '%' }" />
                        </div>
                        <div v-else class="flex items-center justify-between gap-2">
                            <span class="text-sm text-red-600 dark:text-red-400" role="alert">{{ q.error }}</span>
                            <span class="flex shrink-0 gap-2">
                                <button v-if="q.file.size && !q.started && !q.final" type="button" class="btn btn-sm" @click="retry(q)">Réessayer</button>
                                <button type="button" class="btn btn-sm" @click="dismiss(q)">Ignorer</button>
                            </span>
                        </div>
                    </li>
                </ul>

                <EmptyState v-if="!files.length && !queue.length" illustration="vault" small title="Ce dossier est vide">
                    <p>Importe des fichiers, prends une photo, filme ou enregistre un message vocal.</p>
                </EmptyState>

                <div v-if="files.some((f) => isMergeable(f))" class="flex items-center justify-between gap-2">
                    <button type="button" class="btn btn-sm" @click="toggleSelecting">{{ selecting ? 'Annuler la sélection' : 'Sélectionner' }}</button>
                    <button v-if="selecting" type="button" class="btn btn-primary btn-sm" :disabled="selected.size < 2 || merging" @click="mergeSelected">
                        <Icon name="copy" :size="14" /> {{ merging ? 'Fusion…' : `Fusionner en PDF (${selected.size})` }}
                    </button>
                </div>
                <p v-if="selecting" class="muted text-xs">Choisis au moins deux PDF ou images (JPEG/PNG) à combiner dans l’ordre.</p>

                <ul v-if="files.length" class="grid grid-cols-3 gap-2">
                    <li v-for="(f, i) in files" :key="f.id" class="rise" :style="{ '--i': Math.min(i, 8) }">
                        <button
                            type="button"
                            class="relative block aspect-square w-full overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-left transition-transform active:scale-95 dark:border-slate-700 dark:bg-slate-800"
                            :class="{ 'opacity-40': selecting && !isMergeable(f), 'ring-2 ring-indigo-600': selected.has(f.id) }"
                            :aria-label="`Ouvrir ${f.name}`"
                            @click="view(f)"
                        >
                            <img v-if="f.kind === 'image'" :src="contentUrl(f.id)" :alt="f.name" loading="lazy" class="size-full object-cover" />
                            <span v-else class="grid size-full place-items-center text-slate-500 dark:text-slate-400"><Icon :name="KINDS[f.kind]?.icon ?? 'file'" :size="32" /></span>
                            <span class="absolute inset-x-0 bottom-0 truncate bg-slate-900/60 px-1.5 py-0.5 text-[10px] text-white">{{ f.name }}</span>
                            <span v-if="f.kind === 'video'" class="absolute top-1.5 right-1.5 rounded-full bg-slate-900/60 p-1 text-white"><Icon name="film" :size="12" /></span>
                            <span v-if="selecting && isMergeable(f)" class="absolute top-1.5 left-1.5 grid size-5 place-items-center rounded-full border-2 border-white text-white" :class="selected.has(f.id) ? 'bg-indigo-600' : 'bg-slate-900/40'">
                                <Icon v-if="selected.has(f.id)" name="check" :size="12" />
                            </span>
                        </button>
                        <span class="muted block pt-0.5 text-center text-[11px]">{{ fmtSize(f.size) }}</span>
                    </li>
                </ul>
            </section>

            <DossierForm v-model="formOpen" :dossier="dossier" @saved="(d) => (dossier = { ...d, files: dossier.files })" />
            <AudioRecorder v-model="recorderOpen" @recorded="(file) => enqueue([file])" />
            <FileViewer v-model="viewerOpen" :file="viewing" @updated="onUpdated" @deleted="onDeleted" />
        </template>

        <div v-else-if="loading" class="muted py-10 text-center">Chargement…</div>
    </div>
</template>
