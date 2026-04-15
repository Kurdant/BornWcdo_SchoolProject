document.addEventListener('DOMContentLoaded', () => {
  // LOGIN BUTTON
  const loginBtn = document.getElementById('loginBtn');
  if (loginBtn) {
    loginBtn.addEventListener('click', () => {
      window.location.href = 'login.html';
    });
  }

  // CHOOSE DELIVERY MODE
  document.querySelectorAll('.chooseEat').forEach((item, index) => {
    item.addEventListener('click', () => {
      const old = document.querySelector('.chooseEatSelected');
      if (old) old.classList.remove('chooseEatSelected');
      item.classList.add('chooseEatSelected');

      const type = index === 0 ? 'sur_place' : 'a_emporter';
      sessionStorage.setItem('type_commande', type);

      window.location.href = 'menu-selection.html';
    });
  });
});
