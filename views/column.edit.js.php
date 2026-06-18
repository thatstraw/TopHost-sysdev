<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


use Modules\TopHostsMonzphere\Includes\CWidgetFieldColumnsList;

?>

window.tophostsmonzphere_column_edit_form = new class {

	init({form_id, thresholds, colors}) {
		this._overlay = overlays_stack.getById('tophostsmonzphere-column-edit-overlay');
		this._dialogue = this._overlay.$dialogue[0];
		this._$widget_form = $(`#${form_id}`);

		this._$thresholds_table = this._$widget_form.find('#thresholds_table');

		$('[name="data"], [name="aggregate_function"], [name="display"], [name="history"]', this._$widget_form)
			.on('change', () => this._update());

		colorPalette.setThemeColors(colors);

		this._$thresholds_table.dynamicRows({
			rows: thresholds,
			template: '#thresholds-row-tmpl',
			allow_empty: true,
			dataCallback: row_data => {
				if (!('color' in row_data)) {
					const colors = this._$widget_form[0].querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}`);
					const used_colors = [];

					for (const color of colors) {
						if (color.color !== '') {
							used_colors.push(color.color);
						}
					}

					row_data.color = colorPalette.getNextColor(used_colors);
				}
			}
		});

		this._$thresholds_table
			.on('afteradd.dynamicRows', () => this._update())
			.on('afterremove.dynamicRows', () => this._update());

		this._$widget_form[0].addEventListener('change', ({target}) => {
			if (target.matches('[type="text"]')) {
				target.value = target.value.trim();
			}
		});

		// Initialize form elements accessibility.
		this._update();

		this._$widget_form[0].style.display = '';
		this._overlay.recoverFocus();
	}

	_update() {
		const display_as_is = ($('[name="display"]:checked', this._$widget_form).val() ==
			<?= CWidgetFieldColumnsList::DISPLAY_AS_IS ?>);
		const history_data_trends = ($('[name="history"]:checked', this._$widget_form).val() ==
			<?= CWidgetFieldColumnsList::HISTORY_DATA_TRENDS ?>);
		const data_item_value = ($('[name="data"]', this._$widget_form).val() ==
			<?= CWidgetFieldColumnsList::DATA_ITEM_VALUE ?>);
		const data_text = ($('[name="data"]', this._$widget_form).val() ==
			<?= CWidgetFieldColumnsList::DATA_TEXT ?>);
		const aggregate_function = parseInt(document.getElementById('aggregate_function').value);

		$('#item', this._$widget_form).multiSelect(data_item_value ? 'enable' : 'disable');
		$('[name="aggregate_function"]', this._$widget_form).attr('disabled', !data_item_value);
		$('[name="display"],[name="history"]', this._$widget_form).attr('disabled', !data_item_value);
		$('[name="text"]', this._$widget_form).attr('disabled', !data_text);
		$('[name="min"],[name="max"]', this._$widget_form).attr('disabled', display_as_is || !data_item_value);
		$('[name="decimal_places"]', this._$widget_form).attr('disabled', !data_item_value);
		this._$thresholds_table.toggleClass('disabled', !data_item_value);

		// Toggle warning icons for non-numeric items settings.
		if (data_item_value) {
			const aggregate_warning_functions = [<?= AGGREGATE_AVG ?>, <?= AGGREGATE_MIN ?>, <?= AGGREGATE_MAX ?>,
				<?= AGGREGATE_SUM ?>
			];

			document.getElementById('tophosts-column-aggregate-function-warning').style.display =
					aggregate_warning_functions.includes(aggregate_function)
				? ''
				: 'none';

			document.getElementById('tophosts-column-display-warning').style.display = display_as_is ? 'none' : '';
			document.getElementById('tophosts-column-history-data-warning').style.display = history_data_trends
				? ''
				: 'none';
		}

		this._$widget_form[0].fields.time_period.disabled = !data_item_value
			|| aggregate_function == <?= AGGREGATE_NONE ?>;

		// Toggle visibility of disabled form elements.
		$('.form-grid > label', this._$widget_form).each((i, elm) => {
			const form_field = $(elm).next();
			const is_visible = (form_field.find(':disabled,.disabled').length == 0);

			$(elm).toggle(is_visible);
			form_field.toggle(is_visible);
		});
	}

	submit() {
		const form = this._$widget_form[0];
		const curl = new Curl(form.getAttribute('action'));
		const fields = getFormFields(form);

		this._overlay.setLoading();

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(fields)
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this._overlay.dialogueid);

				this._dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((exception) => {
				const form = this._$widget_form[0];

				for (const element of form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				form.parentNode.insertBefore(message_box, form);
			})
			.finally(() => {
				this._overlay.unsetLoading();
			});
	}
};
