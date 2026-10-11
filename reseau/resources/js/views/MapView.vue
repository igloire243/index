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
import { MODES, advanceStep, distanceMeters, distanceToRoute, fetchRoute, fmtDuration, fmtRouteDistance } from '../lib/routing';
import { contacts, distTo, loadContacts, locate, openDetail, startRoute } from '../stores/contacts';
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
let routeLayer;

/* ---------- Carte et contacts ---------- */

const domains = computed(() => allDomains(contacts.list));
const withoutPlace = computed(() => contacts.list.filter((c) => !hasCoords(c)).length);
const target = computed(() => contacts.list.find((c) => c.id === contacts.routeTo) || null);

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
    const link = (label, onClick) => {
        const a = document.createElement('a');
        a.href = '#';
        a.textContent = label;
        a.className = 'block mt-1';
        a.addEventListener('click', (e) => {
            e.preventDefault();
            onClick();
        });
        return a;
    };
    el.append(document.createElement('br'), link('Ouvrir la fiche', () => openDetail(c.id)), link('Itinéraire', () => startRoute(c.id)));
    return el;
}

function draw(fit) {
    if (!map) return;
    markers.clearLayers();
    me.clearLayers();
    const pts = [];
    // En mode itinéraire, seule la destination est montrée (par le tracé) : pas les autres contacts.
    for (const c of target.value ? [] : items.value) {
        L.marker([c.lat, c.lng]).bindPopup(popup(c)).addTo(markers);
        pts.push([c.lat, c.lng]);
    }
    if (contacts.pos) {
        L.circleMarker([contacts.pos.lat, contacts.pos.lng], { radius: 8, color: '#fff', weight: 2, fillColor: '#2563eb', fillOpacity: 1 }).bindTooltip('Moi').addTo(me);
        if (radius.value > 0 && !target.value) L.circle([contacts.pos.lat, contacts.pos.lng], { radius: radius.value * 1000, weight: 1, color: '#2563eb', fillOpacity: 0.06 }).addTo(me);
        pts.push([contacts.pos.lat, contacts.pos.lng]);
    }
    if (fit && pts.length && !target.value) {
        if (pts.length === 1) map.setView(pts[0], 13);
        else map.fitBounds(pts, { padding: [30, 30], maxZoom: 15 });
    }
}

/* ---------- Itinéraire dans l'application ---------- */

const mode = ref('driving');
const route = ref(null);
const routeError = ref('');
const routeLoading = ref(false);
const stepsOpen = ref(false);
const stepIndex = ref(0);
const live = ref(false);
let controller = null;
let watchId = null;
let wakeLock = null;
let lastReroute = 0;
let gpsLost = false;

const nextStep = computed(() => (route.value ? route.value.steps[Math.min(stepIndex.value + 1, route.value.steps.length - 1)] : null));
const toNext = computed(() => (contacts.pos && nextStep.value ? distanceMeters([contacts.pos.lat, contacts.pos.lng], nextStep.value.location) : null));
const remaining = computed(() => (contacts.pos && target.value ? distanceMeters([contacts.pos.lat, contacts.pos.lng], [target.value.lat, target.value.lng]) : null));
const externalUrl = computed(() => (target.value ? `https://www.google.com/maps/dir/?api=1&destination=${target.value.lat},${target.value.lng}` : '#'));

function drawRoute() {
    routeLayer.clearLayers();
    if (!target.value) return;
    const dest = [target.value.lat, target.value.lng];
    L.marker(dest).bindTooltip(target.value.name, { permanent: false }).addTo(routeLayer);
    const from = contacts.pos ? [contacts.pos.lat, contacts.pos.lng] : null;
    if (route.value) {
        L.polyline(route.value.coords, { color: '#fff', weight: 9, opacity: 0.9, lineCap: 'round' }).addTo(routeLayer);
        L.polyline(route.value.coords, { color: '#4f46e5', weight: 5, opacity: 1, lineCap: 'round', className: 'route-line' }).addTo(routeLayer);
    } else if (from) {
        // Calcul impossible : on montre au moins la direction à vol d'oiseau.
        L.polyline([from, dest], { color: '#4f46e5', weight: 3, dashArray: '6 8' }).addTo(routeLayer);
    }
}

function fitRoute() {
    if (!map || !target.value) return;
    const pts = route.value ? route.value.coords : [[target.value.lat, target.value.lng], ...(contacts.pos ? [[contacts.pos.lat, contacts.pos.lng]] : [])];
    map.fitBounds(pts, { paddingTopLeft: [24, 24], paddingBottomRight: [24, 260], maxZoom: 17 });
}

