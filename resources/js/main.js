(function () {
  'use strict';

  /* Theme toggle — persisted so the pre-paint script in index.php can restore it. */
  var toggle = document.querySelector('.theme-toggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var isDark = document.documentElement.classList.toggle('dark');
      try {
        localStorage.setItem('mutendi-theme', isDark ? 'dark' : 'light');
      } catch (e) {}
    });
  }

  /* Parallax: floating cards lag the pointer slightly, each at its own depth. */
  var floaters = document.querySelectorAll('.floaters .float');
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (floaters.length && !reduced && window.matchMedia('(min-width: 1181px)').matches) {
    var depths = [];
    floaters.forEach(function (el, i) {
      depths.push(8 + (i % 4) * 5);
      el.style.transition = 'translate .5s cubic-bezier(.22,1,.36,1)';
    });

    window.addEventListener('mousemove', function (e) {
      var x = (e.clientX / window.innerWidth - 0.5) * 2;
      var y = (e.clientY / window.innerHeight - 0.5) * 2;

      floaters.forEach(function (el, i) {
        var d = depths[i];
        el.style.translate = (-x * d).toFixed(1) + 'px ' + (-y * d).toFixed(1) + 'px';
      });
    }, { passive: true });
  }

  /* Mobile menu placeholder — reveals the nav links inline. */
  var burger = document.querySelector('.nav__burger');
  var links = document.querySelector('.nav__links');
  if (burger && links) {
    var setMenu = function (open) {
      burger.setAttribute('aria-expanded', String(open));
      links.classList.toggle('is-open', open);
    };

    burger.addEventListener('click', function () {
      setMenu(burger.getAttribute('aria-expanded') !== 'true');
    });

    // Picking a destination closes the menu so the scroll is visible.
    links.addEventListener('click', function (e) {
      if (e.target.closest('a')) { setMenu(false); }
    });

    document.addEventListener('click', function (e) {
      if (!e.target.closest('.nav')) { setMenu(false); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { setMenu(false); }
    });
  }

  /* Scroll-spy: the nav marks whichever section is currently under it. */
  var spyLinks = [].slice.call(document.querySelectorAll('.nav__link[href^="#"]'))
    .map(function (a) {
      var href = a.getAttribute('href');
      // A link owns its own section plus any that opt in via data-spy, so
      // regions without their own nav entry don't leave the bar unlit.
      var owned = [].slice.call(document.querySelectorAll('[data-spy="' + href + '"]'));
      var self = document.querySelector(href);
      if (self) { owned.unshift(self); }
      return { link: a, sections: owned };
    })
    .filter(function (pair) { return pair.sections.length; });

  if (spyLinks.length && 'IntersectionObserver' in window) {
    var navH = parseInt(getComputedStyle(document.documentElement)
                 .getPropertyValue('--nav-h'), 10) || 76;
    var onScreen = new Set();

    var mark = function () {
      // The current section is the first one, in page order, still on screen.
      var active = null;
      for (var i = 0; i < spyLinks.length; i++) {
        var hit = spyLinks[i].sections.some(function (sec) { return onScreen.has(sec); });
        if (hit) { active = spyLinks[i].link; break; }
      }
      spyLinks.forEach(function (pair) {
        pair.link.classList.toggle('is-current', pair.link === active);
      });
    };

    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { onScreen.add(entry.target); }
        else { onScreen.delete(entry.target); }
      });
      mark();
    }, { rootMargin: '-' + (navH + 20) + 'px 0px -55% 0px', threshold: 0 });

    spyLinks.forEach(function (pair) {
      pair.sections.forEach(function (sec) { spy.observe(sec); });
    });
  }

  /* Module showcase: tabs swap the mock interface, sidebar and caption together. */
  var tabs = [].slice.call(document.querySelectorAll('.tab'));

  if (tabs.length) {
    var setActive = function (nodes, attr, key) {
      nodes.forEach(function (el) {
        el.classList.toggle('is-active', el.getAttribute(attr) === key);
      });
    };

    var panels   = [].slice.call(document.querySelectorAll('.panel'));
    var captions = [].slice.call(document.querySelectorAll('.caption'));
    var sideItems = [].slice.call(document.querySelectorAll('.side__item[data-side]'));

    var select = function (key, focus) {
      tabs.forEach(function (t) {
        var on = t.dataset.module === key;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        if (on && focus) { t.focus(); }
      });
      setActive(panels, 'data-panel', key);
      setActive(captions, 'data-caption', key);
      setActive(sideItems, 'data-side', key);

      // On narrow screens the strip scrolls, so keep the chosen tab visible.
      var active = document.querySelector('.tab.is-active');
      if (active && active.scrollIntoView) {
        active.scrollIntoView({ inline: 'center', block: 'nearest',
                                behavior: reduced ? 'auto' : 'smooth' });
      }
    };

    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { select(tab.dataset.module); });

      // Left/right arrows move between tabs, per the tablist pattern.
      tab.addEventListener('keydown', function (e) {
        var step = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (!step) { return; }
        e.preventDefault();
        select(tabs[(i + step + tabs.length) % tabs.length].dataset.module, true);
      });
    });
  }

  /* Reveal the showcase as it scrolls into view. */
  var reveals = [].slice.call(document.querySelectorAll('.reveal'));

  if (reveals.length) {
    if (reduced || !('IntersectionObserver' in window)) {
      reveals.forEach(function (el) { el.classList.add('is-in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-in');
            io.unobserve(entry.target);
          }
        });
      }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

      reveals.forEach(function (el) { io.observe(el); });
    }
  }

  /* Testimonial deck: cards cycle forward, the rest restack behind. */
  var deck = document.querySelector('.deck');

  if (deck) {
    var quotes = [].slice.call(deck.querySelectorAll('.quote'));
    var dots   = [].slice.call(document.querySelectorAll('.pager__dot'));
    var count  = quotes.length;
    var active = 0;
    var timer  = null;

    var render = function () {
      quotes.forEach(function (q, i) {
        // Distance forward from the active card decides its depth in the stack.
        var pos = (i - active + count) % count;
        q.setAttribute('data-pos', pos);
        if (pos === 0) {
          q.removeAttribute('aria-hidden');
        } else {
          q.setAttribute('aria-hidden', 'true');
        }
      });
      dots.forEach(function (d, i) {
        d.classList.toggle('is-on', i === active);
        d.setAttribute('aria-selected', String(i === active));
      });
    };

    var go = function (i) {
      active = (i + count) % count;
      render();
    };

    var start = function () {
      if (reduced || timer) { return; }
      timer = setInterval(function () { go(active + 1); }, 5200);
    };
    var stop = function () { clearInterval(timer); timer = null; };

    dots.forEach(function (d) {
      d.addEventListener('click', function () {
        go(parseInt(d.dataset.slide, 10));
        stop();
        start();
      });
    });

    // Hold the current card while it is being read or tabbed through.
    ['mouseenter', 'focusin'].forEach(function (e) { deck.addEventListener(e, stop); });
    ['mouseleave', 'focusout'].forEach(function (e) { deck.addEventListener(e, start); });

    // Only cycle while the deck is actually on screen.
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        entries[0].isIntersecting ? start() : stop();
      }, { threshold: 0.25 }).observe(deck);
    } else {
      start();
    }

    render();
  }
})();
