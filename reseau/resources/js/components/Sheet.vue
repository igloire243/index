<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({ modelValue: Boolean, title: String });
const emit = defineEmits(['update:modelValue']);
const dlg = ref(null);
const rendered = ref(false); // le contenu reste affiché pendant l'animation de fermeture

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function closeAnimated() {
    const d = dlg.value;
    if (!d?.open) return;
    if (reduced()) return d.close();
    d.classList.add('closing');
    setTimeout(() => {
        d.close();
        d.classList.remove('closing');
    }, 180);
}

// <dialog> natif : focus piégé et fond assombri gérés par le navigateur.
watch(
    () => props.modelValue,
    async (open) => {
        if (open) {
            rendered.value = true;
            await nextTick();
            if (dlg.value && !dlg.value.open) dlg.value.showModal();
        } else closeAnimated();
    },
    { immediate: true, flush: 'post' },
);

const onClose = () => {
    rendered.value = false;
    emit('update:modelValue', false);
};
const requestClose = () => emit('update:modelValue', false);
const onBackdrop = (e) => {
    if (e.target === dlg.value) requestClose();
};
</script>

<template>
    <dialog
        ref="dlg"
        class="m-auto mb-0 max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-white p-0 text-slate-900 shadow-2xl sm:mb-auto sm:rounded-3xl dark:bg-slate-900 dark:text-slate-100"
        :aria-label="title"
        @close="onClose"
        @cancel.prevent="requestClose"
        @click="onBackdrop"
    >
        <div v-if="rendered" class="p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))]">
            <h2 v-if="title" class="mb-4 text-lg font-semibold">{{ title }}</h2>
            <slot />
        </div>
    </dialog>
</template>
