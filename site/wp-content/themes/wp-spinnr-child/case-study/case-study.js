/**
 * Interactive case study: a scroll-down page of sections, each appearing when the one above is
 * finished, with flip cards, choice questions and a sort-into-columns activity. Finished sections
 * stay on the page for review. All copy and answers come from the markup (data-* attributes and
 * [data-cs-msg] blocks).
 */
(function () {
  'use strict';

  const root = document.querySelector('[data-cs]');
  if (!root) return;
  root.classList.add('cs-js');

  const $ = (sel, el = root) => el.querySelector(sel);
  const $$ = (sel, el = root) => Array.from(el.querySelectorAll(sel));
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- GA4 events, delivered like the hcp-popups and hcp-videos plugins ---------- */

  // gtag is configured with send_page_view off and events addressed with send_to, so the
  // GTM container's pageviews are never doubled.
  const ga = window.hcpCaseStudy || {};
  let gaReady = false;
  function track(name, params) {
    if (ga.debug) {
      const event = [name, Object.assign({ case_study: ga.caseStudy || '' }, params)];
      (window.hcpCaseStudyEvents = window.hcpCaseStudyEvents || []).push(event);
      console.debug('[case study GA4, not sent]', ...event);
      return;
    }
    if (!ga.measurementId) return;
    if (!gaReady) {
      window.dataLayer = window.dataLayer || [];
      if (!window.gtag) {
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        const s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga.measurementId);
        document.head.appendChild(s);
      }
      window.gtag('config', ga.measurementId, { send_page_view: false });
      gaReady = true;
    }
    window.gtag('event', name, Object.assign({ send_to: ga.measurementId, case_study: ga.caseStudy || '' }, params));
  }
  const stepOf = (el) => (el.closest('[data-cs-sec]') || {}).dataset.name || '';

  /* ---------- feedback pop-up: closed only by its own button ---------- */

  const modal = (function () {
    const wrap = document.createElement('div');
    wrap.className = 'cs-modal';
    wrap.hidden = true;
    wrap.innerHTML =
      '<div class="cs-modal__backdrop"></div>' +
      '<div class="cs-modal__box" role="dialog" aria-modal="true">' +
      '<div class="cs-modal__body"></div>' +
      '<div class="cs-modal__actions"></div>' +
      '</div>';
    document.body.appendChild(wrap);
    const box = wrap.querySelector('.cs-modal__box');
    const body = wrap.querySelector('.cs-modal__body');
    const actions = wrap.querySelector('.cs-modal__actions');
    let returnFocus = null;

    function close() {
      wrap.hidden = true;
      document.documentElement.classList.remove('cs-modal-open');
      if (returnFocus) returnFocus.focus({ preventScroll: true });
    }
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !wrap.hidden) close(); });

    return {
      open(key, onAction) {
        const src = $('[data-cs-msg="' + key + '"]');
        if (!src) return;
        returnFocus = document.activeElement;
        body.innerHTML = src.innerHTML;
        box.dataset.tone = src.dataset.tone || 'info';
        actions.innerHTML = '';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cs-btn';
        btn.textContent = src.dataset.label || 'Close';
        btn.addEventListener('click', () => { close(); if (onAction) onAction(); });
        actions.appendChild(btn);
        wrap.hidden = false;
        document.documentElement.classList.add('cs-modal-open');
        btn.focus({ preventScroll: true });
      }
    };
  })();

  /* ---------- sections: reveal one at a time, scroll to each as it appears ---------- */

  const sections = $$('[data-cs-sec]');
  const progress = $('[data-cs-progress]');
  const closing = $('[data-cs-closing]');

  // Space taken at the top of the window by the sticky site header (mobile) and the progress bar.
  function headerHeight() {
    const header = document.getElementById('header');
    if (!header) return 0;
    const pos = getComputedStyle(header).position;
    return pos === 'sticky' || pos === 'fixed' ? header.offsetHeight : 0;
  }
  function topInset() {
    return headerHeight() + (progress.hidden ? 0 : progress.offsetHeight);
  }
  function setStickyTop() {
    root.style.setProperty('--cs-top', headerHeight() + 'px');
  }

  // Centres the section in the visible area (where the eye rests) when it fits; a taller section
  // aligns its top under the bars instead, so its instructions are what the reader sees first.
  function scrollTarget(el) {
    const inset = topInset();
    const room = window.innerHeight - inset;
    const r = el.getBoundingClientRect();
    const spare = room - r.height;
    const offset = spare > 32 ? spare / 2 : 16;
    return r.top + window.scrollY - inset - offset;
  }

  function setProgress(step) {
    $$('[data-cs-dot]').forEach((d) => {
      const i = Number(d.dataset.csDot);
      d.classList.toggle('is-current', i === step);
      d.classList.toggle('is-done', step > i);
    });
  }

  function reveal(el) {
    el.hidden = false;
    if (el.matches('[data-cs-sec]')) track('case_study_step', { cs_step: el.dataset.name });
    if (el === closing) track('case_study_complete');
    if (el.dataset.step) setProgress(Number(el.dataset.step));
    // Measured before the slide-in animation starts, since its transform shifts the box.
    const top = scrollTarget(el);
    el.classList.add('is-new');
    setTimeout(() => el.classList.remove('is-new'), 700);
    window.scrollTo({ top, behavior: reduceMotion ? 'auto' : 'smooth' });
  }

  function advance(from) {
    const actions = $('[data-cs-actions]', from);
    if (actions) actions.hidden = true;
    const i = sections.indexOf(from);
    if (i === 0) {
      progress.hidden = false;
      track('case_study_start');
    }
    if (i === sections.length - 1) {
      setProgress(5);
      reveal(closing);
    } else {
      reveal(sections[i + 1]);
    }
  }

  // Shows the section's Continue once its activity is done.
  function unlock(section) {
    const btn = $('[data-cs-continue]', section);
    if (!btn) return;
    btn.hidden = false;
    btn.disabled = false;
  }

  sections.forEach((s, i) => { if (i > 0) s.hidden = true; });
  progress.hidden = true;
  closing.hidden = true;
  setStickyTop();
  window.addEventListener('resize', setStickyTop);

  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-cs-continue]');
    if (btn) advance(btn.closest('[data-cs-sec]'));
  });

  // Resource cards (thumbnail or button) and the Order samples banner in the closing section.
  closing.addEventListener('click', (e) => {
    const link = e.target.closest('a[href]');
    if (!link) return;
    const card = link.closest('.cs-res');
    const title = card ? card.querySelector('h3').textContent : link.textContent;
    track('case_study_resource', { cs_resource: title.replace(/\s+/g, ' ').trim(), link_url: link.href });
  });

  /* ---------- flip cards ---------- */

  $$('[data-cs-flips]').forEach((group) => {
    const section = group.closest('[data-cs-sec]');
    const cards = $$('.cs-flip', group);

    // Peek: once the cards are on screen, each gives a slight turn in turn to show they flip.
    // Any touch of a card cancels the peek on every card, so it never fights a flip.
    const peekTimers = [];
    let peekDone = false;
    function stopPeek() {
      peekDone = true;
      peekTimers.forEach(clearTimeout);
      cards.forEach((c) => c.classList.remove('is-peeking'));
    }
    function peek() {
      if (reduceMotion || peekDone) return;
      cards.forEach((c, i) => peekTimers.push(setTimeout(() => {
        c.classList.remove('is-peeking');
        void c.offsetWidth;
        c.classList.add('is-peeking');
      }, i * 180)));
    }
    if ('IntersectionObserver' in window) {
      const seen = new IntersectionObserver((entries) => {
        if (!entries.some((e) => e.isIntersecting)) return;
        seen.disconnect();
        peekTimers.push(setTimeout(peek, 400));
      }, { threshold: 0.6 });
      seen.observe(group);
    }
    cards.forEach((card) => card.addEventListener('pointerdown', stopPeek));
    cards.forEach((card) => card.addEventListener('click', () => {
      stopPeek();
      const flipped = card.classList.toggle('is-flipped');
      card.setAttribute('aria-pressed', String(flipped));
      card.classList.add('is-seen');
      if (cards.every((c) => c.classList.contains('is-seen'))) unlock(section);
    }));
  });

  /* ---------- choice questions: one pick per row, every row must be right ---------- */

  $$('[data-cs-quiz]').forEach((quiz) => {
    const section = quiz.closest('[data-cs-sec]');
    const rows = $$('[data-cs-row]', quiz);
    const check = $('[data-cs-check]', quiz);
    const picked = (row) => $('.cs-opt[aria-pressed="true"]', row);
    let attempts = 0;
    check.disabled = true;

    rows.forEach((row) => {
      $$('.cs-opt', row).forEach((opt) => opt.addEventListener('click', () => {
        if (quiz.classList.contains('is-locked')) return;
        $$('.cs-opt', row).forEach((o) => o.setAttribute('aria-pressed', String(o === opt)));
        row.classList.remove('is-wrong');
        check.disabled = !rows.every(picked);
      }));
    });

    check.addEventListener('click', () => {
      const wrong = rows.filter((row) => !picked(row) || !picked(row).hasAttribute('data-correct'));
      attempts++;
      track('case_study_answer', { cs_step: stepOf(quiz), cs_result: wrong.length ? 'incorrect' : 'correct', cs_attempt: attempts });
      if (wrong.length) {
        // With several questions, outline the wrong ones and take the reader to the first.
        if (rows.length > 1) {
          wrong.forEach((row) => row.classList.add('is-wrong'));
          modal.open(quiz.dataset.wrong, () => {
            window.scrollTo({ top: scrollTarget(wrong[0]), behavior: reduceMotion ? 'auto' : 'smooth' });
          });
        } else {
          modal.open(quiz.dataset.wrong);
        }
        return;
      }
      quiz.classList.add('is-locked');
      $$('.cs-opt', quiz).forEach((o) => { o.disabled = true; });
      check.closest('.cs-actions').hidden = true;
      unlock(section);
      modal.open(quiz.dataset.right, () => advance(section));
    });
  });

  /* ---------- sort into columns: drag or tap, both go through place(). A card placed in its
     right column is marked and fixed there; the activity completes when every card is right. ---------- */

  $$('[data-cs-sort]').forEach((sort) => {
    const section = sort.closest('[data-cs-sec]');
    const pool = $('[data-cs-pool]', sort);
    const cols = $$('[data-cs-col]', sort);
    const zones = [pool].concat(cols);
    const cards = $$('[data-cs-card]', sort);
    const max = Number(sort.dataset.max) || 3;
    const list = (zone) => $('[data-cs-list]', zone);
    const zoneOf = (card) => card.parentElement.closest('[data-cs-col], [data-cs-pool]');
    let selected = null;
    let misses = 0;
    let locked = false;
    let suppressClick = false;

    function refresh() {
      cols.forEach((col) => {
        const n = list(col).children.length;
        $('[data-cs-count]', col).textContent = n + '/' + max;
        col.classList.toggle('is-full', n >= max);
      });
      pool.classList.toggle('is-empty', list(pool).children.length === 0);
    }

    function place(card, zone) {
      if (locked || !zone || zone === zoneOf(card)) return;
      if (zone !== pool && list(zone).children.length >= max) {
        modal.open('sort-full');
        return;
      }
      list(zone).appendChild(card);
      refresh();
      if (zone === pool) return;
      if (zone.dataset.csCol !== card.dataset.answer) {
        misses++;
        return;
      }
      card.classList.add('is-correct');
      card.disabled = true;
      if (cards.every((c) => c.classList.contains('is-correct'))) complete();
    }

    function complete() {
      locked = true;
      sort.classList.add('is-locked');
      track('case_study_answer', { cs_step: stepOf(sort), cs_result: 'correct', cs_attempt: misses + 1 });
      unlock(section);
      // A short pause lets the last tick show before the pop-up covers it.
      setTimeout(() => modal.open(sort.dataset.right, () => advance(section)), 450);
    }

    function select(card) {
      if (selected) selected.classList.remove('is-selected');
      selected = card;
      if (card) card.classList.add('is-selected');
      sort.classList.toggle('is-selecting', !!card);
      sort.classList.toggle('is-selecting-placed', !!card && zoneOf(card) !== pool);
    }

    cards.forEach((card) => card.addEventListener('click', (e) => {
      e.stopPropagation();
      if (suppressClick || locked) return;
      select(selected === card ? null : card);
    }));

    zones.forEach((zone) => zone.addEventListener('click', () => {
      if (!selected || locked) return;
      const card = selected;
      select(null);
      place(card, zone);
      card.focus({ preventScroll: true });
    }));

    /* drag: pointer events cover mouse, touch and pen */
    cards.forEach((card) => card.addEventListener('pointerdown', (down) => {
      if (locked || card.disabled || down.button !== 0) return;
      const startX = down.clientX;
      const startY = down.clientY;
      let ghost = null;
      let offX = 0;
      let offY = 0;
      let over = null;
      let lastY = startY;
      let raf = 0;

      function zoneAt(x, y) {
        const el = document.elementFromPoint(x, y);
        const zone = el && el.closest('[data-cs-col], [data-cs-pool]');
        return zone && sort.contains(zone) ? zone : null;
      }

      function highlight(zone) {
        if (over === zone) return;
        if (over) over.classList.remove('is-over', 'is-over-full');
        over = zone;
        if (!zone) return;
        const full = zone !== pool && zone !== zoneOf(card) && list(zone).children.length >= max;
        zone.classList.add(full ? 'is-over-full' : 'is-over');
      }

      // Scrolls the page while the finger or cursor sits near the top or bottom of the visible area.
      function autoScroll() {
        const top = topInset();
        const edge = 70;
        let dy = 0;
        if (top + edge > lastY) dy = -Math.ceil((top + edge - lastY) / 6);
        else if (lastY > window.innerHeight - edge) dy = Math.ceil((lastY - (window.innerHeight - edge)) / 6);
        if (dy) window.scrollBy(0, dy);
        raf = requestAnimationFrame(autoScroll);
      }

      function move(e) {
        if (!ghost) {
          if (6 > Math.hypot(e.clientX - startX, e.clientY - startY)) return;
          const r = card.getBoundingClientRect();
          offX = startX - r.left;
          offY = startY - r.top;
          ghost = card.cloneNode(true);
          ghost.classList.add('cs-card--ghost');
          ghost.style.width = r.width + 'px';
          ghost.style.fontSize = getComputedStyle(card).fontSize;
          document.body.appendChild(ghost);
          card.classList.add('is-dragging');
          select(null);
          raf = requestAnimationFrame(autoScroll);
        }
        e.preventDefault();
        lastY = e.clientY;
        ghost.style.transform = 'translate(' + (e.clientX - offX) + 'px,' + (e.clientY - offY) + 'px) rotate(2deg)';
        highlight(zoneAt(e.clientX, e.clientY));
      }

      function end(e) {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', end);
        window.removeEventListener('pointercancel', end);
        if (!ghost) return;
        cancelAnimationFrame(raf);
        ghost.remove();
        card.classList.remove('is-dragging');
        const target = e.type === 'pointerup' ? zoneAt(e.clientX, e.clientY) : null;
        highlight(null);
        place(card, target);
        suppressClick = true;
        setTimeout(() => { suppressClick = false; }, 0);
      }

      window.addEventListener('pointermove', move, { passive: false });
      window.addEventListener('pointerup', end);
      window.addEventListener('pointercancel', end);
    }));

    refresh();
  });
})();
