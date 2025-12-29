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

$totalQuizzes = 0;
$totalQuestions = 0;
$totalUsers = 0;

try {
    $conn = getDbConnection();

    $res = $conn->query("SELECT COUNT(*) AS c FROM quizzes");
    if ($res && ($row = $res->fetch_assoc())) {
        $totalQuizzes = (int) $row['c'];
    }

    $res = $conn->query("SELECT COUNT(*) AS c FROM questions");
    if ($res && ($row = $res->fetch_assoc())) {
        $totalQuestions = (int) $row['c'];
    }

    $res = $conn->query("SELECT COUNT(*) AS c FROM users");
    if ($res && ($row = $res->fetch_assoc())) {
        $totalUsers = (int) $row['c'];
    }

    $conn->close();
} catch (Throwable $e) {
    // keep zeros
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Wednesday Quiz</title>
    <style>
        @font-face { font-family: 'MetalMania'; src: url('../asset/MetalMania-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Risque'; src: url('../asset/Risque-Regular.ttf') format('truetype'); }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Risque', serif;
            min-height: 100vh; width: 100%;
            background: url('../asset/background.jpg') no-repeat center center/cover;
            display: flex; justify-content: center; align-items: center;
            position: relative; overflow: hidden;
        }

        body::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6); z-index: 0;
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

        /* --- PROFILE CONTAINER --- */
        .profile-container { position: relative; cursor: pointer; }

        .admin-profile {
            display: flex; align-items: center; background-color: #d4af37;
            padding: 5px 15px 5px 5px; border-radius: 30px; color: black; font-weight: bold;
        }
        .admin-profile img {
            width: 35px; height: 35px; border-radius: 50%; object-fit: cover;
            margin-right: 10px; border: 2px solid black; background: #fff;
        }

        /* --- DROPDOWN MENU (UKURAN DIPERKECIL) --- */
        .dropdown-menu {
            display: none; 
            position: absolute; top: 50px; right: 0;
            width: 200px; /* Lebar dikecilkan */
            border-radius: 15px; overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5); 
            border: 2px solid #333;
            animation: fadeIn 0.2s ease-in-out;
        }

        .dropdown-header {
            background-color: #d4af37; 
            padding: 15px; /* Padding dikurangi */
            text-align: center;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .dropdown-header img {
            width: 45px; height: 45px; /* Gambar header lebih kecil */
            border-radius: 50%; border: 2px solid white; object-fit: cover;
        }
        .dropdown-header span {
            font-family: 'Risque', serif; 
            font-size: 18px; /* Font nama lebih kecil */
            color: white; text-shadow: 1px 1px 2px black;
        }

        .dropdown-body {
            background-color: #5d5318; 
            padding: 10px 0;
        }
        
        .dropdown-item {
            display: flex; align-items: center; justify-content: center;
            padding: 8px 15px;
            color: white; text-decoration: none; 
            font-family: 'MetalMania', cursive;
            font-size: 18px; /* Font menu lebih kecil */
            transition: 0.3s; text-transform: uppercase;
        }
        .dropdown-item:hover { background-color: rgba(255,255,255,0.1); }
        
        .dropdown-item svg { 
            width: 20px; height: 20px; /* Icon lebih kecil */
            fill: white; margin-right: 8px; 
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- MAIN CARD --- */
        .main-card {
            position: relative; z-index: 2;
            background: rgba(255, 255, 255, 0.16);
            width: 92%;
            max-width: 760px;
            padding: 52px 60px;
            border: 3px solid rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            text-align: center;
            backdrop-filter: blur(2px);
            box-shadow: 0 0 55px rgba(0,0,0,0.75);
            margin-top: 60px;
        }

        .welcome-text { font-family: 'Risque', serif; color: white; font-size: 44px; margin-bottom: 8px; letter-spacing: 2px; text-shadow: 3px 3px 8px rgba(0,0,0,0.9); }
        .admin-title { font-family: 'Risque', serif; color: white; font-size: 120px; margin-bottom: 38px; letter-spacing: 6px; text-shadow: 6px 6px 14px rgba(0,0,0,0.9); text-transform: uppercase; line-height: 0.95; }
        .btn-container { display: flex; justify-content: center; gap: 30px; }
        .btn-gold {
            background: linear-gradient(180deg, rgba(201, 168, 71, 0.72) 0%, rgba(168, 134, 43, 0.72) 100%);
            color: white;
            border: none;
            padding: 14px 0;
            width: 200px;
            font-family: 'Risque', serif;
            font-size: 32px;
            border-radius: 10px;
            cursor: pointer; text-decoration: none; display: flex; justify-content: center; align-items: center;
            box-shadow: 0 6px 12px rgba(0,0,0,0.55);
            transition: transform 0.2s;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.85);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .btn-gold:hover { background: linear-gradient(180deg, rgba(215, 179, 78, 0.8) 0%, rgba(176, 142, 47, 0.8) 100%); transform: scale(1.05); }

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

    <div class="main-card">
        <div class="welcome-text">WELCOME</div>
        <div class="admin-title">ADMIN</div>

        <div class="btn-container">
            <a href="manage_soal.php" class="btn-gold">QUIZ</a>
            <a href="manage_users.php" class="btn-gold">USERS</a>
        </div>
    </div>

    <script>
        function toggleDropdown() {
            var dropdown = document.getElementById("adminDropdown");
            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }
        }
        window.onclick = function(event) {
            if (!event.target.closest('.profile-container')) {
                document.getElementById("adminDropdown").style.display = "none";
            }
        }
    </script>

</body>
</html>