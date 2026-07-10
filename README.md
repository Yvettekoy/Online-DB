# 🤖 AI DebugCamp - Python 除錯學習平台

> 一個 AI 驅動的互動式 Python 編程學習系統，幫助學習者通過實踐和 AI 指導掌握除錯技能。

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)](https://www.mysql.com/)

---

## 📌 專案概述

**AI DebugCamp** 是一個創新的編程教育平台，專注於 Python 除錯技能的培養。通過整合 **OpenAI API**，系統能夠：

- 🤖 **AI 智能評分** - 自動判斷使用者的除錯代碼是否正確
- 🎯 **個性化引導** - 根據錯誤類型提供針對性的學習提示
- 📊 **進度追蹤** - 記錄每位使用者的學習歷程
- 🏆 **積分獎勵** - 完成題目獲得積分，解鎖隱藏寶箱
- 📚 **動態題庫** - 支援教師上傳自訂題目

**適用場景**：
- 大學 Python 編程教學輔助
- 編程自學者的練習平台
- 企業員工代碼除錯訓練

---

## ✨ 核心功能

### 用戶系統
- ✅ 帳號註冊與登入（密碼加密儲存）
- ✅ 個人檔案管理（上傳大頭貼）
- ✅ 會話管理（基於 Session 的認證）

### 題目挑戰
- ✅ 瀏覽題庫（按類型分類）
- ✅ 閱讀題目說明與錯誤代碼
- ✅ 手動編輯代碼後提交
- ✅ 追蹤作答次數與進度

### AI 智能輔助
- ✅ **AI 即時解釋** - 分析錯誤代碼，生成詳細解釋
- ✅ **引導式學習** - 提供漸進式提示題，幫助使用者自我發現問題
- ✅ **自動評分** - 比對使用者代碼與正確答案
- ✅ **績效反饋** - 即時顯示評分結果和改進方向

### 激勵系統
- ✅ **積分機制** - 完成題目即可獲得積分
- ✅ **寶箱獎勵** - 達到特定積分里程碑時解鎖隱藏獎勵
- ✅ **排行榜**（未來功能）- 用戶排名競爭

### 題庫管理
- ✅ 教師後臺上傳新題目
- ✅ 題目包含：類型、說明、錯誤代碼、正確代碼
- ✅ 支援題目分類（輸入輸出、IF 判斷等）

---

## 🛠 技術棧

### 後端
- **Language**: PHP 7.4+
- **Database**: MySQL 5.7+ / MariaDB
- **Database Access**: PDO（參數化查詢防 SQL 注入）
- **Authentication**: PHP Sessions + Password Hashing (bcrypt)

### 前端
- **Framework**: Bootstrap 5
- **Language**: HTML5, CSS3, JavaScript (ES6+)
- **UI/UX**: 響應式設計，支援行動裝置

### 外部 API
- **OpenAI API** - 用於 AI 代碼評分與生成引導題

### 架構特點
- **MVC 模式思想** - 邏輯與顯示分離
- **無框架設計** - 便於初學者理解核心原理
- **模組化結構** - 易於維護與擴展

---

## 📂 專案結構

```
Online-DB/
├── README.md                    # 項目文檔
├── db.php                        # 數據庫連線配置
├── python_tutor_setup.sql        # 數據庫初始化腳本
│
├── 認證相關
├── login.php                     # 登入頁面
├── register.php                  # 註冊頁面
├── logout.php                    # 登出功能
│
├── 首頁與儀表板
├── index.php                     # 首頁 / 登入後重定向
├── dashboard.php                 # 題目列表與挑戰主頁
│
├── 題目挑戰
├── manual_edit.php               # 手動編輯代碼的作答區
├── check_manual_answer.php       # 提交答案並檢查
├── play.php                      # 題目詳細頁面
│
├── AI 輔助功能
├── ai_assist.php                 # AI 即時解釋代碼
├── ai_guide.php                  # AI 生成引導式提示題
│
├── 題庫管理
├── upload_question.php           # 教師上傳新題目
│
└── 用戶設定
    └── settings.php              # 使用者個人設定頁面
```

---

## 🗄 數據庫架構

### 核心表結構

#### 1. `users` - 用戶表
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255),
    score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 2. `questions` - 題庫表
