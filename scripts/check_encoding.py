import chardet
import os

folder = r'X:\gemini\linebot-warehouse-manager\unstructured-data'
for filename in os.listdir(folder):
    if filename.endswith('.txt'):
        path = os.path.join(folder, filename)
        with open(path, 'rb') as f:
            rawdata = f.read()
            result = chardet.detect(rawdata)
            print(f"File: {filename}, Encoding: {result['encoding']}, Confidence: {result['confidence']}")
