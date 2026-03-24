const toastWrap = document.getElementById('toast-wrap');

export function toast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span class="material-icons-round">${type === 'success' ? 'check_circle' : 'error_outline'}</span>${msg}`;
    toastWrap.appendChild(el);
    setTimeout(() => {
        el.style.animation = 'toastOut 0.25s ease forwards';
        el.addEventListener('animationend', () => el.remove());
    }, 2800);
}

export function formatDate(iso) {
    return new Date(iso).toLocaleDateString('it-IT', { day: 'numeric', month: 'short' });
}

export function spawnConfetti(x, y, count = 18) {
    const colors = ['#0066cc','#28b463','#e8392a','#d97706','#a855f7','#fff'];
    for (let i = 0; i < count; i++) {
        const el    = document.createElement('div');
        el.className = 'confetti-particle';
        const angle = (Math.random() * 360) * (Math.PI / 180);
        const dist  = 50 + Math.random() * 70;
        el.style.cssText = `
            left:${x}px;top:${y}px;
            background:${colors[Math.floor(Math.random() * colors.length)]};
            --tx:${Math.cos(angle) * dist}px;
            --ty:${Math.sin(angle) * dist + 40}px;
            --rot:${Math.random() > 0.5 ? '' : '-'}${180 + Math.random() * 180}deg;
            --dur:${0.5 + Math.random() * 0.45}s;
            border-radius:${Math.random() > 0.5 ? '50%' : '2px'};
        `;
        document.body.appendChild(el);
        el.addEventListener('animationend', () => el.remove());
    }
}

export function bumpCredits(val) {
    document.getElementById('credits-val').textContent  = val;
    document.getElementById('stat-credits').textContent = val;
    if (window.__user) window.__user.credits = val;
    const pill = document.querySelector('.credits-pill');
    if (!pill) return;
    pill.classList.remove('bump');
    void pill.offsetWidth;
    pill.classList.add('bump');
    pill.addEventListener('animationend', () => pill.classList.remove('bump'), { once: true });
}