<script setup>
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import icon2x from 'leaflet/dist/images/marker-icon-2x.png';
import icon from 'leaflet/dist/images/marker-icon.png';
import shadow from 'leaflet/dist/images/marker-shadow.png';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ContactRow from '../components/ContactRow.vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import { allDomains, fmtDist, hasCoords, inDomain } from '../lib/contacts';
import { contacts, distTo, loadContacts, locate, openDetail } from '../stores/contacts';
import { toast } from '../stores/session';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl: icon, iconRetinaUrl: icon2x, shadowUrl: shadow });

const mapEl = ref(null);
const domain = ref('');
const radius = ref(50);
const fullscreen = ref(false);
let map;
let markers;
let me;

const domains = computed(() => allDomains(contacts.list));
const withoutPlace = computed(() => contacts.list.filter((c) => !hasCoords(c)).length);

const items = computed(() => {
    let list = contacts.list.filter((c) => hasCoords(c) && (!domain.value || inDomain(c, domain.value)));
    if (contacts.pos && radius.value > 0) list = list.filter((c) => distTo(c) <= radius.value);
    return contacts.pos ? list.sort((a, b) => distTo(a) - distTo(b)) : list.sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const hint = computed(() => {
    const parts = [];
    parts.push(
        contacts.pos
            ? `${items.value.length} contact${items.value.length > 1 ? 's' : ''} ${radius.value > 0 ? `dans ${radius.value} km` : 'sur la carte'}.`
            : 'Active ta position pour voir qui est près de toi.',
    );
    if (withoutPlace.value) parts.push(`${withoutPlace.value} sans localisation.`);
    return parts.join(' ');
});

function popup(c) {
    const el = document.createElement('div');
    const name = document.createElement('b');
    name.textContent = c.name;
    el.append(name);
    for (const line of [c.role, distTo(c) != null ? fmtDist(distTo(c)) : null]) {
        if (line) el.append(document.createElement('br'), document.createTextNode(line));
    }
    const link = document.createElement('a');
    link.href = '#';
    link.textContent = 'Ouvrir la fiche';
    link.className = 'block mt-1';
    link.addEventListener('click', (e) => {
        e.preventDefault();
        openDetail(c.id);
    });
    el.append(document.createElement('br'), link);
    return el;
}

function draw(fit) {
    if (!map) return;
    markers.clearLayers();
    me.clearLayers();
    const pts = [];
    for (const c of items.value) {
        L.marker([c.lat, c.lng]).bindPopup(popup(c)).addTo(markers);
        pts.push([c.lat, c.lng]);
    }
    if (contacts.pos) {
        L.circleMarker([contacts.pos.lat, contacts.pos.lng], { radius: 8, color: '#fff', weight: 2, fillColor: '#2563eb', fillOpacity: 1 })
            .bindTooltip('Moi')
            .addTo(me);
        if (radius.value > 0) L.circle([contacts.pos.lat, contacts.pos.lng], { radius: radius.value * 1000, weight: 1, color: '#2563eb', fillOpacity: 0.06 }).addTo(me);
        pts.push([contacts.pos.lat, contacts.pos.lng]);
    }
    if (fit && pts.length) {
        if (pts.length === 1) map.setView(pts[0], 13);
        else map.fitBounds(pts, { padding: [30, 30], maxZoom: 15 });
    }
}

onMounted(async () => {
    map = L.map(mapEl.value, { doubleClickZoom: false }).setView([0, 20], 2);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);
    markers = L.layerGroup().addTo(map);
    me = L.layerGroup().addTo(map);
    try {
        await loadContacts();
    } catch (e) {
        toast(e.message, 'error');
    }
    draw(true);
});

// Plein écran : la carte recouvre tout l'écran (superposition CSS, qui marche aussi sur iPhone) ; Échap ou le bouton la ramènent.
const onKey = (e) => e.key === 'Escape' && fullscreen.value && !document.querySelector('dialog[open]') && (fullscreen.value = false);
window.addEventListener('keydown', onKey);

watch(fullscreen, async (on) => {
    document.documentElement.style.overflow = on ? 'hidden' : '';
    await nextTick();
    map?.invalidateSize();
    draw(true);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    document.documentElement.style.overflow = '';
    map?.remove();
});

watch([items, () => contacts.pos], () => draw(false));
watch([domain, radius], () => draw(true));

async function onLocate() {
    if (await locate()) {
        toast('Position mise à jour.');
        draw(true);
    } else toast(contacts.posError, 'error');
}
</script>

<template>
    <div class="space-y-3">
        <div class="grid grid-cols-[1fr_1fr_auto] items-end gap-2">
            <label class="muted block">
                Domaine
                <select v-model="domain" class="input mt-1">
                    <option value="">Tous</option>
                    <option v-for="d in domains" :key="d.key" :value="d.key">{{ d.label }}</option>
                </select>
            </label>
            <label class="muted block">
                Rayon
                <select v-model.number="radius" class="input mt-1">
                    <option v-for="r in [1, 5, 10, 25, 50, 100]" :key="r" :value="r">{{ r }} km</option>
                    <option :value="0">Illimité</option>
                </select>
            </label>
            <button type="button" class="btn size-11 !p-0" :class="{ 'text-indigo-600': contacts.pos }" aria-label="Utiliser ma position" @click="onLocate">
                <Icon name="locate" />
            </button>
        </div>

        <div :class="fullscreen ? 'fixed inset-0 z-50 !m-0 bg-slate-50 dark:bg-slate-950' : 'relative'">
            <!-- Le cadre change de taille ; l'élément de la carte garde des classes fixes (Leaflet y ajoute les siennes). -->
            <div :class="fullscreen ? 'z-0 h-full w-full' : 'z-0 h-[48vh] min-h-72 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800'">
                <div ref="mapEl" class="h-full w-full" role="application" aria-label="Carte de mes contacts" />
            </div>

            <!-- Boutons flottants au-dessus de la carte -->
            <div class="absolute top-2 right-2 z-[1000] flex flex-col gap-2" :class="{ 'top-[calc(0.5rem+env(safe-area-inset-top))]': fullscreen }">
                <button
                    type="button"
                    class="btn size-11 !p-0 shadow-md"
                    :aria-label="fullscreen ? 'Quitter le plein écran' : 'Afficher la carte en plein écran'"
                    :aria-pressed="fullscreen"
                    @click="fullscreen = !fullscreen"
                >
                    <Icon :name="fullscreen ? 'shrink' : 'expand'" />
                </button>
                <button v-if="fullscreen" type="button" class="btn size-11 !p-0 shadow-md" :class="{ 'text-indigo-600': contacts.pos }" aria-label="Utiliser ma position" @click="onLocate">
                    <Icon name="locate" />
                </button>
            </div>

            <!-- Filtres en plein écran -->
            <div
                v-if="fullscreen"
                class="absolute inset-x-2 bottom-[calc(0.5rem+env(safe-area-inset-bottom))] z-[1000] grid grid-cols-[1fr_1fr] gap-2 rounded-2xl bg-white/95 p-2 shadow-lg dark:bg-slate-900/95"
            >
                <select v-model="domain" class="input !min-h-10 !py-1" aria-label="Domaine">
                    <option value="">Tous les domaines</option>
                    <option v-for="d in domains" :key="d.key" :value="d.key">{{ d.label }}</option>
                </select>
                <select v-model.number="radius" class="input !min-h-10 !py-1" aria-label="Rayon">
                    <option v-for="r in [1, 5, 10, 25, 50, 100]" :key="r" :value="r">{{ r }} km</option>
                    <option :value="0">Illimité</option>
                </select>
            </div>
        </div>
        <p class="muted">{{ hint }}</p>

        <ul class="space-y-2">
            <li v-for="c in items" :key="c.id"><ContactRow :contact="c" /></li>
        </ul>

        <EmptyState v-if="contacts.loaded && !items.length" illustration="map" title="Rien à afficher sur la carte" small>
            <p>Ajoute un lieu à tes contacts, ou élargis le rayon de recherche.</p>
        </EmptyState>
    </div>
</template>
