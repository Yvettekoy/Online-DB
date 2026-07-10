<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT username, score, profile_photo FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$username = $user['username'] ?? '訪客';
$score = $user['score'] ?? 0;
$photo = !empty($user['profile_photo']) ? $user['profile_photo'] : 'default_avatar.jpeg';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>AI DebugCamp - 首頁</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', 'Noto Sans TC', sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
        }

        .navbar {
            background-color: #1e293b;
            padding: 14px 2%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .navbar .left, .navbar .right {
            display: flex;
            align-items: center;
        }

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

        .navbar strong {
            color:rgb(35, 224, 249);
            margin-right: 20px;
            font-size: 18px;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info span {
            margin-right: 12px;
            font-weight: 500;
        }

        .navbar img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #23e0f9;
            margin-left: 10px;

        }

        .main-title {
            text-align: center;
            margin: 60px auto 50px;
            font-size: 32px;
            color: #ffffff;
        }

        .container {
            max-width: 500px;
            margin: 20px auto 80px;
            padding: 40px;
            background-color: #1e293b;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.5s ease;
        }

        .center-wrapper {
            justify-content: flex-start;  /* 不垂直置中，從上方開始 */
            text-align: center;
            padding-top: 65px;            /* 與上方距離 */
        }

        .score {
            font-weight: 600;
            font-size: 23px;
            margin-bottom: 50px;
            color: #ffffff;
        }

        .button-group {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .button {
            padding: 14px 24px;
            background-color:rgb(59, 190, 246);
            color: white;
            text-decoration: none;
            font-size: 17px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .button:hover {
            background-color:rgb(33, 149, 216);
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 600px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .button-group {
                flex-direction: column;
            }
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
        <span><?= htmlspecialchars($username) ?></span>
        <a href="logout.php">登出</a>
        <img src="<?= htmlspecialchars($photo) ?>" alt="大頭貼">
    </div>
</div>

<div class="center-wrapper">
    <h1 class="main-title">歡迎回來，<?= htmlspecialchars($username) ?> 👋</h1>

    <div class="container">
        <div class="score">🎉 目前積分：<?= htmlspecialchars($score) ?> 分</div>

        <div class="button-group">
            <a href="dashboard.php" class="button">開始挑戰題目</a>
            <a href="upload_question.php" class="button">上傳我的題目</a>
        </div>
    </div>
</div>
</body>
</html>
