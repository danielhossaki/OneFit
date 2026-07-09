const root = document.documentElement;
const toggle = document.getElementById('themeToggle');
const icon = document.getElementById('toggleIcon');

function applyTheme(theme) {
    root.setAttribute('data-theme', theme);
    if (theme === 'light') {
        icon.innerHTML = '<path d="M12 3.5a8.5 8.5 0 108.5 8.5A6.8 6.8 0 0112 3.5z"/>';
    } else {
        icon.innerHTML = '<path d="M12 3v1M12 20v1M4.2 4.2l.7.7M19.1 19.1l.7.7M3 12h1M20 12h1M4.2 19.8l.7-.7M19.1 4.9l.7-.7"/><circle cx="12" cy="12" r="4.5"/>';
    }
}

let currentTheme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
applyTheme(currentTheme);

toggle.addEventListener('click', () => {
    currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
    applyTheme(currentTheme);
});

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
