<?php
/**
 * LIFF: 台北倉向大園倉補貨
 */
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚛 大園補貨 - 倉管小幫手</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f2f5; padding-bottom: 100px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .stock-info { background-color: #e3f2fd; border-radius: 8px; padding: 8px 12px; font-size: 0.9rem; color: #1565C0; }
        .stock-info.low { background-color: #ffebee; color: #c62828; }
        .conversion-hint { font-size: 0.8rem; color: #666; margin-top: 4px; }
        .preview-add { color: #2e7d32; font-weight: bold; font-size: 0.9rem; }
        
        .btn-action { width: 36px; height: 36px; border-radius: 50%; padding: 0; font-weight: bold; }
        .qty-input { width: 50px; text-align: center; border: none; background: transparent; font-weight: bold; font-size: 1.1rem; }
        
        .checkout-bar { position: fixed; bottom: 0; left: 0; right: 0; background: white; padding: 15px; box-shadow: 0 -4px 10px rgba(0,0,0,0.1); z-index: 999; }
    </style>
</head>
<body>
    <div id="app" class="container py-4">
        <h4 class="mb-4 text-center fw-bold">🚛 補貨申請 (大園 ➔ 台北)</h4>

        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">正在盤點大園庫存...</p>
        </div>

        <div v-else>
            <div v-for="item in products" :key="item.id" class="card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="fw-bold mb-1">{{ item.name }}</h5>
                        <div class="text-muted small">{{ item.spec }}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-secondary">{{ item.category }}</span>
                    </div>
                </div>

                <div class="row mt-3 g-2">
                    <!-- 台北現況 -->
                    <div class="col-6">
                        <div class="stock-info" :class="{ 'low': item.taipei_units < 10 }">
                            <div class="small opacity-75">台北現有</div>
                            <div class="fw-bold">{{ item.taipei_units }} {{ getUnit(item.spec) }}</div>
                        </div>
                    </div>
                    <!-- 大園庫存 -->
                    <div class="col-6">
                        <div class="stock-info bg-light text-dark">
                            <div class="small opacity-75">大園庫存</div>
                            <div class="fw-bold">{{ item.dayuan_cases }} 箱</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top">
                    <div>
                        <div class="conversion-hint">1 箱 = {{ item.unit_per_case }} {{ getUnit(item.spec) }}</div>
                        <div v-if="cart[item.id] > 0" class="preview-add">
                            預計 +{{ cart[item.id] * item.unit_per_case }} {{ getUnit(item.spec) }}
                        </div>
                    </div>
                    <div class="d-flex align-items-center bg-light rounded-pill p-1">
                        <button @click="updateQty(item, -1)" class="btn btn-light btn-action">−</button>
                        <input type="text" readonly class="qty-input" :value="cart[item.id] || 0">
                        <button @click="updateQty(item, 1)" class="btn btn-light btn-action">+</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 底部操作區 -->
        <div class="checkout-bar d-flex justify-content-between align-items-center">
            <div>
                <div class="small text-muted">本次調撥</div>
                <div class="fw-bold fs-5">{{ totalCases }} 箱</div>
            </div>
            <button @click="submitRestock" :disabled="totalCases === 0 || submitting" class="btn btn-primary px-4 rounded-pill fw-bold">
                {{ submitting ? '處理中...' : '確認調撥' }}
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

                const totalCases = computed(() => {
                    return Object.values(cart.value).reduce((a, b) => a + b, 0);
                });

                const getUnit = (spec) => {
                    return spec && spec.includes('包') ? '包' : '盒';
                };

                const fetchData = async () => {
                    try {
                        const res = await fetch('api_get_restock_list.php');
                        const json = await res.json();
                        if (json.success) {
                            products.value = json.data;
                        }
                    } catch (e) {
                        Swal.fire('AURUMA', '載入失敗', 'error');
                    } finally {
                        loading.value = false;
                    }
                };

                const updateQty = (item, delta) => {
                    const current = cart.value[item.id] || 0;
                    const next = current + delta;
                    if (next < 0) return;
                    if (next > item.dayuan_cases) {
                        Swal.fire('AURUMA', '大園庫存不足！', 'warning');
                        return;
                    }
                    if (next === 0) delete cart.value[item.id];
                    else cart.value[item.id] = next;
                };

                const submitRestock = async () => {
                    const confirmRes = await Swal.fire({
                        title: 'AURUMA',
                        text: `確認要調撥共 ${totalCases.value} 箱產品到台北倉嗎？`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: '確定',
                        cancelButtonText: '取消'
                    });
                    
                    if (!confirmRes.isConfirmed) return;
                    
                    submitting.value = true;
                    try {
                        const items = Object.entries(cart.value).map(([id, qty]) => ({
                            product_id: id,
                            quantity: qty
                        }));

                        const res = await fetch('api_process_restock.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ items })
                        });
                        const json = await res.json();
                        
                        if (json.success) {
                            await Swal.fire('AURUMA', '✅ 訂單已送出！請等待倉管人員備貨。', 'success');
                            liff.closeWindow();
                        } else {
                            Swal.fire('AURUMA', '❌ 失敗：' + json.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('AURUMA', '網路錯誤', 'error');
                    } finally {
                        submitting.value = false;
                    }
                };

                onMounted(async () => {
                    await fetchData();
                    try { await liff.init({ liffId: "2008988832-PuJ7aR9I" }); } catch (e) {}
                });

                return { products, cart, loading, submitting, totalCases, getUnit, updateQty, submitRestock };
            }
        }).mount('#app');
    </script>
</body>
</html>