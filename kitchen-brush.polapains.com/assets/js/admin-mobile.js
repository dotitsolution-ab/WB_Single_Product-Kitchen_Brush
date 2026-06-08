(function () {
    var bootNode = document.getElementById('admin-mobile-data');
    if (!bootNode) {
        return;
    }

    var boot = JSON.parse(bootNode.textContent || '{}');
    var state = boot.state || {};
    var statusOptions = boot.statusOptions || [];
    var selectedOrderId = Number(boot.selectedOrderId || 0);
    var activeStatus = '';
    var activeQuery = '';
    var pollTimer = null;
    var installPromptEvent = null;
    var isBusy = false;

    var root = document.querySelector('[data-admin-mobile-app]');
    var orderList = document.querySelector('[data-order-list]');
    var detailPanel = document.querySelector('[data-detail-panel]');
    var detailBackdrop = document.querySelector('[data-detail-backdrop]');
    var detailContent = document.querySelector('[data-detail-content]');
    var toastNode = document.querySelector('[data-mobile-toast]');
    var searchInput = document.querySelector('[data-order-search]');
    var lastSyncNode = document.querySelector('[data-last-sync]');
    var notificationButton = document.querySelector('[data-notification-toggle]');
    var installButton = document.querySelector('[data-install-app]');
    var connectionState = document.querySelector('[data-connection-state]');
    var lastSeenKey = 'adminMobileLastSeenOrderId';
    var storedLastSeen = Number(window.localStorage.getItem(lastSeenKey) || 0);
    var latestOrderId = Number(state.latest_order_id || 0);
    var lastSeenOrderId = storedLastSeen > 0 ? storedLastSeen : latestOrderId;

    if (!storedLastSeen && latestOrderId > 0) {
        window.localStorage.setItem(lastSeenKey, String(latestOrderId));
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function setText(selector, value) {
        var node = document.querySelector(selector);
        if (node) {
            node.textContent = String(value);
        }
    }

    function showToast(message, tone) {
        if (!toastNode) {
            return;
        }

        toastNode.textContent = message;
        toastNode.hidden = false;
        toastNode.classList.toggle('is-error', tone === 'error');
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(function () {
            toastNode.hidden = true;
        }, 3200);
    }

    function updateConnection(online) {
        if (!connectionState) {
            return;
        }

        connectionState.textContent = online ? 'Live' : 'Offline';
        connectionState.classList.toggle('is-offline', !online);
    }

    function updateNotificationButton() {
        if (!notificationButton) {
            return;
        }

        if (!('Notification' in window)) {
            notificationButton.textContent = 'No Notify';
            notificationButton.disabled = true;
            return;
        }

        if (Notification.permission === 'granted') {
            notificationButton.textContent = 'Notify On';
        } else if (Notification.permission === 'denied') {
            notificationButton.textContent = 'Notify Off';
        } else {
            notificationButton.textContent = 'Notify';
        }
    }

    function updateStats() {
        var stats = state.stats || {};
        setText('[data-pending-total]', stats.pending || 0);
        setText('[data-orders-today]', stats.orders_today || 0);
        setText('[data-sales-total]', stats.sales_total_formatted || 'BDT 0');

        var counts = state.status_counts || {};
        var total = 0;
        statusOptions.forEach(function (status) {
            total += Number(counts[status] || 0);
            setText('[data-status-count="' + status + '"]', counts[status] || 0);
        });
        setText('[data-status-count="All"]', total);
    }

    function statusClass(status) {
        return 'mobile-status-' + String(status || 'Pending').toLowerCase().replace(/[^a-z0-9]+/g, '-');
    }

    function nextStatus(order) {
        var map = {
            Pending: 'Confirmed',
            Confirmed: 'Processing',
            Processing: 'Shipped',
            Shipped: 'Delivered'
        };

        return map[order.status] || '';
    }

    function renderOrderCard(order) {
        var next = nextStatus(order);
        var quickAction = next
            ? '<button class="mobile-mini-action" type="button" data-status-update="' + escapeHtml(next) + '" data-order-id="' + order.id + '">' + escapeHtml(next) + '</button>'
            : '';

        return [
            '<article class="mobile-order-card" data-card-order-id="' + order.id + '">',
            '<button class="mobile-order-open" type="button" data-open-order="' + order.id + '">',
            '<span class="mobile-card-top"><strong>' + escapeHtml(order.order_number) + '</strong><em class="' + statusClass(order.status) + '">' + escapeHtml(order.status) + '</em></span>',
            '<span class="mobile-card-customer">' + escapeHtml(order.customer_name) + '</span>',
            '<span class="mobile-card-meta"><span>' + escapeHtml(order.customer_phone) + '</span><span>' + escapeHtml(order.age_label || order.created_label) + '</span></span>',
            '<span class="mobile-card-bottom"><strong>' + escapeHtml(order.total_formatted) + '</strong><span>' + escapeHtml(order.district_area) + '</span></span>',
            '</button>',
            '<div class="mobile-card-actions">',
            '<a href="tel:' + escapeHtml(order.customer_phone) + '">Call</a>',
            quickAction,
            '</div>',
            '</article>'
        ].join('');
    }

    function renderOrderList() {
        if (!orderList) {
            return;
        }

        var orders = state.orders || [];
        if (!orders.length) {
            orderList.innerHTML = '<div class="mobile-empty-state"><strong>No orders found</strong><span>Try another status or search.</span></div>';
            return;
        }

        orderList.innerHTML = orders.map(renderOrderCard).join('');
    }

    function renderStatusButtons(order) {
        return statusOptions.map(function (status) {
            var active = order.status === status ? ' is-active' : '';
            return '<button class="mobile-status-action' + active + '" type="button" data-status-update="' + escapeHtml(status) + '" data-order-id="' + order.id + '">' + escapeHtml(status) + '</button>';
        }).join('');
    }

    function renderItems(order) {
        var items = order.items || [];
        if (!items.length) {
            return '<div class="mobile-empty-state compact"><span>No items.</span></div>';
        }

        return items.map(function (item) {
            return [
                '<div class="mobile-item-row">',
                '<div><strong>' + escapeHtml(item.product_name) + '</strong><span>Qty ' + escapeHtml(item.quantity) + '</span></div>',
                '<strong>' + escapeHtml(item.line_total_formatted) + '</strong>',
                '</div>'
            ].join('');
        }).join('');
    }

    function renderShipment(order) {
        var shipment = order.shipment || null;
        if (!shipment) {
            return '<button class="mobile-outline-action" type="button" data-api-action="steadfast" data-order-id="' + order.id + '">Create Steadfast</button>';
        }

        return [
            '<div class="mobile-info-grid compact">',
            '<div><span>Courier</span><strong>' + escapeHtml(shipment.courier_name || 'Steadfast') + '</strong></div>',
            '<div><span>Tracking</span><strong>' + escapeHtml(shipment.tracking_code || shipment.consignment_id || 'Saved') + '</strong></div>',
            '</div>',
            '<button class="mobile-outline-action" type="button" data-api-action="steadfast" data-order-id="' + order.id + '">Update Steadfast</button>'
        ].join('');
    }

    function renderOrderDetail(order) {
        if (!detailContent) {
            return;
        }

        if (!order) {
            detailContent.innerHTML = '<div class="mobile-empty-state compact"><strong>Order not found</strong></div>';
            return;
        }

        detailContent.innerHTML = [
            '<div class="mobile-detail-head">',
            '<div><span class="mobile-kicker">Order</span><h2>' + escapeHtml(order.order_number) + '</h2></div>',
            '<button class="mobile-close-button" type="button" data-close-detail>Close</button>',
            '</div>',
            '<div class="mobile-detail-total"><span>' + escapeHtml(order.status) + '</span><strong>' + escapeHtml(order.total_formatted) + '</strong></div>',
            '<section class="mobile-detail-section">',
            '<h3>Customer</h3>',
            '<div class="mobile-info-grid">',
            '<div><span>Name</span><strong>' + escapeHtml(order.customer_name) + '</strong></div>',
            '<div><span>Phone</span><strong>' + escapeHtml(order.customer_phone) + '</strong></div>',
            '<div><span>Area</span><strong>' + escapeHtml(order.district_area) + '</strong></div>',
            '<div><span>Date</span><strong>' + escapeHtml(order.created_label) + '</strong></div>',
            '</div>',
            '<p class="mobile-address">' + escapeHtml(order.customer_address) + '</p>',
            order.delivery_note ? '<p class="mobile-note">' + escapeHtml(order.delivery_note) + '</p>' : '',
            '<div class="mobile-inline-actions">',
            '<a href="tel:' + escapeHtml(order.customer_phone) + '">Call Customer</a>',
            '<a href="' + escapeHtml(order.invoice_url) + '" target="_blank" rel="noopener">Invoice</a>',
            '<a href="' + escapeHtml(order.detail_url) + '">Desktop</a>',
            '</div>',
            '</section>',
            '<section class="mobile-detail-section">',
            '<h3>Process</h3>',
            '<div class="mobile-status-actions">' + renderStatusButtons(order) + '</div>',
            '</section>',
            '<section class="mobile-detail-section">',
            '<h3>Items</h3>',
            renderItems(order),
            '<div class="mobile-total-rows">',
            '<div><span>Subtotal</span><strong>' + escapeHtml(order.subtotal_formatted) + '</strong></div>',
            '<div><span>Delivery</span><strong>' + escapeHtml(order.delivery_charge_formatted) + '</strong></div>',
            '</div>',
            '</section>',
            '<section class="mobile-detail-section">',
            '<h3>Courier</h3>',
            renderShipment(order),
            '</section>',
            '<section class="mobile-detail-section">',
            '<h3>Message</h3>',
            '<div class="mobile-inline-actions">',
            '<button type="button" data-api-action="customer_sms" data-order-id="' + order.id + '">SMS</button>',
            '<button type="button" data-api-action="customer_email" data-order-id="' + order.id + '">Email</button>',
            '</div>',
            '</section>'
        ].join('');
    }

    function openDetailPanel() {
        if (detailPanel) {
            detailPanel.hidden = false;
            detailPanel.classList.add('is-open');
        }
        if (detailBackdrop) {
            detailBackdrop.hidden = false;
        }
        document.body.classList.add('has-mobile-detail');
    }

    function closeDetailPanel() {
        if (detailPanel) {
            detailPanel.classList.remove('is-open');
            detailPanel.hidden = true;
        }
        if (detailBackdrop) {
            detailBackdrop.hidden = true;
        }
        document.body.classList.remove('has-mobile-detail');
    }

    function findOrder(id) {
        var orders = state.orders || [];
        for (var i = 0; i < orders.length; i += 1) {
            if (Number(orders[i].id) === Number(id)) {
                return orders[i];
            }
        }
        return null;
    }

    function buildStateUrl(extra) {
        var url = new URL(boot.apiUrl, window.location.origin);
        url.searchParams.set('status', activeStatus);
        url.searchParams.set('q', activeQuery);
        url.searchParams.set('after_id', String(lastSeenOrderId));

        Object.keys(extra || {}).forEach(function (key) {
            url.searchParams.set(key, extra[key]);
        });

        return url.toString();
    }

    function handleAuth(response) {
        if (response.status === 401) {
            window.location.href = boot.loginUrl;
            return false;
        }

        return true;
    }

    function applyState(nextState, options) {
        options = options || {};
        state = nextState || state;
        latestOrderId = Number(state.latest_order_id || latestOrderId || 0);
        updateStats();
        renderOrderList();

        if (state.selected_order) {
            renderOrderDetail(state.selected_order);
            selectedOrderId = Number(state.selected_order.id || selectedOrderId);
        }

        if (lastSyncNode) {
            lastSyncNode.textContent = 'Synced ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        if (!options.skipNotifications) {
            handleNewOrders(state.new_orders || []);
        }
    }

    function fetchState(options) {
        options = options || {};
        if (isBusy && !options.force) {
            return Promise.resolve();
        }

        updateConnection(window.navigator.onLine);
        return window.fetch(buildStateUrl(options.orderId ? { order_id: options.orderId } : {}), {
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            if (!handleAuth(response)) {
                return null;
            }
            return response.json();
        }).then(function (payload) {
            if (!payload) {
                return;
            }
            if (!payload.ok) {
                throw new Error(payload.message || 'Could not load orders.');
            }
            applyState(payload.data, options);
            updateConnection(true);
        }).catch(function (error) {
            updateConnection(false);
            if (!options.silent) {
                showToast(error.message || 'Could not load orders.', 'error');
            }
        });
    }

    function postAction(action, data) {
        data = data || {};
        isBusy = true;
        root && root.classList.add('is-busy');

        var body = new URLSearchParams();
        body.set('_csrf', boot.csrf);
        body.set('action', action);
        body.set('status_filter', activeStatus);
        body.set('q', activeQuery);
        body.set('after_id', String(lastSeenOrderId));
        Object.keys(data).forEach(function (key) {
            body.set(key, data[key]);
        });

        return window.fetch(boot.apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (response) {
            if (!handleAuth(response)) {
                return null;
            }
            return response.json();
        }).then(function (payload) {
            if (!payload) {
                return;
            }
            if (!payload.ok) {
                throw new Error(payload.message || 'Action failed.');
            }
            applyState(payload.data, { skipNotifications: true });
            showToast(payload.message || 'Saved.');
        }).catch(function (error) {
            showToast(error.message || 'Action failed.', 'error');
        }).finally(function () {
            isBusy = false;
            root && root.classList.remove('is-busy');
        });
    }

    function showBrowserNotification(order) {
        var title = 'New order ' + order.order_number;
        var body = order.customer_name + ' - ' + order.total_formatted;

        if (window.AndroidAdmin && typeof window.AndroidAdmin.notifyNewOrder === 'function') {
            window.AndroidAdmin.notifyNewOrder(title, body, order.mobile_url);
            return;
        }

        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        var options = {
            body: body,
            tag: 'order-' + order.id,
            icon: boot.notificationIcon,
            badge: boot.notificationIcon,
            data: { url: order.mobile_url }
        };

        if (window.navigator.serviceWorker && window.navigator.serviceWorker.ready) {
            window.navigator.serviceWorker.ready.then(function (registration) {
                registration.showNotification(title, options);
            }).catch(function () {
                new Notification(title, options);
            });
        } else {
            new Notification(title, options);
        }
    }

    function handleNewOrders(newOrders) {
        if (!newOrders.length) {
            return;
        }

        var newest = newOrders[0];
        var maxId = newOrders.reduce(function (max, order) {
            return Math.max(max, Number(order.id || 0));
        }, lastSeenOrderId);

        if (maxId <= lastSeenOrderId) {
            return;
        }

        lastSeenOrderId = maxId;
        window.localStorage.setItem(lastSeenKey, String(lastSeenOrderId));
        showToast('New order: ' + newest.order_number);
        showBrowserNotification(newest);
    }

    function updateStatusTabs() {
        document.querySelectorAll('[data-filter-status]').forEach(function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-filter-status') === activeStatus);
        });
    }

    function requestNotifications() {
        if (!('Notification' in window)) {
            updateNotificationButton();
            return;
        }

        Notification.requestPermission().then(function () {
            updateNotificationButton();
            if (Notification.permission === 'granted') {
                showToast('Notifications enabled.');
            }
        });
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in window.navigator)) {
            return;
        }

        window.navigator.serviceWorker.register(boot.serviceWorkerUrl).catch(function () {
            return null;
        });
    }

    function startPolling() {
        window.clearInterval(pollTimer);
        pollTimer = window.setInterval(function () {
            fetchState({ silent: true });
        }, 15000);
    }

    document.addEventListener('click', function (event) {
        var openButton = event.target.closest('[data-open-order]');
        var statusButton = event.target.closest('[data-status-update]');
        var apiButton = event.target.closest('[data-api-action]');
        var filterButton = event.target.closest('[data-filter-status]');

        if (openButton) {
            selectedOrderId = Number(openButton.getAttribute('data-open-order') || 0);
            renderOrderDetail(findOrder(selectedOrderId));
            openDetailPanel();
            fetchState({ orderId: selectedOrderId, force: true, skipNotifications: true });
            return;
        }

        if (statusButton) {
            postAction('status', {
                order_id: statusButton.getAttribute('data-order-id'),
                status: statusButton.getAttribute('data-status-update')
            });
            return;
        }

        if (apiButton) {
            postAction(apiButton.getAttribute('data-api-action'), {
                order_id: apiButton.getAttribute('data-order-id')
            });
            return;
        }

        if (filterButton) {
            activeStatus = filterButton.getAttribute('data-filter-status') || '';
            updateStatusTabs();
            fetchState({ force: true, skipNotifications: true });
            return;
        }

        if (event.target.closest('[data-close-detail]') || event.target === detailBackdrop) {
            closeDetailPanel();
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            window.clearTimeout(searchInput.timer);
            searchInput.timer = window.setTimeout(function () {
                activeQuery = searchInput.value.trim();
                fetchState({ force: true, skipNotifications: true });
            }, 260);
        });
    }

    if (notificationButton) {
        notificationButton.addEventListener('click', requestNotifications);
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        installPromptEvent = event;
        if (installButton) {
            installButton.hidden = false;
        }
    });

    if (installButton) {
        installButton.addEventListener('click', function () {
            if (!installPromptEvent) {
                return;
            }
            installPromptEvent.prompt();
            installPromptEvent.userChoice.finally(function () {
                installPromptEvent = null;
                installButton.hidden = true;
            });
        });
    }

    var refreshButton = document.querySelector('[data-refresh-orders]');
    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            fetchState({ force: true });
        });
    }

    window.addEventListener('online', function () {
        updateConnection(true);
        fetchState({ force: true, silent: true });
    });
    window.addEventListener('offline', function () {
        updateConnection(false);
    });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            fetchState({ force: true, silent: true });
        }
    });

    registerServiceWorker();
    updateNotificationButton();
    updateStats();
    renderOrderList();
    updateStatusTabs();
    updateConnection(window.navigator.onLine);
    startPolling();

    if (selectedOrderId > 0 && state.selected_order) {
        renderOrderDetail(state.selected_order);
        openDetailPanel();
    }
}());
