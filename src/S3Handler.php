<?php

namespace Tgolsen\Ern3Indexer;

use Aws\S3\S3Client;

class S3Handler
{
    private $s3Client;
    private $bucket;

    public function __construct(S3Client $s3Client, string $bucket)
    {
        $this->s3Client = $s3Client;
        $this->bucket = $bucket;
    }

    /**
     * Check if an object exists in the bucket
     */
    public function fileExists(string $key): bool
    {
        try {
            $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            return false;
        }
    }

    /**
     * Fetch the content of an object from the bucket
     *
     * @throws \Exception
     */
    public function getObjectContent(string $key): string
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
            return (string)$result['Body'];
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new \Exception("Error fetching object: $key (" . $e->getMessage() . ")");
        }
    }

    /**
     * List all top-level directories or directories under a specific prefix in the bucket
     *
     * @param string $prefix Directory prefix (optional)
     * @return array List of directory paths
     */
    public function listDirectories(string $prefix = ''): array
    {
        $directories = [];
        $continuationToken = null; // Token to handle pagination

        do {
            // Prepare parameters for listObjectsV2
            $params = [
                'Bucket' => $this->bucket,
                'Delimiter' => '/', // Fetch directories only
            ];

            // Add prefix if provided
            if (!empty($prefix)) {
                $params['Prefix'] = $prefix;
            }

            // Add continuation token if available
            if ($continuationToken) {
                $params['ContinuationToken'] = $continuationToken;
            }

            // Fetch the objects
            $result = $this->s3Client->listObjectsV2($params);

            // Extract directories from the result
            if (isset($result['CommonPrefixes'])) {
                foreach ($result['CommonPrefixes'] as $prefixObj) {
                    $directories[] = $prefixObj['Prefix'];
                }
            }

            // Update the continuation token for the next iteration
            $continuationToken = $result['NextContinuationToken'] ?? null;

        } while ($continuationToken); // Continue if there's a next page

        return $directories;
    }


    /**
     * List valid item directories, ensuring each contains the defined item XML file
     *
     * @param string $batchPrefix Prefix for the batch folder
     * @return array List of valid directories
     */
    public function getValidItemDirectories(string $batchPrefix): array
    {
        $directories = $this->listDirectories($batchPrefix);

        $validDirectories = [];
        foreach ($directories as $directory) {
            $itemId = basename(rtrim($directory, '/'));
            $xmlFile = "{$directory}{$itemId}.xml";

            // Validate the existence of the corresponding XML file
            if ($this->fileExists($xmlFile)) {
                $validDirectories[] = $directory;
            } else {
                echo "Skipping invalid directory: $directory (No XML file found)\n";
            }
        }

        return $validDirectories;
    }

    public function getPublicUrl(string $imageKey)
    {
        return "https://{$this->bucket}.s3.amazonaws.com/{$imageKey}";
    }
}
