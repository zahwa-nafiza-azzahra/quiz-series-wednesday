<?php
session_start();

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

$questionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($questionId <= 0) {
    header('Location: manage_soal.php');
    exit();
}

$conn = getDbConnection();

$error = '';

$currentQuestionText = '';
$currentImageUrl = null;
$currentOptions = ['', '', '', ''];
$currentOptionIds = [0, 0, 0, 0];
$currentCorrectIndex = 0;

$stmt = $conn->prepare('SELECT question_text, image_url FROM questions WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $questionId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    $conn->close();
    header('Location: manage_soal.php');
    exit();
}

$currentQuestionText = (string) ($row['question_text'] ?? '');
$currentImageUrl = ($row['image_url'] ?? null);

$optStmt = $conn->prepare('SELECT id, option_text, is_correct, option_order FROM options WHERE question_id = ? ORDER BY option_order ASC');
$optStmt->bind_param('i', $questionId);
$optStmt->execute();
$optRes = $optStmt->get_result();
if ($optRes) {
    $optsByOrder = [];
    $correctOrder = null;
    while ($optRow = $optRes->fetch_assoc()) {
        $order = (int) ($optRow['option_order'] ?? 0);
        if ($order <= 0) {
            continue;
        }
        if (!isset($optsByOrder[$order])) {
            $optsByOrder[$order] = [
                'id' => (int) ($optRow['id'] ?? 0),
                'text' => (string) ($optRow['option_text'] ?? ''),
            ];
        }
        if ((int) ($optRow['is_correct'] ?? 0) === 1) {
            $correctOrder = $order;
        }
    }

    if (!empty($optsByOrder)) {
        ksort($optsByOrder);
        $orders = array_keys($optsByOrder);
        $vals = array_values($optsByOrder);
        while (count($vals) < 4) {
            $vals[] = ['id' => 0, 'text' => ''];
        }

        $vals = array_slice($vals, 0, 4);
        $currentOptions = array_map(static fn($v) => (string) ($v['text'] ?? ''), $vals);
        $currentOptionIds = array_map(static fn($v) => (int) ($v['id'] ?? 0), $vals);

        if ($correctOrder !== null) {
            $idx = array_search($correctOrder, $orders, true);
            if ($idx !== false) {
                $currentCorrectIndex = (int) $idx;
            }
        }
    }
}
$optStmt->close();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['question_text']) && isset($_POST['answers'])) {
    $questionText = isset($_POST['question_text']) ? trim((string) $_POST['question_text']) : '';
    $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : [];
    $optionIds = isset($_POST['option_id']) && is_array($_POST['option_id']) ? $_POST['option_id'] : [];
    $correctAnswer = isset($_POST['correct_answer']) ? (int) $_POST['correct_answer'] : -1;

    $answers = array_values(array_map(static fn($v) => trim((string) $v), $answers));
    $optionIds = array_values(array_map(static fn($v) => (int) $v, $optionIds));
    while (count($answers) < 4) {
        $answers[] = '';
    }
    $answers = array_slice($answers, 0, 4);

    while (count($optionIds) < 4) {
        $optionIds[] = 0;
    }
    $optionIds = array_slice($optionIds, 0, 4);

    if ($questionText === '') {
        $error = 'Question text is required.';
    } elseif ($correctAnswer < 0 || $correctAnswer > 3) {
        $error = 'Please select a correct answer.';
    } elseif (in_array(0, $optionIds, true)) {
        $error = 'Option ID is missing. Please reload the page and try again.';
    } else {
        $imageUrl = $currentImageUrl;
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

            $lockOpt = $conn->prepare('SELECT id FROM options WHERE question_id = ? FOR UPDATE');
            $lockOpt->bind_param('i', $questionId);
            $lockOpt->execute();
            $lockOpt->close();

            // Update question
            $qStmt = $conn->prepare('UPDATE questions SET question_text = ?, image_url = ? WHERE id = ?');
            $qStmt->bind_param('ssi', $questionText, $imageUrl, $questionId);
            $qStmt->execute();
            $qStmt->close();

            // Update existing options (no INSERT to avoid duplicates)
            $updOpt = $conn->prepare('UPDATE options SET option_text = ?, is_correct = ?, option_order = ? WHERE id = ? AND question_id = ?');
            foreach ($answers as $idx => $optText) {
                $isCorrect = ($idx === $correctAnswer) ? 1 : 0;
                $order = $idx + 1;
                $optId = (int) ($optionIds[$idx] ?? 0);
                if ($optId <= 0) {
                    throw new RuntimeException('Option ID missing.');
                }
                $updOpt->bind_param('siiii', $optText, $isCorrect, $order, $optId, $questionId);
                if (!$updOpt->execute()) {
                    throw new RuntimeException('Failed to update option data.');
                }
            }
            $updOpt->close();

            $conn->commit();

            header('Location: manage_soal.php');
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Failed to save changes.';
        }
    }

    $currentQuestionText = $questionText;
    $currentOptions = $answers;
    $currentOptionIds = $optionIds;
    $currentCorrectIndex = ($correctAnswer >= 0 && $correctAnswer <= 3) ? $correctAnswer : 0;
    $currentImageUrl = $imageUrl;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question - Admin</title>
    <style>
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

        .profile-container { position: relative; cursor: pointer; }
        .admin-profile { display: flex; align-items: center; background-color: #d4af37; padding: 5px 15px 5px 5px; border-radius: 30px; color: black; font-weight: bold; }
        .admin-profile img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; margin-right: 10px; border: 2px solid black; background: #fff; }

        .dropdown-menu {
            display: none; position: absolute; top: 50px; right: 0; width: 200px;
            border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            border: 2px solid #333; animation: fadeIn 0.2s ease-in-out;
        }

        .dropdown-header { background-color: #d4af37; padding: 15px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .dropdown-header img { width: 45px; height: 45px; border-radius: 50%; border: 2px solid white; object-fit: cover; }
        .dropdown-header span { font-family: 'Risque', serif; font-size: 18px; color: white; text-shadow: 1px 1px 2px black; }
        .dropdown-body { background-color: #5d5318; padding: 10px 0; }
        .dropdown-item { display: flex; align-items: center; justify-content: center; padding: 8px 15px; color: white; text-decoration: none; font-family: 'MetalMania', cursive; font-size: 18px; transition: 0.3s; text-transform: uppercase; }
        .dropdown-item:hover { background-color: rgba(255,255,255,0.1); }
        .dropdown-item svg { width: 20px; height: 20px; fill: white; margin-right: 8px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .container {
            position: relative; z-index: 2; width: 90%; max-width: 900px;
            border: 2px solid white; border-radius: 15px;
            padding: 40px; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px);
        }

        .form-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }

        .page-title {
            font-family: 'MetalMania', cursive; color: white; font-size: 42px;
            text-shadow: 2px 2px 4px black; letter-spacing: 2px; text-transform: uppercase;
        }

        .btn-save {
            background-color: #d4af37;
            color: white;
            border: none;
            padding: 10px 40px; border-radius: 5px;
            font-family: 'Risque', serif;
            font-size: 20px; font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-save:hover { background-color: #c49f27; }

        .gold-card {
            background: rgba(139, 115, 20, 0.85);
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
        }

        .question-wrapper {
            position: relative;
            background: rgba(0, 0, 0, 0.3);
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

        .btn-add-file {
            position: absolute; top: 15px; right: 15px;
            background: rgba(255,255,255,0.2); color: white;
            padding: 5px 12px; border-radius: 5px; cursor: pointer;
            font-family: 'Risque', serif; font-size: 14px;
            display: flex; align-items: center; gap: 8px; transition: 0.3s;
        }

        .btn-add-file:hover { background: rgba(255,255,255,0.4); }

        .btn-add-file svg { width: 16px; height: 16px; fill: white; }

        .answers-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 20px 60px;
        }

        .answer-item {
            display: flex; align-items: center; gap: 15px;
        }

        .custom-radio {
            appearance: none;
            width: 24px; height: 24px;
            border: 2px solid white;
            border-radius: 50%;
            cursor: pointer;
            background: transparent;
            position: relative;
            flex-shrink: 0;
        }

        .custom-radio:checked {
            background-color: white;
            box-shadow: 0 0 5px rgba(255,255,255,0.8);
        }

        .answer-text-input {
            background: transparent; border: none;
            color: white; font-family: 'Risque', serif; font-size: 18px;
            width: 100%; outline: none;
        }

        .answer-text-input::placeholder { color: white; opacity: 0.9; }

        .msg {
            margin: 0 0 16px 0;
            padding: 10px 14px;
            border-radius: 10px;
            color: #ffd0d0;
            background: rgba(200,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .back-link {
            display: block; text-align: center;
            font-family: 'MetalMania', cursive; font-size: 32px;
            color: white; text-decoration: none; cursor: pointer;
            text-shadow: 2px 2px 4px black; letter-spacing: 2px;
            text-transform: uppercase;
        }

        .back-link:hover { color: #d4af37; }

        @media (max-width: 700px) {
            .container { padding: 26px; }
            .answers-grid { grid-template-columns: 1fr; gap: 14px; }
        }
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
        <form action="" method="POST" enctype="multipart/form-data" id="editQuestionForm">
            <?php if ($error !== ''): ?>
                <div style="margin-bottom: 15px; color: #fff; font-family: 'Risque', serif; background: rgba(200,0,0,0.35); border: 1px solid rgba(255,255,255,0.3); padding: 10px 15px; border-radius: 10px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-header">
                <h1 class="page-title">EDIT QUESTION</h1>
                <button type="submit" name="save_changes" class="btn-save" id="saveChangesBtn">Save Changes</button>
            </div>

            <div class="gold-card">
                <div class="question-wrapper">
                    <textarea name="question_text" class="question-input" placeholder="Add your question here" required><?php echo htmlspecialchars($currentQuestionText); ?></textarea>
                    
                    <label for="fileUpload" class="btn-add-file">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        Add File
                    </label>
                    <input type="file" id="fileUpload" name="question_image" style="display: none;" onchange="alert('File Selected!')">
                </div>

                <div class="answers-grid">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="answer-item">
                            <input type="radio" name="correct_answer" value="<?php echo $i; ?>" class="custom-radio" <?php echo ($currentCorrectIndex === $i) ? 'checked' : ''; ?> required>
                            <input type="hidden" name="option_id[]" value="<?php echo (int) ($currentOptionIds[$i] ?? 0); ?>">
                            <input type="text" name="answers[]" class="answer-text-input" placeholder="Add Answer" value="<?php echo htmlspecialchars($currentOptions[$i] ?? ''); ?>" required>
                        </div>
                    <?php endfor; ?>
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

        (function () {
            var form = document.getElementById('editQuestionForm');
            var btn = document.getElementById('saveChangesBtn');
            if (!form || !btn) return;
            form.addEventListener('submit', function () {
                btn.disabled = true;
            });
        })();
    </script>

</body>
</html>
