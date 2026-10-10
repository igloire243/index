'use strict';

/* ---------- Constantes & état ---------- */

const STORE_KEY = 'reseau.contacts.v1';
const POS_KEY = 'reseau.pos.v1';
const RELANCE_DAYS = 90;

const state = {
  contacts: [],
  pos: null,            // { lat, lng, t }
  view: 'contacts',
  query: '',
  domain: null,         // domaine filtré (clé normalisée) dans la vue Contacts
  sort: 'name',
  editingId: null,
  form: { lat: null, lng: null },
};

const $ = (sel, root = document) => root.querySelector(sel);

/* ---------- Utilitaires ---------- */

// Création de nœuds DOM : tout passe par textContent, jamais par innerHTML.
function h(tag, props, ...kids) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(props || {})) {
    if (v == null || v === false) continue;
    if (k === 'class') el.className = v;
    else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
    else el.setAttribute(k, v === true ? '' : v);
  }
  for (const kid of kids.flat()) {
    if (kid != null && kid !== false) el.append(kid.nodeType ? kid : String(kid));
  }
  return el;
}

const norm = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
const uid = () => (crypto.randomUUID ? crypto.randomUUID() : 'id-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 9));
const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const hasCoords = (c) => Number.isFinite(c.lat) && Number.isFinite(c.lng);

function distKm(a, b) {
  const R = 6371, r = Math.PI / 180;
  const dLat = (b.lat - a.lat) * r, dLng = (b.lng - a.lng) * r;
  const x = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(x));
}

function fmtDist(km) {
  if (km < 1) return `${Math.round(km * 10) * 100} m`.replace(/^0 m$/, '< 100 m');
  if (km < 10) return `${km.toFixed(1).replace('.', ',')} km`;
  return `${Math.round(km).toLocaleString('fr-FR')} km`;
}

function distTo(c) {
  return state.pos && hasCoords(c) ? distKm(state.pos, c) : null;
}

