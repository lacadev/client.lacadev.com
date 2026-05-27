(function ($) {
	'use strict';

	var mediaFrame = null;
	var $activeThumb = null;
	var settings = window.lacaKdsFoodList || {};

	function i18n(key, fallback) {
		if (settings.i18n && settings.i18n[key]) {
			return settings.i18n[key];
		}

		return fallback;
	}

	function getFoodId($row) {
		var rowId = String($row.attr('id') || '');
		var match = rowId.match(/^post-(\d+)$/);

		return match ? match[1] : '';
	}

	function getAttachmentUrl(attachment) {
		if (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
			return attachment.sizes.thumbnail.url;
		}

		return attachment.url || '';
	}

	function getRowData($row) {
		return {
			title: $.trim($row.find('.laca-food-inline-title').val()),
			price: String($row.find('.laca-food-inline-price').val() || '0'),
			thumbnail_id: String($row.find('.laca-food-inline-thumb').attr('data-thumbnail-id') || '0'),
			is_available: $row.find('.laca-food-inline-available').is(':checked') ? '1' : '0'
		};
	}

	function getRowHash($row) {
		return JSON.stringify(getRowData($row));
	}

	function setSavedHash($row) {
		$row.data('lacaSavedHash', getRowHash($row));
	}

	function hasChanged($row) {
		return getRowHash($row) !== $row.data('lacaSavedHash');
	}

	function setStatus($row, type, message) {
		$row.find('.laca-food-inline-status')
			.removeClass('is-idle is-saving is-success is-error is-dirty')
			.addClass(type ? 'is-' + type : 'is-idle')
			.text(message || i18n('autosave', 'Tự lưu'));
	}

	function refreshDirtyState($row) {
		if (hasChanged($row)) {
			$row.addClass('laca-food-inline-is-dirty');

			if ($row.hasClass('laca-food-inline-is-saving')) {
				$row.data('lacaNeedsSave', true);
				return;
			}

			setStatus($row, 'dirty', i18n('dirty', 'Có thay đổi chưa lưu'));
			return;
		}

		$row.removeClass('laca-food-inline-is-dirty');

		if (!$row.hasClass('laca-food-inline-is-saving')) {
			setStatus($row, 'idle', i18n('autosave', 'Tự lưu'));
		}
	}

	function renderThumbnail($thumb, thumbnailId, imageUrl) {
		var $preview = $thumb.find('.laca-food-inline-thumb__preview');
		var normalizedId = Number(thumbnailId || 0);

		$thumb.attr('data-thumbnail-id', normalizedId);
		$preview.empty();

		if (imageUrl) {
			$preview.append($('<img>', {
				src: imageUrl,
				alt: ''
			}));
			$thumb.find('.laca-food-inline-remove-image').removeClass('is-hidden');
			return;
		}

		$preview.append($('<span>').text(i18n('noImage', 'Chạm để chọn ảnh')));
		$thumb.find('.laca-food-inline-remove-image').addClass('is-hidden');
	}

	function applySavedData($row, data, requestHash) {
		if (getRowHash($row) !== requestHash) {
			$row.data('lacaSavedHash', requestHash);
			refreshDirtyState($row);
			return;
		}

		$row.find('.laca-food-inline-title').val(data.title || getRowData($row).title);
		$row.find('.laca-food-inline-price').val(data.price || 0);
		$row.find('.laca-food-inline-available').prop('checked', !!data.is_available);
		renderThumbnail($row.find('.laca-food-inline-thumb'), data.thumbnail_id, data.thumbnail_url);
		setSavedHash($row);
		$row.removeClass('laca-food-inline-is-dirty');
		setStatus($row, 'success', i18n('saved', 'Đã lưu'));
	}

	function saveRow($row, force) {
		var rowData = getRowData($row);
		var requestHash = JSON.stringify(rowData);

		if (!force && !hasChanged($row)) {
			refreshDirtyState($row);
			return;
		}

		if (!rowData.title) {
			setStatus($row, 'error', i18n('titleError', 'Tên món không được để trống.'));
			$row.find('.laca-food-inline-title').trigger('focus');
			return;
		}

		if ($row.hasClass('laca-food-inline-is-saving')) {
			$row.data('lacaNeedsSave', true);
			return;
		}

		$row.addClass('laca-food-inline-is-saving');
		setStatus($row, 'saving', i18n('saving', 'Đang lưu...'));

		$.ajax({
			url: settings.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'laca_kds_inline_update_food',
				nonce: settings.nonce,
				food_id: getFoodId($row),
				title: rowData.title,
				price: rowData.price,
				thumbnail_id: rowData.thumbnail_id,
				is_available: rowData.is_available
			}
		}).done(function (response) {
			var data = response && response.data ? response.data : {};

			if (!response || !response.success) {
				setStatus($row, 'error', data.message || i18n('error', 'Không thể lưu. Vui lòng thử lại.'));
				return;
			}

			applySavedData($row, data, requestHash);
		}).fail(function (xhr) {
			var response = xhr && xhr.responseJSON ? xhr.responseJSON : {};
			var data = response.data || {};

			setStatus($row, 'error', data.message || i18n('error', 'Không thể lưu. Vui lòng thử lại.'));
		}).always(function () {
			var needsSave = !!$row.data('lacaNeedsSave');

			$row.removeClass('laca-food-inline-is-saving');
			$row.removeData('lacaNeedsSave');

			if (needsSave && hasChanged($row)) {
				saveRow($row, true);
			} else if (hasChanged($row)) {
				refreshDirtyState($row);
			}
		});
	}

	function openMediaFrame($thumb) {
		if (!window.wp || !wp.media) {
			setStatus($thumb.closest('tr'), 'error', i18n('error', 'Không thể lưu. Vui lòng thử lại.'));
			return;
		}

		$activeThumb = $thumb;

		if (!mediaFrame) {
			mediaFrame = wp.media({
				title: i18n('chooseImage', 'Chọn ảnh món'),
				button: {
					text: i18n('useImage', 'Dùng ảnh này')
				},
				library: {
					type: 'image'
				},
				multiple: false
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				var $row = $activeThumb ? $activeThumb.closest('tr') : $();

				if (!$activeThumb || !attachment || !$row.length) {
					return;
				}

				renderThumbnail($activeThumb, attachment.id, getAttachmentUrl(attachment));
				refreshDirtyState($row);
				saveRow($row, true);
			});
		}

		mediaFrame.open();
	}

	$(function () {
		$('.post-type-laca_food .wp-list-table tbody tr').each(function () {
			setSavedHash($(this));
		});

		$('.post-type-laca_food')
			.on('input', '.laca-food-inline-title, .laca-food-inline-price', function () {
				refreshDirtyState($(this).closest('tr'));
			})
			.on('blur', '.laca-food-inline-title, .laca-food-inline-price', function () {
				saveRow($(this).closest('tr'), false);
			})
			.on('change', '.laca-food-inline-available', function () {
				var $row = $(this).closest('tr');

				refreshDirtyState($row);
				saveRow($row, true);
			})
			.on('keydown', '.laca-food-inline-title, .laca-food-inline-price', function (event) {
				if (event.key === 'Enter') {
					event.preventDefault();
					$(this).trigger('blur');
				}
			})
			.on('click', '.laca-food-inline-image-button', function () {
				openMediaFrame($(this).closest('.laca-food-inline-thumb'));
			})
			.on('click', '.laca-food-inline-remove-image', function (event) {
				var $thumb = $(this).closest('.laca-food-inline-thumb');
				var $row = $thumb.closest('tr');

				event.preventDefault();
				event.stopPropagation();

				renderThumbnail($thumb, 0, '');
				refreshDirtyState($row);
				saveRow($row, true);
			});

		$(window).on('beforeunload', function () {
			if ($('.laca-food-inline-is-dirty').length) {
				return i18n('dirty', 'Có thay đổi chưa lưu');
			}

			return undefined;
		});
	});
})(jQuery);
