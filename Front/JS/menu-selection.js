/* ============================================================
MODAL OPEN/CLOSE
============================================================ */

// Bloc 1 : catalogue servi depuis des fichiers JSON statiques (Ajax → JSON).
// Panier géré côté client (sessionStorage) — aucune dépendance backend.
const JSON_BASE = './BDD_JSON';

// Mapping clés produits.json ⇢ ids de categories.json
const CATEGORIE_MAP = {
  menus: 1, boissons: 2, burgers: 3, frites: 4,
  encas: 5, wraps: 6, salades: 7, desserts: 8, sauces: 9
};

// URL d'API fictive pour l'envoi final du détail de commande (sujet Bloc 1).
// Accepte n'importe quel POST JSON et le renvoie — utile pour démontrer l'échange.
const FAKE_ORDER_API = 'https://httpbin.org/post';



Promise.all([
  fetch(`${JSON_BASE}/categories.json`).then(r => r.json()),
  fetch(`${JSON_BASE}/produits.json`).then(r => r.json())
]).then(([rawCats, rawProds]) => {
  // Normalisation catégories : on expose `nom` (majuscule initiale) pour l'UI.
  window.apiCategories = rawCats.map(c => ({
    id: c.id,
    nom: (c.title || '').charAt(0).toUpperCase() + (c.title || '').slice(1),
    image: c.image
  }));

  // Aplatissement produits : produits.json est un objet { menus:[...], burgers:[...], ... }
  // On reconstruit une liste plate avec `id_categorie` dérivé de la clé.
  const produits = [];
  Object.entries(rawProds).forEach(([cle, liste]) => {
    const idCat = CATEGORIE_MAP[cle];
    if (!idCat || !Array.isArray(liste)) return;
    liste.forEach(p => produits.push({
      id:           p.id,
      nom:          p.nom,
      prix:         p.prix,
      image:        p.image,
      description:  p.description || '',
      id_categorie: idCat,
      stock:        99,    // stock générique pour la borne (Bloc 1, pas de gestion stock)
      disponible:   true
    }));
  });
  window.apiProduits = produits;

  // Hydratation du panier depuis sessionStorage (persistant pendant la session onglet).
  window.apiPanierLignes = loadPanierLocal();

  createCategories();
  createFoodItems();
  if (apiCategories.length > 0) displayFoodByCategory(apiCategories[0].id);
}).catch(error => console.error('Erreur chargement JSON :', error));


// Les images JSON utilisent des chemins de type "/burgers/xxx.png" → on les préfixe
// avec le dossier BDD_JSON/ qui contient les assets fournis.
function imgUrl(path) {
  if (!path) return 'images/logo.png';
  return `${JSON_BASE}${path}`;
}

/* ============================================================
PANIER LOCAL (sessionStorage)
============================================================ */

const PANIER_KEY = 'wcdo_panier_lignes';

function loadPanierLocal() {
  try {
    return JSON.parse(sessionStorage.getItem(PANIER_KEY) || '[]');
  } catch (_) {
    return [];
  }
}

function savePanierLocal() {
  sessionStorage.setItem(PANIER_KEY, JSON.stringify(window.apiPanierLignes || []));
}

// Quantité déjà présente dans le panier courant pour un produit donné.
// Utilisé pour empêcher de dépasser le stock disponible (somme panier + demande ≤ stock).
function getQuantiteProduitPanier(produitId) {
  const lignes = window.apiPanierLignes || [];
  return lignes
    .filter(l => l.id_produit === produitId)
    .reduce((s, l) => s + (parseInt(l.quantite, 10) || 0), 0);
}

function getStockProduit(produitId) {
  const p = (window.apiProduits || []).find(x => x.id === produitId);
  return p ? parseInt(p.stock, 10) : 0;
}


// Convertit les chemins d'images renvoyés par l'API (/Front/images/...)
// en chemins valides pour le front servi depuis wakdo-front.acadenice.fr
function imgUrl(path) {
    if (!path) return 'images/logo.png';
    return path.replace(/^\/Front/, '');
}

