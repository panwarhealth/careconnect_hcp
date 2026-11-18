/**
 * Site-wide GA4 events. The first five keep the names and firing rules the old
 * GTM container used, so report series continue unchanged:
 *   allPages                 every page, with full_url
 *   15 Second Page View      once per page, after 15 seconds
 *   login                    page load where the URL contains "welcome"
 *   Form_submissions         any form submit on a URL containing "register"
 *   conversion_event_signup  same, only when the submit is not cancelled
 * New:
 *   article_scroll           articles only, percent_scrolled 25 / 50 / 75 / 100,
 *                            once each (GA4's built-in scroll event stays at 90)
 * And, for logged-in HCPs only, PDF clicks are reported to the site's own
 * engagement record (not GA4), since PDFs open without a page view.
 * gtag and the page context are set up in <head> by the plugin.
 */
(function () {
	'use strict';

	var cfg = window.hcpAnalytics;
	if (!cfg || !cfg.measurementId || typeof window.gtag !== 'function') {
		return;
	}

	function send(name, params) {
		var payload = { send_to: cfg.measurementId };
		for (var k in params || {}) {
			payload[k] = params[k];
		}
		window.gtag('event', name, payload);
	}

	var url = window.location.href;

	send('allPages', { full_url: url });
	setTimeout(function () { send('15 Second Page View'); }, 15000);
	if (url.indexOf('welcome') !== -1) {
		send('login');
	}

	if (url.indexOf('register') !== -1) {
		document.addEventListener('submit', function (e) {
			var form = e.target;
			send('Form_submissions', { page: window.location.pathname, Form: form.id || form.getAttribute('name') || '' });
			setTimeout(function () {
				if (!e.defaultPrevented) {
					send('conversion_event_signup');
				}
			}, 0);
		});
	}

	if (cfg.pdfUrl && navigator.sendBeacon) {
		document.addEventListener('click', function (e) {
			var link = e.target.closest ? e.target.closest('a[href]') : null;
			if (!link || !/\/wp-content\/uploads\/.+\.pdf(\?|#|$)/i.test(link.href)) {
				return;
			}
			var data = new FormData();
			data.append('url', link.href);
			data.append('_wpnonce', cfg.nonce);
			navigator.sendBeacon(cfg.pdfUrl, data);
		}, true);
	}

	if (cfg.article) {
		var marks = [25, 50, 75, 100];
		var sent = {};
		var onScroll = function () {
			var doc = document.documentElement;
			var max = doc.scrollHeight - window.innerHeight;
			var pct = max > 0 ? (window.scrollY / max) * 100 : 100;
			for (var i = 0; i < marks.length; i++) {
				if (pct >= marks[i] - 0.5 && !sent[marks[i]]) {
					sent[marks[i]] = true;
					send('article_scroll', { percent_scrolled: marks[i] });
				}
			}
			if (sent[100]) {
				window.removeEventListener('scroll', onScroll);
			}
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}
})();
