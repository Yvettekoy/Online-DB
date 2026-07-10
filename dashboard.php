<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 處理寶箱請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_treasure'])) {
    $boxNumber = (int)$_POST['claim_treasure'];

    // 確認是否已領取該寶箱
    $checkTreasure = $pdo->prepare("SELECT COUNT(*) FROM treasure_claims WHERE user_id = ? AND box_number = ?");
    $checkTreasure->execute([$user_id, $boxNumber]);
    $claimed = $checkTreasure->fetchColumn();

    if (!$claimed) {
        $points = rand(10, 30);
        $pdo->prepare("UPDATE users SET score = score + ? WHERE id = ?")->execute([$points, $user_id]);
        $pdo->prepare("INSERT INTO treasure_claims (user_id, box_number) VALUES (?, ?)")->execute([$user_id, $boxNumber]);
        $_SESSION['treasure_message'] = "🎁 恭喜你獲得了 {$points} 分！";
    } else {
        $_SESSION['treasure_message'] = "⛔ 此寶箱已領取過囉！";
    }

    header("Location: dashboard.php");
    exit;
}

// 取得使用者積分
$scoreStmt = $pdo->prepare("SELECT score FROM users WHERE id = ?");
$scoreStmt->execute([$user_id]);
$userScore = $scoreStmt->fetchColumn() ?: 0;

// 題庫資訊
$totalInputOutput = $pdo->query("SELECT COUNT(*) FROM questions WHERE type = '輸入輸出'")->fetchColumn();
$totalIf = $pdo->query("SELECT COUNT(*) FROM questions WHERE type = 'if'")->fetchColumn();
$totalQuestions = $totalInputOutput + $totalIf;

// 使用者完成題數
$progressStmt = $pdo->prepare("SELECT COUNT(DISTINCT question_id) FROM question_attempts WHERE user_id = ? AND completed = 1");
$progressStmt->execute([$user_id]);
$completedCount = (int)$progressStmt->fetchColumn();

// 目前階段箱子編號（以 5 題為一箱）
$currentBox = floor($completedCount / 5) + 1;
$currentBoxProgress = $completedCount % 5;

// 查詢已領取的寶箱
$claimedBoxesStmt = $pdo->prepare("SELECT box_number FROM treasure_claims WHERE user_id = ?");
$claimedBoxesStmt->execute([$user_id]);
$claimedBoxes = $claimedBoxesStmt->fetchAll(PDO::FETCH_COLUMN);
$nextBoxClaimed = in_array($currentBox, $claimedBoxes);

// 階段判定
$completedInputOutputStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT qa.question_id)
    FROM question_attempts qa
    JOIN questions q ON qa.question_id = q.id
    WHERE qa.user_id = ? AND qa.completed = 1 AND q.type = '輸入輸出'
");
$completedInputOutputStmt->execute([$user_id]);
$completedInputOutputCount = (int)$completedInputOutputStmt->fetchColumn();
$inputOutputCleared = ($completedInputOutputCount >= $totalInputOutput && $totalInputOutput > 0);

$completedIfStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT qa.question_id)
    FROM question_attempts qa
    JOIN questions q ON qa.question_id = q.id
    WHERE qa.user_id = ? AND qa.completed = 1 AND q.type = 'if'
");
$completedIfStmt->execute([$user_id]);
$completedIfCount = (int)$completedIfStmt->fetchColumn();
$ifCleared = ($completedIfCount >= $totalIf && $totalIf > 0);

