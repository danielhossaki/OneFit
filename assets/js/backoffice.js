document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('boSidebar');
    const backdrop = document.getElementById('boSidebarBackdrop');
    const toggle = document.getElementById('boSidebarToggle');

    if (toggle && sidebar && backdrop) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            backdrop.classList.toggle('active');
        });
        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('active');
            backdrop.classList.remove('active');
        });
    }

    document.querySelectorAll('[data-bo-table]').forEach((table) => {
        const filterId = table.getAttribute('data-bo-table');
        const searchInput = document.querySelector(`[data-bo-filter="search"][data-bo-target="${filterId}"]`);
        const statusSelect = document.querySelector(`[data-bo-filter="status"][data-bo-target="${filterId}"]`);
        const emptyRow = table.querySelector('.bo-empty-row');

        const applyFilters = () => {
            const term = (searchInput ? searchInput.value : '').trim().toLowerCase();
            const status = statusSelect ? statusSelect.value : '';
            let visibleCount = 0;

            table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((row) => {
                const haystack = row.getAttribute('data-search') || '';
                const rowStatus = row.getAttribute('data-status') || '';
                const matchesTerm = term === '' || haystack.toLowerCase().includes(term);
                const matchesStatus = status === '' || rowStatus === status;
                const visible = matchesTerm && matchesStatus;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount += 1;
            });

            if (emptyRow) {
                emptyRow.style.display = visibleCount === 0 ? '' : 'none';
            }
        };

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (statusSelect) statusSelect.addEventListener('change', applyFilters);
    });

    document.body.addEventListener('click', (event) => {
        const toggleBtn = event.target.closest('[data-bo-action="toggle-status"]');
        if (toggleBtn) {
            const row = toggleBtn.closest('tr');
            const badge = row.querySelector('.bo-badge');
            const active = badge.classList.contains('bo-badge-active');

            badge.classList.toggle('bo-badge-active', !active);
            badge.classList.toggle('bo-badge-inactive', active);
            badge.textContent = active ? 'Inativo' : 'Ativo';
            row.setAttribute('data-status', active ? 'inativo' : 'ativo');
            toggleBtn.innerHTML = `<i class="bi ${active ? 'bi-play-circle' : 'bi-pause-circle'}"></i>`;
            toggleBtn.title = active ? 'Ativar' : 'Inativar';
        }

        const deleteBtn = event.target.closest('[data-bo-action="delete"]');
        if (deleteBtn) {
            const label = deleteBtn.getAttribute('data-bo-name') || 'este registro';
            if (window.confirm(`Tem certeza que deseja excluir ${label}?`)) {
                deleteBtn.closest('tr').remove();
            }
        }

        const editBtn = event.target.closest('[data-bo-action="edit"]');
        if (editBtn) {
            const modalEl = document.getElementById(editBtn.getAttribute('data-bo-modal'));
            if (modalEl) {
                modalEl.querySelectorAll('[data-bo-field]').forEach((field) => {
                    const key = field.getAttribute('data-bo-field');
                    if (editBtn.dataset[key] !== undefined) {
                        field.value = editBtn.dataset[key];
                    }
                });
            }
        }
    });
});
