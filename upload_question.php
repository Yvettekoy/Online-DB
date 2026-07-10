<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ----- 設定你的資料庫連線 -----
$host = 'localhost';
$dbname = 'python_tutor';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Exception $e) {
    die("DB連線錯誤：" . $e->getMessage());
}

// 這裡用 session 模擬登入使用者ID
$userId = $_SESSION['user_id'] ?? 1; // 改成實際登入時的user_id

// ------------------
// 直接在程式碼裡寫 API KEY（請換成你自己的）
define('OPENAI_API_KEY', '');
define('ASSISTANT_ID', '');

// 讀使用者資料 (username, score, profile_photo)
$stmt = $pdo->prepare("SELECT username, score, profile_photo FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die("使用者不存在");
}
$username = $user['username'];
$score = (int)$user['score'];
$photo = !empty($user['profile_photo']) ? $user['profile_photo'] : 'default_avatar.jpeg'; // 預設圖放同資料夾

// 呼叫 OpenAI API 函式
function call_openai_api($url, $method, $data = null, $apiKey = '')
{
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
    if (curl_errno($ch)) {
        curl_close($ch);
        return ['error' => curl_error($ch)];
    }
    curl_close($ch);
    return json_decode($result, true);
}

// 檢查答案
function check_answer($userAnswer, $correctAnswer)
{
    return mb_strtolower(trim($userAnswer)) === mb_strtolower(trim($correctAnswer));
}

// 使用 OpenAI 產生錯誤程式碼解釋 & 引導題目
function openai_generate_explanation_and_questions($wrong_code)
{
    $apiKey = OPENAI_API_KEY;
    $assistantId = ASSISTANT_ID;

    // 1. 建立 Thread
    $thread = call_openai_api("https://api.openai.com/v1/threads", "POST", [], $apiKey);
    $thread_id = $thread['id'] ?? null;
    if (!$thread_id) return ['error' => "無法建立執行緒"];

    // 2. 傳送使用者訊息
    $prompt = "請幫我解釋以下 Python 錯誤程式碼並出兩題選擇題引導學習，格式為 JSON:
    程式碼：
    {$wrong_code}

    ⚠️ 規則：
    1. JSON 每題一定要有 question、choices、answer 三欄位。
    2. **answer 只回傳 A、B、C 或 D 的大寫字母，不要帶句子或符號。**

    格式範例：{
    \"explanation\": \"...\",
    \"questions\": [
        {\"question\":\"...\",\"choices\":[\"A. ...\",\"B. ...\",\"C. ...\",\"D. ...\"],\"answer\":\"A\"},
        ...
    ]
    }";

    call_openai_api("https://api.openai.com/v1/threads/{$thread_id}/messages", "POST", [
        "role" => "user",
        "content" => $prompt
    ], $apiKey);

    // 3. 建立 Run
    $run = call_openai_api("https://api.openai.com/v1/threads/{$thread_id}/runs", "POST", [
        "assistant_id" => $assistantId
    ], $apiKey);
    $run_id = $run['id'] ?? null;
    if (!$run_id) return ['error' => "無法建立 AI 執行"];

    // 4. 輪詢直到完成（最多 10 次）
    for ($i = 0; $i < 10; $i++) {
        sleep(2);
        $status = call_openai_api("https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}", "GET", null, $apiKey);
        if (isset($status['status']) && $status['status'] === 'completed') break;
    }

    // 5. 取訊息
    $messages = call_openai_api("https://api.openai.com/v1/threads/{$thread_id}/messages", "GET", null, $apiKey);
    $content = $messages['data'][0]['content'][0]['text']['value'] ?? null;
    if (!$content) return ['error' => "無法取得 AI 回應"];

    // 嘗試解 JSON
    $json = json_decode($content, true);
    if (!$json) {
        // 嘗試用正則把 json 摘出
        preg_match('/\{.*\}/s', $content, $matches);
        if (isset($matches[0])) {
            $json = json_decode($matches[0], true);
        }
    }
    if (!$json) return ['error' => "AI 回傳格式錯誤，請稍後再試"];

    return $json;
}

