<?php

define('PATCH_PATH', 'sites/all/patches');
define('DOCROOT', '.');

/**
 * Interface MakeFileWriterInterface.
 *
 * Interface to capture the commonalities between make files.
 */
interface MakeFileWriterInterface {

  /**
   * Write the start of the file.
   */
  public function writePreface();

  /**
   * Write information for the core project.
   */
  public function writeCore($version, $patches);

  /**
   * Write patches for a project.
   */
  public function writePatches($patches, $project, $path);

  /**
   * Write a module project.
   */
  public function writeModule(ProjectInfo $module, $version, $patches);

}

/**
 * Class LegacyMakeFileWriter.
 *
 * Old fashioned makefiles using the ini-like format.
 */
class LegacyMakeFileWriter implements MakeFileWriterInterface {

  /**
   * {@inheritdoc}
   */
  public function writePreface() {
    wl('core = 7.x');
    wl('defaults[projects][subdir] = contrib');
    wl();
    wl('api = 2');
    wl();
  }

  /**
   * {@inheritdoc}
   */
  public function writeCore($version, $patches) {
    $p = 'projects[drupal]';
    wl($p . '[type] = "core"');
    wl($p . '[subdir] = ""');
    wl($p . '[directory_name] = ""');
    wl($p . '[version] = "' . $version . '"');

    $this->writePatches($patches, 'drupal', PATCH_PATH . '/core');

    // Blank line after the core entry.
    wl();
  }

  /**
   * {@inheritdoc}
   */
  public function writePatches($patches, $project, $path) {
    foreach ($patches as $file) {
      if (strpos($file, '.patch')) {
        wl('projects[' . $project . '][patch][] = "' . $path . '/' . $file . '"');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function writeModule(ProjectInfo $module, $version, $patches) {
    $p = 'projects[' . $module->getMachineName() . ']';
    wl($p . '[version]  = "' . $version . '"');

    $this->writePatches($patches, $module->getMachineName(), 'patches/contrib/' . $module->getMachineName());

    // Blank line at the end of the entry.
    wl();
  }

}

/**
 * Class YmlMakeFileWriter.
 *
 * Fancy new Yml makefiles.
 */
class YmlMakeFileWriter implements MakeFileWriterInterface {

  /**
   * {@inheritdoc}
   */
  public function writePreface() {
    wl("core: '7.x'");
    wl("api: 2");
    wl("defaults:");
    wl("  projects:");
    wl("    subdir: 'contrib'");
    wl();
    wl('projects:');
  }

  /**
   * {@inheritdoc}
   */
  public function writeCore($version, $patches) {
    wl('  drupal:');
    wl("    version: '" . $version . "'");

    $this->writePatches($patches, 'drupal', PATCH_PATH . '/core');
  }

  /**
   * {@inheritdoc}
   */
  public function writePatches($patches, $project, $path) {
    if (!empty($patches)) {
      wl('    patch:');
      foreach ($patches as $file) {
        if (strpos($file, '.patch')) {
          wl('      - ' . $path . '/' . $file);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function writeModule(ProjectInfo $module, $version, $patches) {
    wl('  ' . $module->getMachineName() . ':');
    wl("    version: '" . $version . "'");

    $this->writePatches($patches, $module->getMachineName(), 'patches/contrib/' . $module->getMachineName());
  }

}

/**
 * Class CsvFileWriter.
 *
 * No drush make file, actually, just a csv file containing module information.
 */
class CsvFileWriter implements MakeFileWriterInterface {

  /**
   * {@inheritdoc}
   */
  public function writePreface() {
    // Nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  public function writeCore($version, $patches) {
    wl("\"Drupal\";\"$version\";\"https://drupal.org/project/drupal\"");
  }

  /**
   * {@inheritdoc}
   */
  public function writeModule(ProjectInfo $module, $version, $patches) {
    wl("\"{$module->getFriendlyName()}\";\"$version\";\"https://drupal.org/project/{$module->getMachineName()}\"");
  }

  /**
   * {@inheritdoc}
   */
  public function writePatches($patches, $project, $path) {
    // Nothing to do here.
  }

}

/**
 * Class ProjectInfo.
 *
 * Class to contain information about a project (module, theme, ...).
 */
class ProjectInfo {
  private $version;
  private $machineName;
  private $friendlyName;

  /**
   * Constructor.
   */
  public function __construct($machineName, $version, $friendlyName) {
    $this->machineName = $machineName;
    $this->version = $version;
    $this->friendlyName = $friendlyName;
  }

  /**
   * Get the version string.
   */
  public function getVersion() {
    return $this->version;
  }

  /**
   * Get the friendly name.
   */
  public function getFriendlyName() {
    return $this->friendlyName;
  }

  /**
   * Get the machine name.
   */
  public function getMachineName() {
    return $this->machineName;
  }

}

// See which format we should be generating.
$format = 'legacy';

foreach ($argv as $value) {
  if (strpos($value, 'yml') !== FALSE) {
    $format = 'yml';
  }
  elseif (strpos($value, 'csv') !== FALSE) {
    $format = 'csv';
  }
}

$writer = NULL;

switch ($format) {
  case 'legacy':
    $writer = new LegacyMakeFileWriter();
    break;

  case 'yml':
    $writer = new YmlMakeFileWriter();
    break;

  case 'csv':
    $writer = new CsvFileWriter();
    break;
}

$writer->writePreface();

// Determine Drupal core version.
$bootstrap_inc = file_get_contents(DOCROOT . '/includes/bootstrap.inc');
$matches = array();

preg_match("/define\\('VERSION', '(\\d.\\d\\d)'\\);/", $bootstrap_inc, $matches);

// Find patches in the patches directory for core.
$patches = findPatches(PATCH_PATH . '/core');

$writer->writeCore($matches[1], $patches);

// Find all contrib modules and their versions and patches. Note that we are
// assuming directory names == project names (which not necessarily equals
// module names).
$contrib_dir = DOCROOT . '/sites/all/modules/contrib';
$modules = scandir($contrib_dir);

foreach ($modules as $module) {
  if (is_dir($contrib_dir . '/' . $module)) {
    // Find an info file.
    $files = scandir($contrib_dir . '/' . $module);

    foreach ($files as $file) {
      if (strpos($file, '.info')) {
        $info = file_get_contents($contrib_dir . '/' . $module . '/' . $file);
        $matches = array();

        // Find the version.
        $version = '';
        if (preg_match('/version = "?7\.x-(\d.\d{1,2}(-[a-zA-Z0-9]*)?)"?/', $info, $matches)) {
          // If we found a version string, build the project entry.
          $version = $matches[1];
        }

        // See if we have any patches for this module.
        $patches = findPatches(PATCH_PATH . '/contrib/' . $module);

        // Find the friendly name.
        $friendlyName = '';
        if (preg_match('/name = "?(.*)"?/', $info, $matches)) {
          // If we found a version string, build the project entry.
          $friendlyName = $matches[1];
        }

        $module = new ProjectInfo($module, $version, $friendlyName);

        $writer->writeModule($module, $version, $patches);

        // Only process a single .info file per directory. We'll assume each
        // one contains the same version.
        break;
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
 * @param string $path
 *    The path to search for patches.
 *
 */
function findPatches($path) {
  $files = [];

  if (is_dir($path)) {
    $files = scandir($path);
  }

  return $files;
}
