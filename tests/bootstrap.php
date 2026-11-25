<?php

$root = dirname(__DIR__);
if (!class_exists(MM\Meros\Foundations\Testing\Bootstrap::class) && is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
}
if (!class_exists(MM\Meros\Foundations\Testing\Bootstrap::class)) {
    throw new RuntimeException('Install development dependencies or use the shared workspace test runner.');
}
MM\Meros\Foundations\Testing\Bootstrap::load($root);
