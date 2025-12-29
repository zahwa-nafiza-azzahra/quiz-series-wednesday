<?php
session_start();

require_once __DIR__ . '/config/db.php';

if (isset($_POST['register'])) {
    $nickname = trim($_POST['nickname']);
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($nickname) || empty($username) || empty($email) || empty($password)) {
        $error = "Semua kolom wajib diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } else {
        // Cek apakah username atau email sudah ada
        $conn = getDbConnection();
        
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param('ss', $username, $email);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username atau email sudah digunakan!";
        } else {
            // Hash password dan simpan ke database
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $created_at = date('Y-m-d H:i:s');
            $default_avatar = 'profildefault.jpg'; // Foto profil default untuk user baru
            
            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, full_name, password, profile_picture, created_at, total_score) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $insert_stmt->bind_param('ssssss', $username, $email, $nickname, $hashed_password, $default_avatar, $created_at);
            
            if ($insert_stmt->execute()) {
                $_SESSION['message'] = "Registrasi berhasil! Silakan login.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Terjadi kesalahan. Silakan coba lagi.";
            }
            
            $insert_stmt->close();
        }
        
        $check_stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Wednesday Theme</title>
    <style>
        /* --- COPY STYLE LOGIN AGAR SAMA --- */
        @font-face {
            font-family: 'MetalMania';
            src: url('asset/MetalMania-Regular.ttf') format('truetype');
        }
        @font-face {
            font-family: 'Risque';
            src: url('asset/Risque-Regular.ttf') format('truetype');
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Risque', serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('asset/background.jpg') no-repeat center center/cover;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 0;
        }

        .login-card {
            position: relative;
            z-index: 1;
            background: rgba(0, 0, 0, 0.7);
            padding: 40px;
            width: 400px;
            border: 2px solid white;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 0 20px rgba(0,0,0,0.9);
            backdrop-filter: blur(4px);
        }

        .login-card h2 {
            font-family: 'MetalMania', cursive;
            color: white;
            font-size: 30px;
            margin-bottom: 25px;
            text-transform: capitalize;
            text-shadow: 2px 2px 5px black;
        }

        .input-group { position: relative; margin-bottom: 15px; }

        .input-group input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            background: transparent;
            border: 1px solid white;
            border-radius: 5px;
            color: white;
            font-size: 16px;
            outline: none;
            font-family: 'Risque', serif;
        }

        .input-group input::placeholder { color: rgba(255, 255, 255, 0.7); }

        .icon-svg {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px; fill: white; pointer-events: none;
        }

        .btn-login {
            width: 100%; padding: 10px;
            background-color: white; color: black;
            border: none; border-radius: 5px;
            font-family: 'MetalMania', cursive;
            font-size: 20px;
            cursor: pointer; margin-top: 15px;
            transition: 0.3s; text-transform: uppercase;
        }
        .btn-login:hover { background-color: #ddd; transform: scale(1.02); }

        .footer-text { margin-top: 20px; font-size: 14px; color: white; }
        .footer-text a { color: #f1c40f; text-decoration: none; font-weight: bold; }
        .footer-text a:hover { text-decoration: underline; }

        .error-msg {
            color: #ff6b6b; font-size: 14px; margin-bottom: 15px;
            background: rgba(0,0,0,0.5); padding: 5px; border-radius: 5px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h2>Create Account</h2>
        
        <?php if(isset($error)) echo "<p class='error-msg'>$error</p>"; ?>

        <form action="" method="POST">
            <div class="input-group">
                <input type="text" name="nickname" placeholder="Your Nickname" required>
                <svg class="icon-svg" viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
            </div>
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required>
                <svg class="icon-svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <div class="input-group">
                <input type="email" name="email" placeholder="Email" required>
                <svg class="icon-svg" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
                <svg class="icon-svg" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
            </div>
            <button type="submit" name="register" class="btn-login">Sign-Up</button>
        </form>

        <div class="footer-text">
            Have an account? <a href="login.php">Login Here</a>
        </div>
    </div>

</body>
</html>