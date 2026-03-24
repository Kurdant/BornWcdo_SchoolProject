/* ============================================================
MODAL OPEN/CLOSE
============================================================ */

const API_BASE = 'https://wakdo-back.acadenice.fr/api';

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
LOAD API + INITIAL CREATION OF CATEGORIES AND FOOD ITEMS
============================================================ */

Promise.all([
  fetch(`${API_BASE}/categories`, { credentials: 'include' }).then(r => r.json()),
  fetch(`${API_BASE}/produits`,   { credentials: 'include' }).then(r => r.json())
]).then(([catRes, prodRes]) => {
  window.apiCategories = catRes.data;
  window.apiProduits   = prodRes.data;
  createCategories();
  createFoodItems();
  if (apiCategories.length > 0) displayFoodByCategory(apiCategories[0].id);
}).catch(error => console.error('Erreur API :', error));

/* ============================================================
CATEGORY CREATION + CATEGORY SELECTION
============================================================ */

const categorieList = document.getElementById('categorieList');

function createCategories() {
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
}

function selectCategory(categoryId) {
  const categories = document.querySelectorAll('.categorieItem');
  categories.forEach(cat => {
    cat.classList.remove('categorieItemSelected');
    if (cat.id == categoryId) cat.classList.add('categorieItemSelected');
  });
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

      // CAS BOISSON SEULE
      if (produit.id_categorie === 5) {
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
      });

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

// ✅ RESET CAROUSEL (nouvelle fonction)
function resetCarousel() {
    scrollIndex = 0;
    boissonsContainer.scrollTo({ 
        left: 0 * itemWidth, 
        behavior: 'instant' 
    });
}

function addBoissonMenu() {
    fetch(`${API_BASE}/boissons`, { credentials: 'include' })
        .then(r => r.json())
        .then(res => {
            const boissonsFroides = res.data;
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
                    console.log('Boisson sélectionnée id:', idMenu);
                });
            });

            // ✅ RESET APRÈS AJOUT DES ITEMS
            requestAnimationFrame(() => {
                boissonsContainer.scrollLeft = 0;
                scrollIndex = 0;
                console.log('FORCE RESET scrollLeft=0');
            });
        })
        .catch(error => console.error(error));
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
    fetch(`${API_BASE}/panier/ligne/${ligneId}`, { method: 'DELETE', credentials: 'include' })
      .catch(err => console.error('Erreur suppression panier API:', err));
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
  document.querySelector('.third-step')
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
      console.log('letape actuelle ', step)
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
PANIER API - HELPER
============================================================ */

function ajouterPanierAPI(produitId, quantite, details, callback) {
  fetch(`${API_BASE}/panier/ajouter`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ produit_id: produitId, quantite, details: details || null })
  })
  .then(r => r.json())
  .then(res => {
    const lignes = res.lignes || [];
    const matches = lignes.filter(l => l.id_produit === produitId);
    const ligneId = matches.length > 0 ? matches[matches.length - 1].id : null;
    if (callback) callback(ligneId);
  })
  .catch(err => {
    console.error('Erreur panier API:', err);
    if (callback) callback(null);
  });
}

/* ============================================================
ABANDON + PAYER
============================================================ */

document.querySelector('.panierEndingAbandon').addEventListener('click', () => {
  fetch(`${API_BASE}/panier`, { method: 'DELETE', credentials: 'include' })
    .finally(() => { window.location.href = 'accueil.html'; });
});

document.querySelector('.panierEndingPay').addEventListener('click', () => {
  const typeCommande   = sessionStorage.getItem('type_commande') || 'sur_place';
  const numeroChevalet = parseInt(sessionStorage.getItem('numero_chevalet') || '1', 10);

  fetch(`${API_BASE}/commande`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      type_commande:   typeCommande,
      numero_chevalet: numeroChevalet,
      mode_paiement:   'carte'
    })
  })
  .then(r => r.json())
  .then(res => {
    if (res.numero_commande) {
      sessionStorage.setItem('numero_commande', res.numero_commande);
      window.location.href = 'remerciement.html';
    } else {
      alert(res.error || 'Erreur lors de la commande');
    }
  })
  .catch(err => alert('Erreur réseau : ' + err));
});

/* ============================================================
CHANGE CATEGORIES WITH ARROWS
============================================================ */

const leftArrow = document.querySelector('.categorieArrowLeft');
const rightArrow = document.querySelector('.categorieArrowRight');
let currentCategorieIndex = 0;

function changeCategoriesArrows(direction) {
  const categories = document.querySelectorAll('.categorieItem');
  const total = categories.length;

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
  if (categoryItem) {
    selectCategory(categoryItem.id);
    displayFoodByCategory(categoryItem.id);
  }

  const allCategories = document.querySelectorAll('.categorieItem');
  const index = Array.from(allCategories).indexOf(categoryItem);
  currentCategorieIndex = index;
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

