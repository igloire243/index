const COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#38bdf8'];

/** Petite pluie de confettis (pas d'animation si l'utilisateur préfère moins de mouvement). */
export function confetti(count = 40) {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
    const layer = document.createElement('div');
    layer.className = 'confetti-layer';
    layer.setAttribute('aria-hidden', 'true');
    for (let i = 0; i < count; i++) {
        const piece = document.createElement('span');
        piece.style.left = 15 + Math.random() * 70 + '%';
        piece.style.background = COLORS[i % COLORS.length];
        piece.style.setProperty('--dx', (Math.random() - 0.5) * 220 + 'px');
        piece.style.setProperty('--rot', (Math.random() - 0.5) * 900 + 'deg');
        piece.style.setProperty('--dur', 1.3 + Math.random() * 0.9 + 's');
        piece.style.setProperty('--delay', Math.random() * 0.25 + 's');
        layer.append(piece);
    }
    document.body.append(layer);
    setTimeout(() => layer.remove(), 2800);
}