const modal = document.getElementById('modal');
const modalClose = document.getElementById('closeModal');
let prixMenu = 0;
let menuComposition = [];
let prixFinal = [0];
let currentBoisson = null;
let currentMenuIndexPrix = null; 
let isMaxiSelected = false;
let currentMenuProduitId = null;

let tailleItem = 0;
let is50clSelected = false;

let step = 0;

document.getElementById('closeBoissonModal').addEventListener('click', closeModal);

function openModal() {
  modal.style.display = 'flex';
}

function closeModal() {
  modal.style.display = 'none';
  document.querySelector('.first-step').style.display = 'none';
  document.querySelector('.second-step').style.display = 'none';
  document.querySelector('.third-step').style.display = 'none';
  menuComposition.length = 0;
  updateBackDisplay();
  step = 0;

  is50clSelected = false;
  isMaxiSelected = false;  

  document.querySelectorAll('.modalMenuItemSelected, .tailleItem.modalMenuItemSelected')
  .forEach(item => item.classList.remove('modalMenuItemSelected'));
  incrementationNombre = 1;
  chiffre.textContent = incrementationNombre; // Update display
  document.getElementById('nextStep').style.display = "block";
}
modalClose.addEventListener('click', closeModal);



/* ============================================================
AFFICHAGE INFOS CLIENT (si connecté)
============================================================ */

const nomEl    = document.getElementById('clientNom');
const pointsEl = document.getElementById('clientPoints');
const infoBox  = document.getElementById('clientInfo');
const clientNomStored    = sessionStorage.getItem('client_name');
const clientPointsStored = sessionStorage.getItem('client_points');

if (clientNomStored) {
    nomEl.textContent    = clientNomStored;
    pointsEl.textContent = clientPointsStored || '0';
    infoBox.style.display = 'flex';
}



/* ============================================================
CATEGORY CREATION + CATEGORY SELECTION
============================================================ */

const categorieList = document.getElementById('categorieList');

function createCategories() {
  categorieList.innerHTML = '';
  apiCategories.forEach((categorie, idx) => {
    const div = document.createElement('div');
    div.classList.add('categorieItem');
    if (idx === 0) div.classList.add('categorieItemSelected');
    div.id = categorie.id;

    const label = document.createElement('div');
    label.classList.add('categorieLabel');
    label.textContent = categorie.nom;

    div.appendChild(label);
    categorieList.appendChild(div);
  });

  currentCategorieIndex = 0;
  updateCategoryArrowsState();
  scrollSelectedCategoryIntoView();
}

function selectCategory(categoryId) {
  const categories = document.querySelectorAll('.categorieItem');
  categories.forEach(cat => {
    cat.classList.remove('categorieItemSelected');
    if (cat.id == categoryId) cat.classList.add('categorieItemSelected');
  });

  const nextIndex = Array.from(categories).findIndex(cat => cat.id == categoryId);
  if (nextIndex >= 0) {
    currentCategorieIndex = nextIndex;
  }
  scrollSelectedCategoryIntoView();
  updateCategoryArrowsState();
}

function displayFoodByCategory(categoryId) {
  const allFoods = document.querySelectorAll('.foodItem');
  allFoods.forEach(food => {
    food.style.display = food.dataset.category == categoryId ? 'flex' : 'none';
  });
}

/* ============================================================
OPEN MODAL FOR MENU OR DRINKS
============================================================ */

function openForMenu() {
  document.querySelector('.first-step').style.display = 'block';
  document.querySelector('.modalBoisson').style.display = 'none';
  document.querySelector('.modalTailleBoisson').style.display = 'none';
}

function openForBoisson() {
  document.querySelector('.modalBoisson').style.display = 'block';
  document.querySelector('.first-step').style.display = 'none';
  document.querySelector('.modalTailleBoisson').style.display = 'none';
}

function openForTailleBoisson() {
  document.querySelector('.modalTailleBoisson').style.display = 'block';
  document.querySelector('.first-step').style.display = 'none';
  document.querySelector('.modalBoisson').style.display = 'none';
}

