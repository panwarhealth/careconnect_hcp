/**
 * Site-wide GA4 events. The first five keep the names and firing rules the old
 * GTM container used, so report series continue unchanged:
 *   allPages                 every page, with full_url
 *   15 Second Page View      once per page, after 15 seconds
 *   login                    page load where the URL contains "welcome"
 *   Form_submissions         any form submit on a URL containing "register"
 * Changed rule:
 *   conversion_event_signup  a registration that succeeds (Formidable's signal
 *                            just before it redirects to /welcome). GTM fired it
 *                            on uncancelled submits, which Formidable's AJAX
 *                            submit cannot reproduce, so the series restarts at
 *                            go-live.
 * New:
 *   article_scroll           articles only, percent_scrolled 25 / 50 / 75 / 100 of
 *                            the article body, once each (GA4's built-in scroll
 *                            event stays at 90, measured on the whole page)
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
		});
		if (window.jQuery) {
			// Formidable fires both events on one successful registration.
			var signupSent = false;
			window.jQuery(document).on('frmBeforeFormRedirect frmFormComplete', function () {
				if (signupSent) {
					return;
				}
				signupSent = true;
				send('conversion_event_signup');
			});
		}
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
		var body = document.querySelector('.entry-content') || document.documentElement;
		var onScroll = function () {
			var rect = body.getBoundingClientRect();
			var pct = rect.height > 0 ? ((window.innerHeight - rect.top) / rect.height) * 100 : 100;
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
