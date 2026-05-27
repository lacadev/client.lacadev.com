(function ($) {
	'use strict';

	function reindexRows() {
		$('.laca-combo-items .laca-combo-item-row').each(function (index) {
			$(this).find('.laca-combo-item-row__badge').text('#' + (index + 1));
			$(this).find('select, input').each(function () {
				var name = String($(this).attr('name') || '');

				$(this).attr('name', name.replace(/laca_combo_items\[[^\]]+\]/, 'laca_combo_items[' + index + ']'));
			});
		});

		$('.laca-combo-items').attr('data-next-index', $('.laca-combo-items .laca-combo-item-row').length);
	}

	function formatMoney(amount) {
		return Number(amount || 0).toLocaleString('vi-VN') + 'đ';
	}

	function updateOriginalPricePreview() {
		var total = 0;

		$('.laca-combo-items .laca-combo-item-row').each(function () {
			var $row = $(this);
			var price = Number($row.find('select option:selected').attr('data-price') || 0);
			var quantity = Math.max(1, Number($row.find('input[type="number"]').val() || 1));

			total += price * quantity;
		});

		$('.laca-combo-price-preview strong').text(formatMoney(total));
	}

	function syncUniqueFoodSelections() {
		var selectedValues = {};
		var hadDuplicate = false;

		$('.laca-combo-items select').each(function () {
			var value = String($(this).val() || '0');

			if (value !== '0') {
				if (selectedValues[value]) {
					$(this).val('0');
					hadDuplicate = true;
					return;
				}

				selectedValues[value] = true;
			}
		});

		selectedValues = {};
		$('.laca-combo-items select').each(function () {
			var value = String($(this).val() || '0');

			if (value !== '0') {
				selectedValues[value] = true;
			}
		});

		$('.laca-combo-items select').each(function () {
			var currentValue = String($(this).val() || '0');

			$(this).find('option').each(function () {
				var optionValue = String($(this).attr('value') || '0');
				var shouldDisable = optionValue !== '0' && optionValue !== currentValue && !!selectedValues[optionValue];

				$(this).prop('disabled', shouldDisable);
			});
		});

		$('.laca-combo-unique-warning').prop('hidden', !hadDuplicate);
	}

	function refreshComboBuilder() {
		syncUniqueFoodSelections();
		updateOriginalPricePreview();
	}

	$(function () {
		$('.laca-combo-add-row').on('click', function () {
			var $items = $('.laca-combo-items');
			var index = Number($items.attr('data-next-index') || 0);
			var template = $('#laca-combo-row-template').html() || '';

			$items.append(template.replace(/__index__/g, index));
			$items.attr('data-next-index', index + 1);
			refreshComboBuilder();
		});

		$('.laca-combo-items').on('click', '.laca-combo-remove-row', function () {
			var $rows = $('.laca-combo-items .laca-combo-item-row');

			if ($rows.length <= 1) {
				$(this).closest('.laca-combo-item-row').find('select').val('0');
				$(this).closest('.laca-combo-item-row').find('input[type="number"]').val('1');
				refreshComboBuilder();
				return;
			}

			$(this).closest('.laca-combo-item-row').remove();
			reindexRows();
			refreshComboBuilder();
		});

		$('.laca-combo-items').on('change input', 'select, input[type="number"]', refreshComboBuilder);
		refreshComboBuilder();
	});
})(jQuery);
