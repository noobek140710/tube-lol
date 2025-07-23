# OpenChap
The source code for ChapTube.

To make this code work, you must upload all of the files to your webhosting. If you are self hosting, you must connect to XAMPP.

On PhpMyAdmin you run this SQL code:

$createTableSQL = "CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploader_ip VARCHAR(45) NOT NULL

And

CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT NOT NULL,
    username VARCHAR(15) NOT NULL,
    commenter_ip VARCHAR(45) NOT NULL,
    comment VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(id)
);

These are the essential databases needed to make the website work.
