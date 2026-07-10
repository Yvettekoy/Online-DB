<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
$submitted_code = isset($_POST['submitted_code']) ? trim($_POST['submitted_code']) : '';

if ($question_id === 0 || $submitted_code === '') {
    die('題目或答案缺失');
}

// 先從資料庫取得該題正確的標準答案 (例如存在 questions 表裡)
$stmt = $pdo->prepare("SELECT correct_code FROM questions WHERE id = ?");
$stmt->execute([$question_id]);
$correct_code = $stmt->fetchColumn();

if ($correct_code === false) {
    die('找不到題目');
}

// 判斷使用者提交的程式碼是否「正確」
// 這邊簡單用字串相等作示範，實際可換成更複雜的判斷
$is_correct = (trim($submitted_code) === trim($correct_code));

// 計算給予的積分
$score_awarded = $is_correct ? 5 : 0;

// 寫入 user_answers 表
$insertStmt = $pdo->prepare("
    INSERT INTO user_answers 
        (user_id, question_id, submitted_code, hint_count, score_awarded, last_submit_time, correct, updated_at)
    VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW())
");

// 這裡 hint_count 預設 0，若你有提示計數要自己傳入
$insertStmt->execute([
    $user_id,
    $question_id,
    $submitted_code,
    0,
    $score_awarded,
    $is_correct ? 1 : 0
]);

// 回傳結果給前端或跳轉頁面
if ($is_correct) {
    echo "答對了！獲得 $score_awarded 分。";
} else {
    echo "答案不正確，再試試看！";
}
