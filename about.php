<?php
// Include the configuration file
require 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - ChapTube</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to CSS file -->
</head>
<body>
    <header>
        <h1><img src="logo.png" height="50" width="140"></h1>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="upload_video.php">Upload</a></li>
                <li><a href="about.php">About</a></li>
                  
            </ul>
        </nav>
             <form action="query.php" method="get">
            <input type="text" name="search" placeholder="Search videos..." required>
            <button type="submit">Search</button>
        </form>
           
          </header>
        
        
    <main>
        <section class="about-section">
            <h2>About Website</h2>
            <p>This wesite is a successor to VikTube. VikTube shut down back in October due to a high website traffic.
            <br>I have a new hosting service (That should hopefully run so much better) for the site.
            <br>This website was started on Jun 11th, 2025.
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> ChapTube. All rights reserved.</p>
    </footer>
</body>
</html>