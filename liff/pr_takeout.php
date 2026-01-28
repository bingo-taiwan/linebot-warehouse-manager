<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏢 公關品取貨 - 倉管小幫手</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        body { background-color: #f8f9fa; padding-bottom: 100px; }
        .card { border-radius: 12px; border: none; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .checkout-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #343a40; padding: 15px; color: white; }
    </style>
</head>
<body>
    <div id="app" class="container py-4">
        <h3 class="mb-4 text-center fw-bold">🏢 公關品自取</h3>
        
        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div v-else class="row g-3">
            <div v-for="item in products" :key="item.id" class="col-12">
                <div class="card p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">{{ item.name }}</h5>
                            <div class="text-muted small">庫存: {{ item.total_units }} {{ getUnit(item.spec) }}</div>
                        </div>
                        <div class="d-flex align-items-center border rounded-pill p-1">
                            <button @click="updateQty(item.id, -1)" class="btn btn-light btn-sm rounded-circle fw-bold">−</button>
                            <span class="mx-3 fw-bold">{{ cart[item.id] || 0 }}</span>
                            <button @click="updateQty(item.id, 1, item.total_units)" class="btn btn-light btn-sm rounded-circle fw-bold">+</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="checkout-bar d-flex justify-content-between align-items-center">
            <div>已選 {{ totalItems }} 項</div>
            <button @click="submit" :disabled="totalItems === 0 || submitting" class="btn btn-warning fw-bold rounded-pill">
                {{ submitting ? '處理中...' : '確認取貨' }}
            </button>
        </div>
    </div>

    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script>
        const { createApp, ref, computed, onMounted } = Vue;

        createApp({
            setup() {
                const products = ref([]);
                const cart = ref({});
                const loading = ref(true);
                const submitting = ref(false);

                const totalItems = computed(() => Object.values(cart.value).reduce((a, b) => a + b, 0));

                const fetchData = async () => {
                    try {
                        const res = await fetch('api_get_products.php'); // 重用獲取產品 API
                        const json = await res.json();
                        if (json.success) products.value = json.data;
                    } catch (e) {} finally { loading.value = false; }
                };

                const updateQty = (id, delta, max) => {
                    const next = (cart.value[id] || 0) + delta;
                    if (next >= 0 && next <= max) {
                        if (next === 0) delete cart.value[id];
                        else cart.value[id] = next;
                    }
                };

                const getUnit = (spec) => spec && spec.includes('包') ? '包' : '盒';

                const submit = async () => {
                    if (!confirm('確認取貨？將直接扣除庫存。')) return;
                    submitting.value = true;
                    try {
                        const items = Object.entries(cart.value).map(([id, qty]) => ({ product_id: id, quantity: qty }));
                        const res = await fetch('api_pr_takeout.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ items })
                        });
                        const json = await res.json();
                        if (json.success) {
                            alert('✅ 取貨完成！庫存已扣除。');
                            liff.closeWindow();
                        } else {
                            alert('❌ 失敗：' + json.message);
                        }
                    } catch (e) {
                        alert('網路錯誤');
                    } finally {
                        submitting.value = false;
                    }
                };

                onMounted(async () => {
                    await fetchData();
                    try { await liff.init({ liffId: "2008988832-Xbi6ryWE" }); } catch (e) {}
                });

                return { products, cart, loading, submitting, totalItems, updateQty, submit, getUnit };
            }
        }).mount('#app');
    </script>
</body>
</html>
