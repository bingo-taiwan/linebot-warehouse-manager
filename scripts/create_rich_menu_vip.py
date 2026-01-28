from PIL import Image, ImageDraw, ImageFont
import os

# Rich Menu Size
WIDTH = 2500
HEIGHT = 1686

# Colors
BG_COLOR = (255, 250, 240) # 稍微不同的底色以區分 VIP
BORDER_COLOR = (200, 200, 200)
TEXT_COLOR = (50, 50, 50)
HIGHLIGHT_COLOR = (255, 140, 0) # 公關取貨用橘色

def create_rich_menu_image(output_path):
    # Create background
    img = Image.new('RGB', (WIDTH, HEIGHT), color=BG_COLOR)
    draw = ImageDraw.Draw(img)
    
    # Draw Grid (3x2)
    col_w = WIDTH // 3
    row_h = HEIGHT // 2
    
    # Horizontal line
    draw.line([(0, row_h), (WIDTH, row_h)], fill=BORDER_COLOR, width=10)
    # Vertical lines
    draw.line([(col_w, 0), (col_w, HEIGHT)], fill=BORDER_COLOR, width=10)
    draw.line([(col_w * 2, 0), (col_w * 2, HEIGHT)], fill=BORDER_COLOR, width=10)
    
    # Text helper
    def draw_centered_text(text, box, font_size=90, color=TEXT_COLOR):
        try:
            font = ImageFont.truetype("msjh.ttc", font_size) 
        except:
            font = ImageFont.load_default()
            
        x0, y0, x1, y1 = box
        w, h = x1 - x0, y1 - y0
        
        bbox = draw.textbbox((0, 0), text, font=font)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
        
        draw.text((x0 + (w - tw) / 2, y0 + (h - th) / 2), text, font=font, fill=color)

    # Label areas
    draw_centered_text("大園庫存", (0, 0, col_w, row_h))
    draw_centered_text("台北庫存", (col_w, 0, col_w * 2, row_h))
    draw_centered_text("🏢公關取貨", (col_w * 2, 0, WIDTH, row_h), color=HIGHLIGHT_COLOR)
    
    draw_centered_text("新品入庫", (0, row_h, col_w, HEIGHT))
    draw_centered_text("下單大園", (col_w, row_h, col_w * 2, HEIGHT))
    draw_centered_text("福利品(2W)", (col_w * 2, row_h, WIDTH, HEIGHT))
    
    img.save(output_path)
    print(f"Image saved to {output_path}")

if __name__ == "__main__":
    create_rich_menu_image("rich_menu_vip.png")
