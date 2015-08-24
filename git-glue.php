<?php

require_once('vendor/autoload.php');

// git-glue is a CLI script to merge the contents of a list of source
// repositories into subdirectories within a target repository while preserving
// the git history of each source.

$app = new Silly\Application('git-glue', '0.1');

$app->command('glue', function(\Symfony\Component\Console\Output\OutputInterface $output) use ($app) {
  // Load configuration
  $config = require('config.php');
  $workingDir = $config['workingDir'];
  $workingBranch = $config['workingBranch'];
  $target = $config['targetRepo'];
  $sources = $config['sourceRepos'];

  // Create services
  $git = new \GitWrapper\GitWrapper();
  $git->addLoggerListener(new \GitWrapper\Event\GitLoggerListener(new \Symfony\Component\Console\Logger\ConsoleLogger($output)));

  $fs = new \Symfony\Component\Filesystem\Filesystem();

  $progress = new \Symfony\Component\Console\Helper\ProgressBar($output, count($sources) + 1);
  $progress->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% | %message%");
  $progress->start();

  // Glue each source into subdirectories within the target directory.
  // The approach in inspired by http://gbayer.com/development/moving-files-from-one-git-repository-to-another-preserving-history/
  // except for the fact that we want all the content from each source added to
  // the target so there is no need to use filter-branch.

  // First checkout a new branch from a clean version of the target repository.
  $progress->setMessage(sprintf('Prepare target repository %s', $target));
  $targetDir = $workingDir . parse_url($target, PHP_URL_PATH);
  $fs->remove($targetDir);
  $targetRepo = $git->cloneRepository($target, $targetDir);
  $targetRepo->checkoutNewBranch($workingBranch);
  $progress->advance();

  foreach ($sources as $source => $targetSubDir) {
    // Now clone each of the sources. We assume that the default branch is the
    // one we want.
    $sourceDir = $workingDir . parse_url($source, PHP_URL_PATH);

    $progress->setMessage(sprintf('Merge source repository %s into %s', $source, $targetSubDir));

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
    $sourceRepo->commit(
      sprintf('Moved %s into subdirectory %s', $source, $targetSubDir)
    );

    // Finally add the local clone of the source as a remote to the target and
    // pull in the changes.
    $targetRepo->remote('add', $targetSubDir, $sourceRepo->getDirectory());
    $targetRepo->pull($targetSubDir, $workingBranch);

    $progress->advance();
  }

  $progress->finish();
});

$app->run();
