(function ($) {
	'use strict';

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function render(response) {
		var orders = response && response.orders ? response.orders : [];

		if (!orders.length) {
			$('.laca-pickup-board').html('<div class="laca-empty-menu">' + escapeHtml(lacaPickupScreen.i18n.empty) + '</div>');
			return;
		}

		$('.laca-pickup-board').html(orders.map(function (order) {
			return [
				'<article class="laca-pickup-card">',
					'<span>Mã nhận món</span>',
					'<strong>' + escapeHtml(order.queue_number) + '</strong>',
					'<em>' + escapeHtml(order.masked_phone) + '</em>',
				'</article>'
			].join('');
		}).join(''));
	}

	function fetchOrders() {
		$.ajax({
			url: lacaPickupScreen.pickupUrl,
			method: 'GET',
			dataType: 'json'
		}).done(render).fail(function () {
			render({ orders: [] });
		});
	}

	$(function () {
		fetchOrders();
		window.setInterval(fetchOrders, 5000);
	});
})(jQuery);
