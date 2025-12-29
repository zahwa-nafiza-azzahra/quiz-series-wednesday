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

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 25;
if (!in_array($limit, [25, 50, 100], true)) {
    $limit = 25;
}

$conn = getDbConnection();

$users = [];

$stmt = $conn->prepare(
    "SELECT id, username, email, full_name, total_score, created_at\n"
    . "FROM users\n"
    . "ORDER BY total_score DESC, username ASC\n"
    . "LIMIT ?"
);
$stmt->bind_param('i', $limit);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <style>
        /* --- FONTS --- */
        @font-face { font-family: 'MetalMania'; src: url('../asset/MetalMania-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Risque'; src: url('../asset/Risque-Regular.ttf') format('truetype'); }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Risque', serif;
            min-height: 100vh; width: 100%;
            background: url('../asset/background.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex; justify-content: center; align-items: flex-start;
            position: relative; overflow-y: auto;
            padding-top: 100px; padding-bottom: 50px;
        }

        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7); z-index: 0;
        }

        /* --- NAVBAR FIXED (Sama seperti dashboard) --- */
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

        /* Admin Profile Dropdown */
        .profile-container { position: relative; cursor: pointer; }
        .admin-profile {
            display: flex; align-items: center; background-color: #d4af37;
            padding: 5px 15px 5px 5px; border-radius: 30px; color: black; font-weight: bold;
        }
        .admin-profile img {
            width: 35px; height: 35px; border-radius: 50%; object-fit: cover;
            margin-right: 10px; border: 2px solid black; background: #fff;
        }
        .dropdown-menu {
            display: none; position: absolute; top: 50px; right: 0; width: 200px;
            border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            border: 2px solid #333; animation: fadeIn 0.2s ease-in-out;
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


        /* --- CONTENT USERS RANK --- */
        .container {
            position: relative; z-index: 10; width: 95%; max-width: 1100px;
            /* Container Transparan Border Putih */
            border: 2px solid white; border-radius: 15px;
            padding: 40px; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);
        }

        /* HEADER: TITLE & FILTER */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }
        .page-title {
            font-family: 'MetalMania', cursive; color: white; font-size: 42px;
            text-shadow: 2px 2px 4px black; letter-spacing: 2px;
        }

        /* FILTER BUTTON CONTAINER */
        .filter-container {
            position: relative;
        }
        .btn-filter {
            background-color: #d4af37; color: white; text-decoration: none;
            font-family: 'Risque', serif; padding: 10px 30px; border-radius: 5px;
            font-size: 18px; border: none; cursor: pointer; transition: 0.3s;
        }
        .btn-filter:hover { background-color: #c49f27; }

        /* Filter Dropdown (Custom Style sesuai gambar) */
        .filter-dropdown {
            display: none; /* Hidden by default */
            position: absolute; top: 50px; right: 0;
            background-color: #5d5318; /* Coklat Zaitun */
            border: 2px solid #d4af37; border-radius: 10px;
            width: 180px; overflow: hidden; z-index: 100;
        }
        .filter-header {
            background-color: #d4af37; color: white; padding: 10px;
            text-align: center; font-family: 'Risque', serif; font-size: 20px;
            font-weight: bold;
        }
        .filter-list button {
            display: block; width: 100%; background: transparent; border: none;
            color: white; padding: 10px; text-align: center;
            font-family: 'MetalMania', cursive; font-size: 18px;
            cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.1);
            text-decoration: none;
        }
        .filter-list button.active {
            text-decoration: underline;
        }
        .filter-list button:last-child { border-bottom: none; }
        .filter-list button:hover { background-color: rgba(255,255,255,0.1); }


        /* --- TABLE STYLE (GOLDEN OVERLAY) --- */
        .table-overlay {
            /* Latar belakang emas transparan seperti di gambar */
            background: linear-gradient(180deg, rgba(139, 115, 20, 0.4) 0%, rgba(184, 134, 11, 0.8) 100%);
            border-radius: 10px;
            overflow: hidden; /* Agar sudut tumpul */
        }

        table { width: 100%; border-collapse: collapse; color: white; }
        
        thead {
            /* Header tabel sedikit lebih gelap */
            background: rgba(0, 0, 0, 0.3);
        }
        
        th {
            padding: 20px 10px; text-align: left;
            font-family: 'Risque', serif; font-size: 18px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        td {
            padding: 15px 10px;
            font-family: 'Risque', serif; font-size: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Baris terakhir tanpa border bawah */
        tbody tr:last-child td { border-bottom: none; }

        /* BACK LINK */
        .back-link {
            display: block; text-align: center; margin-top: 30px;
            font-family: 'MetalMania', cursive; font-size: 32px;
            color: white; text-decoration: none; cursor: pointer;
            text-shadow: 2px 2px 4px black; letter-spacing: 2px;
            text-transform: uppercase;
        }
        .back-link:hover { color: #d4af37; }

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
            <h1 class="page-title">USERS RANK</h1>
            
            <div class="filter-container">
                <button class="btn-filter" onclick="toggleFilter()"><?php echo $limit; ?> Users</button>
                <div class="filter-dropdown" id="filterDropdown">
                    <div class="filter-header">All Users</div>
                    <div class="filter-list">
                        <button type="button" onclick="setLimit(25)" class="<?php echo $limit == 25 ? 'active' : ''; ?>">25 Users</button>
                        <button type="button" onclick="setLimit(50)" class="<?php echo $limit == 50 ? 'active' : ''; ?>">50 Users</button>
                        <button type="button" onclick="setLimit(100)" class="<?php echo $limit == 100 ? 'active' : ''; ?>">100 Users</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-overlay">
            <table>
                <thead>
                    <tr>
                        <th width="5%" style="text-align: center;">NO</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Joined</th>
                        <th style="text-align: right;">Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $idx => $u): ?>
                    <tr>
                        <td style="text-align: center;"><?php echo (int) $idx + 1; ?></td>
                        <td><?php echo htmlspecialchars($u['full_name'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($u['created_at']))); ?></td>
                        <td style="text-align: right;"><?php echo str_pad((string) ((int) $u['total_score']), 3, '0', STR_PAD_LEFT); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="dashboard.php" class="back-link">BACK</a>

    </div>

    <script>
        // Toggle Admin Profile Dropdown
        function toggleDropdown() {
            var dropdown = document.getElementById("adminDropdown");
            dropdown.style.display = (dropdown.style.display === "block") ? "none" : "block";
        }

        // Toggle Filter Dropdown
        function toggleFilter() {
            var filter = document.getElementById("filterDropdown");
            filter.style.display = (filter.style.display === "block") ? "none" : "block";
        }

        function setLimit(limit) {
            var url = new URL(window.location.href);
            url.searchParams.set('limit', String(limit));
            window.location.href = url.toString();
        }

        // Close dropdowns if clicked outside
        window.onclick = function(event) {
            // Close Profile Dropdown
            if (!event.target.closest('.profile-container')) {
                document.getElementById("adminDropdown").style.display = "none";
            }
            // Close Filter Dropdown
            if (!event.target.closest('.filter-container')) {
                document.getElementById("filterDropdown").style.display = "none";
            }
        }
    </script>

</body>
</html>