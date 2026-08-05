// DH Letná — menu toggle, scroll reveal, stat rings
(function () {
  // Mobile menu
  var toggle = document.querySelector('.nav__toggle');
  var nav = document.querySelector('.nav');
  if (toggle && nav) {
    var backdrop = document.createElement('div');
    backdrop.className = 'nav-backdrop';
    document.body.appendChild(backdrop);

    function openMenu() {
      nav.classList.add('open');
      backdrop.classList.add('show');
      toggle.setAttribute('aria-expanded', 'true');
    }
    function closeMenu() {
      nav.classList.remove('open');
      backdrop.classList.remove('show');
      toggle.setAttribute('aria-expanded', 'false');
    }
    toggle.setAttribute('aria-expanded', 'false');
    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      if (nav.classList.contains('open')) closeMenu(); else openMenu();
    });
    backdrop.addEventListener('click', closeMenu);
    nav.querySelectorAll('.nav__links a').forEach(function (a) {
      a.addEventListener('click', closeMenu);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });
    // Close if resized up to desktop
    window.addEventListener('resize', function () {
      if (window.innerWidth > 720) closeMenu();
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


// Hero headline rotator
(function(){
  document.querySelectorAll('.rotator').forEach(function(el){
    var words=(el.getAttribute('data-words')||'').split('|').filter(Boolean);
    if(words.length<2) return;
    var i=0;
    setInterval(function(){
      i=(i+1)%words.length;
      el.style.opacity='0';
      setTimeout(function(){ el.textContent=words[i]; el.style.opacity='1'; },350);
    },3800);
  });
})();
