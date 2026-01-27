from PIL import Image, ImageDraw, ImageFont
import os

# Rich Menu Size
WIDTH = 2500
HEIGHT = 1686

# Colors
BG_COLOR = (240, 240, 240)
BORDER_COLOR = (200, 200, 200)
TEXT_COLOR = (50, 50, 50)

def create_rich_menu_image(output_path):
    # Create background
    img = Image.new('RGB', (WIDTH, HEIGHT), color=BG_COLOR)
    draw = ImageDraw.Draw(img)
    
    # Draw Grid
    # Horizontal line
    draw.line([(0, HEIGHT // 2), (WIDTH, HEIGHT // 2)], fill=BORDER_COLOR, width=10)
    # Vertical lines
    draw.line([(WIDTH // 2, 0), (WIDTH // 2, HEIGHT // 2)], fill=BORDER_COLOR, width=10)
    draw.line([(WIDTH // 3, HEIGHT // 2), (WIDTH // 3, HEIGHT)], fill=BORDER_COLOR, width=10)
    draw.line([(WIDTH * 2 // 3, HEIGHT // 2), (WIDTH * 2 // 3, HEIGHT)], fill=BORDER_COLOR, width=10)
    
    # Text helper
    def draw_centered_text(text, box, font_size=100):
        # Fallback to default font if custom font not found
        try:
            # Try to find a CJK font on Windows
            font = ImageFont.truetype("msjh.ttc", font_size) # Microsoft JhengHei
        except:
            font = ImageFont.load_default()
            
        x0, y0, x1, y1 = box
        w, h = x1 - x0, y1 - y0
        
        # Get text size
        bbox = draw.textbbox((0, 0), text, font=font)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
        
        draw.text((x0 + (w - tw) / 2, y0 + (h - th) / 2), text, font=font, fill=TEXT_COLOR)

    # Label areas
    draw_centered_text("大園庫存", (0, 0, 1250, 843))
    draw_centered_text("台北庫存", (1250, 0, 2500, 843))
    draw_centered_text("新品入庫", (0, 843, 833, 1686), font_size=80)
    draw_centered_text("下單大園", (833, 843, 1666, 1686), font_size=80)
    draw_centered_text("福利品", (1666, 843, 2500, 1686), font_size=80)
    
    img.save(output_path)
    print(f"Image saved to {output_path}")

if __name__ == "__main__":
    create_rich_menu_image("rich_menu_admin.png")
