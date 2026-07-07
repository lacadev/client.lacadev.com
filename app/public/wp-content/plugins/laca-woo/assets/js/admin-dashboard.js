(function() {
    "use strict";

    const config = window.lacaWooAdmin || {};
    const root = document.querySelector(".laca-woo-page");
    let revenueChart = null;
    let lastActionableOrders = null;

    if (!root) {
        return;
    }

    function qs(selector, context) {
        return (context || root).querySelector(selector);
    }

    function qsa(selector, context) {
        return Array.from((context || root).querySelectorAll(selector));
    }

    function endpoint(path, params) {
        const url = new URL(String(config.restUrl || "") + path);
        Object.keys(params || {}).forEach(function(key) {
            if (params[key] !== "" && params[key] !== null && typeof params[key] !== "undefined") {
                url.searchParams.set(key, params[key]);
            }
        });
        return url.toString();
    }

    function fetchJson(path, params) {
        return fetch(endpoint(path, params), {
            credentials: "same-origin",
            headers: {
                "X-WP-Nonce": config.nonce || ""
            }
        }).then(function(response) {
            if (!response.ok) {
                throw new Error("Request failed");
            }
            return response.json();
        });
    }

    function money(value) {
        const currency = config.currency || "VND";
        const locale = String(config.locale || "vi_VN").replace("_", "-");
        try {
            return new Intl.NumberFormat(locale, {
                style: "currency",
                currency: currency,
                maximumFractionDigits: currency === "VND" ? 0 : 2
            }).format(Number(value || 0));
        } catch (error) {
            return String(config.currencySymbol || "") + Number(value || 0).toLocaleString(locale);
        }
    }

    function compactMoney(value) {
        const amount = Number(value || 0);
        const suffixes = [
            [1000000000, "tỷ"],
            [1000000, "tr"],
            [1000, "k"]
        ];

        for (const item of suffixes) {
            if (Math.abs(amount) >= item[0]) {
                const scaled = amount / item[0];
                const rounded = scaled >= 10 ? Math.round(scaled) : Math.round(scaled * 10) / 10;
                return String(rounded).replace(".", ",") + item[1];
            }
        }

        return amount === 0 ? "0" : String(Math.round(amount));
    }

    function number(value) {
        return Number(value || 0).toLocaleString(String(config.locale || "vi_VN").replace("_", "-"));
    }

    function comparisonLabel(value) {
        if (value === null || typeof value === "undefined") {
            return "Mới";
        }
        const sign = value > 0 ? "+" : "";
        return sign + value + "%";
    }

    function setLoading(target) {
        if (target) {
            target.innerHTML = '<p class="laca-woo-empty">' + (config.i18n && config.i18n.loading ? config.i18n.loading : "Loading...") + "</p>";
        }
    }

    function setError(target) {
        if (target) {
            target.innerHTML = '<p class="laca-woo-empty is-error">' + (config.i18n && config.i18n.error ? config.i18n.error : "Error") + "</p>";
        }
    }

    function renderMetrics(revenue, actionableOrders) {
        const targets = qsa("[data-laca-woo-metrics]");
        if (!targets.length || !revenue) {
            return;
        }

        const metrics = revenue.metrics || {};
        const comparison = revenue.comparison || {};
        const cards = [
            ["Gross sales", money(metrics.gross_sales), comparison.gross_sales],
            ["Net sales", money(metrics.net_sales), comparison.net_sales],
            ["Orders", number(metrics.orders), comparison.orders],
            ["AOV", money(metrics.average_order_value), comparison.average_order_value],
            ["Refunds", money(metrics.refunds), null],
            ["Processing", number(actionableOrders && actionableOrders.processing), null]
        ];

        const html = cards.map(function(card) {
            const changeClass = card[2] > 0 ? "is-up" : (card[2] < 0 ? "is-down" : "");
            const change = card[2] === null ? "" : '<span class="laca-woo-metric__change ' + changeClass + '">' + comparisonLabel(card[2]) + "</span>";
            return '<article class="laca-woo-metric"><span>' + card[0] + '</span><strong>' + card[1] + "</strong>" + change + "</article>";
        }).join("");

        targets.forEach(function(target) {
            target.innerHTML = html;
        });
    }

    function renderRevenueChart(revenue) {
        const canvas = document.getElementById("laca-woo-revenue-chart");
        const empty = qs("[data-laca-woo-chart-empty]");
        const label = qs("[data-laca-woo-range-label]");

        if (label) {
            label.textContent = revenue.range_label || "";
        }

        if (!canvas || !window.Chart) {
            if (empty) {
                empty.hidden = false;
                empty.textContent = "Chart.js chưa sẵn sàng.";
            }
            return;
        }

        const series = revenue.series || [];
        const hasData = series.some(function(item) {
            return Number(item.gross_sales || 0) > 0 || Number(item.net_sales || 0) > 0;
        });

        if (empty) {
            empty.hidden = hasData;
        }

        if (revenueChart) {
            revenueChart.destroy();
        }

        revenueChart = new Chart(canvas, {
            type: "bar",
            data: {
                labels: series.map(function(item) { return item.label; }),
                datasets: [
                    {
                        type: "bar",
                        label: "Doanh thu gộp",
                        data: series.map(function(item) { return item.gross_sales; }),
                        backgroundColor: "rgba(34, 113, 177, 0.2)",
                        borderColor: "#2271b1",
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false
                    },
                    {
                        type: "line",
                        label: "Doanh thu thực nhận",
                        data: series.map(function(item) { return item.net_sales; }),
                        borderColor: "#00a32a",
                        backgroundColor: "#00a32a",
                        borderWidth: 2,
                        pointRadius: 2,
                        pointHoverRadius: 4,
                        tension: 0.35
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: "index", intersect: false },
                layout: {
                    padding: { top: 8, right: 10, bottom: 0, left: 0 }
                },
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ": " + money(context.parsed.y);
                            },
                            afterBody: function(items) {
                                const index = items && items.length ? items[0].dataIndex : -1;
                                if (index < 0 || !series[index]) {
                                    return "";
                                }
                                return "Đơn hàng: " + number(series[index].orders || 0);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        ticks: {
                            callback: function(value) {
                                return compactMoney(value);
                            }
                        },
                        grid: { color: "rgba(0, 0, 0, 0.06)" }
                    }
                }
            }
        });
    }

    function productTable(items) {
        if (!items || !items.length) {
            return '<p class="laca-woo-empty">' + (config.i18n && config.i18n.empty ? config.i18n.empty : "Empty") + "</p>";
        }

        return '<table class="widefat striped laca-woo-table"><thead><tr>' +
            "<th>Sản phẩm</th><th>SKU</th><th>Giá</th><th>Tồn kho</th><th>Đã bán</th><th>Ước tính doanh thu</th>" +
            "</tr></thead><tbody>" + items.map(function(item) {
                const stockClass = item.stock_status === "outofstock" || item.is_low_stock ? " is-warning" : "";
                return "<tr>" +
                    '<td><div class="laca-woo-product"><img src="' + escapeHtml(item.image || "") + '" alt=""><div><a href="' + escapeHtml(item.edit_url || "#") + '">' + escapeHtml(item.name) + "</a><span>" + escapeHtml(item.type || "") + "</span></div></div></td>" +
                    "<td>" + escapeHtml(item.sku || "-") + "</td>" +
                    "<td>" + escapeHtml(item.price_html || money(item.price)) + "</td>" +
                    '<td><span class="laca-woo-stock' + stockClass + '">' + escapeHtml(item.stock_label || "-") + "</span></td>" +
                    "<td>" + number(item.total_sales) + "</td>" +
                    "<td>" + money(item.estimated_revenue) + "</td>" +
                "</tr>";
            }).join("") + "</tbody></table>";
    }

    function ordersTable(items) {
        if (!items || !items.length) {
            return '<p class="laca-woo-empty">' + (config.i18n && config.i18n.empty ? config.i18n.empty : "Empty") + "</p>";
        }

        return '<table class="widefat striped laca-woo-table"><thead><tr>' +
            "<th>Đơn</th><th>Khách hàng</th><th>Trạng thái</th><th>Thanh toán</th><th>Tổng</th><th>Ngày</th>" +
            "</tr></thead><tbody>" + items.map(function(item) {
                return "<tr>" +
                    '<td><a href="' + escapeHtml(item.edit_url || "#") + '">#' + escapeHtml(item.number) + "</a></td>" +
                    "<td>" + escapeHtml(item.customer || "-") + "</td>" +
                    '<td><span class="laca-woo-pill">' + escapeHtml(item.status_label || item.status) + "</span></td>" +
                    "<td>" + escapeHtml(item.payment_method || "-") + "</td>" +
                    "<td>" + money(item.total) + "</td>" +
                    "<td>" + escapeHtml(item.created || "-") + "</td>" +
                "</tr>";
            }).join("") + "</tbody></table>";
    }

    function renderStatusList(items, target) {
        if (!target) {
            return;
        }
        target.innerHTML = '<ul class="laca-woo-status-list">' + (items || []).map(function(item) {
            return '<li><a href="' + escapeHtml(item.url || "#") + '">' + escapeHtml(item.label) + "</a><strong>" + number(item.count) + "</strong></li>";
        }).join("") + "</ul>";
    }

    function renderInventory(summary, target) {
        if (!target || !summary) {
            return;
        }
        const items = [
            ["Tổng sản phẩm", summary.total_products],
            ["Sắp hết hàng", summary.low_stock],
            ["Hết hàng", summary.out_of_stock],
            ["Chưa quản lý tồn kho", summary.not_managed],
            ["Chưa bán được", summary.no_sales]
        ];
        target.innerHTML = '<ul class="laca-woo-status-list">' + items.map(function(item) {
            return "<li><span>" + item[0] + "</span><strong>" + number(item[1]) + "</strong></li>";
        }).join("") + "</ul>";
    }

    function escapeHtml(value) {
        return String(value === null || typeof value === "undefined" ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function loadSummary() {
        setLoading(qs("[data-laca-woo-top-products]"));
        setLoading(qs("[data-laca-woo-recent-orders]"));

        fetchJson("/summary", { period: "month" }).then(function(data) {
            lastActionableOrders = data.actionable_orders || null;
            renderMetrics(data.revenue, data.actionable_orders);
            renderRevenueChart(data.revenue);
            qs("[data-laca-woo-top-products]").innerHTML = productTable(data.top_products);
            qs("[data-laca-woo-recent-orders]").innerHTML = ordersTable(data.recent_orders);
            qs("[data-laca-woo-low-stock]").innerHTML = productTable(data.low_stock_products);
            renderStatusList(data.order_statuses, qs("[data-laca-woo-order-statuses]"));
            renderInventory(data.inventory, qs("[data-laca-woo-inventory-summary]"));
        }).catch(function() {
            setError(qs("[data-laca-woo-top-products]"));
            setError(qs("[data-laca-woo-recent-orders]"));
        });
    }

    function loadRevenue(period, start, end) {
        fetchJson("/revenue", { period: period, start: start || "", end: end || "" }).then(function(data) {
            renderMetrics(data, lastActionableOrders);
            renderRevenueChart(data);
        }).catch(function() {
            const empty = qs("[data-laca-woo-chart-empty]");
            if (empty) {
                empty.hidden = false;
                empty.textContent = config.i18n && config.i18n.error ? config.i18n.error : "Error";
            }
        });
    }

    function loadProducts(filter) {
        const target = qs("[data-laca-woo-products-table]");
        setLoading(target);
        fetchJson("/products", { filter: filter || "top_selling", limit: 40 }).then(function(data) {
            target.innerHTML = productTable(data.items);
        }).catch(function() {
            setError(target);
        });
    }

    function loadOrders(status) {
        const target = qs("[data-laca-woo-orders-table]");
        setLoading(target);
        fetchJson("/orders", { status: status || "any", limit: 20 }).then(function(data) {
            target.innerHTML = ordersTable(data.items);
            renderStatusList(data.statuses, qs("[data-laca-woo-order-statuses]"));
        }).catch(function() {
            setError(target);
        });
    }

    qsa(".laca-woo-tabs button").forEach(function(button) {
        button.addEventListener("click", function() {
            const panel = button.dataset.panel;
            qsa(".laca-woo-tabs button").forEach(function(item) {
                item.classList.toggle("is-active", item === button);
            });
            qsa(".laca-woo-panel").forEach(function(item) {
                item.classList.toggle("is-active", item.dataset.panel === panel);
            });
        });
    });

    qsa("[data-period]").forEach(function(button) {
        button.addEventListener("click", function() {
            const period = button.dataset.period;
            qsa("[data-period]").forEach(function(item) {
                item.classList.toggle("is-active", item === button);
            });
            const customRange = qs("[data-laca-woo-custom-range]");
            if (customRange) {
                customRange.hidden = period !== "custom";
            }
            if (period !== "custom") {
                loadRevenue(period);
            }
        });
    });

    const applyRange = qs("[data-laca-woo-apply-range]");
    if (applyRange) {
        applyRange.addEventListener("click", function() {
            loadRevenue("custom", qs("[data-laca-woo-start]").value, qs("[data-laca-woo-end]").value);
        });
    }

    const productFilter = qs("[data-laca-woo-product-filter]");
    if (productFilter) {
        productFilter.addEventListener("change", function() {
            loadProducts(productFilter.value);
        });
    }

    const orderStatus = qs("[data-laca-woo-order-status]");
    if (orderStatus) {
        orderStatus.addEventListener("change", function() {
            loadOrders(orderStatus.value);
        });
    }

    loadSummary();
    loadProducts("top_selling");
    loadOrders("any");
}());
