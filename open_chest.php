<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['stage'])) {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stage = (int) $_POST['stage'];

// 確認是否已領取過
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_chests WHERE user_id = ? AND stage = ?");
$stmt->execute([$user_id, $stage]);
if ($stmt->fetchColumn() > 0) {
    header("Location: dashboard.php");
    exit;
}

// 加分機制（可自訂）
$bonusScore = 20;

// 寫入領取紀錄
$insert = $pdo->prepare("INSERT INTO user_chests (user_id, stage) VALUES (?, ?)");
$insert->execute([$user_id, $stage]);

// 更新使用者分數
$update = $pdo->prepare("UPDATE users SET score = score + ? WHERE id = ?");
$update->execute([$bonusScore, $user_id]);

header("Location: dashboard.php");
exit;
