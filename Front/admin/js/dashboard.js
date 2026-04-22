/**
 * dashboard.js — page tableau de bord (index)
 * Dépend de : utils.js
 */

/* ==========================================
   INITIALISATION
========================================== */
(async function init() {
    const role = sessionStorage.getItem('admin_role');
    const nom  = sessionStorage.getItem('admin_nom');
    if (!role) { window.location.replace('login.html'); return; }
    if (!['administration'].includes(role)) { window.location.replace('login.html'); return; }

    document.getElementById('user-nom').textContent  = nom  || '';
    document.getElementById('user-role').textContent = role || '';

    document.getElementById('date-display').textContent =
        new Date().toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    await loadCommandes();
})();

/* ==========================================
   CHARGEMENT ET AFFICHAGE DES COMMANDES
========================================== */
async function loadCommandes() {
    document.getElementById('commandes-tbody').innerHTML =
        '<tr class="loading-row"><td colspan="6"><span class="spinner"></span> Chargement…</td></tr>';

    try {
        const result = await apiFetch(`${API_BASE}/admin/commandes`);
        if (!result) return;
        if (!result.success) { showToast('Erreur chargement commandes', 'error'); return; }

        const commandes = result.data || [];

        // Filtrer les commandes du jour
        const today   = new Date().toISOString().split('T')[0];
        const duJour  = commandes.filter(c => (c.date_creation || '').startsWith(today));

        const enAttente = duJour.filter(c => c.statut === 'en_attente').length;
        const preparees = duJour.filter(c => c.statut === 'preparee').length;
        const caDuJour  = duJour.reduce((s, c) => s + parseFloat(c.montant_total || 0), 0);

        document.getElementById('stat-total').textContent     = duJour.length;
        document.getElementById('stat-attente').textContent   = enAttente;
        document.getElementById('stat-preparees').textContent = preparees;
        document.getElementById('stat-ca').textContent        = caDuJour.toFixed(2) + ' €';

        // Afficher les 10 dernières toutes dates confondues
        const dernieres = [...commandes]
            .sort((a, b) => new Date(b.date_creation || 0) - new Date(a.date_creation || 0))
            .slice(0, 10);

        renderTable(dernieres);
    } catch (err) {
        console.error('Erreur loadCommandes:', err);
        showToast('Erreur réseau', 'error');
    }
}

function renderTable(commandes) {
    const tbody = document.getElementById('commandes-tbody');

    if (!commandes.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="6">Aucune commande trouvée</td></tr>';
        return;
    }

    const badgeMap = {
        'en_attente': ['badge-orange', '⏳ En attente'],
        'preparee':   ['badge-blue',   '✅ Préparée'],
        'livree':     ['badge-green',  '🟢 Livrée']
    };
    const typeMap = { 'sur_place': '🪑 Sur place', 'a_emporter': '🥡 À emporter' };

    tbody.innerHTML = commandes.map(c => {
        const [cls, lbl] = badgeMap[c.statut] || ['badge-orange', escHtml(c.statut)];
        const heure = c.date_creation
            ? new Date(c.date_creation).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
            : '—';
        return `
            <tr>
                <td><strong>#${escHtml(c.numero_commande || String(c.id))}</strong></td>
                <td>${escHtml(String(c.numero_chevalet || '—'))}</td>
                <td>${typeMap[c.type_commande] || escHtml(c.type_commande || '—')}</td>
                <td><strong>${parseFloat(c.montant_total || 0).toFixed(2)} €</strong></td>
                <td><span class="badge ${cls}">${lbl}</span></td>
                <td>${heure}</td>
            </tr>`;
    }).join('');
}
