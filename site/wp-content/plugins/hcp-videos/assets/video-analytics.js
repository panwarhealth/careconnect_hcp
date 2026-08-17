/**
 * Forwards Vimeo player events to GA4 for every Vimeo iframe on the page,
 * using the enhanced-measurement event schema (video_start / video_progress
 * at 25, 50, 75 / video_complete) so they surface in the standard GA4 video
 * reports. The Vimeo SDK and gtag are only loaded once an iframe is found.
 */
(function () {
	'use strict';

	var cfg = window.hcpVideoAnalytics;
	if (!cfg || !cfg.measurementId) {
		return;
	}

	function loadScript(src, onload) {
		var s = document.createElement('script');
		s.src = src;
		s.async = true;
		s.onload = onload;
		document.head.appendChild(s);
	}

	function ensureGtag() {
		window.dataLayer = window.dataLayer || [];
		if (!window.gtag) {
			window.gtag = function () { window.dataLayer.push(arguments); };
			window.gtag('js', new Date());
			loadScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.measurementId));
		}
		window.gtag('config', cfg.measurementId, { send_page_view: false });
	}

	function track(iframe) {
		var player = new window.Vimeo.Player(iframe);
		var title = iframe.title || '';
		var sent = {};

		player.getVideoTitle().then(function (t) { title = t || title; }).catch(function () {});

		function send(name, percent, data) {
			if (sent[name + percent]) {
				return;
			}
			sent[name + percent] = true;
			window.gtag('event', name, {
				send_to: cfg.measurementId,
				video_provider: 'vimeo',
				video_title: title,
				video_url: iframe.src.split('?')[0],
				video_percent: percent,
				video_current_time: Math.round((data && data.seconds) || 0),
				video_duration: Math.round((data && data.duration) || 0)
			});
		}

		player.on('play', function (data) {
			send('video_start', 0, data);
		});

		player.on('timeupdate', function (data) {
			var pct = Math.floor((data.percent || 0) * 100);
			[25, 50, 75].forEach(function (mark) {
				if (pct >= mark) {
					send('video_progress', mark, data);
				}
			});
		});

		player.on('ended', function (data) {
			send('video_complete', 100, data);
		});
	}

	function init() {
		var iframes = document.querySelectorAll('iframe[src*="player.vimeo.com"]');
		if (!iframes.length) {
			return;
		}
		ensureGtag();
		var bind = function () { iframes.forEach(track); };
		if (window.Vimeo && window.Vimeo.Player) {
			bind();
		} else {
			loadScript('https://player.vimeo.com/api/player.js', bind);
		}
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
