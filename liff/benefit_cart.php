<?php
/**
 * LIFF: 福利品自選購物車
 */
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎁 福利品自選 - 倉管小幫手</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        body { background-color: #f0f2f5; padding-bottom: 100px; }
        .product-card { border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .product-card:hover { transform: translateY(-3px); }
        .price { color: #00B900; font-weight: bold; font-size: 1.2rem; }
        .stock-tag { font-size: 0.75rem; color: #666; }
        
        /* 底部結帳條 */
        .checkout-bar { 
            position: fixed; bottom: 0; left: 0; right: 0; 
            background: white; padding: 15px 20px; 
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1); 
            z-index: 1000;
        }
        .quota-info { font-size: 0.9rem; color: #666; }
        .total-amount { font-size: 1.3rem; font-weight: bold; color: #d32f2f; }
        .remaining-quota { font-weight: bold; color: #1565C0; }
        
        .counter-btn { width: 32px; height: 32px; padding: 0; border-radius: 50%; font-weight: bold; }
        .quantity { width: 40px; text-align: center; font-weight: bold; }
        
        [v-cloak] { display: none; }
    </style>
</head>
<body>
    <div id="app" v-cloak class="container py-4">
        <h3 class="mb-4 text-center fw-bold">🎁 員工福利品自選</h3>
        
        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-success" role="status"></div>
            <p class="mt-2 text-muted">載入清單中...</p>
        </div>

        <div v-else class="row g-3">
            <div v-for="item in products" :key="item.id" class="col-12 col-md-6">
                <div class="product-card card h-100 p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-light rounded" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 2rem;">{{ getEmoji(item.category) }}</span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1" style="font-size: 1rem;">{{ item.name }}</h5>
                            <div class="stock-tag mb-1">{{ item.spec }} | 目前 {{ item.total_units }}{{ getUnit(item.spec) }} 在庫</div>
                            <div class="price">${{ formatNumber(item.price_member) }}</div>
                        </div>
                        <div class="flex-shrink-0 d-flex align-items-center border rounded-pill p-1">
                            <button @click="updateQty(item.id, -1)" class="btn btn-light counter-btn">−</button>
                            <span class="quantity">{{ cart[item.id] || 0 }}</span>
                            <button @click="updateQty(item.id, 1, item.total_units)" class="btn btn-light counter-btn">+</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 結帳條 -->
        <div class="checkout-bar d-flex justify-content-between align-items-center">
            <div class="quota-info">
                <div>已選：<span class="total-amount">${{ formatNumber(totalPrice) }}</span></div>
                <div>剩餘額度：<span class="remaining-quota">${{ formatNumber(quota - totalPrice) }}</span></div>
            </div>
            <button @click="submitOrder" :disabled="totalPrice === 0 || totalPrice > quota || submitting" class="btn btn-primary px-4 py-2 fw-bold rounded-pill" style="background-color: #00B900; border: none;">
                {{ submitting ? '處理中...' : '送出訂單' }}
            </button>
        </div>
    </div>

    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script>
        const { createApp, ref, computed, onMounted } = Vue;

        createApp({
            setup() {
                const products = ref([]);
                const cart = ref({}); // { productId: qty }
                const quota = ref(10000);
                const loading = ref(true);
                const submitting = ref(false);

                const totalPrice = computed(() => {
                    return Object.entries(cart.value).reduce((sum, [id, qty]) => {
                        const product = products.value.find(p => p.id == id);
                        return sum + (product ? product.price_member * qty : 0);
                    }, 0);
                });

                const fetchData = async () => {
                    try {
                        const resp = await fetch('api_get_products.php');
                        const result = await resp.json();
                        if (result.success) {
                            products.value = result.data;
                            quota.value = result.quota_limit;
                        }
                    } catch (err) {
                        alert('無法載入產品資料');
                    } finally {
                        loading.value = false;
                    }
                };

                const updateQty = (id, delta, max) => {
                    const current = cart.value[id] || 0;
                    const next = current + delta;
                    if (next < 0) return;
                    if (delta > 0 && next > max) {
                        alert('庫存不足');
                        return;
                    }
                    if (next === 0) {
                        delete cart.value[id];
                    } else {
                        cart.value[id] = next;
                    }
                };

                const submitOrder = async () => {
                    if (totalPrice.value > quota.value) {
                        alert('超過福利品額度！');
                        return;
                    }
                    submitting.value = true;
                    try {
                        const orderItems = Object.entries(cart.value).map(([id, qty]) => ({
                            product_id: id,
                            quantity: qty
                        }));
                        
                        const resp = await fetch('api_benefit_order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ items: orderItems })
                        });
                        const res = await resp.json();
                        if (res.success) {
                            alert('✅ 訂單已送出！請等待倉管人員確認。');
                            liff.closeWindow();
                        } else {
                            alert('❌ 失敗：' + res.message);
                        }
                    } catch (err) {
                        alert('網路異常，請稍後再試');
                    } finally {
                        submitting.value = false;
                    }
                };

                const getEmoji = (cat) => {
                    if (cat === '產品') return '💊';
                    if (cat === '包材') return '📦';
                    return '📎';
                };

                const getUnit = (spec) => {
                    if (!spec) return '散';
                    return spec.includes('包') ? '包' : '盒';
                };

                const formatNumber = (num) => {
                    return Number(num).toLocaleString();
                };

                onMounted(async () => {
                    await fetchData();
                    try {
                        await liff.init({ liffId: "2008988832-TPY6jyIR" });
                    } catch (err) {}
                });

                return { products, cart, quota, loading, submitting, totalPrice, updateQty, submitOrder, getEmoji, getUnit, formatNumber };
            }
        }).mount('#app');
    </script>
</body>
</html>
