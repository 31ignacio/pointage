import './bootstrap';
import 'bootstrap';
import QRCode from 'qrcode';

window.QRCode = QRCode;

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = button.parentElement.querySelector('[data-password-toggle-input]');
        if (!input) return;
        const isVisible = input.type === 'text';
        input.type = isVisible ? 'password' : 'text';
        button.setAttribute('aria-pressed', String(!isVisible));
        button.setAttribute('aria-label', isVisible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
        const icon = button.querySelector('i');
        if (icon) icon.className = isVisible ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});

const navToggle = document.querySelector('[data-nav-toggle]');
const mainNav = document.getElementById('mainNav');

if (navToggle && mainNav) {
    navToggle.addEventListener('click', () => {
        const isOpen = mainNav.classList.toggle('is-open');
        navToggle.setAttribute('aria-expanded', String(isOpen));
        navToggle.setAttribute('aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu');
    });

    mainNav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 1199.98px)').matches) {
                mainNav.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
                navToggle.setAttribute('aria-label', 'Ouvrir le menu');
            }
        });
    });

    mainNav.querySelectorAll('[data-submenu-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const group = toggle.closest('.nav-group');
            const isOpen = group.classList.toggle('is-open');

            mainNav.querySelectorAll('.nav-group').forEach((otherGroup) => {
                if (otherGroup !== group) {
                    otherGroup.classList.remove('is-open');
                    otherGroup.querySelector('[data-submenu-toggle]')?.setAttribute('aria-expanded', 'false');
                }
            });

            toggle.setAttribute('aria-expanded', String(isOpen));
        });
    });

    document.addEventListener('click', (event) => {
        if (!mainNav.contains(event.target)) {
            mainNav.querySelectorAll('.nav-group.is-open').forEach((group) => {
                group.classList.remove('is-open');
                group.querySelector('[data-submenu-toggle]')?.setAttribute('aria-expanded', 'false');
            });
        }
    });
}
