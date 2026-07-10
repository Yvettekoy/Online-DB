<?php
require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $profile_photo = $_FILES['profile_photo'] ?? null;

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->rowCount() > 0) {
            $message = "此帳號已存在，請使用其他帳號。";
        } else {
            $profilePicturePath = '';
            if ($profile_photo && $profile_photo['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true); // 建立 uploads 目錄
                }
                $fileExt = pathinfo($profile_photo['name'], PATHINFO_EXTENSION);
                $safeFileName = uniqid('img_', true) . '.' . $fileExt;
                $uploadFile = $uploadDir . $safeFileName;

                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (in_array($profile_photo['type'], $allowedTypes)) {
                    if (move_uploaded_file($profile_photo['tmp_name'], $uploadFile)) {
                        $profilePicturePath = $uploadFile;
                    } else {
                        $message = "上傳大頭照失敗，請再試一次。";
                    }
                } else {
                    $message = "只允許上傳 JPEG、PNG 或 GIF 格式的圖片。";
                }
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, profile_photo) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $profilePicturePath]);

            $message = "✅ 註冊成功！請 <a href='index.php'>登入</a>";
        }
    } else {
        $message = "請輸入帳號與密碼。";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>AI DebugCamp - 註冊</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', 'Noto Sans TC', sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .card {
            background-color: #1e293b;
            padding: 15px 40px 40px 40px;
            border-radius: 16px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.5s ease;
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
            color: #ffffff;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #cbd5e1;
        }

        input[type="text"],
        input[type="password"],
        input[type="file"] {
            width: 94%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #334155;
            background-color: #0f172a;
            color: #f8fafc;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        #preview {
            margin-top: 10px;
            max-width: 100%;
            border-radius: 10px;
            display: none;
        }

        .btn {
            margin-top: 30px;
            display: block;
            width: 100%;
            padding: 14px;
            background-color: rgb(59, 190, 246);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .btn:hover {
            background-color: rgb(33, 149, 216);
            transform: translateY(-2px);
        }

        .error-message {
            background-color: #dc2626;
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
            font-weight: 500;
        }

        .register-link {
            margin-top: 16px;
            text-align: center;
            font-size: 14px;
        }

        .register-link a {
            color: rgb(59, 190, 246);
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #preview {
            margin: 10px auto 0 auto; /* 上方10px，左右自動置中 */
            width: 120px;     /* 固定寬度 */
            height: 120px;    /* 固定高度，正方形 */
            border-radius: 50%; /* 變圓形 */
            display: none;
            object-fit: cover; /* 保持比例裁切填滿正方形 */
            border: 1px solid #334155;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>📝 註冊帳號</h2>

    <?php if (!empty($message)): ?>
        <div class="error-message"><?= $message ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="username">👤 帳號</label>
            <input type="text" name="username" id="username" required>
        </div>

        <div class="form-group">
            <label for="password">🔑 密碼</label>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="form-group">
            <label for="profile_photo">🖼 上傳大頭貼</label>
            <input type="file" name="profile_photo" id="profile_photo" accept="image/*">
            <img id="preview" src="#" alt="預覽圖片">
        </div>

        <button type="submit" class="btn">註冊</button>
    </form>

    <div class="register-link">
        已經有帳號？<a href="index.php">點此登入</a>
    </div>
</div>

<script>
    const fileInput = document.getElementById('profile_photo');
    const previewImg = document.getElementById('preview');

    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            previewImg.src = '#';
            previewImg.style.display = 'none';
        }
    });
</script>

</body>
</html>
