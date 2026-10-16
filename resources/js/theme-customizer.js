const STORAGE_KEY = 'tracker.theme';

const defaults = {
    mode: 'light',
    accent: 'blue',
    sidebarStyle: 'full',
    sidebarPosition: 'left',
    sidebarVisibility: 'show',
};

const readState = () => {
    try {
        return { ...defaults, ...JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') };
    } catch (e) {
        return { ...defaults };
    }
};

const writeState = (state) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
};

const applyState = (panel, state) => {
    const root = document.documentElement;
    root.setAttribute('data-bs-theme', state.mode === 'dark' ? 'dark' : 'light');
    root.setAttribute('data-accent', state.accent);
    root.setAttribute('data-sidebar-position', state.sidebarPosition);
    root.setAttribute('data-sidebar-visibility', state.sidebarVisibility);

    document.body.classList.toggle('sidebar-collapse', state.sidebarStyle === 'compact');

    if (! panel) {
        return;
    }

    panel.querySelectorAll('[data-theme-mode]').forEach((btn) => {
        btn.classList.toggle('is-active', btn.dataset.themeMode === state.mode);
    });

    panel.querySelectorAll('[data-theme-accent]').forEach((btn) => {
        btn.classList.toggle('is-active', btn.dataset.themeAccent === state.accent);
    });

    panel.querySelectorAll('[data-sidebar-style]').forEach((btn) => {
        btn.classList.toggle('is-active', btn.dataset.sidebarStyle === state.sidebarStyle);
    });

    panel.querySelectorAll('[data-sidebar-position]').forEach((btn) => {
        btn.classList.toggle('is-active', btn.dataset.sidebarPosition === state.sidebarPosition);
    });

    panel.querySelectorAll('[data-sidebar-visibility]').forEach((btn) => {
        btn.classList.toggle('is-active', btn.dataset.sidebarVisibility === state.sidebarVisibility);
    });
};

export function initThemeCustomizer() {
    const panel = document.querySelector('[data-customizer-panel]');
    let state = readState();
    applyState(panel, state);

    const update = (patch) => {
        state = { ...state, ...patch };
        writeState(state);
        applyState(panel, state);
    };

    const backdrop = document.querySelector('[data-customizer-backdrop]');
    const openBtn = document.querySelector('[data-customizer-open]');
    const closeBtn = panel?.querySelector('[data-customizer-close]');

    const openPanel = () => {
        panel?.classList.add('is-open');
        backdrop?.classList.add('is-open');
        panel?.setAttribute('aria-hidden', 'false');
    };

    const closePanel = () => {
        panel?.classList.remove('is-open');
        backdrop?.classList.remove('is-open');
        panel?.setAttribute('aria-hidden', 'true');
    };

    openBtn?.addEventListener('click', openPanel);
    closeBtn?.addEventListener('click', closePanel);
    backdrop?.addEventListener('click', closePanel);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePanel();
        }
    });

    if (panel) {
        panel.querySelectorAll('[data-theme-mode]').forEach((btn) => {
            btn.addEventListener('click', () => update({ mode: btn.dataset.themeMode }));
        });

        panel.querySelectorAll('[data-theme-accent]').forEach((btn) => {
            btn.addEventListener('click', () => update({ accent: btn.dataset.themeAccent }));
        });

        panel.querySelectorAll('[data-sidebar-style]').forEach((btn) => {
            btn.addEventListener('click', () => update({ sidebarStyle: btn.dataset.sidebarStyle }));
        });

        panel.querySelectorAll('[data-sidebar-position]').forEach((btn) => {
            btn.addEventListener('click', () => update({ sidebarPosition: btn.dataset.sidebarPosition }));
        });

        panel.querySelectorAll('[data-sidebar-visibility]').forEach((btn) => {
            btn.addEventListener('click', () => update({ sidebarVisibility: btn.dataset.sidebarVisibility }));
        });

        panel.querySelector('[data-customizer-reset]')?.addEventListener('click', () => {
            localStorage.removeItem(STORAGE_KEY);
            state = { ...defaults };
            applyState(panel, state);
        });
    }

    document.querySelector('[data-sidebar-compact-toggle]')?.addEventListener('click', () => {
        update({ sidebarStyle: state.sidebarStyle === 'compact' ? 'full' : 'compact' });
    });

    document.querySelector('[data-sidebar-reveal]')?.addEventListener('click', () => {
        update({ sidebarVisibility: 'show' });
    });

    const bodyObserver = new MutationObserver(() => {
        const isCompact = document.body.classList.contains('sidebar-collapse');
        if ((state.sidebarStyle === 'compact') !== isCompact) {
            state = { ...state, sidebarStyle: isCompact ? 'compact' : 'full' };
            writeState(state);
            applyState(panel, state);
        }
    });

    bodyObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
}
