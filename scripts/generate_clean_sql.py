import os
import re

DATA_DIR = r'X:\gemini\linebot-warehouse-manager\unstructured-data'
OUTPUT_SQL = r'X:\gemini\linebot-warehouse-manager\scripts\final_import.sql'

def get_file_content(keyword):
    files = [f for f in os.listdir(DATA_DIR) if keyword in f and f.endswith('.txt')]
    if not files: return ""
    with open(os.path.join(DATA_DIR, files[0]), 'r', encoding='utf-8') as f:
        return f.read()

# 根據內容識別
content_a = get_file_content('2026.01.26') # 檔名含 2026.01.26
content_b = get_file_content('20260119')  # 檔名含 20260119

if '台北倉庫' in content_a:
    taipei_raw = content_a
    dayuan_raw = content_b
else:
    taipei_raw = content_b
    dayuan_raw = content_a

def parse_dayuan(raw):
    """
    大園倉格式：
    品名：...
    規格：...
    數量：...
    """
    items = []
    # 使用 \n\n 分割產品塊
    chunks = raw.split('\n\n')
    for chunk in chunks:
        if '品名：' not in chunk: continue
        
        name = re.search(r'品名：(.*?)\n', chunk)
        spec = re.search(r'規格：(.*?)\n', chunk)
        qty = re.search(r'數量：(\d+)', chunk)
        expiry = re.search(r'(?:效期|到期日|生產日)：(\d{4}[\.\-/]\d{2}[\.\-/]?\d{2}?)', chunk)
        
        if name and qty:
            n = name.group(1).strip()
            s = spec.group(1).strip() if spec else "件"
            q = int(qty.group(1))
            e_raw = expiry.group(1).replace('.', '-') if expiry else "NULL"
            if e_raw != "NULL":
                parts = e_raw.split('-')
                e = f"'{parts[0]}-{parts[1]}-{parts[2] if len(parts)>2 else '01'}'"
            else:
                e = "NULL"
                
            items.append({'name': n, 'spec': s, 'qty': q, 'expiry': e})
    return items

def parse_taipei(raw):
    """
    台北倉格式：
    品名：... 規格：... 數量：... 到期日：...
    """
    items = []
    lines = raw.split('\n')
    for line in lines:
        if '品名：' not in line: continue
        
        # 使用正則捕捉同一行內的資訊
        m = re.search(r'品名：(.*?)\s+規格：(.*?)\s+數量：(.*?)\s+到期日：(.*?)$', line)
        if m:
            n = m.group(1).strip()
            s = m.group(2).strip()
            q_match = re.search(r'(\d+)', m.group(3))
            q = int(q_match.group(1)) if q_match else 0
            e_raw = m.group(4).strip().replace('.', '-')
            parts = e_raw.split('-')
            e = f"'{parts[0]}-{parts[1]}-{parts[2] if len(parts)>2 else '01'}'"
            
            items.append({'name': n, 'spec': s, 'qty': q, 'expiry': e})
    return items

dayuan_items = parse_dayuan(dayuan_raw)
taipei_items = parse_taipei(taipei_raw)

print(f"Parsed Dayuan: {len(dayuan_items)} items")
print(f"Parsed Taipei: {len(taipei_items)} items")

sql_lines = [
    "SET FOREIGN_KEY_CHECKS = 0;",
    "TRUNCATE TABLE stocks;",
    "TRUNCATE TABLE products;",
    "SET FOREIGN_KEY_CHECKS = 1;"
]

product_map = {} # name -> id
p_id_counter = 1

for item in dayuan_items:
    if item['name'] not in product_map:
        product_map[item['name']] = p_id_counter
        sql_lines.append(f"INSERT INTO products (id, name, spec) VALUES ({p_id_counter}, '{item['name']}', '{item['spec']}');")
        p_id_counter += 1
    sql_lines.append(f"INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date) VALUES ('DAYUAN', {product_map[item['name']]}, {item['qty']}, {item['expiry']});")

for item in taipei_items:
    if '捆' in item['spec']: continue
    
    match_id = None
    for p_name, p_id in product_map.items():
        if item['name'][:3] in p_name or p_name[:3] in item['name']:
            match_id = p_id
            break
            
    if match_id:
        sql_lines.append(f"INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date) VALUES ('TAIPEI', {match_id}, {item['qty']}, {item['expiry']});")

with open(OUTPUT_SQL, 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_lines))

print(f"SQL generated: {OUTPUT_SQL}")
