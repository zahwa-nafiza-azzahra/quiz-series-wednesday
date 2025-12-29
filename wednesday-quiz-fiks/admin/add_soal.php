<?php
session_start();

// Cek Login Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';

require_once __DIR__ . '/../config/db.php';

// Get admin profile picture from database
$conn = getDbConnection();
$stmt = $conn->prepare('SELECT profile_picture FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$profilePicture = $result['profile_picture'] ?? '../asset/profile_placeholder.jpg';

// Handle admin.jpg specifically
if ($profilePicture === 'admin.jpg') {
    $profilePicture = '../asset/admin.jpg';
}

$stmt->close();
$conn->close();

$error = '';

if (isset($_POST['save_question'])) {
    $questionText = isset($_POST['question_text']) ? trim((string) $_POST['question_text']) : '';
    $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : [];
    $correctAnswer = isset($_POST['correct_answer']) ? (int) $_POST['correct_answer'] : -1;

    $answers = array_values(array_map(static fn($v) => trim((string) $v), $answers));

    if ($questionText === '') {
        $error = 'Question text is required.';
    } elseif (count($answers) < 2) {
        $error = 'At least 2 answers are required.';
    } elseif ($correctAnswer < 0 || $correctAnswer >= count($answers)) {
        $error = 'Please select a valid correct answer.';
    } else {
        $conn = getDbConnection();

        $quizId = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : 0;
        if ($quizId <= 0) {
            $quizId = 1;
        }

        $quizCheck = $conn->prepare('SELECT id FROM quizzes WHERE id = ? LIMIT 1');
        $quizCheck->bind_param('i', $quizId);
        $quizCheck->execute();
        $quizCheckRes = $quizCheck->get_result();
        if (!$quizCheckRes || $quizCheckRes->num_rows === 0) {
            $fallbackRes = $conn->query('SELECT id FROM quizzes ORDER BY id ASC LIMIT 1');
            if ($fallbackRes && ($row = $fallbackRes->fetch_assoc())) {
                $quizId = (int) $row['id'];
            }
        }
        $quizCheck->close();

        $imageUrl = null;
        if (isset($_FILES['question_image']) && is_array($_FILES['question_image']) && ($_FILES['question_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmpName = (string) $_FILES['question_image']['tmp_name'];
            $origName = (string) $_FILES['question_image']['name'];

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($ext, $allowed, true)) {
                $uploadDir = __DIR__ . '/../uploads/questions';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }

                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
                $fileName = $safeName . '_' . uniqid('', true) . '.' . $ext;
                $dest = $uploadDir . '/' . $fileName;

                if (is_dir($uploadDir) && move_uploaded_file($tmpName, $dest)) {
                    $imageUrl = '../uploads/questions/' . $fileName;
                }
            }
        }

        try {
            $conn->begin_transaction();

            $questionType = 'multiple_choice';
            $points = 10;
            $stmt = $conn->prepare('INSERT INTO questions (quiz_id, question_text, question_type, points, image_url) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('issis', $quizId, $questionText, $questionType, $points, $imageUrl);
            $stmt->execute();
            $questionId = (int) $stmt->insert_id;
            $stmt->close();

            $optStmt = $conn->prepare('INSERT INTO options (question_id, option_text, is_correct, option_order) VALUES (?, ?, ?, ?)');
            foreach ($answers as $idx => $optText) {
                if ($optText === '') {
                    continue;
                }
                $isCorrect = ($idx === $correctAnswer) ? 1 : 0;
                $order = $idx + 1;
                $optStmt->bind_param('isii', $questionId, $optText, $isCorrect, $order);
                $optStmt->execute();
            }
            $optStmt->close();

            $conn->commit();

            header('Location: manage_soal.php');
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Failed to save question.';
        } finally {
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Question - Admin</title>
    <style>
        /* --- FONTS --- */
        @font-face { font-family: 'MetalMania'; src: url('../asset/MetalMania-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Risque'; src: url('../asset/Risque-Regular.ttf') format('truetype'); }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Risque', serif;
            min-height: 100vh; width: 100%;
            background: url('../asset/background.jpg') no-repeat center center/cover;
            display: flex; justify-content: center; align-items: flex-start;
            position: relative; overflow-y: auto;
            padding-top: 100px; padding-bottom: 50px;
        }

        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7); z-index: -1;
        }

        /* --- NAVBAR --- */
        .navbar {
            position: fixed; top: 0; left: 0; width: 100%; padding: 15px 40px;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .nav-logo { font-family: 'MetalMania', cursive; font-size: 26px; color: white; letter-spacing: 2px; }
        .nav-menu { display: flex; align-items: center; gap: 30px; }
        .nav-menu a { font-family: 'Risque', serif; color: white; text-decoration: none; font-size: 22px; letter-spacing: 1px; transition: 0.3s; }
        .nav-menu a:hover { color: #d4af37; }

        /* Profil Admin */
        .profile-container { position: relative; cursor: pointer; }
        .admin-profile {
            display: flex; align-items: center; background-color: #d4af37;
            padding: 5px 15px 5px 5px; border-radius: 30px; color: black; font-weight: bold;
        }
        .admin-profile img {
            width: 35px; height: 35px; border-radius: 50%; object-fit: cover;
            margin-right: 10px; border: 2px solid black; background: #fff;
        }
        /* Dropdown CSS */
        .dropdown-menu {
            display: none; position: absolute; top: 50px; right: 0; width: 200px;
            border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            border: 2px solid #333; animation: fadeIn 0.2s ease-in-out;
        }
        .dropdown-header { background-color: #d4af37; padding: 15px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .dropdown-header img { width: 45px; height: 45px; border-radius: 50%; border: 2px solid white; object-fit: cover; }
        .dropdown-header span { font-family: 'Risque', serif; font-size: 18px; color: white; text-shadow: 1px 1px 2px black; }
        .dropdown-body { background-color: #5d5318; padding: 10px 0; }
        .dropdown-item {
            display: flex; align-items: center; justify-content: center; padding: 8px 15px;
            color: white; text-decoration: none; font-family: 'MetalMania', cursive; font-size: 18px; transition: 0.3s; text-transform: uppercase;
        }
        .dropdown-item:hover { background-color: rgba(255,255,255,0.1); }
        .dropdown-item svg { width: 20px; height: 20px; fill: white; margin-right: 8px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }


        /* --- MAIN CONTAINER --- */
        .container {
            position: relative; z-index: 2; width: 90%; max-width: 900px;
            /* Container utama dibuat transparan sesuai gambar, border putih di luar */
            border: 2px solid white; border-radius: 15px;
            padding: 40px; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px);
        }

        /* Header: Title kiri, Button kanan */
        .form-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }
        .page-title {
            font-family: 'MetalMania', cursive; color: white; font-size: 42px;
            text-shadow: 2px 2px 4px black; letter-spacing: 2px; text-transform: uppercase;
        }
        .btn-save {
            background-color: #d4af37; color: white; border: none;
            padding: 10px 40px; border-radius: 5px;
            font-family: 'Risque', serif; font-size: 20px; font-weight: bold;
            cursor: pointer; transition: 0.3s;
        }
        .btn-save:hover { background-color: #c49f27; }

        /* --- GOLDEN FORM CARD --- */
        .gold-card {
            background: rgba(139, 115, 20, 0.85); /* Warna Emas Kecoklatan Gelap */
            border-radius: 15px; padding: 40px;
            margin-bottom: 30px;
        }

        /* Area Input Soal */
        .question-wrapper {
            position: relative;
            background: rgba(0, 0, 0, 0.3); /* Latar belakang input soal agak gelap */
            border-radius: 10px; padding: 20px;
            margin-bottom: 40px;
            min-height: 150px;
        }

        .question-input {
            width: 100%; background: transparent; border: none;
            color: white; font-family: 'Risque', serif; font-size: 18px;
            resize: none; outline: none; min-height: 120px;
        }
        .question-input::placeholder { color: rgba(255,255,255,0.8); }

        /* Tombol Add File (Icon + Text) */
        .btn-add-file {
            position: absolute; top: 15px; right: 15px;
            background: rgba(255,255,255,0.2); color: white;
            padding: 5px 12px; border-radius: 5px; cursor: pointer;
            font-family: 'Risque', serif; font-size: 14px;
            display: flex; align-items: center; gap: 8px; transition: 0.3s;
        }
        .btn-add-file:hover { background: rgba(255,255,255,0.4); }
        .btn-add-file svg { width: 16px; height: 16px; fill: white; }

        /* --- JAWABAN GRID --- */
        .answers-grid {
            display: grid; grid-template-columns: 1fr 1fr; /* 2 Kolom */
            gap: 20px 60px; /* Jarak antar kolom lebih lebar */
        }

        .answer-item {
            display: flex; align-items: center; gap: 15px;
        }

        /* CUSTOM RADIO BUTTON (Agar bulat putih seperti gambar) */
        .custom-radio {
            appearance: none;
            width: 24px; height: 24px;
            border: 2px solid white; border-radius: 50%;
            cursor: pointer; flex-shrink: 0;
            background: transparent;
            position: relative;
        }
        .custom-radio:checked {
            background-color: white; /* Isi putih saat dipilih */
            box-shadow: 0 0 5px rgba(255,255,255,0.8);
        }

        .answer-text-input {
            background: transparent; border: none;
            color: white; font-family: 'Risque', serif; font-size: 18px;
            width: 100%; outline: none;
        }
        .answer-text-input::placeholder { color: white; opacity: 0.9; }

        /* BACK LINK */
        .back-link {
            display: block; text-align: center;
            font-family: 'MetalMania', cursive; font-size: 32px;
            color: white; text-decoration: none; cursor: pointer;
            text-shadow: 2px 2px 4px black; letter-spacing: 2px;
            text-transform: uppercase;
        }
        .back-link:hover { color: #d4af37; }

    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-logo">WEDNESDAY QUIZ</div>
        <div class="nav-menu">
            <a href="dashboard.php">HOME</a>
            
            <div class="profile-container" onclick="toggleDropdown()">
                <div class="admin-profile">
                    <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Admin"> 
                    <span><?php echo htmlspecialchars($username); ?></span>
                </div>
                <div class="dropdown-menu" id="adminDropdown">
                    <div class="dropdown-header">
                        <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Admin">
                        <span><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="dropdown-body">
                        <a href="edit_admin.php" class="dropdown-item">
                            <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            PASSWORD
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

    <div class="container">
        <form action="" method="POST" enctype="multipart/form-data">

            <?php if (!empty($error)): ?>
                <div style="margin-bottom: 15px; color: #fff; font-family: 'Risque', serif; background: rgba(200,0,0,0.35); border: 1px solid rgba(255,255,255,0.3); padding: 10px 15px; border-radius: 10px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-header">
                <h1 class="page-title">ADD QUESTION</h1>
                <button type="submit" name="save_question" class="btn-save">Save</button>
            </div>

            <div class="gold-card">
                
                <div class="question-wrapper">
                    <textarea name="question_text" class="question-input" placeholder="Add your question here" required></textarea>
                    
                    <label for="fileUpload" class="btn-add-file">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        Add File
                    </label>
                    <input type="file" id="fileUpload" name="question_image" style="display: none;" onchange="alert('File Selected!')">
                </div>

                <div class="answers-grid">
                    <div class="answer-item">
                        <input type="radio" name="correct_answer" value="0" class="custom-radio" required>
                        <input type="text" name="answers[]" class="answer-text-input" placeholder="Add Answer" required>
                    </div>
                    
                    <div class="answer-item">
                        <input type="radio" name="correct_answer" value="1" class="custom-radio">
                        <input type="text" name="answers[]" class="answer-text-input" placeholder="Add Answer" required>
                    </div>
                    
                    <div class="answer-item">
                        <input type="radio" name="correct_answer" value="2" class="custom-radio">
                        <input type="text" name="answers[]" class="answer-text-input" placeholder="Add Answer" required>
                    </div>
                    
                    <div class="answer-item">
                        <input type="radio" name="correct_answer" value="3" class="custom-radio">
                        <input type="text" name="answers[]" class="answer-text-input" placeholder="Add Answer" required>
                    </div>
                </div>

            </div>
        </form>

        <a href="manage_soal.php" class="back-link">BACK</a>
    </div>

    <script>
        function toggleDropdown() {
            var dropdown = document.getElementById("adminDropdown");
            if (dropdown.style.display === "block") { dropdown.style.display = "none"; } 
            else { dropdown.style.display = "block"; }
        }
        window.onclick = function(event) {
            if (!event.target.closest('.profile-container')) {
                document.getElementById("adminDropdown").style.display = "none";
            }
        }
    </script>

</body>
</html>