function initials(name) {
  const parts = String(name).trim().split(/\s+/).filter(Boolean);
  return ((parts[0]?.[0] || '?') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
}

function avatar(c, big) {
  let hash = 0;
  for (const ch of c.name) hash = (hash * 31 + ch.charCodeAt(0)) >>> 0;
  return h('div', { class: 'avatar' + (big ? ' big' : ''), style: `background:hsl(${hash % 360} 48% 42%)`, 'aria-hidden': 'true' }, initials(c.name));
}

function toast(msg) {
  const el = $('#toast');
  el.textContent = msg;
  el.hidden = false;
  clearTimeout(toast.t);
  toast.t = setTimeout(() => { el.hidden = true; }, 3200);
}

function daysSince(iso) {
  if (!iso) return null;
  const t = Date.parse(iso + 'T00:00:00');
  return Number.isFinite(t) ? Math.floor((Date.now() - t) / 86400000) : null;
}

const phoneHref = (p) => 'tel:' + String(p).replace(/[^\d+]/g, '');
const smsHref = (p) => 'sms:' + String(p).replace(/[^\d+]/g, '');
const waHref = (p) => 'https://wa.me/' + String(p).replace(/\D/g, '');

/* ---------- Données ---------- */

function parseDomains(input) {
  const seen = new Set();
  const out = [];
  for (const raw of Array.isArray(input) ? input : String(input || '').split(/[,;]/)) {
    const label = String(raw).trim().slice(0, 40);
    const key = norm(label);
    if (key && !seen.has(key)) { seen.add(key); out.push(label); }
  }
  return out;
}

const str = (v, max) => (typeof v === 'string' ? v.trim().slice(0, max) : '');
const num = (v) => (v === null || v === '' || v === undefined ? NaN : Number(v));

// Ne garde que les champs connus, avec le bon type (utilisé pour les imports).
function cleanContact(raw) {
  const name = str(raw && raw.name, 120);
  if (!name) return null;
  const lat = num(raw.lat), lng = num(raw.lng);
  const okGeo = Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
  const now = Date.now();
  return {
    id: str(raw.id, 80) || uid(),
    name,
    phone: str(raw.phone, 40),
    email: str(raw.email, 120),
    domains: parseDomains(raw.domains),
    role: str(raw.role, 120),
    place: str(raw.place, 200),
    lat: okGeo ? lat : null,
    lng: okGeo ? lng : null,
    how: str(raw.how, 200),
    anecdote: str(raw.anecdote, 1000),
    notes: str(raw.notes, 2000),
    favor: raw.favor === 'owed' || raw.favor === 'owe' ? raw.favor : '',
    last: /^\d{4}-\d{2}-\d{2}$/.test(raw.last || '') ? raw.last : '',
    createdAt: Number.isFinite(+raw.createdAt) ? +raw.createdAt : now,
    updatedAt: Number.isFinite(+raw.updatedAt) ? +raw.updatedAt : now,
  };
}

function load() {
  try {
    const data = JSON.parse(localStorage.getItem(STORE_KEY) || '[]');
    state.contacts = (Array.isArray(data) ? data : []).map(cleanContact).filter(Boolean);
  } catch { state.contacts = []; }
  try {
    const p = JSON.parse(localStorage.getItem(POS_KEY) || 'null');
    if (p && Number.isFinite(p.lat) && Number.isFinite(p.lng)) state.pos = p;
  } catch { /* position inconnue */ }
}

function save() {
  try {
    localStorage.setItem(STORE_KEY, JSON.stringify(state.contacts));
  } catch {
    toast('Impossible d’enregistrer (stockage plein ou bloqué).');
  }
}

function allDomains() {
  const map = new Map();
  for (const c of state.contacts) {
    for (const d of c.domains) {
      const k = norm(d);
      const e = map.get(k) || { key: k, label: d, count: 0 };
      e.count++;
      map.set(k, e);
    }
  }
  return [...map.values()].sort((a, b) => a.label.localeCompare(b.label, 'fr'));
}

const inDomain = (c, key) => c.domains.some((d) => norm(d) === key);

function matches(c, q) {
  if (!q) return true;
  const hay = norm([c.name, c.role, c.place, c.how, c.anecdote, c.notes, c.phone, c.email, c.domains.join(' ')].join(' '));
  return q.split(/\s+/).every((w) => hay.includes(w));
}

function sortContacts(list, mode) {
  const byName = (a, b) => a.name.localeCompare(b.name, 'fr', { sensitivity: 'base' });
  const arr = [...list];
  if (mode === 'distance') {
    arr.sort((a, b) => {
      const da = distTo(a), db = distTo(b);
      if (da == null && db == null) return byName(a, b);
      if (da == null) return 1;
      if (db == null) return -1;
      return da - db;
    });
  } else if (mode === 'recent') {
    arr.sort((a, b) => b.createdAt - a.createdAt);
  } else if (mode === 'relance') {
    // Jamais contactés d'abord, puis du plus ancien au plus récent.
    arr.sort((a, b) => (a.last || '').localeCompare(b.last || '') || byName(a, b));
  } else {
    arr.sort(byName);
  }
  return arr;
}

/* ---------- Rendu : lignes de contact ---------- */

function contactRow(c, opts = {}) {
  const d = distTo(c);
  const sub = [c.role, c.domains.join(', ')].filter(Boolean).join(' · ') || c.place;
  return h('li', null,
    h('button', { class: 'row', type: 'button', onclick: () => openDetail(c.id) },
      avatar(c),
      h('div', { class: 'body' },
        h('div', { class: 'name' }, c.name),
        sub ? h('div', { class: 'sub' }, sub) : null),
      d != null && !opts.hideDist ? h('div', { class: 'dist' }, fmtDist(d)) : null));
}

function fillList(ul, items, opts) {
  ul.replaceChildren(...items.map((c) => contactRow(c, opts)));
}

/* ---------- Vue Contacts ---------- */

function renderContacts() {
  const doms = allDomains();
  if (state.domain && !doms.some((d) => d.key === state.domain)) state.domain = null;

  const chips = $('#chips');
  chips.replaceChildren(
    ...doms.map((d) => h('button', {
      class: 'chip' + (state.domain === d.key ? ' on' : ''), type: 'button',
      'aria-pressed': String(state.domain === d.key),
      onclick: () => { state.domain = state.domain === d.key ? null : d.key; renderContacts(); },
    }, d.label, h('span', { class: 'n' }, d.count))));
  chips.hidden = doms.length === 0;

  const q = norm(state.query);
  const items = sortContacts(
    state.contacts.filter((c) => (!state.domain || inDomain(c, state.domain)) && matches(c, q)),
    state.sort);

  $('#count').textContent = `${items.length} contact${items.length > 1 ? 's' : ''}`;
  fillList($('#list'), items);

  const empty = $('#empty');
  empty.hidden = items.length > 0;
  if (items.length === 0) {
    if (state.contacts.length === 0) {
      empty.replaceChildren(
        h('p', null, 'Ton carnet est vide.'),
        h('p', null, 'Ajoute ton premier contact, ou importe ceux de Google Contacts (fichier .vcf).'),
        h('button', { class: 'btn primary', type: 'button', onclick: () => openForm() }, 'Ajouter un contact'),
        ' ',
        h('button', { class: 'btn', type: 'button', onclick: openImport }, 'Importer'));
    } else {
      empty.replaceChildren(h('p', null, 'Aucun résultat.'));
    }
  }
}

/* ---------- Vue Domaines ---------- */

function renderDomains() {
  const box = $('#domains');
  const doms = allDomains();
  if (doms.length === 0) {
    box.replaceChildren(h('div', { class: 'empty' }, 'Aucun domaine pour l’instant. Ajoute des domaines à tes contacts (juridique, santé, transport…).'));
    return;
  }
  const open = new Set([...box.querySelectorAll('details[open]')].map((el) => el.dataset.key));
  box.replaceChildren(...doms.map((d) => {
    const ul = h('ul', { class: 'list' });
    fillList(ul, sortContacts(state.contacts.filter((c) => inDomain(c, d.key)), state.pos ? 'distance' : 'name'));
    return h('details', { class: 'dom', 'data-key': d.key, open: open.has(d.key) },
      h('summary', null, d.label, h('span', { class: 'n' }, `${d.count} contact${d.count > 1 ? 's' : ''}`)),
      ul);
  }));
}

/* ---------- Vue Carte ---------- */

let map = null;
let markersLayer = null;
let meLayer = null;

function ensureMap() {
  if (map || typeof L === 'undefined') return;
  L.Icon.Default.imagePath = 'vendor/leaflet/images/';
  map = L.map('map', { zoomControl: true }).setView([0, 20], 2);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map);
  markersLayer = L.layerGroup().addTo(map);
  meLayer = L.layerGroup().addTo(map);
}

