(function () {
	const cfg = window.hcpMcaEnrol;
	if (!cfg) return;

	const courseUrlPath = (function () {
		try {
			return new URL(cfg.courseUrl, window.location.origin).pathname;
		} catch (e) {
			return cfg.courseUrl || '';
		}
	})();

	function matchesCourseLink(href) {
		if (!href) return false;
		try {
			const url = new URL(href, window.location.origin);
			return url.pathname === courseUrlPath || url.pathname === courseUrlPath + '/';
		} catch (e) {
			return false;
		}
	}

	function buildModal() {
		const wrap = document.createElement('div');
		wrap.className = 'hcp-racgp-modal';
		wrap.setAttribute('role', 'dialog');
		wrap.setAttribute('aria-modal', 'true');
		wrap.setAttribute('aria-labelledby', 'hcp-racgp-modal-title');
		wrap.innerHTML = [
			'<div class="hcp-racgp-modal__backdrop" data-close="1"></div>',
			'<div class="hcp-racgp-modal__dialog">',
			'  <button type="button" class="hcp-racgp-modal__close" aria-label="Close" data-close="1">&times;</button>',
			'  <h2 id="hcp-racgp-modal-title">One quick step</h2>',
			'  <p>To enrol you in this CPD activity we need your RACGP number. This is used to report your completion to RACGP.</p>',
			'  <form class="hcp-racgp-modal__form" novalidate>',
			'    <label for="hcp-racgp-input">RACGP Number</label>',
			'    <input type="text" id="hcp-racgp-input" name="racgp_number" required inputmode="numeric" autocomplete="off" maxlength="7" pattern="\\d{6,7}" />',
			'    <div class="hcp-racgp-modal__hint">6 to 7 digits</div>',
			'    <div class="hcp-racgp-modal__error" hidden></div>',
			'    <div class="hcp-racgp-modal__actions">',
			'      <button type="submit" class="btn cta">Start Course</button>',
			'      <button type="button" class="hcp-racgp-modal__cancel" data-close="1">Cancel</button>',
			'    </div>',
			'  </form>',
			'</div>',
		].join('');
		return wrap;
	}

	let modalEl = null;
	let lastFocus = null;

	function openModal() {
		if (modalEl) return;
		modalEl = buildModal();
		document.body.appendChild(modalEl);
		document.body.classList.add('hcp-racgp-modal-open');
		lastFocus = document.activeElement;
		const input = modalEl.querySelector('#hcp-racgp-input');
		setTimeout(function () { input && input.focus(); }, 0);

		// Strip any non-digit characters as the user types or pastes. Also enforces the
		// 7-char cap defensively in case maxlength is bypassed (e.g. autofill, paste).
		input.addEventListener('input', function () {
			const cleaned = input.value.replace(/\D/g, '').slice(0, 7);
			if (cleaned !== input.value) {
				input.value = cleaned;
			}
		});

		modalEl.addEventListener('click', function (e) {
			if (e.target && e.target.getAttribute && e.target.getAttribute('data-close') === '1') {
				closeModal();
			}
		});
		document.addEventListener('keydown', escHandler);
		modalEl.querySelector('form').addEventListener('submit', onSubmit);
	}

	function closeModal() {
		if (!modalEl) return;
		document.removeEventListener('keydown', escHandler);
		modalEl.remove();
		modalEl = null;
		document.body.classList.remove('hcp-racgp-modal-open');
		if (lastFocus && typeof lastFocus.focus === 'function') {
			lastFocus.focus();
		}
	}

	function escHandler(e) {
		if (e.key === 'Escape') closeModal();
	}

	function showError(msg) {
		const err = modalEl && modalEl.querySelector('.hcp-racgp-modal__error');
		if (!err) return;
		err.textContent = msg;
		err.hidden = false;
	}

	function setBusy(busy) {
		if (!modalEl) return;
		const submitBtn = modalEl.querySelector('button[type="submit"]');
		const input     = modalEl.querySelector('#hcp-racgp-input');
		const cancelBtn = modalEl.querySelector('.hcp-racgp-modal__cancel');
		const closeBtn  = modalEl.querySelector('.hcp-racgp-modal__close');

		if (submitBtn) {
			submitBtn.disabled = !!busy;
			if (busy) {
				if (!submitBtn.dataset.originalLabel) {
					submitBtn.dataset.originalLabel = submitBtn.textContent;
				}
				submitBtn.innerHTML = '<span class="hcp-racgp-modal__spinner" aria-hidden="true"></span>Enrolling you in the course…';
			} else if (submitBtn.dataset.originalLabel) {
				submitBtn.textContent = submitBtn.dataset.originalLabel;
			}
		}
		if (input)     input.disabled     = !!busy;
		if (cancelBtn) cancelBtn.disabled = !!busy;
		if (closeBtn)  closeBtn.disabled  = !!busy;
	}

	function onSubmit(e) {
		e.preventDefault();
		const input = modalEl.querySelector('#hcp-racgp-input');
		const value = (input && input.value || '').trim();
		const err = modalEl.querySelector('.hcp-racgp-modal__error');
		if (err) err.hidden = true;

		if (!value) {
			showError('Please enter your RACGP number.');
			return;
		}
		if (!/^\d{6,7}$/.test(value)) {
			showError('RACGP numbers are usually 6 to 7 digits. Please check and try again.');
			return;
		}

		setBusy(true);
		const body = new URLSearchParams();
		body.append('action', 'hcp_mca_enrol_with_racgp');
		body.append('nonce', cfg.nonce);
		body.append('racgp_number', value);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		})
		.then(function (r) { return r.json().catch(function () { return { success: false }; }); })
		.then(function (json) {
			if (json && json.success && json.data && json.data.redirect) {
				window.location.href = json.data.redirect;
				return;
			}
			const msg = (json && json.data && json.data.message) || 'Something went wrong. Please try again.';
			showError(msg);
			setBusy(false);
		})
		.catch(function () {
			showError('Network error. Please try again.');
			setBusy(false);
		});
	}

	function shouldIntercept() {
		return cfg.hasRacgp !== true && cfg.hasRacgp !== 'true' && cfg.hasRacgp !== 1 && cfg.hasRacgp !== '1';
	}

	function attach() {
		// Delegated handler so dynamically-rendered LD buttons are also caught.
		document.addEventListener('click', function (e) {
			if (!shouldIntercept()) return;

			// Anchor links to the prereq course
			const anchor = e.target.closest && e.target.closest('a[href]');
			if (anchor && matchesCourseLink(anchor.getAttribute('href'))) {
				e.preventDefault();
				e.stopPropagation();
				openModal();
				return;
			}

			// LearnDash "Take this Course" form submit button on the course page
			const ldBtn = e.target.closest && e.target.closest('.learndash_join_button button, .ld-button.ld-button-alternate, form.learndash_join_form button');
			if (ldBtn) {
				e.preventDefault();
				e.stopPropagation();
				openModal();
				return;
			}
		}, true);

		// Also catch LD form submits directly in case the click was on a wrapped child.
		document.addEventListener('submit', function (e) {
			if (!shouldIntercept()) return;
			const form = e.target;
			if (form && (form.classList.contains('learndash_join_form') || (form.getAttribute('action') || '').indexOf('learndash') !== -1)) {
				e.preventDefault();
				openModal();
			}
		}, true);
	}

	function maybeAutoOpen() {
		if (cfg.autoOpen === true || cfg.autoOpen === 'true' || cfg.autoOpen === 1 || cfg.autoOpen === '1') {
			openModal();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			attach();
			maybeAutoOpen();
		});
	} else {
		attach();
		maybeAutoOpen();
	}
})();
