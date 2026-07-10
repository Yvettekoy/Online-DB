<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$questionId = $_POST['question_id'] ?? $_GET['question_id'] ?? null;
$source = $_GET['source'] ?? '';  // 來源：ai、submit 或空字串

// 如果沒有題目 ID，就找一題沒作答過的
if (!$questionId) {
    $stmt = $pdo->prepare("
        SELECT q.* FROM questions q
        WHERE q.id NOT IN (
            SELECT question_id FROM question_attempts WHERE user_id = ?
        )
        ORDER BY RAND() LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$question) {
        echo "<p>🎉 你已完成所有題目。</p>";
        exit;
    }
    $questionId = $question['id'];
} else {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$questionId]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$question) {
        echo "<p style='color:red;'>❗ 題目不存在。</p>";
        exit;
    }
}

// 使用者分數
$scoreStmt = $pdo->prepare("SELECT score FROM users WHERE id = ?");
$scoreStmt->execute([$user_id]);
$userScore = $scoreStmt->fetchColumn() ?: 0;

// 如果來源是 ai 或 submit，才加 attempts
if (in_array($source, ['ai', 'submit'])) {
    $stmt = $pdo->prepare("
        INSERT INTO question_attempts (user_id, question_id, attempts)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE attempts = attempts + 1
    ");
    $stmt->execute([$user_id, $questionId]);
}

// 取得目前作答次數
$attemptStmt = $pdo->prepare("SELECT attempts FROM question_attempts WHERE user_id = ? AND question_id = ?");
$attemptStmt->execute([$user_id, $questionId]);
$attemptCount = $attemptStmt->fetchColumn() ?: 0;
$photo = !empty($user['profile_photo']) ? $user['profile_photo'] : 'default_avatar.jpeg';
?>


<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AI DebugCamp - 挑戰題目作答區</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            width: 43px;
            height: 43px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #23e0f9;
            margin-left: 10px;

        }

        .flex-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 50px;
            margin: 50px auto;
            max-width: 1650px;
            padding: 0 20px;
            animation: fadeIn 0.5s ease;
        }

        .left-panel, .right-panel {
            background-color: #ffffff;
            color: #1e293b;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
            flex: 1;
            min-width: 500px;
        }

        .left-panel {
            max-width: 40%;
            height: 75vh; /* 高度為瀏覽器視窗的 70% */
        }

        .right-panel {
            max-width: 65%;
            height: 75vh; /* 高度為瀏覽器視窗的 80% */

        }

        @media (max-width: 900px) {
            .flex-container {
                flex-direction: column;
                align-items: center;
            }

            .left-panel, .right-panel {
                max-width: 100%;
            }
        }

        .score-details {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .score-box {
            width: 100%;
            font-weight: bold;
            font-size: 21px;
            color: #ffffff;
            background: #1e293b;
            padding: 10px 0;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.3s ease;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .section {
            margin-bottom: 30px;
        }

        .section h5 {
            color:rgb(0, 0, 0);
            font-size: 23px;
            margin-top: 30px;
            font-weight: bold; 
        }

        h3 {
            color:rgb(0, 0, 0);
            font-size: 19px;
            margin-top: 30px;
            font-weight: bold; 
        }


        .section p {
            font-size: 16px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        pre {
            background-color: #eef3ff;
            border-left: 6px solid #0052cc;
            padding: 18px;
            border-radius: 10px;
            white-space: pre-wrap;
            font-family: Consolas, monospace;
            font-size: 17px;
            margin-top: 20px;
            max-height: 250px;
        }

        textarea {
            width: 50%;
            height: 320px;
            font-family: monospace;
            background-color: #0f172a;
            color: #e2e8f0;
            border: 1px solid #334155;
            padding: 10px;
            border-radius: 8px;
            margin-top: 8px;
        }

        .button-group {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 40px;
            margin-top: 30px;
        }

        .custom-button {
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        /* 提交答案按鈕（質感綠色） */
        .custom-button.primary {
            padding: 10px 75px;
            background-color: #e0f7e9;
            color: #2e7d32;
        }

        .custom-button.primary:hover {
            background-color: #c5efd6;
            transform: translateY(-2px);
        }

        /* 回上一頁按鈕（淺灰帶藍感） */
        .custom-button.outline {
            padding: 10px 45px;
            background-color:rgb(214, 221, 233);
            color:rgb(56, 64, 77);
        }

        .custom-button.outline:hover {
            background-color: #CBD5E0;
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 600px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .score-details {
                flex-direction: column;
                gap: 10px;
            }

            .btn-group {
                flex-direction: column;
            }
        }

        .copy-btn {
            position: absolute;
            top: 18px;
            right: 18px;
            background: #0f172a;
            color: #e2e8f0;
            border: none;
            padding: 6px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            transition: background 0.3s;
        }
        .copy-btn:hover {
            background: #1e293b;
        }
        .code-container {
            position: relative;
            margin-bottom: 1rem;
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
            <span><?= htmlspecialchars($username) ?></span>
            <a href="logout.php">登出</a>
            <img src="<?= htmlspecialchars($photo) ?>" alt="大頭貼">
        </div>
    </div>

    <div class="flex-container">

        <!-- 左邊：題目與說明 -->
        <div class="left-panel">
            <div class="question-header">
                <div class="score-details">
                    <div class="score-box">🎉 積分：<?= htmlspecialchars($userScore) ?> 分</div>
                </div>
            </div>

            <h3>題目類型：<?= htmlspecialchars($question['type']) ?></h3>

            <div class="section">
                <h5>題目說明：</h5>
                <pre><?= nl2br(htmlspecialchars($question['prompt'])) ?></pre>
            </div>

            <div class="section">
                <h5>錯誤的 Python 程式碼：</h5>
                <div class="code-container">
                    <button class="copy-btn" onclick="copyWrongCode()" title="複製程式碼">
                        📋 複製
                    </button>
                    <pre id="wrongCode"><?= htmlspecialchars($question['wrong_code']) ?></pre>
                </div>
            </div>
        </div>

        <!-- 右邊：作答區 -->
        <div class="right-panel">
            <form method="post" action="check_manual_answer.php" class="answer-form">
                <input type="hidden" name="question_id" value="<?= htmlspecialchars($questionId) ?>">

                <div class="score-details">
                    <div class="score-box">🎯 已作答次數： <?= $attemptCount ?> </div>
                </div>

                <div class="mb-3">
                    <label for="corrected_code" class="form-label" style="color: rgb(0, 0, 0); font-size: 23px; font-weight: bold; margin-top: 8px;">作答區：</label>
                    <textarea id="correctedCode" name="corrected_code" rows="10" class="form-control" required></textarea>
                </div>

                <div class="button-group">
                    <button type="submit" class="custom-button primary">提交答案</button>
                    <button type="button" class="custom-button outline" onclick="window.location.href='dashboard.php'">回上一頁</button>
                </div>
            </form>
        </div>

    </div>
    
    <script>
    window.addEventListener('DOMContentLoaded', () => {
        const wrongCode = document.getElementById('wrongCode').innerText;
        document.getElementById('correctedCode').value = wrongCode;
    });

    function copyWrongCode() {
        const code = document.getElementById("wrongCode").innerText;
        navigator.clipboard.writeText(code).then(() => {
            const btn = document.querySelector('.copy-btn');
            btn.textContent = "✅ 已複製";
            setTimeout(() => {
                btn.textContent = "📋 複製";
            }, 1500);
        }).catch(err => {
            alert("複製失敗：" + err);
        });
    }
    </script>


</body>

</html>
