import sys
import os

source_file = r"X:\gemini\linebot-warehouse-manager\unstructured-data\台北倉庫20260126盤點.txt"
dest_file = r"X:\gemini\linebot-warehouse-manager\unstructured-data\inventory_utf8.txt"

encodings_to_try = ['cp950', 'big5', 'utf-8', 'utf-16']

content = None
success_encoding = None

if not os.path.exists(source_file):
    print(f"Error: File not found at {source_file}")
    sys.exit(1)

with open(source_file, 'rb') as f:
    raw_data = f.read()

for enc in encodings_to_try:
    try:
        decoded = raw_data.decode(enc)
        print(f"Successfully decoded with {enc}")
        content = decoded
        success_encoding = enc
        break
    except UnicodeDecodeError:
        continue

if content:
    print("--- Preview (First 500 chars) ---")
    print(content[:500])
    print("--- End Preview ---")
    
    with open(dest_file, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Saved UTF-8 content to {dest_file}")
else:
    print("Failed to decode file with standard Traditional Chinese encodings.")