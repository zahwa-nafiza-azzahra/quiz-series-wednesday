<?php
session_start();

// Cek Login
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
    header("Location: ../login.php");
    exit();
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
$avatarSrc = '../asset/profile_placeholder.jpg'; // Default

// Load avatar dari database untuk user yang sedang login
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
            // Jika path dimulai dengan 'uploads/', tambahkan '../asset/'
            if (strpos($profilePic, 'uploads/') === 0) {
                $fullPath = '../asset/' . $profilePic;
                // Verify file exists
                if (file_exists(__DIR__ . '/../asset/' . $profilePic)) {
                    $avatarSrc = $fullPath;
                } else {
                    $avatarSrc = '../asset/profile_placeholder.jpg';
                }
            }
            // Jika hanya filename tanpa path, cek dulu di asset langsung (misal: 'profildefault.jpg', 'admin.jpg')
            elseif (strpos($profilePic, '/') === false && strpos($profilePic, '../') === false) {
                // Cek apakah file ada di asset langsung
                if (file_exists(__DIR__ . '/../asset/' . $profilePic)) {
                    $avatarSrc = '../asset/' . $profilePic;
                }
                // Jika tidak ada di asset, cek di uploads
                elseif (file_exists(__DIR__ . '/../asset/uploads/' . $profilePic)) {
                    $avatarSrc = '../asset/uploads/' . $profilePic;
                } else {
                    $avatarSrc = '../asset/profile_placeholder.jpg';
                }
            }
            // Jika sudah path lengkap dengan '../asset/', verify file exists
            elseif (strpos($profilePic, '../asset/') === 0) {
                $relativePath = str_replace('../asset/', '', $profilePic);
                if (file_exists(__DIR__ . '/../asset/' . $relativePath)) {
                    $avatarSrc = $profilePic;
                } else {
                    $avatarSrc = '../asset/profile_placeholder.jpg';
                }
            }
            // Untuk file khusus di asset (backward compatibility)
            elseif ($profilePic === 'admin.jpg' || $profilePic === 'profildefault.jpg') {
                $avatarSrc = '../asset/' . $profilePic;
            } else {
                $avatarSrc = '../asset/profile_placeholder.jpg';
            }
        }
    }
    $stmtAvatar->close();
    $connAvatar->close();
}

require_once __DIR__ . '/../config/db.php';
$conn = getDbConnection();

// Tentukan quiz yang dipakai leaderboard (Default ke quiz_id 1 atau quiz aktif pertama)
$quizId = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : null;
$quizTitle = 'LEADERBOARD';

if ($quizId === null) {
    $quizRes = $conn->query("SELECT id, title FROM quizzes WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
    if ($quizRes && $quizRow = $quizRes->fetch_assoc()) {
        $quizId = (int) $quizRow['id'];
        $quizTitle = $quizRow['title'];
    } else {
        $quizId = 1;
    }
}

// --- LEADERBOARD QUERY ---
// Ambil skor terbaik per user dari tabel users, urutkan dari skor tertinggi
// Query ini selalu mengambil data terbaru dari database (tidak ada cache)
$leaderboard = [];
$sql = "
    SELECT 
        u.id AS user_id,
        u.username,
        COALESCE(u.full_name, u.username) AS display_name,
        u.total_score,
        u.profile_picture
    FROM users u
    WHERE u.total_score > 0
    ORDER BY u.total_score DESC, u.created_at ASC
";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            // Handle profile picture for all users - selalu ambil dari database
            $profilePic = $row['profile_picture'];
            
            // Jika profile_picture ada di database, format path dengan benar
            if ($profilePic && !empty($profilePic)) {
                // Jika path dimulai dengan 'uploads/', tambahkan '../asset/'
                if (strpos($profilePic, 'uploads/') === 0) {
                    $fullPath = '../asset/' . $profilePic;
                    // Verify file exists
                    if (file_exists(__DIR__ . '/../asset/' . $profilePic)) {
                        $profilePic = $fullPath;
                    } else {
                        $profilePic = '../asset/profile_placeholder.jpg';
                    }
                }
                // Jika hanya filename tanpa path, cek dulu di asset langsung (misal: 'profildefault.jpg', 'admin.jpg')
                elseif (strpos($profilePic, '/') === false && strpos($profilePic, '../') === false) {
                    // Cek apakah file ada di asset langsung
                    if (file_exists(__DIR__ . '/../asset/' . $profilePic)) {
                        $profilePic = '../asset/' . $profilePic;
                    }
                    // Jika tidak ada di asset, cek di uploads
                    elseif (file_exists(__DIR__ . '/../asset/uploads/' . $profilePic)) {
                        $profilePic = '../asset/uploads/' . $profilePic;
                    } else {
                        $profilePic = '../asset/profile_placeholder.jpg';
                    }
                }
                // Jika sudah path lengkap dengan '../asset/', verify file exists
                elseif (strpos($profilePic, '../asset/') === 0) {
                    $relativePath = str_replace('../asset/', '', $profilePic);
                    if (!file_exists(__DIR__ . '/../asset/' . $relativePath)) {
                        $profilePic = '../asset/profile_placeholder.jpg';
                    }
                }
                // Untuk file khusus di asset (sudah di-handle di atas, tapi tetap untuk backward compatibility)
                elseif ($profilePic === 'admin.jpg' || $profilePic === 'profildefault.jpg') {
                    $profilePic = '../asset/' . $profilePic;
                }
            } else {
                // Jika tidak ada profile_picture di database, gunakan placeholder
                $profilePic = '../asset/profile_placeholder.jpg';
            }
            
            $leaderboard[] = [
                'username' => $row['username'],
                'name' => $row['display_name'],
                'score' => (int) $row['total_score'],
                'profile_picture' => $profilePic
            ];
        }
    }
    $stmt->close();
}