function mapItems() {
  const dom = $('#map-domain').value;
  const radius = Number($('#map-radius').value);
  let items = state.contacts.filter((c) => hasCoords(c) && (!dom || inDomain(c, dom)));
  if (state.pos && radius > 0) items = items.filter((c) => distTo(c) <= radius);
  return sortContacts(items, state.pos ? 'distance' : 'name');
}

function renderMapDomains() {
  const sel = $('#map-domain');
  const current = sel.value;
  sel.replaceChildren(
    h('option', { value: '' }, 'Tous'),
    ...allDomains().map((d) => h('option', { value: d.key }, d.label)));
  sel.value = [...sel.options].some((o) => o.value === current) ? current : '';
}

function renderMap(fit) {
  ensureMap();
  const items = mapItems();
  const radius = Number($('#map-radius').value);

  fillList($('#nearby'), items);
  const noGeo = state.contacts.filter((c) => !hasCoords(c)).length;
  $('#map-hint').textContent = [
    state.pos ? `${items.length} contact${items.length > 1 ? 's' : ''} ${radius > 0 ? `dans ${radius} km` : 'sur la carte'}` : 'Active ta position (icône en haut) pour voir qui est près de toi.',
    noGeo ? `${noGeo} sans localisation.` : '',
  ].filter(Boolean).map((t) => (/[.]$/.test(t) ? t : t + '.')).join(' ');

  if (!map) {
    $('#map').replaceChildren(h('p', { class: 'empty' }, 'La carte n’a pas pu se charger.'));
    return;
  }
  markersLayer.clearLayers();
  meLayer.clearLayers();

  const pts = [];
  for (const c of items) {
    const d = distTo(c);
    const pop = h('div', null,
      h('b', null, c.name), h('br'),
      c.role ? [c.role, h('br')] : null,
      d != null ? [fmtDist(d), h('br')] : null,
      h('a', { href: '#', onclick: (e) => { e.preventDefault(); openDetail(c.id); } }, 'Ouvrir la fiche'));
    L.marker([c.lat, c.lng]).bindPopup(pop).addTo(markersLayer);
    pts.push([c.lat, c.lng]);
  }
  if (state.pos) {
    L.circleMarker([state.pos.lat, state.pos.lng], { radius: 8, color: '#fff', weight: 2, fillColor: '#2563eb', fillOpacity: 1 })
      .bindTooltip('Moi').addTo(meLayer);
    if (radius > 0) L.circle([state.pos.lat, state.pos.lng], { radius: radius * 1000, weight: 1, color: '#2563eb', fillOpacity: 0.06 }).addTo(meLayer);
    pts.push([state.pos.lat, state.pos.lng]);
  }
  if (fit && pts.length) {
    if (pts.length === 1) map.setView(pts[0], 13);
    else map.fitBounds(pts, { padding: [30, 30], maxZoom: 15 });
  }
}

