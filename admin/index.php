<?php
session_start();
require_once __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';

// 簡易驗證
if (isset($_POST['password'])) {
    if ($_POST['password'] === $config['db']['mysql']['password']) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = "密碼錯誤";
    }
}

if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Login - Warehouse Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="d-flex align-items-center justify-content-center vh-100 bg-light">
        <div class="card p-4 shadow" style="width: 350px;">
            <h4 class="text-center mb-4">倉管後台登入</h4>
            <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <form method="post">
                <input type="password" name="password" class="form-control mb-3" placeholder="請輸入管理員密碼" required>
                <button type="submit" class="btn btn-primary w-100">登入</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 倉儲管理後台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .sidebar { min-height: 100vh; background-color: #343a40; color: white; }
        .nav-link { color: rgba(255,255,255,.75); cursor: pointer; }
        .nav-link.active { color: white; background-color: rgba(255,255,255,.1); }
        .alert-row { background-color: #fff3f3; }
    </style>
</head>
<body>
    <div id="app" class="d-flex">
        <h1 style="color:red; position:fixed; top:0; right:0; z-index:9999;">REMOTE TEST 1.3</h1>
        <!-- Sidebar -->
        <div class="sidebar p-3 d-flex flex-column flex-shrink-0" style="width: 250px;">
            <h4 class="mb-4 px-2">📦 倉儲管理 V2</h4>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a class="nav-link" :class="{active: view === 'dashboard'}" @click="view = 'dashboard'">📊 總覽與預警</a>
                </li>
                <li>
                    <a class="nav-link" :class="{active: view === 'inventory'}" @click="view = 'inventory'">🏭 庫存管理</a>
                </li>
                <li>
                    <a class="nav-link" :class="{active: view === 'reports'}" @click="view = 'reports'">📑 報表中心</a>
                </li>
                <li>
                    <a class="nav-link" :class="{active: view === 'benefit'}" @click="view = 'benefit'">🎁 福利品紀錄</a>
                </li>
                <li>
                    <a class="nav-link" href="users.php?bot=warehouse">👥 用戶權限</a>
                </li>
            </ul>
            <div class="mt-auto p-2 text-white-50 small">Version 1.2</div>
        </div>

        <!-- Content -->
        <div class="flex-grow-1 p-4 bg-light overflow-auto" style="height: 100vh;">
            
            <!-- Dashboard View -->
            <div v-if="view === 'dashboard'">
                <h3 class="mb-4">儀表板</h3>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card p-3 border-start border-4 border-primary">
                            <div class="text-muted small">總品項數</div>
                            <div class="fs-2 fw-bold">{{ stats.products_count }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3 border-start border-4 border-warning">
                            <div class="text-muted small">待處理訂單</div>
                            <div class="fs-2 fw-bold">{{ stats.pending_orders }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3 border-start border-4 border-danger">
                            <div class="text-muted small">庫存預警</div>
                            <div class="fs-2 fw-bold text-danger">{{ stats.alert_count }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="alerts.length > 0" class="card">
                    <div class="card-header bg-danger text-white fw-bold">⚠️ 低庫存警示</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>倉庫</th><th>產品</th><th>目前庫存</th><th>安全水位</th></tr></thead>
                            <tbody>
                                <tr v-for="a in alerts">
                                    <td><span class="badge" :class="a.type==='DAYUAN'?'bg-primary':'bg-success'">{{ a.type==='DAYUAN'?'大園':'台北' }}</span></td>
                                    <td>{{ a.product }}</td>
                                    <td class="fw-bold text-danger">{{ a.current }}</td>
                                    <td class="text-muted">{{ a.threshold }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Inventory View -->
            <div v-if="view === 'inventory'">
                <div class="d-flex justify-content-between mb-3">
                    <h3>即時庫存</h3>
                    <div class="d-flex gap-2">
                        <!-- View Mode Switch -->
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" :class="{active: viewMode === 'TOTAL'}" @click="viewMode = 'TOTAL'">總覽</button>
                            <button class="btn btn-outline-secondary" :class="{active: viewMode === 'DAYUAN'}" @click="viewMode = 'DAYUAN'">大園倉</button>
                            <button class="btn btn-outline-secondary" :class="{active: viewMode === 'TAIPEI'}" @click="viewMode = 'TAIPEI'">台北倉</button>
                        </div>

                        <select v-model="filterCategory" class="form-select form-select-sm" style="width: 150px;">
                            <option value="ALL">全部類別</option>
                            <option value="產品">產品</option>
                            <option value="包材">包材</option>
                            <option value="雜項">雜項</option>
                        </select>
                        <button class="btn btn-sm btn-outline-primary" @click="fetchData">🔄 刷新</button>
                    </div>
                </div>
                
                <!-- Debug Info -->
                <div class="alert alert-info py-1 px-2 mb-2 small">
                    共載入 {{ inventory.length }} 筆資料。
                    <span v-if="inventory.length > 0">最後一筆: {{ inventory[inventory.length-1].name }}</span>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0 align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>產品名稱</th>
                                    <th>類別</th>
                                    <th>規格</th>
                                    <th class="text-center" v-if="viewMode === 'TOTAL' || viewMode === 'DAYUAN'">大園倉</th>
                                    <th class="text-center" v-if="viewMode === 'TOTAL' || viewMode === 'TAIPEI'">台北倉</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in filteredInventory" :key="p.id">
                                    <td class="fw-bold">{{ p.name }}</td>
                                    <td><span class="badge bg-secondary">{{ p.category }}</span></td>
                                    <td class="text-muted small">
                                        {{ p.spec }}
                                        <div v-if="p.unit_per_case > 1">({{ p.unit_per_case }}{{ getUnit(p.name, p.spec) }}/箱)</div>
                                    </td>
                                    <td class="text-center" v-if="viewMode === 'TOTAL' || viewMode === 'DAYUAN'" :class="{'text-danger fw-bold': parseInt(p.dayuan_stock) < parseInt(p.alert_threshold_cases)}">
                                        {{ p.dayuan_stock }} <span class="small text-muted">{{ p.unit_per_case == 1 ? getUnit(p.name, p.spec) : '箱' }}</span>
                                        <div v-if="p.dayuan_expiry" class="mt-1 border-top pt-1" style="font-size: 0.75rem;">
                                            <div v-for="exp in (p.dayuan_expiry ? p.dayuan_expiry.split(', ') : [])" :key="exp" :class="{'text-danger fw-bold': isExpired(exp.split(':')[0])}" class="text-muted text-nowrap">
                                                {{ exp.split(':')[0] }} ({{ exp.split(':')[1] }})
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center" v-if="viewMode === 'TOTAL' || viewMode === 'TAIPEI'" :class="{'text-danger fw-bold': parseInt(p.taipei_stock) < parseInt(p.alert_threshold_units)}">
                                        {{ p.taipei_stock }} <span class="small text-muted">{{ getUnit(p.name, p.spec) }}</span>
                                        <div v-if="p.taipei_expiry" class="mt-1 border-top pt-1" style="font-size: 0.75rem;">
                                            <div v-for="exp in (p.taipei_expiry ? p.taipei_expiry.split(', ') : [])" :key="exp" :class="{'text-danger fw-bold': isExpired(exp.split(':')[0])}" class="text-muted text-nowrap">
                                                {{ exp.split(':')[0] }} ({{ exp.split(':')[1] }})
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Reports View -->
            <div v-if="view === 'reports'">
                <div class="d-flex justify-content-between mb-3">
                    <h3>訂單紀錄</h3>
                    <a href="api/get_orders.php?format=csv" target="_blank" class="btn btn-success">📥 匯出 Excel (CSV)</a>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>#</th><th>類型</th><th>申請人</th><th>內容</th><th>狀態</th><th>操作</th><th>時間</th></tr></thead>
                            <tbody>
                                <tr v-for="o in orders" :key="o.id">
                                    <td>{{ o.id }}</td>
                                    <td><span class="badge bg-secondary">{{ o.order_type }}</span></td>
                                    <td>{{ o.requester_name }}</td>
                                    <td>
                                        <div class="small">
                                            <div v-for="item in JSON.parse(o.items_json)" :key="item.product_id">
                                                • {{ item.product_name || ('ID:' + item.product_id) }}: {{ item.quantity }} 箱
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" :class="statusClass(o.status)">{{ o.status }}</span>
                                    </td>
                                    <td>
                                        <button v-if="o.status === 'PENDING'" @click="updateOrderStatus(o.id, 'SHIPPED')" class="btn btn-sm btn-outline-primary">🚚 出貨</button>
                                        <button v-if="o.status === 'SHIPPED'" @click="updateOrderStatus(o.id, 'RECEIVED')" class="btn btn-sm btn-outline-success">✅ 簽收</button>
                                        <span v-else-if="o.status === 'RECEIVED'" class="text-success small">已完成</span>
                                    </td>
                                    <td class="small">{{ o.created_at }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Benefit View -->
            <div v-if="view === 'benefit'">
                <div class="d-flex justify-content-between mb-3">
                    <h3>🎁 福利品紀錄</h3>
                    <div class="d-flex gap-2">
                        <input type="month" v-model="benefitMonth" class="form-control" @change="fetchBenefitLogs">
                        <button class="btn btn-sm btn-outline-primary" @click="fetchBenefitLogs">🔄 刷新</button>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light"><tr><th>下單時間</th><th>員工</th><th>領取內容</th><th class="text-end">總金額</th><th class="text-center">狀態</th><th>領取日</th></tr></thead>
                            <tbody>
                                <tr v-for="log in benefitLogs" :key="log.id">
                                    <td class="small">{{ log.date }}</td>
                                    <td class="fw-bold">{{ log.staff }}</td>
                                    <td class="small text-muted">{{ log.details }}</td>
                                    <td class="text-end fw-bold text-success">${{ log.amount.toLocaleString() }}</td>
                                    <td class="text-center"><span class="badge" :class="statusClass(log.status)">{{ log.status }}</span></td>
                                    <td class="small text-center">{{ log.receive_date }}</td>
                                </tr>
                                <tr v-if="benefitLogs.length === 0">
                                    <td colspan="6" class="text-center py-4 text-muted">本月尚無紀錄</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="benefitLogs.length > 0">
                                <tr class="table-active fw-bold">
                                    <td colspan="3" class="text-end">本月總計：</td>
                                    <td class="text-end">${{ totalBenefitAmount.toLocaleString() }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        const { createApp, ref, computed, onMounted } = Vue;

        createApp({
            setup() {
                const view = ref('dashboard');
                const viewMode = ref('TOTAL'); // TOTAL, DAYUAN, TAIPEI
                const stats = ref({});
                const inventory = ref([]);
                const alerts = ref([]);
                const orders = ref([]);
                const benefitLogs = ref([]);
                const filterCategory = ref('ALL');
                const benefitMonth = ref(new Date().toISOString().slice(0, 7));

                const setView = (v, mode = 'TOTAL') => {
                    view.value = v;
                    viewMode.value = mode;
                };

                const filteredInventory = computed(() => {
                    if (filterCategory.value === 'ALL') return inventory.value;
                    return inventory.value.filter(p => p.category === filterCategory.value);
                });

                const totalBenefitAmount = computed(() => {
                    return benefitLogs.value.reduce((sum, log) => sum + log.amount, 0);
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
                    // Get Stats & Inventory
                    const res1 = await fetch('api/get_dashboard_stats.php');
                    const json1 = await res1.json();
                    if (json1.success) {
                        stats.value = json1.stats;
                        inventory.value = json1.inventory;
                        alerts.value = json1.alerts;
                    }

                    // Get Orders
                    const res2 = await fetch('api/get_orders.php');
                    const json2 = await res2.json();
                    if (json2.success) {
                        orders.value = json2.data;
                    }
                };

                const fetchBenefitLogs = async () => {
                    const res = await fetch(`api/get_benefit_logs.php?month=${benefitMonth.value}`);
                    const json = await res.json();
                    if (json.success) {
                        benefitLogs.value = json.data;
                    }
                };

                const updateOrderStatus = async (orderId, status) => {
                    const actionName = status === 'SHIPPED' ? '出貨' : (status === 'RECEIVED' ? '簽收' : '更新狀態');
                    
                    const result = await Swal.fire({
                        title: `確定要${actionName}嗎？`,
                        text: `訂單 #${orderId} 將變更為 ${status}`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: '確定',
                        cancelButtonText: '取消',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33'
                    });

                    if (result.isConfirmed) {
                        try {
                            Swal.showLoading();
                            const res = await fetch('api/update_order_status.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ order_id: orderId, status: status })
                            });
                            const json = await res.json();
                            
                            if (json.success) {
                                await Swal.fire('成功', `${actionName}成功！`, 'success');
                                fetchData();
                            } else {
                                Swal.fire('失敗', '更新失敗: ' + json.message, 'error');
                            }
                        } catch (e) {
                            Swal.fire('錯誤', '系統發生錯誤', 'error');
                        }
                    }
                };

                const statusClass = (s) => {
                    if (s === 'PENDING') return 'bg-warning text-dark';
                    if (s === 'SHIPPED') return 'bg-info text-dark';
                    if (s === 'RECEIVED') return 'bg-success';
                    return 'bg-secondary';
                };

                const isExpired = (dateStr) => {
                    if (!dateStr) return false;
                    return new Date(dateStr) < new Date();
                };

                onMounted(() => {
                    fetchData();
                    fetchBenefitLogs();
                });
                // Auto refresh every 60s
                setInterval(fetchData, 60000);

                return { view, viewMode, setView, stats, inventory, alerts, orders, fetchData, statusClass, filterCategory, filteredInventory, benefitMonth, fetchBenefitLogs, benefitLogs, totalBenefitAmount, isExpired, getUnit, updateOrderStatus };
            }
        }).mount('#app');
    </script>
</body>
</html>
