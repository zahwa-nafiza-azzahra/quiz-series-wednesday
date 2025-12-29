<?php
ob_start(); // <--- TAMBAHAN PENTING: Mencegah error redirect
session_start();

// Cek Login
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
    header("Location: ../login.php");
    exit();
}

// Load data profil dari database
require_once __DIR__ . '/../config/db.php';
$conn = getDbConnection();

$userId = $_SESSION['user_id'] ?? null;
$data = [
    'fullname' => '',
    'username' => $_SESSION['username'] ?? 'Guest',
    'avatar' => '../asset/profile_placeholder.jpg',
    'birthday' => '',
    'location' => ''
];

if ($userId) {
    // Ambil data user dari database
    $stmt = $conn->prepare("SELECT username, full_name, profile_picture FROM users WHERE id = ?");
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
    }
    $stmt->close();
    
    // Update session dengan data terbaru dari database
    $_SESSION['profile'] = $data;
}

$conn->close();

$avatarSrc = $data['avatar'];
$username = $data['username'];

$uploadError = null;

// --- LOGIKA SIMPAN (Save Changes) ---
if (isset($_POST['save_changes'])) {
    if (isset($_FILES['avatar']) && isset($_FILES['avatar']['error']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $uploadError = 'Upload gagal (error code: ' . (int)$_FILES['avatar']['error'] . ').';
        } else {
            $tmpName = $_FILES['avatar']['tmp_name'];
            $origName = $_FILES['avatar']['name'];
            $fileSize = (int) $_FILES['avatar']['size'];

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'jfif', 'png', 'webp'];
            $maxSize = 2 * 1024 * 1024;

            $imgInfo = @getimagesize($tmpName);
            if ($imgInfo === false) {
                $uploadError = 'File yang diupload bukan gambar yang valid.';
            } elseif (!in_array($ext, $allowedExt, true)) {
                $uploadError = 'Format gambar tidak didukung. Gunakan: JPG, JPEG, JFIF, PNG, WEBP.';
            } elseif ($fileSize <= 0 || $fileSize > $maxSize) {
                $uploadError = 'Ukuran gambar maksimal 2MB.';
            } else {
                $uploadDir = __DIR__ . '/../asset/uploads';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0775, true)) {
                        $uploadError = 'Folder upload tidak bisa dibuat: ' . $uploadDir;
                    }
                }

                if ($uploadError === null && !is_writable($uploadDir)) {
                    $uploadError = 'Folder upload tidak bisa ditulis: ' . $uploadDir;
                }

                if ($uploadError === null) {
                    $safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($_POST['username'] ?? ($_SESSION['username'] ?? 'user')));
                    $fileName = 'avatar_' . $safeUser . '_' . time() . '.' . $ext;
                    $destPath = $uploadDir . '/' . $fileName;

                    if (move_uploaded_file($tmpName, $destPath)) {
                        $avatarPath = '../asset/uploads/' . $fileName;
                        $_SESSION['profile']['avatar'] = $avatarPath;
                        
                        // Simpan path avatar ke database
                        require_once __DIR__ . '/../config/db.php';
                        $conn = getDbConnection();
                        $dbAvatarPath = 'uploads/' . $fileName; // Simpan relatif tanpa '../asset/'
                        $updateAvatarStmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $updateAvatarStmt->bind_param('si', $dbAvatarPath, $_SESSION['user_id']);
                        $updateAvatarStmt->execute();
                        $updateAvatarStmt->close();
                        $conn->close();
                    } else {
                        $uploadError = 'Gagal menyimpan file upload.';
                    }
                }
            }
        }
    }

    // 1. Update data Session dengan inputan baru
    $_SESSION['profile']['fullname'] = $_POST['fullname'];
    $_SESSION['profile']['username'] = $_POST['username'];
    $_SESSION['profile']['birthday'] = $_POST['birthday'];
    $_SESSION['profile']['location'] = $_POST['location'];

    // 2. Update variabel username utama session login juga
    $_SESSION['username'] = $_POST['username'];

    // 3. Update database dengan data profil baru
    require_once __DIR__ . '/../config/db.php';
    $conn = getDbConnection();
    
    // Update full_name di database (jika kolom ada)
    $updateProfileStmt = $conn->prepare("UPDATE users SET full_name = ?, username = ? WHERE id = ?");
    $updateProfileStmt->bind_param('ssi', $_POST['fullname'], $_POST['username'], $_SESSION['user_id']);
    $updateProfileStmt->execute();
    $updateProfileStmt->close();
    $conn->close();

    // 4. Redirect (Pindah) ke halaman profile
    if ($uploadError === null) {
        header("Location: profil.php");
        exit();
    }
}