/* ============================================================
FOOD ITEMS CREATION
============================================================ */

function createFoodItems() {
  const foodContainer = document.getElementById('foodList');

  apiProduits.forEach(produit => {
    const imgSrc = imgUrl(produit.image);
    const div = document.createElement('div');
    div.classList.add('foodItem');
    div.dataset.category = produit.id_categorie;
    div.id = `food-${produit.id}`;

    div.innerHTML = `
      <img src="${imgSrc}" alt="${produit.nom}" />
      <h3>${produit.nom}</h3>
      <p>${produit.description || ''}</p>
      <span>${parseFloat(produit.prix).toFixed(2)}€</span>
    `;

    const epuise = produit.stock <= 0 || produit.disponible === false;
    if (epuise) {
      div.classList.add('out-of-stock');
    } else {
      div.addEventListener('click', () => {
      const nom  = produit.nom;
      const prix = parseFloat(produit.prix);

      // CAS MENU
      if (produit.id_categorie === 1) {
        prixMenu = prix;
        console.log('Prix du menu sélectionné :', prixMenu);
        openForMenu();
        openModal();
        menuComposition = [nom];
        currentMenuProduitId = produit.id;
        return;
      }

      // CAS BOISSON SEULE (catégorie "boissons" = id 2 dans categories.json)
      if (produit.id_categorie === 2) {
        openForTailleBoisson();
        openModal();
        document.getElementById('nextStep').style.display = "none";

        const imgSmall = document.getElementById('boissonTailleSmall');
        const imgLarge = document.getElementById('boissonTailleLarge');
        if (imgSmall && imgLarge) {
          imgSmall.src = imgSrc;
          imgSmall.alt = produit.nom + ' 30cl';
          imgLarge.src = imgSrc;
          imgLarge.alt = produit.nom + ' 50cl';
        }
        currentBoisson = produit;
        document.querySelectorAll('.tailleItem').forEach(item => {
          item.addEventListener('click', () => {
            is50clSelected = item.dataset.boissonid === '2';
            const old = document.querySelector('.modalMenuItemSelected');
            if (old) old.classList.remove('modalMenuItemSelected');
            item.classList.add('modalMenuItemSelected');
          });
        });
        return;
      }

      // CAS PRODUIT SIMPLE
      ajouterPanierAPI(produit.id, 1, null, (ligneId) => {
        AjoutProduitSimple(nom, prix, ligneId);
      });
      }); // fin addEventListener click
    } // fin else (produit dispo)

    foodContainer.appendChild(div);
  });
}

const envoie = document.getElementById('sendBoisson');
envoie.addEventListener('click', () => {
  if (!currentBoisson) return;

  let prixUnite = parseFloat(currentBoisson.prix);
  if (is50clSelected) prixUnite += 0.50;

  const qte = incrementationNombre;
  const prixFinalBoisson = prixUnite * qte;
  const details = { taille: is50clSelected ? '50cl' : '30cl' };

  ajouterPanierAPI(currentBoisson.id, qte, details, (ligneId) => {
    AjoutProduitSimple(currentBoisson.nom, prixFinalBoisson, ligneId);
    currentBoisson = null;
    is50clSelected = false;
    incrementationNombre = 1;
    chiffre.textContent = incrementationNombre;
    document.getElementById('nextStep').style.display = "block";
    closeModal();
  });
});

/* ============================================================
MODAL MENU ITEM SELECTION
============================================================ */

let idMenu = null;

document.querySelectorAll('.modalMenuItem').forEach(item => {
  item.addEventListener('click', () => {
    const old = document.querySelector('.modalMenuItemSelected');
    if (old) old.classList.remove('modalMenuItemSelected');

    if (item.id === '1') {
    isMaxiSelected = true;
    }

    item.classList.add('modalMenuItemSelected');
    idMenu = item.id;
  });
});

/* ============================================================
BOISSONS SLIDER + LOADING DRINKS IN STEP 3
============================================================ */

