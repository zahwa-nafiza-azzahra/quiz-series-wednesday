<?php
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'admin';

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
$success = '';

if (isset($_POST['save_new_password'])) {
    $newPassword = isset($_POST['new_password']) ? trim((string) $_POST['new_password']) : '';
    $confirmPassword = isset($_POST['confirm_password']) ? trim((string) $_POST['confirm_password']) : '';

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } else {
        $conn = getDbConnection();
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        // Create admin row if missing; otherwise update password only.
        $email = 'admin@example.com';
        $fullName = 'Admin';
        $totalScore = 0;
        $quizCompleted = 0;
        $stmt = $conn->prepare(
            'INSERT INTO users (username, email, password, full_name, total_score, quiz_completed) '
            . 'VALUES (?, ?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE password = VALUES(password)'
        );
        $stmt->bind_param('ssssii', $username, $email, $hashed, $fullName, $totalScore, $quizCompleted);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        $success = 'Password updated.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin - Wednesday Quiz</title>
    <style>
        @font-face { font-family: 'MetalMania'; src: url('../asset/MetalMania-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Risque'; src: url('../asset/Risque-Regular.ttf') format('truetype'); }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Risque', serif;
            min-height: 100vh;
            background: url('../asset/background.jpg') no-repeat center center/cover;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.55);
            z-index: 0;
        }

        .navbar {
            position: fixed;
            top: 0; left: 0;
            width: 100%;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
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
        .nav-menu a { font-family: 'Risque', serif; color: white; text-decoration: none; font-size: 22px; letter-spacing: 1px; transition: 0.3s; }
        .nav-menu a:hover { color: #d4af37; }

        .profile-container { position: relative; cursor: pointer; }

        .admin-profile {
            display: flex; align-items: center; background-color: #d4af37;
            padding: 5px 15px 5px 5px; border-radius: 30px; color: black; font-weight: bold;
        }

        .admin-profile img {
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
            width: 200px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            border: 2px solid #333;
            animation: fadeIn 0.2s ease-in-out;
        }

        .dropdown-header {
            background-color: #d4af37;
            padding: 15px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .dropdown-header img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid white;
            object-fit: cover;
        }

        .dropdown-header span {
            font-family: 'Risque', serif;
            font-size: 18px;
            color: white;
            text-shadow: 1px 1px 2px black;
        }

        .dropdown-body {
            background-color: #5d5318;
            padding: 10px 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 15px;
            color: white;
            text-decoration: none;
            font-family: 'MetalMania', cursive;
            font-size: 18px;
            transition: 0.3s;
            text-transform: uppercase;
            gap: 8px;
        }

        .dropdown-item svg { width: 20px; height: 20px; fill: white; }
        .dropdown-item:hover { background-color: rgba(255,255,255,0.1); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .page-wrap {
            position: relative;
            z-index: 2;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 80px;
        }

        .outer-frame {
            width: 780px;
            max-width: calc(100% - 100px);
            border: 2px solid rgba(255,255,255,0.9);
            border-radius: 10px;
            padding: 40px 30px;
            background: rgba(0,0,0,0.20);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            box-shadow: 0 0 30px rgba(0,0,0,0.6);
        }

        .title {
            text-align: center;
            font-family: 'MetalMania', cursive;
            color: white;
            font-size: 46px;
            letter-spacing: 2px;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.8);
            line-height: 1.05;
            margin-bottom: 22px;
            text-transform: uppercase;
        }

        .gold-box {
            width: 420px;
            max-width: 100%;
            margin: 0 auto;
            background: rgba(212, 175, 55, 0.75);
            border-radius: 4px;
            padding: 18px 18px 20px 18px;
        }

        .field {
            margin-bottom: 14px;
        }

        .label {
            display: block;
            font-family: 'Risque', serif;
            color: white;
            font-size: 16px;
            margin-bottom: 8px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
        }

        .input {
            width: 100%;
            height: 34px;
            background: rgba(0,0,0,0.35);
            border: none;
            border-radius: 18px;
            padding: 0 14px;
            color: white;
            font-family: 'Risque', serif;
            font-size: 14px;
            outline: none;
        }

        .save-wrap {
            text-align: center;
            margin-top: 26px;
        }

        .save-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            color: white;
            font-family: 'Risque', serif;
            font-size: 25px;
            text-decoration: underline;
            letter-spacing: 1px;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.9);
        }

        .msg {
            width: 420px;
            max-width: 100%;
            margin: 0 auto 14px auto;
            font-family: 'Risque', serif;
            font-size: 14px;
            text-align: center;
            color: white;
        }

        .msg.error {
            color: #ffd0d0;
        }

        .msg.success {
            color: #d6ffda;
        }

        @media (max-width: 520px) {
            .outer-frame { padding: 28px 18px; }
            .title { font-size: 36px; }
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

    <div class="page-wrap">
        <div class="outer-frame">
            <div class="title">EDIT ADMIN<br>PASSWORD</div>

            <?php if ($error !== ''): ?>
                <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="gold-box">
                    <div class="field">
                        <label class="label" for="new_password">New Password</label>
                        <input class="input" id="new_password" name="new_password" type="password" required>
                    </div>

                    <div class="field" style="margin-bottom:0;">
                        <label class="label" for="confirm_password">Confirmation Password</label>
                        <input class="input" id="confirm_password" name="confirm_password" type="password" required>
                    </div>
                </div>

                <div class="save-wrap">
                    <button class="save-btn" type="submit" name="save_new_password">Save New Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleDropdown() {
            var dropdown = document.getElementById("adminDropdown");
            dropdown.style.display = (dropdown.style.display === "block") ? "none" : "block";
        }

        window.onclick = function(event) {
            if (!event.target.closest('.profile-container')) {
                var dd = document.getElementById("adminDropdown");
                if (dd) dd.style.display = "none";
            }
        }
    </script>

</body>
</html>
