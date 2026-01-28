<?php
/**
 * LIFF: 新品入庫表單
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f8f9fa; font-family: sans-serif; }
        .form-container { max-width: 500px; margin: 20px auto; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #00B900; border: none; }
        .btn-primary:hover { background-color: #009900; }
        .section-title { font-size: 1.1rem; font-weight: bold; border-left: 4px solid #00B900; padding-left: 10px; margin: 20px 0 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h3 class="text-center mb-4">📥 新品入庫</h3>
            
            <form id="stockForm">
                <div class="section-title">基本資訊</div>
                <div class="mb-3">
                    <label class="form-label">產品名稱</label>
                    <input type="text" class="form-control" name="name" required placeholder="例如：甲足飽盒裝">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">類別</label>
                        <select class="form-select" name="category">
                            <option value="產品">產品</option>
                            <option value="包材">包材</option>
                            <option value="雜項">雜項</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">規格</label>
                        <input type="text" class="form-control" name="spec" placeholder="例如：22盒/箱">
                    </div>
                </div>

                <div class="section-title">庫存設定 (大園倉)</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">每箱數量</label>
                        <input type="number" class="form-control" name="unit_per_case" value="1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">入庫箱數</label>
                        <input type="number" class="form-control" name="cases" required value="0">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">效期 (Expiry Date)</label>
                    <input type="date" class="form-control" name="expiry_date">
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">確認送出</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script>
        async function main() {
            try {
                await liff.init({ liffId: "2008988832-qQ0xjwL8" });
            } catch (err) {}
            
            document.getElementById('stockForm').onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData.entries());
                
                const btn = e.target.querySelector('button');
                btn.disabled = true;
                btn.innerText = '儲存中...';

                try {
                    const resp = await fetch('api_add_stock.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await resp.json();
                    
                    if (result.success) {
                        await Swal.fire({
                            title: 'AURUMA',
                            text: '✅ 入庫成功！',
                            icon: 'success',
                            confirmButtonColor: '#00B900'
                        });
                        liff.closeWindow();
                    } else {
                        Swal.fire('AURUMA', '❌ 錯誤：' + result.message, 'error');
                        btn.disabled = false;
                        btn.innerText = '確認送出';
                    }
                } catch (err) {
                    Swal.fire('AURUMA', '❌ 網路錯誤，請稍後再試', 'error');
                    btn.disabled = false;
                    btn.innerText = '確認送出';
                }
            };
        }
        main();
    </script>
</body>
</html>