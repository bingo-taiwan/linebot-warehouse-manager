import sys
import os
from PIL import Image

# 用法: python process_image.py <input_path> <output_dir> <filename_base>

def process_image(input_path, output_dir, base_name):
    try:
        img = Image.open(input_path)
        
        # 1. 處理主圖 (限制寬度 1024)
        main_img = img.copy()
        if main_img.width > 1024:
            ratio = 1024 / main_img.width
            new_height = int(main_img.height * ratio)
            main_img = main_img.resize((1024, new_height), Image.Resampling.LANCZOS)
            
        main_filename = f"{base_name}.jpg"
        main_path = os.path.join(output_dir, main_filename)
        # 轉為 RGB 避免 PNG 透明度問題，並壓縮
        main_img.convert('RGB').save(main_path, "JPEG", quality=85)
        
        # 2. 處理縮圖 (限制寬度 240)
        thumb_img = img.copy()
        if thumb_img.width > 240:
            ratio = 240 / thumb_img.width
            new_height = int(thumb_img.height * ratio)
            thumb_img = thumb_img.resize((240, new_height), Image.Resampling.LANCZOS)
            
        thumb_filename = f"{base_name}_sm.jpg"
        thumb_path = os.path.join(output_dir, thumb_filename)
        thumb_img.convert('RGB').save(thumb_path, "JPEG", quality=85)
        
        print(f"SUCCESS|{main_filename}|{thumb_filename}")
        
    except Exception as e:
        print(f"ERROR|{str(e)}")

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("ERROR|Missing arguments")
    else:
        process_image(sys.argv[1], sys.argv[2], sys.argv[3])
