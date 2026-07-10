<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, profile_photo FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$photo = !empty($user['profile_photo']) ? $user['profile_photo'] : 'default_avatar.jpeg';

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AI DebugCamp - 帳號設定</title>
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

        /* 中間積分區塊 */
        .center {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            color: #ffffff;
            font-weight: 500;
            font-size: 23px;
            margin: 30px 0;

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
        .container {
            display: flex;
            justify-content: center;
            padding: 40px 0;
            padding-top: 80px;            /* 與上方距離 */
        }

        .settings-card {
            background-color: #1e293b;
            color: #e2e8f0;
            padding: 15px 30px;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 450px;
            animation: fadeIn 0.5s ease;
        }

        .settings-card h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #23e0f9;
        }

        .settings-card label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .settings-card input[type="password"],
        .settings-card input[type="file"] {
            width: 95%;
            padding: 13px 10px;
            border: none;
            border-radius: 8px;
            background-color: #334155;
            color: #f1f5f9;
        }

        .current-photo {
            text-align: center;
            margin-top: 20px;
        }

        img.profile {
            max-width: 100px;
            border-radius: 50%;
            border: 2px solid #38bdf8;
            margin-top: 8px;
        }

        .update-btn {
            width: 100%;
            margin-top: 30px;
            padding: 10px;
            background-color: #38bdf8;
            color:rgb(255, 255, 255);
            font-weight: bold;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            font-size: 15px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .update-btn:hover {
            background-color: #0ea5e9;
            transform: translateY(-2px);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 15px;
        }

        .back-link:hover {
            color: #38bdf8;
        }

        /* 動畫效果 */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #preview {
            margin: 10px auto 0 auto;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: none;
            object-fit: cover;
            border: 1px solid #334155;
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
    <div class="container">
        <div class="settings-card">
            
            <h2>帳號設定</h2>
            <form action="update_settings.php" method="POST" enctype="multipart/form-data">
                <label for="new_password">新密碼（可留空）：</label>
                <input type="password" name="new_password" id="new_password" placeholder="留空則不更改密碼" />

                <label for="profile_photo">上傳新大頭貼（限1.05MB內圖片）：</label>
                <input type="file" name="profile_photo" id="profile_photo" accept="image/*" onchange="previewImage(event)" />

                <!-- ✅ 預覽圖片容器 -->
                <img id="preview" alt="圖片預覽">

                <?php if (!empty($user['profile_photo'])): ?>
                    <div class="current-photo">
                        <label>目前照片：</label>
                        <img class="profile" src="<?= htmlspecialchars($user['profile_photo']) ?>" alt="大頭貼">
                    </div>
                <?php endif; ?>

                <button class="update-btn" type="submit">更新資料</button>
            </form>

            <a class="back-link" href="index.php">返回首頁</a>
        </div>
    </div>

<script>
function previewImage(event) {
    const preview = document.getElementById('preview');
    const file = event.target.files[0];
    if (file) {
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
        preview.src = '';
    }
}
</script>


</body>
</html>
