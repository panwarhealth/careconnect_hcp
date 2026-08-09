/**
 * Pop-up display, dismissal and event reporting.
 *
 * Never fires on first paint: the dialog waits out a dwell timer, so the page can
 * be read before the modal lands. Time only, nothing the reader does brings it on.
 *
 * Events go to the plugin's own endpoint and straight to GA4 via gtag, addressed
 * with send_to so they land regardless of what the site's GTM container does.
 */
(function () {
	var cfg = window.hcpPopup;
	if (!cfg) return;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}

	function start() {

	var root = document.getElementById('hcp-popup-' + cfg.id);
	if (!root) return;

	var shown = false;

	function report(event) {
		var params = { popup_id: cfg.id, page_path: cfg.pagePath };

		if (typeof window.gtag === 'function' && cfg.measurementId) {
			params.send_to = cfg.measurementId;
			window.gtag('event', 'popup_' + event, params);
		}

		// also on the dataLayer, in case a container tag is ever pointed at these
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ event: 'popup_' + event, popup_id: cfg.id, page_path: cfg.pagePath });

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

	// Max-Age rather than a session cookie: browsers with session restore keep
	// session cookies alive for days, which suppresses the pop-up indefinitely.
	function markSeen() {
		var seen = (document.cookie.match(/(?:^|;\s*)hcp_popup_seen=([^;]*)/) || [])[1] || '';
		var ids = seen ? decodeURIComponent(seen).split(',') : [];
		if (ids.indexOf(cfg.id) === -1) ids.push(cfg.id);
		document.cookie = cfg.seenCookie + '=' + encodeURIComponent(ids.join(','))
			+ '; path=/; Max-Age=' + cfg.seenMaxAge + '; SameSite=Lax';
	}

	function show() {
		if (shown) return;
		shown = true;
		window.clearTimeout(timer);
		root.hidden = false;
		// A transition cannot start from display:none. Force a reflow so the
		// hidden-state styles are computed, then flip the class on the next
		// frame — one rAF alone can still be batched into the same style pass.
		void root.offsetWidth;
		window.requestAnimationFrame(function () {
			window.requestAnimationFrame(function () {
				root.classList.add('is-open');
			});
		});
		document.body.classList.add('hcp-popup-open');
		markSeen();
		report('shown');
	}

	function close(reason) {
		root.classList.remove('is-open');
		document.body.classList.remove('hcp-popup-open');
		window.setTimeout(function () {
			root.hidden = true;
		}, 200);
		if (reason) report(reason);
	}

	var timer = window.setTimeout(show, cfg.delayMs);

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

	}
})();