// 下一題（依 id 排序）
$questionStmt = $pdo->prepare("
    SELECT * FROM questions
    WHERE id NOT IN (
        SELECT question_id FROM question_attempts WHERE user_id = ? AND completed = 1
    )
    ORDER BY id ASC LIMIT 1
");
$questionStmt->execute([$user_id]);
$question = $questionStmt->fetch(PDO::FETCH_ASSOC);
$photo = !empty($user['profile_photo']) ? $user['profile_photo'] : 'default_avatar.jpeg';
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>AI DebugCamp - 挑戰題目</title>
    <style>
        /* 通用樣式 */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0f172a;
            margin: 0;
        }

        /* 導覽列 navbar 樣式（與 index.php 一致） */
        .navbar {
            background-color: #1e293b;
            padding: 14px 2%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        /* 左邊區塊 */
        .navbar .left {
            display: flex;
            align-items: center;
            flex: 1;
        }

        /* 中間積分區塊 */
        .center {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            color: #ffffff;
            font-weight: 500;
            font-size: 23px;
            margin: 30px 0;
        }

        /* 右邊區塊 */
        .navbar .right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex: 1;
        }

        /* 連結樣式 */
        .navbar a {
            color: #cbd5e1;
            text-decoration: none;
            margin: 0 10px;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .navbar a:hover {
            color: #38bdf8;
        }

        /* 標題 */
        .navbar strong {
            color: rgb(35, 224, 249);
            margin-right: 20px;
            font-size: 18px;
        }

        .user-info span {
            margin-right: 12px;
            font-weight: 500;
            color: #cbd5e1;
        }

        .navbar img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #23e0f9;
            margin-left: 10px;

        }


        /* 容器樣式 */
        .container {
            max-width: 960px;
            margin: 25px auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            animation: fadeIn 0.5s ease;
        }

        /* 標題樣式 */
        h2 {
            color:rgb(0, 0, 0);
            font-size: 23px;
            margin-top: 15px;
        }

        h4 {
            display: flex;
            justify-content: center;
            align-items: center;
            color:rgb(225, 117, 117);
        }

        /* 進度條與標籤 */
        .progress-label {
            font-weight: bold;
            color: #333;
            margin-bottom: 12px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 18px;
        }

        .progress-bar {
            font-size: 22px;
            text-align: center;
            margin-bottom: 24px;
        }

        /* 顯示程式碼的區塊 */
        pre {
            background-color: #eef3ff;
            border-left: 6px solid #0052cc;
            padding: 18px;
            border-radius: 10px;
            white-space: pre-wrap;
            font-family: Consolas, monospace;
            font-size: 17px;
        }

        /* 按鈕樣式 */
        button {
            background-color: #0052cc;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 16px;
        }

        button:hover {
            background-color: #003d99;
        }

        /* 寶箱樣式 */
        .treasure-box {
            background-color: #f0ad4e;
            color: white;
            font-weight: bold;
            padding: 12px 20px;
            border-radius: 14px;
            box-shadow: 0 3px 8px rgba(240, 173, 78, 0.6);
            text-align: center;
            margin: 16px auto;
            cursor: pointer;
            max-width: 200px;
        }

        .treasure-box:hover {
            background-color: #ec971f;
        }

        .treasure-content {
            background: #fff9e6;
            color: #8a6d3b;
            font-weight: 600;
            border: 2px solid #f0ad4e;
            border-radius: 14px;
            padding: 18px 22px;
            max-width: 380px;
            margin: 20px auto;
            text-align: center;
            box-shadow: 0 0 12px rgba(240, 173, 78, 0.5);
        }

        /* 客服機器人按鈕 */
        #chatbot-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #23e0f9, #0052cc);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 28px;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(0, 82, 204, 0.4);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        #chatbot-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 10px 32px rgba(0, 82, 204, 0.6);
        }

        /* 聊天視窗 */
        #chatbot-window {
            position: fixed;
            bottom: 112px;
            right: 30px;
            width: 330px;
            max-height: 500px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(14px);
            border-radius: 20px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 1000;
            animation: fadeInUp 0.3s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* 標題列 */
        #chatbot-header {
            background: linear-gradient(135deg, #4A90E2, #0052cc);
            color: white;
            padding: 0 18px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
        }

        #chatbot-close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-top: 5px;
            margin-right: -15px;

        }

        #chatbot-close-btn:hover {
            transform: rotate(90deg);
        }

        /* 訊息區 */
        #chatbot-messages {
            flex: 1;
            padding: 20px 18px 14px 18px;
            overflow-y: auto;
            font-size: 14px;
            background-color: #f5f8ff;
        }

        /* 訊息泡泡 */
        .chatbot-message {
            margin-bottom: 12px;
            padding: 10px 14px;
            border-radius: 20px;
            max-width: 90%;
            word-wrap: break-word;
            line-height: 1.4;
            animation: fadeIn 0.2s ease;
            justify-content: center; 

        }

        .chatbot-message.user {
            background: linear-gradient(135deg, #0052cc, #4A90E2);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .chatbot-message.bot {
            background-color:rgb(210, 226, 251);
            color: #003366;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* 輸入區 */
        #chatbot-input-area {
            border-top: 1px solid #dcdcdc;
            padding: 8px 12px 15px 12px;
            display: flex;
            gap: 15px;
            background-color: white;
        }

        #chatbot-input {
            flex: 1;
            border-radius: 20px;
            border: 1px solid #ccc;
            padding: 10px 14px;
            font-size: 14px;
            outline: none;
            transition: border 0.2s ease;
            height: 20px; /* ⭐ 設定固定高度（你可以調整這個值） */
            margin-top: 17px;
        }

        #chatbot-input:focus {
            border-color: #4A90E2;
        }

        #chatbot-send-btn {
            background: linear-gradient(135deg, #4A90E2, #0052cc);
            border: none;
            color: white;
            border-radius: 20px;
            padding: 10px 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        #chatbot-send-btn:hover {
            background: linear-gradient(135deg, #0052cc, #003d99);
            transform: scale(1.05);
        }

        .button-group {
            display: flex;
            justify-content: center; 
            gap: 25px;
            margin-top: 18px;
        }

        .custom-button {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        /* 手動編輯按鈕風格 */
        .custom-button.manual {
            background-color: #e0f7e9;
            color: #2e7d32;
            padding: 10px 70px;
        }

        .custom-button.manual:hover {
            background-color: #c5efd6;
            transform: translateY(-2px);
        }

        /* AI 協助按鈕風格 */
        .custom-button.ai {
            background-color: #e3ecff;
            color: #0052cc;
            padding: 10px 50px;

        }

        .custom-button.ai:hover {
            background-color: #cfdfff;
            transform: translateY(-2px);
        }


    </style>
</head>
<body>

<div class="navbar">
    <div class="left">
        <strong>AI DebugCamp</strong>
        <a href="index.php">首頁</a>
        <a href="dashboard.php">挑戰題目</a>
        <a href="upload_question.php">上傳題目</a>
        <a href="settings.php">設定</a>
    </div>

    <div class="right user-info">
        <span><?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="logout.php">登出</a>
        <img src="<?= htmlspecialchars($photo) ?>" alt="大頭貼">
    </div>
</div>

<div class="center">
        <span>🎉 積分：<?= htmlspecialchars($userScore) ?> 分</span>
</div>

<div class="container">
    <div class="progress-label">
        挑戰進度：<?= $currentBoxProgress ?>/5 題（第 <?= $currentBox ?> 階段）
    </div>
    <div class="progress-bar">
        <?php for ($i = 1; $i <= 5; $i++) echo $i <= $currentBoxProgress ? '🔵 ' : '⚪ '; ?>
    </div>

    <div class="progress-label">
        <?php
        foreach ($claimedBoxes as $boxNum) echo "✅ 第 {$boxNum} 個寶箱已領取 ";
        if (!$nextBoxClaimed) {
            echo '<form method="POST" style="display:inline-block;">
                    <input type="hidden" name="claim_treasure" value="' . $currentBox . '">
                    <button type="submit" class="treasure-box">🎁 第 ' . $currentBox . ' 個寶箱</button>
                  </form>';
        }
        ?>
    </div>

    <?php if ($inputOutputCleared): ?>
        <div id="inputOutputTreasure" class="treasure-content" style="display:block;">
            恭喜完成「輸入輸出」階段！獲得 30 積分獎勵寶箱 🎉
        </div>
    <?php endif; ?>

    <?php if ($ifCleared): ?>
        <div id="ifTreasure" class="treasure-content" style="display:block;">
            恭喜完成「if」階段！獲得 30 積分獎勵寶箱 🎉
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['treasure_message'])): ?>
        <div class="treasure-content" style="display:block;">
            <?= htmlspecialchars($_SESSION['treasure_message']) ?>
        </div>
        <?php unset($_SESSION['treasure_message']); ?>
    <?php endif; ?>

    <?php if ($question): ?>
        <h3>題目類型：<?= htmlspecialchars($question['type']) ?></h3>
        <h2>題目說明：</h2>
        <pre><?= htmlspecialchars($question['prompt']) ?></pre>        
        <h2>錯誤的 Python 程式碼：</h2>
        <pre><?= htmlspecialchars($question['wrong_code']) ?></pre>
        <h4>! 提交答案、請AI協助都會增加作答次數哦 !</h4>

        <div class="button-group">
            <form method="post" action="manual_edit.php">
                <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                <button type="submit" class="custom-button manual">這題我會 !</button>
            </form>
            <form method="post" action="ai_assist.php">
                <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                <button type="submit" class="custom-button ai">請 AI 幫幫我 !</button>
            </form>
        </div>

    <?php else: ?>
        <h2>恭喜你已完成所有題目！</h2>
    <?php endif; ?>
