#!/bin/bash
# Downloads the [SMALL] films listed in src/videos.js from the public Drive folder into raw/video/<key>.src.mp4
cd "$(dirname "$0")/.."
grep -oE '"[a-z0-9-]+": "[A-Za-z0-9_-]{20,}"' src/videos.js | tr -d '"' | while IFS=': ' read -r k id; do
  out="raw/video/$k.src.mp4"; [ -s "$out" ] && continue
  curl -sSL -o "$out" "https://drive.usercontent.google.com/download?id=$id&export=download&confirm=t"
  echo "$k $(stat -f%z "$out") $(file -b "$out" | cut -c1-30)"
done
