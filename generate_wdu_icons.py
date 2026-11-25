import math
from PIL import Image, ImageDraw, ImageFont

def create_wdu_icon(size):
    # Create image with RGBA
    img = Image.new('RGBA', (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    
    # Dimensions
    pad = int(size * 0.05)
    r = int(size * 0.22) # squircle corner radius
    
    # 1. Background Rounded Squircle with subtle gradient simulation
    # Deep royal blue to academic navy (#1e3a8a -> #1d4ed8)
    for i in range(pad, size - pad):
        factor = (i - pad) / float(size - 2 * pad)
        # Interpolate between deep navy (15, 23, 42) to bright royal blue (29, 78, 216)
        r_c = int(20 + factor * 15)
        g_c = int(45 + factor * 45)
        b_c = int(140 + factor * 85)
        draw.rounded_rectangle(
            [pad, pad, size - pad, size - pad],
            radius=r,
            fill=(r_c, g_c, b_c, 255)
        )
    
    # Outer gold/white subtle border
    draw.rounded_rectangle(
        [pad, pad, size - pad, size - pad],
        radius=r,
        outline=(245, 158, 11, 220), # Ethiopian gold
        width=max(2, int(size * 0.02))
    )

    # Inner subtle glow ring
    inner_pad = pad + max(2, int(size * 0.02)) + 2
    draw.rounded_rectangle(
        [inner_pad, inner_pad, size - inner_pad, size - inner_pad],
        radius=int(r * 0.9),
        outline=(255, 255, 255, 60),
        width=max(1, int(size * 0.008))
    )

    cx = size // 2
    cy = int(size * 0.52)
    s = size / 512.0 # scaling factor based on 512px canvas

    # 2. Golden Arch / Knowledge Rays (Star at top)
    star_cy = int(cy - 125 * s)
    star_r = int(14 * s)
    # Draw central gold star
    draw.ellipse([cx - star_r, star_cy - star_r, cx + star_r, star_cy + star_r], fill=(245, 158, 11, 255))
    # Radiating points
    for angle in [0, 45, 90, 135, 180, 225, 270, 315]:
        rad = math.radians(angle)
        x1 = cx + math.cos(rad) * (star_r + 4 * s)
        y1 = star_cy + math.sin(rad) * (star_r + 4 * s)
        x2 = cx + math.cos(rad) * (star_r + 12 * s)
        y2 = star_cy + math.sin(rad) * (star_r + 12 * s)
        draw.line([(x1, y1), (x2, y2)], fill=(251, 191, 36, 230), width=max(2, int(3 * s)))

    # 3. Academic Open Book (Symbol of University Library)
    # Book spine center
    spine_x = cx
    spine_top = cy - int(45 * s)
    spine_bottom = cy + int(70 * s)
    
    # Left page & Right page polygon
    # Left page
    left_top_outer = (cx - int(120 * s), cy - int(40 * s))
    left_top_ctrl = (cx - int(60 * s), cy - int(65 * s))
    left_bot_outer = (cx - int(120 * s), cy + int(50 * s))
    left_bot_ctrl = (cx - int(60 * s), cy + int(25 * s))

    # Right page
    right_top_outer = (cx + int(120 * s), cy - int(40 * s))
    right_top_ctrl = (cx + int(60 * s), cy - int(65 * s))
    right_bot_outer = (cx + int(120 * s), cy + int(50 * s))
    right_bot_ctrl = (cx + int(60 * s), cy + int(25 * s))

    # Left Page Fill (White with slight gradient/shading)
    left_poly = [
        (spine_x, spine_top),
        left_top_ctrl,
        left_top_outer,
        left_bot_outer,
        left_bot_ctrl,
        (spine_x, spine_bottom)
    ]
    draw.polygon(left_poly, fill=(255, 255, 255, 255))

    # Right Page Fill
    right_poly = [
        (spine_x, spine_top),
        right_top_ctrl,
        right_top_outer,
        right_bot_outer,
        right_bot_ctrl,
        (spine_x, spine_bottom)
    ]
    draw.polygon(right_poly, fill=(248, 250, 252, 255))

    # Book outlines
    draw.line(left_poly + [(spine_x, spine_top)], fill=(203, 213, 225, 255), width=max(2, int(3 * s)))
    draw.line(right_poly + [(spine_x, spine_top)], fill=(203, 213, 225, 255), width=max(2, int(3 * s)))
    # Central spine line
    draw.line([(spine_x, spine_top), (spine_x, spine_bottom)], fill=(245, 158, 11, 255), width=max(2, int(4 * s)))

    # Lines representing text on the pages
    for offset_y in [ -10, 8, 26 ]:
        y = cy + int(offset_y * s)
        # Left page lines
        draw.line([(cx - int(95 * s), y - int(12 * s)), (cx - int(25 * s), y - int(6 * s))], fill=(148, 163, 184, 220), width=max(1, int(2.5 * s)))
        # Right page lines
        draw.line([(cx + int(25 * s), y - int(6 * s)), (cx + int(95 * s), y - int(12 * s))], fill=(148, 163, 184, 220), width=max(1, int(2.5 * s)))

    # 4. Under Book Ribbon / Banner with "WDU"
    banner_w = int(90 * s)
    banner_h = int(24 * s)
    banner_y = spine_bottom + int(12 * s)
    draw.rounded_rectangle(
        [cx - banner_w, banner_y, cx + banner_w, banner_y + banner_h],
        radius=int(6 * s),
        fill=(245, 158, 11, 255), # Gold banner
        outline=(255, 255, 255, 200),
        width=max(1, int(1.5 * s))
    )

    # 5. Clean WDU Text on Ribbon
    try:
        font_size = int(16 * s)
        font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", font_size)
    except:
        font = ImageFont.load_default()

    text = "WDU"
    try:
        bbox = draw.textbbox((0, 0), text, font=font)
        tw = bbox[2] - bbox[0]
        th = bbox[3] - bbox[1]
    except:
        tw, th = 30 * s, 10 * s
    draw.text((cx - tw / 2, banner_y + (banner_h - th) / 2 - 1), text, fill=(15, 23, 42, 255), font=font)

    return img

# Generate icons
for size in [512, 192]:
    icon = create_wdu_icon(size)
    icon.save(f"public/icons/icon-{size}x{size}.png", "PNG")
    print(f"Generated public/icons/icon-{size}x{size}.png ({size}x{size})")

# Generate apple touch icon
icon_180 = create_wdu_icon(180)
icon_180.save("public/icons/apple-touch-icon.png", "PNG")
print("Generated public/icons/apple-touch-icon.png")

# Generate favicon.ico (multi-resolution 16, 32, 48)
icon_48 = create_wdu_icon(48)
icon_32 = create_wdu_icon(32)
icon_16 = create_wdu_icon(16)
icon_48.save("public/favicon.ico", format="ICO", sizes=[(16, 16), (32, 32), (48, 48)])
print("Generated public/favicon.ico")
