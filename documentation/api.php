<?php

declare(strict_types=1);

use OpenApi\Generator;

require __DIR__ . '/../vendor/autoload.php';

// Scan Laravel app directory for API classes
$scanPaths = [
    __DIR__ . '/../app/Http/Controllers/Api',
    __DIR__ . '/../app/Http/Requests/Api',
    __DIR__ . '/../app/Http/Resources/Api',
    __DIR__ . '/../app/Http/Schemas/Api',
    __DIR__ . '/../app/Models',
];

$openapi = Generator::scan($scanPaths);

if ($openapi === null) {
    exit('Failed to generate OpenAPI documentation.');
}

$filename = 'documentation/openapi.yml';
$content  = $openapi->toYaml();

if (file_put_contents($filename, $content) === false) {
    exit('Unable to write to file ' . $filename);
}

echo 'OpenAPI documentation generated successfully at ' . $filename . PHP_EOL;
