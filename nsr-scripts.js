document.addEventListener('DOMContentLoaded', function () {
  const reminder = document.querySelector('.nsr-auto-reminder');

  if (reminder) {
    let visible = true;
    setInterval(() => {
      visible = !visible;
      reminder.style.opacity = visible ? '1' : '.45';
    }, 4000);
  }

  document.addEventListener('keydown', function (e) {
    const tag = (document.activeElement && document.activeElement.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

    const map = {
      r: 'Reveal Item',
      t: 'Start Timer',
      n: 'Next Item'
    };

    const wanted = map[e.key.toLowerCase()];
    if (!wanted) return;

    const btn = Array.from(document.querySelectorAll('button')).find(
      b => b.textContent.trim() === wanted
    );

    if (btn) btn.click();
  });
});
