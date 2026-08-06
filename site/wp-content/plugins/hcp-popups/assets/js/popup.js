/**
 * Pop-up display, dismissal and event reporting.
 *
 * Never fires on first paint: the dialog waits for a dwell timer or a quarter of
 * the page scrolled, whichever comes first, so the page can be read before the
 * modal lands.
 *
 * Events go to the plugin's own endpoint and to the dataLayer. The site runs GTM
 * rather than a raw gtag snippet, so a GTM tag forwards these to GA4.
 */
(function () {
	var cfg = window.hcpPopup;
	if (!cfg) return;

	var root = document.getElementById('hcp-popup-' + cfg.id);
	if (!root) return;

	var shown = false;

	function report(event) {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			event: 'popup_' + event,
			popup_id: cfg.id,
			page_path: cfg.pagePath
		});

		if (typeof window.gtag === 'function') {
			window.gtag('event', 'popup_' + event, { popup_id: cfg.id, page_path: cfg.pagePath });
		}

		var body = JSON.stringify({
			popup_id: cfg.id,
			event: event,
			page_path: cfg.pagePath,
			session_id: cfg.sessionId
		});

		// keepalive so the CTA click's beacon survives the navigation away
		fetch(cfg.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: body,
			credentials: 'same-origin',
			keepalive: true
		}).catch(function () {});
	}

	function markSeen() {
		var seen = (document.cookie.match(/(?:^|;\s*)hcp_popup_seen=([^;]*)/) || [])[1] || '';
		var ids = seen ? decodeURIComponent(seen).split(',') : [];
		if (ids.indexOf(cfg.id) === -1) ids.push(cfg.id);
		document.cookie = cfg.seenCookie + '=' + encodeURIComponent(ids.join(',')) + '; path=/; SameSite=Lax';
	}

	function show() {
		if (shown) return;
		shown = true;
		teardown();
		root.hidden = false;
		document.body.classList.add('hcp-popup-open');
		markSeen();
		report('shown');
	}

	function close(reason) {
		root.hidden = true;
		document.body.classList.remove('hcp-popup-open');
		if (reason) report(reason);
	}

	function onScroll() {
		var doc = document.documentElement;
		var scrollable = doc.scrollHeight - window.innerHeight;
		if (scrollable <= 0) return;
		if ((window.scrollY / scrollable) * 100 >= cfg.scrollPct) show();
	}

	var timer = window.setTimeout(show, cfg.delayMs);

	function teardown() {
		window.clearTimeout(timer);
		window.removeEventListener('scroll', onScroll);
	}

	window.addEventListener('scroll', onScroll, { passive: true });

	root.addEventListener('click', function (ev) {
		if (ev.target.closest('[data-popup-cta]')) {
			report('cta_click');
			return;
		}
		if (ev.target.closest('[data-popup-dismiss]') && !cfg.blocking) {
			close('dismissed');
		}
	});

	document.addEventListener('keydown', function (ev) {
		if (ev.key === 'Escape' && !root.hidden && !cfg.blocking) close('dismissed');
	});
})();
