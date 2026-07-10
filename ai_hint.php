<?php
require 'db.php';
session_start();

$OPENAI_API_KEY = '';
$ASSISTANT_ID = '';

// 接收題目 ID
$questionId = $_POST['question_id'] ?? null;

if (!$questionId) {
    echo json_encode(["error" => "缺少題目 ID"]);
    exit;
}

// 從資料庫取得題目內容
$stmt = $pdo->prepare("SELECT question_text FROM questions WHERE id = ?");
$stmt->execute([$questionId]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    echo json_encode(["error" => "找不到該題目"]);
    exit;
}

$questionText = $question['question_text'];

// 記錄提示次數
if (!isset($_SESSION['hint_counts'])) {
    $_SESSION['hint_counts'] = [];
}
if (!isset($_SESSION['hint_counts'][$questionId])) {
    $_SESSION['hint_counts'][$questionId] = 1;
} else {
    $_SESSION['hint_counts'][$questionId]++;
}

// 建立對話執行緒
$thread = create_thread($OPENAI_API_KEY);
if (!$thread) {
    echo json_encode(["error" => "無法建立對話 thread"]);
    exit;
}

// 加入 user message
add_message_to_thread($OPENAI_API_KEY, $thread['id'], "請針對以下 Python 題目給提示（不要直接給答案）：\n\n$questionText");

// 執行 assistant
$runId = run_assistant($OPENAI_API_KEY, $ASSISTANT_ID, $thread['id']);
if (!$runId) {
    echo json_encode(["error" => "無法啟動 assistant"]);
    exit;
}

// 等待完成
$result = wait_for_run_complete($OPENAI_API_KEY, $thread['id'], $runId);

// 取得 assistant 回覆
$hint = get_latest_message($OPENAI_API_KEY, $thread['id']);
echo json_encode(["hint" => $hint]);


// === 以下是 Assistant API 所需函式 ===

function create_thread($apiKey) {
    $url = "https://api.openai.com/v1/threads";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ]
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function add_message_to_thread($apiKey, $threadId, $content) {
    $url = "https://api.openai.com/v1/threads/$threadId/messages";
    $data = [
        "role" => "user",
        "content" => $content
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ]
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function run_assistant($apiKey, $assistantId, $threadId) {
    $url = "https://api.openai.com/v1/threads/$threadId/runs";
    $data = [ "assistant_id" => $assistantId ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($res, true);
    return $json['id'] ?? null;
}

function wait_for_run_complete($apiKey, $threadId, $runId) {
    $url = "https://api.openai.com/v1/threads/$threadId/runs/$runId";
    for ($i = 0; $i < 10; $i++) {
        sleep(1); // 等待 1 秒
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $apiKey",
                "Content-Type: application/json"
            ]
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($res, true);
        if (($json['status'] ?? '') === 'completed') return true;
    }
    return false;
}

function get_latest_message($apiKey, $threadId) {
    $url = "https://api.openai.com/v1/threads/$threadId/messages";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($res, true);
    return $json['data'][0]['content'][0]['text']['value'] ?? "⚠️ 無法取得提示內容";
}
?>
