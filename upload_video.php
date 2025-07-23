<?php
session_start();
require_once 'config.php';

$message = '';

// Connect to the database
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . htmlspecialchars($mysqli->connect_error, ENT_QUOTES, 'UTF-8'));
}

// Create videos table if it doesn't exist (added file_hash column for possible future use)
$createTableSQL = "CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploader_ip VARCHAR(45) NOT NULL
)";

if (!$mysqli->query($createTableSQL)) {
    die("Could not create table: " . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8'));
}

// Get uploader IP
$uploader_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// 1-hour cooldown in seconds
$cooldownPeriod = 60 * 60;
$canUpload = true;

// Check cooldown from DB by IP
$stmtCooldown = $mysqli->prepare("SELECT MAX(created_at) FROM videos WHERE uploader_ip = ?");
if (!$stmtCooldown) {
    die("SQL prepare error: " . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8'));
}
$stmtCooldown->bind_param("s", $uploader_ip);
$stmtCooldown->execute();
$stmtCooldown->bind_result($lastUploadTime);
$stmtCooldown->fetch();
$stmtCooldown->close();

if ($lastUploadTime !== null) {
    $lastUploadTimestamp = strtotime($lastUploadTime);
    if (time() - $lastUploadTimestamp < $cooldownPeriod) {
        $remainingTime = $cooldownPeriod - (time() - $lastUploadTimestamp);
        $minutes = floor($remainingTime / 60);
        $seconds = $remainingTime % 60;
        $message = "You must wait {$minutes} minute(s) and {$seconds} second(s) before uploading again.";
        $canUpload = false;
    }
}

function isValidMp4($filePath) {
    if (!is_readable($filePath)) {
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);

    return $mimeType === 'video/mp4';
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) {
        return 'video';
    }
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canUpload) {
    $title = trim($_POST['title']);
    $title = preg_replace('/[<>\[\]{}"\'=()]/', '', $title); // Remove prohibited characters

    if (strlen($title) > 100) {
        $message = 'Video title cannot exceed 100 characters.';
    } elseif (empty($title)) {
        $message = 'Video title is required and cannot contain prohibited characters.';
    } elseif (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['video']['tmp_name'];
        $fileName = $_FILES['video']['name'];
        $fileSize = $_FILES['video']['size'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = ['mp4'];
        $maxFileSize = 10 * 1024 * 1024; // 10 MB

        if (!in_array($fileExtension, $allowedfileExtensions) || $fileSize > $maxFileSize) {
            $message = 'Upload failed. Only MP4 files under 10 MB are allowed.';
        } else {
            $uploadFileDir = './uploads/';
            if (!is_dir($uploadFileDir) && !mkdir($uploadFileDir, 0755, true)) {
                die('Failed to create upload directory.');
            }

            $slugTitle = slugify($title);
            $newFileName = $slugTitle . '.' . $fileExtension;

            $counter = 1;
            while (file_exists($uploadFileDir . $newFileName)) {
                $newFileName = $slugTitle . '-' . $counter . '.' . $fileExtension;
                $counter++;
            }

            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                if (!isValidMp4($dest_path)) {
                    unlink($dest_path);
                    $message = 'Uploaded file is corrupt or not a valid MP4. Please upload a valid MP4 file.';
                } else {
                    // Regenerate session ID for security after upload
                    session_regenerate_id();

                    $stmt = $mysqli->prepare("INSERT INTO videos (title, file_path, uploader_ip) VALUES (?, ?, ?)");
                    if (!$stmt) {
                        die("SQL prepare error: " . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8'));
                    }

                    $stmt->bind_param("sss", $title, $dest_path, $uploader_ip);

                    if (!$stmt->execute()) {
                        die('File uploaded, but there was an error saving to the database: ' . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8'));
                    }

                    $message = 'File is successfully uploaded and recorded in the database. <a href="' . htmlspecialchars($dest_path, ENT_QUOTES, 'UTF-8') . '">View Video</a>';
                    $stmt->close();
                }
            } else {
                $message = 'There was an error moving the uploaded file.';
            }
        }
    } else {
        $message = 'There was an error uploading the file. Error code: ' . htmlspecialchars($_FILES['video']['error'], ENT_QUOTES, 'UTF-8');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$canUpload) {
    // Already set $message in cooldown check
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Upload Video</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
<header>
    <h1><img src="logo.png" height="50" width="140" alt="ChapTube Logo" /></h1>
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

<main>
    <section>
        <h2>Upload Video</h2>
        <?php if (!empty($message)): ?>
            <p><?php echo $message; ?></p>
        <?php endif; ?>

        <form action="upload_video.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
            <label for="title">Video Title:</label>
            <input type="text" name="title" required
                <?php if (!$canUpload) echo 'disabled'; ?> />

            <label for="video">Choose Video:</label>
            <input type="file" name="video" accept="video/mp4" required id="videoInput"
                <?php if (!$canUpload) echo 'disabled'; ?> />

            <p style="font-size: 0.9em; color: #555;">
                Please upload an MP4 video file (maximum size: 10 MB).
            </p>

            <input type="submit" value="Upload Video"
                <?php if (!$canUpload) echo 'disabled'; ?> />
        </form>
    </section>
</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> ChapTube. All rights reserved.</p>
</footer>

<script>
function validateForm() {
    const titleInput = document.querySelector('input[name="title"]');
    const videoInput = document.getElementById('videoInput');
    const title = titleInput.value.trim();
    const file = videoInput.files[0];

    if (!title) {
        alert("Please enter a video title.");
        return false;
    }

    if (!file) {
        alert("Please select a video file.");
        return false;
    }

    const maxSize = 10 * 1024 * 1024; // 10 MB
    if (file.size > maxSize) {
        alert("File size must be 10 MB or less.");
        return false;
    }

    const validFileTypes = ['video/mp4'];
    if (!validFileTypes.includes(file.type)) {
        alert("Invalid file type. Please upload an MP4 video.");
        return false;
    }

    return true; // Form is valid
}
</script>
</body>
</html>
