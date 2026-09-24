"""Generate simple placeholder deal images for the local demo."""
import os
from PIL import Image, ImageDraw, ImageFont

OUT = "/var/lib/freelancer/projects/40728457/demo-images"
os.makedirs(OUT, exist_ok=True)

DEALS = [
    ("airport-transfer.jpg", (15, 76, 92), (28, 138, 145), "AIRPORT", "TRANSFER"),
    ("island-day-tour.jpg", (176, 98, 26), (219, 152, 61), "ISLAND", "DAY TOUR"),
    ("suv-weekly-hire.jpg", (32, 58, 96), (66, 105, 158), "SUV", "WEEKLY HIRE"),
    ("sunset-city-tour.jpg", (110, 38, 74), (176, 78, 115), "SUNSET", "CITY TOUR"),
]

FONT_PATHS = [
    "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
    "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
]


def font(size):
    for p in FONT_PATHS:
        if os.path.exists(p):
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


W, H = 1200, 750

for name, c1, c2, line1, line2 in DEALS:
    img = Image.new("RGB", (W, H), c1)
    draw = ImageDraw.Draw(img)

    # Diagonal gradient.
    for y in range(H):
        t = y / H
        colour = tuple(int(c1[i] + (c2[i] - c1[i]) * t) for i in range(3))
        draw.line([(0, y), (W, y)], fill=colour)

    # Soft geometric accent.
    draw.polygon([(W * 0.55, H), (W, H * 0.25), (W, H)], fill=tuple(min(255, v + 22) for v in c2))
    draw.ellipse([W * 0.78, H * 0.10, W * 0.78 + 130, H * 0.10 + 130],
                 outline=(255, 255, 255), width=4)

    f1 = font(78)
    f2 = font(78)
    draw.text((72, H * 0.52), line1, font=f1, fill=(255, 255, 255))
    draw.text((72, H * 0.52 + 92), line2, font=f2, fill=(255, 255, 255, 220))

    img.save(os.path.join(OUT, name), quality=88)
    print("wrote", name)
