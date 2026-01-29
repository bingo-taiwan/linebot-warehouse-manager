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
        body { background-color: #f0f2f5; padding-bottom: 120px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .stock-info { background-color: #e3f2fd; border-radius: 8px; padding: 8px 12px; font-size: 0.9rem; color: #1565C0; }
        .stock-info.low { background-color: #ffebee; color: #c62828; }
        .conversion-hint { font-size: 0.8rem; color: #666; margin-top: 4px; }
        .preview-add { color: #2e7d32; font-weight: bold; font-size: 0.9rem; }
        
        .btn-action { width: 36px; height: 36px; border-radius: 50%; padding: 0; font-weight: bold; }
        .qty-input { width: 50px; text-align: center; border: none; background: transparent; font-weight: bold; font-size: 1.1rem; }
        .product-img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-right: 12px; }
        
        .checkout-bar { position: fixed; bottom: 0; left: 0; right: 0; background: white; padding: 15px; box-shadow: 0 -4px 10px rgba(0,0,0,0.1); z-index: 999; }
        
        /* Tab Styling */
        .nav-pills .nav-link { border-radius: 8px; font-weight: bold; color: #666; }
        .nav-pills .nav-link.active { background-color: #0d6efd; color: white; }
        
        [v-cloak] { display: none; }
    </style>
</head>
<body>
    <div id="app" v-cloak class="container py-3">
        <h4 class="mb-3 text-center fw-bold">🚛 補貨申請 (大園 ➔ 台北)</h4>

        <!-- 分類切換 -->
        <ul class="nav nav-pills nav-fill mb-3 bg-white p-1 rounded-3 shadow-sm">
            <li class="nav-item"><a class="nav-link" :class="{active: currentTab === '產品'}" @click="currentTab = '產品'">產品</a></li>
            <li class="nav-item"><a class="nav-link" :class="{active: currentTab === '包材'}" @click="currentTab = '包材'">包材</a></li>
            <li class="nav-item"><a class="nav-link" :class="{active: currentTab === '雜項'}" @click="currentTab = '雜項'">雜項</a></li>
        </ul>

        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted small">正在盤點大園庫存...</p>
        </div>

        <div v-else>
            <div v-for="item in filteredProducts" :key="item.id" class="card p-3">
                <div class="d-flex align-items-center mb-2">
                    <img v-if="item.image_url" :src="item.image_url" class="product-img" alt="Product Image">
                    <div v-else class="rounded bg-light d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        {{ getEmoji(item.category) }}
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="fw-bold mb-1" style="font-size: 1.1rem;">{{ item.name }}</h5>
                                <div class="text-muted small">{{ item.spec }}</div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-secondary">{{ item.category }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-2 g-2 text-center">
                    <div class="col-6">
                        <div class="stock-info" :class="{ 'low': item.taipei_units < 10 }">
                            <div class="small opacity-75">台北現有</div>
                            <div class="fw-bold">{{ item.taipei_units }} {{ getUnit(item.name, item.spec) }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stock-info bg-light text-dark">
                            <div class="small opacity-75">大園庫存</div>
                            <div class="fw-bold">{{ item.dayuan_cases }} 箱</div>
                            <div class="mt-1 x-small" style="font-size: 0.75rem;" v-html="getExpiryStatus(item.earliest_expiry)"></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
                    <div class="small">
                        <div class="conversion-hint text-muted">1 箱 = {{ item.unit_per_case }} {{ getUnit(item.name, item.spec) }}</div>
                        <div v-if="cart[item.id] > 0" class="preview-add">➔ +{{ cart[item.id] * item.unit_per_case }}</div>
                    </div>
                    <div class="d-flex align-items-center bg-light rounded-pill p-1">
                        <button @click="updateQty(item, -1)" class="btn btn-light btn-action shadow-sm">−</button>
                        <input type="text" readonly class="qty-input" :value="cart[item.id] || 0">
                        <button @click="updateQty(item, 1)" class="btn btn-light btn-action shadow-sm">+</button>
                    </div>
                </div>
            </div>
            
            <div v-if="filteredProducts.length === 0" class="text-center py-5 text-muted">
                此分類目前無可調撥商品
            </div>
        </div>

        <!-- 底部操作區 -->
        <div class="checkout-bar d-flex justify-content-between align-items-center">
            <div>
                <div class="small text-muted">本次調撥共</div>
                <div class="fw-bold fs-5 text-primary">{{ totalCases }} 箱</div>
            </div>
            <button @click="submitRestock" :disabled="totalCases === 0 || submitting" class="btn btn-primary px-4 rounded-pill fw-bold py-2 shadow">
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
                const currentTab = ref('產品');

                const filteredProducts = computed(() => {
                    return products.value.filter(p => p.category === currentTab.value);
                });

                const totalCases = computed(() => {
                    return Object.values(cart.value).reduce((a, b) => a + b, 0);
                });

                const getUnit = (name, spec) => {
                    if (name && name.includes('盒')) return '盒';
                    if (name && name.includes('包')) return '包';
                    if (name && name.includes('瓶')) return '瓶';
                    if (spec) {
                        if (spec.includes('包')) return '包';
                        if (spec.includes('盒')) return '盒';
                    }
                    return '單位';
                };

                const getEmoji = (cat) => {
                    if (cat === '產品') return '💊';
                    if (cat === '包材') return '📦';
                    return '📎';
                };

                const getExpiryStatus = (dateStr) => {
                    if (!dateStr) return '';
                    const today = new Date();
                    const expiry = new Date(dateStr);
                    const diffTime = expiry - today;
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                    if (diffDays <= 0) return '<span class="text-danger">⚠️ 已過期</span>';
                    if (diffDays <= 90) return `<span class="text-warning">🔔 剩 ${diffDays} 天</span>`;
                    return `<span class="text-success">😌 剩 ${diffDays} 天</span>`;
                };

                const fetchData = async () => {
                    try {
                        const res = await fetch('api_get_restock_list.php');
                        const json = await res.json();
                        if (json.success) {
                            products.value = json.data;
                        } else {
                            Swal.fire('AURUMA', '載入失敗：' + json.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('AURUMA', '網路錯誤', 'error');
                    } finally {
                        loading.value = false;
                    }
                };

                const updateQty = (item, delta) => {
                    const current = cart.value[item.id] || 0;
                    const next = current + delta;
                    if (next < 0) return;
                    if (next > item.dayuan_cases) {
                        Swal.fire({
                            title: '庫存不足',
                            text: `大園倉僅剩 ${item.dayuan_cases} 箱`,
                            icon: 'warning',
                            toast: true,
                            position: 'top',
                            showConfirmButton: false,
                            timer: 2000
                        });
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
                        cancelButtonText: '取消',
                        confirmButtonColor: '#0d6efd'
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
                            await Swal.fire('AURUMA', '✅ 補貨申請已送出！', 'success');
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

                return { products, cart, loading, submitting, currentTab, filteredProducts, totalCases, getUnit, updateQty, submitRestock, getExpiryStatus, getEmoji };
            }
        }).mount('#app');
    </script>
</body>
</html>
