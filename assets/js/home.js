// Animação de contadores da div STATS
const counters = document.querySelectorAll(".num[data-target]");

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return;

        const counter = entry.target;
        const target = +counter.dataset.target;
        const suffix = counter.dataset.suffix || "";

        let current = 0;
        const duration = 2000; // 2 segundos
        const increment = target / (duration / 16);

        const updateCounter = () => {
            current += increment;

            if (current < target) {
                counter.textContent = Math.floor(current) + suffix;
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target + suffix;
            }
        };

        updateCounter();

        observer.unobserve(counter); // executa apenas uma vez
    });
}, {
    threshold: 0.5
});

counters.forEach(counter => observer.observe(counter));