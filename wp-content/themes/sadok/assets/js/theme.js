// Мобільне меню
document.getElementById('burger')?.addEventListener('click', () => {
  document.body.classList.toggle('nav-open');
});

// Фільтри: чекбокси одного атрибута збираємо у прихований інпут "filter_vik=1-rik,2-roky",
// бо саме такий формат розуміє WooCommerce
document.querySelectorAll('.filters').forEach((form) => {
  form.addEventListener('change', (e) => {
    const box = e.target;
    if (!box.dataset.filter) return;

    const name = `filter_${box.dataset.filter}`;
    const values = [...form.querySelectorAll(`[data-filter="${box.dataset.filter}"]:checked`)].map((b) => b.value);
    form.querySelector(`input[name="${name}"]`).value = values.join(',');
  });

  // порожні поля не тягнемо в URL — посилання виходять чистішими
  form.addEventListener('submit', () => {
    form.querySelectorAll('input[name]').forEach((input) => {
      if (!input.value) input.disabled = true;
    });
  });
});
