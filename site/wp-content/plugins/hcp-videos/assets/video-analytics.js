/**
 * Forwards events from every hcp-videos <video> player on the page to GA4,
 * using the enhanced-measurement event schema (video_start / video_progress
 * at 25, 50, 75 / video_complete) so they surface in the standard GA4 video
 * reports, plus video_endcard_click for end-card links. Media events do not
 * bubble, so one capture-phase listener on the document covers players added
 * at any time. gtag is only loaded on first play.
 */
(function () {
	'use strict';

	var cfg = window.hcpVideoAnalytics;
	if (!cfg || !cfg.measurementId) {
		return;
	}

	var sentByVideo = new WeakMap();
	var gtagReady = false;

	function ensureGtag() {
		if (gtagReady) {
			return;
		}
		gtagReady = true;
		window.dataLayer = window.dataLayer || [];
		if (!window.gtag) {
			window.gtag = function () { window.dataLayer.push(arguments); };
			window.gtag('js', new Date());
			var s = document.createElement('script');
			s.async = true;
			s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.measurementId);
			document.head.appendChild(s);
		}
		window.gtag('config', cfg.measurementId, { send_page_view: false });
	}

	function send(video, name, percent) {
		var sent = sentByVideo.get(video) || {};
		if (sent[name + percent]) {
			return;
		}
		sent[name + percent] = true;
		sentByVideo.set(video, sent);

		ensureGtag();
		window.gtag('event', name, {
			send_to: cfg.measurementId,
			video_provider: 'r2',
			video_title: video.getAttribute('data-hcp-video') || '',
			video_url: (video.currentSrc || video.src || '').split('?')[0],
			video_percent: percent,
			video_current_time: Math.round(video.currentTime || 0),
			video_duration: Math.round(video.duration || 0)
		});
	}

	function player(e) {
		var el = e.target;
		return el instanceof HTMLVideoElement && el.hasAttribute('data-hcp-video') ? el : null;
	}

	document.addEventListener('play', function (e) {
		var video = player(e);
		if (video) {
			send(video, 'video_start', 0);
		}
	}, true);

	document.addEventListener('timeupdate', function (e) {
		var video = player(e);
		if (!video || !video.duration) {
			return;
		}
		var pct = Math.floor((video.currentTime / video.duration) * 100);
		[25, 50, 75].forEach(function (mark) {
			if (pct >= mark) {
				send(video, 'video_progress', mark);
			}
		});
	}, true);

	document.addEventListener('ended', function (e) {
		var video = player(e);
		if (video) {
			send(video, 'video_complete', 100);
		}
	}, true);

	// End-card clicks (cards are added by player.js next to their <video>).
	document.addEventListener('click', function (e) {
		var link = e.target.closest && e.target.closest('a[data-hcp-endcard-link]');
		var video = link && link.closest('.hcp-endcard').parentElement.querySelector('video[data-hcp-video]');
		if (!video) {
			return;
		}
		ensureGtag();
		window.gtag('event', 'video_endcard_click', {
			send_to: cfg.measurementId,
			video_provider: 'r2',
			video_title: video.getAttribute('data-hcp-video') || '',
			link_url: link.href,
			link_text: link.textContent.replace(/\s*→$/, ''),
			transport_type: 'beacon'
		});
	}, true);
})();
