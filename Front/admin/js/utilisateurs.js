/**
 * utilisateurs.js — gestion CRUD des comptes admin
 * Dépend de : utils.js
 */

let allUsers      = [];
let editMode      = false;
let currentUserId = null;

/* ==========================================
   INITIALISATION
========================================== */
(async function init() {
    checkAuth(['administration']);

    currentUserId = parseInt(sessionStorage.getItem('admin_id'));
    document.getElementById('user-nom').textContent  = sessionStorage.getItem('admin_nom')  || '';
    document.getElementById('user-role').textContent = sessionStorage.getItem('admin_role') || '';

    await loadUsers();
})();

/* ==========================================
   CHARGEMENT
========================================== */
async function loadUsers() {
    try {
        const result = await apiFetch(`${API_BASE}/admin/utilisateurs`);
        if (!result) return;
        if (!result.success) { showToast('Erreur chargement utilisateurs', 'error'); return; }
        allUsers = result.data || [];
        renderUsers();
    } catch (e) { console.error(e); showToast('Erreur réseau', 'error'); }
}

/* ==========================================
   RENDU DU TABLEAU
========================================== */
function initials(nom) {
    return (nom || '?').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

function renderUsers() {
    const tbody = document.getElementById('users-tbody');
    document.getElementById('users-count').textContent = `${allUsers.length} utilisateur(s)`;

    if (!allUsers.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="4">Aucun utilisateur trouvé</td></tr>';
        return;
    }

    const roleBadge = {
        'administration': '<span class="badge badge-admin">Administration</span>',
        'preparation':    '<span class="badge badge-prep">Préparation</span>',
        'accueil':        '<span class="badge badge-accueil">Accueil</span>'
    };

    tbody.innerHTML = allUsers.map(u => {
        const isSelf       = u.id === currentUserId;
        const selfTag      = isSelf ? ' <span style="font-size:0.72rem;color:#888">(vous)</span>' : '';
        const disabledAttr = isSelf ? 'disabled title="Impossible de modifier/supprimer votre propre compte"' : '';

        return `
            <tr>
                <td>
                    <div class="user-cell">
                        <div class="avatar">${initials(u.nom)}</div>
                        <strong>${escHtml(u.nom)}${selfTag}</strong>
                    </div>
                </td>
                <td>${escHtml(u.email)}</td>
                <td>${roleBadge[u.role] || escHtml(u.role)}</td>
                <td>
                    <div class="actions-cell">
                        <button class="btn btn-edit" onclick="openModal('edit',${u.id})" ${disabledAttr}>✏️</button>
                        <button class="btn btn-danger" onclick="deleteUser(${u.id},'${u.nom.replace(/'/g, "\\'")}','${escHtml(u.email)}')" ${disabledAttr}>🗑️</button>
                    </div>
                </td>
            </tr>`;
    }).join('');
}

/* ==========================================
   MODAL
========================================== */
function openModal(mode, id = null) {
    editMode = mode === 'edit';
    document.getElementById('user-form').reset();

    const pwdHint  = document.getElementById('pwd-hint');
    const pwdLabel = document.getElementById('pwd-required-label');

    if (editMode && id !== null) {
        const u = allUsers.find(x => x.id == id);
        if (!u) return;
        document.getElementById('modal-title').textContent = '✏️ Modifier l\'utilisateur';
        document.getElementById('user-id').value           = u.id;
        document.getElementById('f-nom').value             = u.nom || '';
        document.getElementById('f-email').value           = u.email || '';
        document.getElementById('f-role').value            = u.role || '';
        pwdHint.style.display = 'block';
        pwdLabel.textContent  = '(optionnel)';
        document.getElementById('f-password').removeAttribute('required');
    } else {
        document.getElementById('modal-title').textContent = '➕ Nouvel utilisateur';
        pwdHint.style.display = 'none';
        pwdLabel.textContent  = '*';
        document.getElementById('f-password').setAttribute('required', 'required');
    }

    document.getElementById('modal-overlay').classList.add('open');
    document.getElementById('f-nom').focus();
}

function closeModal() {
    document.getElementById('modal-overlay').classList.remove('open');
}

document.getElementById('modal-overlay').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});

/* ==========================================
   SAUVEGARDE
========================================== */
document.getElementById('user-form').addEventListener('submit', async function (e) {
    e.preventDefault();

    const nom      = document.getElementById('f-nom').value.trim();
    const email    = document.getElementById('f-email').value.trim();
    const role     = document.getElementById('f-role').value;
    const password = document.getElementById('f-password').value;

    if (!nom || !email || !role) { showToast('Nom, email et rôle sont obligatoires.', 'error'); return; }
    if (!editMode && !password)  { showToast('Le mot de passe est obligatoire pour un nouvel utilisateur.', 'error'); return; }

    const body = { nom, email, role };
    if (password) body.mot_de_passe = password;

    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.textContent = '⏳ Sauvegarde…';

    try {
        const id     = document.getElementById('user-id').value;
        const url    = editMode ? `${API_BASE}/admin/utilisateurs/${id}` : `${API_BASE}/admin/utilisateurs`;
        const result = await apiFetch(url, { method: editMode ? 'PUT' : 'POST', body: JSON.stringify(body) });
        if (!result) return;

        if (result.success) {
            showToast(editMode ? '✅ Utilisateur modifié !' : '✅ Utilisateur créé !');
            closeModal();
            await loadUsers();
        } else {
            showToast(result.error || 'Erreur sauvegarde.', 'error');
        }
    } catch (err) {
        console.error(err); showToast('Erreur réseau', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Sauvegarder';
    }
});

/* ==========================================
   SUPPRESSION
========================================== */
async function deleteUser(id, nom, email) {
    if (!confirm(`Supprimer l'utilisateur "${nom}" (${email}) ?\n\nCette action est irréversible.`)) return;
    try {
        const result = await apiFetch(`${API_BASE}/admin/utilisateurs/${id}`, { method: 'DELETE' });
        if (!result) return;
        if (result.success) { showToast('🗑️ Utilisateur supprimé.'); await loadUsers(); }
        else showToast(result.error || 'Erreur suppression.', 'error');
    } catch (err) { console.error(err); showToast('Erreur réseau', 'error'); }
}
