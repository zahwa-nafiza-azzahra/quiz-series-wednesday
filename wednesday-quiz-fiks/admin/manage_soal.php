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

$conn = getDbConnection();

$questions = [];

if (isset($_POST['delete_question_id'])) {
    $deleteId = (int) $_POST['delete_question_id'];
    if ($deleteId > 0) {
        try {
            $conn->begin_transaction();

            $delOpt = $conn->prepare('DELETE FROM options WHERE question_id = ?');
            $delOpt->bind_param('i', $deleteId);
            $delOpt->execute();
            $delOpt->close();

            $delQ = $conn->prepare('DELETE FROM questions WHERE id = ?');
            $delQ->bind_param('i', $deleteId);
            $delQ->execute();
            $delQ->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
        }
    }
}

$sql = "SELECT q.id AS question_id, q.question_text, q.image_url, o.option_text, o.is_correct, o.option_order\n"
    . "FROM questions q\n"
    . "LEFT JOIN options o ON o.question_id = q.id\n"
    . "ORDER BY q.id, o.option_order";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $qid = (int) $row['question_id'];
        if (!isset($questions[$qid])) {
            $questions[$qid] = [
                'id' => $qid,
                'question' => $row['question_text'],
                'img' => $row['image_url'] ?: null,
                'options' => [],
                'options_by_order' => [],
                'correct' => null,
                'correct_order' => null,
            ];
        }

        if ($row['option_text'] !== null) {
            $order = isset($row['option_order']) ? (int) $row['option_order'] : 0;
            if ($order <= 0) {
                $order = count($questions[$qid]['options_by_order']) + 1;
            }

            if (!isset($questions[$qid]['options_by_order'][$order])) {
                $questions[$qid]['options_by_order'][$order] = $row['option_text'];
            }

            if ((int) $row['is_correct'] === 1) {
                $questions[$qid]['correct_order'] = $order;
            }
        }
    }
}

$conn->close();

