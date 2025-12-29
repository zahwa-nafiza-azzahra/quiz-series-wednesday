<?php
session_start();

// Cek Login & Role
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
                $avatarSrc = '../asset/' . $profilePic;
            } elseif (strpos($profilePic, '/') === false) {
                // Jika hanya filename, cek dulu di asset langsung, lalu di uploads
                if (file_exists(__DIR__ . '/../asset/' . $profilePic)) {
                    $avatarSrc = '../asset/' . $profilePic;
                } elseif (file_exists(__DIR__ . '/../asset/uploads/' . $profilePic)) {
                    $avatarSrc = '../asset/uploads/' . $profilePic;
                } else {
                    $avatarSrc = '../asset/profile_placeholder.jpg';
                }
            } else {
                $avatarSrc = $profilePic;
            }
            // Verify file exists
            $avatarPath = str_replace('../asset/', __DIR__ . '/../asset/', $avatarSrc);
            if (!file_exists($avatarPath)) {
                $avatarSrc = '../asset/profile_placeholder.jpg';
            }
        }
    }
    $stmtAvatar->close();
    $connAvatar->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ready? - Wednesday Quiz</title>
    <style>
        /* --- LOAD FONT LOKAL --- */
        @font-face {
            font-family: 'MetalMania';
            src: url('../asset/MetalMania-Regular.ttf') format('truetype');
        }
        @font-face {
            font-family: 'Risque';
            src: url('../asset/Risque-Regular.ttf') format('truetype');
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Risque', serif;
            height: 100vh;
            width: 100%;
            background: url('../asset/background.jpg') no-repeat center center/cover;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        /* Overlay gelap background */
        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 0;
        }

        /* --- NAVBAR (Tetap ada agar konsisten) --- */
        .navbar {
            position: fixed; /* Mengunci navbar di atas layar (ikut scroll) */
            top: 0; 
            left: 0; 
            width: 100%;
            padding: 15px 40px; /* Padding disesuaikan */
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            
            /* SOLUSI KLIK: Z-Index sangat tinggi agar selalu di paling depan */
            z-index: 9999; 
            
            /* SOLUSI TAMPILAN: Warna semi-transparan + Efek Blur */
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            
            /* Garis bawah tipis agar batasnya jelas (opsional) */
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

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-menu a {
            font-family: 'Risque', serif;
            color: white;
            text-decoration: none;
            font-size: 20px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: 0.3s;
        }
        
        .nav-menu a:hover { color: #d4af37; }

        /* Profil User */
        .profile-container {
            position: relative;
            cursor: pointer;
        }

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
            width: 35px; height: 35px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 2px solid black;
            background: #fff;
        }

        .dropdown-menu {
            display: none; /* Hidden by default */
            position: absolute;
            top: 50px; right: 0;
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
            display: flex; align-items: center; justify-content: center; gap: 15px;
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- READY CARD --- */
        .ready-card {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.16);
            padding: 52px 60px;
            border: 3px solid rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 0 55px rgba(0,0,0,0.75);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            width: 90%;
            max-width: 760px;
        }

        .ready-card h3 {
            font-family: 'MetalMania', cursive;
            color: white;
            font-size: 34px;
            margin-bottom: 8px;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-shadow: 3px 3px 8px rgba(0,0,0,0.9);
        }

        .ready-card h1 {
            font-family: 'Risque', serif;
            color: white;
            font-size: 120px;
            margin: 0 0 32px 0;
            letter-spacing: 6px;
            text-transform: uppercase;
            line-height: 0.95;
            text-shadow: 6px 6px 14px rgba(0,0,0,0.9);
        }

        /* Container Tombol YES / NO */
        .btn-container {
            display: flex;
            justify-content: center;
            gap: 70px;
        }

        .btn-option {
            background: rgba(212, 175, 55, 0.92);
            color: white;
            text-decoration: none;
            font-family: 'MetalMania', cursive;
            font-size: 26px;
            width: 150px;
            height: 42px;
            line-height: 42px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 12px rgba(0,0,0,0.55);
            transition: transform 0.2s, background 0.3s;
            display: inline-block;
            text-shadow: 1px 1px 2px black;
        }

        .btn-option:hover {
            background: rgba(196, 159, 39, 0.96);
            transform: scale(1.05);
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

    <div class="ready-card">
        <h3>ARE YOU</h3>
        <h1>READY?</h1>

        <div class="btn-container">
            <a href="quiz.php" class="btn-option">YES</a>
            
            <a href="dashboard.php" class="btn-option" onclick="stopMusicPreference()">NO</a>
        </div>
    </div>

    <audio id="bgMusic" loop>
        <source src="../asset/music.mp3" type="audio/mpeg">
    </audio>

    <script>
        // --- LOGIKA AUDIO OTOMATIS BERDASARKAN HALAMAN SEBELUMNYA ---
        window.addEventListener('load', function() {
            var music = document.getElementById("bgMusic");
            var status = localStorage.getItem('musicStatus'); // Ambil status dari dashboard

            // Jika status 'on', coba mainkan musik
            if (status === 'on') {
                var playPromise = music.play();

                if (playPromise !== undefined) {
                    playPromise.then(_ => {
                        // Musik berhasil diputar otomatis
                        console.log("Music continues playing...");
                    })
                    .catch(error => {
                        // Browser memblokir autoplay (biasanya jika user belum interaksi sama sekali)
                        console.log("Autoplay prevented by browser policy");
                    });
                }
            }
        });

        // Jika user klik NO dan kembali ke dashboard, kita biarkan statusnya tetap, 
        // tapi secara teknis dashboard akan baca ulang statusnya nanti.
        function stopMusicPreference() {
            // Opsional: jika ingin saat kembali ke dashboard musik mati, uncomment bawah ini:
            // localStorage.setItem('musicStatus', 'off');
        }

        // --- SCRIPT DROPDOWN BAWAAN ---
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