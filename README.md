# git-glue

git-glue is a PHP CLI app to merge the contents of a list of source repositories into subdirectories within a target repository while preserving the git history of each source.

The approach in inspired by the blog post ["Moving Files from one Git Repository to Another, Preserving History"](http://gbayer.com/development/moving-files-from-one-git-repository-to-another-preserving-history/) by Greg Bayer except for the fact that we want all the content from each source added to the target so there is no need to use filter-branch.

## Installation and setup

1. Go to the [latest release](releases/latest) 
2. Download the `git-glue.phar` file
3. Run `phar git-glue.phar` to see a list of available commands
4. Create a `config.php` from where you intend to run git-glue. See [the sample file](https://raw.githubusercontent.com/kasperg/git-glue/master/config.sample.php) for configuration options. The location can either be a git checkout of the target repository on an arbitrary directory if the `workingDir` configuration option is defined.

## Usage

### Merging together repositories

1. Run `php git-glue.phar glue`.
2. Wait - the process can take a while
3. Go to the working directory, examine the result and decide what to do next (merge the git-glue working branch, push it etc.)

### Apply patches from merged repositories

git-glue can help apply patches to source directories even after they have been merged.

This can be useful when dealing with changes that have been left behind. For example a pull request can be converted to a patch file by adding `.patch` to the URL.

git-glue uses the source repository list to determine where in the target repository the patch belongs and tries to apply the patch accordingly.

1. Run `php git-glue.phar apply-patch [path to patch]`
2. Go to the working directory, examine the result and decide what to do next (merge the git-glue working branch, push it etc.)

## Credit

Development of git-glue has been sponsored by [Danskernes Digitale Bibliotek](http://www.danskernesdigitalebibliotek.dk/).