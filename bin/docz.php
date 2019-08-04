#!/usr/bin/env php
<?php

$paths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        include($path);
        break;
    }
}

/**
 * Time to parse arguments...
 */
$arguments = $argv;

array_shift($arguments);

/**
 * Let's re-index the arguments array.
 */
$arguments = array_values($arguments);
$positional = [];
$named =[
    'lt' => '%c.md',
    'index' => 'index.md',
];

for ($i = 0; $i < count($arguments); $i++) {
    if (substr($arguments[$i], 0, 2) === '--') {
        $named[substr($arguments[$i], 2)] = $arguments[$i + 1];
        $i++;
    } else {
        $positional[] = $arguments[$i];
    }
}

$input = $positional[0];

$outputDir = 'docs';

if (isset($positional[1])) {
    $outputDir = $positional[1];
}

$parser = new \TinyPixel\PHPDocZ\Parser($input);

echo "Parsing structure.xml\n";

$classDefinitions = $parser->run();

$templateDir = dirname(__DIR__) . '/templates/';

$generator = new \TinyPixel\PHPDocZ\Generator(
    $classDefinitions,
    $outputDir,
    $templateDir,
    $named['lt'],
    $named['index']
);

echo "Generating pages\n";

$generator->run();

echo "Fin\n";
