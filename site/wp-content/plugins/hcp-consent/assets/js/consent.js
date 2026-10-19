/**
 * Saves the marketing choice from any of its three homes: the pop-up, Edit
 * Your Profile and /consent-preferences/. What happens after a save depends on
 * where the choice sits, not on which page it is.
 */
(function () {
	var cfg = window.hcpConsent;
	if (!cfg) return;

	document.addEventListener('click', function (ev) {
		var btn = ev.target.closest && ev.target.closest('[data-hcp-consent-choice] [data-answer]');
		if (!btn) return;

		var choice = btn.closest('[data-hcp-consent-choice]');
		var buttons = choice.querySelectorAll('[data-answer]');
		var error = choice.querySelector('.hcp-consent-choice__error');

		buttons.forEach(function (b) { b.disabled = true; });
		error.hidden = true;

		fetch(cfg.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify({ answer: btn.getAttribute('data-answer'), method: choice.getAttribute('data-method') })
		})
			.then(function (res) {
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.json();
			})
			.then(function (data) { saved(choice, data); })
			.catch(function () { error.hidden = false; })
			.then(function () { buttons.forEach(function (b) { b.disabled = false; }); });
	});

	function saved(choice, data) {
		var popup = choice.closest('.hcp-popup');
		if (popup) {
			popup.dispatchEvent(new CustomEvent('hcp-popup:close'));
			return;
		}

		var scope = choice.parentNode;
		var status = scope.querySelector('[data-hcp-consent-status]');
		if (status && data.status) status.textContent = data.status;

		var done = scope.querySelector('[data-hcp-consent-saved]');
		if (done) {
			choice.hidden = true;
			done.hidden = false;
		}
	}
})();
