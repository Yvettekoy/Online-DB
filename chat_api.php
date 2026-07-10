<?php
header('Content-Type: text/plain; charset=utf-8');

$apiKey = '';
$assistantId = '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['question'])) {
    echo "請輸入有效的問題。";
    exit;
}

$question = trim($_POST['question']);
if (empty($question)) {
    echo "問題不能為空。";
    exit;
}

// 建立 thread
$thread = createThread($apiKey);
if (!$thread) {
    echo "無法建立對話。";
    exit;
}

// 加入使用者訊息
addMessageToThread($apiKey, $thread, $question);

// 執行 Assistant Run
$runId = runAssistant($apiKey, $assistantId, $thread);

// 等待結果
$replyJson = waitForCompletion($apiKey, $thread, $runId);
if (!$replyJson) {
    echo "AI 沒有回覆。";
    exit;
}

// 解析 JSON 拿出文字
$data = json_decode($replyJson, true);
if (isset($data['answer'])) {
    echo $data['answer'];
} else {
    echo $replyJson;  // 如果格式怪怪的，直接輸出
}

// 函式們
function createThread($apiKey) {
    $ch = curl_init("https://api.openai.com/v1/threads");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json",
            "OpenAI-Beta: assistants=v2"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}'
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    return $data['id'] ?? false;
}

function addMessageToThread($apiKey, $threadId, $content) {
    $ch = curl_init("https://api.openai.com/v1/threads/$threadId/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json",
            "OpenAI-Beta: assistants=v2"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "role" => "user",
            "content" => $content
        ])
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function runAssistant($apiKey, $assistantId, $threadId) {
    $ch = curl_init("https://api.openai.com/v1/threads/$threadId/runs");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json",
            "OpenAI-Beta: assistants=v2"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "assistant_id" => $assistantId
        ])
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    return $data['id'] ?? false;
}

function waitForCompletion($apiKey, $threadId, $runId) {
    for ($i = 0; $i < 20; $i++) {
        sleep(1);
        $ch = curl_init("https://api.openai.com/v1/threads/$threadId/runs/$runId");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $apiKey",
                "Content-Type: application/json",
                "OpenAI-Beta: assistants=v2"
            ]
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($res, true);
        if (isset($data['status']) && $data['status'] === 'completed') {
            // 取得 messages
            $ch = curl_init("https://api.openai.com/v1/threads/$threadId/messages");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $apiKey",
                    "Content-Type: application/json",
                    "OpenAI-Beta: assistants=v2"
                ]
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $msgs = json_decode($res, true);
            foreach ($msgs['data'] as $msg) {
                if ($msg['role'] === 'assistant') {
                    // 直接回傳 assistant 文字內容 (JSON 字串)
                    return $msg['content'][0]['text']['value'] ?? false;
                }
            }
        }
    }
    return false;
}
?>
