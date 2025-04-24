<?php

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Predis\Client as RedisClient;
use Tgolsen\Ern3Indexer\EnvironmentHandler;
use Tgolsen\Ern3Indexer\ItemProcessor;
use Tgolsen\Ern3Indexer\S3Handler;

// Initialize environment handler
$envHandler = new EnvironmentHandler(__DIR__);
$env = $envHandler->getEnvVariables();

// Initialize AWS S3 Client
$s3Client = new S3Client([
    'version' => 'latest',
    'region' => $env['AWS_REGION'],
    'credentials' => [
        'key' => $env['AWS_ACCESS_KEY'],
        'secret' => $env['AWS_SECRET_KEY']
    ]
]);

// Initialize Redis Client
$redisClient = new RedisClient([
    'scheme' => 'tcp',
    'host' => $env['REDIS_HOST'],
    'port' => $env['REDIS_PORT'],
]);

// Initialize S3 Handler and Item Processor
$s3Handler = new S3Handler($s3Client, $env['S3_BUCKET']);
$itemProcessor = new ItemProcessor($s3Handler, $redisClient, $env['SOLR_URL'], null); // No prefix

// Get the BATCH_ID from the environment variables
$batchId = $env['BATCH_ID'] ?? null; // Null if not set

if ($batchId) {
    // Process only the specific batch if BATCH_ID is specified
    $batchFolder = "{$batchId}/"; // Ensure it ends with a slash
    echo "Processing specified batch folder: $batchFolder\n";

    // Check if this batch folder contains the `BatchComplete` file
    $batchCompleteFile = "{$batchFolder}BatchComplete_" . rtrim($batchId, "/") . ".xml";
    if (!$s3Handler->fileExists($batchCompleteFile)) {
        echo "The specified batch folder ($batchFolder) is not valid (BatchComplete file not found).\n";
        exit(1); // Exit with error if the batch is invalid
    }

    echo "Valid batch folder found: $batchFolder\n";

    // Fetch all item directories within this batch folder
    $itemDirectories = $s3Handler->listDirectories($batchFolder);

    foreach ($itemDirectories as $itemDirectory) {
        echo "Processing item directory: $itemDirectory\n";

        // Check if the item folder contains the required XML file (e.g., `item-id.xml`)
        $itemId = basename($itemDirectory);
        $itemXmlFile = "{$itemDirectory}{$itemId}.xml";

        if (!$s3Handler->fileExists($itemXmlFile)) {
            echo "Skipping item directory: $itemDirectory (Missing XML file).\n";
            continue; // Move to the next item directory
        }

        // Process the valid item directory
        $itemProcessor->processItem($env['S3_BUCKET'], $batchId, $itemId);

        // Free memory
        unset($itemId, $itemXmlFile, $itemDirectory);
    }

    echo "Processing complete for batch: $batchFolder\n";

} else {
    // Process all batches if BATCH_ID is not specified
    echo "No BATCH_ID specified. Processing all batch folders.\n";

    // Fetch all batch directories (top-level folders)
    $batchFolders = $s3Handler->listDirectories(""); // Get all top-level directories

    if (empty($batchFolders)) {
        echo "No batch folders found in the bucket.\n";
        exit(0);
    }

    foreach ($batchFolders as $batchFolder) {

        $batchId = basename($batchFolder);

        // Check if the batch is already completed
        $batchCompleteKey = "batch:complete:$batchId";

        if ($redisClient->exists($batchCompleteKey)) {
            echo "Skipping batch folder: $batchFolder (Already completed).\n";
            continue; // Skip to the next batch folder
        }

        echo "Processing batch folder: $batchFolder\n";

        // Check if this batch folder contains the `BatchComplete` file
        $batchCompleteFile = "{$batchFolder}BatchComplete_" . basename($batchFolder) . ".xml";
        if (!$s3Handler->fileExists($batchCompleteFile)) {
            echo "Skipping batch folder: $batchFolder (BatchComplete file not found).\n";
            continue; // Move to the next batch folder
        }

        echo "Valid batch folder found: $batchFolder\n";

        // Fetch all item directories within this batch folder
        $itemDirectories = $s3Handler->listDirectories($batchFolder);

        foreach ($itemDirectories as $itemDirectory) {
            echo "Processing item directory: $itemDirectory\n";

            // Check if the item folder contains the required XML file (e.g., `item-id.xml`)
            $itemId = basename(rtrim($itemDirectory, '/'));
            $batchId = basename(dirname($itemDirectory));
            $itemXmlFile = "{$itemDirectory}{$itemId}.xml";

            if (!$s3Handler->fileExists($itemXmlFile)) {
                echo "Skipping item directory: $itemDirectory (Missing XML file).\n";
                continue; // Move to the next item directory
            }

            // Process the valid item directory
            $itemProcessor->processItem($env['S3_BUCKET'], $batchId, $itemId);

            // Free memory
            unset($itemId, $itemXmlFile, $itemDirectory);
        }

        echo "Marking batch as completed: $batchFolder\n";

        // Mark the batch as completed in Redis
        $redisClient->set($batchCompleteKey, true);

        echo "Processing complete for batch: $batchFolder\n";

    }

    echo "Processing complete for all batches.\n";
}
