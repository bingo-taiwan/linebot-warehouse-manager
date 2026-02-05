import re
import json

# Updated Database Products
db_products = [
    {"id": 1, "name": "甲足飽裸包", "category": "產品", "spec": "20g/包", "unit_per_case": 750},
    {"id": 2, "name": "甲足飽盒裝", "category": "產品", "spec": "20g/包", "unit_per_case": 22},
    {"id": 3, "name": "泰享瘦咖啡盒裝（過期）", "category": "產品", "spec": "12g/包", "unit_per_case": 22},
    {"id": 4, "name": "泰享瘦咖啡盒裝", "category": "產品", "spec": "12g/包", "unit_per_case": 22},
    {"id": 5, "name": "MPC囥糖（鋁箔包）", "category": "產品", "spec": "10粒/片", "unit_per_case": 220},
    {"id": 6, "name": "IM99免ㄧ利（鋁箔包）", "category": "產品", "spec": "10粒/片", "unit_per_case": 220},
    {"id": 7, "name": "靚顏悅色膠囊（鋁箔包）", "category": "產品", "spec": "10粒/片", "unit_per_case": 193},
    {"id": 8, "name": "靚顏悅色膠囊（鋁箔包）", "category": "產品", "spec": "10粒/片", "unit_per_case": 300},
    {"id": 9, "name": "NMN+繁腦還童", "category": "產品", "spec": "10粒/片", "unit_per_case": 285},
    {"id": 10, "name": "好嚐道益生菌（過期）", "category": "產品", "spec": "5克/包", "unit_per_case": 63},
    {"id": 11, "name": "魔幻奇蹟霜100mL", "category": "產品", "spec": "100mL/瓶", "unit_per_case": 72},
    {"id": 12, "name": "透妍光彩膠原蛋白（裸包）", "category": "產品", "spec": "5g/包", "unit_per_case": 2000},
    {"id": 13, "name": "肌優骨（鋁箔包）", "category": "產品", "spec": "10粒/片", "unit_per_case": 300},
    {"id": 14, "name": "好嚐道益生菌（裸包）", "category": "產品", "spec": "5g/包", "unit_per_case": 2200},
    {"id": 15, "name": "好嚐道益生菌（裸包）", "category": "產品", "spec": "5g/包", "unit_per_case": 4500},
    {"id": 16, "name": "泰纖身（鋁箔包）", "category": "產品", "spec": "10粒/片", "unit_per_case": 285},
    {"id": 17, "name": "果燃有酵（裸包）", "category": "產品", "spec": "15g/包", "unit_per_case": 700},
    {"id": 18, "name": "女王豐（盒裝）", "category": "產品", "spec": "10粒/片", "unit_per_case": 66},
    {"id": 19, "name": "海洋肽", "category": "產品", "spec": "45mL/瓶", "unit_per_case": 126},
    {"id": 20, "name": "薰衣草噴霧", "category": "產品", "spec": "30mL/瓶", "unit_per_case": 125},
    {"id": 21, "name": "貓頭鷹外盒", "category": "包材", "spec": "", "unit_per_case": 108},
    {"id": 22, "name": "泰纖身外盒", "category": "包材", "spec": "", "unit_per_case": 108},
    {"id": 23, "name": "好嚐道益生菌外盒", "category": "包材", "spec": "", "unit_per_case": 84},
    {"id": 24, "name": "透妍光采膠原蛋白外盒", "category": "包材", "spec": "", "unit_per_case": 60},
    {"id": 25, "name": "靚顏悅色膠囊外盒", "category": "包材", "spec": "", "unit_per_case": 117},
    {"id": 26, "name": "囥糖外盒", "category": "包材", "spec": "", "unit_per_case": 105},
    {"id": 27, "name": "IM99免ㄧ利外盒", "category": "包材", "spec": "", "unit_per_case": 108},
    {"id": 28, "name": "肌優骨外盒", "category": "包材", "spec": "", "unit_per_case": 108},
    {"id": 29, "name": "果燃有酵外盒", "category": "包材", "spec": "", "unit_per_case": 84},
    {"id": 30, "name": "果燃有酵外盒", "category": "包材", "spec": "", "unit_per_case": 72},
    {"id": 31, "name": "雪肌膠原（Eddie)外盒", "category": "包材", "spec": "", "unit_per_case": 84},
    {"id": 32, "name": "果然薈酵素（Eddie)外盒", "category": "包材", "spec": "", "unit_per_case": 84},
    {"id": 33, "name": "亮白皙外盒（Eddie)外盒", "category": "包材", "spec": "", "unit_per_case": 120},
    {"id": 34, "name": "弧形半圓沙發", "category": "雜項", "spec": "", "unit_per_case": 1},
    {"id": 35, "name": "貓頭鷹", "category": "產品", "spec": "10粒/片", "unit_per_case": 1},
    {"id": 36, "name": "瘦瘦菌", "category": "福利品", "spec": "10粒/片", "unit_per_case": 1},
    {"id": 37, "name": "Happy Go 組合包", "category": "產品", "spec": "體驗組", "unit_per_case": 1},
    {"id": 38, "name": "女王豐外盒", "category": "包材", "spec": "個", "unit_per_case": 1},
    {"id": 39, "name": "口罩", "category": "雜項", "spec": "個", "unit_per_case": 1},
    {"id": 40, "name": "氫水杯", "category": "雜項", "spec": "個", "unit_per_case": 1},
    {"id": 41, "name": "體驗包", "category": "雜項", "spec": "包", "unit_per_case": 1}
]

