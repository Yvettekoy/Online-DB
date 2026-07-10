<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => '只允許 POST 請求']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_input = trim($input['message'] ?? '');

if (empty($user_input)) {
    echo json_encode(['error' => '請輸入訊息']);
    exit;
}

$api_key = 'sk';  // 

$headers = [
    "Content-Type: application/json",
    "Authorization: Bearer {$api_key}"
];

$post_data = [
    'model' => 'gpt-4o-mini',
    'messages' => [
        [
            'role' => 'system',
            'content' => '妳是Python高手，擅長用最精簡的方式回覆使用者提出的問題，如果收到跟程式無關的訊息就兇狠回覆"請不要浪費我的時間"。'
        ],
        [
            'role' => 'user',
            'content' => $user_input
        ]
    ],
    'max_tokens' => 500,
    'temperature' => 0.3,
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.openai.com/v1/chat/completions",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($post_data),
]);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(['error' => '發送訊息失敗: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$data = json_decode($response, true);

$reply = $data['choices'][0]['message']['content'] ?? '';

if (!$reply) {
    echo json_encode(['error' => '未取得回覆', 'api_response' => $data]);
    exit;
}

echo json_encode(['reply' => $reply]);
