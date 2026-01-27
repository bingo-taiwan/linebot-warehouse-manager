# Gemini 工作環境配置

## Gemini Added Memories
- The user wants all responses to be in Traditional Chinese (正體中文).

## 啟動時必做：讀取環境狀態
每次啟動時，請先讀取 `X:\gemini\AI_ENV.md` 了解：
- 當前位置（家裡/公司）
- Tailscale 連線狀態
- Git 同步狀態
- 與 Claude 的協作分工

## ⚠️ Bash 環境 UNC 路徑陷阱（必讀！）

你（Gemini CLI）運行在 Bash 環境中。**Bash 會自動將 `\\` 轉譯為 `\`**，導致 UNC 網路路徑變成本地路徑：

```
# ❌ 你以為的：
powershell "Test-Path '\\100.90.117.31\data\gemini'"
# 實際傳給 PowerShell 的：'\100.90.117.31\data\gemini'
# PowerShell 解讀為本地路徑：C:\100.90.117.31\data\gemini\
# 結果：在本機建了一個假資料夾，檔案根本沒到 NAS！

# ✅ 正確寫法（四個反斜線）：
powershell "Test-Path '\\\\100.90.117.31\\data\\gemini'"
# 或用磁碟代號（推薦，最安全）：
powershell "Test-Path 'X:\gemini'"
```

**規則：在 Bash 中呼叫 PowerShell 存取 NAS 時，一律用 `X:\` 磁碟代號，不要用 UNC 路徑。**

### 待修復：先前誤搬的檔案

因為此問題，`all_118`（火影忍者元素週期表卡片）被搬到了公司電腦本機 `C:\100.90.117.31\data\gemini\all_118\` 而非 NAS。下次在公司電腦操作時請修正：

```powershell
# 在公司電腦的 PowerShell（非 Bash）中執行：
Move-Item -Path 'C:\100.90.117.31\data\gemini\all_118' -Destination 'X:\gemini\' -Force
# 清理誤建的資料夾：
Remove-Item -Path 'C:\100.90.117.31' -Recurse -Force
```

## 基礎設定
- **網路磁碟機**: `X:` (映射自 `\\mynetoffice\data`)
- **工作目錄**: `X:\gemini`
- **Python 環境**: `X:\gemini\venv` (已安裝 Google Generative AI SDK)

## 環境偵測
用戶在兩台電腦間切換工作：

| 主機名稱 | 位置 | Tailscale IP |
|----------|------|--------------|
| AURUMA-BINGO | 公司 | 100.86.118.23 |
| DESKTOP-J9CIIVU | 家裡 | 100.70.111.60 |

判斷方式：執行 `hostname` 即可知道當前位置。

## 網路與同步
- **網路架構**: 家裡與公司皆安裝 Tailscale，透過 NAS 進行資料同步。
- **NAS Tailscale IP**: 100.90.117.31 (mynetoffice)
- **Skill 儲存**: Gemini 的 Skill 存放在 NAS 上，可供兩地共用。

## 與 Claude 的協作分工
- **Gemini 負責**: 圖片生成、大量文字生成、資料視覺化底圖
- **Claude 負責**: 程式邏輯、檔案編輯、Git 操作、伺服器部署、matplotlib 加中文標籤
- **協作原則**: Gemini 生成無標籤底圖 → Claude 用 matplotlib 加中文標籤

## 工作流程與規範
1. **Skill 共享**: 確保 Skill 檔案在 NAS 上可被讀寫。
2. **洞見更新 (Insight Update)**:
   - 每次任務結束後，必須將該次任務習得的洞見 (Insights) 更新到相應的 Skill 文件中。
   - 更新內容應包含改進的 Prompt、新的工具使用模式或特定領域的知識。
3. **版本控制**:
   - Skill 與配置文件的變更必須同步更新到 GitHub 倉庫。

## 重要檔案
- **此檔案**: `X:\gemini\GEMINI.md`（NAS 共用，兩地 symlink 到 `~/.gemini/GEMINI.md`）
- **環境狀態**: `X:\gemini\AI_ENV.md`（PowerShell profile 自動更新）
- **環境 JSON**: `X:\gemini\env_status.json`（程式可讀的環境狀態）