# Alias Map
ALIAS_MAP = {
    "免一利": "免ㄧ利",
    "光采": "光彩",
    "海洋精華胜肽": "海洋肽",
    "貓頭鷹(裸片)": "貓頭鷹", 
    "貓頭鷹盒裝": "貓頭鷹",
    "貓頭鷹": "貓頭鷹", # Explicit
    "Happy Go": "Happy Go 組合包",
    "瘦瘦菌": "瘦瘦菌", # Explicit
    "女王豐外盒": "女王豐外盒",
    "貓頭鷹外盒": "貓頭鷹外盒",
    "肌優骨外盒": "肌優骨外盒",
    "果燃有酵外盒": "果燃有酵外盒",
    "果然薈酵素": "果然薈酵素",
    "口罩": "口罩",
    "氫水杯": "氫水杯",
    "體驗包": "體驗包"
}

inventory_file = r"X:\gemini\linebot-warehouse-manager\unstructured-data\inventory_utf8.txt"
sql_output_file = r"X:\gemini\linebot-warehouse-manager\scripts\final_import.sql"

def parse_inventory(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        text = f.read()

    items = []
    blocks = re.split(r'\n(?=品名：)', text)
    
    for block in blocks:
        if not block.strip() or "品名：" not in block:
            continue
            
        item = {"raw": block.strip()}
        
        name_match = re.search(r'品名：(.*?)(?:\n|$)', block)
        if name_match:
            item['name'] = name_match.group(1).strip().replace('✅', '')
        
        spec_match = re.search(r'規格：(.*?)(?:\n|$)', block)
        if spec_match:
            item['spec'] = spec_match.group(1).strip()
            
        qty_match = re.search(r'數量：(.*?)(?:\n|$)', block)
        if qty_match:
            item['qty_raw'] = qty_match.group(1).strip()
        
        # Extract Expiry (Optional, nice to have)
        exp_match = re.search(r'效期：(.*?)(?:\n|$)', block)
        if exp_match:
            # Try to standardize date to YYYY-MM-DD
            exp_str = exp_match.group(1).strip()
            date_match = re.search(r'(\d{4})\.(\d{2})(?:\.(\d{2}))?', exp_str)
            if date_match:
                y, m, d = date_match.groups()
                item['expiry'] = f"{y}-{m}-{d if d else '01'}"
            
        items.append(item)
    return items

def normalize_name(name):
    name = name.replace('（', '(').replace('）', ')')
    name = name.replace('(新包裝)', '').replace('(舊)', '')
    name = name.replace('盒裝', '').replace('裸包', '').replace('鋁箔包', '').replace('裸片', '')
    name = name.replace('裸條', '')
    
    # Check for direct alias match first
    for alias, target in ALIAS_MAP.items():
        if alias == name: # Exact alias match
            return target
            
    # Then substring match
    for alias, target in ALIAS_MAP.items():
        if alias in name:
            name = name.replace(alias, target)
            
    return name.strip()

def parse_conversion_rate(spec_str):
    if not spec_str:
        return 1
    match = re.search(r'(\d+)[\u4e00-\u9fa5]+/[盒包]', spec_str)
    if match:
        return int(match.group(1))
    return 1

def parse_quantity(qty_str):
    """
    解析數量:
    "750包*0.9" -> 675, "包"
    "32 包" -> 32, "包"
    """
    clean_qty = re.sub(r'\(.*?\)', '', qty_str) # Remove comments in parens
    
    # Check for multiplication: "750包*0.9"
    mult_match = re.search(r'(\d+)\s*([^\d\s\*]+)\s*\*\s*([\d\.]+)', clean_qty)
    if mult_match:
        base_val = float(mult_match.group(1))
        unit = mult_match.group(2)
        multiplier = float(mult_match.group(3))
        return base_val * multiplier, unit
        
    # Standard: "138 盒", "1.1 箱"
    match = re.search(r'([\d\.]+)\s*([箱盒包片捆組瓶個])', clean_qty)
    if match:
        return float(match.group(1)), match.group(2)
        
    return 0, "unknown"

def find_match(inv_item):
    inv_name = inv_item.get('name', '')
    norm_inv_name = normalize_name(inv_name)
    
    # 1. Exact Match (Normalized)
    for p in db_products:
        norm_db_name = normalize_name(p['name'])
        if norm_inv_name == norm_db_name:
            return p, "Exact"
            
    # 2. Fuzzy Match
    potential_matches = []
    for p in db_products:
        norm_db_name = normalize_name(p['name'])
        if norm_inv_name in norm_db_name or norm_db_name in norm_inv_name:
             potential_matches.append(p)
    
    if len(potential_matches) == 1:
        return potential_matches[0], "Fuzzy"
    elif len(potential_matches) > 1:
        if "盒" in inv_name:
            for pm in potential_matches:
                if "盒" in pm['name']:
                    return pm, "Fuzzy (Box)"
        if "鋁箔" in inv_name or "裸" in inv_name:
             for pm in potential_matches:
                if "鋁箔" in pm['name'] or "裸" in pm['name']:
                    return pm, "Fuzzy (Loose)"
        return potential_matches, "Ambiguous"
        
    return None, "No Match"

def main():
    items = parse_inventory(inventory_file)
    sql_statements = []
    
    print("| 盤點品名 | 原始數量 | DB 品名 | 入庫數量 (Unit) | 備註 |")
    print("|---|---|---|---|---|")
    
    sql_statements.append("TRUNCATE TABLE stocks; -- Clear old stocks (CAREFUL!)")
    # Actually, maybe we only want to clear TAIPEI stocks?
    # But for now, let's assume we are resetting TAIPEI only.
    sql_statements.append("DELETE FROM stocks WHERE warehouse_id = 'TAIPEI';")

    for item in items:
        match, status = find_match(item)
        
        inv_name = item.get('name', '')
        qty_raw = item.get('qty_raw', '')
        spec_raw = item.get('spec', '')
        expiry = item.get('expiry', '2027-12-31') # Default expiry if missing
        
        qty_val, qty_unit = parse_quantity(qty_raw)
        
        final_qty = 0
        db_id = 0
        db_name = "UNKNOWN"
        
        if isinstance(match, dict):
            db_id = match['id']
            db_name = match['name']
            
            # Special Rules (Hardcoded)
            if "8條/包" in inv_name or "8包/組" in inv_name:
                final_qty = qty_val * 8
            elif "25條/包" in inv_name or "25包/組" in inv_name:
                final_qty = qty_val * 25
            elif "箱" in qty_unit:
                # Use DB unit_per_case
                # Special case: 好嚐道 0.9 箱 (DB 14 or 15?)
                if match['unit_per_case']:
                    final_qty = qty_val * match['unit_per_case']
                else:
                    final_qty = qty_val # Fallback
            elif "盒" in qty_unit:
                # Use spec parsing
                rate = parse_conversion_rate(spec_raw)
                final_qty = qty_val * rate
            else:
                # Assuming base unit matches (包=包, 片=片, 捆=10片?)
                if "捆" in qty_unit:
                    final_qty = qty_val * 10 # Assumption based on "10片1捆" text
                elif "組" in qty_unit:
                     final_qty = qty_val # 1組 = 1 unit for some items?
                else:
                    final_qty = qty_val

            # Rounding
            final_qty = round(final_qty)
            
            if final_qty > 0:
                # Generate SQL
                # Assuming all are 'unit_count', case_count = 0 for Taipei (Scatter out)
                sql = f"INSERT INTO stocks (warehouse_id, product_id, case_count, unit_count, expiry_date, is_expired) VALUES ('TAIPEI', {db_id}, 0, {final_qty}, '{expiry}', 0);"
                sql_statements.append(sql)

        print(f"| {inv_name} | {qty_raw} | {db_name} | {final_qty} | {status} |")
        
    with open(sql_output_file, 'w', encoding='utf-8') as f:
        f.write("\n".join(sql_statements))
    print(f"\nSaved SQL to {sql_output_file}")

if __name__ == "__main__":
    main()