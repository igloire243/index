<script setup>
import { computed, ref, watch } from 'vue';
import { fmtFull } from '../../lib/dates';
import { fmtMoney } from '../../lib/finance';
import { deleteMovement, loadMovements } from '../../stores/finance';
import { toast } from '../../stores/session';
import Icon from '../Icon.vue';
import Sheet from '../Sheet.vue';

const props = defineProps({ modelValue: Boolean, account: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const list = ref([]);
const loading = ref(false);

async function load() {
    if (!props.account) return;
    loading.value = true;
    try {
        list.value = await loadMovements(props.account.id);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}
watch(() => props.modelValue, (isOpen) => isOpen && load());
watch(() => props.account?.balance, () => props.modelValue && load()); // le solde a changé : on relit l'historique

async function remove(m) {
    const both = m.transfer_id ? ' (les deux côtés du virement seront annulés)' : '';
    if (!confirm(`Annuler ce mouvement de ${fmtMoney(m.amount, props.account.currency)} ?${both}`)) return;
    try {
        await deleteMovement(m.id);
        await load();
        toast('Mouvement annulé.');
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <Sheet v-model="open" :title="account ? `Historique · ${account.name}` : 'Historique'">
        <div v-if="account" class="space-y-3">
            <p class="muted">Solde actuel : <b class="text-slate-900 dark:text-slate-100">{{ fmtMoney(account.balance, account.currency) }}</b></p>
            <p v-if="!loading && !list.length" class="muted py-6 text-center">Aucun mouvement pour l’instant.</p>
            <ul class="space-y-2">
                <li v-for="m in list" :key="m.id" class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-medium break-words">{{ m.note || (m.amount >= 0 ? 'Entrée' : 'Sortie') }}</div>
                        <div class="muted">{{ fmtFull(m.date) }}<template v-if="m.transfer_id"> · virement</template></div>
                    </div>
                    <div class="shrink-0 text-sm font-semibold" :class="m.amount >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ m.amount >= 0 ? '+' : '−' }}{{ fmtMoney(Math.abs(m.amount), account.currency) }}
                    </div>
                    <button type="button" class="btn btn-danger size-9 !p-0" :aria-label="`Annuler le mouvement du ${fmtFull(m.date)}`" @click="remove(m)"><Icon name="x" :size="16" /></button>
                </li>
            </ul>
            <button type="button" class="btn w-full" @click="open = false">Fermer</button>
        </div>
    </Sheet>
</template>
