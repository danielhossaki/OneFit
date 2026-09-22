// Contadores: uma execução por elemento, com duração independente da tela.
(() => {
    const counters = document.querySelectorAll('.num[data-target]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const finish = counter => {
        counter.textContent = counter.dataset.target + (counter.dataset.suffix || '');
    };
    if (!('IntersectionObserver' in window) || motion.matches) {
        counters.forEach(finish);
        return;
    }
    const observer = new IntersectionObserver(entries => {
        entries.forEach(({ target: counter, isIntersecting }) => {
            if (!isIntersecting) return;
            observer.unobserve(counter);
            const start = performance.now();
            const target = Number(counter.dataset.target);
            const tick = now => {
                if (motion.matches) { finish(counter); return; }
                const progress = Math.min((now - start) / 1400, 1);
                counter.textContent = Math.round(target * (1 - Math.pow(1 - progress, 3))) + (counter.dataset.suffix || '');
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        });
    }, { threshold: 0.5 });
    counters.forEach(counter => observer.observe(counter));
    motion.addEventListener('change', () => {
        if (!motion.matches) return;
        observer.disconnect();
        counters.forEach(finish);
    });
})();