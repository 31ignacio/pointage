import './bootstrap';
import 'bootstrap';
import { Modal, Toast } from 'bootstrap';
window.bootstrap = Object.assign(window.bootstrap || {}, { Modal, Toast });
import QRCode from 'qrcode';
import { Passkeys } from '@laravel/passkeys';

window.QRCode = QRCode;
window.Passkeys = Passkeys;

window.showToast = (message, type = 'info') => {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const variants = { success: ['Succès', 'bi-check-circle-fill', 'text-success'], error: ['Erreur', 'bi-exclamation-circle-fill', 'text-danger'], warning: ['Attention', 'bi-exclamation-triangle-fill', 'text-warning'], info: ['Information', 'bi-info-circle-fill', 'text-primary'] };
    const [title, icon, color] = variants[type] || variants.info;
    const toast = document.createElement('div');
    toast.className = 'toast app-toast border-0';
    toast.setAttribute('role', 'status');
    toast.innerHTML = `<div class="d-flex"><div class="toast-body"><div class="fw-bold mb-1 ${color}"><i class="bi ${icon} me-2"></i>${title}</div><div class="toast-message"></div></div><button type="button" class="btn-close me-3 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button></div>`;
    toast.querySelector('.toast-message').textContent = String(message);
    container.appendChild(toast);
    const instance = Toast.getOrCreateInstance(toast, { delay: 6000 });
    toast.addEventListener('hidden.bs.toast', () => toast.remove(), { once: true });
    instance.show();
};

document.querySelectorAll('[data-toast-message]').forEach((item) => {
    window.showToast(item.dataset.toastMessage, item.dataset.toastType || 'info');
});

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
