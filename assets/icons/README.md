# PWA 图标说明

## 需要的图标尺寸

请将你的 HLCC Logo 转换成以下尺寸的 PNG 图标：

### 必需的图标（用于 PWA 功能）
- `icon-192x192.png` - Android 主屏幕
- `icon-512x512.png` - Android 启动画面

### iOS 专用图标
- `icon-120x120.png` - iPhone/iPod Touch
- `icon-152x152.png` - iPad
- `icon-180x180.png` - iPhone Retina

## 快速生成方法

### 方法 1：在线工具（推荐）
访问：https://realfavicongenerator.net/
- 上传你的 512x512 Logo
- 选择所有平台
- 下载并替换到此目录

### 方法 2：使用 ImageMagick
```bash
cd /Users/gino/Documents/iCollections/视频剪辑/洗纹身/HLCC/handlock-care-center/assets/icons

# 准备一个 original-logo.png (512x512 或更大)
# 然后运行：

for size in 120 152 180 192 512; do
  convert original-logo.png -resize ${size}x${size} icon-${size}x${size}.png
done
```

### 方法 3：请 Claude 帮忙
如果你有 Logo 源文件，可以：
1. 把 Logo 发给我
2. 我帮你生成所有尺寸
3. 下载并放到这个目录

## 临时方案（测试用）

如果暂时没有图标，可以使用纯色占位图：

```bash
# 生成纯色占位图（深灰色背景 + 白色 H 字母）
convert -size 192x192 xc:"#1a1a1a" -fill white -pointsize 120 -gravity center -annotate +0+0 "H" icon-192x192.png
convert -size 512x512 xc:"#1a1a1a" -fill white -pointsize 320 -gravity center -annotate +0+0 "H" icon-512x512.png
convert -size 120x120 xc:"#1a1a1a" -fill white -pointsize 80 -gravity center -annotate +0+0 "H" icon-120x120.png
convert -size 152x152 xc:"#1a1a1a" -fill white -pointsize 100 -gravity center -annotate +0+0 "H" icon-152x152.png
convert -size 180x180 xc:"#1a1a1a" -fill white -pointsize 120 -gravity center -annotate +0+0 "H" icon-180x180.png
```

## 注意事项

1. **图标设计建议**：
   - 使用简洁的图标（不是复杂的 Logo）
   - 确保在小尺寸（120px）下清晰可辨
   - 建议使用单色或双色设计
   - 避免细线条（在小屏幕上看不清）

2. **背景处理**：
   - iOS: 图标会自动加圆角，无需预先处理
   - Android: 建议提供透明背景或纯色背景

3. **测试**：
   - 生成后在真实设备上测试
   - 检查主屏幕图标是否清晰
   - 确认启动画面显示正常
