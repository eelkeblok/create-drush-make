<?php
wl('core = 7.x');
wl('defaults[projects][subdir] = contrib');
wl();
wl('api = 2');
wl();

$p = 'projects[drupal]';
wl($p . '[type] = "core"');
wl($p . '[subdir] = ""');
wl($p . '[directory_name] = ""');


// Determine Drupal core version.
$bootstrap_inc = file_get_contents('includes/bootstrap.inc');
$matches = array();

preg_match("/define\\('VERSION', '(\\d.\\d\\d)'\\);/", $bootstrap_inc, $matches);

wl($p . '[version] = "' . $matches[1] . '"');

// Find patches in the patches directory for core.
find_patches('drupal', 'patches/core');

// Blank line after the core entry.
wl();

// Find all contrib modules and their versions and patches. Note that we are
// assuming directory names == project names (which not necessarily equals
// module names).
$contrib_dir = 'sites/all/modules/contrib';
$modules = scandir($contrib_dir);

foreach ($modules as $module) {
  if (is_dir($contrib_dir . '/' . $module)) {
    // Find an info file.
    $files = scandir($contrib_dir . '/' . $module);

    foreach ($files as $file) {
      if (strpos($file, '.info')) {
        $info = file_get_contents($contrib_dir . '/' . $module . '/' . $file);
        $matches = array();
        if (preg_match('/version = "?7\.x-(\d.\d{1,2}(-[a-zA-Z0-9]*)?)"?/', $info, $matches)) {
          // If we found a version string, build the project entry.
          $version = $matches[1];
          $p = 'projects[' . $module . ']';
          wl($p . '[version]  = "' . $version . '"');

          // See if we have any patches for this module.
          find_patches($module, 'patches/contrib/' . $module);

          // Blank line at the end of the entry.
          wl();

          // Only process a single .info file per directory. We'll assume each
          // one contains the same version.
          break;
        }
      }
    }
  }
}

/**
 * Write a line.
 *
 * @param string $string
 *   The line to write.
 */
function wl($string = '') {
  echo $string . "\n";
}

/**
 * Find patches for a project.
 *
 * @param string $project
 *    The name of the project.
 * @param string $path
 *    The path to search for patches. This should be relative to the sites/all
 *    directory in the flat project.
 *
 */
function find_patches($project, $path) {
  // Find patches in the patches directory for core.
  $dir = 'sites/all/' . $path;
  if (is_dir($dir)) {
    $files = scandir($dir);

    foreach ($files as $file) {
      if (strpos($file, '.patch')) {
        wl('projects[' . $project . '][patch][] = "' . $path . '/' . $file . '"');
      }
    }
  }
}