// 產生修正後程式碼
function openai_generate_correct_code($wrong_code)
{
    $apiKey = OPENAI_API_KEY;
    $assistantId = ASSISTANT_ID;

    // 建立 debug log 開始
    $log = [];

    // 1. 建立 Thread
    $thread = call_openai_api("https://api.openai.com/v1/threads", "POST", [], $apiKey);
    $log['thread'] = $thread;
    $thread_id = $thread['id'] ?? null;
    if (!$thread_id) {
        $log['error'] = '無法建立執行緒';
        file_put_contents(__DIR__ . "/correct_code_debug.log", print_r($log, true));
        return "無法建立執行緒";
    }

    // 2. 傳送使用者訊息
    $prompt = "請幫我修正以下 Python 程式碼，並只輸出修正後的完整程式碼，不需要多餘解釋：\n\n" . $wrong_code;
    $messageRes = call_openai_api("https://api.openai.com/v1/threads/{$thread_id}/messages", "POST", [
        "role" => "user",
        "content" => $prompt
    ], $apiKey);
    $log['user_message'] = $messageRes;

    // 3. 建立 Run
    $run = call_openai_api("https://api.openai.com/v1/threads/{$thread_id}/runs", "POST", [
        "assistant_id" => $assistantId
    ], $apiKey);
    $log['run'] = $run;

    $run_id = $run['id'] ?? null;
    if (!$run_id) {
        $log['error'] = '無法建立 AI 執行';
        file_put_contents(__DIR__ . "/correct_code_debug.log", "enter openai_generate_correct_code()\n", FILE_APPEND);

        return "無法建立 AI 執行";
    }

    // 4. 輪詢直到完成（最多 10 次）
    for ($i = 0; $i < 10; $i++) {
        sleep(2);
        $status = call_openai_api("https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}", "GET", null, $apiKey);
        $log["poll_{$i}"] = $status;
        if (isset($status['status']) && $status['status'] === 'completed') break;
    }

    // 5. 取訊息
    $messages = call_openai_api("https://api.openai.com/v1/threads/{$thread_id}/messages", "GET", null, $apiKey);
    $log['final_messages'] = $messages;

    $content = $messages['data'][0]['content'][0]['text']['value'] ?? "無法取得修正程式碼";

    $log['final_output'] = $content;
    file_put_contents(__DIR__ . "/correct_code_debug.log", print_r($log, true));


    return $content;
}

// -------------
// 處理流程

$message = '';
$step = $_POST['step'] ?? 'input_code';

if ($step === 'input_code') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['wrong_code'])) {
        $wrong_code = trim($_POST['wrong_code']);
        if ($score < 10) {
            $message = "積分不足（需要 10 分）";
        } else {
            // 扣分
            $pdo->prepare("UPDATE users SET score = score - 10 WHERE id = ?")->execute([$userId]);
            $score -= 10;

            // 呼叫 AI 產生解釋跟題目
            $res = openai_generate_explanation_and_questions($wrong_code);

            if (isset($res['error'])) {
                $message = "AI 錯誤：" . htmlspecialchars($res['error']);
            } else {
                // 存 session
                $_SESSION['wrong_code'] = $wrong_code;
                $_SESSION['explanation'] = $res['explanation'] ?? '無解釋內容';
                $_SESSION['guideQuestions'] = $res['questions'] ?? [];
                $_SESSION['current_question'] = 0;
                $_SESSION['answers'] = [];
                $step = 'question';
            }
        }
    }
} elseif ($step === 'question') {
    if (!isset($_SESSION['wrong_code'], $_SESSION['guideQuestions'], $_SESSION['current_question'])) {
        $message = "流程錯誤，請重新開始";
        $step = 'input_code';
    } else {
        $currentQ = $_SESSION['current_question'];
        $guideQuestions = $_SESSION['guideQuestions'];
        function letterOnly($str) {
            return preg_match('/[A-D]/i', $str, $m) ? strtoupper($m[0]) : strtoupper(trim($str));
        }
        $userAnswer = letterOnly($_POST['answer'] ?? '');


        // 把「A. 缺少參數」或「缺少參數」統一轉成 A B C D


        // 從題目物件不同欄位嘗試抓答案字母（answer / correct / correctAnswer）
        $rawAnswer = $guideQuestions[$currentQ]['answer']        ??
                    $guideQuestions[$currentQ]['correct']       ??
                    $guideQuestions[$currentQ]['correctAnswer'] ?? '';

        $correctAnswer = letterOnly($rawAnswer);
        if ($correctAnswer === '') {   // 仍然抓不到才算格式錯誤

            $message = "AI 題目格式錯誤，請重新開始";
            $step = 'input_code';
        } elseif (check_answer($userAnswer, $correctAnswer)) {
            $_SESSION['answers'][$currentQ] = $userAnswer;
            $currentQ++;
            if ($currentQ >= count($guideQuestions)) {
                // 答完全部題目，存 DB
                $stmt = $pdo->prepare("INSERT INTO ai_explanations 
                    (user_id, wrong_code, explanation, guide_question1, guide_answer1, guide_question2, guide_answer2) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId,
                    $_SESSION['wrong_code'],
                    $_SESSION['explanation'],
                    $guideQuestions[0]['question'] ?? '',
                    $_SESSION['answers'][0] ?? '',
                    $guideQuestions[1]['question'] ?? '',
                    $_SESSION['answers'][1] ?? '',
                ]);
                $step = 'done';
            } else {
                $_SESSION['current_question'] = $currentQ;
            }
        } else {
            $message = "答案錯誤，請再試一次";
        }
    }
} elseif ($step === 'done') {
    if (!isset($_SESSION['wrong_code'])) {
        $message = "流程錯誤，請重新開始";
        $step = 'input_code';
    } else {
        if (empty($_SESSION['correct_code'])) {
            $_SESSION['correct_code'] = openai_generate_correct_code($_SESSION['wrong_code']);
        }
        $correctCode = $_SESSION['correct_code']; // 這行要加，讓畫面能正確顯示
    }
}