/* ---------- Navigation ---------- */

function show(view) {
  state.view = view;
  for (const name of ['contacts', 'map', 'domains']) $('#view-' + name).hidden = name !== view;
  document.querySelectorAll('.tabs button').forEach((b) => b.classList.toggle('active', b.dataset.view === view));
  window.scrollTo(0, 0);
  render(true);
}

function render(fit) {
  if (state.view === 'contacts') renderContacts();
  else if (state.view === 'domains') renderDomains();
  else {
    renderMapDomains();
    renderMap(fit);
    if (map) setTimeout(() => map.invalidateSize(), 0);
  }
  $('#btn-locate').classList.toggle('on', !!state.pos);
}

/* ---------- Géolocalisation ---------- */

function locate(quiet) {
  if (!navigator.geolocation) { if (!quiet) toast('Géolocalisation non disponible.'); return Promise.resolve(null); }
  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      (p) => {
        state.pos = { lat: p.coords.latitude, lng: p.coords.longitude, t: Date.now() };
        try { localStorage.setItem(POS_KEY, JSON.stringify(state.pos)); } catch { /* ignoré */ }
        resolve(state.pos);
      },
      (err) => {
        if (!quiet) toast(err.code === 1 ? 'Autorise la localisation dans ton navigateur.' : 'Position introuvable.');
        resolve(null);
      },
      { enableHighAccuracy: false, timeout: 12000, maximumAge: 60000 });
  });
}

async function geocode(q) {
  const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&accept-language=fr&q=' + encodeURIComponent(q);
  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error('geocode ' + res.status);
  const [hit] = await res.json();
  return hit ? { lat: parseFloat(hit.lat), lng: parseFloat(hit.lon) } : null;
}

