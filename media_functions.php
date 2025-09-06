<?php
// Media helper functions for property images and audio notes

/**
 * Get all images for a property
 */
function getPropertyImages($property_id, $conn) {
    $images = [];
    $sql = "SELECT image_id, file_path, upload_date, is_primary FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, upload_date ASC";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $images[] = $row;
        }
        $stmt->close();
    }
    
    return $images;
}

/**
 * Get all audio notes for a property
 */
function getPropertyAudioNotes($property_id, $conn) {
    $audio_notes = [];
    $sql = "SELECT note_id, file_path, upload_date, duration FROM property_audio_notes WHERE property_id = ? ORDER BY upload_date ASC";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $audio_notes[] = $row;
        }
        $stmt->close();
    }
    
    return $audio_notes;
}

/**
 * Get all videos for a property
 */
function getPropertyVideos($property_id, $conn) {
    $videos = [];
    $sql = "SELECT VideoID, VideoPath, VideoName, VideoSize, UploadDate FROM property_videos WHERE PropertyID = ? ORDER BY UploadDate ASC";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $videos[] = $row;
        }
        $stmt->close();
    }
    
    return $videos;
}

/**
 * Get primary image for a property (for card view)
 */
function getPropertyPrimaryImage($property_id, $conn) {
    $sql = "SELECT file_path FROM property_images WHERE property_id = ? AND is_primary = 1 LIMIT 1";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['file_path'];
        }
        $stmt->close();
    }
    
    // If no primary image, get the first image
    $sql = "SELECT file_path FROM property_images WHERE property_id = ? ORDER BY upload_date ASC LIMIT 1";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['file_path'];
        }
        $stmt->close();
    }
    
    return null; // No images found
}

/**
 * Set an image as primary
 */
function setImageAsPrimary($image_id, $property_id, $conn) {
    // First, remove primary flag from all images of this property
    $sql1 = "UPDATE property_images SET is_primary = 0 WHERE property_id = ?";
    if ($stmt1 = $conn->prepare($sql1)) {
        $stmt1->bind_param("i", $property_id);
        $stmt1->execute();
        $stmt1->close();
    }
    
    // Then set the specified image as primary
    $sql2 = "UPDATE property_images SET is_primary = 1 WHERE image_id = ? AND property_id = ?";
    if ($stmt2 = $conn->prepare($sql2)) {
        $stmt2->bind_param("ii", $image_id, $property_id);
        $result = $stmt2->execute();
        $stmt2->close();
        return $result;
    }
    
    return false;
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Format duration for audio files
 */
function formatDuration($seconds) {
    if ($seconds === null) {
        return 'Unknown';
    }
    
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    
    return sprintf('%02d:%02d', $minutes, $seconds);
}


/**
 * Move temporary uploads from a session temp folder into the property's media and create DB entries.
 * session_id should be the PHP session id (session_id()).
 */
function moveTempUploadsToProperty($property_id, $session_id, $conn) {
    $base = __DIR__ . '/uploads/';
    $tempDir = $base . 'temp/' . $session_id . '/';
    if (!is_dir($tempDir)) return false;

    $moved = ['images' => [], 'audio' => [], 'videos' => []];

    // Images
    $tempImagesDir = $tempDir . 'images/';
    if (is_dir($tempImagesDir)) {
        $files = scandir($tempImagesDir);
        foreach ($files as $f) {
            if (in_array($f, ['.', '..'])) continue;
            $src = $tempImagesDir . $f;
            $dst = $base . 'images/' . $f;
            if (rename($src, $dst)) {
                // Determine if primary (if no images exist yet)
                $is_primary = 0;
                $check_sql = "SELECT COUNT(*) as count FROM property_images WHERE property_id = ?";
                if ($stmt = $conn->prepare($check_sql)) {
                    $stmt->bind_param("i", $property_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $is_primary = ($row['count'] == 0) ? 1 : 0;
                    $stmt->close();
                }

                $relative = 'uploads/images/' . $f;
                $ins = "INSERT INTO property_images (property_id, file_path, is_primary) VALUES (?, ?, ?)";
                if ($stmt = $conn->prepare($ins)) {
                    $stmt->bind_param("isi", $property_id, $relative, $is_primary);
                    $stmt->execute();
                    $stmt->close();
                    $moved['images'][] = $relative;
                }
            }
        }
    }

    // Audio
    $tempAudioDir = $tempDir . 'audio/';
    if (is_dir($tempAudioDir)) {
        $files = scandir($tempAudioDir);
        foreach ($files as $f) {
            if (in_array($f, ['.', '..'])) continue;
            $src = $tempAudioDir . $f;
            $dst = $base . 'audio/' . $f;
            if (rename($src, $dst)) {
                $relative = 'uploads/audio/' . $f;
                $ins = "INSERT INTO property_audio_notes (property_id, file_path, duration) VALUES (?, ?, ?)";
                if ($stmt = $conn->prepare($ins)) {
                    $duration = null;
                    $stmt->bind_param("isi", $property_id, $relative, $duration);
                    $stmt->execute();
                    $stmt->close();
                    $moved['audio'][] = $relative;
                }
            }
        }
    }

    // Videos
    $tempVideosDir = $tempDir . 'videos/';
    if (is_dir($tempVideosDir)) {
        $files = scandir($tempVideosDir);
        foreach ($files as $f) {
            if (in_array($f, ['.', '..'])) continue;
            $src = $tempVideosDir . $f;
            $dst = $base . 'videos/' . $f;
            if (rename($src, $dst)) {
                $relative = 'uploads/videos/' . $f;
                $ins = "INSERT INTO property_videos (PropertyID, VideoPath, VideoName, VideoSize, EmployeeID) VALUES (?, ?, ?, ?, ?)";
                if ($stmt = $conn->prepare($ins)) {
                    // We don't have original name/size here; attempt to use filename and filesize
                    $videoName = $f;
                    $videoSize = filesize($dst);
                    $employee_id = $_SESSION['employee_id'] ?? null;
                    $stmt->bind_param("issii", $property_id, $relative, $videoName, $videoSize, $employee_id);
                    $stmt->execute();
                    $stmt->close();
                    $moved['videos'][] = $relative;
                }
            }
        }
    }

    // Cleanup temp dir if empty
    // Attempt to remove directories
    @rmdir($tempDir . 'images/');
    @rmdir($tempDir . 'audio/');
    @rmdir($tempDir . 'videos/');
    @rmdir($tempDir);

    return $moved;
}
?>

