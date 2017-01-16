<?php
// See if we should be generating legacy format or yml format.
$format = 'legacy';
$patch_path = 'patches';
$docroot = 'docroot';

foreach ($argv as $value) {
  if (strpos($value, 'yml') !== FALSE) {
    $format = 'yml';
  }
}

writePreface();

// Determine Drupal core version.
$bootstrap_inc = file_get_contents($docroot . '/includes/bootstrap.inc');
$matches = array();

preg_match("/define\\('VERSION', '(\\d.\\d\\d)'\\);/", $bootstrap_inc, $matches);

// Find patches in the patches directory for core.
$patches = findPatches('drupal', $patch_path . '/core');

writeCore($matches[1], $patches);

// Find all contrib modules and their versions and patches. Note that we are
// assuming directory names == project names (which not necessarily equals
// module names).
$contrib_dir = $docroot . '/sites/all/modules/contrib';
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

          // See if we have any patches for this module.
          $patches = findPatches($module, 'patches/contrib/' . $module);

          writeModule($module, $version, $patches);
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
function findPatches($project, $path) {
  // Find patches in the patches directory for core.
  $dir = 'sites/all/' . $path;
  $files = [];

  if (is_dir($dir)) {
    $files = scandir($dir);
  }

  return $files;
}

function writePreface() {
  wl('core = 7.x');
  wl('defaults[projects][subdir] = contrib');
  wl();
  wl('api = 2');
  wl();
}

function writeCore($version, $patches) {
  global $patch_path;

  $p = 'projects[drupal]';
  wl($p . '[type] = "core"');
  wl($p . '[subdir] = ""');
  wl($p . '[directory_name] = ""');
  wl($p . '[version] = "' . $version . '"');

  writePatches($patches, 'drupal', $patch_path . '/core');

  // Blank line after the core entry.
  wl();
}

function writePatches($patches, $project, $path) {
  foreach ($patches as $file) {
    if (strpos($file, '.patch')) {
      wl('projects[' . $project . '][patch][] = "' . $path . '/' . $file . '"');
    }
  }
}

function writeModule($module, $version, $patches) {
  $p = 'projects[' . $module . ']';
  wl($p . '[version]  = "' . $version . '"');

  writePatches($patches, $module, 'patches/contrib/' . $module);

  // Blank line at the end of the entry.
  wl();
}
