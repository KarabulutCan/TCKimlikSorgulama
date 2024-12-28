<?php
// dogrula.php

header('Content-Type: application/json; charset=utf-8');

// 1) JSON verisini al
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!isset($data['tcNo'], $data['ad'], $data['soyad'], $data['dogumYili'])) {
    echo json_encode(["error" => "Eksik parametreler (tcNo, ad, soyad, dogumYili)"]);
    exit;
}

$tcNo = $data['tcNo'];
$ad = $data['ad'];
$soyad = $data['soyad'];
$dogumYili = $data['dogumYili'];

// 2) SOAP XML Gövdesi Hazırla
$soapRequest = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope 
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
    xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TCKimlikNoDogrula xmlns="http://tckimlik.nvi.gov.tr/WS">
      <TCKimlikNo>{$tcNo}</TCKimlikNo>
      <Ad>{$ad}</Ad>
      <Soyad>{$soyad}</Soyad>
      <DogumYili>{$dogumYili}</DogumYili>
    </TCKimlikNoDogrula>
  </soap:Body>
</soap:Envelope>
XML;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://tckimlik.nvi.gov.tr/service/kpspublic.asmx");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $soapRequest);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: text/xml; charset=utf-8",
    "SOAPAction: http://tckimlik.nvi.gov.tr/WS/TCKimlikNoDogrula"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// (Gerekiyorsa SSL doğrulamasını devre dışı bırakabilirsiniz.)
// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(["error" => "cURL Error: " . $err]);
    exit;
}

$sonuc = null;
if (preg_match('/<TCKimlikNoDogrulaResult>(.*?)<\/TCKimlikNoDogrulaResult>/', $response, $matches)) {
    $sonuc = $matches[1]; // "true" veya "false"
}

echo json_encode(["sonuc" => $sonuc]);
exit;
