(function ($) {
	'use strict';

	var cart = {};
	var activeCategory = 'all';
	var searchTimer = null;
	var currentMenuRequest = null;
	var paymentTimer = null;
	var statusPollTimer = null;
	var activeOrder = null;
	var selectedGiftChoices = {};

	function escapeHtml(value) {
		if (value === null || typeof value === 'undefined') {
			return '';
		}

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

	function getCartItems() {
		return Object.keys(cart).map(function (id) {
			return cart[id];
		});
	}

	function getCartSubtotal() {
		return getCartItems().reduce(function (total, item) {
			return total + (item.price * item.quantity);
		}, 0);
	}

	function getCartCount() {
		return getCartItems().reduce(function (total, item) {
			return total + item.quantity;
		}, 0);
	}

	function calculateDiscountAmount(subtotal, type, value) {
		var safeValue = Math.max(0, Number(value || 0));

		if (type === 'percent') {
			return Math.min(subtotal, subtotal * Math.min(100, safeValue) / 100);
		}

		return Math.min(subtotal, safeValue);
	}

	function getCartPricing() {
		var promotions = lacaMenuApp.promotions || {};
		var rules = Array.isArray(promotions.rules) ? promotions.rules : [];
		var subtotal = getCartSubtotal();
		var count = getCartCount();
		var discounts = [];
		var totalDiscount = 0;
		var gifts = [];

		rules.forEach(function (rule, ruleIndex) {
			var triggerType = rule.trigger_type || 'quantity';
			var rewardType = rule.reward_type || 'gift';
			var matches = false;

			if (!Number(rule.enabled || 0)) {
				return;
			}

			if (triggerType === 'total') {
				matches = Number(rule.min_total || 0) > 0 && subtotal >= Number(rule.min_total || 0);
			} else {
				matches = Number(rule.min_qty || 0) > 0 && count >= Number(rule.min_qty || 0);
			}

			if (!matches) {
				return;
			}

			if (rewardType === 'gift') {
				var options = Array.isArray(rule.gift_options) ? rule.gift_options : [];

				if (rule.gift_text || options.length) {
					gifts.push({
						rule_index: typeof rule.rule_index !== 'undefined' ? Number(rule.rule_index) : ruleIndex,
						label: rule.label || 'Tặng kèm',
						text: rule.gift_text || '',
						options: options
					});
				}
				return;
			}

			discounts.push({
				label: rule.label || 'Ưu đãi tự động',
				amount: calculateDiscountAmount(subtotal, rewardType === 'discount_percent' ? 'percent' : 'fixed', rule.discount_value)
			});
		});

		discounts = discounts.filter(function (discount) {
			return Number(discount.amount || 0) > 0;
		});

		totalDiscount = discounts.reduce(function (total, discount) {
			return total + Number(discount.amount || 0);
		}, 0);
		totalDiscount = Math.min(subtotal, totalDiscount);
		discounts = discounts.reduce(function (applied, discount) {
			var used = applied.reduce(function (total, row) {
				return total + Number(row.amount || 0);
			}, 0);
			var remaining = Math.max(0, subtotal - used);
			var amount = Math.min(remaining, Number(discount.amount || 0));

			if (amount > 0) {
				applied.push({
					label: discount.label,
					amount: amount
				});
			}

			return applied;
		}, []);

		return {
			subtotal: subtotal,
			discounts: discounts,
			total: Math.max(0, subtotal - totalDiscount),
			gifts: gifts
		};
	}

	function getCardVariants($card) {
		var raw = $card.attr('data-variants') || '[]';

		try {
			var parsed = JSON.parse(raw);
			return Array.isArray(parsed) ? parsed : [];
		} catch (error) {
			return [];
		}
	}

	function getSelectedVariantData($card) {
		var groups = getCardVariants($card);
		var selected = [];
		var labels = [];
		var priceDelta = 0;

		groups.forEach(function (group, groupIndex) {
			var $select = $card.find('.laca-variant-select[data-group-index="' + groupIndex + '"]');
			var optionIndex = Number($select.length ? $select.val() : 0);
			var options = Array.isArray(group.options) ? group.options : [];

			if (!options[optionIndex]) {
				optionIndex = 0;
			}

			if (!options[optionIndex]) {
				return;
			}

			selected[groupIndex] = optionIndex;
			labels.push({
				group: group.name || '',
				option: options[optionIndex].name || '',
				price_delta: Number(options[optionIndex].price_delta || 0)
			});
			priceDelta += Number(options[optionIndex].price_delta || 0);
		});

		return {
			selected: selected,
			labels: labels,
			priceDelta: priceDelta
		};
	}

	function renderVariantLabels(labels) {
		if (!Array.isArray(labels) || !labels.length) {
			return '';
		}

		return '<small class="laca-cart-row__variants">' + labels.map(function (variant) {
			return escapeHtml(variant.group) + ': ' + escapeHtml(variant.option);
		}).join(' · ') + '</small>';
	}

	function getCardItemData($card) {
		var variants = getSelectedVariantData($card);
		var basePrice = Number($card.data('food-price'));

		return {
			item_type: String($card.data('item-type') || 'food'),
			item_id: Number($card.data('item-id') || $card.data('food-id')),
			food_id: Number($card.data('food-id')),
			combo_id: Number($card.data('combo-id') || 0),
			name: String($card.data('food-name')),
			base_price: basePrice,
			price: Math.max(0, basePrice + variants.priceDelta),
			regular_price: Number($card.data('regular-price') || $card.data('food-price')),
			variants: variants.selected,
			variant_labels: variants.labels
		};
	}

	function getVariantKey(variants) {
		if (!Array.isArray(variants) || !variants.length) {
			return '';
		}

		return variants.map(function (value, index) {
			return index + '=' + Number(value || 0);
		}).join('|');
	}

	function getCartKey(itemData) {
		var variantKey = itemData.item_type === 'food' ? getVariantKey(itemData.variants) : '';

		return String(itemData.item_type || 'food') + ':' + String(itemData.item_id || itemData.food_id || itemData.combo_id || 0) + (variantKey ? ':' + variantKey : '');
	}

	function setCartQuantity(itemData, quantity) {
		var cartKey = getCartKey(itemData);
		var safeQuantity = Math.max(0, Math.min(99, Number(quantity || 0)));

		if (!safeQuantity) {
			delete cart[cartKey];
		} else {
			cart[cartKey] = {
				cart_key: cartKey,
				item_type: String(itemData.item_type || 'food'),
				item_id: Number(itemData.item_id || itemData.food_id || itemData.combo_id),
				food_id: Number(itemData.food_id),
				combo_id: Number(itemData.combo_id || 0),
				name: itemData.name,
				base_price: Number(itemData.base_price || itemData.price),
				price: Number(itemData.price),
				regular_price: Number(itemData.regular_price || itemData.price),
				variants: Array.isArray(itemData.variants) ? itemData.variants : [],
				variant_labels: Array.isArray(itemData.variant_labels) ? itemData.variant_labels : [],
				quantity: safeQuantity
			};
		}

		syncQuantityDisplays();
		renderCart();
	}

	function changeCartQuantity(itemData, delta) {
		var cartKey = getCartKey(itemData);
		var currentQuantity = cart[cartKey] ? Number(cart[cartKey].quantity || 0) : 0;

		setCartQuantity(itemData, currentQuantity + Number(delta || 0));
	}

	function syncQuantityDisplays() {
		$('.laca-food-card').each(function () {
			var $card = $(this);
			var itemData = getCardItemData($card);
			var cartKey = getCartKey(itemData);
			var quantity = cart[cartKey] ? cart[cartKey].quantity : 0;

			$card.toggleClass('is-in-cart', quantity > 0);
			$card.find('.laca-card-quantity').text(quantity);
			$card.find('.laca-quantity-minus').prop('disabled', quantity <= 0);
		});
	}

	function renderCart() {
		var items = getCartItems();
		var count = getCartCount();
		var $cart = $('.laca-floating-cart');

		if (!count) {
			$cart.prop('hidden', true);
			return;
		}

		$('.laca-cart-count').text(count + ' món');
		$('.laca-cart-items').html(items.map(function (item) {
			return [
				'<div class="laca-cart-row">',
					'<span class="laca-cart-row__name">' + escapeHtml(item.name) + '</span>',
					renderVariantLabels(item.variant_labels),
					'<div class="laca-cart-row__controls">',
						'<button type="button" class="laca-cart-qty laca-cart-minus" data-cart-key="' + escapeHtml(item.cart_key) + '" data-direction="-1" aria-label="Bớt ' + escapeHtml(item.name) + '">-</button>',
						'<span class="laca-cart-row__qty">x' + escapeHtml(item.quantity) + '</span>',
						'<button type="button" class="laca-cart-qty laca-cart-plus" data-cart-key="' + escapeHtml(item.cart_key) + '" data-direction="1" aria-label="Thêm ' + escapeHtml(item.name) + '">+</button>',
					'</div>',
				'</div>'
			].join('');
		}).join(''));
		$('.laca-cart-total').html(renderPricingSummary(getCartPricing(), true));
		$cart.prop('hidden', false);
	}

	function showStep(step) {
		$('[data-laca-step]').prop('hidden', true);
		$('[data-laca-step="' + step + '"]').prop('hidden', false);
		$('.laca-floating-cart').prop('hidden', step !== 'menu' || !getCartCount());
	}

	function showMessage(message, isError) {
		$('.laca-form-message')
			.toggleClass('is-error', !!isError)
			.text(message || '');
	}

	function setMenuStatus(message) {
		$('.laca-menu-results-status').text(message || '');
	}

	function buildOrderPayload(phone) {
		var pricing = getCartPricing();

		return {
			customer_phone: phone,
			items: getCartItems().map(function (item) {
				return {
					item_type: item.item_type,
					item_id: item.item_id,
					food_id: item.food_id,
					combo_id: item.combo_id,
					variants: item.variants,
					quantity: item.quantity
				};
			}),
			selected_gifts: pricing.gifts.map(function (gift) {
				var selectedFoodId = selectedGiftChoices[gift.rule_index];
				var options = Array.isArray(gift.options) ? gift.options : [];

				if (!selectedFoodId && options.length) {
					selectedFoodId = Number(options[0].id || 0);
				}

				return {
					rule_index: gift.rule_index,
					food_id: Number(selectedFoodId || 0)
				};
			}).filter(function (gift) {
				return gift.food_id > 0;
			})
		};
	}

	function renderTerms(categories) {
		if (!Array.isArray(categories) || !categories.length) {
			return '';
		}

		return '<div class="laca-food-card__terms">' + categories.map(function (category) {
			return '<span>' + escapeHtml(category.name) + '</span>';
		}).join('') + '</div>';
	}

	function renderComboMeta(item) {
		var html = '';

		if (item.is_combo) {
			html += '<span class="laca-combo-badge">' + escapeHtml(item.combo_badge || 'Combo') + '</span>';
		}

		if (item.is_combo && item.combo_details) {
			html += '<p class="laca-combo-details">' + escapeHtml(item.combo_details) + '</p>';
		}

		return html;
	}

	function renderVariantPicker(item) {
		if (item.is_combo || !Array.isArray(item.variants) || !item.variants.length) {
			return '';
		}

		return '<div class="laca-variant-picker">' + item.variants.map(function (group, groupIndex) {
			var options = Array.isArray(group.options) ? group.options : [];

			return '<label><span>' + escapeHtml(group.name || '') + '</span><select class="laca-variant-select" data-group-index="' + escapeHtml(groupIndex) + '">' + options.map(function (option, optionIndex) {
				var priceDelta = Number(option.price_delta || 0);
				var suffix = priceDelta > 0 ? ' +' + formatMoney(priceDelta) : '';

				return '<option value="' + escapeHtml(optionIndex) + '" data-price-delta="' + escapeHtml(priceDelta) + '">' + escapeHtml(option.name || '') + escapeHtml(suffix) + '</option>';
			}).join('') + '</select></label>';
		}).join('') + '</div>';
	}

	function updateCardPrice($card) {
		var itemData = getCardItemData($card);

		if ($card.data('item-type') === 'combo') {
			return;
		}

		$card.find('.laca-food-card__price strong').text(formatMoney(itemData.price));
	}

	function renderFoodCard(item) {
		var imageHtml = item.thumbnail_url
			? '<img src="' + escapeHtml(item.thumbnail_url) + '" alt="' + escapeHtml(item.name) + '" loading="lazy" />'
			: '<span>La Cà</span>';
		var priceHtml = item.is_combo && Number(item.regular_price || 0) > Number(item.price || 0)
			? '<p class="laca-food-card__price"><del>' + formatMoney(item.regular_price) + '</del><strong>' + formatMoney(item.price) + '</strong></p>'
			: '<p class="laca-food-card__price"><strong>' + formatMoney(item.price) + '</strong></p>';

		return [
			'<article class="laca-food-card" data-item-id="' + escapeHtml(item.item_id || item.id) + '" data-item-type="' + escapeHtml(item.item_type || 'food') + '" data-food-id="' + escapeHtml(item.food_id || 0) + '" data-combo-id="' + escapeHtml(item.combo_id || 0) + '" data-food-name="' + escapeHtml(item.name) + '" data-food-price="' + escapeHtml(item.price) + '" data-regular-price="' + escapeHtml(item.regular_price || item.price) + '" data-variants="' + escapeHtml(JSON.stringify(item.variants || [])) + '">',
				'<div class="laca-food-card__image">' + imageHtml + '</div>',
				'<div class="laca-food-card__body">',
					renderComboMeta(item),
					'<h3>' + escapeHtml(item.name) + '</h3>',
					priceHtml,
					renderTerms(item.categories),
					renderVariantPicker(item),
					'<div class="laca-food-card__actions">',
						'<div class="laca-quantity-control" data-cart-key="' + escapeHtml((item.item_type || 'food') + ':' + (item.item_id || item.id)) + '">',
							'<button type="button" class="laca-quantity-btn laca-quantity-minus" data-direction="-1" aria-label="Giảm số lượng">-</button>',
							'<strong class="laca-card-quantity">0</strong>',
							'<button type="button" class="laca-quantity-btn laca-quantity-plus" data-direction="1" aria-label="Tăng số lượng">+</button>',
						'</div>',
						'<button type="button" class="laca-add-to-cart">',
							'<span aria-hidden="true">+</span>',
							escapeHtml(lacaMenuApp.i18n.addToCart),
						'</button>',
					'</div>',
				'</div>',
			'</article>'
		].join('');
	}

	function renderFoodItems(items) {
		var $grid = $('.laca-food-grid');

		if (!$grid.length) {
			$('.laca-menu-toolbar').after('<div class="laca-food-grid"></div>');
			$grid = $('.laca-food-grid');
		}

		$('.laca-empty-menu').remove();

		if (!Array.isArray(items) || !items.length) {
			$grid.html('');
			$grid.after('<div class="laca-empty-menu laca-empty-menu--small">' + escapeHtml(lacaMenuApp.i18n.noResults) + '</div>');
			return;
		}

		$grid.html(items.map(renderFoodCard).join(''));
		syncQuantityDisplays();
	}

	function fetchMenuItems() {
		var search = $.trim($('.laca-food-search').val());

		setMenuStatus(lacaMenuApp.i18n.searching);

		if (currentMenuRequest) {
			currentMenuRequest.abort();
		}

		currentMenuRequest = $.ajax({
			url: lacaMenuApp.menuItemsUrl,
			method: 'GET',
			dataType: 'json',
			data: {
				search: search,
				category: activeCategory
			}
		}).done(function (response) {
			var items = response && response.items ? response.items : [];

			renderFoodItems(items);
			setMenuStatus(items.length ? items.length + ' ' + lacaMenuApp.i18n.resultCount : lacaMenuApp.i18n.noResults);
		}).fail(function (xhr) {
			if (xhr.statusText === 'abort') {
				return;
			}

			setMenuStatus(lacaMenuApp.i18n.genericError);
		}).always(function () {
			currentMenuRequest = null;
		});
	}

	function scheduleMenuFetch() {
		window.clearTimeout(searchTimer);
		searchTimer = window.setTimeout(fetchMenuItems, 260);
	}

	function renderCheckoutSummary() {
		var items = getCartItems();
		var rows = items.map(function (item) {
			return '<div><span>' + escapeHtml(item.name) + ' x' + escapeHtml(item.quantity) + renderVariantLabels(item.variant_labels) + '</span><strong>' + formatMoney(item.price * item.quantity) + '</strong></div>';
		}).join('');

		$('.laca-checkout-summary').html(rows + renderPricingSummary(getCartPricing(), false));
	}

	function renderPricingSummary(pricing, compact) {
		var rows = '';

		if (!compact) {
			rows += '<div><span>Tạm tính</span><strong>' + formatMoney(pricing.subtotal) + '</strong></div>';
		}

		if (Array.isArray(pricing.discounts)) {
			rows += pricing.discounts.map(function (discount) {
				return '<div class="laca-discount-row"><span>' + escapeHtml(discount.label) + '</span><strong>-' + formatMoney(discount.amount) + '</strong></div>';
			}).join('');
		}

		if (Array.isArray(pricing.gifts)) {
			rows += pricing.gifts.map(function (gift) {
				var options = Array.isArray(gift.options) ? gift.options : [];
				var selectedFoodId = Number(selectedGiftChoices[gift.rule_index] || (options[0] ? options[0].id : 0));
				var choice = '';

				if (options.length) {
					choice = '<select class="laca-gift-select" data-rule-index="' + escapeHtml(gift.rule_index) + '">' + options.map(function (option) {
						return '<option value="' + escapeHtml(option.id) + '"' + (Number(option.id) === selectedFoodId ? ' selected' : '') + '>' + escapeHtml(option.name) + '</option>';
					}).join('') + '</select>';
				} else {
					choice = '<strong>' + escapeHtml(gift.text || gift.label) + '</strong>';
				}

				return '<div class="laca-gift-row"><span>' + escapeHtml(gift.label || 'Tặng kèm') + '</span>' + choice + '</div>';
			}).join('');
		}

		rows += '<div class="laca-summary-total"><span>' + escapeHtml(lacaMenuApp.i18n.cartTotal) + '</span><strong>' + formatMoney(pricing.total) + '</strong></div>';

		return rows;
	}

	function formatSeconds(seconds) {
		var safeSeconds = Math.max(0, Number(seconds || 0));
		var minutes = Math.floor(safeSeconds / 60);
		var remainder = safeSeconds % 60;

		return minutes + ':' + String(remainder).padStart(2, '0');
	}

	function clearPaymentTimers() {
		if (paymentTimer) {
			window.clearInterval(paymentTimer);
			paymentTimer = null;
		}

		if (statusPollTimer) {
			window.clearInterval(statusPollTimer);
			statusPollTimer = null;
		}
	}

	function setPaymentState(message, state) {
		$('.laca-payment-countdown')
			.prop('hidden', false)
			.removeClass('is-expired is-paid')
			.addClass(state ? 'is-' + state : '')
			.html(message);
	}

	function startPaymentCountdown(order) {
		var remaining = Math.max(0, Number(order.expires_in || 0));

		if (!remaining) {
			$('.laca-payment-countdown').prop('hidden', true);
			return;
		}

		setPaymentState(escapeHtml(lacaMenuApp.i18n.paymentCountdown) + ' <strong>' + formatSeconds(remaining) + '</strong>', '');

		paymentTimer = window.setInterval(function () {
			remaining -= 1;

			if (remaining <= 0) {
				window.clearInterval(paymentTimer);
				paymentTimer = null;
				setPaymentState(escapeHtml(lacaMenuApp.i18n.paymentExpired), 'expired');
				return;
			}

			setPaymentState(escapeHtml(lacaMenuApp.i18n.paymentCountdown) + ' <strong>' + formatSeconds(remaining) + '</strong>', '');
		}, 1000);
	}

	function pollOrderStatus() {
		if (!activeOrder || !activeOrder.status_token) {
			return;
		}

		$.ajax({
			url: lacaMenuApp.statusUrl,
			method: 'GET',
			dataType: 'json',
			data: {
				order_id: activeOrder.order_id,
				token: activeOrder.status_token
			}
		}).done(function (status) {
			if (!status || !status.status) {
				return;
			}

			if (status.status === 'paid' || status.status === 'completed') {
				clearPaymentTimers();
				setPaymentState(escapeHtml(lacaMenuApp.i18n.paymentConfirmed), 'paid');
			}

			if (status.status === 'canceled') {
				clearPaymentTimers();
				setPaymentState(escapeHtml(lacaMenuApp.i18n.paymentExpired), 'expired');
			}
		});
	}

	function startStatusPolling(order) {
		if (!order.status_token) {
			return;
		}

		statusPollTimer = window.setInterval(pollOrderStatus, 3000);
	}

	function renderPayment(order) {
		clearPaymentTimers();
		activeOrder = order;

		var details = [
			'<div><span>' + escapeHtml(lacaMenuApp.i18n.orderId) + '</span><strong>#' + escapeHtml(order.order_id) + '</strong></div>',
			'<div><span>' + escapeHtml(lacaMenuApp.i18n.amount) + '</span><strong>' + formatMoney(order.total_amount) + '</strong></div>',
			'<div><span>' + escapeHtml(lacaMenuApp.i18n.transferContent) + '</span><strong>' + escapeHtml(order.transfer_content) + '</strong></div>',
			'<div><span>' + escapeHtml(lacaMenuApp.i18n.bank) + '</span><strong>' + escapeHtml(order.bank.bank_bin) + '</strong></div>',
			'<div><span>' + escapeHtml(lacaMenuApp.i18n.account) + '</span><strong>' + escapeHtml(order.bank.account_number) + ' - ' + escapeHtml(order.bank.account_name) + '</strong></div>'
		].join('');

		$('.laca-qr-image').attr('src', order.qr_url);
		$('.laca-payment-details').html(details);
		$('.laca-payment-actions').html([
			'<a class="laca-payment-action" href="' + escapeHtml(order.qr_url) + '" target="_blank" rel="noopener">' + escapeHtml(lacaMenuApp.i18n.openQr) + '</a>',
			'<button type="button" class="laca-payment-action laca-copy-transfer" data-transfer-content="' + escapeHtml(order.transfer_content) + '">' + escapeHtml(lacaMenuApp.i18n.copyTransfer) + '</button>'
		].join(''));
		if (order.status_url) {
			$('.laca-status-link-wrap').html('<a class="laca-status-link" href="' + escapeHtml(order.status_url) + '">' + escapeHtml(lacaMenuApp.i18n.trackOrder) + '</a>');
		}
		startPaymentCountdown(order);
		startStatusPolling(order);
		showStep('payment');
	}

	$(function () {
		var initialCount = $('.laca-food-card').length;
		if (initialCount) {
			setMenuStatus(initialCount + ' ' + lacaMenuApp.i18n.resultCount);
		}
		syncQuantityDisplays();

		$('.laca-menu-app').on('click', '.laca-category-chip', function () {
			activeCategory = String($(this).data('category') || 'all');
			$('.laca-category-chip').removeClass('is-active');
			$(this).addClass('is-active');
			fetchMenuItems();
		});

		$('.laca-menu-app').on('input', '.laca-food-search', scheduleMenuFetch);

		$('.laca-menu-app').on('change', '.laca-gift-select', function () {
			selectedGiftChoices[$(this).data('rule-index')] = Number($(this).val() || 0);
			renderCart();
			if (!$('[data-laca-step="checkout"]').prop('hidden')) {
				renderCheckoutSummary();
			}
		});

		$('.laca-menu-app').on('change', '.laca-variant-select', function () {
			var $card = $(this).closest('.laca-food-card');

			updateCardPrice($card);
			syncQuantityDisplays();
		});

		$('.laca-menu-app').on('click', '.laca-add-to-cart', function () {
			var $card = $(this).closest('.laca-food-card');

			changeCartQuantity(getCardItemData($card), 1);
		});

		$('.laca-menu-app').on('click', '.laca-quantity-btn', function () {
			var $card = $(this).closest('.laca-food-card');
			var delta = Number($(this).data('direction') || 0);

			changeCartQuantity(getCardItemData($card), delta);
		});

		$('.laca-floating-cart').on('click', '.laca-cart-qty', function () {
			var cartKey = String($(this).data('cart-key'));
			var delta = Number($(this).data('direction') || 0);

			if (!cart[cartKey]) {
				return;
			}

			changeCartQuantity(cart[cartKey], delta);
		});

		$('.laca-checkout-button').on('click', function () {
			if (!getCartItems().length) {
				window.alert(lacaMenuApp.i18n.emptyCart);
				return;
			}

			renderCheckoutSummary();
			showMessage('');
			showStep('checkout');
			window.scrollTo({ top: $('.laca-menu-app').offset().top, behavior: 'smooth' });
		});

		$('.laca-back-to-menu').on('click', function () {
			showStep('menu');
			renderCart();
		});

		$('.laca-menu-app').on('click', '.laca-copy-transfer', function () {
			var $button = $(this);
			var value = String($button.data('transfer-content') || '');
			var done = function () {
				$button.text(lacaMenuApp.i18n.copied);
				window.setTimeout(function () {
					$button.text(lacaMenuApp.i18n.copyTransfer);
				}, 1400);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(value).then(done);
				return;
			}

			window.prompt(lacaMenuApp.i18n.copyTransfer, value);
			done();
		});

		$('.laca-checkout-form').on('submit', function (event) {
			event.preventDefault();

			var $form = $(this);
			var phone = $.trim($form.find('[name="customer_phone"]').val());

			if (!phone || phone.length < 8) {
				showMessage(lacaMenuApp.i18n.invalidPhone, true);
				return;
			}

			if (!getCartItems().length) {
				showMessage(lacaMenuApp.i18n.emptyCart, true);
				return;
			}

			showMessage(lacaMenuApp.i18n.creatingOrder, false);
			$form.find('button').prop('disabled', true);

			$.ajax({
				url: lacaMenuApp.restUrl,
				method: 'POST',
				contentType: 'application/json',
				dataType: 'json',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-Laca-Nonce', lacaMenuApp.nonce);
				},
				data: JSON.stringify(buildOrderPayload(phone))
			}).done(function (order) {
				renderPayment(order);
				cart = {};
				syncQuantityDisplays();
				renderCart();
			}).fail(function (xhr) {
				var message = lacaMenuApp.i18n.genericError;

				if (xhr.responseJSON && xhr.responseJSON.message) {
					message = xhr.responseJSON.message;
				}

				showMessage(message, true);
			}).always(function () {
				$form.find('button').prop('disabled', false);
			});
		});
	});
})(jQuery);
