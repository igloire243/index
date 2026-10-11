<script setup>
import { computed, ref, watch } from 'vue';
import { contentUrl, fmtSize, KINDS } from '../../lib/vault';
import { toast } from '../../stores/session';
import { removeFile, updateFile } from '../../stores/vault';
import Icon from '../Icon.vue';
import Sheet from '../Sheet.vue';

const props = defineProps({ modelValue: Boolean, file: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue', 'updated', 'deleted']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const name = ref('');
const caption = ref('');
const busy = ref(false);

watch(
    () => props.file?.id,
    () => {
        name.value = props.file?.name ?? '';
        caption.value = props.file?.caption ?? '';
    },
    { immediate: true },
);

const url = computed(() => (props.file ? contentUrl(props.file.id) : ''));
const dirty = computed(() => name.value.trim() !== props.file?.name || (caption.value.trim() || null) !== (props.file?.caption || null));

async function save() {
    busy.value = true;
    try {
        const updated = await updateFile(props.file.id, { name: name.value.trim() || props.file.name, caption: caption.value.trim() || null });
        emit('updated', updated);
        toast('Modifications enregistrées.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}

async function remove() {
    if (!confirm(`Supprimer définitivement « ${props.file.name} » ?`)) return;
    try {
        await removeFile(props.file.id);
        emit('deleted', props.file.id);
        open.value = false;
        toast('Fichier supprimé.');
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <Sheet v-model="open" :title="undefined">
        <div v-if="file" class="space-y-4">
            <div class="overflow-hidden rounded-2xl bg-slate-100 dark:bg-slate-800">
                <img v-if="file.kind === 'image'" :src="url" :alt="file.name" class="mx-auto max-h-[60dvh] w-full object-contain" />
                <video v-else-if="file.kind === 'video'" :src="url" controls playsinline preload="metadata" class="max-h-[60dvh] w-full" />
                <div v-else-if="file.kind === 'audio'" class="p-4"><audio :src="url" controls preload="metadata" class="w-full" /></div>
                <div v-else class="grid place-items-center gap-2 p-8 text-slate-500"><Icon name="file" :size="40" /><span class="text-sm">Aperçu indisponible : télécharge le fichier pour l’ouvrir.</span></div>
            </div>
            <p class="muted">{{ KINDS[file.kind]?.label }} · {{ fmtSize(file.size) }} · {{ file.mime }}</p>

            <div>
                <label class="label" for="fv-name">Nom</label>
                <input id="fv-name" v-model="name" class="input" maxlength="200" autocomplete="off" />
            </div>
            <div>
                <label class="label" for="fv-caption">Note sur ce fichier</label>
                <textarea id="fv-caption" v-model="caption" class="input" rows="3" maxlength="2000" placeholder="Date, lieu, contexte…" />
            </div>

            <div class="flex flex-wrap justify-between gap-2">
                <a class="btn" :href="url" :download="file.name"><Icon name="download" :size="18" /> Télécharger</a>
                <button type="button" class="btn btn-primary" :disabled="!dirty || busy" @click="save">Enregistrer</button>
            </div>
            <div class="flex justify-between gap-2">
                <button type="button" class="btn btn-danger" @click="remove">Supprimer</button>
                <button type="button" class="btn" @click="open = false">Fermer</button>
            </div>
        </div>
    </Sheet>
</template>
