/**
 * produits.js — gestion CRUD des produits
 * Dépend de : utils.js
 */

let allProduits   = [];
let allCategories = [];
let editMode = false;

/* ==========================================
   INITIALISATION
========================================== */
(async function init() {
    checkAuth(['administration']);

    document.getElementById('user-nom').textContent  = sessionStorage.getItem('admin_nom')  || '';
    document.getElementById('user-role').textContent = sessionStorage.getItem('admin_role') || '';

    await Promise.all([loadProduits(), loadCategories()]);
})();

/* ==========================================
   CHARGEMENT
========================================== */
async function loadProduits() {
    try {
        const result = await apiFetch(`${API_BASE}/admin/produits`);
        if (!result) return;
        if (!result.success) { showToast('Erreur chargement produits', 'error'); return; }
        allProduits = result.data || [];
        renderProduits(allProduits);
    } catch (err) {
        console.error(err); showToast('Erreur réseau', 'error');
    }
}

async function loadCategories() {
    try {
        const result = await apiFetch(`${API_BASE}/categories`);
        if (!result) return;
        if (!result.success) return;
        allCategories = result.data || [];
        const select = document.getElementById('f-categorie');
        select.innerHTML = '<option value="">— Sélectionner —</option>' +
            allCategories.map(c => `<option value="${c.id}">${escHtml(c.nom)}</option>`).join('');
    } catch (err) { console.error(err); }
}

/* ==========================================
   RENDU DU TABLEAU
========================================== */
function renderProduits(list) {
    const tbody = document.getElementById('produits-tbody');
    document.getElementById('produits-count').textContent = `${list.length} produit(s)`;

    if (!list.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="7">Aucun produit trouvé</td></tr>';
        return;
    }

    tbody.innerHTML = list.map(p => {
        const catNom = (allCategories.find(c => c.id == p.id_categorie) || {}).nom || '—';
        const stockClass = p.stock == 0 ? 'stock-zero' : (p.stock < 10 ? 'stock-low' : '');
        const imgHtml = p.image
            ? `<img src="${escHtml(p.image)}" alt="${escHtml(p.nom)}" class="img-thumb" onerror="this.style.display='none'">`
            : `<div class="img-placeholder">🍔</div>`;
        const dispoBadge = p.disponible
            ? `<span class="badge badge-green">✓ Dispo</span>`
            : `<span class="badge badge-gray">✗ Indispo</span>`;

        return `
            <tr>
                <td>${imgHtml}</td>
                <td><strong>${escHtml(p.nom)}</strong></td>
                <td>${escHtml(catNom)}</td>
                <td><strong>${parseFloat(p.prix || 0).toFixed(2)} €</strong></td>
                <td class="${stockClass}">${p.stock ?? '—'}</td>
                <td>${dispoBadge}</td>
                <td>
                    <div class="actions-cell">
                        <button class="btn btn-edit" onclick="openModal('edit', ${p.id})" title="Modifier">✏️</button>
                        <button class="btn btn-danger" onclick="deleteProduit(${p.id}, '${p.nom.replace(/'/g, "\\'")}')" title="Supprimer">🗑️</button>
                    </div>
                </td>
            </tr>`;
    }).join('');
}

/* ==========================================
   RECHERCHE CÔTÉ CLIENT
========================================== */
document.getElementById('search-input').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    renderProduits(q ? allProduits.filter(p => p.nom.toLowerCase().includes(q)) : allProduits);
});

/* ==========================================
   MODAL
========================================== */
function openModal(mode, id = null) {
    editMode = mode === 'edit';
    document.getElementById('produit-form').reset();

    if (editMode && id !== null) {
        const p = allProduits.find(x => x.id == id);
        if (!p) return;
        document.getElementById('modal-title').textContent  = '✏️ Modifier le produit';
        document.getElementById('produit-id').value         = p.id;
        document.getElementById('f-nom').value              = p.nom || '';
        document.getElementById('f-description').value      = p.description || '';
        document.getElementById('f-prix').value             = p.prix || '';
        document.getElementById('f-stock').value            = p.stock ?? '';
        document.getElementById('f-categorie').value        = p.id_categorie || '';
        document.getElementById('f-image').value            = p.image || '';
        document.getElementById('f-disponible').checked     = !!p.disponible;
    } else {
        document.getElementById('modal-title').textContent  = '➕ Ajouter un produit';
        document.getElementById('f-disponible').checked     = true;
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
   SAUVEGARDE (création / modification)
========================================== */
document.getElementById('produit-form').addEventListener('submit', async function (e) {
    e.preventDefault();

    const nom   = document.getElementById('f-nom').value.trim();
    const prix  = parseFloat(document.getElementById('f-prix').value);
    const catId = document.getElementById('f-categorie').value;

    if (!nom || isNaN(prix) || !catId) {
        showToast('Nom, prix et catégorie sont obligatoires.', 'error');
        return;
    }

    const body = {
        nom,
        description:  document.getElementById('f-description').value.trim(),
        prix,
        stock:        parseInt(document.getElementById('f-stock').value) || 0,
        id_categorie: parseInt(catId),
        image:        document.getElementById('f-image').value.trim() || null,
        disponible:   document.getElementById('f-disponible').checked ? 1 : 0
    };

    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.textContent = '⏳ Sauvegarde…';

    try {
        const id     = document.getElementById('produit-id').value;
        const url    = editMode ? `${API_BASE}/admin/produits/${id}` : `${API_BASE}/admin/produits`;
        const method = editMode ? 'PUT' : 'POST';

        const result = await apiFetch(url, { method, body: JSON.stringify(body) });
        if (!result) return;

        if (result.success) {
            showToast(editMode ? '✅ Produit modifié !' : '✅ Produit créé !');
            closeModal();
            await loadProduits();
        } else {
            showToast(result.error || 'Erreur lors de la sauvegarde.', 'error');
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
async function deleteProduit(id, nom) {
    if (!confirm(`Supprimer le produit "${nom}" ?\n\nCette action est irréversible.`)) return;

    try {
        const result = await apiFetch(`${API_BASE}/admin/produits/${id}`, { method: 'DELETE' });
        if (!result) return;

        if (result.success) {
            showToast('🗑️ Produit supprimé.');
            await loadProduits();
        } else {
            showToast(result.error || 'Erreur suppression.', 'error');
        }
    } catch (err) {
        console.error(err); showToast('Erreur réseau', 'error');
    }
}
