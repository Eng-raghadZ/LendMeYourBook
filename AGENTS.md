<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application.

Follow these repository instructions before and while working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

php -v
composer -V

If either PHP or Composer is unavailable:

- stop
- report which prerequisite is missing
- ask the user for explicit authorization before any installation

Do NOT automatically install:

- PHP
- Composer
- system dependencies
- Laravel packages
- Composer packages
- NPM packages
- third-party dependencies

Do NOT run remote installation scripts unless the user has explicitly authorized that specific installation.

## Agent Setup

Laravel Boost is optional for this repository.

Do NOT install Laravel Boost unless the user explicitly authorizes that installation.

In particular, do NOT automatically run:

composer require laravel/boost --dev
php artisan boost:install

If Laravel Boost is already installed and available, you may use it when appropriate.

If Laravel Boost is not installed, continue the user's requested work without installing it.

Do NOT block application work solely because Laravel Boost is unavailable.

Do not modify package dependencies unless the user explicitly requests or authorizes the modification.

## Repository Safety

Do NOT open, read, print, modify, or expose sensitive files or secrets unless the user explicitly requests access and it is necessary for the task.

This includes, but is not limited to:

- .env
- .env.*
- passwords
- API keys
- tokens
- credentials
- private keys
- production secrets

Do NOT dump environment variables or secret configuration.

Do NOT run destructive project or database commands unless the user explicitly requests and authorizes them.

Examples include:

- php artisan migrate:fresh
- php artisan db:wipe
- destructive database resets
- git reset
- git clean
- commands that discard uncommitted work

Preserve unrelated existing working-tree changes.

Do not revert, overwrite, stage, commit, or otherwise modify unrelated files unless the user explicitly asks.

## Git

Do NOT:

- commit
- push
- rebase
- reset
- discard changes

unless the user explicitly requests that specific Git operation.

When implementing a feature, keep unrelated existing modifications untouched.

## Working Style

Before changing application code:

1. inspect the relevant existing code and conventions
2. inspect the current git status
3. identify the files that need to change
4. preserve unrelated work
5. implement only the requested scope
6. run relevant tests and existing formatting tools when appropriate
7. report any blocker instead of bypassing repository constraints

Do not install a dependency merely to make a task easier if the task can be completed using the existing repository.

If repository instructions conflict with a direct user instruction, stop and clearly report the conflict instead of silently choosing one.

</laravel-boost-guidelines>
