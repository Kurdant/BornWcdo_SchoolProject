document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.chooseEat').forEach((item, index) => {
    item.addEventListener('click', () => {
      const old = document.querySelector('.chooseEatSelected');
      if (old) old.classList.remove('chooseEatSelected');
      item.classList.add('chooseEatSelected');

      const type = index === 0 ? 'sur_place' : 'a_emporter';
      sessionStorage.setItem('type_commande', type);

      if (type === 'sur_place') {
        window.location.href = 'table-number.html';
      } else {
        sessionStorage.setItem('numero_chevalet', '1');
        window.location.href = 'menu-selection.html';
      }
    });
  });
});