async function reverseGeocode(lat, lng) {
  const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=14&accept-language=fr&lat=${lat}&lon=${lng}`;
  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error('reverse ' + res.status);
  const data = await res.json();
  const a = data.address || {};
  return [a.suburb || a.neighbourhood, a.city || a.town || a.village || a.municipality, a.country].filter(Boolean).join(', ');
}

/* ---------- Fiche détail ---------- */

function openDetail(id) {
  const c = state.contacts.find((x) => x.id === id);
  if (!c) return;
  const d = distTo(c);
  const since = daysSince(c.last);
  const block = (label, value) => (value ? h('div', { class: 'd-block' }, h('b', null, label), h('p', null, value)) : null);

  const actions = [
    c.phone ? h('a', { class: 'btn primary', href: phoneHref(c.phone) }, 'Appeler') : null,
    c.phone ? h('a', { class: 'btn', href: smsHref(c.phone) }, 'SMS') : null,
    c.phone ? h('a', { class: 'btn', href: waHref(c.phone), target: '_blank', rel: 'noopener' }, 'WhatsApp') : null,
    c.email ? h('a', { class: 'btn', href: 'mailto:' + encodeURI(c.email) }, 'Email') : null,
    hasCoords(c) ? h('a', { class: 'btn', href: `https://www.google.com/maps/dir/?api=1&destination=${c.lat},${c.lng}`, target: '_blank', rel: 'noopener' }, 'Itinéraire') : null,
  ];

  const favorText = { owed: 'Cette personne te doit un service', owe: 'Tu lui dois un service' }[c.favor];
  const lastText = c.last
    ? `${new Date(c.last + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}${since != null ? ` (il y a ${since} j)` : ''}`
    : 'Jamais noté';

  $('#detail').replaceChildren(...[
    h('div', { class: 'd-head' }, avatar(c, true),
      h('div', null, h('h2', null, c.name), c.role ? h('div', { class: 'muted' }, c.role) : null)),
    h('div', null,
      ...c.domains.map((x) => h('span', { class: 'tag' }, x)),
      favorText ? h('span', { class: 'tag warn' }, favorText) : null,
      since != null && since >= RELANCE_DAYS ? h('span', { class: 'tag warn' }, 'À relancer') : null),
    actions.some(Boolean) ? h('div', { class: 'd-actions' }, actions) : null,
    block('Téléphone', c.phone),
    block('Email', c.email),
    block('Lieu', c.place ? c.place + (d != null ? ` — à ${fmtDist(d)}` : '') : ''),
    block('Comment on s’est connus', c.how),
    block('Anecdote', c.anecdote),
    block('Notes', c.notes),
    block('Dernier contact', lastText),
    h('div', { class: 'd-foot' },
      h('button', { class: 'btn', type: 'button', onclick: () => markContacted(c.id) }, 'Contacté aujourd’hui'),
      h('span', null,
        h('button', { class: 'btn', type: 'button', onclick: () => { $('#dlg-detail').close(); openForm(c.id); } }, 'Modifier'), ' ',
        h('button', { class: 'btn danger', type: 'button', onclick: () => removeContact(c.id) }, 'Supprimer'))),
    h('button', { class: 'btn', type: 'button', onclick: () => $('#dlg-detail').close() }, 'Fermer'),
  ].filter(Boolean));

  if (!$('#dlg-detail').open) $('#dlg-detail').showModal();
}

function markContacted(id) {
  const c = state.contacts.find((x) => x.id === id);
  if (!c) return;
  c.last = today();
  c.updatedAt = Date.now();
  save();
  render();
  openDetail(id);
  toast('Noté : contacté aujourd’hui.');
}

function removeContact(id) {
  const c = state.contacts.find((x) => x.id === id);
  if (!c || !confirm(`Supprimer ${c.name} ?`)) return;
  state.contacts = state.contacts.filter((x) => x.id !== id);
  save();
  $('#dlg-detail').close();
  render();
  toast('Contact supprimé.');
}

/* ---------- Formulaire ---------- */

const F = (id) => $('#f-' + id);

function setGeoStatus() {
  const { lat, lng } = state.form;
  F('geo-status').textContent = Number.isFinite(lat) ? `Localisé (${lat.toFixed(3)}, ${lng.toFixed(3)})` : 'Pas encore localisé';
}

function renderSuggest() {
  const current = new Set(parseDomains(F('domains').value).map(norm));
  $('#f-suggest').replaceChildren(...allDomains().map((d) => h('button', {
    class: 'chip' + (current.has(d.key) ? ' on' : ''), type: 'button',
    onclick: () => {
      const list = parseDomains(F('domains').value).filter((x) => norm(x) !== d.key);
      if (!current.has(d.key)) list.push(d.label);
      F('domains').value = list.join(', ');
      renderSuggest();
    },
  }, d.label)));
}

function openForm(id) {
  const c = id ? state.contacts.find((x) => x.id === id) : null;
  state.editingId = c ? c.id : null;
  $('#form-title').textContent = c ? 'Modifier le contact' : 'Nouveau contact';
  F('name').value = c ? c.name : '';
  F('phone').value = c ? c.phone : '';
  F('email').value = c ? c.email : '';
  F('domains').value = c ? c.domains.join(', ') : '';
  F('role').value = c ? c.role : '';
  F('place').value = c ? c.place : '';
  F('how').value = c ? c.how : '';
  F('anecdote').value = c ? c.anecdote : '';
  F('notes').value = c ? c.notes : '';
  F('favor').value = c ? c.favor : '';
  F('last').value = c ? c.last : '';
  state.form = c && hasCoords(c) ? { lat: c.lat, lng: c.lng } : { lat: null, lng: null };
  setGeoStatus();
  renderSuggest();
  $('#dlg-form').showModal();
  if (!c) F('name').focus();
}