const boissonsContainer = document.getElementById('boissonsMenu');
const leftArrowBoisson = document.querySelector('.boissonsArrowLeft');
const rightArrowBoisson = document.querySelector('.boissonsArrowRight');

let scrollIndex = 0;
const itemWidth = 160;

function scrollToIndex(index) {
    const maxIndex = Math.floor((boissonsContainer.scrollWidth - boissonsContainer.clientWidth) / itemWidth);
    scrollIndex = Math.max(0, Math.min(index, maxIndex));
    
    boissonsContainer.scrollTo({
        left: scrollIndex * itemWidth,
        behavior: 'smooth'
    });
}

// GAUCHE
leftArrowBoisson.addEventListener('click', () => {
    scrollToIndex(scrollIndex - 1);
});

// DROITE
rightArrowBoisson.addEventListener('click', () => {
    scrollToIndex(scrollIndex + 1);
});

// SYNC scrollIndex avec scroll manuel
// boissonsContainer.addEventListener('scroll', () => {
//     const scrollLeft = boissonsContainer.scrollLeft;
//     scrollIndex = Math.round(scrollLeft / itemWidth);
// });

// RESET CAROUSEL (nouvelle fonction)
function resetCarousel() {
    scrollIndex = 0;
    boissonsContainer.scrollTo({ 
        left: 0 * itemWidth, 
        behavior: 'instant' 
    });
}

function addBoissonMenu() {
    // Boissons disponibles dans le menu = catégorie id=2 (categories.json)
    const boissonsFroides = (window.apiProduits || []).filter(p => p.id_categorie === 2);
    const container = document.getElementById('boissonsMenu');

    container.innerHTML = '';

    boissonsFroides.forEach(produit => {
        const div = document.createElement('div');
        div.classList.add('boissonsFroidMenu', 'modalMenuItem');
        div.id = `${produit.id}`;
        div.innerHTML = `
            <img src="${imgUrl(produit.image)}" alt="${produit.nom}" />
            <h3 class='modalMenuLabel'>${produit.nom}</h3>
        `;
        container.appendChild(div);
        div.addEventListener('click', () => {
            const old = container.querySelector('.modalMenuItemSelected');
            if (old) old.classList.remove('modalMenuItemSelected');
            div.classList.add('modalMenuItemSelected');
            idMenu = div.id;
        });
    });

    // Reset du carousel après rendu
    requestAnimationFrame(() => {
        boissonsContainer.scrollLeft = 0;
        scrollIndex = 0;
    });
}

function addSaucesMenu() {
  const container = document.getElementById('saucesMenu');
  if (container.children.length > 0) return;
  // Sauces = catégorie id=9 (categories.json)
  const sauces = (window.apiProduits || []).filter(p => p.id_categorie === 9);
  container.innerHTML = '';
  sauces.forEach(sauce => {
    const div = document.createElement('div');
    div.classList.add('modalMenuItem');
    div.dataset.sauceid = sauce.id;
    div.innerHTML = `<div class="modalMenuLabel">${sauce.nom}</div>`;
    div.addEventListener('click', () => {
      container.querySelectorAll('.modalMenuItem').forEach(el => el.classList.remove('modalMenuItemSelected'));
      div.classList.add('modalMenuItemSelected');
    });
    container.appendChild(div);
  });
}

/* ============================================================
BACK BUTTON DISPLAY (SHOW ONLY IF SELECTION EXISTS)
============================================================ */

const backModal = document.getElementById('backModal');

function updateBackDisplay() {
  backModal.style.display = menuComposition.length === 0 ? 'none' : 'block';
}
updateBackDisplay();

/* ============================================================
SUPPRESSION DANS LE PANIER
============================================================ */

function panierTrash(event) {
  const item = event.target.closest('.panierOrderItem');
  if (!item) return;

  const indexPrix = item.dataset.indexPrix;
  const ligneId   = item.dataset.ligneId;

  if (indexPrix !== undefined) prixFinal[indexPrix] = 0;

  item.remove();
  prixFinalCalc();

  if (ligneId) {
    // Suppression locale (panier en sessionStorage, plus d'appel backend).
    window.apiPanierLignes = (window.apiPanierLignes || [])
      .filter(l => String(l.id) !== String(ligneId));
    savePanierLocal();
  }
}

