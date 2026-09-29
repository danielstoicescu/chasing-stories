"""Finds faces and heads in every asset so palette squares never land on a person's head.
Writes assets/avoid.json: {key: {"h": hard boxes (faces, heads), "s": soft boxes (whole person)}} in 0..1 image coords."""
import os, sys, json
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'py'))
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'models'))
import cv2, numpy as np
from nanodet import NanoDet
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
M = os.path.join(ROOT, 'tools', 'models')
yunet = cv2.FaceDetectorYN.create(os.path.join(M, 'face_detection_yunet_2023mar.onnx'), '', (320, 320), 0.55, 0.3, 5000)
nano = NanoDet(os.path.join(M, 'object_detection_nanodet_2022nov.onnx'), prob_threshold=0.33, iou_threshold=0.5)
def nano_infer(img):
    # OpenCV 5 returns the heads in another order: pair class scores (N,80) with box logits (N,32) by N
    nano.net.setInput(nano.pre_process(img))
    outs = [o.squeeze(0) if o.ndim == 3 else o for o in nano.net.forward(nano.net.getUnconnectedOutLayersNames())]
    cls = sorted([o for o in outs if o.shape[1] == 80], key=lambda o: -o.shape[0])
    reg = sorted([o for o in outs if o.shape[1] != 80], key=lambda o: -o.shape[0])
    return nano.post_process([x for pair in zip(cls, reg) for x in pair])
out = {}
keys = [f[:-5] for f in sorted(os.listdir(os.path.join(ROOT, 'assets'))) if f.endswith('.webp')]
for k in keys:
    img = cv2.imread(os.path.join(ROOT, 'assets', k + '.webp'))
    if img is None: continue
    H, W = img.shape[:2]; hard, soft = [], []
    s = 640 / max(W, H); sm = cv2.resize(img, (int(W * s), int(H * s)))
    yunet.setInputSize((sm.shape[1], sm.shape[0]))
    _, faces = yunet.detect(sm)
    for f in (faces if faces is not None else []):
        x, y, w, h = f[:4] / s
        cx, cy = x + w / 2, y + h / 2; R = max(w, h) * 0.95          # face plus hair
        hard.append([(cx - R) / W, (cy - R * 1.15) / H, (cx + R) / W, (cy + R * .9) / H])
    dets = nano_infer(cv2.resize(img, (416, 416)))
    for d in (dets if dets is not None else []):
        x0, y0, x1, y1, conf, cls = d[:6]
        if int(cls) != 0: continue
        x0, x1 = x0 * W / 416, x1 * W / 416; y0, y1 = y0 * H / 416, y1 * H / 416
        bw, bh = x1 - x0, y1 - y0
        soft.append([x0 / W, y0 / H, x1 / W, y1 / H])
        headh = min(bh, max(0.24 * bh, 0.5 * bw))                  # head zone: top of the person box
        hard.append([(x0 - .08 * bw) / W, (y0 - .05 * bh) / H, (x1 + .08 * bw) / W, (y0 + headh) / H])
    clip = lambda b: [round(float(min(1, max(0, v))), 4) for v in b]
    if hard or soft: out[k] = {'h': [clip(b) for b in hard], 's': [clip(b) for b in soft]}
json.dump(out, open(os.path.join(ROOT, 'assets', 'avoid.json'), 'w'))
print(len(keys), 'images,', len(out), 'with people')
