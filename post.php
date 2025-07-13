<?php
$xmlFile = 'envelope.xml';
$xmlData = file_get_contents($xmlFile);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://xmlgw.companieshouse.gov.uk/v1-0/xmlgw/Gateway');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: text/xml',
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'cURL error: ' . curl_error($ch);
} else {
    echo "Response from Companies House:\n\n";
    echo htmlspecialchars($response);
}

curl_close($ch);
?>
