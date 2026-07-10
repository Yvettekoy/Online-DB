<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$new_password = $_POST['new_password'] ?? '';
$photo_path = null;

// 上傳處理
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['profile_photo']['tmp_name'];
    $fileName = basename($_FILES['profile_photo']['name']);
    $fileSize = $_FILES['profile_photo']['size'];

    // 限制檔案大小
    if ($fileSize > 1100 * 1024) {
        die("❗ 圖片過大，請小於 1.05MB");
    }

    // 確保是圖片
    $fileInfo = getimagesize($fileTmpPath);
    if ($fileInfo === false) {
        die("❗ 上傳的不是有效圖片檔案");
    }

    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $newName = "uploads/user_" . $user_id . "." . $ext;

    if (!move_uploaded_file($fileTmpPath, $newName)) {
        die("❗ 上傳失敗，請再試一次");
    }

    $photo_path = $newName;
}

// 更新資料
if (!empty($new_password)) {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    if ($photo_path) {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, profile_photo = ? WHERE id = ?");
        $stmt->execute([$hashed, $photo_path, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
    }
} elseif ($photo_path) {
    $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
    $stmt->execute([$photo_path, $user_id]);
}

header("Location: settings.php");
exit;
