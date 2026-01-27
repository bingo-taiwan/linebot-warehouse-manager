# AI CLI 環境資訊（Claude & Gemini 共用）

**位置**: NAS 共用檔案 `X:\gemini\AI_ENV.md`
**自動更新時間**: 2026-01-28 00:57

---

## 📍 當前位置

- **位置**: 🏠 家裡 (DESKTOP-J9CIIVU)
- **Tailscale IP**: 100.70.111.60
- **X: 磁碟機**: 已連接
- **Git 狀態**: 已同步

---

## 🤝 協作分工

### Claude 負責
- 程式邏輯、除錯
- 檔案編輯（PHP, JSON, Python）
- Git 操作
- 伺服器部署
- matplotlib 加中文標籤

### Gemini 負責
- 圖片生成（消耗 Gemini API，不耗 Claude token）
- 大量文字生成
- 資料視覺化底圖

### 協作原則
- Gemini 生成無標籤底圖 → Claude 用 matplotlib 加中文標籤
- 圖片必須 < 300KB（LINE Bot 限制）
- 手機可讀字體：標籤 >= 16pt，標題 >= 20pt

---

*此檔案由 PowerShell profile 自動生成並儲存於 NAS，兩地通用*
