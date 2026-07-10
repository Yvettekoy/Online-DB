<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
        exit;
    } else {
        $error = '帳號或密碼錯誤';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>AI DebugCamp - 登入</title>
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
        input[type="password"] {
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
    </style>
</head>
<body>

<div class="card">
    <h2>🔐 登入系統</h2>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="username">👤 使用者名稱</label>
            <input type="text" name="username" required>
        </div>

        <div class="form-group">
            <label for="password">🔑 密碼</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" class="btn">登入</button>
    </form>

    <div class="register-link">
        還沒有帳號？<a href="register.php">註冊</a>
    </div>
</div>

</body>
</html>
