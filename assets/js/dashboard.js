/* Interações mínimas: tema, menu do usuário e sidebar mobile. */
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('boSidebar');
    const backdrop = document.getElementById('boSidebarBackdrop');
    const toggle = document.getElementById('boSidebarToggle');
    const avatar = document.getElementById('boAvatar');
    const userMenu = document.getElementById('boUserMenu');
    const logoutLink = document.getElementById('boLogoutLink');
    const logoutModalElement = document.getElementById('boLogoutModal');
    const logoutModal = logoutModalElement && window.bootstrap
        ? new bootstrap.Modal(logoutModalElement)
        : null;

    const closeSidebar = () => {
        sidebar?.classList.remove('active');
        backdrop?.classList.remove('active');
    };

    toggle?.addEventListener('click', () => {
        sidebar?.classList.toggle('active');
        backdrop?.classList.toggle('active');
    });
    backdrop?.addEventListener('click', closeSidebar);

    avatar?.addEventListener('click', () => {
        const isOpen = userMenu?.classList.toggle('is-open');
        avatar.setAttribute('aria-expanded', String(isOpen));
        userMenu?.setAttribute('aria-hidden', String(!isOpen));
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('#boUserMenuWrap')) {
            userMenu?.classList.remove('is-open');
            avatar?.setAttribute('aria-expanded', 'false');
        }
    });

    logoutLink?.addEventListener('click', (event) => {
        event.preventDefault();
        userMenu?.classList.remove('is-open');
        logoutModal?.show();
    });
});
