(function ($) {
	'use strict';

	var refreshTimer = null;
	var isLoading = false;
	var boardHash = '';
	var recentHash = '';
	var refreshInterval = Math.max(1000, Number(lacaKdsAdmin.refreshIntervalMs || 2000));

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function formatMoney(amount) {
		return Number(amount || 0).toLocaleString('vi-VN') + 'đ';
	}

	function formatOrderNumber(orderId) {
		return '#' + String(orderId || 0).padStart(4, '0');
	}

	function formatStatus(status) {
		var labels = {
			pending: 'Chờ thanh toán',
			paid: 'Đã thanh toán',
			completed: 'Hoàn thành',
			canceled: 'Đã hủy'
		};
		var label = labels[status] || status;
		return '<span class="laca-status-badge laca-status-' + escapeHtml(status) + '">' + label + '</span>';
	}

	function formatStatusHint(order) {
		if (order.status === 'paid') {
			return '<p class="laca-order-status-hint is-paid">Hoàn thành đơn này sẽ gửi SMS và tính vào doanh thu.</p>';
		}

		if (order.status === 'pending') {
			return '<p class="laca-order-status-hint">Hoàn thành khi khách trả tiền mặt: không gửi SMS, không tính doanh thu web.</p>';
		}

		return '';
	}

	function formatExpiry(order) {
		if (order.status !== 'pending' || !order.expires_at) {
			return '';
		}

		var seconds = Math.max(0, Number(order.expires_in || 0));
		var minutes = Math.floor(seconds / 60);
		var remainder = seconds % 60;
		var label = minutes + ':' + String(remainder).padStart(2, '0');

		return '<span>Hạn TT: <strong>' + escapeHtml(label) + '</strong></span>';
	}

	function renderVariantLabels(variants) {
		if (!Array.isArray(variants) || !variants.length) {
			return '';
		}

		return '<small>' + variants.map(function (variant) {
			return escapeHtml(variant.group || '') + ': ' + escapeHtml(variant.option || '');
		}).join(' · ') + '</small>';
	}

	function renderOrderItems(items) {
		if (!Array.isArray(items) || !items.length) {
			return '<li>Không có món</li>';
		}

		return items.map(function (item) {
			if (item.item_type === 'discount') {
				return '<li class="laca-order-item-discount"><span>Giảm: ' + escapeHtml(item.name || '') + '</span> <em>' + formatMoney(item.line_total || 0) + '</em></li>';
			}

			if (item.item_type === 'gift') {
				return '<li class="laca-order-item-gift"><span>Tặng: ' + escapeHtml(item.name || '') + '</span></li>';
			}

			if (item.item_type === 'combo') {
				var comboDetails = item.combo_details ? '<small>' + escapeHtml(item.combo_details) + '</small>' : '';
				return '<li class="laca-order-item-combo"><span>' + escapeHtml(item.name || '') + '</span><i>SL ' + escapeHtml(item.quantity || 0) + '</i>' + comboDetails + '</li>';
			}

			return '<li><span>' + escapeHtml(item.name || '') + '</span><i>SL ' + escapeHtml(item.quantity || 0) + '</i>' + renderVariantLabels(item.variants) + '</li>';
		}).join('');
	}

	function renderSummary(orders) {
		var rows = Array.isArray(orders) ? orders : [];
		var pending = rows.filter(function (order) {
			return order.status === 'pending';
		}).length;
		var paid = rows.filter(function (order) {
			return order.status === 'paid';
		}).length;
		var total = rows.reduce(function (sum, order) {
			return sum + Number(order.total_amount || 0);
		}, 0);

		$('#laca-kds-summary').html([
			'<div><span>Đơn đang mở</span><strong>' + escapeHtml(rows.length) + '</strong></div>',
			'<div><span>Chờ thanh toán</span><strong>' + escapeHtml(pending) + '</strong></div>',
			'<div><span>Đã thanh toán</span><strong>' + escapeHtml(paid) + '</strong></div>',
			'<div><span>Tổng giá trị</span><strong>' + formatMoney(total) + '</strong></div>'
		].join(''));
	}

	function renderOrderBoard(selector, orders, options) {
		var $board = $(selector);
		var emptyText = options && options.emptyText ? options.emptyText : lacaKdsAdmin.i18n.empty;
		var allowUndo = !!(options && options.allowUndo);
		var nextHash = JSON.stringify(orders || []);

		if (selector === '#laca-kds-board' && nextHash === boardHash) {
			return;
		}

		if (selector === '#laca-kds-recent-board' && nextHash === recentHash) {
			return;
		}

		if (selector === '#laca-kds-board') {
			boardHash = nextHash;
		} else if (selector === '#laca-kds-recent-board') {
			recentHash = nextHash;
		}

		if (!orders.length) {
			$board.html('<div class="laca-kds-empty">' + escapeHtml(emptyText) + '</div>');
			return;
		}

		var html = orders.map(function (order) {
			var paidButton = order.status === 'pending' && !allowUndo
				? '<button type="button" class="button laca-kds-action laca-kds-paid" data-status="paid">' + escapeHtml(lacaKdsAdmin.i18n.paid) + '</button>'
				: '';
			var primaryActions = allowUndo
				? '<button type="button" class="button button-primary laca-kds-action" data-status="undo">' + escapeHtml(lacaKdsAdmin.i18n.undo) + '</button>'
				: '<div class="laca-order-actions__main">' +
					'<button type="button" class="button button-primary laca-kds-action" data-status="completed">' + escapeHtml(lacaKdsAdmin.i18n.completed) + '</button>' +
					'<button type="button" class="button laca-kds-action laca-kds-cancel" data-status="canceled">' + escapeHtml(lacaKdsAdmin.i18n.canceled) + '</button>' +
					'</div>' + paidButton;

			return [
				'<article class="laca-order-card laca-order-card--' + escapeHtml(order.status) + '" data-order-id="' + escapeHtml(order.id) + '">',
					'<div class="laca-order-card__top">',
						'<div>',
							'<span class="laca-order-label">Đơn hàng</span>',
							'<h2>' + escapeHtml(formatOrderNumber(order.id)) + '</h2>',
						'</div>',
						formatStatus(order.status),
					'</div>',
					'<div class="laca-order-meta">',
						'<span class="laca-order-phone"><span class="laca-order-phone-icon" aria-hidden="true">☎</span>' + escapeHtml(order.customer_phone || '') + '</span>',
						'<span class="laca-order-created">Tạo lúc: ' + escapeHtml(order.created_at) + '</span>',
						formatExpiry(order),
					'</div>',
					formatStatusHint(order),
					'<ul class="laca-order-items">' + renderOrderItems(order.order_items) + '</ul>',
					'<div class="laca-order-total"><span>Tổng đơn</span><strong>' + formatMoney(order.total_amount) + '</strong></div>',
					'<div class="laca-order-actions">',
						primaryActions,
					'</div>',
				'</article>'
			].join('');
		}).join('');

		$board.html(html);
	}

	function renderOrders(orders, recentOrders) {
		renderSummary(orders || []);
		renderOrderBoard('#laca-kds-board', orders || [], {
			emptyText: lacaKdsAdmin.i18n.empty
		});
		renderOrderBoard('#laca-kds-recent-board', recentOrders || [], {
			emptyText: lacaKdsAdmin.i18n.emptyRecent,
			allowUndo: true
		});
	}

	function updateLastRefresh() {
		var time = new Date().toLocaleTimeString('vi-VN');
		$('#laca-kds-last-updated').text(lacaKdsAdmin.i18n.lastUpdated + ': ' + time);
	}

	function fetchOrders() {
		if (isLoading) {
			return;
		}

		isLoading = true;

		$.ajax({
			url: lacaKdsAdmin.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'laca_kds_get_orders',
				nonce: lacaKdsAdmin.nonce
			}
		}).done(function (response) {
			if (response && response.success) {
				renderOrders(response.data.orders || [], response.data.recent_orders || []);
				updateLastRefresh();
				return;
			}

			$('#laca-kds-board').html('<div class="laca-kds-error">' + escapeHtml(lacaKdsAdmin.i18n.error) + '</div>');
		}).fail(function () {
			$('#laca-kds-board').html('<div class="laca-kds-error">' + escapeHtml(lacaKdsAdmin.i18n.error) + '</div>');
		}).always(function () {
			isLoading = false;
		});
	}

	function updateOrderStatus($button) {
		var $card = $button.closest('.laca-order-card');
		var status = $button.data('status');
		var orderId = $card.data('order-id');

		if (status === 'canceled' && !window.confirm(lacaKdsAdmin.i18n.confirmCancel)) {
			return;
		}

		if (status === 'undo' && !window.confirm(lacaKdsAdmin.i18n.confirmUndo)) {
			return;
		}

		$card.addClass('is-updating');
		$card.find('button').prop('disabled', true);

		$.ajax({
			url: lacaKdsAdmin.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'laca_kds_update_status',
				nonce: lacaKdsAdmin.nonce,
				order_id: orderId,
				status: status
			}
		}).done(function (response) {
			if (response && response.success) {
				if (status === 'paid') {
					$card.find('.laca-status-badge').replaceWith(formatStatus('paid'));
					$button.remove();
					$card.removeClass('is-updating');
					$card.find('button').prop('disabled', false);
				} else {
					$card.fadeOut(120, function () {
						$(this).remove();
					});
				}
				window.setTimeout(fetchOrders, 180);
				return;
			}

			$card.removeClass('is-updating');
			$card.find('button').prop('disabled', false);
		}).fail(function () {
			$card.removeClass('is-updating');
			$card.find('button').prop('disabled', false);
		});
	}

	function renderFoodItems(items) {
		var $board = $('#laca-food-board');

		if (!$board.length) {
			return;
		}

		if (!items.length) {
			$board.html('<div class="laca-kds-empty">' + escapeHtml(lacaKdsAdmin.i18n.empty) + '</div>');
			return;
		}

		$board.html(items.map(function (item) {
			var statusClass = item.is_available ? 'is-available' : 'is-sold-out';
			var label = item.is_available ? lacaKdsAdmin.i18n.available : lacaKdsAdmin.i18n.soldOut;
			var nextValue = item.is_available ? 0 : 1;

			return [
				'<article class="laca-food-admin-card ' + statusClass + '" data-food-id="' + escapeHtml(item.id) + '">',
					'<div>',
						'<span class="laca-order-label">' + escapeHtml(label) + '</span>',
						'<h2>' + escapeHtml(item.name) + '</h2>',
						'<p>' + formatMoney(item.price) + '</p>',
					'</div>',
					'<div class="laca-order-actions">',
						'<button type="button" class="button button-primary laca-food-toggle" data-next="' + nextValue + '">' + escapeHtml(item.is_available ? lacaKdsAdmin.i18n.soldOut : lacaKdsAdmin.i18n.available) + '</button>',
						'<a class="button" href="' + escapeHtml(item.edit_url) + '">Sửa</a>',
					'</div>',
				'</article>'
			].join('');
		}).join(''));
	}

	function fetchFoodItems() {
		if (!$('#laca-food-board').length) {
			return;
		}

		$.ajax({
			url: lacaKdsAdmin.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'laca_kds_get_food_items',
				nonce: lacaKdsAdmin.nonce
			}
		}).done(function (response) {
			renderFoodItems(response && response.success ? response.data.items || [] : []);
		}).fail(function () {
			$('#laca-food-board').html('<div class="laca-kds-error">' + escapeHtml(lacaKdsAdmin.i18n.error) + '</div>');
		});
	}

	function toggleFoodAvailability($button) {
		var $card = $button.closest('.laca-food-admin-card');

		$card.addClass('is-updating');
		$button.prop('disabled', true);

		$.ajax({
			url: lacaKdsAdmin.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'laca_kds_toggle_food_availability',
				nonce: lacaKdsAdmin.nonce,
				food_id: $card.data('food-id'),
				is_available: $button.data('next')
			}
		}).always(fetchFoodItems);
	}

	$(function () {
		if ($('#laca-kds-board').length) {
			fetchOrders();
			refreshTimer = window.setInterval(fetchOrders, refreshInterval);
		}

		fetchFoodItems();

		$('#laca-kds-refresh').on('click', fetchOrders);
		$('#laca-food-refresh').on('click', fetchFoodItems);
		$('#laca-kds-board, #laca-kds-recent-board').on('click', '.laca-kds-action', function () {
			updateOrderStatus($(this));
		});
		$('#laca-food-board').on('click', '.laca-food-toggle', function () {
			toggleFoodAvailability($(this));
		});

		$(window).on('beforeunload', function () {
			if (refreshTimer) {
				window.clearInterval(refreshTimer);
			}
		});
	});
})(jQuery);
