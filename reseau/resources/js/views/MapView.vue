<script setup>
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import icon2x from 'leaflet/dist/images/marker-icon-2x.png';
import icon from 'leaflet/dist/images/marker-icon.png';
import shadow from 'leaflet/dist/images/marker-shadow.png';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ContactRow from '../components/ContactRow.vue';
import Icon from '../components/Icon.vue';
import { allDomains, fmtDist, hasCoords, inDomain } from '../lib/contacts';
import { contacts, distTo, loadContacts, locate, openDetail } from '../stores/contacts';
import { toast } from '../stores/session';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl: icon, iconRetinaUrl: icon2x, shadowUrl: shadow });

const mapEl = ref(null);
const domain = ref('');
const radius = ref(50);
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
    map = L.map(mapEl.value).setView([0, 20], 2);
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

onBeforeUnmount(() => map?.remove());

watch([items, () => contacts.pos], () => draw(false));
watch([domain, radius], () => draw(true));

async function onLocate() {
    if (await locate()) {
        toast('Position mise à jour.');
        draw(true);
    } else toast('Position indisponible : autorise la localisation.', 'error');
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

        <div ref="mapEl" class="z-0 h-[48vh] min-h-72 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800" role="application" aria-label="Carte de mes contacts" />
        <p class="muted">{{ hint }}</p>

        <ul class="space-y-2">
            <li v-for="c in items" :key="c.id"><ContactRow :contact="c" /></li>
        </ul>
    </div>
</template>