</div>

<!-- 客服機器人按鈕 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<button id="chatbot-btn"><i class="fas fa-robot"></i></button>

<!-- 客服機器人視窗 -->
<div id="chatbot-window" role="dialog" aria-modal="true" aria-labelledby="chatbot-header" aria-describedby="chatbot-messages">
    <div id="chatbot-header">
        Python小助手
        <button id="chatbot-close-btn" aria-label="關閉客服機器人視窗">✖</button>
    </div>
    <div id="chatbot-messages" tabindex="0"></div>
    <div id="chatbot-input-area">
        <input type="text" id="chatbot-input" placeholder="輸入你的問題..." aria-label="客服機器人輸入框">
        <button id="chatbot-send-btn" aria-label="送出訊息">送出</button>
    </div>
</div>

<script>
    const chatbotBtn = document.getElementById('chatbot-btn');
    const chatbotWindow = document.getElementById('chatbot-window');
    const chatbotCloseBtn = document.getElementById('chatbot-close-btn');
    const chatbotMessages = document.getElementById('chatbot-messages');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotSendBtn = document.getElementById('chatbot-send-btn');

    chatbotBtn.addEventListener('click', () => {
        chatbotWindow.style.display = 'flex';
        chatbotInput.focus();
    });

    chatbotCloseBtn.addEventListener('click', () => {
        chatbotWindow.style.display = 'none';
    });

    function appendMessage(content, sender) {
        const msg = document.createElement('div');
        msg.className = 'chatbot-message ' + sender;
        msg.textContent = content;
        chatbotMessages.appendChild(msg);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    async function sendMessage() {
        const userText = chatbotInput.value.trim();
        if (!userText) return;

        appendMessage(userText, 'user');
        chatbotInput.value = '';
        chatbotInput.disabled = true;
        chatbotSendBtn.disabled = true;

        try {
            const response = await fetch('ai_guide.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: userText })
            });
            if (!response.ok) {
                throw new Error('伺服器錯誤');
            }
            const data = await response.json();
            if (data.error) {
                appendMessage('錯誤：' + data.error, 'bot');
            } else {
                appendMessage(data.reply || '抱歉，我沒聽懂您的問題。', 'bot');
            }
        } catch (error) {
            appendMessage('無法連接到伺服器，請稍後再試。', 'bot');
        } finally {
            chatbotInput.disabled = false;
            chatbotSendBtn.disabled = false;
            chatbotInput.focus();
        }
    }

    chatbotSendBtn.addEventListener('click', sendMessage);
    chatbotInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') sendMessage();
        
    });
    // 👇 這裡是新增的功能（點擊視窗外關閉）
    document.addEventListener('click', function (e) {
        const isClickInside = chatbotWindow.contains(e.target) || chatbotBtn.contains(e.target);
        if (!isClickInside) {
            chatbotWindow.style.display = 'none';
        }
    });
</script>

</body>
</html>