foreach ($questions as &$q) {
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

$questions = array_values($questions);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - Admin</title>
    <style>
        /* --- FONTS --- */
        @font-face { font-family: 'MetalMania'; src: url('../asset/MetalMania-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Risque'; src: url('../asset/Risque-Regular.ttf') format('truetype'); }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Risque', serif;
            min-height: 100vh;
            width: 100%;
            background: url('../asset/background.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
            padding-top: 100px;
            padding-bottom: 50px;
        }

        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7); z-index: 0;
        }

        /* --- NAVBAR FIXED --- */
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
        .admin-profile {
            display: flex; align-items: center; background-color: #d4af37;
            padding: 5px 15px 5px 5px; border-radius: 30px; color: black; font-weight: bold;
        }
        .admin-profile img {
            width: 35px; height: 35px; border-radius: 50%; object-fit: cover;
            margin-right: 10px; border: 2px solid black; background: #fff;
        }

        /* DROPDOWN (Copy dari Dashboard) */
        .dropdown-menu {
            display: none; position: absolute; top: 50px; right: 0; width: 200px;
            border-radius: 15px; overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5); border: 2px solid #333;
            animation: fadeIn 0.2s ease-in-out;
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


        /* --- CONTENT MAIN --- */
        .container {
            position: relative; z-index: 10; width: 90%; max-width: 900px;
            border: 2px solid white; border-radius: 15px;
            padding: 40px; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px);
            max-height: calc(100vh - 160px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header: Title & Add Button */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px;
        }
        .page-title {
            font-family: 'MetalMania', cursive; color: white; font-size: 42px;
            text-shadow: 2px 2px 4px black; letter-spacing: 2px;
        }
        .btn-add {
            background-color: #d4af37; color: white; text-decoration: none;
            font-family: 'Risque', serif; padding: 10px 22px; border-radius: 8px;
            font-size: 18px; border: none; transition: 0.3s;
            display: flex; align-items: center; gap: 5px;
        }
        .btn-add:hover { background-color: #c49f27; transform: scale(1.04); }

        /* --- QUESTION LIST --- */
        .q-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
            flex: 1;
            overflow-y: auto;
            padding-right: 8px;
        }

        .q-list::-webkit-scrollbar { width: 8px; }
        .q-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.18); border-radius: 10px; }
        .q-list::-webkit-scrollbar-track { background: rgba(0,0,0,0.25); border-radius: 10px; }

        /* Kartu Soal (Gold Bar) */
        .q-card {
            background: rgba(139, 115, 20, 0.85);
            border-radius: 16px; padding: 30px 34px;
            display: flex; gap: 26px; align-items: center;
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
        }

        /* Thumbnail Gambar Soal */
        .q-thumb {
            width: 120px; height: 80px; object-fit: cover;
            border: 2px solid white; border-radius: 8px;
            flex-shrink: 0;
        }

        /* Konten Teks Soal */
        .q-content { flex-grow: 1; }
        
        .q-text {
            font-family: 'Risque', serif; color: white; font-size: 24px;
            margin-bottom: 18px; text-shadow: 1px 1px 2px black;
        }

        /* Grid Jawaban */
        .q-options {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px 80px;
            font-family: 'Risque', serif; font-size: 18px; color: rgba(255,255,255,0.9);
        }

        .opt-item { display: flex; align-items: center; gap: 8px; }
        .checkmark { color: white; font-weight: bold; font-size: 18px; } /* Centang Putih */

        /* Tombol Aksi (Edit/Delete) di Kanan Atas */
        .q-actions {
            display: flex; gap: 10px;
            position: absolute; top: 20px; right: 20px;
        }

        .btn-action {
            background: rgba(0,0,0,0.6); color: white;
            text-decoration: none; padding: 5px 15px; border-radius: 20px;
            font-family: 'Risque', serif; font-size: 14px;
            display: flex; align-items: center; gap: 5px;
            border: 1px solid rgba(255,255,255,0.3); transition: 0.3s;
        }
        .btn-action:hover { background: #d4af37; color: black; }
        .btn-action svg { width: 14px; height: 14px; fill: currentColor; }

        /* Tombol Back di Bawah */
        .back-link {
            display: block; text-align: center; margin-top: 20px;
            font-family: 'MetalMania', cursive; font-size: 32px;
            color: white; text-decoration: none; cursor: pointer;
            text-shadow: 2px 2px 4px black; letter-spacing: 2px;
            text-transform: uppercase;
        }
        .back-link:hover { color: #d4af37; }

        .confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            justify-content: center;
            align-items: center;
            background: transparent;
            overflow: hidden;
        }

        .confirm-modal::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(0, 0, 0, 0.58), rgba(0, 0, 0, 0.58)), url('../asset/background.jpg') no-repeat center center/cover;
            filter: none;
        }

        .confirm-box {
            width: 92%;
            max-width: 680px;
            padding: 40px 46px 34px;
            border: 4px solid rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            background: rgba(55, 55, 55, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.75);
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .confirm-text {
            font-family: 'MetalMania', cursive;
            color: white;
            font-size: 36px;
            line-height: 1.1;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-shadow: 4px 4px 12px rgba(0,0,0,0.9);
            margin-bottom: 22px;
        }

        .confirm-actions {
            display: flex;
            justify-content: center;
            gap: 70px;
            margin-top: 12px;
        }

        .confirm-btn {
            width: 150px;
            height: 42px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-family: 'MetalMania', cursive;
            font-size: 24px;
            letter-spacing: 1px;
            color: white;
            background: rgba(212, 175, 55, 0.92);
            box-shadow: 0 6px 12px rgba(0,0,0,0.55);
            text-shadow: 2px 2px 6px rgba(0,0,0,0.85);
            transition: transform 0.2s, background 0.2s;
        }

        .confirm-btn:hover {
            background: rgba(196, 159, 39, 0.96);
            transform: scale(1.04);
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
        <div class="page-header">
            <h1 class="page-title">QUESTIONS</h1>
            <a href="add_soal.php" class="btn-add">
                <span>+</span> Add Question
            </a>
        </div>

        <div class="q-list">
            <?php foreach($questions as $q): ?>
                <div class="q-card">
                    <?php if($q['img']): ?>
                        <img src="<?php echo $q['img']; ?>" alt="Soal" class="q-thumb">
                    <?php endif; ?>

                    <div class="q-content">
                        <div class="q-text"><?php echo htmlspecialchars($q['question']); ?></div>
                        
                        <div class="q-options">
                            <?php foreach($q['options'] as $idx => $opt): ?>
                                <div class="opt-item">
                                    <?php if($idx === $q['correct']): ?>
                                        <span class="checkmark">✓</span>
                                    <?php endif; ?>
                                    
                                    <span><?php echo htmlspecialchars($opt); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="q-actions">
                        <a href="edit_soal.php?id=<?php echo (int) $q['id']; ?>" class="btn-action">
                            <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            Edit
                        </a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="delete_question_id" value="<?php echo (int) $q['id']; ?>">
                            <button type="button" class="btn-action" style="cursor:pointer;" onclick="openDeleteConfirm(this.form)">
                                <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                Hapus
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="confirm-modal" id="deleteConfirmModal" aria-hidden="true">
            <div class="confirm-box" role="dialog" aria-modal="true">
                <div class="confirm-text">ARE YOU SURE WANT<br>TO DELETE THIS QUESTION?</div>
                <div class="confirm-actions">
                    <button type="button" class="confirm-btn" id="confirmYesBtn">YES</button>
                    <button type="button" class="confirm-btn" id="confirmNoBtn">NO</button>
                </div>
            </div>
        </div>

        <a href="dashboard.php" class="back-link">BACK</a>
    </div>

    <script>
        var deleteModalEl = null;
        var confirmYesBtn = null;
        var confirmNoBtn = null;
        var pendingDeleteForm = null;

        function toggleDropdown() {
            var dropdown = document.getElementById("adminDropdown");
            if (dropdown.style.display === "block") { dropdown.style.display = "none"; } 
            else { dropdown.style.display = "block"; }
        }

        function openDeleteConfirm(formEl) {
            pendingDeleteForm = formEl;
            if (deleteModalEl) {
                deleteModalEl.style.display = 'flex';
                deleteModalEl.setAttribute('aria-hidden', 'false');
            }
        }

        function closeDeleteConfirm() {
            pendingDeleteForm = null;
            if (deleteModalEl) {
                deleteModalEl.style.display = 'none';
                deleteModalEl.setAttribute('aria-hidden', 'true');
            }
        }

        window.addEventListener('DOMContentLoaded', function () {
            deleteModalEl = document.getElementById('deleteConfirmModal');
            confirmYesBtn = document.getElementById('confirmYesBtn');
            confirmNoBtn = document.getElementById('confirmNoBtn');

            if (confirmYesBtn) {
                confirmYesBtn.addEventListener('click', function () {
                    if (pendingDeleteForm) pendingDeleteForm.submit();
                });
            }

            if (confirmNoBtn) {
                confirmNoBtn.addEventListener('click', closeDeleteConfirm);
            }

            if (deleteModalEl) {
                deleteModalEl.addEventListener('click', function (e) {
                    if (e.target === deleteModalEl) closeDeleteConfirm();
                });
            }

            window.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeDeleteConfirm();
            });
        });

        window.onclick = function(event) {
            if (!event.target.closest('.profile-container')) {
                document.getElementById("adminDropdown").style.display = "none";
            }
        }
    </script>

</body>
</html>