async function loadRoute({ refit = true } = {}) {
    if (!target.value) return;
    controller?.abort();
    controller = new AbortController();
    routeLoading.value = true;
    routeError.value = '';
    try {
        if (!contacts.pos && !(await locate())) {
            routeError.value = contacts.posError || 'Position indisponible.';
            route.value = null;
        } else {
            route.value = await fetchRoute(contacts.pos, target.value, mode.value, controller.signal);
            stepIndex.value = 0;
        }
    } catch (e) {
        if (e.name === 'AbortError') return;
        route.value = null;
        routeError.value = e.message || 'Itinéraire indisponible.';
    } finally {
        routeLoading.value = false;
    }
    drawRoute();
    if (refit) fitRoute();
}

async function beginRoute() {
    if (!target.value) return;
    if (!hasCoords(target.value)) {
        toast('Ce contact n’a pas de localisation.', 'error');
        contacts.routeTo = null;
        return;
    }
    fullscreen.value = true;
    await nextTick();
    map?.invalidateSize();
    draw(false);
    await loadRoute();
}

function endRoute() {
    stopLive();
    controller?.abort();
    contacts.routeTo = null;
    route.value = null;
    routeError.value = '';
    stepsOpen.value = false;
    routeLayer?.clearLayers();
    fullscreen.value = false;
}

async function stopLive() {
    live.value = false;
    if (watchId != null) navigator.geolocation.clearWatch(watchId);
    watchId = null;
    try {
        await wakeLock?.release();
    } catch {
        /* ignoré */
    }
    wakeLock = null;
}

async function startLive() {
    if (!navigator.geolocation || !route.value) return;
    live.value = true;
    stepsOpen.value = false;
    try {
        wakeLock = (await navigator.wakeLock?.request('screen')) || null; // garde l'écran allumé pendant le trajet
    } catch {
        /* facultatif */
    }
    watchId = navigator.geolocation.watchPosition(
        (p) => {
            gpsLost = false;
            contacts.pos = { lat: p.coords.latitude, lng: p.coords.longitude, t: Date.now() };
            const here = [p.coords.latitude, p.coords.longitude];
            stepIndex.value = advanceStep(route.value.steps, stepIndex.value, here);
            map.panTo(here, { animate: true });
            if (remaining.value != null && remaining.value < 30) {
                toast('Tu es arrivé !');
                stopLive();
            } else if (distanceToRoute(route.value.coords, here) > 80 && Date.now() - lastReroute > 15000) {
                lastReroute = Date.now();
                loadRoute({ refit: false }); // hors tracé : on recalcule depuis la position actuelle
            }
        },
        (err) => {
            // Seul un refus de permission arrête le suivi ; un signal perdu (tunnel, immeuble) reprend tout seul.
            if (err.code === 1) {
                toast('Localisation refusée : le suivi est arrêté.', 'error');
                stopLive();
            } else if (!gpsLost) {
                gpsLost = true;
                toast('Signal GPS perdu : le suivi reprendra dès que possible.');
            }
        },
        { enableHighAccuracy: true, maximumAge: 2000, timeout: 20000 },
    );
}

watch(() => contacts.routeTo, (id) => id && map && beginRoute());
watch(mode, () => target.value && loadRoute());

/* ---------- Cycle de vie ---------- */

// Plein écran : la carte recouvre tout l'écran (superposition CSS, qui marche aussi sur iPhone) ; Échap ou le bouton la ramènent.
const onKey = (e) => {
    if (e.key !== 'Escape' || !fullscreen.value || document.querySelector('dialog[open]')) return;
    if (target.value) endRoute();
    else fullscreen.value = false;
};
window.addEventListener('keydown', onKey);

watch(fullscreen, async (on) => {
    document.documentElement.style.overflow = on ? 'hidden' : '';
    await nextTick();
    map?.invalidateSize();
    if (target.value) fitRoute();
    else draw(true);
});

onMounted(async () => {
    map = L.map(mapEl.value, { doubleClickZoom: false }).setView([0, 20], 2);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);
    markers = L.layerGroup().addTo(map);
    me = L.layerGroup().addTo(map);
    routeLayer = L.layerGroup().addTo(map);
    try {
        await loadContacts();
    } catch (e) {
        toast(e.message, 'error');
    }
    draw(true);
    if (contacts.routeTo) beginRoute();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    document.documentElement.style.overflow = '';
    stopLive();
    controller?.abort();
    contacts.routeTo = null;
    map?.remove();
});

watch([items, () => contacts.pos], () => draw(false));
watch([domain, radius], () => draw(true));

async function onLocate() {
    if (await locate()) {
        toast('Position mise à jour.');
        if (target.value) loadRoute({ refit: false });
        else draw(true);
    } else toast(contacts.posError, 'error');
}
</script>

