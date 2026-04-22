/**
 * preparation.js — page préparation des commandes (dark theme)
 * Dépend de : utils.js
 */

let commandesEnAttente = [];
let refreshTimer       = null;
const REFRESH_INTERVAL = 30000;
let audioCtx           = null;

/* ==========================================
   SON DE NOTIFICATION (Web Audio API)
========================================== */
function playNotifSound() {
    try {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        [523, 659].forEach((freq, i) => {
            const osc  = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain); gain.connect(audioCtx.destination);
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime + i * 0.18);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + i * 0.18 + 0.35);
            osc.start(audioCtx.currentTime + i * 0.18);
            osc.stop(audioCtx.currentTime + i * 0.18 + 0.36);
        });
    } catch (e) { /* Web Audio non disponible */ }
}

// Initialiser AudioContext au premier clic (politique navigateur)
document.addEventListener('click', function initAudio() {
    if (!audioCtx) {
        try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {}
    }
}, { once: true });

/* ==========================================
   INITIALISATION
========================================== */
(async function init() {
    checkAuth(['preparation', 'administration']);
    document.getElementById('user-nom').textContent = sessionStorage.getItem('admin_nom') || '';
    await loadCommandes(true);
    startAutoRefresh();
})();

function startAutoRefresh() {
    if (refreshTimer) clearInterval(refreshTimer);
    refreshTimer = setInterval(() => loadCommandes(false), REFRESH_INTERVAL);
}

/* ==========================================
   CHARGEMENT
========================================== */
async function loadCommandes(showLoader = false) {
    if (showLoader) document.getElementById('loading-overlay').classList.add('show');

    try {
        const result = await apiFetch(`${API_BASE}/admin/commandes/preparation`);
        if (!result) return;
        if (!result.success) { showToast('Erreur chargement commandes', 'error'); return; }

        const nouvelles = result.data || [];

        // Notifier si de nouvelles commandes apparaissent (pas au 1er chargement)
        const ancienIds  = new Set(commandesEnAttente.map(c => c.id));
        const nouveauxId = nouvelles.filter(c => !ancienIds.has(c.id));
        if (nouveauxId.length > 0 && commandesEnAttente.length > 0) {
            playNotifSound();
            showToast(`🔔 ${nouveauxId.length} nouvelle(s) commande(s) !`, 'info');
        }

        commandesEnAttente = nouvelles;
        renderCommandes();

        document.getElementById('count-attente').textContent = nouvelles.length;
        document.getElementById('last-refresh').textContent  =
            'Mis à jour à ' + new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    } catch (err) {
        console.error('Erreur loadCommandes:', err);
        showToast('Erreur réseau', 'error');
    } finally {
        document.getElementById('loading-overlay').classList.remove('show');
    }
}

/* ==========================================
   RENDU DES CARDS
========================================== */
function renderCommandes() {
    const grid       = document.getElementById('orders-grid');
    const emptyState = document.getElementById('empty-state');

    if (!commandesEnAttente.length) {
        grid.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    emptyState.style.display = 'none';

    // Trier par heure de livraison (urgence), puis par date de création
    const sorted = [...commandesEnAttente].sort((a, b) => {
        if (a.heure_livraison && b.heure_livraison) {
            return a.heure_livraison.localeCompare(b.heure_livraison);
        }
        if (a.heure_livraison) return -1;
        if (b.heure_livraison) return  1;
        return new Date(a.date_creation || 0) - new Date(b.date_creation || 0);
    });

    grid.innerHTML = sorted.map(c => buildOrderCard(c)).join('');
}

function buildOrderCard(c) {
    const typeClass = c.type_commande === 'sur_place' ? 'type-sur-place' : 'type-a-emporter';
    const typeLabel = c.type_commande === 'sur_place' ? '🪑 Sur place' : '🥡 À emporter';

    let heureHtml = '';
    if (c.heure_livraison) {
        const now  = new Date();
        const livr = new Date();
        const [h, m] = c.heure_livraison.split(':');
        livr.setHours(parseInt(h), parseInt(m), 0, 0);
        const diffMin = (livr - now) / 60000;
        const cls = diffMin < 5 ? 'heure-urgente' : 'heure-normale';
        heureHtml = `<span class="heure-livraison ${cls}">⏰ ${escHtml(c.heure_livraison)}</span>`;
    }

    const produits = Array.isArray(c.lignes) ? c.lignes : (Array.isArray(c.produits) ? c.produits : []);
    const produitsHtml = produits.length
        ? `<div class="products-title">Produits</div>
           <ul class="products-list">
               ${produits.map(p => `
                   <li>
                       <span>${escHtml(p.nom_produit || p.nom || 'Produit')}</span>
                       <span class="qty">×${p.quantite || 1}</span>
                   </li>`).join('')}
           </ul>`
        : '<p style="color:rgba(255,255,255,0.3);font-size:0.82rem;font-style:italic;">Détail non disponible</p>';

    return `
        <div class="order-card" id="card-${c.id}">
            <div class="card-top">
                <div class="order-num">
                    #${escHtml(c.numero_commande || String(c.id))}
                    <span>Commande</span>
                </div>
                <span class="type-badge ${typeClass}">${typeLabel}</span>
            </div>
            <div class="card-body">
                <div class="card-meta">
                    <span class="chevalet">🏷️ Chevalet ${escHtml(String(c.numero_chevalet || '—'))}</span>
                    ${heureHtml}
                </div>
                ${produitsHtml}
            </div>
            <div class="card-footer">
                <button class="btn-prete" onclick="marquerPrete(${c.id}, this)">
                    ✓ Commande prête
                </button>
            </div>
        </div>`;
}

/* ==========================================
   MARQUER PRÊTE
========================================== */
async function marquerPrete(id, btn) {
    btn.disabled = true;
    btn.textContent = '⏳ Traitement…';

    try {
        const result = await apiFetch(`${API_BASE}/admin/commandes/${id}/preparer`, { method: 'PUT' });
        if (!result) { btn.disabled = false; btn.textContent = '✓ Commande prête'; return; }

        if (result.success) {
            const card = document.getElementById(`card-${id}`);
            if (card) {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity    = '0';
                card.style.transform  = 'scale(0.95)';
                setTimeout(() => {
                    card.remove();
                    commandesEnAttente = commandesEnAttente.filter(c => c.id !== id);
                    document.getElementById('count-attente').textContent = commandesEnAttente.length;
                    if (!commandesEnAttente.length) {
                        document.getElementById('empty-state').style.display = 'block';
                    }
                }, 300);
            }
            showToast('✅ Commande marquée comme prête !');
        } else {
            showToast(result.error || 'Erreur lors de la mise à jour.', 'error');
            btn.disabled = false; btn.textContent = '✓ Commande prête';
        }
    } catch (err) {
        console.error(err);
        showToast('Erreur réseau', 'error');
        btn.disabled = false; btn.textContent = '✓ Commande prête';
    }
}
