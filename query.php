<?php
// Include the configuration file
require 'config.php';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the search query from the URL
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare the SQL statement to search for videos
$query = $conn->prepare("SELECT id, title FROM videos WHERE title LIKE ?");
$searchTerm = '%' . $search . '%';
$query->bind_param('s', $searchTerm);
$query->execute();

// Bind the results to variables
$query->bind_result($id, $title);

// Initialize the $videos array
$videos = [];
while ($query->fetch()) {
    $videos[] = ['id' => $id, 'title' => $title];
}

// Close the prepared statement
$query->close();

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results for "<?php echo htmlspecialchars($search); ?>"</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1><img src="logo.png" alt="ChapTube Logo" style="height=" 50"="" width="140"></h1>
        <nav>
            <ul>
                <li><a href="/">Home</a></li>
                <li><a href="upload_video.php">Upload Video</a></li>
                <li><a href="about.php">About</a></li>
            </ul>
        </nav>
        <!-- Search Bar -->
        <form action="query.php" method="get">
            <input type="text" name="search" placeholder="Search videos..." value="<?php echo htmlspecialchars($search); ?>" required>
            <button type="submit">Search</button>
        </form>
    </header>

    <main>
        <section class="video-list">
            <h2>Search Results</h2>
            <ul>
                <?php if ($videos): ?>
                    <?php foreach ($videos as $video): ?>
                        <li>
                            <a href="watch.php?id=<?php echo $video['id']; ?>">
                                <?php echo htmlspecialchars($video['title']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No results found for "<?php echo htmlspecialchars($search); ?>".</p>
                <?php endif; ?>
            </ul>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> ChapTube. All rights reserved.</p>
    </footer>
</body>
</html>