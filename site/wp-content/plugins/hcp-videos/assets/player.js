/**
 * End cards for hcp-videos players. When a <video data-hcp-endcard> finishes,
 * fullscreen is exited (an overlay cannot sit over native fullscreen) and a
 * clickable card is laid over the player: heading, optional thumbnail, title,
 * a link button and Replay. Playing or seeking again removes the card.
 *
 * Series cards (data.autoplay) count down, then swap the next video page's
 * <main> into this page and play it. Staying on the same page is what lets it
 * play with sound: browsers only allow sound on a page the viewer has already
 * clicked, and a freshly loaded page has not been. Cancel stops the countdown;
 * a plain click on the card's link swaps the same way.
 *
 * If the swap cannot be done, it falls back to opening the page with
 * ?hcp_autoplay=1, which starts the video on arrival, muted with a
 * "Tap to unmute" button when the browser refuses sound.
 *
 * window.hcpEndcard exposes the same rendering for the edit-screen preview,
 * where the countdown is shown but never runs.
 */
(function () {
	'use strict';

	var AUTOPLAY_SECONDS = 5;
	var AUTOPLAY_PARAM = 'hcp_autoplay';

	var CSS = '' +
		'.hcp-endcard{position:absolute;inset:0;z-index:10;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(10,20,30,.86);color:#fff;text-align:center;}' +
		'.hcp-endcard-inner{display:flex;align-items:center;gap:1.25rem;max-width:640px;text-align:left;}' +
		// Whole thumbnail, never cropped (some posters are portrait), letterboxed
		// in black like the player itself.
		'.hcp-endcard-thumb{flex:0 0 42%;max-width:240px;aspect-ratio:16/9;background:#000;object-fit:contain;border-radius:8px;}' +
		'.hcp-endcard-heading{margin:0 0 .25rem;font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#35B1C9;}' +
		'.hcp-endcard-title{margin:0 0 .9rem;font-size:1.05rem;font-weight:600;line-height:1.35;color:#fff;}' +
		'.hcp-endcard-actions{display:flex;flex-wrap:wrap;gap:.5rem;}' +
		// Both buttons sized explicitly: the theme's own button/link rules (scoped
		// under #app) otherwise make Replay taller than the link.
		'.hcp-endcard .hcp-endcard-link,.hcp-endcard .hcp-endcard-replay{display:inline-flex!important;align-items:center;justify-content:center;box-sizing:border-box!important;height:2.75rem!important;min-height:0!important;margin:0!important;padding:0 1.25rem!important;border-radius:9999px!important;font-size:.95rem!important;font-weight:600!important;line-height:1!important;letter-spacing:normal!important;text-transform:none!important;text-decoration:none!important;box-shadow:none!important;cursor:pointer;}' +
		'.hcp-endcard .hcp-endcard-link{background:#35B1C9!important;border:1px solid #35B1C9!important;color:#fff!important;}' +
		'.hcp-endcard .hcp-endcard-link:hover{background:#2a96ab!important;border-color:#2a96ab!important;}' +
		'.hcp-endcard .hcp-endcard-replay{background:transparent!important;border:1px solid rgba(255,255,255,.6)!important;color:#fff!important;}' +
		'.hcp-endcard .hcp-endcard-replay:hover{border-color:#fff!important;}' +
		// Countdown sits bottom-centre of the card, where players usually put it.
		'.hcp-endcard-countdown{position:absolute;left:0;right:0;bottom:3.5rem;display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:.75rem;margin:0;font-size:.9rem;color:rgba(255,255,255,.85);}' +
		'.hcp-endcard-countdown strong{color:#fff;}' +
		'.hcp-endcard .hcp-endcard-cancel{background:none!important;border:0!important;padding:0!important;margin:0!important;min-height:0!important;height:auto!important;color:#35B1C9!important;font-size:.9rem!important;font-weight:600!important;line-height:1.2!important;text-decoration:underline!important;text-transform:none!important;letter-spacing:normal!important;box-shadow:none!important;cursor:pointer;}' +
		'.hcp-endcard.is-narrow .hcp-endcard-inner{flex-direction:column;text-align:center;gap:.5rem;}' +
		'.hcp-endcard.is-narrow .hcp-endcard-countdown{bottom:2.75rem;font-size:.8rem;}' +
		'.hcp-unmute{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:11;display:inline-flex!important;align-items:center;gap:.5rem;height:3rem!important;min-height:0!important;margin:0!important;padding:0 1.5rem!important;border:0!important;border-radius:9999px!important;background:rgba(10,20,30,.86)!important;color:#fff!important;font-size:1rem!important;font-weight:600!important;line-height:1!important;text-transform:none!important;letter-spacing:normal!important;box-shadow:0 2px 12px rgba(0,0,0,.35)!important;cursor:pointer;}' +
		'.hcp-unmute:hover{background:#35B1C9!important;}' +
		'.hcp-endcard.is-narrow .hcp-endcard-thumb{display:none;}' +
		'.hcp-endcard.is-narrow .hcp-endcard-title{font-size:.95rem;margin-bottom:.6rem;}' +
		'.hcp-endcard.is-narrow .hcp-endcard-actions{justify-content:center;}';

	function addStyles() {
		if (document.getElementById('hcp-endcard-css')) {
			return;
		}
		var style = document.createElement('style');
		style.id = 'hcp-endcard-css';
		style.textContent = CSS;
		document.head.appendChild(style);
	}

	function el(tag, cls, text) {
		var node = document.createElement(tag);
		if (cls) {
			node.className = cls;
		}
		if (text) {
			node.textContent = text;
		}
		return node;
	}

	function exitFullscreen(video) {
		if (document.fullscreenElement && document.exitFullscreen) {
			document.exitFullscreen().catch(function () {});
		}
		if (video.webkitDisplayingFullscreen && video.webkitExitFullscreen) {
			video.webkitExitFullscreen();
		}
	}

	function remove(video) {
		var card = video.parentElement && video.parentElement.querySelector('.hcp-endcard');
		if (card) {
			clearInterval(card.hcpTimer);
			card.remove();
		}
	}

	function withAutoplay(url) {
		var u = new URL(url, window.location.href);
		u.searchParams.set(AUTOPLAY_PARAM, '1');
		return u.toString();
	}

	function announce(action, video, url) {
		document.dispatchEvent(new CustomEvent('hcp-endcard-autoplay', {
			detail: { action: action, video: video, url: url }
		}));
	}

	/**
	 * Countdown row for an autoplaying card. In preview mode it is drawn but
	 * never ticks, so the editor can see it without being navigated away.
	 */
	function countdown(card, video, data, preview) {
		var row = el('div', 'hcp-endcard-countdown');
		var label = el('span');
		var seconds = AUTOPLAY_SECONDS;
		var count = el('strong', '', String(seconds));
		label.appendChild(document.createTextNode('Playing next in '));
		label.appendChild(count);
		row.appendChild(label);

		var cancel = el('button', 'hcp-endcard-cancel', 'Cancel autoplay');
		cancel.type = 'button';
		cancel.addEventListener('click', function () {
			clearInterval(card.hcpTimer);
			row.remove();
			if (!preview) {
				announce('cancel', video, data.url);
			}
		});
		row.appendChild(cancel);

		if (!preview) {
			card.hcpTimer = setInterval(function () {
				seconds -= 1;
				count.textContent = String(Math.max(seconds, 0));
				if (seconds <= 0) {
					clearInterval(card.hcpTimer);
					announce('next', video, data.url);
					swapTo(data.url);
				}
			}, 1000);
		}
		return row;
	}

	function show(video, data, options) {
		var preview = !!(options && options.preview);
		addStyles();
		remove(video);
		exitFullscreen(video);

		var card = el('div', 'hcp-endcard');
		var inner = el('div', 'hcp-endcard-inner');
		var text = el('div');
		var actions = el('div', 'hcp-endcard-actions');

		if (data.thumb) {
			var img = el('img', 'hcp-endcard-thumb');
			img.src = data.thumb;
			img.alt = '';
			inner.appendChild(img);
		}
		if (data.heading) {
			text.appendChild(el('p', 'hcp-endcard-heading', data.heading));
		}
		if (data.title) {
			text.appendChild(el('p', 'hcp-endcard-title', data.title));
		}
		if (data.autoplay) {
			text.appendChild(countdown(card, video, data, preview));
		}

		var link = el('a', 'hcp-endcard-link', data.label + ' →');
		link.href = data.autoplay ? withAutoplay(data.url) : data.url;
		link.setAttribute('data-hcp-endcard-link', '');
		if (data.autoplay && !preview) {
			link.addEventListener('click', function (e) {
				if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
					return;
				}
				e.preventDefault();
				clearInterval(card.hcpTimer);
				swapTo(data.url);
			});
		}
		actions.appendChild(link);

		var replay = el('button', 'hcp-endcard-replay', 'Replay');
		replay.type = 'button';
		replay.addEventListener('click', function () {
			remove(video);
			video.currentTime = 0;
			video.play();
		});
		actions.appendChild(replay);

		text.appendChild(actions);
		inner.appendChild(text);
		card.appendChild(inner);

		if (video.parentElement.clientWidth < 480) {
			card.classList.add('is-narrow');
		}
		video.parentElement.appendChild(card);
	}

	function bind(video) {
		var data;
		try {
			data = JSON.parse(video.getAttribute('data-hcp-endcard'));
		} catch (e) {
			return;
		}
		if (!data || !data.url) {
			return;
		}
		video.addEventListener('ended', function () { show(video, data); });
		video.addEventListener('play', function () { remove(video); });
		video.addEventListener('seeking', function () { remove(video); });
	}

	/**
	 * Browsers only allow sound without a click on this page, so a refused
	 * start falls back to playing muted with a "Tap to unmute" button.
	 */
	function startMuted(video) {
		video.muted = true;
		var attempt = video.play();
		if (attempt && attempt.catch) {
			attempt.catch(function () {});
		}
		addStyles();
		var button = el('button', 'hcp-unmute', '\uD83D\uDD07 Tap to unmute');
		button.type = 'button';
		function done() {
			button.remove();
			video.removeEventListener('volumechange', onVolume);
		}
		function onVolume() {
			if (!video.muted) {
				done();
			}
		}
		button.addEventListener('click', function () {
			video.muted = false;
			done();
		});
		video.addEventListener('volumechange', onVolume);
		video.parentElement.appendChild(button);
	}

	function play(video) {
		var attempt = video.play();
		if (attempt && attempt.catch) {
			attempt.catch(function () { startMuted(video); });
		}
	}

	/**
	 * Load the next video page and swap its <main> in place of this one's, so
	 * playback can continue with sound. Anything unexpected (a failed request,
	 * a redirect such as a login gate, a page without a player) falls back to
	 * a normal page load.
	 */
	var swapped = false;

	function swapTo(url) {
		var target = new URL(url, window.location.href);
		fetch(target.toString(), { credentials: 'same-origin' })
			.then(function (response) {
				if (!response.ok || new URL(response.url).pathname !== target.pathname) {
					throw new Error('unexpected response');
				}
				return response.text();
			})
			.then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var next = doc.querySelector('#main');
				var current = document.querySelector('#main');
				if (!next || !current || !next.querySelector('video[data-hcp-video]')) {
					throw new Error('not a video page');
				}

				var referrer = window.location.href;
				document.title = doc.title;
				window.history.pushState({ hcpVideoSwap: true }, '', target.toString());
				swapped = true;
				next = document.adoptNode(next);
				current.replaceWith(next);
				window.scrollTo(0, 0);

				// Parsed scripts never run; recreate them in the live page so they do.
				next.querySelectorAll('script').forEach(function (old) {
					var script = document.createElement('script');
					Array.prototype.forEach.call(old.attributes, function (a) { script.setAttribute(a.name, a.value); });
					script.textContent = old.textContent;
					old.replaceWith(script);
				});

				var video = document.querySelector('#main video[data-hcp-video]');
				if (video.hasAttribute('data-hcp-endcard')) {
					bind(video);
				}
				document.dispatchEvent(new CustomEvent('hcp-video-swap', {
					detail: { url: target.toString(), title: doc.title, referrer: referrer }
				}));
				play(video);
			})
			.catch(function () {
				window.location.href = withAutoplay(url);
			});
	}

	// Back/forward after a swap: the page in view no longer matches the
	// address, so load it properly.
	window.addEventListener('popstate', function () {
		if (swapped) {
			window.location.reload();
		}
	});

	// Arrived from an autoplay countdown: start the page's video, then drop the
	// flag from the address so a reload or a shared link does not autoplay.
	function autoplayOnArrival() {
		var params = new URLSearchParams(window.location.search);
		if (!params.has(AUTOPLAY_PARAM)) {
			return;
		}
		params.delete(AUTOPLAY_PARAM);
		var query = params.toString();
		window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);

		var video = document.querySelector('video[data-hcp-video]');
		if (video) {
			play(video);
		}
	}

	function init() {
		document.querySelectorAll('video[data-hcp-endcard]').forEach(bind);
		autoplayOnArrival();
	}

	// Used by the edit screen to preview a card over its preview player.
	window.hcpEndcard = { show: show, remove: remove };

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
