import re
import pymysql
import json

# 配置資訊
config = {
    'host': 'localhost',
    'user': 'linebot_wh',
    'password': 'warehouse_pass_2026',
    'db': 'warehouse',
    'charset': 'utf8mb4'
}

def parse_taipei_txt(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # 根據 "品名：" 分隔
    items = content.split('品名：')[1:]
    parsed_items = []
    
    for item in items:
        lines = item.strip().split('
')
        name = lines[0].replace('✅', '').strip()
        
        spec = ""
        qty_str = ""
        expiry = ""
        
        for line in lines[1:]:
            if '規格：' in line:
                spec = line.replace('規格：', '').strip()
            elif '數量：' in line:
                qty_str = line.replace('數量：', '').strip()
            elif '效期：' in line or '到期日：' in line:
                expiry = re.search(r'\d{4}[\.\-/]\d{2}[\.\-/]?\d{0,2}', line)
                expiry = expiry.group().replace('.', '-') if expiry else ""
                if expiry and len(expiry) == 7: # YYYY-MM
                    expiry += "-01"
        
        # 處理數量 (台北倉多為散裝，單位可能是 盒、包、瓶、捆、箱)
        # 匹配數字 (包含 0.9, 1.1 等)
        qty_match = re.search(r'(\d+\.?\d*)', qty_str)
        if qty_match:
            val = float(qty_match.group(1))
            unit = "個"
            if '盒' in qty_str: unit = "盒"
            elif '包' in qty_str: unit = "包"
            elif '瓶' in qty_str: unit = "瓶"
            elif '捆' in qty_str: unit = "捆"
            elif '箱' in qty_str: unit = "箱"
            
            parsed_items.append({
                'name': name,
                'spec': spec,
                'qty': val,
                'unit': unit,
                'expiry': expiry
            })
            
    return parsed_items

def import_to_db(items):
    conn = pymysql.connect(**config)
    try:
        with conn.cursor() as cursor:
            # 清除舊的台北倉資料 (或是根據需求更新)
            # cursor.execute("DELETE FROM stocks WHERE warehouse_id = 'TAIPEI'")
            
            for item in items:
                # 1. 尋找或建立產品
                # 嘗試模糊匹配品名
                clean_name = item['name'].split('(')[0].split('（')[0].strip()
                cursor.execute("SELECT id FROM products WHERE name LIKE %s", (f"%{clean_name}%",))
                row = cursor.fetchone()
                
                if row:
                    product_id = row[0]
                else:
                    # 建立新產品
                    cat = "產品"
                    if "外盒" in item['name'] or "包材" in item['name']: cat = "包材"
                    cursor.execute("INSERT INTO products (name, category, spec) VALUES (%s, %s, %s)", 
                                   (item['name'], cat, item['spec']))
                    product_id = conn.insert_id()
                
                # 2. 寫入庫存
                # 台北倉以 unit_count 為主
                # 如果單位是 "箱"，則嘗試轉換為 unit_count (需要 unit_per_case)
                unit_count = item['qty']
                case_count = 0
                
                if item['unit'] == '箱':
                    cursor.execute("SELECT unit_per_case FROM products WHERE id = %s", (product_id,))
                    upc = cursor.fetchone()[0] or 1
                    unit_count = item['qty'] * upc
                    case_count = item['qty']
                
                cursor.execute("""
                    INSERT INTO stocks (warehouse_id, product_id, case_count, unit_count, expiry_date) 
                    VALUES ('TAIPEI', %s, %s, %s, %s)
                """, (product_id, case_count, unit_count, item['expiry'] if item['expiry'] else None))
                
        conn.commit()
        print(f"成功匯入 {len(items)} 筆台北倉資料")
    finally:
        conn.close()

if __name__ == "__main__":
    taipei_file = "/home/lt4.mynet.com.tw/public_html/linebot/warehouse/unstructured-data/台北倉庫20260126盤點.txt"
    # 注意：此路徑為伺服器路徑
    try:
        data = parse_taipei_txt('X:/gemini/linebot-warehouse-manager/unstructured-data/台北倉庫20260126盤點.txt')
        # 在伺服器上執行時需改路徑
        import_to_db(data)
    except Exception as e:
        print(f"錯誤: {e}")
