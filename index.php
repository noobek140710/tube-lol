<?php
// Include the configuration file
require 'config.php';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8'));
}

// Fetch the latest 5 video titles from the database
$query = $conn->prepare("SELECT id, title, created_at FROM videos ORDER BY created_at DESC LIMIT 5");
$query->execute();
$query->bind_result($id, $title, $created_at);

// Initialize the $videos array
$videos = [];
while ($query->fetch()) {
    $videos[] = ['id' => $id, 'title' => $title, 'created_at' => $created_at];
}

// Close the prepared statement and connection
$query->close();
$conn->close();

function timeAgo($datetime) {
    $time_ago = strtotime($datetime);
    $current_time = time();
    $time_difference = $current_time - $time_ago;

    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);

    if ($seconds <= 60) {
        return "Just Now";
    } elseif ($minutes <= 60) {
        return ($minutes == 1) ? "one minute ago" : "$minutes minutes ago";
    } elseif ($hours <= 24) {
        return ($hours == 1) ? "an hour ago" : "$hours hours ago";
    } elseif ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } elseif ($weeks <= 4) {
        return ($weeks == 1) ? "a week ago" : "$weeks weeks ago";
    } elseif ($months <= 12) {
        return ($months == 1) ? "a month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "one year ago" : "$years years ago";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ChapTube</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <header>
        <h1>
            <a href="/">
                <img src="logo.png" alt="ChapTube Logo" style="height: 50px; width: 140px;" />
            </a>
        </h1>
        <nav>
            <ul>
                <li><a href="/">Home</a></li>
                <li><a href="upload_video.php">Upload</a></li>
                <li><a href="about.php">About</a></li>
            </ul>
        </nav>
        <form action="query.php" method="get">
            <input type="text" name="search" placeholder="Search videos..." required />
            <button type="submit">Search</button>
        </form>
    </header>

    <!-- Fixed: wrapped paragraph inside a section -->
    <section class="info-message" style="margin: 20px; font-family: Arial, sans-serif;">
        <p>
            
        </p>
    </section>
	<center>
    <main>
		
		
        <section class="video-list">
            <h2>Latest Uploads</h2>
            <ul>
                <?php if ($videos): ?>
                    <?php foreach ($videos as $video): ?>
                        <li>
                            <a href="watch.php?id=<?php echo htmlspecialchars($video['id']); ?>">
                                <?php echo htmlspecialchars($video['title']); ?>
                            </a>
                            <span>(<?php echo timeAgo($video['created_at']); ?>)</span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No videos available.</p>
                <?php endif; ?>
            </ul>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> ChapTube. All rights reserved.</p>
    </footer>
</body>
</html>
