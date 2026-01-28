from PIL import Image, ImageDraw, ImageFont
import os

# Rich Menu Size
WIDTH = 2500
HEIGHT = 1686

# Colors
BG_COLOR = (245, 245, 245)
BORDER_COLOR = (200, 200, 200)
TEXT_COLOR = (50, 50, 50)

def create_rich_menu_image(output_path):
    # Create background
    img = Image.new('RGB', (WIDTH, HEIGHT), color=BG_COLOR)
    draw = ImageDraw.Draw(img)
    
    # Draw Grid (2x2)
    # Horizontal line
    draw.line([(0, HEIGHT // 2), (WIDTH, HEIGHT // 2)], fill=BORDER_COLOR, width=10)
    # Vertical line
    draw.line([(WIDTH // 2, 0), (WIDTH // 2, HEIGHT)], fill=BORDER_COLOR, width=10)
    
    # Text helper
    def draw_centered_text(text, box, font_size=100):
        try:
            font = ImageFont.truetype("msjh.ttc", font_size) 
        except:
            font = ImageFont.load_default()
            
        x0, y0, x1, y1 = box
        w, h = x1 - x0, y1 - y0
        
        bbox = draw.textbbox((0, 0), text, font=font)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
        
        draw.text((x0 + (w - tw) / 2, y0 + (h - th) / 2), text, font=font, fill=TEXT_COLOR)

    # Label areas
    draw_centered_text("查詢效期品", (0, 0, 1250, 843))
    draw_centered_text("台北庫存", (1250, 0, 2500, 843))
    draw_centered_text("訂補大園貨", (0, 843, 1250, 1686))
    draw_centered_text("福利品自選", (1250, 843, 2500, 1686))
    
    img.save(output_path)
    print(f"Image saved to {output_path}")

if __name__ == "__main__":
    create_rich_menu_image("rich_menu_office.png")
