<?php

require_once('vendor/autoload.php');

// git-glue is a CLI script to merge the contents of a list of source
// repositories into subdirectories within a target repository while preserving
// the git history of each source.

// Configuration is currently hardcoded into the script but could be moved into
// command line arguments.

// Create services
$cli = new League\CLImate\CLImate;
$logger = new \duncan3dc\CLImate\Logger(\Psr\Log\LogLevel::INFO, $cli);

$git = new \GitWrapper\GitWrapper();
$git->addLoggerListener(new \GitWrapper\Event\GitLoggerListener($logger));

$fs = new \Symfony\Component\Filesystem\Filesystem();

// Setup configuration.
// The local directory where repositories will be cloned to.
$workingDir = '/tmp/git-glue';
// The name of the branches where changes will be committed to.
$workingBranch = 'git-glue';

// The repository where all sources should be merged into.
$target = 'https://github.com/kasperg/ding2';

// A map of repositories which should be merged into the target repository and
// their intended subdirectory within the target repository.
$sources = array(
  'https://github.com/ding2/ddbasic' => 'themes/ddbasic',
  'https://github.com/ding2/alma' => 'modules/alma',
  'https://github.com/ding2/bpi' => 'modules/bpi',
  'https://github.com/ding2/ddb_cp' => 'modules/ddb_cp',
  'https://github.com/ding2/ding_adhl_frontend' => 'modules/ding_adhl_frontend',
  'https://github.com/ding2/ding_availability' => 'modules/ding_availability',
  'https://github.com/ding2/ding_base' => 'modules/ding_base',
  'https://github.com/ding2/ding_bookmark' => 'modules/ding_bookmark',
  'https://github.com/ding2/ding_campaign' => 'modules/ding_campaign',
  'https://github.com/ding2/ding_contact' => 'modules/ding_contact',
  'https://github.com/ding2/ding_content' => 'modules/ding_content',
  'https://github.com/ding2/ding_debt' => 'modules/ding_debt',
  'https://github.com/ding2/ding_devel' => 'modules/ding_devel',
  'https://github.com/ding2/ding_dibs' => 'modules/ding_dibs',
  'https://github.com/ding2/ding_dummy_provider' => 'modules/ding_dummy_provider',
  'https://github.com/ding2/ding_entity' => 'modules/ding_entity',
  'https://github.com/ding2/ding_event' => 'modules/ding_event',
  'https://github.com/ding2/ding_example_content' => 'modules/ding_example_content',
  'https://github.com/ding2/ding_facetbrowser' => 'modules/ding_facetbrowser',
  'https://github.com/ding2/ding_frontend' => 'modules/ding_frontend',
  'https://github.com/ding2/ding_frontpage' => 'modules/ding_frontpage',
  'https://github.com/ding2/ding_groups' => 'modules/ding_groups',
  'https://github.com/ding2/ding_library' => 'modules/ding_library',
  'https://github.com/ding2/ding_loan' => 'modules/ding_loan',
  'https://github.com/ding2/ding_news' => 'modules/ding_news',
  'https://github.com/ding2/ding_page' => 'modules/ding_page',
  'https://github.com/ding2/ding_periodical' => 'modules/ding_periodical',
  'https://github.com/ding2/ding_permissions' => 'modules/ding_permissions',
  'https://github.com/ding2/ding_place2book' => 'modules/ding_place2book',
  'https://github.com/ding2/ding_popup' => 'modules/ding_popup',
  'https://github.com/ding2/ding_provider' => 'modules/ding_provider',
  'https://github.com/ding2/ding_redirect' => 'modules/ding_redirect',
  'https://github.com/ding2/ding_reservation' => 'modules/ding_reservation',
  'https://github.com/ding2/ding_session_cache' => 'modules/ding_session_cache',
  'https://github.com/ding2/ding_staff' => 'modules/ding_staff',
  'https://github.com/ding2/ding_tabroll' => 'modules/ding_tabroll',
  'https://github.com/ding2/ding_ting_frontend' => 'modules/ding_ting_frontend',
  'https://github.com/ding2/ding_toggle_format' => 'modules/ding_toggle_format',
  'https://github.com/ding2/ding_user' => 'modules/ding_user',
  'https://github.com/ding2/ding_user_frontend' => 'modules/ding_user_frontend',
  'https://github.com/ding2/ding_varnish' => 'modules/ding_varnish',
  'https://github.com/ding2/ding_wayf' => 'modules/ding_wayf',
  'https://github.com/ding2/ding_webtrends' => 'modules/ding_webtrends',
  'https://github.com/ding2/fbs' => 'modules/fbs',
  'https://github.com/ding2/openruth' => 'modules/openruth',
  'https://github.com/ding2/ting' => 'modules/ting',
  'https://github.com/ding2/ting_covers' => 'modules/ting_covers',
  'https://github.com/ding2/ting_fulltext' => 'modules/ting_fulltext',
  'https://github.com/ding2/ting_infomedia' => 'modules/ting_infomedia',
  'https://github.com/ding2/ting_material_details' => 'modules/ting_material_details',
  'https://github.com/ding2/ting_new_materials' => 'modules/ting_new_materials',
  'https://github.com/ding2/ting_proxy' => 'modules/ting_proxy',
  'https://github.com/ding2/ting_reference' => 'modules/ting_reference',
  'https://github.com/ding2/ting_relation' => 'modules/ting_relation',
  'https://github.com/ding2/ting_search' => 'modules/ting_search',
  'https://github.com/ding2/ting_search_carousel' => 'modules/ting_search_carousel',
  'https://github.com/ding2/ting_sfx' => 'modules/ting_sfx',
);

// Glue each source into subdirectories within the target directory.
// The approach in inspired by http://gbayer.com/development/moving-files-from-one-git-repository-to-another-preserving-history/
// except for the fact that we want all the content from each source added to
// the target so there is no need to use filter-branch.

// First checkout a new branch from a clean version of the target repository.
$targetDir = $workingDir . parse_url($target, PHP_URL_PATH);
$fs->remove($targetDir);
$targetRepo = $git->cloneRepository($target, $targetDir);
$targetRepo->checkoutNewBranch($workingBranch);

foreach ($sources as $source => $targetSubDir) {
  // Now clone each of the sources. We assume that the default branch is the
  // one we want.
  $sourceDir = $workingDir . parse_url($source, PHP_URL_PATH);
  $fs->remove($sourceDir);
  $sourceRepo = $git->cloneRepository($source, $sourceDir);
  $sourceRepo->checkoutNewBranch($workingBranch);

  // Move repository content into the intented subdirectory.
  $fs->mkdir($sourceDir . DIRECTORY_SEPARATOR . $targetSubDir);
  // GitWrapper does not support moving * as each argument is escaped.
  // Loop through directory contents instead.
  foreach (scandir($sourceDir) as $file) {
    if (!in_array($file, array('.', '..'))) {
      // Add the k option to suppress errors when trying to move into own
      // subdirectory.
      $sourceRepo->mv($file, $targetSubDir, array('k' => TRUE));
    }
  }
  $sourceRepo->commit(sprintf('Moved %s into subdirectory %s', $source, $targetSubDir));

  // Finally add the local clone of the source as a remote to the target and
  // pull in the changes.
  $targetRepo->remote('add', $targetSubDir, $sourceRepo->getDirectory());
  $targetRepo->pull($targetSubDir, $workingBranch);
}
