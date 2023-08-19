<?php
  session_start();

  function filesize_formatted($path) {
    $size = filesize($path);
    $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
  }

  function show_entry($link, $label) {
    echo "<p><a href='$link' style='display:inline-flex; min-width:20rem;'>$label</p>";
  }

  # read all files in cwd
  $path = '.';
  $files = scandir($path);
  show_entry('..', "..");

  for ($i=0; $i<count($files); $i++) {
    $fname = $files[$i];
    if (! $fname ) { continue; }
    if ($fname == 'index.php' || $fname == '.' || $fname == '..') { continue; }
    $fsize = filesize_formatted($fname);
    show_entry($fname, "$fname</a>&nbsp;$fsize");
  }
?>