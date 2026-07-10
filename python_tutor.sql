-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026-07-10 18:48:57
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `python_tutor`
--

-- --------------------------------------------------------

--
-- 資料表結構 `ai_explanations`
--

CREATE TABLE `ai_explanations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `wrong_code` longtext NOT NULL,
  `explanation` longtext NOT NULL,
  `guide_question1` longtext DEFAULT NULL,
  `guide_answer1` char(1) DEFAULT NULL,
  `guide_question2` longtext DEFAULT NULL,
  `guide_answer2` char(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT '輸入輸出 或 if',
  `prompt` longtext NOT NULL COMMENT '題目說明',
  `wrong_code` longtext NOT NULL COMMENT '錯誤的程式碼',
  `correct_code` longtext DEFAULT NULL COMMENT '修正後的程式碼',
  `created_by` int(11) DEFAULT NULL COMMENT '上傳者 user_id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `questions`
--

INSERT INTO `questions` (`id`, `type`, `prompt`, `wrong_code`, `correct_code`, `created_by`, `created_at`) VALUES
(1, '輸入輸出', '修正這個程式碼以輸出 Hello World', 'print(\"Hello world\")\nprint(x)', 'print(\"Hello World\")', 1, '2026-07-08 17:19:53'),
(2, 'if', '修正這個 if 判斷式讓它正確檢查 x 是否大於 10', 'if x > 10\n    print(\"x is greater than 10\")', 'if x > 10:\n    print(\"x is greater than 10\")', 1, '2026-07-08 17:19:53'),
(3, '迴圈', '修正這個 for 迴圈讓它輸出 0 到 4', 'for i in range(5)\r\nprint(i)', 'for i in range(5):\r\n    print(i)', 1, '2026-07-10 16:48:12'),
(4, '函式', '修正函式定義使其能回傳兩數相加', 'def add(a, b)\r\n    return a + b', 'def add(a, b):\r\n    return a + b', 1, '2026-07-10 16:48:12'),
(5, '串列', '修正程式讓它正確取得串列第一個元素', 'numbers = [1,2,3]\r\nprint(numbers(0))', 'numbers = [1,2,3]\r\nprint(numbers[0])', 1, '2026-07-10 16:48:12'),
(6, '字典', '修正程式以正確取得字典中的 name', 'user = {\"name\":\"Tom\",\"age\":20}\r\nprint(user.name)', 'user = {\"name\":\"Tom\",\"age\":20}\r\nprint(user[\"name\"])', 1, '2026-07-10 16:48:12'),
(7, 'while', '修正 while 迴圈直到 x 等於 5', 'x = 0\r\nwhile x < 5\r\n    x += 1\r\nprint(x)', 'x = 0\r\nwhile x < 5:\r\n    x += 1\r\nprint(x)', 1, '2026-07-10 16:48:12'),
(8, '輸入輸出', '修正程式讓使用者輸入姓名後印出 Hello', 'name = input(\"Name:\")\r\nprint(\"Hello\" + name', 'name = input(\"Name:\")\r\nprint(\"Hello \" + name)', 1, '2026-07-10 16:48:12'),
(9, '型別轉換', '修正程式讓兩個輸入數字能相加', 'a = input()\r\nb = input()\r\nprint(a + b)', 'a = int(input())\r\nb = int(input())\r\nprint(a + b)', 1, '2026-07-10 16:48:12'),
(10, 'List', '修正程式讓它新增元素到串列', 'nums = [1,2,3]\r\nnums.add(4)\r\nprint(nums)', 'nums = [1,2,3]\r\nnums.append(4)\r\nprint(nums)', 1, '2026-07-10 16:48:12'),
(11, 'for', '修正程式讓它正確遍歷字串', 'text = \"Python\"\r\nfor c in text\r\n    print(c)', 'text = \"Python\"\r\nfor c in text:\r\n    print(c)', 1, '2026-07-10 16:48:12'),
(12, '例外處理', '修正程式讓它正確捕捉除以零錯誤', 'try:\r\n    print(10/0)\r\ncatch:\r\n    print(\"Error\")', 'try:\r\n    print(10/0)\r\nexcept ZeroDivisionError:\r\n    print(\"Error\")', 1, '2026-07-10 16:48:12');

-- --------------------------------------------------------

--
-- 資料表結構 `question_attempts`
--

CREATE TABLE `question_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `completed` int(11) DEFAULT 0 COMMENT '0=未完成，1=已完成',
  `attempts` int(11) DEFAULT 0 COMMENT '作答次數',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `treasure_claims`
--

CREATE TABLE `treasure_claims` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `box_number` int(11) NOT NULL,
  `claimed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `profile_photo`, `score`, `created_at`, `updated_at`) VALUES
(1, 'testuser', '$2y$10$abc123', 'default_avatar.jpeg', 0, '2026-07-08 17:19:53', '2026-07-08 17:19:53');

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `ai_explanations`
--
ALTER TABLE `ai_explanations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 資料表索引 `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_questions_type_created` (`type`,`created_at`);

--
-- 資料表索引 `question_attempts`
--
ALTER TABLE `question_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_question` (`user_id`,`question_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_completed` (`completed`),
  ADD KEY `idx_attempts_user_completed` (`user_id`,`completed`);

--
-- 資料表索引 `treasure_claims`
--
ALTER TABLE `treasure_claims`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_box` (`user_id`,`box_number`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_score` (`score`),
  ADD KEY `idx_users_score` (`score`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `ai_explanations`
--
ALTER TABLE `ai_explanations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `question_attempts`
--
ALTER TABLE `question_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `treasure_claims`
--
ALTER TABLE `treasure_claims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `ai_explanations`
--
ALTER TABLE `ai_explanations`
  ADD CONSTRAINT `ai_explanations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- 資料表的限制式 `question_attempts`
--
ALTER TABLE `question_attempts`
  ADD CONSTRAINT `question_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_attempts_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `treasure_claims`
--
ALTER TABLE `treasure_claims`
  ADD CONSTRAINT `treasure_claims_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
