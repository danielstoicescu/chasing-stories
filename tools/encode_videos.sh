#!/bin/bash
# raw/video/<key>.src.mp4 -> assets/video/<key>.mp4 (720p-class H.264, faststart, <9 MB so it can be uploaded through cPanel)
cd "$(dirname "$0")/.."
FF=tools/py/imageio_ffmpeg/binaries/ffmpeg-macos-aarch64-v7.1
for s in raw/video/*.src.mp4; do
  k=$(basename "$s" .src.mp4); out=assets/video/$k.mp4; [ -s "$out" ] && continue
  $FF -hide_banner -loglevel error -y -i "$s" -vf "scale='if(gt(iw,ih),1280,-2)':'if(gt(iw,ih),-2,1280)',fps='min(30,source_fps)'" \
    -c:v libx264 -preset slow -crf 26 -maxrate 1700k -bufsize 3400k -profile:v high -pix_fmt yuv420p \
    -c:a aac -b:a 96k -ac 2 -movflags +faststart "$out"
  echo "$k $(( $(stat -f%z "$out") / 1024 )) KB"
done