async function geocodeForm(announce) {
  const q = F('place').value.trim();
  if (!q) { if (announce) toast('Écris d’abord un lieu.'); return false; }
  F('geo-status').textContent = 'Recherche…';
  try {
    const hit = await geocode(q);
    if (!hit) { state.form = { lat: null, lng: null }; setGeoStatus(); if (announce) toast('Lieu introuvable. Essaie avec la ville.'); return false; }
    state.form = hit;
    setGeoStatus();
    return true;
  } catch {
    setGeoStatus();
    if (announce) toast('Recherche impossible (connexion ?).');
    return false;
  }
}

async function useHere() {
  F('geo-status').textContent = 'Recherche de ta position…';
  const p = await locate();
  if (!p) { setGeoStatus(); return; }
  state.form = { lat: p.lat, lng: p.lng };
  setGeoStatus();
  render();
  if (!F('place').value.trim()) {
    try { F('place').value = await reverseGeocode(p.lat, p.lng); } catch { /* le lieu reste vide */ }
  }
}

async function submitForm(e) {
  e.preventDefault();
  const name = F('name').value.trim();
  if (!name) return;
  const submit = $('#form button[type=submit]');
  submit.disabled = true;

  // Lieu renseigné mais pas encore localisé : on tente de le faire automatiquement.
  if (F('place').value.trim() && !Number.isFinite(state.form.lat)) await geocodeForm(false);

  const old = state.editingId ? state.contacts.find((x) => x.id === state.editingId) : null;
  const now = Date.now();
  const c = cleanContact({
    id: old ? old.id : uid(),
    name,
    phone: F('phone').value,
    email: F('email').value,
    domains: F('domains').value,
    role: F('role').value,
    place: F('place').value,
    lat: state.form.lat,
    lng: state.form.lng,
    how: F('how').value,
    anecdote: F('anecdote').value,
    notes: F('notes').value,
    favor: F('favor').value,
    last: F('last').value,
    createdAt: old ? old.createdAt : now,
    updatedAt: now,
  });
  if (old) state.contacts[state.contacts.indexOf(old)] = c; else state.contacts.push(c);
  save();
  submit.disabled = false;
  $('#dlg-form').close();
  render(true);
  toast(old ? 'Contact mis à jour.' : 'Contact ajouté.');
}

/* ---------- Import / export ---------- */

function parseVcf(text) {
  const lines = text.replace(/\r\n?/g, '\n').replace(/\n[ \t]/g, '').split('\n');
  const unesc = (s) => s.replace(/\\n/gi, '\n').replace(/\\([,;\\])/g, '$1').trim();
  const out = [];
  let cur = null;
  for (const line of lines) {
    if (/^BEGIN:VCARD/i.test(line)) { cur = {}; continue; }
    if (/^END:VCARD/i.test(line)) { if (cur && cur.name) out.push(cur); cur = null; continue; }
    if (!cur) continue;
    const i = line.indexOf(':');
    if (i < 0) continue;
    const key = line.slice(0, i).split(';')[0].toUpperCase().replace(/^ITEM\d+\./, '');
    const raw = line.slice(i + 1);
    const val = unesc(raw);
    if (key === 'FN' && val) cur.name = val;
    else if (key === 'N' && !cur.name) { const p = raw.split(';'); cur.name = unesc([p[1], p[0]].filter(Boolean).join(' ')); }
    else if (key === 'TEL' && !cur.phone) cur.phone = val;
    else if (key === 'EMAIL' && !cur.email) cur.email = val;
    else if (key === 'ORG') cur.org = unesc(raw.split(';').filter(Boolean).join(' '));
    else if (key === 'TITLE') cur.title = val;
    else if (key === 'NOTE') cur.notes = val;
    else if (key === 'ADR' && !cur.place) {
      const p = raw.split(';').map(unesc);
      cur.place = [p[2], p[3], p[6]].filter(Boolean).join(', ');
    }
  }
  return out.map((v) => ({ ...v, role: [v.title, v.org].filter(Boolean).join(' — ') }));
}

