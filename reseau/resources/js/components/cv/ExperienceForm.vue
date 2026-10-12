<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { saveExperience } from '../../stores/cv';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';

const props = defineProps({ modelValue: Boolean, experience: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ title: '', organization: '', location: '', start_date: '', end_date: '', current: false, description: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const e = props.experience;
        Object.assign(form, {
            title: e?.title ?? '', organization: e?.organization ?? '', location: e?.location ?? '',
            start_date: e?.start_date ?? '', end_date: e?.end_date ?? '', current: e?.current ?? false, description: e?.description ?? '',
        });
    },
);

const steps = computed(() => [
    { key: 'what', title: 'Expérience', valid: form.title.trim().length > 0, hint: 'Donne un titre pour continuer.' },
    { key: 'description', title: 'Détails' },
]);

async function submit() {
    busy.value = true;
    try {
        await saveExperience(
            {
                title: form.title.trim(), organization: form.organization.trim() || null, location: form.location.trim() || null,
                start_date: form.start_date || null, end_date: form.current ? null : form.end_date || null, current: form.current,
                description: form.description.trim() || null,
            },
            props.experience?.id ?? null,
        );
        open.value = false;
        toast(props.experience ? 'Mise à jour.' : 'Ajoutée.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="experience ? 'Modifier l’expérience' : 'Nouvelle expérience'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'what'">
                    <div>
                        <label class="label" for="ex-title">Titre *</label>
                        <input id="ex-title" v-model="form.title" class="input" maxlength="160" placeholder="Chauffeur, vendeur, bénévole…" autocomplete="off" data-autofocus />
                    </div>
                    <div>
                        <label class="label" for="ex-org">Organisation</label>
                        <input id="ex-org" v-model="form.organization" class="input" maxlength="160" autocomplete="off" />
                    </div>
                    <div>
                        <label class="label" for="ex-loc">Lieu</label>
                        <input id="ex-loc" v-model="form.location" class="input" maxlength="120" autocomplete="off" />
                    </div>
                </template>

                <template v-else>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="ex-start">Début</label>
                            <input id="ex-start" v-model="form.start_date" class="input" type="date" />
                        </div>
                        <div>
                            <label class="label" for="ex-end">Fin</label>
                            <input id="ex-end" v-model="form.end_date" class="input" type="date" :disabled="form.current" />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.current" type="checkbox" class="size-4 rounded" />
                        En cours aujourd’hui
                    </label>
                    <div>
                        <label class="label" for="ex-desc">Description</label>
                        <textarea id="ex-desc" v-model="form.description" class="input" rows="4" maxlength="5000" />
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
