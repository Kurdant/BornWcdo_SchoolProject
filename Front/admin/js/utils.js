/**
 * utils.js — fonctions partagées back-office
 * Importé en premier sur toutes les pages admin.
 */

const API_BASE = '/api';

/** Echappe le HTML pour éviter les XSS */
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Vérifie que l'utilisateur est connecté et a le bon rôle.
 * Redirige vers login.html si ce n'est pas le cas.
 * @param {string[]} rolesAutorises — ex: ['administration'] ou ['preparation', 'administration']
 */
function checkAuth(rolesAutorises) {
    const role = sessionStorage.getItem('admin_role');
    if (!role) {
        window.location.replace('login.html');
        return;
    }
    if (rolesAutorises.length > 0 && !rolesAutorises.includes(role)) {
        window.location.replace('login.html');
    }
}

/** Déconnexion : vide sessionStorage et redirige vers login */
function logout() {
    sessionStorage.removeItem('admin_role');
    sessionStorage.removeItem('admin_nom');
    sessionStorage.removeItem('admin_id');
    window.location.replace('login.html');
}

/**
 * Affiche un toast de notification.
 * @param {string} message
 * @param {'success'|'error'|'info'} type
 */
function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 3500);
}

/**
 * Wrapper fetch vers l'API backend.
 * Gère Content-Type JSON et lance une erreur si !response.ok.
 * @param {string} endpoint — chemin relatif ex: '/api/admin/produits'
 * @param {RequestInit} options — options fetch optionnelles
 * @returns {Promise<any>} — le corps JSON décodé
 */
async function apiFetch(endpoint, options = {}) {
    const defaults = {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) }
    };
    const config = { ...defaults, ...options, headers: { ...defaults.headers, ...(options.headers || {}) } };
    const response = await fetch(endpoint, config);
    if (!response.ok) {
        let message = `Erreur ${response.status}`;
        try {
            const err = await response.json();
            message = err.message || message;
        } catch (_) { /* ignore */ }
        throw new Error(message);
    }
    return response.json();
}
