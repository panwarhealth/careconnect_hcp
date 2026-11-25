"""Recolours a studio photo's plain yellow/amber backdrop to a flat brand colour.

    python3 tools/caph0148/recolour_bg.py SRC OUT.webp --top 0.035 [--colour FF8424] [--preview PREVIEW.jpg]
    python3 tools/caph0148/recolour_bg.py SRC OUT.webp --box 0.3125,0.02,0.35 --size 800x800

Crops SRC to the 1200x630 hero shape (full width, starting --top of the way down), or with --box to a
square at x,y (fractions of width and height) with side as a fraction of the width. Then works in
CIELAB: pixels whose b* (yellowness) is high are backdrop. The mask ramps between two b* values, so
hair fringes that mix hair and backdrop move only part of the way to the new colour, avoiding a halo.
Skin, brown hair, the navy top and the blue suitcase have far lower b* and are left untouched.
"""
import argparse

import numpy as np
from PIL import Image, ImageCms

p = argparse.ArgumentParser()
p.add_argument('src')
p.add_argument('out')
p.add_argument('--top', type=float, default=0.035, help='crop start as a fraction of the height')
p.add_argument('--colour', default='FF8424')
p.add_argument('--lo', type=float, default=48, help='b* below this is never backdrop')
p.add_argument('--hi', type=float, default=66, help='b* above this is fully backdrop')
p.add_argument('--box', help='x,y,side as fractions: x and side of the width, y of the height')
p.add_argument('--size', default='1200x630', help='output size WxH')
p.add_argument('--preview')
a = p.parse_args()

im = Image.open(a.src).convert('RGB')
W, H = im.size
ow, oh = (int(v) for v in a.size.split('x'))
if a.box:
    bx, by, bs = (float(v) for v in a.box.split(','))
    x0, y0, side = round(W * bx), round(H * by), round(W * bs)
    im = im.crop((x0, y0, x0 + side, y0 + side))
else:
    ch = round(W * oh / ow)
    y0 = round(H * a.top)
    im = im.crop((0, y0, W, y0 + ch))
im = im.resize((ow * 2, oh * 2), Image.LANCZOS)  # work at 2x, downscale at the end

srgb = ImageCms.createProfile('sRGB')
lab_p = ImageCms.createProfile('LAB')
to_lab = ImageCms.buildTransformFromOpenProfiles(srgb, lab_p, 'RGB', 'LAB')
to_rgb = ImageCms.buildTransformFromOpenProfiles(lab_p, srgb, 'LAB', 'RGB')

lab = np.asarray(ImageCms.applyTransform(im, to_lab)).astype(np.float32)
# PIL LAB: L 0-255 (=0-100); a/b are signed bytes stored in unsigned channels (two's complement)
signed = lambda c: np.where(c >= 128, c - 256, c)
L, A, B = lab[..., 0], signed(lab[..., 1]), signed(lab[..., 2])

target = Image.new('RGB', (1, 1), '#' + a.colour)
t = np.asarray(ImageCms.applyTransform(target, to_lab)).astype(np.float32)[0, 0]
tL, tA, tB = t[0], signed(t[1]), signed(t[2])

alpha = np.clip((B - a.lo) / (a.hi - a.lo), 0, 1)
alpha = alpha * alpha * (3 - 2 * alpha)  # smoothstep

L2 = L + alpha * (tL - L)
A2 = A + alpha * (tA - A)
B2 = B + alpha * (tB - B)
unsigned = lambda c: np.round(c).clip(-128, 127).astype(np.int16) % 256
out = np.stack([L2.clip(0, 255).round().astype(np.int16), unsigned(A2), unsigned(B2)], axis=-1).astype(np.uint8)
res = ImageCms.applyTransform(Image.fromarray(out, 'LAB'), to_rgb).resize((ow, oh), Image.LANCZOS)
res.save(a.out, 'WEBP', quality=82, method=6)
if a.preview:
    res.save(a.preview, quality=88)
print('wrote', a.out, 'backdrop share %.0f%%' % (100 * (alpha > 0.5).mean()))
