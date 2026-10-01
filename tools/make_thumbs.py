"""assets/*.webp -> cms/assets/thumb/*.webp (320px long edge) for the panel's media picker."""
import os, sys
sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), 'py'))
import cv2
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
src, out = os.path.join(ROOT, 'assets'), os.path.join(ROOT, 'cms', 'assets', 'thumb')
os.makedirs(out, exist_ok=True)
for f in os.listdir(src):
    p = os.path.join(out, f)
    if not f.endswith('.webp') or (os.path.exists(p) and os.path.getmtime(p) >= os.path.getmtime(os.path.join(src, f))):
        continue
    im = cv2.imread(os.path.join(src, f))
    h, w = im.shape[:2]; s = 320 / max(h, w)
    if s < 1:
        im = cv2.resize(im, (round(w * s), round(h * s)), interpolation=cv2.INTER_AREA)
    cv2.imwrite(p, im, [cv2.IMWRITE_WEBP_QUALITY, 72])
