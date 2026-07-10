<?php
include 'db.php'; // 資料庫連線
session_start();

$id = $_GET['id'] ?? 1;
$sql = "SELECT * FROM questions WHERE id = $id";
$result = $conn->query($sql);
$question = $result->fetch_assoc();
?>

<h2>請找出下列程式碼的錯誤：</h2>
<pre><?= htmlspecialchars($question['prompt']) ?></pre>

<form method="post" action="check_answer.php">
    <textarea name="user_code" rows="10" cols="60"></textarea><br>
    <input type="hidden" name="id" value="<?= $question['id'] ?>">
    <button name="action" value="manual">1. 我會，自己修改</button>
    <button name="action" value="ai">2. 我不會，讓 AI 引導</button>
</form>
