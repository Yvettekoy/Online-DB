<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_POST['question_id']) || empty($_POST['corrected_code'])) {
    echo "<p style='color:red;'>缺少題目或程式碼 !</p>";
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$question_id = $_POST['question_id'];
$user_code = $_POST['corrected_code'];
$photo = !empty($user['profile_photo']) ? $user['profile_photo'] : 'default_avatar.jpeg';

$OPENAI_API_KEY = '';
$ASSISTANT_CODE = '';
$ASSISTANT_SIMILAR = '';

// 取得使用者積分
$scoreStmt = $pdo->prepare("SELECT score FROM users WHERE id = ?");
$scoreStmt->execute([$user_id]);
$userScore = $scoreStmt->fetchColumn() ?? 0;

// 取得題目
$stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
$stmt->execute([$question_id]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$question) {
    echo "<p style='color:red;'>題目不存在 !</p>";
    exit;
}

// 取得作答次數
$attemptStmt = $pdo->prepare("SELECT attempts FROM question_attempts WHERE user_id = ? AND question_id = ?");
$attemptStmt->execute([$user_id, $question_id]);
$attemptRow = $attemptStmt->fetch(PDO::FETCH_ASSOC);
$originalAttempts = $attemptRow['attempts'] ?? 0;
$completed = $attemptRow['completed'] ?? 0;

$updatedAttempts = $originalAttempts + 1;

// ======= 檢查使用者答案 =======
function getOpenAIResponse($code, $wrong_code, $apiKey) {
    $prompt = "這段程式碼：\n錯誤程式碼:\n$wrong_code\n使用者修改程式碼:\n$code\n請指出是否正確，錯誤原因及提示，並給一題選擇題（包含選項與正確答案）。格式：
---
判斷: 正確/錯誤
說明: 說明文字
選擇題:
A. 選項一
B. 選項二
C. 選項三
D. 選項四
答案: C
---";

    $postData = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "你是一個 Python 程式碼教學助理。"],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.2,
        "max_tokens" => 500,
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    $result = curl_exec($ch);
    curl_close($ch);

    if (!$result) return false;
    $responseJson = json_decode($result, true);
    return $responseJson['choices'][0]['message']['content'] ?? false;
}

$ai_reply = getOpenAIResponse($user_code, $question['wrong_code'], $OPENAI_API_KEY);
if ($ai_reply === false) {
    echo "<p style='color:red;'>無法取得 AI 回應，請稍後再試 !</p>";
    exit;
}

// ======= 分析 AI 回覆 =======
preg_match('/判斷:\s*(正確|錯誤)/u', $ai_reply, $match_judge);
$is_correct = (isset($match_judge[1]) && $match_judge[1] === '正確');

preg_match('/說明:\s*(.+)/u', $ai_reply, $match_explanation);
$explanation = $match_explanation[1] ?? '';

preg_match('/選擇題:\s*(.*?)答案:/us', $ai_reply, $match_question);
$question_text = trim($match_question[1] ?? '');

preg_match('/答案:\s*([A-D])/u', $ai_reply, $match_answer);
$correct_option = $match_answer[1] ?? '';

// 拆選項
$options = [];
if ($question_text) {
    preg_match_all('/([A-D])\.\s*(.+?)(?=([A-D]\.|$))/s', $question_text, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $options[$m[1]] = trim($m[2]);
    }
}

// ======= 更新得分 =======
$scoreAdd = 0;
if ($is_correct) {
    if ($updatedAttempts == 1) $scoreAdd = 5;
    elseif ($updatedAttempts == 2) $scoreAdd = 3;
    elseif ($updatedAttempts == 3) $scoreAdd = 1;

    $userScore += $scoreAdd;
    $updateScore = $pdo->prepare("UPDATE users SET score = ? WHERE id = ?");
    $updateScore->execute([$userScore, $user_id]);
}

// ======= 更新作答次數及完成狀態 =======
if ($is_correct) {
    // 答對時 attempts +1 且 completed=1
    $updateAttempts = $pdo->prepare("INSERT INTO question_attempts (user_id, question_id, attempts, completed)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE attempts = ?, completed = 1");
    $updateAttempts->execute([$user_id, $question_id, $updatedAttempts, $updatedAttempts]);
} else {
    // 答錯時只更新 attempts，不改 completed
    $updateAttempts = $pdo->prepare("INSERT INTO question_attempts (user_id, question_id, attempts)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE attempts = ?");
    $updateAttempts->execute([$user_id, $question_id, $updatedAttempts, $updatedAttempts]);
}

