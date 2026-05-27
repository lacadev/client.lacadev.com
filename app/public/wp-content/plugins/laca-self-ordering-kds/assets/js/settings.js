(function ($) {
	'use strict';

	function reindexRules() {
		$('.laca-promo-rules .laca-promo-rule').each(function (index) {
			$(this).attr('data-rule-index', index);
			$(this).find('.laca-promo-rule__number').text(index + 1);
			$(this).find('input, select, textarea').each(function () {
				var name = String($(this).attr('name') || '');

				$(this).attr('name', name.replace(/promotion_rules\[[^\]]+\]/, 'promotion_rules[' + index + ']'));
			});
		});

		$('.laca-promo-rules').attr('data-next-index', $('.laca-promo-rule').length);
	}

	function showSettingsSection(section) {
		var $shell = $('.laca-settings-shell');
		var target = section || $shell.attr('data-active-section') || 'brand';

		$shell.attr('data-active-section', target);
		$('.laca-settings-hub a').removeClass('is-active');
		$('.laca-settings-hub a[data-section="' + target + '"]').addClass('is-active');
		$('.laca-settings-section').removeClass('is-active');
		$('.laca-settings-section[data-section="' + target + '"]').addClass('is-active');
	}

	$(function () {
		showSettingsSection($('.laca-settings-shell').attr('data-active-section'));

		$('.laca-settings-hub').on('click', 'a[data-section]', function (event) {
			event.preventDefault();
			showSettingsSection($(this).data('section'));
		});

		$('.laca-add-promo-rule').on('click', function () {
			var $rules = $('.laca-promo-rules');
			var index = Number($rules.attr('data-next-index') || 0);
			var template = $('#laca-promo-rule-template').html() || '';

			$rules.append(template.replace(/__index__/g, index).replace(/__number__/g, index + 1));
			$rules.attr('data-next-index', index + 1);
		});

		$('.laca-promo-rules').on('click', '.laca-remove-promo-rule', function () {
			$(this).closest('.laca-promo-rule').remove();
			reindexRules();
		});

		$('.laca-test-sms-button').on('click', function () {
			var $button = $(this);
			var $box = $button.closest('.laca-sms-test-box');
			var $result = $box.find('.laca-test-sms-result');
			var $spinner = $box.find('.laca-test-sms-spinner');
			var phone = String($('#laca_sms_test_phone').val() || '').trim();
			var message = String($('#laca_sms_test_message').val() || '').trim();

			$result.removeClass('is-success is-error').text('');

			if (!phone) {
				$result.addClass('is-error').text('Vui lòng nhập số điện thoại test.');
				return;
			}

			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.text(lacaKdsSettings.i18n.testing);

			$.ajax({
				url: lacaKdsSettings.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'laca_kds_test_notification',
					nonce: lacaKdsSettings.nonce,
					phone: phone,
					message: message,
					provider: $('#laca_notification_provider').val() || '',
					endpoint: $('#laca_notification_api_endpoint').val() || '',
					api_key: $('#laca_notification_api_key').val() || '',
					secret_key: $('#laca_notification_secret_key').val() || '',
					template_id: $('#laca_notification_template_id').val() || '',
					sms_type: $('#laca_notification_sms_type').val() || '',
					sender: $('#laca_notification_sender').val() || ''
				}
			})
				.done(function (response) {
					var data = response && response.data ? response.data : {};
					var text = data.message || (response.success ? lacaKdsSettings.i18n.success : lacaKdsSettings.i18n.error);

					if (data.http_code) {
						text += ' HTTP ' + data.http_code + '.';
					}

					$result
						.toggleClass('is-success', Boolean(response.success))
						.toggleClass('is-error', !response.success)
						.text(text);
				})
				.fail(function () {
					$result.addClass('is-error').text(lacaKdsSettings.i18n.error);
				})
				.always(function () {
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				});
		});
	});
})(jQuery);
