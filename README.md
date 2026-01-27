# Warehouse Manager LineBot

多倉庫管理系統，整合 LINE Bot Webhook、Flex Message、LIFF 與資料庫。

## 系統目標
1. **多倉管理**：大園倉（箱進箱出）與台北倉（散進散出）。
2. **角色權限 (RBAC)**：根據使用者角色動態切換 Rich Menu 與功能。
3. **效期追蹤**：自動過濾逾期品，並提供到期日預警。
4. **自動化訂單**：台北倉向大園倉訂貨、員工福利品自選。

## 技術架構 (符合 LineBot Architecture 憲法)
- **核心封裝**：使用 `linebot_core` 進行 API 調用。
- **資料庫**：SQLite (開發期) / MySQL (正式環境)。
- **介面**：LINE Flex Message (查詢結果) + LIFF (複雜表單)。

## 目錄結構
- `/handlers`：處理入庫、出庫、訂單等核心邏輯。
- `/modules`：資料庫抽象層、角色管理器。
- `/liff`：福利品自選與新品入庫的 HTML/JS 介面。
- `/data`：存儲 SQLite 資料庫與統計資料。

## 角色說明
| 角色 | 權限內容 |
| --- | --- |
| **雙倉倉管** | 增刪改查所有庫存、下單大園倉、福利品自選。 |
| **行政/台北倉管** | 查效期內庫存、下單大園倉、福利品自選。 |
| **業務講師** | 僅限福利品自選。 |

## 部署資訊
- **Channel ID**: 2008987597
- **Basic ID**: @563aggdt
- **部署伺服器**: LT4 (`/home/lt4.mynet.com.tw/public_html/linebot/warehouse/`)

---
*Created by Gemini CLI based on ui-ux-pro-max & linebot-architecture skills.*
