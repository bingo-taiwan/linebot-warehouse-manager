import os
import re
import pymysql

# --- Configuration ---
DB_CONFIG = {
    'host': 'localhost',
    'user': 'linebot_wh',
    'password': 'warehouse_pass_2026',
    'database': 'warehouse',
    'charset': 'utf8mb4'
}

DATA_DIR = r'X:\gemini\linebot-warehouse-manager\unstructured-data'
# 透過關鍵字識別檔案
FILES = {
    'DAYUAN': '啣澈2026.01.26日.txt',
    'TAIPEI': '憭批澈 20260119日.txt'
}

def clean_text(text):
    return text.replace('?', '').strip()

def parse_warehouse_file(filepath, warehouse_name):
    """
    解析非結構化文字檔。
    這是一個高度客製化的解析器，嘗試從雜亂的文字中提取資訊。
    """
    results = []
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return results

    print(f"Parsing {warehouse_name} from {os.path.basename(filepath)}...")
    
    # 嘗試多種編碼
    for encoding in ['utf-8', 'big5', 'cp950', 'utf-8-sig']:
        try:
            with open(filepath, 'r', encoding=encoding, errors='ignore') as f:
                content = f.read()
                if len(content.strip()) > 0:
                    break
        except:
            continue

    # 簡單的切割邏輯：假設每一筆資料之間有空行，或是以特定的產品標頭開始
    # 這裡我們用正則表達式來抓取關鍵資訊
    # 範例格式猜測：品名:xxx 規格:xxx 數量:xxx 效期:xxx
    # 或者是：產品名稱 規格/單位 數量 效期
    
    lines = content.split('\n')
    current_item = {}
    
    for line in lines:
        line = line.strip()
        if not line: continue
        
        # 嘗試從行中提取資訊 (這部分需要根據實際內容調整，這裡先做通用匹配)
        # 匹配範例：[品名] [規格/單位] [數量] [效期]
        # 這裡假設空格或特定字元分隔
        parts = re.split(r'[\s\t/]+', line)
        
        # 如果這行看起來像是有品名跟數量的
        if len(parts) >= 3:
            # 簡單邏輯：嘗試找出數字作為數量
            name = parts[0]
            qty = 0
            unit = "件"
            expiry = ""
            
            for p in parts[1:]:
                if re.search(r'\d{4}[.\-/]\d{2}', p): # 偵測日期
                    expiry = p
                elif re.match(r'^\d+$', p): # 偵測純數字為數量
                    qty = int(p)
                elif len(p) <= 2: # 偵測短字串為單位
                    unit = p
            
            if qty > 0:
                results.append({
                    'name': name,
                    'unit': unit,
                    'quantity': qty,
                    'expiry': expiry,
                    'warehouse': warehouse_name
                })

    return results

def run_import():
    try:
        conn = pymysql.connect(**DB_CONFIG)
        cursor = conn.cursor()
        
        # 1. 重設資料
        print("Resetting tables...")
        cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
        cursor.execute("TRUNCATE TABLE stocks")
        cursor.execute("TRUNCATE TABLE products")
        cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
        
        # 2. 處理大園倉
        dayuan_path = os.path.join(DATA_DIR, FILES['DAYUAN'])
        dayuan_data = parse_warehouse_file(dayuan_path, "大園倉")
        print(f"Found {len(dayuan_data)} items in Da Yuan.")
        
        for item in dayuan_data:
            # 建立或取得產品
            cursor.execute("SELECT id FROM products WHERE name = %s AND spec = %s", (item['name'], item['unit']))
            row = cursor.fetchone()
            if row:
                p_id = row[0]
            else:
                cursor.execute("INSERT INTO products (name, spec) VALUES (%s, %s)", (item['name'], item['unit']))
                p_id = cursor.lastrowid
            
            # 插入庫存
            expiry = item['expiry'] if item['expiry'] else None
            cursor.execute(
                "INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date) VALUES (%s, %s, %s, %s)",
                ('DAYUAN', p_id, item['quantity'], expiry)
            )
            
        conn.commit()
        print("Da Yuan import finished.")

        # 3. 處理台北倉
        taipei_path = os.path.join(DATA_DIR, FILES['TAIPEI'])
        taipei_data = parse_warehouse_file(taipei_path, "台北倉")
        print(f"Found {len(taipei_data)} items in Taipei.")
        
        imported_taipei = 0
        for item in taipei_data:
            # 排除「捆」單位
            if item['unit'] == '捆':
                print(f"  Skipping '{item['name']}' because unit is '捆'")
                continue
            
            # 只匯入與大園倉相應單位的資料 (這部分邏輯是：如果產品已存在且單位相同)
            cursor.execute("SELECT id FROM products WHERE name = %s AND spec = %s", (item['name'], item['unit']))
            row = cursor.fetchone()
            if row:
                p_id = row[0]
                expiry = item['expiry'] if item['expiry'] else None
                cursor.execute(
                    "INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date) VALUES (%s, %s, %s, %s)",
                    ('TAIPEI', p_id, item['quantity'], expiry)
                )
                imported_taipei += 1
            else:
                # 如果大園倉沒有這個產品，根據需求「匯入跟大園倉相應單位的資料」，這裡選擇跳過或新增？
                # 使用者說「只要匯入跟大園倉相應單位的資料」，暗示大園倉是基準。
                pass

        conn.commit()
        print(f"Taipei import finished. Imported {imported_taipei} items.")
        
    except Exception as e:
        print(f"Error: {e}")
    finally:
        if 'conn' in locals():
            conn.close()

if __name__ == "__main__":
    run_import()