document.addEventListener('click', e => {
  if (e.target.classList.contains('panierTrash')) panierTrash(e);
});


/* ============================================================
AJOUT AU PANIER (MENU COMPLET)
============================================================ */


function AjoutAuPanier() {
  const panier = document.querySelector('.panierOrder');

  console.log(menuComposition);

  const typeMenu = menuComposition[1];
  const burger = menuComposition[0];

  const titre = `1 ${typeMenu} ${burger}`;

  const indexPrix = prixFinal.length;
  if (isMaxiSelected) {
    prixMenu += 0.50;
    isMaxiSelected = false;
  }
  prixFinal.push(prixMenu);
  prixFinalCalc();

  // Sync avec le panier API
  if (currentMenuProduitId) {
    ajouterPanierAPI(currentMenuProduitId, 1, { composition: menuComposition }, () => {});
  }

  const item = document.createElement('div');
  item.classList.add('panierOrderItem');

  item.dataset.indexPrix = indexPrix;

  const title = document.createElement('div');
  title.classList.add('panierOrderTitle');
  title.textContent = titre;

  const trash = document.createElement('span');
  trash.classList.add('panierTrash');
  trash.innerHTML = '&#128465;';

  const ul = document.createElement('ul');

  for (let i = 2; i < menuComposition.length; i++) {
    const li = document.createElement('li');
    li.textContent = menuComposition[i];
    ul.appendChild(li);
  }

  item.appendChild(title);
  item.appendChild(trash);
  item.appendChild(ul);
  panier.appendChild(item);
}


/* ============================================================
AJOUT PRODUIT SIMPLE
============================================================ */

function AjoutProduitSimple(nom, prix, ligneId) {
  const panier = document.querySelector('.panierOrder');

  const indexPrix = prixFinal.length;

  prixFinal.push(prix);

  const item = document.createElement('div');
  item.classList.add('panierOrderItem');

  item.dataset.indexPrix = indexPrix;
  if (ligneId) item.dataset.ligneId = ligneId;

  const title = document.createElement('div');
  title.classList.add('panierOrderTitle');
  title.textContent = `${nom} - ${prix}€`;

  const trash = document.createElement('span');
  trash.classList.add('panierTrash');
  trash.innerHTML = '&#128465;';

  item.appendChild(title);
  item.appendChild(trash);
  panier.appendChild(item);

  prixFinalCalc();
}

/* ============================================================
STEP BUTTON ACTIONS
============================================================ */

const steps = [
  document.querySelector('.first-step'),
  document.querySelector('.second-step'),
  document.querySelector('.third-step'),
  document.querySelector('.fourth-step')
];
const nextBtn = document.getElementById('nextStep');

function renderStep() {
  steps.forEach((el, i) => {
    el.style.display = i === step ? 'block' : 'none';
  });

  nextBtn.textContent = step === steps.length - 1 ? 'Ajouter à la commande' : 'Étape suivante';
  console.log('Affichage step:', step);
}

function getSelectedChoice() {
  return steps[step].querySelector('.modalMenuItemSelected');
}

function nextStep() {
  const selected = getSelectedChoice();
  if (!selected && step !== steps.length - 1) {
    console.log('Impossible de passer à l’étape suivante : aucun choix sélectionné');
    alert('Veuillez sélectionner un choix avant de continuer.');
    return;
  }
  if (selected) {
    const label = selected.querySelector('.modalMenuLabel').textContent;
    console.log('Ajout du choix sélectionné à menuComposition:', label);
    menuComposition.push(label);
    console.log('menuComposition:', menuComposition);
  }
  if (step < steps.length - 1) {
    step++;
    console.log('Step suivante. Nouvel index:', step);
    if (step === 2) {
      console.log('Chargement des boissons');
      addBoissonMenu();
    }
    if (step === 3) {
      console.log('Chargement des sauces');
      addSaucesMenu();
    }
    renderStep();
    updateBackDisplay();
  } else {
    AjoutAuPanier();
    console.log('Ajout final à la commande terminé');
    closeModal();
    step = 0;
    renderStep();
    menuComposition.length = 0;
    console.log('Steps et tableau réinitialisés');
  }
}

