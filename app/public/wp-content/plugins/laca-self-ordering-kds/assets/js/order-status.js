(function ($) {
	'use strict';

	var previousStatus = '';
	var hasInitialStatus = false;
	var hasAlertedReady = false;
	var pollTimer = null;

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function formatMoney(amount) {
		return Number(amount || 0).toLocaleString('vi-VN') + 'đ';
	}

	function statusLabel(status) {
		return lacaOrderStatus.i18n[status] || status;
	}

	function canUseNotification() {
		return 'Notification' in window;
	}

	function canUseWebPush() {
		return 'serviceWorker' in navigator && 'PushManager' in window && !!lacaOrderStatus.vapidPublicKey;
	}

	function base64UrlToUint8Array(value) {
		var padding = '='.repeat((4 - value.length % 4) % 4);
		var base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
		var rawData = window.atob(base64);
		var output = new Uint8Array(rawData.length);

		for (var index = 0; index < rawData.length; index += 1) {
			output[index] = rawData.charCodeAt(index);
		}

		return output;
	}

	function savePushSubscription(subscription) {
		var $app = $('.laca-status-app');

		return $.ajax({
			url: lacaOrderStatus.pushSubscribeUrl,
			method: 'POST',
			contentType: 'application/json',
			dataType: 'json',
			data: JSON.stringify({
				order_id: $app.data('order-id'),
				token: $app.data('token'),
				subscription: subscription.toJSON()
			})
		});
	}

	function subscribeWebPush() {
		if (!canUseWebPush()) {
			return $.Deferred().resolve().promise();
		}

		return navigator.serviceWorker.register(lacaOrderStatus.serviceWorkerUrl)
			.then(function (registration) {
				return registration.pushManager.getSubscription().then(function (existingSubscription) {
					if (existingSubscription) {
						return existingSubscription;
					}

					return registration.pushManager.subscribe({
						userVisibleOnly: true,
						applicationServerKey: base64UrlToUint8Array(lacaOrderStatus.vapidPublicKey)
					});
				});
			})
			.then(savePushSubscription);
	}

	function showNotifyCallout() {
		var $callout = $('.laca-status-notify-callout');

		if (!$callout.length) {
			return;
		}

		$callout.prop('hidden', false);
		if (!canUseNotification()) {
			$callout.addClass('is-muted');
			$('.laca-status-enable-notify').prop('disabled', true).text(lacaOrderStatus.i18n.notifyUnsupported);
		}
	}

	function playReadyTone() {
		var AudioContext = window.AudioContext || window.webkitAudioContext;

		if (!AudioContext) {
			return;
		}

		try {
			var context = new AudioContext();
			var oscillator = context.createOscillator();
			var gain = context.createGain();

			oscillator.type = 'sine';
			oscillator.frequency.value = 740;
			gain.gain.setValueAtTime(0.0001, context.currentTime);
			gain.gain.exponentialRampToValueAtTime(0.12, context.currentTime + 0.02);
			gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.55);
			oscillator.connect(gain);
			gain.connect(context.destination);
			oscillator.start();
			oscillator.stop(context.currentTime + 0.6);
		} catch (error) {
			// Ignore audio errors; vibration and visual state are still useful.
		}
	}

	function notifyReady(order) {
		if (hasAlertedReady) {
			return;
		}

		hasAlertedReady = true;
		$('.laca-status-app').addClass('is-order-ready');
		$('.laca-status-notify-callout').addClass('is-ready');

		if (navigator.vibrate) {
			navigator.vibrate([220, 90, 220, 90, 320]);
		}

		playReadyTone();

		if (canUseNotification() && Notification.permission === 'granted') {
			new Notification(lacaOrderStatus.i18n.readyTitle, {
				body: (order.queue_number || '') + ' - ' + lacaOrderStatus.i18n.readyBody,
				tag: 'laca-order-' + (order.order_id || order.queue_number || 'ready'),
				renotify: true
			});
		}
	}

	function render(order) {
		var html = [
			'<div class="laca-status-ticket laca-status-' + escapeHtml(order.status) + '">',
				'<span class="laca-payment-kicker">' + escapeHtml(lacaOrderStatus.i18n.queueNumber) + '</span>',
				'<strong class="laca-status-number">' + escapeHtml(order.queue_number) + '</strong>',
				'<p class="laca-status-state">' + escapeHtml(statusLabel(order.status)) + '</p>',
				'<div class="laca-payment-details">',
					'<div><span>Điện thoại</span><strong>' + escapeHtml(order.masked_phone) + '</strong></div>',
					'<div><span>Tổng tiền</span><strong>' + formatMoney(order.total_amount) + '</strong></div>',
					'<div><span>Cập nhật</span><strong>' + escapeHtml(order.updated_at || order.created_at) + '</strong></div>',
				'</div>',
			'</div>'
		].join('');

		$('.laca-status-card').html(html);

		if (hasInitialStatus && previousStatus !== order.status && order.status === 'completed') {
			notifyReady(order);
		}

		previousStatus = order.status;
		hasInitialStatus = true;
	}

	function fetchStatus() {
		var $app = $('.laca-status-app');
		var orderId = $app.data('order-id');
		var token = $app.data('token');

		if (!orderId || !token) {
			$('.laca-status-card').html('<div class="laca-empty-menu">' + escapeHtml(lacaOrderStatus.i18n.notFound) + '</div>');
			return;
		}

		$.ajax({
			url: lacaOrderStatus.statusUrl,
			method: 'GET',
			dataType: 'json',
			data: {
				order_id: orderId,
				token: token
			}
		}).done(render).fail(function () {
			$('.laca-status-card').html('<div class="laca-empty-menu">' + escapeHtml(lacaOrderStatus.i18n.notFound) + '</div>');
		});
	}

	$(function () {
		showNotifyCallout();

		$('.laca-status-enable-notify').on('click', function () {
			var $button = $(this);

			if (!canUseNotification()) {
				$button.text(lacaOrderStatus.i18n.notifyUnsupported).prop('disabled', true);
				return;
			}

			Notification.requestPermission().then(function (permission) {
				if (permission === 'granted') {
					Promise.resolve(subscribeWebPush()).then(function () {
						$button.text(lacaOrderStatus.i18n.notifyEnabled).prop('disabled', true);
					}).catch(function () {
						$button.text(lacaOrderStatus.i18n.notifyEnabled).prop('disabled', true);
					});
				}
			});
		});

		fetchStatus();
		pollTimer = window.setInterval(fetchStatus, Math.max(5000, Number(lacaOrderStatus.refreshIntervalMs || 7000)));

		$(window).on('beforeunload', function () {
			if (pollTimer) {
				window.clearInterval(pollTimer);
			}
		});
	});
})(jQuery);
