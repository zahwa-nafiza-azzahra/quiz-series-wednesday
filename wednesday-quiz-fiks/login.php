<?php
session_start();

require_once __DIR__ . '/config/db.php';

$error = "";
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Username dan Password wajib diisi!";
    } else {
        if ($username === 'admin') {
            $isValidAdmin = false;
            $conn = getDbConnection();

            $stmt = $conn->prepare('SELECT password FROM users WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $stored = (string) ($row['password'] ?? '');
                if ($stored !== '') {
                    if (password_verify($password, $stored) || hash_equals($stored, $password)) {
                        $isValidAdmin = true;
                    }
                }
            } else {
                if ($password === 'admin123') {
                    $isValidAdmin = true;
                }
            }

            $stmt->close();
            $conn->close();

            if ($isValidAdmin) {
                $_SESSION['user_role'] = 'admin';
                $_SESSION['username'] = $username;
                header("Location: admin/dashboard.php");
                exit();
            }

            $error = "Username atau Password salah!";
        } else {
            // Login untuk user biasa - cek database
            $conn = getDbConnection();
            
            $stmt = $conn->prepare('SELECT id, username, password, total_score, full_name, profile_picture FROM users WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($res && ($row = $res->fetch_assoc())) {
                $stored = (string) ($row['password'] ?? '');
                if (password_verify($password, $stored) || hash_equals($stored, $password)) {
                    // Login berhasil - simpan session
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['total_score'] = $row['total_score'];
                    
                    // Load profile data dari database ke session
                    $profilePicture = $row['profile_picture'];
                    if ($profilePicture && !empty($profilePicture)) {
                        // Jika path sudah lengkap dengan 'uploads/', tambahkan '../asset/'
                        if (strpos($profilePicture, 'uploads/') === 0) {
                            $profilePicture = '../asset/' . $profilePicture;
                        } elseif (strpos($profilePicture, '../asset/') !== 0 && strpos($profilePicture, '/') === false) {
                            // Jika hanya filename, cek dulu di asset langsung, lalu di uploads
                            if (file_exists(__DIR__ . '/asset/' . $profilePicture)) {
                                $profilePicture = '../asset/' . $profilePicture;
                            } elseif (file_exists(__DIR__ . '/asset/uploads/' . $profilePicture)) {
                                $profilePicture = '../asset/uploads/' . $profilePicture;
                            } else {
                                $profilePicture = '../asset/profile_placeholder.jpg';
                            }
                        }
                    } else {
                        $profilePicture = '../asset/profile_placeholder.jpg';
                    }
                    
                    $_SESSION['profile'] = [
                        'fullname' => $row['full_name'] ?? $row['username'],
                        'username' => $row['username'],
                        'avatar' => $profilePicture,
                        'birthday' => '', // Bisa ditambahkan jika ada kolom di database
                        'location' => ''   // Bisa ditambahkan jika ada kolom di database
                    ];
                    
                    header("Location: user/dashboard.php");
                    exit();
                }
            }
            
            $stmt->close();
            $conn->close();
            
            $error = "Username atau Password salah!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Wednesday Theme</title>
    <style>
        /* --- LOAD FONT LOKAL --- */
        @font-face {
            font-family: 'MetalMania';
            src: url('asset/MetalMania-Regular.ttf') format('truetype');
        }
        @font-face {
            font-family: 'Risque';
            src: url('asset/Risque-Regular.ttf') format('truetype');
        }

        /* RESET & CSS */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            /* Font Utama untuk body menggunakan Risque agar terbaca */
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
            /* Judul menggunakan MetalMania */
            font-family: 'MetalMania', cursive; 
            color: white;
            font-size: 32px;
            margin-bottom: 30px;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-shadow: 2px 2px 5px black;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            background: transparent;
            border: 1px solid white;
            border-radius: 5px;
            color: white;
            font-size: 18px;
            outline: none;
            /* Input text pakai Risque */
            font-family: 'Risque', serif; 
        }

        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        /* SVG ICONS */
        .icon-svg {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px; height: 20px;
            fill: white;
            pointer-events: none;
        }

        .btn-login {
            width: 100%;
            padding: 10px;
            background-color: white;
            color: black;
            border: none;
            border-radius: 5px;
            font-family: 'MetalMania', cursive; /* Tombol pakai MetalMania biar keren */
            font-size: 22px;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
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
        <h2>Login to Your Account</h2>
        
        <?php if(!empty($error)) echo "<p class='error-msg'>$error</p>"; ?>

        <form action="" method="POST">
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required autocomplete="off">
                <svg class="icon-svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
                <svg class="icon-svg" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
            </div>

            <button type="submit" name="login" class="btn-login">Login</button>
        </form>

        <div class="footer-text">
            Don't have an account? <a href="register.php">Create Your Account</a>
        </div>
    </div>

</body>
</html>