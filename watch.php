<?php
// Include the configuration file
require 'config.php';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get video ID from the URL
$videoId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate the video ID
if ($videoId <= 0) {
    echo "<p>Invalid video ID.</p>";
    exit;
}

// Function to fetch video details
function getVideoDetails($conn, $videoId) {
    $query = $conn->prepare("SELECT title, file_path, created_at FROM videos WHERE id = ?");
    $query->bind_param("i", $videoId);
    $query->execute();
    $query->bind_result($title, $file_path, $created_at);
    $query->fetch();
    $query->close();

    return compact('title', 'file_path', 'created_at');
}

// Fetch video details
$videoDetails = getVideoDetails($conn, $videoId);
if (empty($videoDetails['title'])) {
    echo "<p>Video not found.</p>";
    exit;
}

// Function to fetch 4 random videos
function getRandomVideos($conn, $videoId) {
    $query = $conn->prepare("SELECT id, title, created_at FROM videos WHERE id != ? ORDER BY RAND() LIMIT 4");
    $query->bind_param("i", $videoId);
    $query->execute();
    $query->store_result();
    $query->bind_result($otherVideoId, $otherVideoTitle, $otherCreatedAt);

    $otherVideos = [];
    while ($query->fetch()) {
        $otherVideos[] = ['id' => $otherVideoId, 'title' => $otherVideoTitle, 'created_at' => $otherCreatedAt];
    }
    $query->close();

    return $otherVideos;
}

// Fetch 4 random other videos
$otherVideos = getRandomVideos($conn, $videoId);

// Handle comment submission with IP and cooldown by username or IP
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $comment = trim($_POST['comment']);
    $commenterIp = $_SERVER['REMOTE_ADDR'];

    if ($username && $comment) {
        // Check for forbidden characters
        if (preg_match('/[<>;@[\]{}():]/', $comment)) {
            echo "<p>Comments cannot contain forbidden characters.</p>";
        } elseif (strlen($comment) > 50) {
            echo "<p>Comment cannot exceed 50 characters.</p>";
        } else {
            // Check cooldown by username OR IP
            $stmt = $conn->prepare("
                SELECT created_at FROM comments 
                WHERE video_id = ? AND (username = ? OR commenter_ip = ?) 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->bind_param("iss", $videoId, $username, $commenterIp);
            $stmt->execute();
            $stmt->bind_result($lastCommentTime);
            $stmt->fetch();
            $stmt->close();

            $canComment = true;
            if ($lastCommentTime) {
                $now = new DateTime();
                $last = new DateTime($lastCommentTime);
                $secondsAgo = $now->getTimestamp() - $last->getTimestamp();
                if ($secondsAgo < 3600) {
                    $canComment = false;
                    echo "<p>Please wait 1 hour before commenting again (per user/IP).</p>";
                }
            }

            if ($canComment) {
                // Insert comment with IP
                $stmt = $conn->prepare("
                    INSERT INTO comments (video_id, username, commenter_ip, comment) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("isss", $videoId, $username, $commenterIp, $comment);
                $stmt->execute();
                $stmt->close();

                header("Location: watch.php?id=" . $videoId);
                exit;
            }
        }
    }
}

// Function to fetch comments
function getComments($conn, $videoId) {
    $query = $conn->prepare("SELECT username, comment, created_at FROM comments WHERE video_id = ? ORDER BY created_at DESC");
    $query->bind_param("i", $videoId);
    $query->execute();
    $query->bind_result($username, $comment, $created_at);

    $comments = [];
    while ($query->fetch()) {
        $comments[] = ['username' => $username, 'comment' => $comment, 'created_at' => $created_at];
    }
    $query->close();

    return $comments;
}

$comments = getComments($conn, $videoId);
$conn->close();

// Function to format dates as "2 months ago"
function timeAgo($datetime) {
    $now = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);

    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    } elseif ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    } elseif ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'just now';
    }
}

