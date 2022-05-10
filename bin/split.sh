#!/usr/bin/env bash

set -e
set -x

CURRENT_BRANCH="1.x"

function split()
{
    SHA1=`./bin/splitsh-lite --prefix=$1`
    git push $2 "$SHA1:refs/heads/$CURRENT_BRANCH" -f
}

function remote()
{
    git remote add $1 $2 || true
}

git pull origin $CURRENT_BRANCH

remote collections https://github.com/lara-elegant/collections.git
remote contracts https://github.com/lara-elegant/contracts.git
remote filesystem https://github.com/lara-elegant/filesystem.git
remote macroable https://github.com/lara-elegant/macroable.git
remote routing https://github.com/lara-elegant/routing.git
remote support https://github.com/lara-elegant/support.git
remote view https://github.com/lara-elegant/view.git

split 'src/Elegant/Collections' collections
split 'src/Elegant/Contracts' contracts
split 'src/Elegant/Filesystem' filesystem
split 'src/Elegant/Macroable' macroable
split 'src/Elegant/Routing' routing
split 'src/Elegant/Support' support
split 'src/Elegant/View' view