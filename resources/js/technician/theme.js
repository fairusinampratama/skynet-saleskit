const storageKey = 'saleskit-tech-theme';

const preferredSystemTheme = () => (
    window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
);

const applyTheme = theme => {
    document.documentElement.dataset.techTheme = theme;
    document.documentElement.style.colorScheme = theme;

    document.querySelectorAll('[data-tech-theme-icon]').forEach(icon => {
        icon.classList.toggle('hidden', icon.dataset.techThemeIcon !== theme);
    });
};

const storedTheme = () => localStorage.getItem(storageKey);

const initTechnicianTheme = () => {
    const toggle = document.querySelector('[data-tech-theme-toggle]');

    if (! toggle) {
        return;
    }

    applyTheme(storedTheme() || preferredSystemTheme());

    toggle.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.techTheme === 'dark' ? 'light' : 'dark';
        localStorage.setItem(storageKey, nextTheme);
        applyTheme(nextTheme);
    });

    window.matchMedia?.('(prefers-color-scheme: dark)')?.addEventListener('change', event => {
        if (storedTheme()) {
            return;
        }

        applyTheme(event.matches ? 'dark' : 'light');
    });
};

document.addEventListener('DOMContentLoaded', initTechnicianTheme);
