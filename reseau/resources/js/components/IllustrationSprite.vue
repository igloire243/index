<script setup>
// Formes réutilisées par toutes les illustrations (personnages, plante, téléphone, icônes).
// Monté une seule fois dans l'application ; les scènes y font référence avec <use href="#…">.
</script>

<template>
    <svg width="0" height="0" style="position: absolute" aria-hidden="true" focusable="false">
        <defs>
            <!-- Plante en pot -->
            <symbol id="ill-plant" viewBox="0 0 40 50">
                <path d="M20 31C10 27 6 15 8 6c8 4 12 14 12 25z" fill="#16a34a" />
                <path d="M20 31c10-4 14-16 12-25-8 4-12 14-12 25z" fill="#22c55e" />
                <path d="M20 31c-4-11-4-21 0-29 4 8 4 18 0 29z" fill="#4ade80" />
                <rect x="9" y="30" width="22" height="5" rx="2" fill="#d97706" />
                <path d="M11 35h18l-3 14H14z" fill="#f59e0b" />
            </symbol>

            <!-- Femme, queue-de-cheval, veste indigo, téléphone en main -->
            <symbol id="ill-woman" viewBox="0 0 60 140">
                <path class="ill-pants" d="M21 74h11l-1 56h-9z" />
                <path class="ill-pants-2" d="M32 74h11l1 56H33z" />
                <rect x="17" y="130" width="15" height="6" rx="3" fill="#f8fafc" stroke="#94a3b8" />
                <rect x="33" y="130" width="15" height="6" rx="3" fill="#f8fafc" stroke="#94a3b8" />
                <path d="M19 33q11-5 22 0l3 43H16z" fill="#4f46e5" />
                <rect x="16" y="72" width="28" height="4" fill="#4338ca" />
                <path d="M20 37q-8 15-6 33" stroke="#6366f1" stroke-width="7" fill="none" stroke-linecap="round" />
                <circle cx="14" cy="73" r="3.6" fill="#b07a52" />
                <path d="M40 37q12 8 6 21" stroke="#6366f1" stroke-width="7" fill="none" stroke-linecap="round" />
                <rect x="41" y="50" width="7" height="12" rx="1.6" fill="#1e293b" transform="rotate(-12 44 56)" />
                <circle cx="45" cy="60" r="3.6" fill="#b07a52" />
                <rect x="27" y="24" width="6" height="9" fill="#9a6843" />
                <circle cx="30" cy="16" r="10" fill="#b07a52" />
                <path d="M19.4 15C19 2 41 2 40.6 15 38 9.5 33 8 30 8c-4 0-8 1.6-10.600 7z" fill="#111827" />
                <path d="M39 10c11 0 13 14 7 25 1-10-3-16-8-18z" fill="#111827" />
                <circle cx="27" cy="17" r="1" fill="#111827" />
                <circle cx="33.200" cy="17" r="1" fill="#111827" />
                <path d="M27.500 21.500q2.500 2.300 5 0" stroke="#111827" fill="none" stroke-width="1" stroke-linecap="round" />
            </symbol>

            <!-- Homme, chemise verte, un bras levé -->
            <symbol id="ill-man" viewBox="0 0 60 140">
                <path d="M20 74h11l-1 56h-9z" fill="#64748b" />
                <path d="M31 74h11l2 56H33z" fill="#718096" />
                <rect x="16" y="130" width="15" height="6" rx="3" fill="#92400e" />
                <rect x="33" y="130" width="15" height="6" rx="3" fill="#92400e" />
                <path d="M18 33q12-5 24 0l2 43H16z" fill="#10b981" />
                <path d="M20 37q-8 15-6 33" stroke="#34d399" stroke-width="7" fill="none" stroke-linecap="round" />
                <circle cx="14" cy="73" r="3.6" fill="#8d5524" />
                <path d="M41 37q14-2 12-20" stroke="#34d399" stroke-width="7" fill="none" stroke-linecap="round" />
                <circle cx="52.500" cy="13" r="3.8" fill="#8d5524" />
                <rect x="27" y="24" width="6" height="9" fill="#7a481f" />
                <circle cx="30" cy="16" r="10" fill="#8d5524" />
                <path d="M19.600 14.500C20 4 40 4 40.400 14.500 37 10.500 33 9.500 30 9.500c-3.500 0-7 1-10.400 5z" fill="#0f172a" />
                <circle cx="27" cy="17" r="1" fill="#0f172a" />
                <circle cx="33.200" cy="17" r="1" fill="#0f172a" />
                <path d="M27 21q3 2.800 6 0" stroke="#0f172a" fill="none" stroke-width="1" stroke-linecap="round" />
            </symbol>

            <!-- Sportive, bras levés (étirement), bandeau corail -->
            <symbol id="ill-sport" viewBox="0 0 70 140">
                <path class="ill-pants" d="M23 76h11l-1 54h-9z" />
                <path class="ill-pants-2" d="M36 76h11l1 54H37z" />
                <rect x="19" y="130" width="16" height="6" rx="3" fill="#f43f5e" />
                <rect x="37" y="130" width="16" height="6" rx="3" fill="#f43f5e" />
                <rect x="23" y="58" width="24" height="20" fill="#c68642" />
                <path d="M23 36q12-5 24 0l1 22H22z" fill="#14b8a6" />
                <path d="M24 38Q15 20 33 6" stroke="#c68642" stroke-width="6" fill="none" stroke-linecap="round" />
                <path d="M46 38Q55 20 37 6" stroke="#c68642" stroke-width="6" fill="none" stroke-linecap="round" />
                <circle cx="35" cy="5" r="3.800" fill="#c68642" />
                <rect x="30" y="26" width="10" height="11" fill="#b07a3a" />
                <circle cx="35" cy="19" r="10.500" fill="#c68642" />
                <path d="M24.400 18C24 4 46 4 45.600 18 42 12 38 11 35 11c-4 0-7 1-10.600 7z" fill="#111827" />
                <rect x="24.500" y="14" width="21" height="3.200" rx="1.600" fill="#f43f5e" />
                <circle cx="31.500" cy="21" r="1" fill="#111827" />
                <circle cx="38.500" cy="21" r="1" fill="#111827" />
                <path d="M32 25q3 2.800 6 0" stroke="#111827" fill="none" stroke-width="1" stroke-linecap="round" />
            </symbol>

            <!-- Téléphone avec une liste de contacts -->
            <symbol id="ill-phone" viewBox="0 0 80 150">
                <rect class="ill-phone-body" x="2" y="2" width="76" height="146" rx="13" />
                <rect class="ill-screen" x="7" y="8" width="66" height="134" rx="9" />
                <path d="M7 17a9 9 0 0 1 9-9h48a9 9 0 0 1 9 9v13H7z" fill="#6366f1" />
                <rect x="14" y="16" width="30" height="4" rx="2" fill="#c7d2fe" />
                <rect x="14" y="23" width="20" height="3" rx="1.500" fill="#a5b4fc" />
                <g>
                    <circle cx="20" cy="45" r="6.500" fill="#fbbf24" /><rect class="ill-text" x="31" y="40" width="30" height="3.500" rx="1.700" /><rect class="ill-text" x="31" y="47" width="20" height="3" rx="1.500" />
                    <circle cx="20" cy="66" r="6.500" fill="#34d399" /><rect class="ill-text" x="31" y="61" width="26" height="3.500" rx="1.700" /><rect class="ill-text" x="31" y="68" width="32" height="3" rx="1.500" />
                    <circle cx="20" cy="87" r="6.500" fill="#f472b6" /><rect class="ill-text" x="31" y="82" width="32" height="3.500" rx="1.700" /><rect class="ill-text" x="31" y="89" width="18" height="3" rx="1.500" />
                    <circle cx="20" cy="108" r="6.500" fill="#60a5fa" /><rect class="ill-text" x="31" y="103" width="24" height="3.500" rx="1.700" /><rect class="ill-text" x="31" y="110" width="28" height="3" rx="1.500" />
                </g>
                <rect x="30" y="134" width="20" height="3" rx="1.500" fill="#94a3b8" />
            </symbol>

            <!-- Icônes -->
            <symbol id="ill-pin" viewBox="0 0 24 24">
                <path d="M12 22s8-6.600 8-12.400A8 8 0 0 0 4 9.600C4 15.400 12 22 12 22z" fill="#6366f1" />
                <circle cx="12" cy="9.600" r="3.200" fill="#fff" />
            </symbol>
            <symbol id="ill-heart" viewBox="0 0 24 24">
                <path d="M12 21s-8.500-5.200-8.500-11.500A4.800 4.800 0 0 1 12 6.800a4.800 4.800 0 0 1 8.500 2.700C20.500 15.800 12 21 12 21z" fill="#f43f5e" />
            </symbol>
            <symbol id="ill-star" viewBox="0 0 24 24">
                <path d="M12 2l2.900 6.300 6.800.7-5.100 4.600 1.500 6.800L12 17l-6.100 3.400 1.500-6.800L2.300 9l6.800-.7z" fill="#fbbf24" />
            </symbol>
            <symbol id="ill-check" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="11" fill="#22c55e" />
                <path d="M7 12.500l3.500 3.500L17 9" stroke="#fff" stroke-width="2.600" fill="none" stroke-linecap="round" stroke-linejoin="round" />
            </symbol>
        </defs>
    </svg>
</template>
