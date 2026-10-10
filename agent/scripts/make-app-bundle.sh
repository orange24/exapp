#!/bin/sh
# ห่อไฟล์โปรแกรมเป็น .app ให้ดับเบิลคลิกได้จริงบน macOS
#
# ไฟล์ Mach-O เปล่า ๆ ที่ไม่มีนามสกุล พอดับเบิลคลิกใน Finder แล้ว macOS จะ
# เปิด Terminal ขึ้นมารันให้ ทิ้งหน้าต่างดำค้างไว้ซึ่งพนักงานอาจเผลอปิด
# แล้วเครื่องอ่านบัตรก็หยุดทำงานโดยไม่มีใครรู้
#
# .app คือโฟลเดอร์ที่มีโครงสร้างตามที่ macOS กำหนด ไม่ต้องคอมไพล์อะไรเพิ่ม
set -e

BIN="$1"
OUT="${2:-exapp-card-agent.app}"

if [ ! -f "$BIN" ]; then
	echo "ไม่พบไฟล์โปรแกรม: $BIN" >&2
	exit 1
fi

rm -rf "$OUT"
mkdir -p "$OUT/Contents/MacOS"

cp "$BIN" "$OUT/Contents/MacOS/exapp-card-agent"
chmod +x "$OUT/Contents/MacOS/exapp-card-agent"

cat > "$OUT/Contents/Info.plist" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>CFBundleName</key><string>เครื่องอ่านบัตร exapp</string>
  <key>CFBundleDisplayName</key><string>เครื่องอ่านบัตร exapp</string>
  <key>CFBundleIdentifier</key><string>com.softernity.exapp.cardagent</string>
  <key>CFBundleExecutable</key><string>exapp-card-agent</string>
  <key>CFBundlePackageType</key><string>APPL</string>
  <key>CFBundleShortVersionString</key><string>${VERSION:-dev}</string>

  <!-- ไม่ต้องมีไอคอนใน Dock — โปรแกรมทำงานเบื้องหลัง หน้าตาอยู่ในเบราว์เซอร์ -->
  <key>LSUIElement</key><true/>
</dict></plist>
PLIST

echo "สร้าง $OUT แล้ว"