// Pisahkan data untuk Top 3, List Lainnya (4-20), dan Rank User Sendiri
$top3 = [];
$others = []; // Menampilkan rank 4 sampai 20 untuk tampilan list
$myRankData = null;

$rankCounter = 1;
foreach ($leaderboard as $entry) {
    // Top 3 untuk Podium
    if ($rankCounter <= 3) {
        $top3[] = array_merge($entry, ['rank' => $rankCounter]);
    } 
    // Rank 4-20 untuk List (diperluas dari 7 menjadi 20)
    elseif ($rankCounter <= 20) { 
        $others[] = array_merge($entry, ['rank' => $rankCounter]);
    }

    // Cari data user yang sedang login
    if ($entry['username'] === $username) {
        $myRankData = array_merge($entry, ['rank' => $rankCounter]);
    }

    $rankCounter++;
}

// Jika user login ranknya di luar 20 besar, tetap tampilkan di baris paling bawah
$showMyRankSeparately = false;
if ($myRankData && $myRankData['rank'] > 20) {
    $showMyRankSeparately = true;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Wednesday Quiz</title>
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
            overflow-y: auto;
            padding-top: 110px;
            padding-bottom: 40px;
        }

        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6); z-index: 0; pointer-events: none;
        }

        /* --- NAVBAR (KONSISTEN DENGAN DASHBOARD) --- */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .nav-logo {
            font-family: 'MetalMania', cursive;
            font-size: 26px;
            color: white;
            letter-spacing: 2px;
        }
        .nav-menu { display: flex; align-items: center; gap: 30px; }
        .nav-menu a {
            font-family: 'Risque', serif;
            color: white;
            text-decoration: none;
            font-size: 20px;
            transition: 0.3s;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .nav-menu a:hover { color: #d4af37; }

        .profile-container {
            position: relative;
            cursor: pointer;
        }

        .user-profile {
            display: flex;
            align-items: center;
            background-color: #d4af37;
            padding: 5px 15px 5px 5px;
            border-radius: 30px;
            color: black;
            font-weight: bold;
            font-family: 'Risque', serif;
        }

        .user-profile img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 2px solid black;
            background: #fff;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 50px;
            right: 0;
            width: 250px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            animation: fadeIn 0.2s ease-in-out;
            border: 2px solid #333;
        }

        .dropdown-header {
            background-color: #d4af37;
            padding: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .dropdown-header img {
            width: 50px; height: 50px; border-radius: 50%;
            border: 2px solid white; object-fit: cover;
        }
        .dropdown-header span {
            font-family: 'Risque', serif;
            font-size: 20px; color: white;
            text-shadow: 1px 1px 2px black;
        }

        .dropdown-body {
            background-color: #5d5318;
            padding: 15px 0;
        }

        .dropdown-item {
            display: flex; align-items: center;
            padding: 10px 25px;
            color: white; text-decoration: none;
            font-family: 'MetalMania', cursive; font-size: 20px;
            transition: 0.3s;
            text-transform: uppercase;
        }

        .dropdown-item:hover {
            background-color: rgba(255,255,255,0.1);
            padding-left: 35px;
        }

        .dropdown-item svg {
            width: 24px; height: 24px; fill: white;
            margin-right: 15px;
        }

        /* --- MAIN LEADERBOARD CARD --- */
        .leaderboard-container {
            position: relative;
            z-index: 10;
            width: 90%;
            max-width: 850px;
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid rgba(255, 255, 255, 0.85);
            border-radius: 15px;
            padding: 26px 30px;
            box-shadow: 0 0 40px rgba(0,0,0,0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .lb-title {
            font-family: 'MetalMania', cursive;
            font-size: 48px;
            color: #FFF;
            text-align: center;
            margin-bottom: 22px;
            letter-spacing: 3px;
            text-shadow: 2px 2px 4px black;
        }

        /* --- PODIUM SECTION (Top 3 - Segitiga) --- */
        .podium-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            grid-template-rows: auto auto;
            justify-items: center;
            align-items: end;
            gap: 20px;
            width: 100%;
            max-width: 600px;
            margin: 0 auto 28px;
            min-height: 240px;
            position: relative;
        }

        .podium-card {
            width: 160px;
            position: relative;
            background: rgba(184, 134, 11, 0.7);
            border: 2px solid #D4AF37;
            border-radius: 15px;
            padding: 16px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }

        /* Rank 1 di atas (baris pertama, kolom tengah) */
        .podium-card.rank-1 {
            grid-column: 2;
            grid-row: 1;
            transform: scale(1.10);
            z-index: 3;
            background: rgba(212, 175, 55, 0.9);
            border-color: #FFF;
            border-width: 3px;
            margin-bottom: 10px;
        }

        /* Rank 2 di kiri bawah */
        .podium-card.rank-2 {
            grid-column: 1;
            grid-row: 2;
            z-index: 2;
        }

        /* Rank 3 di kanan bawah */
        .podium-card.rank-3 {
            grid-column: 3;
            grid-row: 2;
            z-index: 2;
        }

        .p-icon { font-size: 30px; margin-bottom: 5px; }
        .p-name { font-family: 'Risque', serif; font-size: 18px; margin-bottom: 5px; text-shadow: 1px 1px 2px black; font-weight: bold; }
        .p-badge { 
            background: rgba(0,0,0,0.6); padding: 5px 15px; border-radius: 20px; 
            font-size: 14px; margin-top: 5px; border: 1px solid #D4AF37;
        }

        /* --- LIST SECTION (Rank 4+) --- */
        .list-wrapper {
            width: 100%;
            max-width: 650px;
            background: rgba(212, 175, 55, 0.78);
            border-radius: 12px;
            padding: 14px 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
            border: 1px solid rgba(255,255,255,0.35);
        }

        .list-row {
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(0, 0, 0, 0.20);
            padding: 10px 22px;
            border-radius: 5px;
            color: white; font-family: 'Risque', serif; font-size: 16px;
            border-left: none;
            transition: 0.3s;
        }
        .list-row:hover { background: rgba(139, 115, 20, 0.8); transform: translateX(5px); }

        .list-left { display: flex; align-items: center; gap: 14px; }
        .rank-num { font-weight: bold; width: 40px; font-family: 'MetalMania', cursive; font-size: 22px; }
        .user-avatar-small { width: 30px; height: 30px; fill: white; border: 1px solid white; border-radius: 50%; padding: 3px; }

        .score-val {
            font-weight: bold;
            color: white;
            text-shadow: 1px 1px 1px black;
            background: rgba(0, 0, 0, 0.35);
            border-radius: 30px;
            padding: 5px 14px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        /* --- CURRENT USER ROW (Fixed Bottom or Separated) --- */
        .current-user-separator {
            width: 100%; height: 2px; background: rgba(255,255,255,0.35); margin: 14px 0;
            max-width: 650px;
        }
        .current-user-row {
            background: rgba(212, 175, 55, 0.78);
            border: 1px solid rgba(255,255,255,0.35);
            color: white; font-weight: bold;
        }
        .current-user-row .user-avatar-small { fill: white; border-color: white; }

        /* --- PLAY AGAIN LINK --- */
        .play-again {
            margin-top: 14px; font-family: 'Risque', serif; font-size: 20px;
            color: white; text-decoration: underline; cursor: pointer;
            text-shadow: 1px 1px 2px black; transition: 0.3s;
        }
        .play-again:hover { color: #d4af37; transform: scale(1.05); }

        /* --- RANK NOTIFICATION --- */
        .rank-notification {
            position: fixed;
            top: 120px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(212, 175, 55, 0.95);
            border: 2px solid #D4AF37;
            border-radius: 15px;
            padding: 20px 30px;
            color: white;
            font-family: 'Risque', serif;
            font-size: 18px;
            text-align: center;
            z-index: 1000;
            box-shadow: 0 5px 25px rgba(0,0,0,0.7);
            animation: slideDown 0.5s ease-out;
        }
        .rank-notification h3 {
            font-family: 'MetalMania', cursive;
            font-size: 24px;
            margin-bottom: 10px;
            color: white;
            text-shadow: 2px 2px 4px black;
        }
        .rank-notification .rank-info {
            font-size: 20px;
            font-weight: bold;
            color: #FFF;
            margin: 5px 0;
        }
        .rank-notification .close-btn {
            margin-top: 10px;
            background: rgba(0,0,0,0.3);
            border: 1px solid white;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-family: 'MetalMania', cursive;
            transition: 0.3s;
        }
        .rank-notification .close-btn:hover {
            background: rgba(0,0,0,0.5);
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
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

    <div class="leaderboard-container">
        <?php if ($myRankData): ?>
            <div class="rank-notification" id="rankNotification">
                <h3>Your Quiz Result!</h3>
                <div class="rank-info">Rank: #<?php echo $myRankData['rank']; ?></div>
                <div class="rank-info">Score: <?php echo $myRankData['score']; ?> pts</div>
                <button class="close-btn" onclick="closeNotification()">Got it!</button>
            </div>
        <?php endif; ?>
            
            <h1 class="lb-title">LEADERBOARD</h1>

            <div class="podium-wrapper">
                <div class="podium-card rank-2">
                    <div class="p-icon">
                        <?php if (isset($top3[1]) && $top3[1]['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($top3[1]['profile_picture']); ?>" alt="<?php echo htmlspecialchars($top3[1]['username']); ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid white;">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="white"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="p-name"><?php echo isset($top3[1]) ? htmlspecialchars($top3[1]['username']) : '-'; ?></div>
                    <div class="p-badge">
                        #2 <?php echo isset($top3[1]) ? $top3[1]['score'] : 0; ?> pts
                    </div>
                </div>

                <div class="podium-card rank-1">
                    <div class="p-icon">
                        <?php if (isset($top3[0]) && $top3[0]['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($top3[0]['profile_picture']); ?>" alt="<?php echo htmlspecialchars($top3[0]['username']); ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid white;">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" width="50" height="50" fill="white"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="p-name"><?php echo isset($top3[0]) ? htmlspecialchars($top3[0]['username']) : '-'; ?></div>
                    <div class="p-badge">
                        #1 <?php echo isset($top3[0]) ? $top3[0]['score'] : 0; ?> pts
                    </div>
                </div>

                <div class="podium-card rank-3">
                    <div class="p-icon">
                        <?php if (isset($top3[2]) && $top3[2]['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($top3[2]['profile_picture']); ?>" alt="<?php echo htmlspecialchars($top3[2]['username']); ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid white;">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="white"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="p-name"><?php echo isset($top3[2]) ? htmlspecialchars($top3[2]['username']) : '-'; ?></div>
                    <div class="p-badge">
                        #3 <?php echo isset($top3[2]) ? $top3[2]['score'] : 0; ?> pts
                    </div>
                </div>
            </div>

            <div class="list-wrapper">
                <?php if (!empty($others)): ?>
                    <?php foreach($others as $player): ?>
                        <div class="list-row">
                            <div class="list-left">
                                <span class="rank-num">#<?php echo $player['rank']; ?></span>
                                <?php if ($player['profile_picture']): ?>
                                    <img src="<?php echo htmlspecialchars($player['profile_picture']); ?>" alt="<?php echo htmlspecialchars($player['username']); ?>" class="user-avatar-small" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid white;">
                                <?php else: ?>
                                    <svg class="user-avatar-small" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($player['username']); ?></span>
                            </div>
                            <span class="score-val"><?php echo $player['score']; ?> pts</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="list-row" style="opacity:0.5"><div class="list-left"><span class="rank-num">#4</span><span>...</span></div><span>000 pts</span></div>
                    <div class="list-row" style="opacity:0.5"><div class="list-left"><span class="rank-num">#5</span><span>...</span></div><span>000 pts</span></div>
                <?php endif; ?>
            </div>

            <?php if ($showMyRankSeparately && $myRankData): ?>
                <div class="current-user-separator"></div>
                <div class="list-wrapper">
                    <div class="list-row current-user-row">
                        <div class="list-left">
                            <span class="rank-num">#<?php echo $myRankData['rank']; ?></span>
                            <?php if ($myRankData['profile_picture']): ?>
                                <img src="<?php echo htmlspecialchars($myRankData['profile_picture']); ?>" alt="<?php echo htmlspecialchars($myRankData['username']); ?>" class="user-avatar-small" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid white;">
                            <?php else: ?>
                                <svg class="user-avatar-small" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($myRankData['username']); ?></span>
                        </div>
                        <span class="score-val"><?php echo $myRankData['score']; ?> pts</span>
                    </div>
                </div>
            <?php endif; ?>

            <a href="dashboard.php" class="play-again">Play Again</a>
        </div>

    </body>
    <script>
        function toggleDropdown() {
            var dropdown = document.getElementById("profileDropdown");
            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }
        }

        function closeNotification() {
            var notification = document.getElementById("rankNotification");
            if (notification) {
                notification.style.display = "none";
            }
        }

        // Auto-hide notification after 5 seconds
        setTimeout(function() {
            closeNotification();
        }, 5000);

        window.onclick = function(event) {
            if (!event.target.closest('.profile-container')) {
                var dd = document.getElementById("profileDropdown");
                if (dd) dd.style.display = "none";
            }
        }
    </script>
</html>