// 讀歷史紀錄（最近5筆）
$historyStmt = $pdo->prepare("SELECT * FROM ai_explanations WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$historyStmt->execute([$userId]);
$historyList = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8" />
    <title>AI DebugCamp - 上傳題目</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
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
        .sidebar {
            position: fixed;
            top: 117px;
            left: 110px;
            width: 360px;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06); /* 跟 container 的陰影一致 */
            font-family: 'Segoe UI', sans-serif;
            z-index: 1000;
            overflow: hidden;
            border: 1px solid #e0e0e0;
            animation: fadeIn 0.5s ease;

        }

        .sidebar-header {
            background: linear-gradient(135deg, #0078d7, #005bb5);
            color: white;
            padding: 16px 20px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            border-radius: 16px 16px 0 0; /* 跟 container 一致圓角 */
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s ease;
        }

        .sidebar-header:hover {
            background: linear-gradient(135deg, #005bb5, #004a99);
        }

        .sidebar-content {
            max-height: 350px;
            overflow-y: auto;
            padding: 20px;
            background-color: #fafafa;
            display: none;
            border-radius: 0 0 16px 16px;
        }

        .sidebar-content.show {
            display: block;
        }

        .sidebar-content pre {
            background-color: #f9f9f9;
            padding: 12px;
            border-radius: 12px;
            overflow-x: auto;
            font-size: 14px;
            box-shadow: inset 0 0 6px rgba(0,0,0,0.05);
        }

        .sidebar-content .explanation {
            background-color: #ffffff;
            padding: 12px 16px;
            border-left: 5px solid #0078d7;
            margin-top: 10px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-wrap;
            box-shadow: 0 4px 12px rgba(0, 120, 215, 0.1);
        }

        .question-choice-btn {
            margin: 5px 0;
        }
        
        pre {
            background: #272822;
            color: #000000;
            text-shadow:
                1px 1px 0 #ffffff,
                -1px 1px 0 #ffffff,
                1px -1px 0 #ffffff,
                -1px -1px 0 #ffffff; 
            padding: 15px;
            border-radius: 6px;
            white-space: pre-wrap;
        }

        .container {
            max-width: 960px;
            margin: 45px auto 25px 570px; /* 讓主內容避開側邊欄 */
            background-color: #ffffff;
            padding: 10px 40px 40px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* 中間積分區塊 */
        .center {
            display: flex;
            align-items: left;
            flex: 1;
            color: #272822;
            font-weight: 550;
            font-size: 23px;
            margin: 30px 0;
        }

        .button-group {
            display: flex;
            justify-content: center; 
            gap: 25px;
            margin-top: 35px;
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

        /* AI 協助按鈕風格 */
        .custom-button.ai {
            background-color: #e3ecff;
            color: #0052cc;
            padding: 10px 150px;
        }

        .custom-button.ai:hover {
            background-color: #cfdfff;
            transform: translateY(-2px);
        }

        textarea {
            height: 300px;
        }

        .mb-3 {
            margin-top: 30px;
        }

        h3 {
            margin-top: 15px;
            font-size: 23px;
        }

        .btn.btn-success.mt-2 {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            background-color: #e0f7e9;
            color: #2e7d32;
            padding: 10px 50px;
        }

        .btn.btn-success.mt-2:hover {
            background-color: #c5efd6;
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

<!-- 左側摺疊歷史紀錄 -->
<div class="sidebar" id="historySidebar">
    <div class="sidebar-header" onclick="toggleHistory()">
        歷史紀錄（最近5筆）
        <span id="toggleArrow">▼</span>
    </div>
    <div class="sidebar-content" id="historyContent">
        <?php if (count($historyList) === 0): ?>
            <p class="text-muted small">尚無歷史紀錄</p>
        <?php else: ?>
            <?php foreach ($historyList as $h): ?>
                <div class="mb-4 border-bottom pb-3">
                    <strong>錯誤程式碼：</strong>
                    <pre><?=htmlspecialchars($h['wrong_code'])?></pre>
                    <strong>AI 解釋：</strong>
                    <div class="explanation"><?=nl2br(htmlspecialchars($h['explanation']))?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


</div>

<div class="container">
    <?php if ($step === 'input_code'): ?>
        
        <div class="center">
                <span>🎉 目前積分：<?= htmlspecialchars($score) ?> 分</span>
        </div>

        <h3>貼上你的錯誤 Python 程式碼（扣 10 積分，AI 幫你解釋並出題引導）</h3>
        <?php if ($message): ?>
            <div class="alert alert-danger"><?=htmlspecialchars($message)?></div>
        <?php endif; ?>
        <form method="post" class="mt-3">
            <textarea name="wrong_code" class="form-control" rows="10" placeholder="請貼上錯誤的 Python 程式碼" required><?=htmlspecialchars($_POST['wrong_code'] ?? '')?></textarea>
            <input type="hidden" name="step" value="input_code" />

            <div class="button-group">
                <button type="submit" class="custom-button ai">送出</button>
            </div>
        </form>

    <?php elseif ($step === 'question'): ?>
        <h3>AI 引導題目（請選出正確答案）</h3>
        <div class="mb-3">
            <strong>你提交的錯誤程式碼：</strong>
            <pre><?= htmlspecialchars($_SESSION['wrong_code']) ?></pre>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-danger"><?=htmlspecialchars($message)?></div>
        <?php endif; ?>
        <?php
        $currentQ = $_SESSION['current_question'];
        $guideQuestions = $_SESSION['guideQuestions'];
        $q = $guideQuestions[$currentQ] ?? null;
        ?>
        <?php if ($q): ?>
            <form method="post" class="mt-3">
                <div class="mb-3"><strong>題目<?=($currentQ + 1)?>：</strong><br><?=htmlspecialchars($q['question'])?></div>
                <?php foreach ($q['choices'] as $choice): 
                    $val = strtoupper(trim(substr($choice, 0, 1)));
                ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="answer" id="choice<?=$val?>" value="<?=$val?>" required>
                        <label class="form-check-label" for="choice<?=$val?>"><?=htmlspecialchars($choice)?></label>
                    </div>
                <?php endforeach; ?>
                <input type="hidden" name="step" value="question" />
                <button type="submit" class="btn btn-success mt-2">送出答案</button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">找不到題目，請重新貼上錯誤程式碼</div>
        <?php endif; ?>

        <?php elseif ($step === 'done'): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <h3 style="margin: 0;">恭喜你完成引導題目！</h3>
                <a href="upload_question.php" class="btn btn-success mt-2" style="white-space: nowrap;">重新開始</a>
            </div>
        <?php endif; ?>
</div>

<script>
function toggleHistory() {
    const content = document.getElementById('historyContent');
    content.classList.toggle('show');
}
</script>

<!-- Bootstrap 5 JS (Popper + Bootstrap) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
