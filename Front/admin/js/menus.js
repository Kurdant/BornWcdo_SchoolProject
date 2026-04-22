/**
 * menus.js — gestion CRUD des menus avec composition
 * Dépend de : utils.js
 */

let allMenus    = [];
let allProduits = [];
let composition = [];   // [{id_produit, nom, quantite}]
let editMode    = false;

/* ==========================================
   INITIALISATION
========================================== */
(async function init() {
    checkAuth(['administration']);

    document.getElementById('user-nom').textContent  = sessionStorage.getItem('admin_nom')  || '';
    document.getElementById('user-role').textContent = sessionStorage.getItem('admin_role') || '';

    await Promise.all([loadMenus(), loadProduits()]);
})();

/* ==========================================
   CHARGEMENT
========================================== */
async function loadMenus() {
    try {
        const result = await apiFetch(`${API_BASE}/admin/menus`);
        if (!result) return;
        if (!result.success) { showToast('Erreur chargement menus', 'error'); return; }
        allMenus = result.data || [];
        renderMenus();
    } catch (e) { console.error(e); showToast('Erreur réseau', 'error'); }
}

async function loadProduits() {
    try {
        const result = await apiFetch(`${API_BASE}/produits`);
        if (!result) return;
        if (!result.success) return;
        allProduits = result.data || [];
        const select = document.getElementById('add-produit-select');
        select.innerHTML = '<option value="">— Choisir un produit —</option>' +
            allProduits.map(p =>
                `<option value="${p.id}">${escHtml(p.nom)} — ${parseFloat(p.prix || 0).toFixed(2)} €</option>`
            ).join('');
    } catch (e) { console.error(e); }
}

/* ==========================================
   RENDU DU TABLEAU
========================================== */
function renderMenus() {
    const tbody = document.getElementById('menus-tbody');
    document.getElementById('menus-count').textContent = `${allMenus.length} menu(s)`;

    if (!allMenus.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="6">Aucun menu trouvé</td></tr>';
        return;
    }

    tbody.innerHTML = allMenus.map(m => {
        const imgHtml = m.image
            ? `<img src="${escHtml(m.image)}" alt="${escHtml(m.nom)}" class="img-thumb" onerror="this.style.display='none'">`
            : `<div class="img-placeholder">🍔</div>`;
        const nbProduits = Array.isArray(m.produits) ? m.produits.length : (m.nb_produits || 0);
        const dispoBadge = m.disponible
            ? `<span class="badge badge-green">✓ Dispo</span>`
            : `<span class="badge badge-gray">✗ Indispo</span>`;
        return `
            <tr>
                <td>${imgHtml}</td>
                <td>
                    <strong>${escHtml(m.nom)}</strong><br>
                    <small style="color:#888">${escHtml(m.description || '')}</small>
                </td>
                <td><strong>${parseFloat(m.prix || 0).toFixed(2)} €</strong></td>
                <td>${nbProduits} produit(s)</td>
                <td>${dispoBadge}</td>
                <td>
                    <div class="actions-cell">
                        <button class="btn btn-edit" onclick="openModal('edit',${m.id})" title="Modifier">✏️</button>
                        <button class="btn btn-danger" onclick="deleteMenu(${m.id},'${m.nom.replace(/'/g, "\\'")}')">🗑️</button>
                    </div>
                </td>
            </tr>`;
    }).join('');
}

/* ==========================================
   COMPOSITION
========================================== */
function renderComposition() {
    const container = document.getElementById('composition-list');
    if (!composition.length) {
        container.innerHTML = '<div class="composition-empty">Aucun produit ajouté</div>';
        return;
    }
    container.innerHTML = composition.map((item, idx) => `
        <div class="composition-item">
            <span class="item-nom">${escHtml(item.nom)}</span>
            <span class="item-qty">× ${item.quantite}</span>
            <button type="button" class="btn-remove" onclick="removeFromComposition(${idx})" title="Retirer">✕</button>
        </div>`).join('');
}

function addProduitToComposition() {
    const select = document.getElementById('add-produit-select');
    const qty    = parseInt(document.getElementById('add-produit-qty').value) || 1;
    const id     = parseInt(select.value);
    if (!id) { showToast('Sélectionnez un produit.', 'error'); return; }

    const produit = allProduits.find(p => p.id == id);
    if (!produit) return;

    const existing = composition.find(c => c.id_produit == id);
    if (existing) { existing.quantite += qty; }
    else { composition.push({ id_produit: id, nom: produit.nom, quantite: qty }); }

    select.value = '';
    document.getElementById('add-produit-qty').value = 1;
    renderComposition();
}

function removeFromComposition(idx) {
    composition.splice(idx, 1);
    renderComposition();
}

/* ==========================================
   MODAL
========================================== */
function openModal(mode, id = null) {
    editMode = mode === 'edit';
    composition = [];
    document.getElementById('menu-form').reset();

    if (editMode && id !== null) {
        const m = allMenus.find(x => x.id == id);
        if (!m) return;
        document.getElementById('modal-title').textContent  = '✏️ Modifier le menu';
        document.getElementById('menu-id').value            = m.id;
        document.getElementById('f-nom').value              = m.nom || '';
        document.getElementById('f-description').value      = m.description || '';
        document.getElementById('f-prix').value             = m.prix || '';
        document.getElementById('f-image').value            = m.image || '';
        document.getElementById('f-disponible').checked     = !!m.disponible;
        if (Array.isArray(m.produits)) {
            composition = m.produits.map(p => ({
                id_produit: p.id_produit || p.id,
                nom: p.nom || (allProduits.find(x => x.id == (p.id_produit || p.id)) || {}).nom || '?',
                quantite: p.quantite || 1
            }));
        }
    } else {
        document.getElementById('modal-title').textContent  = '➕ Ajouter un menu';
        document.getElementById('f-disponible').checked     = true;
    }

    renderComposition();
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
document.getElementById('menu-form').addEventListener('submit', async function (e) {
    e.preventDefault();

    const nom  = document.getElementById('f-nom').value.trim();
    const prix = parseFloat(document.getElementById('f-prix').value);
    if (!nom || isNaN(prix)) { showToast('Nom et prix sont obligatoires.', 'error'); return; }

    const body = {
        nom,
        description: document.getElementById('f-description').value.trim(),
        prix,
        image:       document.getElementById('f-image').value.trim() || null,
        disponible:  document.getElementById('f-disponible').checked ? 1 : 0,
        produits:    composition.map(c => ({ id_produit: c.id_produit, quantite: c.quantite }))
    };

    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.textContent = '⏳ Sauvegarde…';

    try {
        const id     = document.getElementById('menu-id').value;
        const url    = editMode ? `${API_BASE}/admin/menus/${id}` : `${API_BASE}/admin/menus`;
        const result = await apiFetch(url, { method: editMode ? 'PUT' : 'POST', body: JSON.stringify(body) });
        if (!result) return;

        if (result.success) {
            showToast(editMode ? '✅ Menu modifié !' : '✅ Menu créé !');
            closeModal();
            await loadMenus();
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
async function deleteMenu(id, nom) {
    if (!confirm(`Supprimer le menu "${nom}" ?\n\nCette action est irréversible.`)) return;
    try {
        const result = await apiFetch(`${API_BASE}/admin/menus/${id}`, { method: 'DELETE' });
        if (!result) return;
        if (result.success) { showToast('🗑️ Menu supprimé.'); await loadMenus(); }
        else showToast(result.error || 'Erreur suppression.', 'error');
    } catch (err) { console.error(err); showToast('Erreur réseau', 'error'); }
}