function mergeImported(rawList) {
  let added = 0, updated = 0;
  const byId = new Map(state.contacts.map((c) => [c.id, c]));
  const known = new Set(state.contacts.map((c) => norm(c.name) + '|' + String(c.phone).replace(/\D/g, '')));
  for (const raw of rawList) {
    const c = cleanContact(raw);
    if (!c) continue;
    const existing = byId.get(c.id);
    if (existing) {
      if (c.updatedAt > existing.updatedAt) { state.contacts[state.contacts.indexOf(existing)] = c; byId.set(c.id, c); updated++; }
      continue;
    }
    const sig = norm(c.name) + '|' + c.phone.replace(/\D/g, '');
    if (known.has(sig)) continue; // même personne déjà présente
    known.add(sig);
    state.contacts.push(c);
    byId.set(c.id, c);
    added++;
  }
  save();
  return { added, updated };
}

async function importFile(file) {
  if (!file) return;
  if (file.size > 20 * 1024 * 1024) { toast('Fichier trop volumineux.'); return; }
  try {
    const text = await file.text();
    let list;
    if (/\.vcf$/i.test(file.name) || /^\s*BEGIN:VCARD/i.test(text)) {
      list = parseVcf(text).map((v) => ({ ...v, id: undefined }));
    } else {
      const data = JSON.parse(text);
      list = Array.isArray(data) ? data : data && Array.isArray(data.contacts) ? data.contacts : null;
      if (!list) throw new Error('format');
    }
    const { added, updated } = mergeImported(list);
    $('#dlg-menu').close();
    render(true);
    toast(`${added} ajouté${added > 1 ? 's' : ''}${updated ? `, ${updated} mis à jour` : ''}.`);
  } catch {
    toast('Fichier illisible : choisis un .json de sauvegarde ou un .vcf.');
  }
}

function exportJson() {
  const blob = new Blob([JSON.stringify({ app: 'reseau', version: 1, exportedAt: new Date().toISOString(), contacts: state.contacts }, null, 2)], { type: 'application/json' });
  const a = h('a', { href: URL.createObjectURL(blob), download: `reseau-${today()}.json` });
  document.body.append(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 2000);
  toast('Sauvegarde téléchargée.');
}

function openImport() {
  $('#file').value = '';
  $('#file').click();
}

/* ---------- Initialisation ---------- */

function init() {
  load();

  document.querySelectorAll('.tabs button').forEach((b) => b.addEventListener('click', () => show(b.dataset.view)));
  $('#fab').addEventListener('click', () => openForm());
  $('#btn-locate').addEventListener('click', async () => {
    const p = await locate();
    if (p) { toast('Position mise à jour.'); render(true); }
  });
  $('#btn-menu').addEventListener('click', () => $('#dlg-menu').showModal());

  $('#search').addEventListener('input', (e) => { state.query = e.target.value; renderContacts(); });
  $('#sort').addEventListener('change', async (e) => {
    state.sort = e.target.value;
    if (state.sort === 'distance' && !state.pos) await locate();
    renderContacts();
  });
  $('#map-domain').addEventListener('change', () => renderMap(true));
  $('#map-radius').addEventListener('change', () => renderMap(true));

  // Formulaire
  $('#form').addEventListener('submit', submitForm);
  $('#f-cancel').addEventListener('click', () => $('#dlg-form').close());
  $('#f-geocode').addEventListener('click', () => geocodeForm(true));
  $('#f-here').addEventListener('click', useHere);
  F('place').addEventListener('input', () => { state.form = { lat: null, lng: null }; setGeoStatus(); });
  F('domains').addEventListener('input', renderSuggest);

  // Menu
  $('#m-export').addEventListener('click', exportJson);
  $('#m-import').addEventListener('click', openImport);
  $('#file').addEventListener('change', (e) => importFile(e.target.files[0]));
  $('#m-close').addEventListener('click', () => $('#dlg-menu').close());
  $('#m-wipe').addEventListener('click', () => {
    if (!state.contacts.length) return;
    if (confirm('Effacer TOUS les contacts de cet appareil ? Pense à exporter une sauvegarde avant.')) {
      state.contacts = [];
      save();
      $('#dlg-menu').close();
      render();
    }
  });

  // Fermer une fenêtre en touchant le fond
  document.querySelectorAll('dialog').forEach((dlg) => {
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
  });

  render(true);

  if (navigator.storage && navigator.storage.persist) navigator.storage.persist().catch(() => {});
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  }
}

init();
