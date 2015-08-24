<?php

return array(
  // The local directory where repositories will be cloned to.
  'workingDir' => '/tmp/git-glue',
  // The name of the branches where changes will be committed to.
  'workingBranch' => 'git-glue',
  // The repository where all sources should be merged into.
  'targetRepo' => 'https://github.com/someuser/target',
  // A map of repositories which should be merged into the target repository and
  // their intended subdirectory within the target repository.
  'sourceRepos' => array(
    'https://github.com/someuser/source1' => 'lib',
    'https://github.com/someuser/source2' => 'src/subdir',
    'https://github.com/someuser/source3' => 'src/subdir',
  )
);