// ======= 下一題邏輯 =======
$next_question_id = null;
if ($is_correct && $updatedAttempts > 1) {
    function generateSimilarQuestion($wrongCode, $apiKey) {
        $prompt = "根據這段錯誤程式碼設計一個相似但不一樣的新 Python 題目，請提供：
題目：...
錯誤程式碼：
...
正確程式碼：
...
解析：...";

        $postData = [
            "model" => "gpt-4o-mini",
            "messages" => [
                ["role" => "system", "content" => "你是一個 Python 題目產生器。"],
                ["role" => "user", "content" => $prompt . "\n\n錯誤碼如下：\n" . $wrongCode]
            ],
            "temperature" => 0.5,
            "max_tokens" => 700,
        ];

        $ch = curl_init("https://api.openai.com/v1/chat/completions");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        $result = curl_exec($ch);
        curl_close($ch);

        if (!$result) return false;
        $responseJson = json_decode($result, true);
        return $responseJson['choices'][0]['message']['content'] ?? false;
    }

    $new_question_text = generateSimilarQuestion($question['wrong_code'], $OPENAI_API_KEY);
    if ($new_question_text) {
        preg_match('/題目：(.*?)錯誤程式碼：/us', $new_question_text, $m1);
        preg_match('/錯誤程式碼：(.*?)正確程式碼：/us', $new_question_text, $m2);
        preg_match('/正確程式碼：(.*?)解析：/us', $new_question_text, $m3);
        preg_match('/解析：(.*)/us', $new_question_text, $m4);

        $title = trim($m1[1] ?? '');
        $wrong_code = trim($m2[1] ?? '');
        $correct_code = trim($m3[1] ?? '');
        $explanation2 = trim($m4[1] ?? '');

        if ($title && $wrong_code && $correct_code) {
            $insert = $pdo->prepare("INSERT INTO questions (prompt, wrong_code, correct_code, explanation) VALUES (?, ?, ?, ?)");
            $insert->execute([$title, $wrong_code, $correct_code, $explanation2]);
            $next_question_id = $pdo->lastInsertId();
        }
    }
}

if (!$next_question_id) {
    $stmtNext = $pdo->prepare("SELECT id FROM questions WHERE id > ? ORDER BY id ASC LIMIT 1");
    $stmtNext->execute([$question_id]);
    $nextRow = $stmtNext->fetch(PDO::FETCH_ASSOC);
    $next_question_id = $nextRow['id'] ?? null;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>AI DebugCamp - 答案檢查結果</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
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
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #23e0f9;
            margin-left: 10px;
        }

                /* 容器樣式 */
        .container {
            max-width: 1400px;
            margin: 25px auto;
            background-color: #ffffff;
            padding: 30px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            animation: fadeIn 0.5s ease;
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
            margin: 30px 0 0 0;
        }
        .option-btn { 
            margin: 0.3rem; 
            min-width: 200px;
            background-color: #e3ecff;
            color: #0052cc;
            padding: 10px 13px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .option-btn:hover, {
            background-color: #cfdfff;
            transform: translateY(-2px);
            color: #0052cc;
        }
        .option-btn.locked { pointer-events: none; opacity: 0.6; }
        .correct { background-color: #2e7d32 !important; color: white !important; }
        .incorrect { background-color: #dc3545 !important; color: white !important; }
        .hidden { display: none; }
        h4{
            font-size: 20px;
            font-weight: 550;
        }

        .ai-comment-pre {
            white-space: pre-wrap; 
            word-wrap: break-word; 
            max-height: 300px; 
            overflow-y: auto;
        }
        .btn.btn-yellow {
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            background-color:rgb(246, 231, 186);
            color:rgb(125, 96, 46);
            padding: 10px 13px;
            margin-left: 7px;
        }
        .btn.btn-yellow:hover{
            background-color:rgb(239, 228, 197);
            transform: translateY(-2px);
        }

        pre{
            font-size: 18px;
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
        <p>答案檢查結果</p>
</div>

<div class="container">
    <p>目前已累積作答次數：<?= $updatedAttempts ?> 次</p>

    <div class="alert <?= $is_correct ? 'alert-success' : 'alert-danger' ?>">
        <h4><?= $is_correct ? "🎉 恭喜！你的修改正確。" : "程式碼尚未完全正確。" ?></h4>
        <p><strong>本次得分：</strong> <?= $scoreAdd ?> 分</p>
        <hr>
        <p><strong>AI 評論：</strong></p>
        <pre class="ai-comment-pre"><?= nl2br(htmlspecialchars($explanation)) ?></pre>
    </div>

    <?php if (!$is_correct): ?>
        <h5>請依據 AI 評論選出正確答案 !</h5>
    <?php endif; ?>

    <?php if ($question_text && count($options) > 0): ?>
        <div>
            <p><?= nl2br(htmlspecialchars(trim(preg_replace('/答案:.*/s', '', $question_text)))) ?></p>
            <div id="options-container">
                <?php foreach ($options as $key => $text): ?>
                    <button class="btn btn-outline-primary option-btn" data-key="<?= $key ?>"><?= htmlspecialchars("$key. $text") ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div id="retry-btn-container" class="mt-3 hidden">
        <form method="post" action="manual_edit.php">
            <input type="hidden" name="question_id" value="<?= htmlspecialchars($question_id) ?>">
            <button type="submit" class="btn btn-yellow">回到作答區，再修改一次 !</button>
        </form>
    </div>

    <?php if ($is_correct): ?>
        <div class="mt-3">
            <?php if ($next_question_id): ?>
                <a href="manual_edit.php?question_id=<?= $next_question_id ?>" class="btn btn-yellow">接著下一題 !</a>
            <?php else: ?>
                <a href="manual_edit.php?question_id=<?= $next_question_id ?>" class="btn btn-yellow">繼續解鎖下一個單元吧 !</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
        const correctOption = '<?= $correct_option ?>';
        const optionButtons = document.querySelectorAll('.option-btn');
        const retryBtnContainer = document.getElementById('retry-btn-container');

        optionButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const selected = btn.getAttribute('data-key');
                if (selected === correctOption) {
                    btn.classList.add('correct');
                    optionButtons.forEach(b => b.classList.add('locked'));
                    retryBtnContainer.classList.remove('hidden');
                    alert('答對了！你可以重新修改其他題目了。');
                } else {
                    btn.classList.add('incorrect');
                    btn.disabled = true;
                }
            });
        });
    </script>
<div>

</body>
</html>
