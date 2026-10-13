/**
 * End cards for hcp-videos players. When a <video data-hcp-endcard> finishes,
 * fullscreen is exited (an overlay cannot sit over native fullscreen) and a
 * clickable card is laid over the player: heading, optional thumbnail, title,
 * a link button and Replay. Playing or seeking again removes the card.
 * window.hcpEndcard exposes the same rendering for the edit-screen preview.
 */
(function () {
	'use strict';

	var CSS = '' +
		'.hcp-endcard{position:absolute;inset:0;z-index:10;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(10,20,30,.86);color:#fff;text-align:center;}' +
		'.hcp-endcard-inner{display:flex;align-items:center;gap:1.25rem;max-width:640px;text-align:left;}' +
		// Whole thumbnail on the light panel the listing cards use, never cropped
		// (some posters are portrait).
		'.hcp-endcard-thumb{flex:0 0 42%;max-width:240px;aspect-ratio:16/9;box-sizing:border-box;padding:.5rem;background:#F6F9FF;object-fit:contain;border-radius:8px;}' +
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
		'.hcp-endcard.is-narrow .hcp-endcard-inner{flex-direction:column;text-align:center;gap:.5rem;}' +
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
			card.remove();
		}
	}

	function show(video, data) {
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

		var link = el('a', 'hcp-endcard-link', data.label + ' →');
		link.href = data.url;
		link.setAttribute('data-hcp-endcard-link', '');
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

	function init() {
		document.querySelectorAll('video[data-hcp-endcard]').forEach(bind);
	}

	// Used by the edit screen to preview a card over its preview player.
	window.hcpEndcard = { show: show, remove: remove };

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