function prevStep() {
  console.log('Retour arrière. Step actuel avant retour:', step);
  if (menuComposition.length > 0) {
    const removed = menuComposition.pop();
    console.log('Suppression du dernier élément ajouté:', removed, menuComposition);
  }
  step = Math.max(0, step - 1);
  console.log('Step après retour:', step);
  renderStep();
  updateBackDisplay();
}

renderStep();

nextBtn.addEventListener('click', nextStep);
document.getElementById('backModal').addEventListener('click', prevStep);

/* ============================================================
ASYNC LOADING FOR MENUS (IF NEEDED LATER)
============================================================ */

let dataJson = null;

async function createMenus() {
  const r = await fetch('./bd.json');
  dataJson = await r.json();
}

/* ============================================================
PANIER LOCAL - AJOUT
============================================================ */

// Ajoute une ligne au panier local (sessionStorage). Signature inchangée pour
// ne pas impacter les appelants (AjoutProduitSimple, AjoutAuPanier, etc.).
function ajouterPanierAPI(produitId, quantite, details, callback) {
  // Pré-check stock : on refuse d'ajouter plus que le stock théorique du produit.
  const stock = getStockProduit(produitId);
  const dejaPanier = getQuantiteProduitPanier(produitId);
  if (stock > 0 && dejaPanier + quantite > stock) {
    const restant = Math.max(0, stock - dejaPanier);
    alert(
      restant === 0
        ? `Stock épuisé : vous avez déjà ${dejaPanier} unité(s) de ce produit dans votre panier.`
        : `Stock insuffisant : il ne reste que ${restant} unité(s) disponible(s) (vous en avez déjà ${dejaPanier} dans votre panier).`
    );
    if (callback) callback(null);
    return;
  }

  const produit = (window.apiProduits || []).find(p => p.id === produitId);
  if (!produit) {
    alert('Produit introuvable.');
    if (callback) callback(null);
    return;
  }

  // RG-003 : supplément 0,50 € pour la grande taille boisson
  let prixUnitaire = parseFloat(produit.prix);
  if (details && details.taille === '50cl') {
    prixUnitaire += 0.50;
  }

  const ligne = {
    id: Date.now() + Math.floor(Math.random() * 1000), // id local unique suffisant pour le panier client
    id_produit: produitId,
    nom: produit.nom,
    quantite: quantite,
    prix_unitaire: parseFloat(prixUnitaire.toFixed(2)),
    sous_total: parseFloat((prixUnitaire * quantite).toFixed(2)),
    details: details || {}
  };

  window.apiPanierLignes = window.apiPanierLignes || [];
  window.apiPanierLignes.push(ligne);
  savePanierLocal();

  if (callback) callback(ligne.id);
}

/* ============================================================
ABANDON + VALIDATION COMMANDE
============================================================ */

document.querySelector('.panierEndingAbandon').addEventListener('click', () => {
  fetch(`${API_BASE}/panier`, { method: 'DELETE', credentials: 'include' })
    .finally(() => { window.location.href = 'accueil.html'; });
});

document.querySelector('.panierEndingPay').addEventListener('click', () => {
  const typeCommande = sessionStorage.getItem('type_commande') || 'sur_place';

  if (typeCommande === 'sur_place') {
    window.location.href = 'table-number.html';
    return;
  }

  // À emporter : commande directe sans chevalet
  const modePaiement = sessionStorage.getItem('mode_paiement') || 'carte';
  const clientId     = sessionStorage.getItem('client_id') ? parseInt(sessionStorage.getItem('client_id')) : null;

  fetch(`${API_BASE}/commande`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      type_commande: typeCommande,
      mode_paiement: modePaiement,
      client_id:     clientId
    })
  })
  .then(r => r.json())
  .then(res => {
    console.log('Réponse commande :', res);
    const commande = res.data?.commande;
    if (commande?.numero_commande) {
      sessionStorage.setItem('numero_commande', commande.numero_commande);
      sessionStorage.setItem('numero_chevalet', commande.numero_chevalet);
      window.location.href = 'remerciement.html';
    } else {
      alert(res.message || res.error || 'Erreur lors de la commande');
    }
  })
  .catch(err => alert('Erreur réseau : ' + err));
});

