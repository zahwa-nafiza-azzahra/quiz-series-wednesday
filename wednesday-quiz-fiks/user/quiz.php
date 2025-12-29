<?php
session_start();

// Cek Login
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
    header("Location: ../login.php");
    exit();
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
$avatarSrc = '../asset/profile_placeholder.jpg'; // Default

// --- 1. LOAD AVATAR USER DARI DATABASE ---
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/db.php';
    $connAvatar = getDbConnection();
    $stmtAvatar = $connAvatar->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmtAvatar->bind_param('i', $_SESSION['user_id']);
    $stmtAvatar->execute();

    $resultAvatar = $stmtAvatar->get_result();
    if ($rowAvatar = $resultAvatar->fetch_assoc()) {
        $profilePic = $rowAvatar['profile_picture'];
        if ($profilePic && !empty($profilePic)) {
            // Logika path gambar (uploads/ vs asset/)
            if (strpos($profilePic, 'uploads/') === 0) {
                $avatarSrc = '../asset/' . $profilePic;
            } elseif (strpos($profilePic, '/') === false) {
                if (file_exists(__DIR__ . '/../asset/' . $profilePic)) {
                    $avatarSrc = '../asset/' . $profilePic;
                } elseif (file_exists(__DIR__ . '/../asset/uploads/' . $profilePic)) {
                    $avatarSrc = '../asset/uploads/' . $profilePic;
                } else {
                    $avatarSrc = '../asset/profile_placeholder.jpg';
                }

            } else {
                $avatarSrc = $profilePic;
            }
            // Validasi file exist
            $avatarPath = str_replace('../asset/', __DIR__ . '/../asset/', $avatarSrc);
            if (!file_exists($avatarPath)) {
                $avatarSrc = '../asset/profile_placeholder.jpg';
            }
        }

    }
    $stmtAvatar->close();
    $connAvatar->close();
}

require_once __DIR__ . '/../config/db.php';

// --- 2. LOGIKA LOAD SOAL QUIZ (BACKEND) ---
$quizParam = isset($_GET['quiz_id']) ? $_GET['quiz_id'] : 'all';
$conn = getDbConnection();

$resultQuizId = 0;

$questionsFromDb = [];
$timeLimit = 15; // default

if ($quizParam !== 'all') {
    $quizId = (int) $quizParam;
    $resultQuizId = $quizId;
    // Ambil time_limit
    $quizStmt = $conn->prepare("SELECT time_limit FROM quizzes WHERE id = ?");
    $quizStmt->bind_param('i', $quizId);
    $quizStmt->execute();
    $quizStmt->bind_result($timeLimitDb);
    if ($quizStmt->fetch() && $timeLimitDb) {
        $timeLimit = (int) $timeLimitDb;
    }
    $quizStmt->close();

    $sql = "SELECT q.id AS question_id, q.question_text, q.question_type, q.points, q.image_url, 
            o.option_text, o.is_correct, o.option_order 
            FROM questions q 
            JOIN options o ON o.question_id = q.id 
            WHERE q.quiz_id = ? 
            ORDER BY q.id, o.option_order";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $quizId);
} else {
    // Logic semua soal
    $quizRes = $conn->query('SELECT id FROM quizzes WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
    if ($quizRes && ($quizRow = $quizRes->fetch_assoc())) {
        $resultQuizId = (int) $quizRow['id'];
    }
    if ($resultQuizId <= 0) {
        $quizResAny = $conn->query('SELECT id FROM quizzes ORDER BY id ASC LIMIT 1');
        if ($quizResAny && ($quizRowAny = $quizResAny->fetch_assoc())) {
            $resultQuizId = (int) $quizRowAny['id'];
        }
    }
    $sql = "SELECT q.id AS question_id, q.question_text, q.question_type, q.points, q.image_url, 
            o.option_text, o.is_correct, o.option_order 
            FROM questions q 
            JOIN quizzes z ON z.id = q.quiz_id AND z.is_active = 1
            JOIN options o ON o.question_id = q.id 
            ORDER BY q.id, o.option_order";
    $stmt = $conn->prepare($sql);
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $qid = $row['question_id'];

        if (!isset($questionsFromDb[$qid])) {
            $questionsFromDb[$qid] = [
                'question' => $row['question_text'],
                'type' => $row['question_type'],
                'points' => (int) $row['points'],
                'img' => $row['image_url'] ?: null,
                'options' => [],
                'options_by_order' => [],
                'correct' => null,
                'correct_order' => null,
            ];
        }

        $order = isset($row['option_order']) ? (int) $row['option_order'] : 0;
        if ($order <= 0) {
            $order = count($questionsFromDb[$qid]['options_by_order']) + 1;
        }

        if (!isset($questionsFromDb[$qid]['options_by_order'][$order])) {
            $questionsFromDb[$qid]['options_by_order'][$order] = $row['option_text'];
        }

        if ((int) $row['is_correct'] === 1) {
            $questionsFromDb[$qid]['correct_order'] = $order;
        }
    }
    $stmt->close();
}
$conn->close();