```sql
CREATE TABLE questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    prompt LONGTEXT NOT NULL,
    wrong_code LONGTEXT NOT NULL,
    correct_code LONGTEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

#### 3. `question_attempts` - 作答紀錄表
```sql
CREATE TABLE question_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    attempts INT DEFAULT 0,
    completed INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, question_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (question_id) REFERENCES questions(id)
);
```

#### 4. `treasure_claims` - 寶箱領取紀錄
```sql
CREATE TABLE treasure_claims (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    box_number INT NOT NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, box_number),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### 5. `ai_explanations` - AI 解釋記錄
```sql
CREATE TABLE ai_explanations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    wrong_code LONGTEXT NOT NULL,
    explanation LONGTEXT NOT NULL,
    guide_question1 LONGTEXT,
    guide_answer1 CHAR(1),
    guide_question2 LONGTEXT,
    guide_answer2 CHAR(1),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## 🚀 快速開始

### 系統需求

- **PHP**: 7.4 或更高版本
- **MySQL**: 5.7 或更高版本（推薦 8.0+）
- **Web Server**: Apache 或 Nginx（需要 mod_rewrite）
- **OpenAI API Key**: 用於 AI 功能（可選）

### 本地安裝

#### 1️⃣ 克隆專案
```bash
git clone https://github.com/Yvettekoy/Online-DB.git
cd Online-DB
```

#### 2️⃣ 設定數據庫

使用 MySQL 命令列：
```bash
mysql -u root -p < python_tutor_setup.sql
```

#### 3️⃣ 配置連線

編輯 `db.php`，設定你的資料庫連線：

```php
<?php
$host = 'localhost';
$db   = 'python_tutor';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
```

#### 4️⃣ 配置 AI 功能（可選）

在相關檔案中設定 OpenAI API Key：

```php
define('OPENAI_API_KEY', 'your-api-key-here');
define('OPENAI_MODEL', 'gpt-3.5-turbo');
```

#### 5️⃣ 啟動本地伺服器

```bash
php -S localhost:8000
```

#### 6️⃣ 訪問應用

```
http://localhost:8000
```

### 首次使用

1. 點擊「註冊」建立新帳號
2. 登入後，選擇「挑戰題目」開始
3. 閱讀題目、編輯代碼、提交答案
4. 點擊「AI 協助」獲得智能提示

---

## 📖 使用說明

### 學習者工作流程

```
1. 註冊帳號
   ↓
2. 進入儀表板查看題庫
   ↓
3. 選擇題目進行挑戰
   ↓
4. 閱讀題目說明與錯誤代碼
   ↓
5. 手動修正代碼
   ↓
6. 提交答案
   ├─ ✅ 正確 → 獲得積分
   └─ ❌ 錯誤 → 可重試或請求 AI 協助
   ↓
7. 累積積分解鎖寶箱
```

### 教師工作流程

```
1. 登入帳號
   ↓
2. 進入「上傳題目」頁面
   ↓
3. 填寫題目資訊
   ├─ 類型
   ├─ 題目說明
   ├─ 錯誤代碼範例
   └─ 正確答案
   ↓
4. 提交
   ↓
5. 題目自動加入題庫
```

---

## 🔐 安全特性

### 已實施的安全措施

- ✅ **密碼加密** - 使用 bcrypt 加密儲存密碼
- ✅ **Session 認證** - 檢查用戶登入狀態
- ✅ **參數化查詢** - 使用 PDO Prepared Statement 防止 SQL 注入
- ✅ **輸入驗證** - 檢查上傳的代碼格式

### 未來改進計畫

- ⏳ CSRF Token 驗證
- ⏳ XSS 防護（HTML 實體編碼）
- ⏳ 速率限制（防暴力破解）
- ⏳ 審計日誌（記錄重要操作）

---

## 📊 主要功能詳解

### 1. AI 即時代碼解釋

**觸發方式**：使用者點擊「獲得 AI 協助」

**工作流程**：
```
使用者錯誤代碼
    ↓