<template>
    <div class="space-y-3">
        <div v-show="!target" class="grid grid-cols-[1fr_1fr_auto] items-end gap-2">
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
                    v-if="!target"
                    type="button"
                    class="btn size-11 !p-0 shadow-md"
                    :aria-label="fullscreen ? 'Quitter le plein écran' : 'Afficher la carte en plein écran'"
                    :aria-pressed="fullscreen"
                    @click="fullscreen = !fullscreen"
                >
                    <Icon :name="fullscreen ? 'shrink' : 'expand'" />
                </button>
                <button v-else type="button" class="btn size-11 !p-0 shadow-md" aria-label="Fermer l’itinéraire" @click="endRoute"><Icon name="x" /></button>
                <button v-if="fullscreen" type="button" class="btn size-11 !p-0 shadow-md" :class="{ 'text-indigo-600': contacts.pos }" aria-label="Utiliser ma position" @click="onLocate">
                    <Icon name="locate" />
                </button>
            </div>

            <!-- Consigne en direct -->
            <div v-if="live && nextStep" class="absolute inset-x-14 top-[calc(0.5rem+env(safe-area-inset-top))] z-[1000] rounded-2xl bg-indigo-600 p-3 text-white shadow-lg" role="status" aria-live="polite">
                <div class="text-lg leading-tight font-bold">{{ nextStep.text }}</div>
                <div v-if="toNext != null" class="text-sm opacity-90">dans {{ fmtRouteDistance(toNext) }}</div>
            </div>

            <!-- Filtres en plein écran (hors itinéraire) -->
            <div
                v-if="fullscreen && !target"
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

            <!-- Panneau d'itinéraire -->
            <section
                v-if="target"
                class="absolute inset-x-2 bottom-[calc(0.5rem+env(safe-area-inset-bottom))] z-[1000] max-h-[55dvh] space-y-2 overflow-y-auto rounded-2xl bg-white/95 p-3 shadow-lg dark:bg-slate-900/95"
                aria-label="Itinéraire"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="muted">Itinéraire vers</div>
                        <div class="truncate font-semibold">{{ target.name }}</div>
                    </div>
                    <div v-if="route" class="shrink-0 text-right">
                        <div class="text-xl font-bold">{{ fmtDuration(route.duration) }}</div>
                        <div class="muted">{{ fmtRouteDistance(route.distance) }}</div>
                    </div>
                </div>

                <div class="flex gap-2" role="radiogroup" aria-label="Mode de déplacement">
                    <button
                        v-for="(m, key) in MODES"
                        :key="key"
                        type="button"
                        role="radio"
                        :aria-checked="mode === key"
                        class="chip flex-1 justify-center"
                        :class="{ 'chip-on': mode === key }"
                        @click="mode = key"
                    >
                        <span aria-hidden="true">{{ m.emoji }}</span> {{ m.label }}
                    </button>
                </div>

                <p v-if="routeLoading" class="muted" role="status">Calcul de l’itinéraire…</p>
                <div v-else-if="routeError" class="space-y-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200" role="alert">
                    <p>{{ routeError }}</p>
                    <p v-if="remaining != null">À vol d’oiseau : {{ fmtRouteDistance(remaining) }}.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm" @click="loadRoute()">Réessayer</button>
                        <a class="btn btn-sm" :href="externalUrl" target="_blank" rel="noopener">Ouvrir dans une autre application</a>
                    </div>
                </div>

                <template v-if="route && !routeLoading">
                    <div class="flex gap-2">
                        <button v-if="!live" type="button" class="btn btn-primary flex-1" @click="startLive"><Icon name="navigation" :size="18" /> Démarrer</button>
                        <button v-else type="button" class="btn flex-1" @click="stopLive">Arrêter le suivi</button>
                        <button type="button" class="btn" :aria-expanded="stepsOpen" @click="stepsOpen = !stepsOpen">Étapes ({{ route.steps.length }})</button>
                    </div>
                    <ol v-if="stepsOpen" class="space-y-1.5">
                        <li
                            v-for="(s, i) in route.steps"
                            :key="i"
                            class="flex items-start gap-3 rounded-xl p-2.5 text-sm"
                            :class="i === stepIndex && live ? 'bg-indigo-50 dark:bg-indigo-950/60' : 'bg-slate-50 dark:bg-slate-800'"
                        >
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-slate-200 text-xs font-semibold dark:bg-slate-700">{{ i + 1 }}</span>
                            <span class="min-w-0 flex-1">{{ s.text }}</span>
                            <span v-if="s.distance > 0" class="muted shrink-0">{{ fmtRouteDistance(s.distance) }}</span>
                        </li>
                    </ol>
                </template>

                <button type="button" class="btn w-full" @click="endRoute">Fermer l’itinéraire</button>
            </section>
        </div>

        <p v-show="!target" class="muted">{{ hint }}</p>

        <ul v-show="!target" class="space-y-2">
            <li v-for="c in items" :key="c.id"><ContactRow :contact="c" /></li>
        </ul>

        <EmptyState v-if="contacts.loaded && !items.length && !target" illustration="map" title="Rien à afficher sur la carte" small>
            <p>Ajoute un lieu à tes contacts, ou élargis le rayon de recherche.</p>
        </EmptyState>
    </div>
</template>