// Rapikan array options
foreach ($questionsFromDb as &$q) {
    if (isset($q['options_by_order']) && is_array($q['options_by_order'])) {
        ksort($q['options_by_order']);
        $orders = array_keys($q['options_by_order']);
        $q['options'] = array_values($q['options_by_order']);

        if ($q['correct_order'] !== null) {
            $idx = array_search($q['correct_order'], $orders, true);
            $q['correct'] = ($idx === false) ? null : (int) $idx;
        }
    }
    unset($q['options_by_order'], $q['correct_order']);
}
unset($q);

$questionsFromDb = array_values($questionsFromDb);

// Auto-attach images
$assetDir = __DIR__ . '/../asset';
$imgByNo = [];
if (is_dir($assetDir)) {
    $patterns = [
        $assetDir . '/q*.jpg', $assetDir . '/q*.jpeg',
        $assetDir . '/q*.png', $assetDir . '/q*.webp',
    ];
    foreach ($patterns as $pattern) {
        $files = glob($pattern);
        if (!$files) continue;
        foreach ($files as $filePath) {
            $base = basename($filePath);
            if (preg_match('/^q(\d+)_/i', $base, $m)) {
                $no = (int) $m[1];
                if ($no >= 1) $imgByNo[$no] = '../asset/' . $base;
            }
        }
    }
}
for ($i = 0; $i < count($questionsFromDb); $i++) {
    $no = $i + 1;
    if (isset($imgByNo[$no])) $questionsFromDb[$i]['img'] = $imgByNo[$no];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - Wednesday</title>
    <style>
        /* --- FONTS --- */
        @font-face { font-family: 'MetalMania'; src: url('../asset/MetalMania-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Risque'; src: url('../asset/Risque-Regular.ttf') format('truetype'); }

        /* --- GLOBAL & BACKGROUND --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Risque', serif;
            background: url('../asset/background.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #FFFFFF;
            height: 100vh;
            overflow: hidden; /* Mencegah scroll agar fit screen seperti game */
            display: flex;
            flex-direction: column;
            justify-content: center; /* Center Vertically */
            align-items: center;     /* Center Horizontally */
        }
        
        /* Overlay Gelap */
        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); z-index: -1;
        }

        /* --- LAYOUT UTAMA --- */
        .game-wrapper {
            display: flex;
            flex-direction: column;
            width: 90%;
            max-width: 1100px;
            /* Memberi ruang untuk margin atas (90px) dan sedikit buffer bawah */
            height: calc(100dvh - 100px); 
            gap: 20px;
            margin-top: 90px;
            overflow-y: auto; /* KUNCI: Mengizinkan scroll internal */
            padding-bottom: 20px; 
            
            scrollbar-width: thin;
            scrollbar-color: #d4af37 rgba(0,0,0,0.2);
        }

        /* Scrollbar Chrome/Safari */
        .game-wrapper::-webkit-scrollbar { width: 8px; }
        .game-wrapper::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        .game-wrapper::-webkit-scrollbar-thumb { background-color: #d4af37; border-radius: 10px; }

        .navbar {
            position: fixed;
            top: 0; left: 0;
            width: 100%;
            padding: 15px 40px;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .nav-logo {
            font-family: 'MetalMania', cursive;
            font-size: 26px; color: white; letter-spacing: 2px;
        }

        .nav-menu { display: flex; align-items: center; gap: 30px; }
        .nav-menu a {
            font-family: 'Risque', serif; color: white; text-decoration: none;
            font-size: 20px; transition: 0.3s; letter-spacing: 1px; text-transform: uppercase;
        }
        .nav-menu a:hover { color: #d4af37; }

        .profile-container { position: relative; cursor: pointer; }

        .user-profile {
            display: flex; align-items: center;
            background-color: #d4af37;
            padding: 5px 15px 5px 5px;
            border-radius: 30px;
            color: black; font-weight: bold; font-family: 'Risque', serif;
        }

        .user-profile img {
            width: 35px; height: 35px; border-radius: 50%;
            object-fit: cover; margin-right: 10px;
            border: 2px solid black; background: #fff;
        }

        .dropdown-menu {
            display: none;
            position: absolute; top: 50px; right: 0;
            width: 250px;
            border-radius: 15px; overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            animation: fadeInNav 0.2s ease-in-out;
            border: 2px solid #333;
        }

        .dropdown-header {
            background-color: #d4af37;
            padding: 20px;
            text-align: center;
            display: flex; align-items: center; justify-content: center; gap: 15px;
        }
        .dropdown-header img {
            width: 50px; height: 50px; border-radius: 50%;
            border: 2px solid white; object-fit: cover;
        }
        .dropdown-header span {
            font-family: 'Risque', serif; font-size: 20px; color: white;
            text-shadow: 1px 1px 2px black;
        }

        .dropdown-body { background-color: #5d5318; padding: 15px 0; }

        .dropdown-item {
            display: flex; align-items: center;
            padding: 10px 25px;
            color: white; text-decoration: none;
            font-family: 'MetalMania', cursive; font-size: 20px;
            transition: 0.3s; text-transform: uppercase;
        }
        .dropdown-item:hover { background-color: rgba(255,255,255,0.1); padding-left: 35px; }
        .dropdown-item svg { width: 24px; height: 24px; fill: white; margin-right: 15px; }

        @keyframes fadeInNav {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- PROGRESS BAR --- */
        .top-bar {
            width: 100%; height: 25px; min-height: 25px;
            border: 2px solid #FFFFFF; border-radius: 50px;
            background: rgba(0, 0, 0, 0.5);
            overflow: hidden; position: relative;
            box-shadow: 0 4px 8px rgba(0,0,0,0.5);
            flex-shrink: 0;
        }
        .progress-fill {
            height: 100%; width: 0%;
            background: linear-gradient(90deg, #D4AF37, #B8860B);
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.8);
            border-radius: 50px;
        }

        /* --- MAIN CONTENT (Kiri: Soal, Kanan: Stats) --- */
        .content-area {
            display: flex; flex: 1; gap: 24px;
            min-height: min-content; align-items: flex-start;
        }

        /* --- KARTU SOAL (KIRI) --- */
        .question-card-frame {
            flex: 3;
            border: 2px solid rgba(255, 255, 255, 0.7);
            border-radius: 16px;
            background: rgba(30, 30, 30, 0.4);
            position: relative;
            display: flex; flex-direction: column;
            padding: 40px 32px 40px;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.8), inset 0 0 20px rgba(0,0,0,0.3);
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
            min-height: min-content;
        }

        .question-number-box {
            position: absolute; top: -18px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(180deg, #D4AF37, #B8860B);
            padding: 6px 32px;
            color: white; font-family: 'MetalMania', cursive; font-size: 24px;
            border: 2px solid rgba(255,255,255,0.8); border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.6), inset 0 1px 2px rgba(255,255,255,0.3);
            z-index: 10; text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }

        /* Area Teks & Gambar Soal */
        .question-content {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            margin-bottom: 12px; text-align: center;
        }
        .question-text {
            font-family: 'MetalMania', cursive; font-size: 26px; color: #FFFFFF;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.8); letter-spacing: 1.5px;
            margin-top: 15px; text-transform: uppercase; line-height: 1.4;
        }
        .question-img {
            max-width: 300px; max-height: 200px;
            border: 2px solid rgba(212, 175, 55, 0.6); border-radius: 12px;
            margin-bottom: 20px; display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }

        /* Container Jawaban */
        .answers-container {
            display: flex; justify-content: center; align-items: center;
            gap: 16px; margin-top: auto; padding-top: 20px;
        }
        .answer-card {
            flex: 1; min-width: 0;
            background: rgba(0, 0, 0, 0.35);
            border: 2px solid rgba(212, 175, 55, 0.9);
            border-radius: 10px;
            color: white; font-family: 'MetalMania', cursive; font-size: 15px;
            letter-spacing: 1px; text-transform: uppercase;
            display: flex; align-items: center; justify-content: center;
            text-align: center; padding: 16px 10px;
            min-height: 100px; cursor: pointer;
            transition: all 0.3s; position: relative; overflow: hidden;
            box-shadow: inset 0 0 25px rgba(0,0,0,0.6), 0 2px 8px rgba(0,0,0,0.4);
        }
        .answer-card:hover {
            background: rgba(212, 175, 55, 0.12); transform: translateY(-3px);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.4);
        }
        .answer-card.selected {
            background: rgba(212, 175, 55, 0.5); border-color: #FFF; border-width: 3px;
            color: #FFF; box-shadow: 0 0 25px #D4AF37, inset 0 0 15px rgba(212, 175, 55, 0.3);
            transform: scale(1.05);
        }
        .answer-card.wrong {
            background: rgba(200, 0, 0, 0.5); border-color: #ff4444; border-width: 3px;
            box-shadow: 0 0 20px rgba(255, 68, 68, 0.6);
        }
        .answer-card.disabled { pointer-events: none; opacity: 0.7; }

        /* --- PANEL STATISTIK (KANAN) --- */
        .stats-panel {
            flex: 1; max-width: 230px;
            display: flex; flex-direction: column; gap: 12px;
        }

        .profile-box {
            border: 2px solid rgba(255, 255, 255, 0.6);
            border-radius: 14px; background: rgba(0, 0, 0, 0.4);
            padding: 18px; text-align: center;
            display: flex; flex-direction: column; align-items: center;
            box-shadow: 0 0 25px rgba(0,0,0,0.6), inset 0 0 15px rgba(0,0,0,0.3);
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
        }
        .profile-img {
            width: 60px; height: 60px; border-radius: 50%; border: 2px solid #D4AF37;
            object-fit: cover; margin-bottom: 12px;
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
        }
        .player-name {
            font-family: 'MetalMania', cursive; font-size: 15px; color: #FFF;
            margin-bottom: 8px; text-transform: uppercase;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.8);
        }
        .score-display {
            font-family: 'MetalMania', cursive; font-size: 48px; color: #D4AF37;
            text-shadow: 0 0 15px rgba(212, 175, 55, 0.8), 2px 2px 4px rgba(0,0,0,0.8);
            line-height: 1;
        }

        .timer-box {
            background: linear-gradient(180deg, #D4AF37, #B8860B);
            border: 2px solid rgba(255, 255, 255, 0.8); border-radius: 12px;
            padding: 20px; text-align: center;
            font-family: 'MetalMania', cursive; font-size: 48px; color: white;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.8);
            box-shadow: 0 0 25px rgba(212, 175, 55, 0.6), inset 0 2px 4px rgba(255,255,255,0.2);
            min-height: 80px; display: flex; align-items: center; justify-content: center;
        }

        .controls-row { display: flex; justify-content: center; gap: 12px; }
        .control-btn {
            background: rgba(184, 134, 11, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.6); border-radius: 10px;
            width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.3s; box-shadow: 0 4px 8px rgba(0,0,0,0.5);
        }
        .control-btn:hover { 
            transform: scale(1.08); background: #D4AF37; box-shadow: 0 0 15px rgba(212, 175, 55, 0.6);
        }
        .control-btn svg { width: 24px; height: 24px; fill: white; }

        .next-btn-container { margin-top: auto; display: flex; justify-content: center; }
        .btn-next {
            width: 100%; background: linear-gradient(180deg, #D4AF37, #B8860B);
            border: 2px solid rgba(255, 255, 255, 0.9); border-radius: 12px;
            padding: 14px 0; font-size: 28px; font-family: 'MetalMania', cursive;
            color: white; cursor: pointer; text-shadow: 2px 2px 5px rgba(0,0,0,0.8);
            box-shadow: 0 4px 15px rgba(0,0,0,0.7), inset 0 1px 2px rgba(255,255,255,0.2);
            transition: 0.3s; display: none; text-transform: uppercase; letter-spacing: 2px;
        }
        .btn-next:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 20px rgba(212,175,55,0.5), inset 0 1px 3px rgba(255,255,255,0.3);
            background: linear-gradient(180deg, #E5C158, #C9A028);
        }

        .pause-modal-content {
            background: rgba(180, 140, 20, 0.85); padding: 40px 60px;
            border-radius: 15px; text-align: center;
            box-shadow: 0 0 30px rgba(0,0,0,0.8);
            border: 2px solid rgba(255,255,255,0.4); width: 320px;
        }
        .pause-title {
            font-family: 'MetalMania', cursive; color: white;
            font-size: 48px; margin-bottom: 30px;
            text-shadow: 2px 2px 5px black; text-transform: uppercase;
        }
        .pause-buttons { display: flex; flex-direction: column; gap: 15px; }
        .pause-btn {
            display: block; width: 100%; background: rgba(42, 42, 42, 0.9);
            color: white; font-family: 'MetalMania', cursive; font-size: 22px;
            padding: 12px; border: 2px solid rgba(255,255,255,0.65);
            border-radius: 10px; cursor: pointer; text-transform: uppercase;
            transition: background 0.3s;
        }
        .pause-btn:hover { background: #444; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-logo">WEDNESDAY QUIZ</div>
        <div class="nav-menu">
            <a href="dashboard.php">HOME</a>
            <a href="leaderboard.php">LEADERBOARD</a>

            <div class="profile-container" onclick="toggleDropdown()">
                <div class="user-profile">
                    <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="User"> 
                    <span><?php echo htmlspecialchars($username); ?></span>
                </div>

                <div class="dropdown-menu" id="profileDropdown">
                    <div class="dropdown-header">
                        <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="User">
                        <span><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="dropdown-body">
                        <a href="profil.php" class="dropdown-item">
                            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            PROFILE
                        </a>
                        <a href="../logout.php" class="dropdown-item">
                            <svg viewBox="0 0 24 24"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                            LOGOUT
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="game-wrapper">
        <div class="top-bar">
            <div class="progress-fill" id="progressBar"></div>
        </div>

        <div class="content-area">
            <div class="question-card-frame">
                <div class="question-number-box" id="questionNumber">1</div>

                <div class="question-content">
                    <img id="questionImg" class="question-img" src="" alt="Question Image" style="display: none;">
                    <div class="question-text" id="questionText">Loading...</div>
                </div>

                <div class="answers-container" id="answersContainer">
                </div>
            </div>

            <div class="stats-panel">
                <div class="profile-box">
                    <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Profile" class="profile-img">
                    <div class="player-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="score-display" id="scoreDisplay">0</div>
                </div>

                <div class="timer-box" id="timerDisplay"><?php echo $timeLimit; ?></div>

                <div class="controls-row">
                    <button class="control-btn" onclick="pauseGame()">
                        <svg viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                    </button>
                    <button class="control-btn" onclick="toggleMusic()">
                        <svg id="musicIcon" viewBox="0 0 24 24">
                            <path d="M4.27 3L3 4.27l9 9v.28c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4v-1.73L19.73 21 21 19.73 4.27 3zM14 7h4V3h-6v5.18l2 2z"/>
                        </svg>
                    </button>
                </div>

                <div class="next-btn-container">
                    <button class="btn-next" id="nextBtn" onclick="nextQuestion()">NEXT</button>
                </div>
            </div>
        </div>
    </div>

    <div id="pauseModal" class="pause-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 10000; justify-content: center; align-items: center;">
        <div class="pause-modal-content">
            <h2 class="pause-title">PAUSE</h2>
            <div class="pause-buttons">
                <button class="pause-btn" onclick="resumeGame()">CONTINUE</button>
                <button class="pause-btn" onclick="exitGame()">EXIT</button>
            </div>
        </div>
    </div>

    <form id="scoreForm" action="result.php" method="POST" style="display:none;">
        <input type="hidden" name="final_score" id="finalScoreInput">
        <input type="hidden" name="quiz_id" value="<?php echo (int) $resultQuizId; ?>">
    </form>

    <audio id="bgMusic" loop>
        <source src="../asset/music.mp3" type="audio/mpeg">
    </audio>

    <script>
        function toggleDropdown() {
            var dropdown = document.getElementById("profileDropdown");
            if (!dropdown) return;
            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }
        }

        window.onclick = function(event) {
            if (!event.target.closest('.profile-container')) {
                var dd = document.getElementById("profileDropdown");
                if (dd) dd.style.display = "none";
            }
        }

        // --- INJECT DATA DARI PHP KE JS DI SINI ---
        const questions = <?php echo json_encode($questionsFromDb, JSON_UNESCAPED_SLASHES); ?>;
        const totalTimePerQuestion = <?php echo (int)$timeLimit; ?> || 15; 
        
        let currentQuestionIndex = 0;
        let currentScore = 0;
        let timeLeft = totalTimePerQuestion;
        let timerInterval;
        let isAnswered = false;
        let isPaused = false;

        // Elements
        const questionTextEl = document.getElementById('questionText');
        const questionNumberEl = document.getElementById('questionNumber');
        const questionImgEl = document.getElementById('questionImg');
        const answersContainerEl = document.getElementById('answersContainer');
        const scoreDisplayEl = document.getElementById('scoreDisplay');
        const timerDisplayEl = document.getElementById('timerDisplay');
        const nextBtnEl = document.getElementById('nextBtn');
        const progressBarEl = document.getElementById('progressBar');
        const pauseModalEl = document.getElementById('pauseModal');

        function renderTimer() {
            if (!timerDisplayEl) return;
            timerDisplayEl.textContent = String(Math.max(0, timeLeft));
        }

        function initGame() { 
            // 1. Cek Musik saat start game
            checkMusicPreference();

            if (!questions.length) {
                questionTextEl.innerText = 'No questions available.';
                return;
            }
            loadQuestion(); 
        }

        function loadQuestion() {
            isAnswered = false;
            timeLeft = totalTimePerQuestion;
            nextBtnEl.style.display = 'none'; 
            
            renderTimer();
            
            if (currentQuestionIndex >= questions.length) {
                finishGame();
                return;
            }

            const currentQ = questions[currentQuestionIndex];
            
            // Image Logic
            if (currentQ.img) {
                questionImgEl.src = currentQ.img;
                questionImgEl.style.display = 'block';
            } else {
                questionImgEl.style.display = 'none';
            }

            questionTextEl.innerText = currentQ.question;
            questionNumberEl.innerText = currentQuestionIndex + 1;
            
            // Progress Bar Logic
            const progressPercent = (currentQuestionIndex / questions.length) * 100;
            progressBarEl.style.width = progressPercent + "%";

            // Render Answers
            answersContainerEl.innerHTML = ''; 
            currentQ.options.forEach((option, index) => {
                const card = document.createElement('div');
                card.className = 'answer-card';
                card.innerText = option;
                card.onclick = () => checkAnswer(index, card);
                answersContainerEl.appendChild(card);
            });

            startTimer();
        }

        function startTimer() {
            clearInterval(timerInterval);
            renderTimer();
            timerInterval = setInterval(() => {
                if (!isPaused) {
                    timeLeft--;
                    renderTimer();
                    if (timeLeft <= 0) {
                        clearInterval(timerInterval);
                        handleTimeOut();
                    }
                }
            }, 1000);
        }

        function handleTimeOut() {
            isAnswered = true;
            disableButtons();
            renderTimer();
            nextBtnEl.style.display = 'inline-block';
        }

        function checkAnswer(selectedIndex, cardElement) {
            if (isAnswered || isPaused) return;
            
            isAnswered = true;
            clearInterval(timerInterval);

            const currentQ = questions[currentQuestionIndex];
            const isCorrect = selectedIndex === currentQ.correct;
            
            disableButtons();

            if (isCorrect) {
                // Scoring Logic
                let bonus = 0;
                const oneThird = totalTimePerQuestion / 3;
                if (timeLeft > (totalTimePerQuestion - oneThird)) { bonus = 50; } 
                else if (timeLeft > (totalTimePerQuestion - (oneThird * 2))) { bonus = 25; } 
                
                const basePoint = currentQ.points || 0;
                currentScore += (basePoint + bonus);
                
                animateScore(currentScore);
                cardElement.classList.add('selected'); // Style Benar (Emas)
            } else {
                cardElement.classList.add('wrong'); // Style Salah (Merah)
            }
            
            nextBtnEl.style.display = 'inline-block';
        }

        function animateScore(targetScore) {
            scoreDisplayEl.innerText = targetScore;
        }

        function disableButtons() {
            const cards = answersContainerEl.getElementsByClassName('answer-card');
            for (let card of cards) { card.classList.add('disabled'); }
        }

        function nextQuestion() {
            currentQuestionIndex++;
            loadQuestion();
        }

        function finishGame() {
            progressBarEl.style.width = "100%";
            document.getElementById('finalScoreInput').value = currentScore;
            document.getElementById('scoreForm').submit();
        }

        function pauseGame() {
            if(isAnswered) return;
            isPaused = true;
            pauseModalEl.style.display = 'flex';
        }

        function resumeGame() {
            isPaused = false;
            pauseModalEl.style.display = 'none';
            renderTimer();
        }

        function exitGame() {
            window.location.href = 'dashboard.php';
        }

        // --- LOGIKA MUSIK & LOCALSTORAGE UPDATE ---
        var music = document.getElementById("bgMusic");
        var musicIcon = document.getElementById("musicIcon"); 
        var isPlaying = false; 

        // Path SVG untuk Icon
        const iconOn = '<path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>'; 
        const iconOff = '<path d="M4.27 3L3 4.27l9 9v.28c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4v-1.73L19.73 21 21 19.73 4.27 3zM14 7h4V3h-6v5.18l2 2z"/>';

        // Fungsi cek status dari halaman sebelumnya
        function checkMusicPreference() {
            var status = localStorage.getItem('musicStatus');
            if (status === 'on') {
                var playPromise = music.play();
                if (playPromise !== undefined) {
                    playPromise.then(_ => {
                        musicIcon.innerHTML = iconOn;
                        isPlaying = true;
                    }).catch(error => {
                        console.log("Autoplay blocked");
                    });
                }
            } else {
                musicIcon.innerHTML = iconOff;
                isPlaying = false;
            }
        }

        // Fungsi Toggle & Simpan Status Baru
        function toggleMusic() {
            if (isPlaying) {
                music.pause();
                musicIcon.innerHTML = iconOff;
                isPlaying = false;
                localStorage.setItem('musicStatus', 'off'); // SIMPAN OFF
            } else {
                music.play().then(() => {
                    musicIcon.innerHTML = iconOn;
                    isPlaying = true;
                    localStorage.setItem('musicStatus', 'on'); // SIMPAN ON
                }).catch(error => {
                    console.log("Autoplay blocked");
                });
            }
        }

        window.onload = initGame;
    </script>

</body>
</html>