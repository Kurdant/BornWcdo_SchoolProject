/**
 * login.js — page de connexion admin
 * Dépend de : (aucune dépendance utils.js — page autonome avant auth)
 */

/* ==========================================
   REDIRECTION SI DÉJÀ CONNECTÉ
========================================== */
(function () {
    const role = sessionStorage.getItem('admin_role');
    if (role) redirectByRole(role);
})();

function redirectByRole(role) {
    const routes = {
        'administration': 'index.html',
        'preparation':    'preparation.html',
        'accueil':        'accueil-bo.html'
    };
    window.location.href = routes[role] || 'index.html';
}

/* ==========================================
   TOGGLE VISIBILITÉ MOT DE PASSE
========================================== */
document.getElementById('toggle-pwd').addEventListener('click', function () {
    const input = document.getElementById('password');
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    this.textContent = isHidden ? '🙈' : '👁️';
});

/* ==========================================
   SOUMISSION DU FORMULAIRE
========================================== */
document.getElementById('login-form').addEventListener('submit', async function (e) {
    e.preventDefault();

    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const btn      = document.getElementById('btn-submit');

    hideError();

    if (!email || !password) {
        showError('Veuillez remplir tous les champs.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Connexion en cours…';

    try {
        const response = await fetch('/api/admin/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ email, mot_de_passe: password })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            const admin = result.data.admin;
            sessionStorage.setItem('admin_role', admin.role);
            sessionStorage.setItem('admin_nom',  admin.nom);
            sessionStorage.setItem('admin_id',   admin.id);
            redirectByRole(admin.role);
        } else {
            showError(result.error || result.message || 'Email ou mot de passe incorrect.');
        }
    } catch (err) {
        console.error('Erreur login:', err);
        showError('Impossible de contacter le serveur. Vérifiez votre connexion.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Se connecter';
    }
});

function showError(msg) {
    const div = document.getElementById('error-msg');
    document.getElementById('error-text').textContent = msg;
    div.classList.add('visible');
}
function hideError() {
    document.getElementById('error-msg').classList.remove('visible');
}