// Prepare search query
$searchQuery = '';
$searchResults = [];
if (isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
    if ($searchQuery !== '') {
        // Reconnect to db for search since $conn was closed above (or move close below)
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $searchStmt = $conn->prepare("SELECT id, title FROM videos WHERE title LIKE ? LIMIT 10");
        $likeQuery = '%' . $conn->real_escape_string($searchQuery) . '%';
        $searchStmt->bind_param("s", $likeQuery);
        $searchStmt->execute();
        $searchStmt->store_result();
        $searchStmt->bind_result($videoId, $videoTitle);

        while ($searchStmt->fetch()) {
            $searchResults[] = ['id' => $videoId, 'title' => $videoTitle];
        }
        $searchStmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="<?php echo htmlspecialchars($videoDetails['title']); ?> - Watch this video." />
    <meta name="keywords" content="video, watch, <?php echo htmlspecialchars($videoDetails['title']); ?>" />
    <title>Watch <?php echo htmlspecialchars($videoDetails['title']); ?></title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        body {
            font-family: 'Arial', Arial, monospace;
            background-color: #e0e0e0;
            color: #000;
            text-align: center;
        }
        .content {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            margin-top: 20px;
        }
        .video-player {
            width: 400px;
            padding: 10px;
            background-color: #ffffff;
            border: 3px solid #0000ff;
            border-radius: 5px;
            box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.3);
            margin-right: 20px;
        }
        .video-links {
            max-width: 200px;
            margin-left: 20px;
        }
        .retro-controls {
            margin-top: 10px;
        }
        .retro-button {
            background-color: #ff0000;
            color: white;
            border: 2px solid #000;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .retro-button:hover {
            background-color: #cc0000;
        }
        .comments-section {
            margin-top: 20px;
            text-align: left;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .comments-section form {
            display: flex;
            flex-direction: column;
        }
        .comments-section input,
        .comments-section textarea {
            margin-bottom: 10px;
            padding: 5px;
            font-family: Arial, monospace;
        }
        .comments-section ul {
            list-style-type: none;
            padding: 0;
        }
        .comments-section li {
            margin-bottom: 15px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 8px;
        }
        footer {
            margin-top: 20px;
            font-size: 12px;
        }
        nav ul {
            list-style: none;
            padding: 0;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        nav ul li {
            display: inline;
        }
        nav ul li a {
            text-decoration: none;
            color: #0000ff;
            font-weight: bold;
        }
        nav ul li a:hover {
            text-decoration: underline;
        }
        header {
            margin-bottom: 20px;
        }
        .search-form {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <header>
        <h1><img src="logo.png" alt="ChapTube Logo" style="height: 50px; width: 140px;" /></h1>
        <nav>
            <ul>
                <li><a href="/">Home</a></li>
                <li><a href="upload_video.php">Upload</a></li>
                <li><a href="about.php">About</a></li>
            </ul>
        </nav>
        <form method="GET" action="query.php" class="search-form" style="margin-top: 10px;">
            <input
                type="text"
                name="search"
                placeholder="Search videos..."
                value="<?php echo htmlspecialchars($searchQuery); ?>"
                style="padding: 5px;"
            />
            <button type="submit" style="padding: 5px;">Search</button>
        </form>
    </header>

    <main>
        <div class="content">
            <section class="video-player">
                <h2><?php echo htmlspecialchars($videoDetails['title']); ?></h2>
                <p><?php echo date('F j, Y, g:i a', strtotime($videoDetails['created_at'])); ?></p>
                <video id="video" width="360" height="240" autoplay>
                    <source src="<?php echo htmlspecialchars($videoDetails['file_path']); ?>" type="video/mp4" />
                    Your browser does not support the video tag.
                </video>
                <div class="retro-controls">
                    <button class="retro-button" id="play-pause">Pause</button>
                    <button class="retro-button" id="stop">Stop</button>
                    <button class="retro-button" id="mute-unmute">Mute</button>
                </div>
            </section>

            <aside class="video-links">
                <h3>Other Videos</h3>
                <ul>
                    <?php if ($otherVideos): ?>
                        <?php foreach ($otherVideos as $video): ?>
                            <li>
                                <a href="watch.php?id=<?php echo $video['id']; ?>">
                                    <?php echo htmlspecialchars($video['title']); ?>
                                </a><br/>
                                <small><?php echo timeAgo($video['created_at']); ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No other videos available.</li>
                    <?php endif; ?>
                </ul>
            </aside>
        </div>

        <section class="comments-section">
            <h3>Comments</h3>
            <form method="POST" action="watch.php?id=<?php echo $videoId; ?>">
                <input
                    type="text"
                    name="username"
                    maxlength="15"
                    placeholder="Your username (max 15 chars)"
                    required
                    style="font-family: monospace;"
                />
                <textarea
                    name="comment"
                    maxlength="50"
                    placeholder="Your comment (max 50 chars)"
                    required
                    style="font-family: monospace; resize: none;"
                ></textarea>
                <button type="submit">Submit Comment</button>
            </form>

            <ul>
                <?php if ($comments): ?>
                    <?php foreach ($comments as $c): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($c['username']); ?></strong> —
                            <?php echo htmlspecialchars($c['comment']); ?><br />
                            <small><?php echo timeAgo($c['created_at']); ?></small>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No comments yet.</li>
                <?php endif; ?>
            </ul>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> ChapTube. All rights reserved.</p>
    </footer>

    <script>
        const video = document.getElementById('video');
        const playPauseBtn = document.getElementById('play-pause');
        const stopBtn = document.getElementById('stop');
        const muteUnmuteBtn = document.getElementById('mute-unmute');

        playPauseBtn.addEventListener('click', () => {
            if (video.paused) {
                video.play();
                playPauseBtn.textContent = 'Pause';
            } else {
                video.pause();
                playPauseBtn.textContent = 'Play';
            }
        });

        stopBtn.addEventListener('click', () => {
            video.pause();
            video.currentTime = 0;
            playPauseBtn.textContent = 'Play';
        });

        muteUnmuteBtn.addEventListener('click', () => {
            video.muted = !video.muted;
            muteUnmuteBtn.textContent = video.muted ? 'Unmute' : 'Mute';
        });
    </script>
</body>
</html>
