<?php
/**
 * LIFF: 新品入庫 (支援既有品項與新品項)
 */
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 新品入庫 - 倉管小幫手</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f8f9fa; padding-bottom: 80px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .btn-action { width: 100%; border-radius: 8px; font-weight: bold; }
        .floating-btn { position: fixed; bottom: 20px; right: 20px; border-radius: 50px; padding: 12px 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 999; }
    </style>
</head>
<body>
    <div id="app" class="container py-4">
        <h4 class="mb-3 text-center fw-bold">📥 新品入庫</h4>

        <!-- 分類切換 -->
        <div class="d-flex justify-content-center mb-4">
            <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="cat" id="cat1" value="產品" v-model="categoryFilter" checked>
                <label class="btn btn-outline-primary" for="cat1">產品</label>

                <input type="radio" class="btn-check" name="cat" id="cat2" value="包材" v-model="categoryFilter">
                <label class="btn btn-outline-primary" for="cat2">包材</label>

                <input type="radio" class="btn-check" name="cat" id="cat3" value="雜項" v-model="categoryFilter">
                <label class="btn btn-outline-primary" for="cat3">雜項</label>
            </div>
        </div>

        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">載入產品清單...</p>
        </div>

        <div v-else>
            <!-- 產品列表 -->
            <div v-for="item in filteredProducts" :key="item.id" class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h5 class="fw-bold mb-1">{{ item.name }}</h5>
                        <div class="text-muted small">{{ item.spec }}</div>
                    </div>
                    <span class="badge bg-secondary">{{ item.category }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        每箱: {{ item.unit_per_case }} {{ getUnit(item.name, item.spec) }}
                    </div>
                    <button class="btn btn-primary btn-sm px-3" @click="openRestockModal(item)">入庫</button>
                </div>
            </div>
            
            <div v-if="filteredProducts.length === 0" class="text-center text-muted my-5">
                此分類尚無產品，請建立新產品。
            </div>
        </div>

        <!-- 建立新產品按鈕 -->
        <button class="btn btn-success floating-btn fw-bold" @click="openCreateModal">
            ➕ 建立新產品
        </button>

        <!-- 入庫 Modal -->
        <div class="modal fade" id="restockModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">入庫：{{ currentItem.name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">入庫箱數</label>
                            <input type="number" class="form-control" v-model="form.cases" min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">效期 (可選)</label>
                            <input type="date" class="form-control" v-model="form.expiry_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary w-100" @click="submitRestock" :disabled="submitting">
                            {{ submitting ? '處理中...' : '確認入庫' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 建立新產品 Modal -->
        <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">建立新產品</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">名稱</label>
                            <input type="text" class="form-control" v-model="createForm.name" placeholder="例如：甲足飽">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">類別</label>
                            <select class="form-select" v-model="createForm.category">
                                <option value="產品">產品</option>
                                <option value="包材">包材</option>
                                <option value="雜項">雜項</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">規格</label>
                            <input type="text" class="form-control" v-model="createForm.spec" placeholder="例如：20g/包">
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">每箱數量</label>
                                <input type="number" class="form-control" v-model="createForm.unit_per_case">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">初始箱數</label>
                                <input type="number" class="form-control" v-model="createForm.cases">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">效期 (可選)</label>
                            <input type="date" class="form-control" v-model="createForm.expiry_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success w-100" @click="submitCreate" :disabled="submitting">
                            {{ submitting ? '處理中...' : '建立並入庫' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script>
        const { createApp, ref, computed, onMounted } = Vue;

        createApp({
            setup() {
                const products = ref([]);
                const loading = ref(true);
                const categoryFilter = ref('產品');
                const submitting = ref(false);
                
                // Modals
                let restockModalInstance = null;
                let createModalInstance = null;

                // Forms
                const currentItem = ref({});
                const form = ref({ cases: 0, expiry_date: '' });
                const createForm = ref({ name: '', category: '產品', spec: '', unit_per_case: 1, cases: 0, expiry_date: '' });

                const filteredProducts = computed(() => {
                    return products.value.filter(p => p.category === categoryFilter.value);
                });

                const getUnit = (name, spec) => {
                    if (name && name.includes('盒')) return '盒';
                    if (name && name.includes('包')) return '包';
                    if (name && name.includes('瓶')) return '瓶';
                    if (name && name.includes('罐')) return '罐';
                    if (name && name.includes('座')) return '座';
                    
                    if (spec) {
                        if (spec.includes('包')) return '包';
                        if (spec.includes('盒')) return '盒';
                        if (spec.includes('瓶')) return '瓶';
                    }
                    return '單位';
                };

                const fetchData = async () => {
                    try {
                        const res = await fetch('api_get_all_products.php');
                        const json = await res.json();
                        if (json.success) {
                            products.value = json.data;
                        }
                    } catch (e) {
                        console.error(e);
                    } finally {
                        loading.value = false;
                    }
                };

                const openRestockModal = (item) => {
                    currentItem.value = item;
                    form.value = { cases: 0, expiry_date: '', product_id: item.id };
                    restockModalInstance.show();
                };

                const openCreateModal = () => {
                    createForm.value = { name: '', category: categoryFilter.value, spec: '', unit_per_case: 1, cases: 0, expiry_date: '' };
                    createModalInstance.show();
                };

                const submitRestock = async () => {
                    if (form.value.cases <= 0) return Swal.fire('錯誤', '數量必須大於 0', 'warning');
                    submitting.value = true;
                    await postData(form.value, restockModalInstance);
                };

                const submitCreate = async () => {
                    if (!createForm.value.name) return Swal.fire('錯誤', '請輸入名稱', 'warning');
                    submitting.value = true;
                    await postData(createForm.value, createModalInstance);
                };

                const postData = async (payload, modal) => {
                    try {
                        const res = await fetch('api_add_stock.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const json = await res.json();
                        
                        if (json.success) {
                            await Swal.fire('成功', '操作成功！', 'success');
                            modal.hide();
                            fetchData(); // Reload list
                        } else {
                            Swal.fire('失敗', json.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('錯誤', '網路連線失敗', 'error');
                    } finally {
                        submitting.value = false;
                    }
                };

                onMounted(async () => {
                    restockModalInstance = new bootstrap.Modal(document.getElementById('restockModal'));
                    createModalInstance = new bootstrap.Modal(document.getElementById('createModal'));
                    
                    // We need a simple product list API. 
                    // Reuse api_get_restock_list.php but maybe we need a simpler one?
                    // api_get_restock_list.php is fine, it returns all products.
                    
                    // But wait, api_get_restock_list.php has HAVING dayuan_cases > 0 filter!
                    // We need ALL products for restock.
                    // I should create api_get_all_products.php or modify api_get_products.php
                    
                    await fetchData();
                    try { await liff.init({ liffId: "2008988832-qQ0xjwL8" }); } catch (e) {}
                });

                return { 
                    loading, categoryFilter, filteredProducts, 
                    currentItem, form, createForm, submitting,
                    openRestockModal, openCreateModal, submitRestock, submitCreate, getUnit
                };
            }
        }).mount('#app');
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
