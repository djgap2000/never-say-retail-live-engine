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

  const wrap = document.querySelector('.nsr-front-wrap');
  if (!wrap) return;

  const ajaxUrl = wrap.dataset.ajax;
  let currentSignature = wrap.dataset.signature || '';
  let timerEnd = parseInt(wrap.dataset.timerEnd || '0', 10);

  const timerEl = document.getElementById('nsr-live-timer');

  function formatCountdown(secondsLeft) {
    const mins = Math.floor(secondsLeft / 60);
    const secs = secondsLeft % 60;
    return `⏱ ${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  }

  function tickTimer() {
    if (!timerEl) return;

    if (!timerEnd || timerEnd <= 0) {
      if (!timerEl.classList.contains('off')) {
        timerEl.classList.add('off');
      }
      timerEl.textContent = 'Timer off until host starts it';
      return;
    }

    const now = Math.floor(Date.now() / 1000);
    const left = timerEnd - now;

    if (left <= 0) {
      timerEl.classList.add('off');
      timerEl.textContent = 'Timer off until host starts it';
      return;
    }

    timerEl.classList.remove('off');
    timerEl.textContent = formatCountdown(left);
  }

  tickTimer();
  setInterval(tickTimer, 1000);

  function pollState() {
    if (!ajaxUrl) return;

    const url = `${ajaxUrl}?action=nsr_live_state&_=${Date.now()}`;

    fetch(url, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (!data || !data.success || !data.data) return;

        const state = data.data;

        if (typeof state.timer_end !== 'undefined') {
          timerEnd = parseInt(state.timer_end, 10) || 0;
        }

        if (state.signature && state.signature !== currentSignature) {
          window.location.reload();
        }
      })
      .catch(() => {});
  }

  setInterval(pollState, 3000);
});
