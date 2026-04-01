# video_cut_go 切片速度优化方案

**日期:** 2026-04-01
**当前问题:** 视频切片速度慢

---

## 当前架构分析

```
Downloader → Editor (水印+重编码) → M3U8 (再次重编码) → Sender (rsync)
```

### 发现的瓶颈

| 瓶颈 | 位置 | 影响 |
|------|------|------|
| 双重编码 | watermark.go → m3u8.go | 水印输出MP4，M3U8又重编码一次，时间翻倍 |
| 全帧重编码 | cut.go `-vcodec h264` | 没有用硬件加速，纯CPU编码 |
| 单任务串行 | downloader.go `total >= 1` | 同时只处理1个视频 |
| rsync重复11次 | send.go:104 成功后缺break | 传输时间×11 |
| 无并行处理 | Rule.Run() 顺序执行 | webp→水印→预览→m3u8 全串行 |
| shell字符串拼接 | ffmpeg命令通过/bin/bash -c | 额外shell开销+安全风险 |

---

## 优化方案（按优先级排序）

### P0: 立即修复 — rsync 重复传输 (预计提速 10x传输时间)

**文件:** `service/sender/send.go:104-119`

当前代码：
```go
for retry := 0; retry <= maxRetry; retry++ {
    _, err = common.BashExecuteContext(s.ctx, cmd)
    if err != nil {
        // ... 重试逻辑
        continue
    }
    // 成功了但没有 break！继续循环执行 11 次！
}
```

修复：
```go
for retry := 0; retry <= maxRetry; retry++ {
    _, err = common.BashExecuteContext(s.ctx, cmd)
    if err != nil {
        if strings.Contains(strings.ToLower(err.Error()), "timeout") {
            time.Sleep(time.Duration(retryTimeWait) * time.Second)
            continue
        }
        s.log.Errorf(config.Identifier, "send error: %s", err)
        return
    }
    break  // ← 加这一行！成功后退出循环
}
```

### P1: 合并水印+M3U8为一条命令 (预计提速 40-50%)

**当前流程（两次编码）：**
```
原始视频 → [ffmpeg 水印重编码] → 中间MP4 → [ffmpeg M3U8重编码] → HLS
```

**优化后（一次编码）：**
```
原始视频 → [ffmpeg 水印+直接输出HLS] → HLS
```

**修改 `watermark.go` 的 `buildCmd`，直接输出 HLS：**
```go
// 在 watermark.Run() 末尾，如果 M3U8 也启用，直接输出 HLS 而非 MP4
if m3u8Enabled {
    // 替换输出格式
    outCmd = fmt.Sprintf(`" -c:v libx264 -threads %d -c:a aac -b:a 128k
        -hls_time %d -hls_playlist_type vod -hls_list_size 0
        -f hls %s`, d.Threads, m3u8Interval, m3u8File)
} else {
    outCmd = fmt.Sprintf(`" -c:v libx264 -threads %d ... -y %s`, d.Threads, output)
}
```

这样省去中间 MP4 文件和第二次完整编码。

### P2: 启用硬件加速编码 (预计提速 3-5x)

**当前:** `-vcodec h264` (libx264 纯CPU)

**如果服务器有 NVIDIA GPU:**
```go
// cut.go
args = fmt.Sprintf("-loglevel error -hwaccel cuda -ss %d -t %d -i %s -c:v h264_nvenc -preset p4 -y %s",
    start, step, input, output)

