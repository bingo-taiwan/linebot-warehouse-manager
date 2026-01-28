<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 福利品訂單看板</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f2f5; }
        .user-card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; }
        .user-info { display: flex; align-items: center; gap: 12px; }
        .avatar { width: 40px; height: 40px; background: #e0e0e0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #666; }
        .avatar-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd; }
        .status-badge { font-size: 0.85rem; padding: 6px 12px; border-radius: 20px; }
        .btn-action { font-size: 0.9rem; font-weight: bold; }
    </style>
</head>
<body>
    <div id="app" class="container py-4">
        <h4 class="mb-4 text-center fw-bold">📋 本月訂單看板</h4>

        <div v-if="loading" class="text-center my-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div v-else>
            <div v-for="user in dashboard" :key="user.userId" class="user-card" :class="{'border border-2 border-primary': user.isMe}">
                <div class="user-info">
                    <img v-if="user.avatar" :src="user.avatar" class="avatar-img" alt="Avatar">
                    <div v-else class="avatar">{{ user.name ? user.name.charAt(0) : '?' }}</div>
                    <div>
                        <div class="fw-bold">{{ user.name || '未命名' }} <span v-if="user.isMe" class="badge bg-primary ms-1">我</span></div>
                        <div class="text-muted small">{{ user.statusText }}</div>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <!-- 僅本人且 PENDING 狀態可操作 -->
                    <template v-if="user.isMe && user.status === 'PENDING'">
                        <button @click="editOrder" class="btn btn-outline-primary btn-sm btn-action rounded-pill">✏️ 修改</button>
                        <button @click="confirmReceipt(user.userId)" class="btn btn-success btn-sm btn-action rounded-pill">✅ 簽收</button>
                    </template>
                    <!-- 本人未下單 -->
                    <template v-else-if="user.isMe && user.status === 'NONE'">
                        <button @click="editOrder" class="btn btn-primary btn-sm btn-action rounded-pill">🛒 前往選擇</button>
                    </template>
                    <!-- 其他人或已完成 -->
                    <span v-else class="badge" :class="'bg-' + user.statusClass">{{ user.statusText }}</span>
                </div>
            </div>
        </div>

        <div class="refresh-info text-center mt-4">
            <button @click="refresh" class="btn btn-link text-decoration-none text-muted">🔄 重新整理</button>
        </div>
    </div>

    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script>
        const { createApp, ref, onMounted } = Vue;

        createApp({
            setup() {
                const dashboard = ref([]);
                const myOrder = ref(null);
                const loading = ref(true);

                const fetchData = async () => {
                    loading.value = true;
                    try {
                        // TODO: 傳入真實 userId
                        const res = await fetch('api_get_orders_status.php?userId=U004f8cad542e37c7834a3920e60d1077');
                        const json = await res.json();
                        if (json.success) {
                            dashboard.value = json.dashboard;
                            myOrder.value = json.myOrder;
                        }
                    } catch (e) {
                        Swal.fire('AURUMA', '載入失敗', 'error');
                    } finally {
                        loading.value = false;
                    }
                };

                const editOrder = () => {
                    // 跳轉回福利品自選購物車
                    window.location.href = 'benefit_cart.php';
                };

                const confirmReceipt = async (userId) => {
                    const result = await Swal.fire({
                        title: 'AURUMA',
                        text: '確認已收到貨物並簽收？庫存將被扣除。',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: '確認簽收',
                        cancelButtonText: '取消',
                        confirmButtonColor: '#00B900'
                    });

                    if (result.isConfirmed) {
                        if (!myOrder.value) return;
                        
                        try {
                            const res = await fetch('api_confirm_receipt.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ order_id: myOrder.value.order_id })
                            });
                            const json = await res.json();
                            if (json.success) {
                                await Swal.fire('AURUMA', '✅ 簽收成功！', 'success');
                                fetchData();
                            } else {
                                Swal.fire('AURUMA', '❌ ' + json.message, 'error');
                            }
                        } catch (err) {
                            Swal.fire('AURUMA', '網路錯誤', 'error');
                        }
                    }
                };

                const refresh = () => fetchData();

                onMounted(async () => {
                    try { 
                        await liff.init({ liffId: "2008988832-4ZdyYI38" }); 
                    } catch (e) {}
                    fetchData();
                });

                return { dashboard, loading, editOrder, confirmReceipt, refresh };
            }
        }).mount('#app');
    </script>
</body>
</html>
