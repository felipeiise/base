<?php

require 'SpringCourier.php';

use SpringCourier\SpringCourier;

$shipment = new SpringCourier();

$params = [
    'api_key' => 'f16753b55cac6c6e',
    'label_format' => 'PDF',
    'service' => 'PPTT',
];

$addressLines['ConsignorAddress'] = $shipment->splitAddress(
    'Kopernika 10',
    $params['service']
);

$addressLines['ConsigneeAddress'] = $shipment->splitAddress(
    'Strada Foisorului, Nr. 16, Bl. F11C, Sc. 1, Ap. 10',
    $params['service']
);

$order = [
    'ConsignorAddress' => [
        'Name' => 'Jan Kowalski',
        'Company' => 'BaseLinker',
        'City' => 'Gdansk',
        'Zip' => '80208',
        'Phone' => '666666666',
        'Email' => '',
    ],
    'ConsigneeAddress' => [
        'Name' => 'Maud Driant',
        'Company' => 'Spring GDS',
        'City' => 'Bucuresti, Sector 3',
        'Zip' => '031179',
        'Country' => 'RO',
        'Phone' => '555555555',
        'Email' => 'john@doe.com',
    ],
    'Weight' => 1.2,
    'Value' => 100,
];

$order = array_merge_recursive($order, $addressLines);

$response = $shipment->newPackage($order, $params);

if ($response['ErrorLevel'] === 0) {
    $shipment->packagePdf($response['Shipment']['TrackingNumber']);
} else {
    echo 'Error: ' . ($response['Error'] ?? 'Unknown error');
}