// watermark.go buildCmd
outCmd = fmt.Sprintf(`" -c:v h264_nvenc -preset p4 -threads %d ...`, d.Threads)
```

**如果是 Intel CPU (有 QSV):**
```go
args = fmt.Sprintf("-hwaccel qsv -ss %d -t %d -i %s -c:v h264_qsv -preset faster -y %s", ...)
```

**检测方法:**
```bash
# GPU
ffmpeg -encoders 2>/dev/null | grep nvenc
# Intel QSV
ffmpeg -encoders 2>/dev/null | grep qsv
# VAAPI (Linux通用)
ffmpeg -encoders 2>/dev/null | grep vaapi
```

### P3: 并行处理独立步骤 (预计提速 20-30%)

**当前 Rule.Run() 串行：**
```go
func (r *Rule) Run(...) {
    r.Webp.Run(vd)      // 串行
    r.WaterMark.Run(vd)  // 串行
    r.Preview.Run(vd)    // 串行 ← preview 可以和 M3U8 并行
    r.M3U8.Run(vd)       // 串行
}
```

**优化：Webp 和 Preview 可以并行，因为它们读取的是不同的输入：**
```go
func (r *Rule) Run(status *string, vd *VideoData) bool {
    // Step 1: Webp (独立，读原始视频截图)
    if !r.Webp.Run(vd) { return false }

    // Step 2: 水印 (必须在 M3U8 之前)
    if !r.WaterMark.Run(vd) { return false }

    // Step 3: Preview 和 M3U8 并行
    var wg sync.WaitGroup
    var previewOk, m3u8Ok bool

    wg.Add(2)
    go func() {
        defer wg.Done()
        previewOk = r.Preview.Run(vd)
    }()
    go func() {
        defer wg.Done()
        m3u8Ok = r.M3U8.Run(vd)
    }()
    wg.Wait()

    return previewOk && m3u8Ok
}
```

### P4: 允许多视频并行处理 (预计提速 2-3x)

**当前限制：**
```go
// downloader.go:124
total, err = editor.GetQueueCount()
if total >= 1 {
    return nil, tooManyErr  // 队列有1个就等
}
```

**优化为可配置的并发数：**
```go
const maxConcurrent = 3  // 或从配置读取

total, err = editor.GetQueueCount()
if total >= maxConcurrent {
    return nil, tooManyErr
}
```

Editor 也需要改为 worker pool 模式：
```go
func (e *Editor) Run() {
    defer e.wg.Done()
    sem := make(chan struct{}, e.threads/2)  // CPU核数/2个并发
    for {
        sem <- struct{}{}
        go func() {
            defer func() { <-sem }()
            e.edit()
        }()
    }
}
```

### P5: ffmpeg 参数优化 (预计提速 15-20%)

**当前参数问题：**
```
-vcodec h264                    ← 没指定 preset
-max_muxing_queue_size 9999     ← 占用大量内存
-force_key_frames "expr:..."    ← M3U8 用 -g 更高效
```

**优化参数：**
```
-c:v libx264 -preset faster     ← faster 比默认 medium 快 2x，质量差异很小
-tune fastdecode                 ← 优化解码速度
-crf 23                          ← 恒定质量，比默认更快
-g 250                           ← 关键帧间隔，替代 force_key_frames
-threads 0                       ← 让 ffmpeg 自动选最优线程数
-max_muxing_queue_size 1024      ← 降低内存占用
```

### P6: 用 exec.Command 替代 shell 字符串 (安全+性能)

**当前:**
```go
func Exec(args string) error {
    _, err := common.BashExecute(Executable + " " + args)  // /bin/bash -c "ffmpeg ..."
}
```

**优化:**
```go
func Exec(args ...string) error {
    cmd := exec.Command(Executable, args...)
    return cmd.Run()
}
```
省去 shell 解析开销，同时消除命令注入风险。

---

## 预估总体提速效果

| 优化 | 预估提速 | 难度 | 优先级 |
|------|---------|------|--------|
| 修复rsync重复11次 | 传输10x | 1行代码 | P0 |
| 合并水印+M3U8一次编码 | 40-50% | 中 | P1 |
| GPU硬件加速 | 3-5x | 需要GPU | P2 |
| 并行Preview+M3U8 | 20-30% | 低 | P3 |
| 多视频并发处理 | 2-3x吞吐量 | 中 | P4 |
| ffmpeg参数优化 | 15-20% | 低 | P5 |
| exec.Command替代shell | 5-10% | 低 | P6 |

**综合：不加GPU的情况下，P0+P1+P3+P5 可以让单视频处理速度提升约 2-3x。**
**加GPU(P2)可以再提升 3-5x，总计 6-15x。**
