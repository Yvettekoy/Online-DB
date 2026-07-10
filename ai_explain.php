<?php
session_start();

// 模擬原始錯誤題和 AI 選擇題（實際應該從資料庫或 API 取得）
$error_code = $_SESSION['error_code'] ?? 'def add(x, y):\n  return x + y\nprint(add(5))'; // 錯誤題目
$mcqs = $_SESSION['mcqs'] ?? [
    [
        'question' => '此程式錯在哪？',
        'options' => ['缺少一個參數', '語法錯誤', '變數未定義'],
        'answer' => '缺少一個參數',
    ],
    [
        'question' => '哪一個才是正確的函式呼叫？',
        'options' => ['add(5)', 'add(5, 10)', 'add()'],
        'answer' => 'add(5, 10)',
    ]
];

$explanation = "這段程式碼缺少一個必要的參數。函式 `add(x, y)` 定義了兩個參數，但只傳入了一個值 `add(5)`，因此會出錯。\n\n修正方法：\n```python\ndef add(x, y):\n  return x + y\nprint(add(5, 10))\n```";

$user_answers = $_SESSION['user_answers'] ?? [];
$correct_count = 0;
$feedback = [];

// 表單提交處理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_answers = $_POST['answers'] ?? [];
    $_SESSION['user_answers'] = $user_answers;
    $feedback = [];

    foreach ($mcqs as $i => $mcq) {
        $user_answer = $user_answers[$i] ?? '';
        $is_correct = ($user_answer === $mcq['answer']);
        if ($is_correct) $correct_count++;
        $feedback[] = [
            'question' => $mcq['question'],
            'your_answer' => $user_answer,
            'correct' => $is_correct,
            'correct_answer' => $mcq['answer']
        ];
    }

    if ($correct_count === count($mcqs)) {
        $_SESSION['show_explanation'] = true;
    }
}

$show_explanation = $_SESSION['show_explanation'] ?? false;
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>AI DebugCamp - AI 解釋錯誤</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .option-label:hover {
        cursor: pointer;
        background-color: #f0f0f0;
    }
  </style>
</head>
<body class="bg-light">
<div class="container mt-5">
  <div class="card shadow p-4">
    <h3 class="mb-3">❌ 錯誤的 Python 題目：</h3>
    <pre class="bg-dark text-light p-3 rounded"><?= htmlspecialchars($error_code) ?></pre>

    <?php if (!$show_explanation): ?>
    <form method="POST">
      <h4 class="mt-4">🧠 請回答以下選擇題：</h4>

      <?php foreach ($mcqs as $index => $mcq): ?>
        <div class="mt-3">
          <strong>Q<?= $index + 1 ?>. <?= htmlspecialchars($mcq['question']) ?></strong>
          <?php foreach ($mcq['options'] as $option): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="answers[<?= $index ?>]" value="<?= htmlspecialchars($option) ?>"
                     id="q<?= $index ?>_<?= md5($option) ?>"
                     <?= (isset($user_answers[$index]) && $user_answers[$index] === $option) ? 'checked' : '' ?>>
              <label class="form-check-label option-label" for="q<?= $index ?>_<?= md5($option) ?>">
                <?= htmlspecialchars($option) ?>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <button type="submit" class="btn btn-primary mt-4">提交答案</button>
    </form>
    <?php endif; ?>

    <?php if (!empty($feedback)): ?>
      <div class="mt-4">
        <h5>📋 回饋：</h5>
        <ul class="list-group">
          <?php foreach ($feedback as $f): ?>
            <li class="list-group-item">
              <strong><?= htmlspecialchars($f['question']) ?></strong><br>
              你的回答：<?= htmlspecialchars($f['your_answer']) ?><br>
              <?= $f['correct'] ? '<span class="text-success">✅ 正確</span>' : '<span class="text-danger">❌ 錯誤，正確答案是：' . htmlspecialchars($f['correct_answer']) . '</span>' ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($show_explanation): ?>
      <div class="mt-5">
        <h4>✅ AI 解釋與修正：</h4>
        <div class="bg-light border p-3 rounded">
          <pre><?= htmlspecialchars($explanation) ?></pre>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
