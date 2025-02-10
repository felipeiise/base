<?php

declare(strict_types=1);

namespace SpringCourier;

use Exception;
use InvalidArgumentException;

class SpringCourier
{
    // I would not hardcode the `apiUrl` and `apiKey` directly into the code.
    // However, the task description emphasizes the KISS (Keep It Simple, Stupid) principle.
    // Otherwise, I would use the `dotenv` package (installed via Composer) to load these variables
    // from `.env` files, which can be configured separately for each environment.
    private string $apiUrl = 'https://mtapi.net/?testMode=1';
    private string $apiKey = 'f16753b55cac6c6e';

    //Array representing table in Page 8 of XBS API manual
    //2.1.1 Label character limit and service validations
    //Instead of hardcoding the service configurations here, it would be more maintainable
    //and scalable to fetch this data from a database. The data can then be cached in memory
    //(e.g., using Redis or Memcached) to improve performance and reduce database load.
    //This approach allows for dynamic updates to service configurations without requiring
    //code changes and ensures that the application remains flexible and efficient.
    private array $services = [
        'PPLEU' => ['soft' => 35, 'hard' => ''],
        'PPLGE/GU' => ['soft' => 50, 'hard' => ''],
        'RM24/48(S)' => ['soft' => 30, 'hard' => 35],
        'PPTT' => ['soft' => 30, 'hard' => ''],
        'PPTR/NT' => ['soft' => 30, 'hard' => ''],
        'SEND(2)' => ['soft' => 35, 'hard' => ''],
        'ITCR' => ['soft' => 60, 'hard' => ''],
        //'HEHDS' => ['soft' => '', 'hard' => 'Validation'], // What this validation means?
        'SC' => ['soft' => 35, 'hard' => ''],
        'PPND' => ['soft' => 35, 'hard' => ''],
    ];

    public function newPackage(array $order, array $params): array
    {
        $requestData = [
            'Apikey' => $params['api_key'],
            'Command' => 'OrderShipment',
            'Shipment' => array_merge($order, [
                'LabelFormat' => $params['label_format'] ?? 'PDF',
                'Service' => $params['service'] ?? 'PPTT',
            ])
        ];

        return $this->sendRequest($requestData);
    }

    public function packagePdf(string $trackingNumber): void
    {
        $requestData = [
            'Apikey' => $this->apiKey,
            'Command' => 'GetShipmentLabel',
            'Shipment' => ['TrackingNumber' => $trackingNumber],
        ];

        $response = $this->sendRequest($requestData);

        if ($response['ErrorLevel'] !== 0) {
            echo 'Error: ' . ($response['Error'] ?? 'Unknown error');
            return;
        }

        header('Content-Type: application/pdf');
        echo base64_decode($response['Shipment']['LabelImage']);
    }

    private function sendRequest(array $data): array
    {
        try {
            $ch = curl_init($this->apiUrl);
            if ($ch === false) {
                throw new Exception('cURL initialization failed.');
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new Exception('cURL error: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new Exception('HTTP error: ' . $httpCode);
            }

            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from API.');
            }

            return $decodedResponse;
        } catch (Exception $e) {
            return ['Error' => $e->getMessage(), 'ErrorLevel' => 10];
        }
    }

    public function splitAddress($address, $serviceKey): array
    {
        // Trim and clean the address
        $address = trim(preg_replace('/\s+/', ' ', $address));

        // Check if the address is empty
        if (empty($address)) {
            return ['error' => 'Address cannot be empty.'];
        }

        // Get the soft and hard limits for the selected service
        if (!isset($this->services[$serviceKey])) {
            throw new InvalidArgumentException("Service key '$serviceKey' not found.");
        }

        $softLimit = $this->services[$serviceKey]['soft'] ?? null;
        $hardLimit = $this->services[$serviceKey]['hard'] ?? null;

        // Check if the hard limit is set and if the address exceeds the total hard limit
        if (!empty($hardLimit)) {
            $totalHardLimit = $hardLimit * 3;
            if (strlen($address) > $totalHardLimit) {
                return ['error' => 'Address exceeds the total hard limit. Please shorten the address.'];
            }
            $lines = $this->splitByHardLimit($address, $hardLimit);
        } else {
            $lines = $this->splitBySoftLimit($address, $softLimit);
        }

        return array_filter([
            'AddressLine1' => $lines[0] ?? '',
            'AddressLine2' => $lines[1] ?? '',
            'AddressLine3' => $lines[2] ?? ''
        ], function ($value) {
            return !empty($value);
        });
    }

    private function splitBySoftLimit($address, $softLimit): array
    {
        $lines = [];
        $remainingAddress = $address;

        for ($i = 0; $i < 3; $i++) {
            if (strlen($remainingAddress) <= $softLimit) {
                $lines[] = $remainingAddress;
                break;
            }

            // Find the next space after the soft limit
            $splitPos = $this->findNextSpace($remainingAddress, $softLimit);

            // If no space is found, split at the soft limit
            if ($splitPos === false) {
                $splitPos = $softLimit;
            }

            $lines[] = substr($remainingAddress, 0, $splitPos);
            $remainingAddress = trim(substr($remainingAddress, $splitPos));
        }

        // Ensure we have exactly 3 lines
        while (count($lines) < 3) {
            $lines[] = '';
        }

        return $lines;
    }

    private function splitByHardLimit($address, $hardLimit): array
    {
        $lines = [];
        $remainingAddress = $address;

        for ($i = 0; $i < 3; $i++) {
            if (strlen($remainingAddress) <= $hardLimit) {
                $lines[] = $remainingAddress;
                break;
            }

            // Find the previous space before the hard limit
            $splitPos = $this->findPreviousSpace($remainingAddress, $hardLimit);

            // If no space is found, split at the hard limit
            if ($splitPos === false) {
                $splitPos = $hardLimit;
            }

            $lines[] = substr($remainingAddress, 0, $splitPos);
            $remainingAddress = trim(substr($remainingAddress, $splitPos));
        }

        // Ensure we have exactly 3 lines
        while (count($lines) < 3) {
            $lines[] = '';
        }

        return $lines;
    }

    private function findNextSpace($address, $limit): false|int
    {
        $pos = strpos($address, ' ', $limit);
        return $pos === false ? false : $pos;
    }

    private function findPreviousSpace($address, $limit): false|int
    {
        $substring = substr($address, 0, $limit);
        $pos = strrpos($substring, ' ');
        return $pos === false ? false : $pos;
    }
}