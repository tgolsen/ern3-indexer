<?php

namespace Tgolsen\Ern3Indexer;

use Predis\Client as RedisClient;
use Exception;

class ItemProcessor
{
    private $s3Handler;
    private $redisClient;
    private $solrUrl;

    public function __construct(S3Handler $s3Handler, RedisClient $redisClient, string $solrUrl)
    {
        $this->s3Handler = $s3Handler;
        $this->redisClient = $redisClient;
        $this->solrUrl = $solrUrl;
    }

    public function processItem(string $bucket,string $batchId,string $itemId): void
    {
        try {
            $successKey = "success:{$batchId}:{$itemId}";
            $failureKey = "failure:{$batchId}:{$itemId}";

            if ($this->redisClient->exists($successKey) || $this->redisClient->exists($failureKey)) {
                echo "Skipping item: $itemId (Already processed in batch $batchId)\n";
                return;
            }

            // Formulate the path to the file based on item directory and ID
            $xmlFile = "$batchId/$itemId/$itemId.xml";

            // Use fileExists instead of doesObjectExist
            if (!$this->s3Handler->fileExists($xmlFile)) {
                throw new Exception("Missing XML file: $xmlFile");
            }

            $xmlContent = $this->s3Handler->getObjectContent($xmlFile);
            $jsonData = $this->convertXmlToJson($xmlContent, $bucket, $batchId, $itemId);

            echo "Submitting item: $itemId in batch $batchId to Solr...\n";
            $response = $this->submitToSolr($jsonData);

            if ($response['success']) {
                echo "Successfully processed item: $itemId in batch $batchId\n";
                $this->redisClient->set($successKey, true);
            } else {
                var_dump($response);
                die;
                throw new Exception("Failed to submit item: $itemId in batch $batchId");
            }
        } catch (Exception $e) {
            echo "Error processing item: {$e->getMessage()}\n";
            $this->redisClient->set($failureKey, true);
        }
    }

    private function convertXmlToJson(string $xmlContent, string $bucket, string $batchId, string $itemId): array
    {
        try {
            // Parse XML content
            $xml = new \SimpleXMLElement($xmlContent);

            // Extract "MessageHeader" data
            $messageHeader = $xml->MessageHeader;
            $messageId = (string)$messageHeader->MessageId;
            $createdDateTime = (string)$messageHeader->MessageCreatedDateTime;

            // Format the timestamp to ISO 8601 (for Solr)
            $timestamp = null;
            if (!empty($createdDateTime)) {
                try {
                    $timestamp = (new \DateTime($createdDateTime))->format('Y-m-d\TH:i:s\Z');
                } catch (\Exception $e) {
                    throw new \Exception("Invalid MessageCreatedDateTime format: $createdDateTime");
                }
            }

            // Extract first "SoundRecording" (assuming one resource per item)
            $soundRecording = $xml->ResourceList->SoundRecording ?? null;
            if (!$soundRecording) {
                throw new \Exception("No SoundRecording found in the XML.");
            }

            $isrc = (string)$soundRecording->SoundRecordingId->ISRC ?? null;
            $title = (string)$soundRecording->ReferenceTitle->TitleText ?? null;
            $duration = (string)$soundRecording->Duration ?? null;

            // Extract "DisplayArtist" details
            $artistName = null;
            if (isset($soundRecording->SoundRecordingDetailsByTerritory->DisplayArtist->PartyName->FullName)) {
                $artistName = (string)$soundRecording->SoundRecordingDetailsByTerritory->DisplayArtist->PartyName->FullName;
            }

            // Extract "Genre" and "SubGenre"
            $genre = (string)$soundRecording->SoundRecordingDetailsByTerritory->Genre->GenreText ?? null;
            $subGenre = (string)$soundRecording->SoundRecordingDetailsByTerritory->Genre->SubGenre ?? null;

            // Extract "LabelName"
            $labelName = (string)$soundRecording->SoundRecordingDetailsByTerritory->LabelName ?? null;

            // Extract information for "image_url" from XML
            $imageNode = $xml->ResourceList->Image ?? null;
            $imageUrl = null;

            if ($imageNode) {
                // Extract image file name and path
                if (isset($imageNode->ImageDetailsByTerritory->TechnicalImageDetails->File)) {
                    $imageFileName = (string)$imageNode->ImageDetailsByTerritory->TechnicalImageDetails->File->FileName ?? null;
                    $imageFilePath = (string)$imageNode->ImageDetailsByTerritory->TechnicalImageDetails->File->FilePath ?? '';

                    if ($imageFileName) {
                        // Construct full image path and verify its existence in the S3 bucket
                        $imageKey = "$batchId/$itemId/$imageFilePath$imageFileName";
                        if ($this->s3Handler->fileExists($imageKey)) {
                            // Get the actual public URL of the image
                            $imageUrl = $this->s3Handler->getPublicUrl($imageKey);
                        }
                    }
                }
            }

            // Extract "keywords" (if applicable, assume placeholders for now)
            $keywords = []; // Add logic if schema specifies keywords in future

            // Structure the JSON object for Solr submission
            $jsonData = [
                'id' => $messageId,                                   // Message ID as unique Solr document ID
                'src_id' => $itemId,                                  // SRC ID derived from directory structure
                'title' => $title,                                    // Title of the sound recording
                'artist_name' => $artistName,                         // Display artist's full name
                'isrc' => $isrc,                                      // ISRC identifier for the recording
                'genre' => $genre,                                    // Genre (e.g., Pop)
                'sub_genre' => $subGenre,                             // Sub-genre (e.g., Mandopop)
                'label_name' => $labelName,                           // Publisher or label (e.g., Music Stream)
                'duration' => $duration,                              // Duration of the track
                'keywords' => $keywords,                              // Keywords (currently empty)
                'batch_id' => $batchId,                               // Batch ID derived from directory structure
                'item_directory' => "$batchId/$itemId",               // Directory path for reference
                'timestamp' => $timestamp,                            // Solr-compatible ISO 8601 timestamp
            ];

            // Add image_url only if it exists
            if ($imageUrl !== null) {
                $jsonData['image_url'] = $imageUrl;
            }

            return $jsonData;

        } catch (\Exception $e) {
            // Handle errors and ensure a proper exception is thrown
            throw new \Exception("Failed to convert XML to JSON: " . $e->getMessage());
        }
    }

    private function submitToSolr(array $jsonData): array
    {
        try {
            // Initialize a cURL session to submit to Solr
            $ch = curl_init();

            // Convert JSON data into a JSON string
            $payload = json_encode([$jsonData]); // Wrap in an array if Solr expects an array of documents

            curl_setopt($ch, CURLOPT_URL, $this->solrUrl . "/update?commit=true");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true];
            } else {
                return [
                    'success' => false,
                    'error' => $response,
                    'code' => $httpCode,
                ];
            }
        } catch (\Exception $e) {
            throw new \Exception("Failed to submit data to Solr: " . $e->getMessage());
        }
    }
}
