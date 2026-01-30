import re
import os

def parse_dayuan(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Normalize newlines
    content = content.replace('\r\n', '\n')
    
    # Split by double newlines or looks like a new block
    # It seems blocks are separated by empty lines
    blocks = re.split(r'\n\s*\n', content)
    
    data = []
    
    for block in blocks:
        lines = [l.strip() for l in block.split('\n') if l.strip()]
        if not lines:
            continue
            
        item = {}
        # Simple heuristic: First line is name (maybe with "品名：")
        name_line = lines[0]
        if "品名：" in name_line:
            item['name'] = name_line.split("品名：")[1].strip()
        else:
            item['name'] = name_line.strip()
            
        # Default category
        if "外盒" in item['name']:
            item['category'] = '包材'
        else:
            item['category'] = '產品'

        # Look for other fields in subsequent lines
        for line in lines[1:]:
            if "規格" in line:
                item['spec'] = line.replace("規格：", "").strip()
            elif "數量" in line and ("包/箱" in line or "瓶/箱" in line):
                # Try to extract unit_per_case
                # ex: 數量：750包/箱 -> 750
                match = re.search(r'(\d+)', line)
                if match:
                    item['unit_per_case'] = int(match.group(1))
            elif "生產日" in line or "製造日期" in line:
                # ex: 生產日：2025.12.30
                match = re.search(r'(\d{4}[./]\d{1,2}[./]\d{1,2})', line)
                if match:
                    item['production_date'] = match.group(1).replace('.', '-')
                else:
                     # Try compact format 20280512
                    match = re.search(r'(\d{8})', line)
                    if match:
                        d = match.group(1)
                        item['production_date'] = f"{d[:4]}-{d[4:6]}-{d[6:]}"

            elif "到期日" in line or "有效日期" in line or "有效期限" in line:
                match = re.search(r'(\d{4}[./]\d{1,2}[./]\d{1,2})', line)
                if match:
                    item['expiry_date'] = match.group(1).replace('.', '-')
                else:
                    match = re.search(r'(\d{8})', line)
                    if match:
                        d = match.group(1)
                        item['expiry_date'] = f"{d[:4]}-{d[4:6]}-{d[6:]}"

            elif "共" in line or "一共" in line:
                # ex: 一共23箱 or 共1箱 or 一共18箱+1小箱
                # We need to be careful. For now, let's extract the first number before "箱"
                match = re.search(r'(\d+)\s*箱', line)
                if match:
                    item['case_count'] = int(match.group(1))
                else:
                    item['case_count'] = 0 # Default if parse fail
                    
        # Fallbacks/Cleanup
        if 'unit_per_case' not in item:
            # Try to find lines like "220包/箱"
            for line in lines:
                if "包/箱" in line or "瓶/箱" in line or "盒/箱" in line:
                     match = re.search(r'(\d+)', line)
                     if match:
                         item['unit_per_case'] = int(match.group(1))
                         break
            if 'unit_per_case' not in item:
                item['unit_per_case'] = 1 # Default

        data.append(item)
    return data

def parse_taipei(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        lines = f.readlines()
        
    data = []
    current_category = '產品'
    
    for line in lines:
        line = line.strip()
        if not line:
            continue
            
        if line in ["產品", "雜項", "包材"]:
            current_category = line
            continue
            
        if "品名：" in line:
            item = {'category': current_category}
            parts = line.split(" ") # This might be fragile if spaces are irregular
            # Better strategy: Split by known keys
            
            # Extract Name
            # Assuming format: 品名：XXX 規格：YYY 數量：ZZZ ...
            # Regex might be safer
            
            name_match = re.search(r'品名：(.*?)(\s+規格：|\s+數量：|$)', line)
            if name_match:
                item['name'] = name_match.group(1).strip()
                
            spec_match = re.search(r'規格：(.*?)(\s+數量：|$)', line)
            if spec_match:
                item['spec'] = spec_match.group(1).strip()
            else:
                item['spec'] = '' # Some items like masks might not have spec
                
            qty_match = re.search(r'數量：(.*?)(\s+到期日：|\s+有效日期：|\s+有效期限：|\s+到期日期：|$)', line)
            if qty_match:
                item['raw_qty'] = qty_match.group(1).strip()
                
            date_match = re.search(r'(到期日：|有效日期：|有效期限：|到期日期：)(.*)', line)
            if date_match:
                date_str = date_match.group(2).strip()
                # Parse date 2027.09.11
                d_match = re.search(r'(\d{4}[./]\d{1,2}[./]\d{1,2})', date_str)
                if d_match:
                    item['expiry_date'] = d_match.group(1).replace('.', '-')
                else:
                    # check for YYYY.MM format
                    d_match_short = re.search(r'(\d{4}[./]\d{1,2})', date_str)
                    if d_match_short:
                         item['expiry_date'] = d_match_short.group(1).replace('.', '-') + "-01" # Default to 1st of month

            data.append(item)
            
    return data

def generate_sql(dayuan_data, taipei_data):
    sql = []
    sql.append("-- Import Script Generated by Python")
    
    # Process Dayuan Data
    sql.append("-- Dayuan Data")
    for item in dayuan_data:
        if not item.get('name'): continue
        
        name = item.get('name').replace("'", "\'")
        cat = item.get('category', '產品')
        spec = item.get('spec', '').replace("'", "\'")
        upc = item.get('unit_per_case', 1)
        
        # Insert Product if not exists (using name as key for simplicity)
        # Note: In real world, might need better dedup. 
        # Here we use ON DUPLICATE KEY UPDATE to ensure we capture the latest spec/category info
        sql.append(f"INSERT INTO products (name, category, spec, unit_per_case) VALUES ('{name}', '{cat}', '{spec}', {upc}) ON DUPLICATE KEY UPDATE category='{cat}', spec='{spec}', unit_per_case={upc};")
        
        # Insert Stock
        # We need to get the product ID first. Since we can't easily do variables in bulk SQL without procedures, 
        # we will use subquery for product_id
        
        case_count = item.get('case_count', 0)
        prod_date = f"'{item.get('production_date')}'" if item.get('production_date') else "NULL"
        exp_date = f"'{item.get('expiry_date')}'" if item.get('expiry_date') else "NULL"
        
        sql.append(f"INSERT INTO stocks (warehouse_id, product_id, case_count, unit_count, production_date, expiry_date, note) SELECT 'DAYUAN', id, {case_count}, 0, {prod_date}, {exp_date}, 'Initial Import' FROM products WHERE name = '{name}';")

    # Process Taipei Data
    sql.append("-- Taipei Data")
    for item in taipei_data:
        if not item.get('name'): continue
        
        name = item.get('name').replace("'", "\'")
        cat = item.get('category', '產品')
        spec = item.get('spec', '').replace("'", "\'")
        raw_qty = item.get('raw_qty', '').replace("'", "\'")
        
        # Insert/Update Product (Taipei might have items Dayuan doesn't, or updated specs)
        sql.append(f"INSERT INTO products (name, category, spec) VALUES ('{name}', '{cat}', '{spec}') ON DUPLICATE KEY UPDATE category='{cat}', spec='{spec}';")
        
        exp_date = f"'{item.get('expiry_date')}'" if item.get('expiry_date') else "NULL"
        
        # Insert Stock - Quantity is 0, Note contains raw quantity
        # Using subquery for product_id
        sql.append(f"INSERT INTO stocks (warehouse_id, product_id, case_count, unit_count, expiry_date, note) SELECT 'TAIPEI', id, 0, 0, {exp_date}, '{raw_qty}' FROM products WHERE name = '{name}';")

    return "\n".join(sql)

# Execution
dayuan_file = r"X:\gemini\linebot-warehouse-manager\unstructured-data\大園倉庫 20260119盤點.txt"
taipei_file = r"X:\gemini\linebot-warehouse-manager\unstructured-data\台北倉庫2026.01.26盤點.txt"

d_data = parse_dayuan(dayuan_file)
t_data = parse_taipei(taipei_file)

sql_output = generate_sql(d_data, t_data)

with open(r"X:\gemini\linebot-warehouse-manager\scripts\import_inventory_full.sql", "w", encoding='utf-8') as f:
    f.write(sql_output)

print("SQL generated at X:\\gemini\\linebot-warehouse-manager\\scripts\\import_inventory_full.sql")
