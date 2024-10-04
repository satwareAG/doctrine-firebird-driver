<?php

declare(strict_types=1);

use DG\BypassFinals;

require __DIR__ . '/../vendor/autoload.php';

define('ROOT_PATH', realpath(__DIR__ . '/..'));
BypassFinals::enable();
