<?php
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
    header("Location: ../login.php");
    exit();
}

// Load data profil dari database
require_once __DIR__ . '/../config/db.php';
$conn = getDbConnection();

$userId = $_SESSION['user_id'] ?? null;
$data = [
    'fullname' => 'Hamada Asahi',
    'username' => $_SESSION['username'] ?? 'Guest',
    'avatar' => '../asset/profile_placeholder.jpg',
    'birthday' => '',
    'location' => ''
];
$lastScore = 0;

if ($userId) {
    // Ambil data user dari database
    $stmt = $conn->prepare("SELECT username, full_name, profile_picture, total_score FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $data['username'] = $row['username'];
        $data['fullname'] = $row['full_name'] ?? $row['username'];
        
        // Handle profile picture path
        $profilePicture = $row['profile_picture'];
        if ($profilePicture && !empty($profilePicture)) {
            if (strpos($profilePicture, 'uploads/') === 0) {
                $data['avatar'] = '../asset/' . $profilePicture;
            } elseif (strpos($profilePicture, '../asset/') !== 0 && strpos($profilePicture, '/') === false) {
                // Jika hanya filename, cek dulu di asset langsung, lalu di uploads
                if (file_exists(__DIR__ . '/../asset/' . $profilePicture)) {
                    $data['avatar'] = '../asset/' . $profilePicture;
                } elseif (file_exists(__DIR__ . '/../asset/uploads/' . $profilePicture)) {
                    $data['avatar'] = '../asset/uploads/' . $profilePicture;
                } else {
                    $data['avatar'] = '../asset/profile_placeholder.jpg';
                }
            } else {
                $data['avatar'] = $profilePicture;
            }
            
            // Verify file exists, otherwise use placeholder
            $avatarPath = str_replace('../asset/', __DIR__ . '/../asset/', $data['avatar']);
            if (!file_exists($avatarPath)) {
                $data['avatar'] = '../asset/profile_placeholder.jpg';
            }
        }
        
        $lastScore = (int)($row['total_score'] ?? 0);
    }
    $stmt->close();
    
    // Update session dengan data terbaru dari database
    $_SESSION['profile'] = $data;
}

$conn->close();

$avatarSrc = $data['avatar'];
$username = $data['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Wednesday Quiz</title>
    <style>
        @font-face { font-family: 'MetalMania'; src: url('../asset/MetalMania-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Risque'; src: url('../asset/Risque-Regular.ttf') format('truetype'); }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Risque', serif;
            height: 100vh; width: 100%;
            background: url('../asset/background.jpg') no-repeat center center/cover;
            display: flex; justify-content: center; align-items: center;
            position: relative; overflow: hidden;
        }
        
        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6); z-index: 0; pointer-events: none;
        }

        .navbar {
            position: fixed; /* Mengunci navbar di atas layar (ikut scroll) */
            top: 0; 
            left: 0; 
            width: 100%;
            padding: 15px 40px; /* Padding disesuaikan */
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            z-index: 9999; 
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        /* Pastikan elemen lain (seperti Overlay Body) z-index nya di bawah navbar */
        body::before {
            z-index: 0; /* Background overlay paling belakang */
        }
        
        .main-card, .game-card, .leaderboard-card, .result-card {
            /* Konten utama di atas background tapi di bawah navbar */
            z-index: 10; 
            margin-top: 80px; /* Beri jarak agar tidak ketutup navbar fixed */
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

        .profile-container { position: relative; cursor: pointer; }

        .user-profile {
            display: flex; align-items: center;
            background-color: #d4af37;
            padding: 5px 15px 5px 5px;
            border-radius: 30px;
            color: black;
            font-weight: bold;
            font-family: 'Risque', serif;
        }
        .user-profile img {
            width: 35px; height: 35px; border-radius: 50%;
            object-fit: cover; margin-right: 10px;
            border: 2px solid black; background: #fff;
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
            font-size: 20px;
            color: white;
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .main-card {
            position: relative; z-index: 2;
            background: rgba(255, 255, 255, 0.08);
            width: 90%;
            max-width: 640px;
            padding: 28px 30px;
            border: 2px solid rgba(255, 255, 255, 0.85);
            border-radius: 15px;
            text-align: center;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            box-shadow: 0 0 40px rgba(0,0,0,0.8);
            margin-top: 0;
        }

        .welcome-title {
            font-family: 'MetalMania', cursive; color: white;
            font-size: 42px; margin-bottom: 18px; letter-spacing: 2px;
            text-shadow: 2px 2px 4px black;
        }

        .gold-card {
            background: rgba(212, 175, 55, 0.78);
            border-radius: 12px;
            padding: 18px;
            position: relative;
            margin: 10px auto 0;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            max-width: 380px;
        }

        .avatar-large {
            width: 72px; height: 72px; border-radius: 50%; border: 3px solid white;
            object-fit: cover; position: absolute; top: -36px; left: 50%;
            transform: translateX(-50%); background: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }

        .full-name {
            font-family: 'Risque', serif;
            font-size: 18px;
            margin-top: 42px;
            margin-bottom: 12px;
            text-shadow: 1px 1px 2px black;
            text-align: center;
        }

        .info-pill {
            background: rgba(0, 0, 0, 0.38);
            border-radius: 30px;
            padding: 7px 22px;
            margin-bottom: 8px;
            width: fit-content;
            min-width: 220px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Risque', serif;
            font-size: 16px;
            position: relative;
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
        }

        .edit-icon {
            position: absolute;
            right: 12px;
            width: 18px;
            height: 18px;
            fill: white;
            cursor: pointer;
        }

        .score-bar {
            background: rgba(212, 175, 55, 0.78);
            border-radius: 12px;
            padding: 10px 18px;
            margin: 14px auto 0;
            width: 100%;
            max-width: 380px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Risque', serif;
            font-size: 16px;
            color: white;
        }

        .score-val {
            background: rgba(0, 0, 0, 0.38);
            border-radius: 30px;
            padding: 5px 14px;
            font-family: 'Risque', serif;
            color: white;
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

    <div class="main-card">
        <h1 class="welcome-title">WELCOME</h1>

        <div class="gold-card">
            <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Profile" class="avatar-large">

            <div class="full-name"><?php echo htmlspecialchars($data['fullname']); ?></div>

            <div class="info-pill">
                <?php echo htmlspecialchars($data['username']); ?>
            </div>

            <div class="info-pill">
                <?php echo !empty($data['birthday']) ? htmlspecialchars($data['birthday']) : 'Birthday'; ?>
            </div>

            <div class="info-pill">
                <?php echo !empty($data['location']) ? htmlspecialchars($data['location']) : 'Location'; ?>
                <a href="edit_profile.php" title="Edit Profile">
                    <svg class="edit-icon" viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                </a>
            </div>
        </div>

        <div class="score-bar">
            <span>Your Last Score</span>
            <span class="score-val"><?php echo $lastScore; ?> pts</span>
        </div>
    </div>

    <script>
        function toggleDropdown() {
            var dropdown = document.getElementById("profileDropdown");
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
    </script>

</body>
</html>