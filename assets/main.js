// DH Letná — menu toggle, scroll reveal, stat rings
(function () {
  // Mobile menu
  var toggle = document.querySelector('.nav__toggle');
  var nav = document.querySelector('.nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('open');
    });
    nav.querySelectorAll('.nav__links a').forEach(function (a) {
      a.addEventListener('click', function () { nav.classList.remove('open'); });
    });
  }

  // Scroll reveal
  var reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && reveals.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.14 });
    reveals.forEach(function (el, i) {
      el.style.transitionDelay = (i % 3) * 0.08 + 's';
      io.observe(el);
    });
  } else {
    reveals.forEach(function (el) { el.classList.add('in'); });
  }

  // Photo slots: show labelled placeholder until a real image is dropped in
  document.querySelectorAll('.photo img').forEach(function (img) {
    function markEmpty() { img.closest('.photo').classList.add('is-empty'); }
    img.addEventListener('error', markEmpty);
    // If the src is missing/failed even before listener attached
    if (!img.getAttribute('src') || (img.complete && img.naturalWidth === 0)) {
      markEmpty();
    }
  });

  // Animate stat rings when visible
  var rings = document.querySelectorAll('.ring[data-p]');
  if ('IntersectionObserver' in window && rings.length) {
    var rio = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        var el = e.target, target = +el.getAttribute('data-p'), cur = 0;
        var b = el.querySelector('b');
        var t = setInterval(function () {
          cur += Math.max(1, Math.round(target / 40));
          if (cur >= target) { cur = target; clearInterval(t); }
          el.style.setProperty('--p', cur);
          if (b) b.textContent = cur + '%';
        }, 22);
        rio.unobserve(el);
      });
    }, { threshold: 0.5 });
    rings.forEach(function (r) { rio.observe(r); });
  }
})();
