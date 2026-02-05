import re

inventory_file = r"X:\gemini\linebot-warehouse-manager\unstructured-data\inventory_utf8.txt"

def extract_categories(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        text = f.read()

    categories = ['包材', '雜項']
    results = {}
    
    for cat in categories:
        # Find content between <Category> and next <...> or End
        pattern = f"<{cat}>(.*?)(?=<|$)"
        match = re.search(pattern, text, re.DOTALL)
        if match:
            block = match.group(1)
            # Extract names
            names = re.findall(r'品名：(.*?)(?:\n|$)', block)
            results[cat] = [n.strip().replace('✅', '') for n in names if n.strip()]
            
    return results

data = extract_categories(inventory_file)
for cat, names in data.items():
    print(f"--- {cat} ---")
    for n in names:
        print(n)