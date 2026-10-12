/**
 * Sends Guess the Glucose quiz interactions to GA4:
 *   quiz_start     first answer
 *   quiz_answer    every answer: quiz_round (on-screen order), quiz_item (the food
 *                  paired against Hydralyte), quiz_result (correct / incorrect)
 *   quiz_complete  last answer: quiz_score (correct answers out of the rounds)
 * Listens in the capture phase so it reads each round before the quiz's own
 * handler marks it answered.
 */
(function () {
	'use strict';

	var cfg = window.hcpQuizAnalytics;
	var root = document.getElementById('ggq');
	if (!cfg || !cfg.measurementId || !root) {
		return;
	}

	var ready = false;
	function send(name, params) {
		if (!ready) {
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
			ready = true;
		}
		var payload = { send_to: cfg.measurementId, quiz: cfg.quiz || '' };
		for (var k in params) {
			payload[k] = params[k];
		}
		window.gtag('event', name, payload);
	}

	var rounds = root.querySelectorAll('.ggq-round');
	var total = rounds.length;
	var answered = 0;
	var score = 0;

	root.addEventListener('click', function (ev) {
		var tile = ev.target.closest ? ev.target.closest('.ggq-tile') : null;
		if (!tile) {
			return;
		}
		var round = tile.closest('.ggq-round');
		if (!round || round.classList.contains('is-done')) {
			return;
		}

		var food = round.querySelector('.ggq-tile[data-role="food"] .ggq-name');
		var correct = tile.getAttribute('data-role') !== 'food';
		var position = Array.prototype.indexOf.call(root.querySelectorAll('.ggq-round'), round) + 1;

		answered++;
		if (correct) {
			score++;
		}
		if (answered === 1) {
			send('quiz_start', {});
		}
		send('quiz_answer', {
			quiz_round: String(position),
			quiz_item: food ? food.textContent.replace(/\s+/g, ' ').trim() : '',
			quiz_result: correct ? 'correct' : 'incorrect'
		});
		if (answered === total) {
			send('quiz_complete', { quiz_score: String(score) });
		}
	}, true);
})();
