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

  function formatCountdown(secondsLeft) {
    const mins = Math.floor(secondsLeft / 60);
    const secs = secondsLeft % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  }

  function setupStudioTimer() {
    const controlCard = Array.from(document.querySelectorAll('.nsr-card')).find(card =>
      card.textContent.includes('Show Controls')
    );
    if (!controlCard) return;

    const timerLine = Array.from(controlCard.querySelectorAll('p')).find(p =>
      p.textContent.includes('Current Timer:')
    );
    if (!timerLine) return;

    function extractServerValue() {
      const txt = timerLine.textContent || '';
      const match = txt.match(/Current Timer:\s*([0-9]{2}):([0-9]{2})/);
      if (!match) return null;
      return (parseInt(match[1], 10) * 60) + parseInt(match[2], 10);
    }

    let studioSecondsLeft = extractServerValue();

    setInterval(() => {
      if (studioSecondsLeft === null || studioSecondsLeft <= 0) return;
      studioSecondsLeft -= 1;
      if (studioSecondsLeft <= 0) {
        timerLine.innerHTML = '<strong>Current Timer:</strong> OFF';
      } else {
        timerLine.innerHTML = '<strong>Current Timer:</strong> ' + formatCountdown(studioSecondsLeft);
      }
    }, 1000);
  }

  setupStudioTimer();

  const wrap = document.querySelector('.nsr-front-wrap');
  if (wrap) {
    const ajaxUrl = wrap.dataset.ajax;
    let currentSignature = wrap.dataset.signature || '';
    let timerEnd = parseInt(wrap.dataset.timerEnd || '0', 10);
    const timerEl = document.getElementById('nsr-live-timer');

    function tickLiveTimer() {
      if (!timerEl) return;

      if (!timerEnd || timerEnd <= 0) {
        timerEl.classList.add('off');
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
      timerEl.textContent = `⏱ ${formatCountdown(left)}`;
    }

    tickLiveTimer();
    setInterval(tickLiveTimer, 1000);

    function pollState() {
      if (!ajaxUrl) return;
      const url = `${ajaxUrl}?action=nsr_live_state&_=${Date.now()}`;

      fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store'
      })
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

    setInterval(pollState, 2000);
  }

  const scannerInput = document.getElementById('scanner_barcode');
  if (scannerInput) {
    scannerInput.focus();
    setTimeout(() => scannerInput.focus(), 200);

    let lastValue = '';
    let scanTimer = null;

    scannerInput.addEventListener('input', function () {
      clearTimeout(scanTimer);
      const current = scannerInput.value.trim();

      scanTimer = setTimeout(() => {
        if (current !== '' && current !== lastValue && current.length >= 8) {
          lastValue = current;
          const form = scannerInput.closest('form');
          if (form) form.submit();
        }
      }, 200);
    });
  }

  const retailInput = document.querySelector('.nsr-retail-input');
  const liveInput = document.querySelector('.nsr-live-input');

  function suggestPrice(retail) {
    const r = parseFloat(retail || '0');
    if (!r || r <= 0) return 5;
    if (r < 10) return 3;
    if (r < 20) return 5;
    if (r < 35) return 10;
    if (r < 50) return 15;
    if (r < 75) return 25;
    if (r < 100) return 35;
    return Math.round(r * 0.35);
  }

  if (retailInput && liveInput) {
    retailInput.addEventListener('change', function () {
      const next = suggestPrice(retailInput.value);
      liveInput.value = Number(next).toFixed(2);
    });
  }
});
document.addEventListener('DOMContentLoaded', function () {
  const liveInput = document.querySelector('.nsr-live-input');
  const scanInput = document.getElementById('scanner_barcode');

  if (scanInput) {
    scanInput.focus();
  }

  const banner = document.querySelector('.nsr-showmode-banner');
  if (banner) {
    banner.animate(
      [
        { transform: 'scale(1)', opacity: 1 },
        { transform: 'scale(1.02)', opacity: 1 },
        { transform: 'scale(1)', opacity: 1 }
      ],
      {
        duration: 900,
        iterations: 2
      }
    );
  }

  const buttons = document.querySelectorAll('.nsr-smart-actions .button');
  buttons.forEach((btn) => {
    btn.addEventListener('click', function () {
      if (liveInput) {
        setTimeout(() => liveInput.focus(), 50);
      }
    });
  });
});
document.addEventListener('DOMContentLoaded', function () {
  const banner = document.querySelector('.nsr-showmode-banner');

  function playTone(freq, duration, type, volume) {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = type || 'sine';
      osc.frequency.value = freq;
      gain.gain.value = volume || 0.03;

      osc.connect(gain);
      gain.connect(ctx.destination);

      osc.start();

      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + duration);
      osc.stop(ctx.currentTime + duration);
    } catch (e) {}
  }

  function playEffect(effect) {
    if (!banner) return;
    const fxEnabled = banner.dataset.fx === '1';
    if (!fxEnabled) return;

    if (effect === 'flash') {
      playTone(880, 0.12, 'square', 0.04);
      setTimeout(() => playTone(1174, 0.14, 'square', 0.04), 120);
    } else if (effect === 'sold') {
      playTone(523, 0.10, 'triangle', 0.04);
      setTimeout(() => playTone(659, 0.12, 'triangle', 0.04), 100);
      setTimeout(() => playTone(784, 0.16, 'triangle', 0.04), 220);
    } else if (effect === 'mystery') {
      playTone(330, 0.18, 'sawtooth', 0.03);
      setTimeout(() => playTone(392, 0.20, 'sawtooth', 0.03), 160);
    } else if (effect === 'hype') {
      playTone(698, 0.08, 'square', 0.04);
      setTimeout(() => playTone(698, 0.08, 'square', 0.04), 100);
      setTimeout(() => playTone(880, 0.14, 'square', 0.04), 220);
    }
  }

  if (banner) {
    const effect = banner.dataset.effect || '';
    playEffect(effect);

    banner.animate(
      [
        { transform: 'translateY(-8px) scale(0.99)', opacity: 0.3 },
        { transform: 'translateY(0) scale(1.02)', opacity: 1 },
        { transform: 'translateY(0) scale(1)', opacity: 1 }
      ],
      {
        duration: 700,
        iterations: 1,
        easing: 'ease-out'
      }
    );

    setTimeout(() => {
      banner.classList.remove('nsr-animate-pulse');
    }, 3200);
  }
});
