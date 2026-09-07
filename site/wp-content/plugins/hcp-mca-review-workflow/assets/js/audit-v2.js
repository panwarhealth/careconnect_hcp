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
		var $err = $wrap.find('.hcp-limit-error');
		var val = parseFloat($input.val());
		$input.attr('max', isNaN(max) ? null : max);
		if (!isNaN(val) && !isNaN(max) && val > max) {
			if (!$err.length) {
				$err = $('<p class="frm_error hcp-limit-error" role="alert"></p>').appendTo($wrap);
			}
			$err.text(message);
			$input.addClass('frm_invalid');
			return false;
		}
		$err.remove();
		$input.removeClass('frm_invalid');
		return true;
	}

	function checkLimits() {
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
			var keys = ($check.data('fields') || '').split(',');
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

	function refresh() {
		renderCriteria();
		checkLimits();
		renderStatements();
		renderAreas();
		initChecks();
	}

	$(function () {
		refresh();
		$(document).on('frmPageChanged frmFormComplete', refresh);
		$(document).on('input change', 'form.frm-show-form input, form.frm-show-form textarea, form.frm-show-form select', function () {
			checkLimits();
			renderStatements();
			renderCriteria();
			renderAreas();
		});
	});
})(jQuery);
