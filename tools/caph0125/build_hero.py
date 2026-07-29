"""CAPH0125 blog hero mock — 1200x630, Hydralyte orange, ORS vs food tiles.

Rendered at 2x supersample and downscaled for maximum edge smoothness.
"""
from PIL import Image, ImageDraw, ImageFont, ImageFilter

S = 2  # supersample factor
W, H = 1200 * S, 630 * S
ORANGE = (255, 132, 36)
ORANGE_D = (222, 96, 0)
CREAM = (255, 232, 214)
WHITE = (255, 255, 255)
NAVY = (20, 42, 66)

FONTS = "/home/doobz/projects/careconnect_hcp/site/wp-content/themes/wp-spinnr-child/_nuxt/"
GGQ = "/home/doobz/projects/careconnect_hcp/site/wp-content/uploads/2026/07/ggq/"

semibold = lambda s: ImageFont.truetype(FONTS + "Poppins-SemiBold.248c0244.ttf", s * S)
medium = lambda s: ImageFont.truetype(FONTS + "Poppins-Medium.8d909883.ttf", s * S)

img = Image.new("RGB", (W, H), ORANGE)

# darker diagonal band lower-right for depth
band = Image.new("RGBA", (W, H), (0, 0, 0, 0))
bd = ImageDraw.Draw(band)
bd.polygon([(W, H), (W, 150 * S), (700 * S, H)], fill=ORANGE_D + (170,))
img.paste(Image.alpha_composite(img.convert("RGBA"), band).convert("RGB"), (0, 0))
d = ImageDraw.Draw(img)

# ---- left text block (gaps above/below title measured equal) ----
x = 80 * S
d.text((x, 101 * S), "Q U I Z", font=medium(26), fill=CREAM)
d.text((x, 141 * S), "Guess the", font=semibold(84), fill=WHITE)
d.text((x, 239 * S), "Glucose", font=semibold(84), fill=WHITE)
d.text((x, 337 * S), "Challenge", font=semibold(84), fill=WHITE)
d.text((x, 475 * S), "Which contains less sugar?", font=medium(34), fill=CREAM)

# ---- right tiles: two white circles with drop shadow ----
def smooth_ellipse_mask(size):
    big = Image.new("L", (size * 4, size * 4), 0)
    ImageDraw.Draw(big).ellipse([0, 0, size * 4 - 1, size * 4 - 1], fill=255)
    return big.resize((size, size), Image.LANCZOS)

def circle_tile(src, size, pad):
    tile = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    mask = smooth_ellipse_mask(size)
    face = Image.new("RGBA", (size, size), WHITE + (255,))
    art = Image.open(src).convert("RGBA").resize((size - 2 * pad, size - 2 * pad), Image.LANCZOS)
    face.paste(art, (pad, pad), art)
    tile.paste(face, (0, 0), mask)
    return tile

def paste_with_shadow(base, tile, pos):
    size = tile.size[0]
    sh = Image.new("RGBA", (size + 60 * S, size + 60 * S), (0, 0, 0, 0))
    ImageDraw.Draw(sh).ellipse([30 * S, 38 * S, 30 * S + size, 38 * S + size], fill=(90, 40, 5, 120))
    sh = sh.filter(ImageFilter.GaussianBlur(18 * S))
    base.alpha_composite(sh, (pos[0] - 30 * S, pos[1] - 30 * S))
    base.alpha_composite(tile, pos)

canvas = img.convert("RGBA")
ors = circle_tile(GGQ + "hydralyte-orange.png", 300 * S, 42 * S)
food = circle_tile(GGQ + "apple.png", 300 * S, 0)
paste_with_shadow(canvas, ors, (760 * S, 70 * S))
paste_with_shadow(canvas, food, (870 * S, 290 * S))

# ---- VS badge centred on the overlap of the two circles ----
d = ImageDraw.Draw(canvas)
bs = 96 * S
bx, by = 945 * S - bs // 2, 325 * S - bs // 2
badge = Image.new("RGBA", (bs, bs), NAVY + (255,))
badge.putalpha(smooth_ellipse_mask(bs))
canvas.alpha_composite(badge, (bx, by))
f = semibold(40)
tw = d.textlength("VS", font=f)
d.text((bx + (bs - tw) / 2, by + 20 * S), "VS", font=f, fill=WHITE)

final = canvas.convert("RGB").resize((1200, 630), Image.LANCZOS)
final.save("/home/doobz/projects/careconnect_hcp/site/wp-content/uploads/2026/07/ggq-hero.png")
print("saved ggq-hero.png")
