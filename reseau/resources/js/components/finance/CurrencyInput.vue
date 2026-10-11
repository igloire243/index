<script setup>
import { computed } from 'vue';
import { COMMON_CURRENCIES } from '../../lib/finance';

const props = defineProps({ modelValue: { type: String, default: '' }, id: String, disabled: Boolean, extra: { type: Array, default: () => [] } });
const emit = defineEmits(['update:modelValue']);

const options = computed(() => [...new Set([...props.extra, ...COMMON_CURRENCIES])].slice(0, 8));
const onInput = (e) => emit('update:modelValue', e.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 6));
</script>

<template>
    <div>
        <input :id="id" class="input uppercase" :value="modelValue" maxlength="6" placeholder="USD" autocomplete="off" autocapitalize="characters" :disabled="disabled" @input="onInput" />
        <div v-if="!disabled" class="mt-2 flex flex-wrap gap-2">
            <button v-for="c in options" :key="c" type="button" class="chip" :class="{ 'chip-on': modelValue === c }" @click="emit('update:modelValue', c)">{{ c }}</button>
        </div>
    </div>
</template>