/* ============================================================
CHANGE CATEGORIES WITH ARROWS
============================================================ */

const leftArrow = document.querySelector('.categorieSelection .categorieArrowLeft');
const rightArrow = document.querySelector('.categorieSelection .categorieArrowRight');
let currentCategorieIndex = 0;

function scrollSelectedCategoryIntoView() {
  const categories = document.querySelectorAll('.categorieItem');
  const selectedCategory = categories[currentCategorieIndex];

  if (!selectedCategory) {
    return;
  }

  selectedCategory.scrollIntoView({
    behavior: 'smooth',
    inline: 'center',
    block: 'nearest'
  });
}

function updateCategoryArrowsState() {
  const hasOverflow = categorieList.scrollWidth > categorieList.clientWidth + 4;

  leftArrow.classList.toggle('is-disabled', !hasOverflow);
  rightArrow.classList.toggle('is-disabled', !hasOverflow);
}

function changeCategoriesArrows(direction) {
  const categories = document.querySelectorAll('.categorieItem');
  const total = categories.length;

  if (!total) {
    return;
  }

  if (direction === 'next') {
    currentCategorieIndex = (currentCategorieIndex + 1) % total;
  } else {
    currentCategorieIndex = (currentCategorieIndex - 1 + total) % total;
  }

  const target = categories[currentCategorieIndex];
  selectCategory(target.id);
  displayFoodByCategory(target.id);
}

rightArrow.addEventListener('click', () => {
  changeCategoriesArrows('next');
});

leftArrow.addEventListener('click', () => {
  changeCategoriesArrows('prev');
});

categorieList.addEventListener('click', (event) => {
  const categoryItem = event.target.closest('.categorieItem');
  if (!categoryItem) {
    return;
  }

  selectCategory(categoryItem.id);
  displayFoodByCategory(categoryItem.id);
});

window.addEventListener('resize', () => {
  updateCategoryArrowsState();
  scrollSelectedCategoryIntoView();
});

/* ============================================================
CALC FINAL PRICE
============================================================ */

const prixFinalID = document.getElementById('prixFinal');

function prixFinalCalc() {
  const total = prixFinal.reduce((acc, val) => acc + val, 0);
  prixFinalID.textContent = `${total.toFixed(2)}€`;
}

/* ============================================================
INCREMENTATION BOISSONS
============================================================ */

const plus = document.getElementById('IncrémentationPlus');
const moins = document.getElementById('IncrémentationMinus');
const chiffre = document.getElementById('IncrémentationNumber');

let incrementationNombre = 1;

plus.addEventListener('click', () => {
  // On borne l'incrément par le stock restant pour le produit courant (boisson).
  // Le calcul tient compte des unités déjà présentes dans le panier.
  if (currentBoisson) {
    const stock = parseInt(currentBoisson.stock, 10) || 0;
    const dejaPanier = getQuantiteProduitPanier(currentBoisson.id);
    if (incrementationNombre + dejaPanier >= stock) {
      const restant = Math.max(0, stock - dejaPanier);
      alert(
        restant === 0
          ? `Stock épuisé pour "${currentBoisson.nom}".`
          : `Stock limité : maximum ${restant} unité(s) pour "${currentBoisson.nom}".`
      );
      return;
    }
  }
  incrementationNombre++;
  chiffre.textContent = incrementationNombre;
});

moins.addEventListener('click', () => {
  if(incrementationNombre == 1){
    alert('tu peux pas commander 0 boissons')
  } else
  incrementationNombre--;                          
  chiffre.textContent = incrementationNombre;
});
