<?php
session_start();

// Cek apakah user login
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
    header("Location: ../login.php");
    exit();
}

// Ambil skor dari kiriman form quiz.php, jika tidak ada set ke 0
$final_score = isset($_POST['final_score']) ? (int)$_POST['final_score'] : 0;

// Simpan skor ke database
if ($final_score > 0 && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/db.php';
    
    $conn = getDbConnection();
    
    // Mulai transaksi untuk memastikan konsistensi data
    $conn->begin_transaction();
    
    try {
        $userId = (int) $_SESSION['user_id'];
        $quizId = isset($_POST['quiz_id']) ? (int) $_POST['quiz_id'] : 0;

        if ($quizId > 0) {
            $chkQuiz = $conn->prepare('SELECT id FROM quizzes WHERE id = ? LIMIT 1');
            $chkQuiz->bind_param('i', $quizId);
            $chkQuiz->execute();
            $chkRes = $chkQuiz->get_result();
            if (!$chkRes || !$chkRes->fetch_assoc()) {
                $quizId = 0;
            }
            $chkQuiz->close();
        }

        if ($quizId <= 0) {
            $quizRes = $conn->query('SELECT id FROM quizzes WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
            if ($quizRes && ($quizRow = $quizRes->fetch_assoc())) {
                $quizId = (int) $quizRow['id'];
            }
        }
        if ($quizId <= 0) {
            $quizResAny = $conn->query('SELECT id FROM quizzes ORDER BY id ASC LIMIT 1');
            if ($quizResAny && ($quizRowAny = $quizResAny->fetch_assoc())) {
                $quizId = (int) $quizRowAny['id'];
            }
        }

        $quizMaxScore = 150;
        $quizTotalQuestions = 15;
        if ($quizId > 0) {
            $quizStmt = $conn->prepare('SELECT max_score, total_questions FROM quizzes WHERE id = ? LIMIT 1');
            $quizStmt->bind_param('i', $quizId);
            $quizStmt->execute();
            $quizRes = $quizStmt->get_result();
            if ($quizRes && ($quizRow = $quizRes->fetch_assoc())) {
                if (isset($quizRow['max_score'])) {
                    $quizMaxScore = (int) $quizRow['max_score'];
                }
                if (isset($quizRow['total_questions'])) {
                    $quizTotalQuestions = (int) $quizRow['total_questions'];
                }
            }
            $quizStmt->close();
        }

        $lockUser = $conn->prepare('SELECT total_score FROM users WHERE id = ? FOR UPDATE');
        $lockUser->bind_param('i', $userId);
        $lockUser->execute();
        $lockRes = $lockUser->get_result();
        $currentTotal = 0;
        if ($lockRes && ($urow = $lockRes->fetch_assoc())) {
            $currentTotal = (int) ($urow['total_score'] ?? 0);
        }
        $lockUser->close();

        $newTotalScore = $final_score;

        $pointsPerQuestion = ($quizTotalQuestions > 0) ? (int) round($quizMaxScore / $quizTotalQuestions) : 10;
        $correct_answers = ($pointsPerQuestion > 0) ? (int) floor($final_score / $pointsPerQuestion) : 0;
        if ($quizTotalQuestions > 0) {
            $correct_answers = min($correct_answers, $quizTotalQuestions);
        }

        // Simpan hasil kuis ke quiz_results (history) jika quizId valid
        if ($quizId > 0) {
            $history_stmt = $conn->prepare('INSERT INTO quiz_results (user_id, quiz_id, total_score, max_score, completed_at, correct_answers, total_questions) VALUES (?, ?, ?, ?, NOW(), ?, ?)');
            $history_stmt->bind_param('iiiiii', $userId, $quizId, $final_score, $quizMaxScore, $correct_answers, $quizTotalQuestions);
            if (!$history_stmt->execute()) {
                error_log('Error saving quiz history: ' . $history_stmt->error);
            }
            $history_stmt->close();
        } else {
            error_log('Quiz history not saved: quiz_id is invalid/missing.');
        }

        // Update users.total_score dengan skor terbaik
        $update_stmt = $conn->prepare('UPDATE users SET total_score = ?, quiz_completed = quiz_completed + 1, last_played = NOW() WHERE id = ?');
        $update_stmt->bind_param('ii', $newTotalScore, $userId);
        if (!$update_stmt->execute()) {
            throw new RuntimeException('Failed to update user score.');
        }
        $update_stmt->close();
        
        // Update session total_score
        $_SESSION['total_score'] = $newTotalScore;
        $final_score = $newTotalScore;
        
        // Commit transaksi
        $conn->commit();
    } catch (Throwable $e) {
        // Rollback jika ada error
        $conn->rollback();
        error_log("Error saving quiz result: " . $e->getMessage());
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Result - Wednesday</title>
    <style>
        /* --- LOAD FONTS --- */
        @font-face {
            font-family: 'MetalMania';
            src: url('../asset/MetalMania-Regular.ttf') format('truetype');
        }
        @font-face {
            font-family: 'Risque';
            src: url('../asset/Risque-Regular.ttf') format('truetype');
        }

        /* --- GLOBAL STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Risque', serif;
            height: 100vh;
            width: 100%;
            /* Background Image */
            background: url('../asset/background.jpg') no-repeat center center/cover;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        /* Overlay Gelap di atas background */
        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7); /* Kegelapan background */
            z-index: 0;
        }

        /* --- RESULT CARD CONTAINER --- */
        .result-card {
            position: relative;
            z-index: 10;
            width: 600px;
            height: 350px;
            /* Border putih tipis dengan sudut melengkung */
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 15px;
            
            /* Background Semi-Transparan + Blur */
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(8px);
            
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            
            /* Shadow halus di sekeliling kotak */
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.8);
        }

        /* Teks "YOUR SCORE" */
        .title-text {
            font-family: 'MetalMania', cursive;
            font-size: 64px;
            color: #FFFFFF;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.8);
            margin-top: -20px; /* Sedikit naik ke atas */
            margin-bottom: 10px;
            letter-spacing: 2px;
            text-transform: capitalize; /* Huruf awal besar */
        }

        /* Angka Skor (Warna Emas) */
        .score-text {
            font-family: 'Risque', serif; /* Atau font serif elegan lainnya */
            font-size: 96px; /* Ukuran besar */
            color: #D4AF37; /* Warna Emas */
            line-height: 1;
            margin-bottom: 20px;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.8);
        }
        
        .score-unit {
            font-size: 0.6em; /* Ukuran "pts" lebih kecil dari angka */
        }

        /* Link/Tombol FINISH */
        .finish-link {
            font-family: 'MetalMania', cursive;
            font-size: 24px;
            color: white;
            text-decoration: none;
            text-transform: uppercase;
            border-bottom: 2px solid white; /* Garis bawah putih */
            padding-bottom: 2px;
            transition: all 0.3s ease;
            letter-spacing: 1px;
            text-shadow: 1px 1px 2px black;
        }

        .finish-link:hover {
            color: #D4AF37;
            border-color: #D4AF37;
            transform: scale(1.05);
        }

    </style>
</head>
<body>

    <div class="result-card">
        <div class="title-text">Your Score</div>
        
        <div class="score-text">
            <?php echo $final_score; ?><span class="score-unit"> pts</span>
        </div>

        <a href="leaderboard.php" class="finish-link">FINISH</a>
    </div>

</body>
</html>