調用 OpenAI API
    ↓
分析 & 生成詳細解釋
    ↓
返回給使用者
```

### 2. 引導式學習

**觸發方式**：使用者點擊「需要提示」

**工作流程**：
```
錯誤代碼
    ↓
AI 生成循序漸進的提示
    ↓
第一個提示題
    ├─ 用戶選擇 A 或 B
    ├─ 如果正確 → 顯示第二個提示
    └─ 如果錯誤 → 給出更詳細的說明
    ↓
最後揭示完整答案
```

### 3. 積分與寶箱系統

| 里程碑 | 獲得積分 | 獎勵 |
|-------|---------|------|
| 完成第 1 道題 | 10 分 | 🎁 寶箱 #1 |
| 累計 50 分 | - | 🎁 寶箱 #2 |
| 累計 100 分 | - | 🎁 寶箱 #3 |
| 累計 200 分 | - | 🏆 特殊徽章 |

---

## 🐛 已知問題與限制

| 問題 | 嚴重性 | 狀態 | 說明 |
|-----|-------|------|------|
| API Key 超額可能中止服務 | 🟠 中等 | ⏳ 待改進 | 需要實施速率限制 |
| 題目無版本控制 | 🟡 輕微 | ⏳ 計畫中 | 考慮添加題目修訂歷史 |
| 無排行榜功能 | 🟡 輕微 | ⏳ 未開始 | 可作為未來功能 |
| 手機端 UI 優化中 | 🟡 輕微 | 🔄 進行中 | 某些元素在小螢幕上仍需調整 |

---

## 🚀 未來功能路線圖

### Phase 2（短期）
- [ ] 題目搜索與過濾功能
- [ ] 用戶個人統計儀表板
- [ ] 批量導入題庫（CSV）
- [ ] 評論與社區功能

### Phase 3（中期）
- [ ] 排行榜系統
- [ ] 成就徽章系統
- [ ] 練習路徑（推薦學習順序）
- [ ] 多語言支援

### Phase 4（長期）
- [ ] 支援更多編程語言
- [ ] 代碼視覺化執行
- [ ] 實時協作編輯
- [ ] 企業版本

---

## 📚 學習資源

- [OpenAI API 文檔](https://platform.openai.com/docs)
- [PHP 官方文檔](https://www.php.net/docs.php)
- [MySQL 教程](https://dev.mysql.com/doc/)
- [Bootstrap 文檔](https://getbootstrap.com/docs)

---

## 🤝 貢獻指南

歡迎提交 Issue 和 Pull Request！

### 開發流程

1. Fork 本倉庫
2. 建立特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 開啟 Pull Request

### 代碼風格

- 遵循 PSR-12 PHP 編碼標準
- 添加註解解釋複雜邏輯
- 使用有意義的變數名稱

---

## 📄 授權

本項目採用 **MIT 授權**。

---

## 💬 聯絡方式

- **GitHub**: [@Yvettekoy](https://github.com/Yvettekoy)
- **Issues**: [報告問題](https://github.com/Yvettekoy/Online-DB/issues)

---

## 🙏 致謝

感謝以下開源項目與技術支援：

- [OpenAI](https://openai.com) - 提供強大的 AI 模型
- [Bootstrap](https://getbootstrap.com) - 前端框架
- [PHP 社區](https://www.php.net/) - 語言與文檔

---

## 📌 版本歷史

### v1.0.0 (2026-07-10) 🎉
- ✅ 核心功能完成
- ✅ AI 整合實現
- ✅ 數據庫架構設計
- ✅ 用戶認證系統
- ✅ 題目管理系統

---

**感謝使用 AI DebugCamp！** 🚀

如有任何問題或建議，歡迎開啟 Issue 或直接聯絡我們。

*最後更新：2026-07-10*
