<?php
namespace Deployer;
require 'vendor/deployer/deployer/recipe/common.php';
set('repository', 'git@<host>:<user>/<repo>.git');

// Install vendors without development dependencies.
task('deploy:vendors', function () {
    run('composer install --no-dev --prefer-dist --optimize-autoloader');
});
after('deploy:update_code', 'deploy:vendors');

// TODO: Add asset builds or caching steps if needed.
