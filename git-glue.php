<?php

require_once('vendor/autoload.php');

// git-glue is a CLI script to merge the contents of a list of source
// repositories into subdirectories within a target repository while preserving
// the git history of each source.

$app = new Silly\Application('git-glue', '0.1');

$app->command('glue', function(\Symfony\Component\Console\Output\OutputInterface $output) use ($app) {
    // Load configuration
    $config = \Noodlehaus\Config::load('config.php');
    $workingDir = $config->get('workingDir');
    $workingBranch = $config->get('workingBranch');
    $target = $config->get('targetRepo');
    $sources = $config->get('sourceRepos', array());

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
    $targetDir = $workingDir . DIRECTORY_SEPARATOR . \GitWrapper\GitWrapper::parseRepositoryName($target);
    $fs->remove($targetDir);
    $targetRepo = $git->cloneRepository($target, $targetDir);
    $targetRepo->checkout($workingBranch, array('B' => true));
    $progress->advance();

    foreach ($sources as $source => $targetSubDir) {
        // Now clone each of the sources. We assume that the default branch is the
        // one we want.
        $sourceDir = $workingDir . DIRECTORY_SEPARATOR . \GitWrapper\GitWrapper::parseRepositoryName($source);

        $progress->setMessage(sprintf('Merge source repository %s into %s', $source, $targetSubDir));

        $fs->remove($sourceDir);
        $sourceRepo = $git->cloneRepository($source, $sourceDir);
        $sourceRepo->checkout($workingBranch, array('B' => true));

        // Move repository content into the intented subdirectory.
        $fs->mkdir($sourceDir . DIRECTORY_SEPARATOR . $targetSubDir);
        // GitWrapper does not support moving * as each argument is escaped.
        // Loop through directory contents instead.
        foreach (scandir($sourceDir) as $file) {
            if (!in_array($file, array('.', '..'))) {
                // Add the k option to suppress errors when trying to move into own
                // subdirectory.
                $sourceRepo->mv($file, $targetSubDir, array('k' => true));
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
})->descriptions('Merge the contents of multiple repositories into one.');

$app->command('apply-patch [url] [--dir]', function($url, $dir, \Symfony\Component\Console\Output\OutputInterface $output) use ($app) {
    // Load configuration
    $config = \Noodlehaus\Config::load('config.php');
    $workingDir = $config->get('workingDir');
    $workingBranch = $config->get('workingBranch');
    $target = $config->get('targetRepo');
    $sources = $config->get('sourceRepos', array());

    // Create services
    $git = new \GitWrapper\GitWrapper();
    $git->addLoggerListener(new \GitWrapper\Event\GitLoggerListener(new \Symfony\Component\Console\Logger\ConsoleLogger($output)));
    $fs = new \Symfony\Component\Filesystem\Filesystem();

    // Download the patch as a temporary file.
    $patch = tempnam(sys_get_temp_dir(), 'git-glue-patch');
    file_put_contents($patch, file_get_contents($url));

    // Determine the directory for the patch if not provided.
    if (empty($dir)) {
        $patchPatterns = array(
            // Patterns for determining the origin repository from patch url.
            // Each should match only a single group which should be the
            // repository name.
            // - https://github.com/user/repo/pull/123.patch
            // - patch-diff.githubusercontent.com/raw/user/repo/pull/123.patch
            '#/([^/]+)/pull#',
            // - https://github.com/user/repo/compare/master...branch.patch
            '#/([^/]+)/compare#'
        );
        $patchRepoName = null;
        foreach ($patchPatterns as $pattern) {
            if (preg_match($pattern, $url, $patchRepoName)) {
                $patchRepoName = $patchRepoName[1];
                break;
            }
        }

        // Build a map of source repository names and paths.
        $sourceNames = array_map(array('\GitWrapper\Gitwrapper', 'parseRepositoryName'), array_keys($sources));
        $namedSourceTargetDirectories = array_combine($sourceNames, array_values($sources));

        if (!empty($namedSourceTargetDirectories[$patchRepoName])) {
            $dir = $namedSourceTargetDirectories[$patchRepoName];
        }
    }

    if (!empty($target) && !empty($workingDir)) {
        // If we have a target repository and a working directory defined then
        // assume that we want to work with that.
        $targetDir = $workingDir . DIRECTORY_SEPARATOR . \GitWrapper\GitWrapper::parseRepositoryName($target);
        if (!$fs->exists($targetDir)) {
            $git->cloneRepository($target, $targetDir, array('branch' => $workingBranch));
        }
    } else {
        // Assume that the current directory is repository we work with.
        $targetDir = getcwd();
    }
    $targetRepo = $git->workingCopy($targetDir);

    if (!empty($workingBranch)) {
        // If we have a working branch then lets use that.
        $targetRepo->checkout($workingBranch, array('B' => true));
    }

    // Apply the patch
    $targetRepo->run(array('am', $patch, array('directory' => $dir)));
})->descriptions('Apply a patch which has been created against a previously merged repository.', array(
    'url' => 'The url for the patch to apply.',
    '--dir' => "The directory to prepend to all file names in the patch. git-glue will try to guess this by comparing patch url and source repository configuration."
));

$app->run();
