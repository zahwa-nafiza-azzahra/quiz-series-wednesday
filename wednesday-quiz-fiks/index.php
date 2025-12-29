<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Wednesday</title>
    <style>
        /* --- LOAD FONT LOKAL --- */
        @font-face {
            font-family: 'MetalMania';
            src: url('asset/MetalMania-Regular.ttf') format('truetype');
        }

        /* RESET & CSS */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            height: 100vh;
            width: 100%;
            /* Ganti dengan gambar Wednesday Payung jika ada, atau background default */
            background: url('asset/background2.jpg') no-repeat center center/cover; 
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            padding-bottom: 8vh; 
            position: relative;
            overflow: hidden;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; width: 100%; height: 50%;
            background: linear-gradient(to top, rgba(0,0,0,1), transparent);
            z-index: 1;
        }

        /* Container Logo Link */
        .logo-link {
            position: relative;
            z-index: 2;
            display: block;
            width: 80%;
            max-width: 500px;
            transition: transform 0.3s ease, filter 0.3s ease;
            cursor: pointer;
            filter: drop-shadow(0 0 5px rgba(255,255,255,0.2));
            text-align: center;
        }

        .logo-link:hover {
            transform: scale(1.05);
            filter: drop-shadow(0 0 15px rgba(255,255,255,0.5));
        }

        .logo-link img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        /* Fallback jika gambar logo tidak ada, pakai Teks MetalMania */
        .logo-text {
            font-family: 'MetalMania', serif;
            font-size: 60px;
            color: white;
            text-decoration: none;
            letter-spacing: 5px;
            text-shadow: 0 0 10px black;
        }
    </style>
</head>
<body>

    <a href="login.php" class="logo-link">
        <?php if(file_exists('asset/fontwesnesday.png')): ?>
            <img src="asset/fontwesnesday.png" alt="WEDNESDAY">
        <?php else: ?>
            <span class="logo-text">WEDNESDAY</span>
        <?php endif; ?>
    </a>

</body>
</html>