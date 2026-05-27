(function ($) {
	'use strict';

	function reindexOptions($group) {
		var groupIndex = String($group.attr('data-group-index'));

		$group.find('.laca-variant-option').each(function (optionIndex) {
			$(this).find('input').each(function () {
				var name = String($(this).attr('name') || '');

				name = name.replace(/laca_food_variants\[[^\]]+\]/, 'laca_food_variants[' + groupIndex + ']');
				name = name.replace(/\[options\]\[[^\]]+\]/, '[options][' + optionIndex + ']');
				$(this).attr('name', name);
			});
		});

		$group.attr('data-next-option-index', $group.find('.laca-variant-option').length);
	}

	function reindexGroups() {
		$('.laca-variant-groups .laca-variant-group').each(function (groupIndex) {
			var $group = $(this);

			$group.attr('data-group-index', groupIndex);
			$group.find('input').each(function () {
				var name = String($(this).attr('name') || '');

				$(this).attr('name', name.replace(/laca_food_variants\[[^\]]+\]/, 'laca_food_variants[' + groupIndex + ']'));
			});
			reindexOptions($group);
		});

		$('.laca-variant-groups').attr('data-next-index', $('.laca-variant-group').length);
	}

	function buildOptionHtml(groupIndex, optionIndex) {
		return [
			'<div class="laca-variant-option">',
				'<label>Tên lựa chọn',
					'<input type="text" name="laca_food_variants[' + groupIndex + '][options][' + optionIndex + '][name]" placeholder="VD: Lớn / Ít cay / Sốt me" />',
				'</label>',
				'<label>Cộng thêm',
					'<input type="number" step="any" name="laca_food_variants[' + groupIndex + '][options][' + optionIndex + '][price_delta]" value="0" />',
				'</label>',
				'<button type="button" class="button laca-variant-remove-option">Xóa</button>',
			'</div>'
		].join('');
	}

	$(function () {
		$('.laca-variant-add-group').on('click', function () {
			var $groups = $('.laca-variant-groups');
			var index = Number($groups.attr('data-next-index') || 0);
			var template = $('#laca-variant-group-template').html() || '';

			$groups.append(template.replace(/__group__/g, index));
			$groups.attr('data-next-index', index + 1);
		});

		$('.laca-variant-groups')
			.on('click', '.laca-variant-add-option', function () {
				var $group = $(this).closest('.laca-variant-group');
				var groupIndex = String($group.attr('data-group-index'));
				var optionIndex = Number($group.attr('data-next-option-index') || 0);

				$group.find('.laca-variant-options').append(buildOptionHtml(groupIndex, optionIndex));
				$group.attr('data-next-option-index', optionIndex + 1);
			})
			.on('click', '.laca-variant-remove-option', function () {
				var $group = $(this).closest('.laca-variant-group');
				var $options = $group.find('.laca-variant-option');

				if ($options.length <= 1) {
					$(this).closest('.laca-variant-option').find('input[type="text"]').val('');
					$(this).closest('.laca-variant-option').find('input[type="number"]').val('0');
					return;
				}

				$(this).closest('.laca-variant-option').remove();
				reindexOptions($group);
			})
			.on('click', '.laca-variant-remove-group', function () {
				var $groups = $('.laca-variant-group');

				if ($groups.length <= 1) {
					$(this).closest('.laca-variant-group').find('input[type="text"]').val('');
					$(this).closest('.laca-variant-group').find('input[type="number"]').val('0');
					return;
				}

				$(this).closest('.laca-variant-group').remove();
				reindexGroups();
			});
	});
})(jQuery);
