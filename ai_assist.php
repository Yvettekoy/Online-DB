<?php 
require 'db.php';
session_start();

// ✅ OpenAI API 設定
$OPENAI_API_KEY = '';
$ASSISTANT_ID = '';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_POST['question_id'])) {
    echo "沒有指定題目。";
    exit;
}

$questionId = $_POST['question_id'];
$user_id = $_SESSION['user_id'];

// 嘗試插入作答紀錄（進入 ai_assist.php 視為作答一次）
$checkStmt = $pdo->prepare("SELECT * FROM question_attempts WHERE user_id = ? AND question_id = ?");
$checkStmt->execute([$user_id, $questionId]);

if ($checkStmt->rowCount() === 0) {
    $insertStmt = $pdo->prepare("INSERT INTO question_attempts (user_id, question_id, attempts) VALUES (?, ?, 1)");
    $insertStmt->execute([$user_id, $questionId]);
}

// 取得題目資料
$stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
$stmt->execute([$questionId]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);
$photo = !empty($user['profile_photo']) ? $user['profile_photo'] : 'default_avatar.jpeg';

if (!$question) {
    echo "題目不存在。";
    exit;
}

// 🧠 呼叫 OpenAI Assistant
$thread = call_openai_api("https://api.openai.com/v1/threads", "POST", [], $OPENAI_API_KEY);
$thread_id = $thread['id'] ?? null;

if (!$thread_id) {
    $error = "⚠️ 無法建立 AI 分析執行緒";
} else {
    // 發送訊息
    call_openai_api("https://api.openai.com/v1/threads/$thread_id/messages", "POST", [
        "role" => "user",
        "content" => "請一步步協助我修正以下 Python 錯誤程式碼，並說明每個步驟原因，用簡單中文。\n\n【錯誤程式碼】:\n" . $question['wrong_code']
    ], $OPENAI_API_KEY);

    // 發起執行
    $run = call_openai_api("https://api.openai.com/v1/threads/$thread_id/runs", "POST", [
        "assistant_id" => $ASSISTANT_ID
    ], $OPENAI_API_KEY);

    $run_id = $run['id'] ?? null;

    // 等待完成（最多 10 秒）
    for ($i = 0; $i < 10; $i++) {
        sleep(2);
        $status = call_openai_api("https://api.openai.com/v1/threads/$thread_id/runs/$run_id", "GET", null, $OPENAI_API_KEY);
        if (isset($status['status']) && $status['status'] === 'completed') {
            break;
        }
    }

    // 取得訊息回覆
    $response = call_openai_api("https://api.openai.com/v1/threads/$thread_id/messages", "GET", null, $OPENAI_API_KEY);

    if (isset($response['data'][0]['content'][0]['text']['value'])) {
        $reply = $response['data'][0]['content'][0]['text']['value'];
    } else {
        $reply = '⚠️ 無法取得 AI 回覆，可能是執行逾時或發生錯誤。';
    }
}

// 取得作答次數
$attemptStmt = $pdo->prepare("SELECT attempts FROM question_attempts WHERE user_id = ? AND question_id = ?");
$attemptStmt->execute([$user_id, $question_id]);
$attemptRow = $attemptStmt->fetch(PDO::FETCH_ASSOC);
$originalAttempts = $attemptRow['attempts'] ?? 0;
$completed = $attemptRow['completed'] ?? 0;

$updatedAttempts = $originalAttempts + 1;

// ✅ API 共用函式
function call_openai_api($url, $method, $data = null, $apiKey = '') {
    $ch = curl_init($url);
    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
        "OpenAI-Beta: assistants=v2"
    ];
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}
?>

<!-- ✅ 美化畫面 -->
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>AI DebugCamp - AI 引導修正</title>
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
        .container {
            max-width: 900px;
            margin: 30px auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        pre {
            background-color: #eef3ff;
            border-left: 6px solid #0052cc;
            padding: 18px;
            border-radius: 10px;
            white-space: pre-wrap;
            font-family: Consolas, monospace;
            font-size: 17px;
        }
        h2 {
            color:rgb(0, 0, 0);
            font-size: 23px;
            margin-top: 15px;
            font-weight: 500;
        }
        
        h3 {
            color:rgb(0, 0, 0);
            font-size: 20px;
            margin-top: 15px;
            font-weight: 500;
        }

        .ai-box {
            background-color: #eef3ff;
            border-left: 6px solid #0052cc;
            padding: 18px;
            border-radius: 10px;
            white-space: pre-wrap;
            font-family: Consolas, monospace;
            font-size: 17px;
        }

        .button-group {
            display: flex;
            justify-content: center; 
        }
    
        .btn {
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            background-color: #e0f7e9;
            color: #2e7d32;
            padding: 10px 100px;
            justify-content: center;
            align-items: center;
        }
        .btn:hover {
            background-color: #c5efd6;
            transform: translateY(-2px);
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
     <span>🤖 AI 引導小助手</span>
</div>
    <div class="container">
        <p>目前已累積作答次數：<?= $updatedAttempts ?> 次</p>

        <h3><strong>題目說明：</strong> <?= htmlspecialchars($question['prompt']) ?></h3>
        <h2><strong>錯誤的 Python 程式碼：</strong></h2>
        <pre><?= htmlspecialchars($question['wrong_code']) ?></pre>

        <div class="ai-box">
            <?= isset($error) ? $error : htmlspecialchars($reply) ?>
        </div>

        <!-- 導向 manual_edit.php 修正 -->
        <form action="manual_edit.php" method="post" style="margin-top: 30px;">
            <input type="hidden" name="question_id" value="<?= htmlspecialchars($questionId) ?>">
            <div class="button-group">
                <button type="submit" class="btn">我明白了 !</button>
            </div>
        </form>
    </div>
</body>
</html>
