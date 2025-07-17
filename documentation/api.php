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

// Generate YAML version
$yamlFilename = 'documentation/openapi.yml';
$yamlContent  = $openapi->toYaml();

if (file_put_contents($yamlFilename, $yamlContent) === false) {
    exit('Unable to write to file ' . $yamlFilename);
}

// Generate JSON version
$jsonFilename = 'documentation/openapi.json';
$jsonContent  = $openapi->toJson();

if (file_put_contents($jsonFilename, $jsonContent) === false) {
    exit('Unable to write to file ' . $jsonFilename);
}

echo 'OpenAPI documentation generated successfully:' . PHP_EOL;
echo '- YAML: ' . $yamlFilename . PHP_EOL;
echo '- JSON: ' . $jsonFilename . PHP_EOL;
