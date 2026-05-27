self.addEventListener('push', function (event) {
	'use strict';

	var payload = {
		title: 'Món của bạn đã sẵn sàng',
		body: 'Vui lòng đến quầy nhận món nhé!',
		url: '/'
	};

	if (event.data) {
		try {
			payload = Object.assign(payload, event.data.json());
		} catch (error) {
			payload.body = event.data.text();
		}
	}

	event.waitUntil(
		self.registration.showNotification(payload.title, {
			body: payload.body,
			tag: payload.tag || 'laca-order-ready',
			renotify: true,
			requireInteraction: true,
			data: {
				url: payload.url || '/'
			}
		})
	);
});

self.addEventListener('notificationclick', function (event) {
	'use strict';

	var url = event.notification && event.notification.data ? event.notification.data.url : '/';

	event.notification.close();
	event.waitUntil(
		clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
			for (var index = 0; index < clientList.length; index += 1) {
				if (clientList[index].url === url && 'focus' in clientList[index]) {
					return clientList[index].focus();
				}
			}

			if (clients.openWindow) {
				return clients.openWindow(url);
			}

			return null;
		})
	);
});
