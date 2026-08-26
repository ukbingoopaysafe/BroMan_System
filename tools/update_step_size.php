<?php
$files = [
    __DIR__ . '/../add_property.php',
    __DIR__ . '/../edit_property.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $count = 0;
        $new_content = str_replace('step="0.01"', 'step="1"', $content, $count);
        file_put_contents($file, $new_content);
        echo basename($file) . ": Replaced " . $count . " occurrences of step=\"0.01\" with step=\"1\"\n";
    }
}