$data = $_SESSION['profile'];
$avatarSrc = isset($data['avatar']) && $data['avatar'] ? $data['avatar'] : '../asset/profile_placeholder.jpg';
$username = $data['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Wednesday Quiz</title>
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
            padding-top: 120px;
        }

        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6); z-index: 0; pointer-events: none;
        }

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

        .profile-container { position: relative; cursor: pointer; }

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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid white;
            object-fit: cover;
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
            display: flex;
            align-items: center;
            padding: 10px 25px;
            color: white;
            text-decoration: none;
            font-family: 'MetalMania', cursive;
            font-size: 20px;
            transition: 0.3s;
            text-transform: uppercase;
        }

        .dropdown-item:hover {
            background-color: rgba(255,255,255,0.1);
            padding-left: 35px;
        }

        .dropdown-item svg {
            width: 24px;
            height: 24px;
            fill: white;
            margin-right: 15px;
        }

        .main-card {
            position: relative; z-index: 2;
            background: rgba(255, 255, 255, 0.08);
            width: 90%;
            max-width: 650px;
            padding: 30px 32px;
            border: 2px solid rgba(255, 255, 255, 0.85);
            border-radius: 15px;
            text-align: center;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            box-shadow: 0 0 40px rgba(0,0,0,0.8);
            margin-top: 0;
        }

        .title {
            font-family: 'MetalMania', cursive; color: white;
            font-size: 42px; margin-bottom: 20px; letter-spacing: 2px;
            text-shadow: 2px 2px 4px black; text-transform: uppercase;
        }

        .gold-card {
            background: rgba(212, 175, 55, 0.78);
            border-radius: 12px;
            padding: 48px 22px 18px;
            position: relative;
            margin: 10px auto 0;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            max-width: 380px;
            width: 100%;
        }

        .avatar-large {
            width: 72px; height: 72px; border-radius: 50%;
            border: 3px solid white;
            object-fit: cover; position: absolute; top: -36px; left: 50%;
            transform: translateX(-50%); background: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }

        .avatar-controls {
            position: absolute;
            top: -14px;
            left: calc(50% + 14px);
            width: 28px;
            height: 28px;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 5;
        }

        .avatar-input {
            display: none;
        }

        .avatar-upload-btn {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(0,0,0,0.45);
            border: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.95);
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.4);
        }

        .avatar-upload-btn:hover {
            background: rgba(0,0,0,0.6);
        }

        .avatar-upload-btn svg {
            width: 16px;
            height: 16px;
            fill: white;
        }

        .form-group {
            margin-bottom: 10px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .form-label {
            display: block;
            font-family: 'Risque', serif;
            font-size: 14px;
            color: rgba(255,255,255,0.95);
            margin-bottom: 5px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.6);
        }

        .form-input {
            width: 100%;
            max-width: none;
            background: rgba(0, 0, 0, 0.38);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 30px;
            padding: 8px 20px;
            color: rgba(255,255,255,0.95);
            font-family: 'Risque', serif;
            font-size: 14px;
            outline: none;
            text-align: left;
            transition: all 0.25s;
        }

        .form-input::placeholder { 
            color: rgba(255, 255, 255, 0.6); 
        }

        input[type="date"]::-webkit-calendar-picker-indicator { 
            filter: invert(1); 
            cursor: pointer; 
            opacity: 0.8;
        }

        
        input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }

        .btn-save {
            display: inline-block;
            margin-top: 14px;
            background: transparent;
            border: none;
            padding: 0;
            color: rgba(255,255,255,0.95);
            font-family: 'Risque', serif;
            font-size: 18px;
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 4px;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.85);
            transition: color 0.2s, transform 0.2s;
        }
        
        .btn-save:hover { 
            color: #d4af37;
            transform: translateY(-1px);
        }

        .error-msg {
            width: 100%;
            max-width: 380px;
            margin: 0 auto 12px;
            padding: 10px 14px;
            border-radius: 10px;
            background: rgba(180, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: rgba(255,255,255,0.95);
            font-family: 'Risque', serif;
            font-size: 14px;
            text-align: left;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.6);
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
                    <img id="avatarSmall" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="User"> 
                    <span><?php echo htmlspecialchars($username); ?></span>
                </div>

                <div class="dropdown-menu" id="profileDropdown">
                    <div class="dropdown-header">
                        <img id="avatarDropdown" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="User">
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
        <h1 class="title">EDIT PROFILE</h1>

        <?php if (!empty($uploadError)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($uploadError); ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="gold-card">
                <img id="avatarLarge" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Profile" class="avatar-large">

                <div class="avatar-controls">
                    <input type="file" name="avatar" id="avatar" class="avatar-input" accept="image/png, image/jpeg, image/jfif, image/webp, .jfif">
                    <label for="avatar" class="avatar-upload-btn" title="Change Photo" aria-label="Change Photo">
                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" for="fullname">Name</label>
                    <input type="text" name="fullname" class="form-input" 
                           id="fullname"
                           value="<?php echo htmlspecialchars($data['fullname']); ?>" 
                           placeholder="Full Name">
                </div>

                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" name="username" class="form-input" 
                           id="username"
                           value="<?php echo htmlspecialchars($data['username']); ?>" 
                           placeholder="Username">
                </div>

                <div class="form-group">
                    <label class="form-label" for="birthday">Birthday</label>
                    <input type="date" name="birthday" class="form-input" 
                           id="birthday"
                           value="<?php echo htmlspecialchars($data['birthday']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="location">Location</label>
                    <input type="text" name="location" class="form-input" 
                           id="location"
                           value="<?php echo htmlspecialchars($data['location']); ?>" 
                           placeholder="Location">
                </div>
            </div>

            <button type="submit" name="save_changes" class="btn-save">Save Changes</button>
        </form>
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
                document.getElementById("profileDropdown").style.display = "none";
            }
        }

        (function() {
            var input = document.getElementById('avatar');
            if (!input) return;
            input.addEventListener('change', function() {
                if (!input.files || !input.files[0]) return;
                var file = input.files[0];
                if (!file.type || file.type.indexOf('image/') !== 0) return;
                var url = URL.createObjectURL(file);
                var large = document.getElementById('avatarLarge');
                var small = document.getElementById('avatarSmall');
                var drop = document.getElementById('avatarDropdown');
                if (large) large.src = url;
                if (small) small.src = url;
                if (drop) drop.src = url;
            });
        })();
    </script>

</body>
</html>