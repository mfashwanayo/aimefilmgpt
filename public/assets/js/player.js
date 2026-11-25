/* AIMEFILMS player: autoplay the next episode/track when one finishes. */
(function () {
  var wrap = document.querySelector('[data-player]');
  if (!wrap) return;
  var media = wrap.querySelector('video, audio');
  var next = wrap.getAttribute('data-next');
  var box = document.getElementById('autoplay-next');
  var KEY = 'aimefilms_autoplay';
  try { if (box) box.checked = localStorage.getItem(KEY) !== 'off'; } catch (e) {}
  if (box) box.addEventListener('change', function () {
    try { localStorage.setItem(KEY, box.checked ? 'on' : 'off'); } catch (e) {}
  });
  if (media && next) {
    media.addEventListener('ended', function () {
      if (!box || box.checked) location.href = next + (next.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1';
    });
  }
  if (media && /[?&]autoplay=1/.test(location.search)) {
    var p = media.play();           // browsers may block this; that is fine
    if (p && p.catch) p.catch(function () {});
  }
})();
