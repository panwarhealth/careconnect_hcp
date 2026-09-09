/**
 * 2026 Clinical Audit form behaviour.
 *
 * Formidable renders answers from other pages as hidden inputs named
 * item_meta[<field id>], so every reader below works from the current page
 * regardless of which step is showing. Field ids are localised as
 * hcpAuditV2.fields (key -> id).
 */
(function ($) {
	'use strict';

	var cfg = window.hcpAuditV2 || { fields: {} };

	function id(key) {
		return cfg.fields[key] || 0;
	}

	function $form() {
		return $('form.frm-show-form').first();
	}

	// Every input carrying a field's value: visible controls and hidden
	// carry-overs, including "other" text and repeater rows.
	function inputs(key) {
		var fid = id(key);
		if (!fid) {
			return $();
		}
		return $form().find('[name^="item_meta[' + fid + ']"], [name^="item_meta[other][' + fid + ']"], [name*="[' + fid + ']"]');
	}

	function values(key) {
		var out = [];
		inputs(key).each(function () {
			var $el = $(this);
			var type = ($el.attr('type') || '').toLowerCase();
			if ((type === 'radio' || type === 'checkbox') && !this.checked) {
				return;
			}
			var v = $.trim($el.val() || '');
			if (v !== '') {
				out.push(v);
			}
		});
		return out;
	}

	function number(key) {
		var v = values(key);
		return v.length ? parseFloat(v[v.length - 1]) : NaN;
	}

	function firstVisible(key) {
		return inputs(key).filter(':visible').first();
	}

	function strip(label) {
		return $.trim(label.replace(/\s*\([^)]*\)\s*$/, ''));
	}

	// ------------------------------------------------------------------
	// Step 1B: criteria selected in Step 1A.
	// ------------------------------------------------------------------
	function renderCriteria() {
		var $list = $('.hcp-criteria');
		if (!$list.length) {
			return;
		}
		var items = [];
		var timeframe = values('v2-due9j');
		if (timeframe.length) {
			items.push(strip(timeframe[timeframe.length - 1]));
		}
		$.each(values('v2-w1fj3'), function (_, v) {
			if (v.indexOf('Other') !== 0) {
				items.push(strip(v));
			}
		});
		$.each(values('v2-wp5is'), function (_, v) {
			items.push(v);
		});
		$list.empty();
		$.each(items, function (_, item) {
			$('<li>').text(item).appendTo($list);
		});
	}

	// ------------------------------------------------------------------
	// Step 1B: counts may not exceed their denominator.
	// ------------------------------------------------------------------
	function limit($input, max, message) {
		var $wrap = $input.closest('.frm_form_field');
		var $err = $wrap.find('.hcp-limit-error').not('.hcp-sum-error');
		var raw = $.trim($input.val() || '');
		var val = parseFloat(raw);
		var min = parseFloat($input.attr('min'));
		if (isNaN(min)) {
			min = 0;
		}
		$input.attr('max', isNaN(max) ? null : max);
		var problem = '';
		if (raw !== '' && !isNaN(val)) {
			if (val < min) {
				problem = min > 0 ? 'Enter at least ' + min + '.' : 'This number cannot be negative.';
			} else if (val !== Math.floor(val)) {
				problem = 'Enter a whole number of patients.';
			} else if (!isNaN(max) && val > max) {
				problem = message;
			}
		}
		if (problem) {
			if (!$err.length) {
				// Not .frm_error: Formidable clears those on edit after a refused submit.
				$err = $('<p class="hcp-limit-error" role="alert"></p>').appendTo($wrap);
			}
			$err.text(problem);
			$input.addClass('frm_invalid');
			return false;
		}
		$err.remove();
		$input.removeClass('frm_invalid');
		return true;
	}

	function checkLimits() {
		// Floor and whole-number check on every count the user types.
		$form().find('input[type="number"]').not('[readonly]').filter(':visible').each(function () {
			limit($(this), NaN, '');
		});
		var total = number('v2-khh7w');
		var diagnosed = number('v2-9962s');
		var $diag = firstVisible('v2-9962s');
		if ($diag.length) {
			limit($diag, total, 'This number cannot exceed the total number of patients identified (' + total + ').');
		}
		$.each(cfg.diagnosedCounts || [], function (_, key) {
			var $el = firstVisible(key);
			if ($el.length) {
				limit($el, diagnosed, 'This number cannot exceed the number of patients diagnosed with anal fissure (' + diagnosed + ').');
			}
		});
		$.each(cfg.exclusiveGroups || [], function (_, group) {
			var sum = 0;
			var filled = 0;
			var $last = $();
			$.each(group.keys, function (_, key) {
				var $el = firstVisible(key);
				if (!$el.length) {
					return;
				}
				$last = $el;
				var v = parseFloat($el.val());
				if (!isNaN(v)) {
					sum += v;
					filled++;
				}
			});
			if (!$last.length) {
				return;
			}
			var $wrap = $last.closest('.frm_form_field');
			var $err = $wrap.find('.hcp-sum-error');
			if (filled && !isNaN(diagnosed) && sum > diagnosed) {
				if (!$err.length) {
					$err = $('<p class="hcp-limit-error hcp-sum-error" role="alert"></p>').appendTo($wrap);
				}
				$err.text('The ' + group.label + ' add up to ' + sum + ', more than the ' + diagnosed + ' patients diagnosed with anal fissure.');
			} else {
				$err.remove();
			}
		});
		$('.hcp-diag-count').text(isNaN(diagnosed) ? 'number of' : diagnosed);
	}

	// ------------------------------------------------------------------
	// Step 1B (D): generated statements.
	// ------------------------------------------------------------------
	function setAuto(name, text) {
		$('.hcp-auto-' + name + ' strong').text(text);
		inputs('v2-auto-' + name).val(text);
	}

	// Leader must exceed the runner-up by more than 20% to count as a predominance.
	function leader(groups) {
		var sorted = groups.slice().sort(function (a, b) { return b.n - a.n; });
		if (!sorted.length || !(sorted[0].n > 0)) {
			return null;
		}
		var next = sorted.length > 1 ? sorted[1].n : 0;
		return sorted[0].n > next * 1.2 ? sorted[0] : null;
	}

	function renderStatements() {
		var total = number('v2-khh7w');
		var diagnosed = number('v2-9962s');
		if (!isNaN(total) && !isNaN(diagnosed) && total > 0) {
			var pct = diagnosed / total * 100;
			setAuto('prevalence', pct < 10 ? 'lower' : (pct > 15 ? 'higher' : 'similar'));
		} else {
			setAuto('prevalence', '[lower / similar / higher]');
		}

		var ages = [
			{ label: '18 to 35 years', n: number('v2-sbk2o') },
			{ label: '36 to 50 years', n: number('v2-knwub') },
			{ label: '51 to 65 years', n: number('v2-wdxdg') },
			{ label: '65 to 75 years', n: number('v2-6kmxw') },
			{ label: 'over 75 years', n: number('v2-f199v') }
		];
		var age = leader(ages);
		if (!ages.some(function (g) { return !isNaN(g.n); })) {
			setAuto('age', '[were most commonly seen in the … age group / showed no age-related predominance]');
		} else {
			setAuto('age', age ? 'were most commonly seen in the ' + age.label + ' age group' : 'showed no age-related predominance');
		}

		var sexes = [
			{ label: 'males', n: number('v2-83nfc') },
			{ label: 'females', n: number('v2-8ufcu') }
		];
		var sex = leader(sexes);
		if (!sexes.some(function (g) { return !isNaN(g.n); })) {
			setAuto('sex', '[were most commonly seen in males / females / showed no sex-related predominance]');
		} else {
			setAuto('sex', sex ? 'were most commonly seen in ' + sex.label : 'showed no sex-related predominance');
		}
	}

	// ------------------------------------------------------------------
	// Step 2A: check buttons.
	// ------------------------------------------------------------------
	function answered(keys) {
		var ok = true;
		$.each(keys, function (_, key) {
			if (!values(key).length) {
				ok = false;
			}
		});
		return ok;
	}

	function initChecks() {
		$('.hcp-check').each(function () {
			var $check = $(this);
			var keys = ($check.attr('class') || '').split(/\s+/).filter(function (c) {
				return c.indexOf('hcp-check--') === 0;
			}).map(function (c) {
				return c.slice('hcp-check--'.length);
			});
			if ($check.data('hcpInit')) {
				return;
			}
			$check.data('hcpInit', true);
			if (answered(keys)) {
				$check.find('.hcp-check__feedback').addClass('is-open');
			}
			$check.on('click', '.hcp-check__btn', function () {
				if (!answered(keys)) {
					$check.find('.hcp-check__prompt').addClass('is-open');
					return;
				}
				$check.find('.hcp-check__prompt').removeClass('is-open');
				$check.find('.hcp-check__feedback').addClass('is-open');
			});
		});
	}

	// ------------------------------------------------------------------
	// Step 2B: areas chosen in Step 1B question 3.
	// ------------------------------------------------------------------
	function renderAreas() {
		var $list = $('.hcp-auto-areas');
		if (!$list.length) {
			return;
		}
		var areas = [];
		$.each(cfg.improvementAreas || [], function (_, key) {
			$.each(values(key), function (_, v) {
				areas.push(v);
			});
		});
		if (!areas.length) {
			return;
		}
		$list.empty();
		$.each(areas, function (_, a) {
			$('<li>').text(a).appendTo($list);
		});
	}

	// Every Check on the page must have been pressed before Next. Previous
	// and Save-and-continue-later stay free.
	function unchecked() {
		return $('.hcp-check').filter(function () {
			return !$(this).find('.hcp-check__feedback').hasClass('is-open');
		});
	}

	// Runs in the capture phase so it wins over Formidable's own click and
	// AJAX submit handlers.
	// A count over its limit blocks every button that saves the page:
	// Previous and Save-and-continue-later carry formnovalidate, so the
	// browser's own max check never runs for them.
	function gateLimits(e) {
		var $bad = $form().find('.hcp-limit-error').filter(':visible');
		if (!$bad.length) {
			return false;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		$('html, body').animate({ scrollTop: $bad.first().offset().top - 160 }, 300);
		$bad.first().prev('input').trigger('focus');
		return true;
	}

	function gateNext() {
		document.addEventListener('click', function (e) {
			var btn = e.target.closest ? e.target.closest('.frm_button_submit, .frm_page_skip, .frm_page_back, .frm_prev_page, .frm_save_draft') : null;
			if (!btn || !$form().length || !$.contains($form()[0], btn)) {
				return;
			}
			if (gateLimits(e)) {
				return;
			}
			if (btn.classList.contains('frm_page_back') || btn.classList.contains('frm_prev_page') || btn.classList.contains('frm_save_draft')) {
				return;
			}
			if (btn.classList.contains('frm_page_skip')) {
				var current = parseInt($('.frm_current_page input[type="button"]').val(), 10);
				if (parseInt(btn.value, 10) < current) {
					return;
				}
			}
			var $pending = unchecked();
			$('.hcp-check-gate').remove();
			if (!$pending.length) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			$pending.addClass('is-pending');
			$('<p class="frm_error hcp-check-gate" role="alert">Press Check on every question before continuing.</p>')
				.insertBefore($form().find('.frm_submit').first());
			$('html, body').animate({ scrollTop: $pending.first().offset().top - 120 }, 300);
		}, true);
		$(document).on('click', '.hcp-check__btn', function () {
			$(this).closest('.hcp-check').removeClass('is-pending');
		});
	}

	// Enter in a text or number box would "click" the first submit button,
	// which in a multi-page Formidable form is Previous: the page saves
	// without validation and the browser steps back. Only buttons submit.
	function blockImplicitSubmit() {
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' || !$form().length || !$.contains($form()[0], e.target)) {
				return;
			}
			var tag = e.target.tagName;
			if (tag === 'INPUT' && !/^(submit|button|reset)$/i.test(e.target.type)) {
				e.preventDefault();
				e.target.blur();
			}
		}, true);
	}

	function refresh() {
		renderCriteria();
		checkLimits();
		renderStatements();
		renderAreas();
		initChecks();
	}

	$(function () {
		refresh();
		gateNext();
		blockImplicitSubmit();
		$(document).on('frmPageChanged frmFormComplete', refresh);
		$(document).on('input change', 'form.frm-show-form input, form.frm-show-form textarea, form.frm-show-form select', function () {
			checkLimits();
			renderStatements();
			renderCriteria();
			renderAreas();
		});
	});
})(jQuery);
