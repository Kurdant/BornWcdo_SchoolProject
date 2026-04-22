/**
 * accueil-bo.js — page accueil comptoir
 * Dépend de : utils.js
 */

let lignesPanier = [];
let allProduits  = [];
let refreshTimer = null;

/* ==========================================
   INITIALISATION
========================================== */
(async function init() {
    checkAuth(['accueil', 'administration']);
    document.getElementById('user-nom').textContent = sessionStorage.getItem('admin_nom') || '';

    await Promise.all([loadReadyOrders(), loadProduits()]);
    refreshTimer = setInterval(loadReadyOrders, 20000);
})();

/* ==========================================
   COLONNE GAUCHE — Commandes prêtes à remettre
========================================== */
async function loadReadyOrders() {
    try {
        const result = await apiFetch(`${API_BASE}/admin/commandes`);
        if (!result) return;
        if (!result.success) return;

        const pretes = (result.data || []).filter(c => c.statut === 'preparee');
        document.getElementById('ready-count').textContent = pretes.length;
        renderReadyList(pretes);
    } catch (err) { console.error(err); }
}

function renderReadyList(commandes) {
    const container = document.getElementById('ready-list');
    if (!commandes.length) {
        container.innerHTML = `
            <div class="empty-left">
                <div class="icon">😊</div>
                <p>Aucune commande en attente de remise.</p>
            </div>`;
        return;
    }

    const sorted = [...commandes].sort((a, b) =>
        new Date(a.date_creation || 0) - new Date(b.date_creation || 0)
    );

    container.innerHTML = sorted.map(c => {
        const typeClass = c.type_commande === 'sur_place' ? 'type-sur-place' : 'type-a-emporter';
        const typeLabel = c.type_commande === 'sur_place' ? '🪑 Sur place' : '🥡 À emporter';
        const heure = c.date_creation
            ? new Date(c.date_creation).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
            : '—';
        return `
            <div class="ready-card" id="ready-${c.id}">
                <div class="ready-info">
                    <div class="ready-num">#${escHtml(c.numero_commande || String(c.id))}</div>
                    <div class="ready-meta">
                        <span>🏷️ Chevalet ${escHtml(String(c.numero_chevalet || '—'))}</span>
                        <span class="ready-type ${typeClass}">${typeLabel}</span>
                        <span>⏱ ${heure}</span>
                    </div>
                </div>
                <button class="btn-livrer" onclick="livrerCommande(${c.id}, this)" title="Remettre au client">
                    ✓ Remettre
                </button>
            </div>`;
    }).join('');
}

async function livrerCommande(id, btn) {
    btn.disabled = true;
    btn.textContent = '⏳…';
    try {
        const result = await apiFetch(`${API_BASE}/admin/commandes/${id}/livrer`, { method: 'PUT' });
        if (!result) { btn.disabled = false; btn.textContent = '✓ Remettre'; return; }

        if (result.success) {
            showToast('✅ Commande remise au client !');
            const row = document.getElementById(`ready-${id}`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
            const count = document.getElementById('ready-count');
            count.textContent = Math.max(0, parseInt(count.textContent || 0) - 1);
        } else {
            showToast(result.error || 'Erreur.', 'error');
            btn.disabled = false; btn.textContent = '✓ Remettre';
        }
    } catch (err) {
        console.error(err); showToast('Erreur réseau', 'error');
        btn.disabled = false; btn.textContent = '✓ Remettre';
    }
}

/* ==========================================
   COLONNE DROITE — Saisie de commande
========================================== */
async function loadProduits() {
    try {
        const result = await apiFetch(`${API_BASE}/produits`);
        if (!result) return;
        if (!result.success) return;
        allProduits = result.data || [];
        const select = document.getElementById('produit-select');
        select.innerHTML = '<option value="">— Choisir un produit —</option>' +
            allProduits
                .filter(p => p.disponible)
                .map(p => `<option value="${p.id}" data-prix="${p.prix}">${escHtml(p.nom)} — ${parseFloat(p.prix || 0).toFixed(2)} €</option>`)
                .join('');
    } catch (err) { console.error(err); }
}

function addLigne() {
    const select = document.getElementById('produit-select');
    const qty    = parseInt(document.getElementById('produit-qty').value) || 1;
    const id     = parseInt(select.value);
    if (!id) { showToast('Sélectionnez un produit.', 'error'); return; }

    const produit = allProduits.find(p => p.id == id);
    if (!produit) return;

    const existing = lignesPanier.find(l => l.id_produit == id);
    if (existing) { existing.quantite += qty; }
    else { lignesPanier.push({ id_produit: id, nom: produit.nom, prix: parseFloat(produit.prix || 0), quantite: qty }); }

    select.value = '';
    document.getElementById('produit-qty').value = 1;
    renderPanier();
}

function removeLigne(idx) {
    lignesPanier.splice(idx, 1);
    renderPanier();
}

function renderPanier() {
    const container = document.getElementById('panier-list');
    const total     = lignesPanier.reduce((s, l) => s + l.prix * l.quantite, 0);

    if (!lignesPanier.length) {
        container.innerHTML = '<div class="panier-empty">Aucun produit ajouté</div>';
        document.getElementById('total-display').textContent = '0,00 €';
        return;
    }

    container.innerHTML = lignesPanier.map((l, idx) => `
        <div class="panier-item">
            <span class="panier-nom">${escHtml(l.nom)}</span>
            <span class="panier-qty">× ${l.quantite}</span>
            <span class="panier-prix">${(l.prix * l.quantite).toFixed(2)} €</span>
            <button class="btn-remove-ligne" onclick="removeLigne(${idx})" title="Retirer">✕</button>
        </div>`).join('');

    document.getElementById('total-display').textContent = total.toFixed(2).replace('.', ',') + ' €';
}

async function validerCommande() {
    if (!lignesPanier.length) { showToast('Ajoutez au moins un produit.', 'error'); return; }

    const type     = document.querySelector('input[name="type_commande"]:checked').value;
    const paiement = document.querySelector('input[name="mode_paiement"]:checked').value;
    const heure    = document.getElementById('heure-livraison').value || null;

    const body = {
        type_commande:   type,
        mode_paiement:   paiement,
        heure_livraison: heure,
        produits: lignesPanier.map(l => ({ id_produit: l.id_produit, quantite: l.quantite }))
    };

    const btn = document.getElementById('btn-valider');
    btn.disabled = true;
    btn.textContent = '⏳ Envoi en cours…';

    try {
        const result = await apiFetch(`${API_BASE}/admin/commandes`, { method: 'POST', body: JSON.stringify(body) });
        if (!result) { btn.disabled = false; btn.textContent = '🛒 Valider la commande'; return; }

        if (result.success) {
            const data = result.data || {};
            document.getElementById('confirm-num').textContent     = `Commande #${data.numero_commande || '—'}`;
            document.getElementById('confirm-chevalet').textContent = `Chevalet n° ${data.numero_chevalet || '—'}`;
            document.getElementById('confirm-overlay').classList.add('open');
            await loadReadyOrders();
        } else {
            showToast(result.error || 'Erreur lors de la commande.', 'error');
        }
    } catch (err) {
        console.error(err); showToast('Erreur réseau', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '🛒 Valider la commande';
    }
}

function closeConfirm() {
    document.getElementById('confirm-overlay').classList.remove('open');
    lignesPanier = [];
    renderPanier();
    document.getElementById('heure-livraison').value  = '';
    document.getElementById('produit-select').value   = '';
    document.getElementById('produit-qty').value      = 1;
    document.getElementById('type-sur-place').checked = true;
    document.getElementById('paiement-carte').checked = true;
}
