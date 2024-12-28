<?php
// topluSorgu.php

require 'vendor/autoload.php'; // PhpSpreadsheet (composer ile kurulmuş olmalı)
use PhpOffice\PhpSpreadsheet\IOFactory;

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
  <meta charset='utf-8' />
  <title>Toplu T.C. Kimlik Sonuçları</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f4;
      margin: 0; 
      padding: 0;
    }
    .container {
      max-width: 800px;
      margin: 2rem auto;
      background: #fff;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h1 {
      text-align: center;
      margin-bottom: 1.5rem;
      color: #333;
    }
    table.result-table {
      border-collapse: collapse;
      width: 100%;
    }
    table.result-table th,
    table.result-table td {
      border: 1px solid #ccc;
      padding: 10px;
      text-align: left;
    }
    table.result-table thead th {
      background: #444;
      color: #fff;
    }
    tr.success-row {
      background-color: #d4edda; /* açık yeşil */
    }
    tr.fail-row {
      background-color: #f8d7da; /* açık kırmızı */
    }
    tr.warn-row {
      background-color: #fff3cd; /* açık turuncu */
    }
  </style>
</head>
<body>
<div class='container'>
";

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    exit("<h2>Dosya yüklenirken hata oluştu veya dosya yok!</h2></div></body></html>");
}

$tmpFilePath = $_FILES['excelFile']['tmp_name'];
$spreadsheet = IOFactory::load($tmpFilePath);
$worksheet = $spreadsheet->getActiveSheet();

$highestRow = $worksheet->getHighestRow();

echo "<h1>Toplu Sorgu Sonuçları</h1>";
echo "<table class='result-table'>
      <thead>
        <tr>
          <th>tcNo</th>
          <th>ad</th>
          <th>soyad</th>
          <th>dogumTarihi</th>
          <th>Durum</th>
        </tr>
      </thead>
      <tbody>";

// Satır satır oku (2. satırdan itibaren, 1. satır başlık)
for ($row = 2; $row <= $highestRow; $row++) {
    // Yeni isimlendirme: A=tcNo, B=ad, C=soyad, D=dogumTarihi
    $tcNo         = trim($worksheet->getCell("A{$row}")->getValue());
    $ad           = trim($worksheet->getCell("B{$row}")->getValue());
    $soyad        = trim($worksheet->getCell("C{$row}")->getValue());
    $dogumTarihi  = trim($worksheet->getCell("D{$row}")->getValue());

    // dogumTarihi içinden 4 basamaklı yılı çıkaracağız:
    $dogumYili = null;
    if (preg_match('/(\d{4})/', $dogumTarihi, $m)) {
        $dogumYili = $m[1];  // 1994, vs.
    }

    // SOAP sorgusu
    $sonuc = tcKimlikSorgula($tcNo, $ad, $soyad, $dogumYili);

    $rowClass = "";
    $durumYazisi = "";

    if ($sonuc === "true") {
        $rowClass = "success-row";
        $durumYazisi = "Doğrulandı";
    } elseif ($sonuc === "false") {
        $rowClass = "fail-row";
        $durumYazisi = "Geçersiz";
    } else {
        $rowClass = "warn-row";
        $durumYazisi = "Hata/Boş";
    }

    echo "<tr class='{$rowClass}'>
            <td>{$tcNo}</td>
            <td>{$ad}</td>
            <td>{$soyad}</td>
            <td>{$dogumTarihi}</td>
            <td>{$durumYazisi}</td>
          </tr>";
}

echo "</tbody></table>";
echo "</div></body></html>";


// SOAP fonksiyonu
function tcKimlikSorgula($tcNo, $ad, $soyad, $dogumYili) {
    if (!$dogumYili) {
        // Yıl yoksa hata
        return null;
    }

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

    $ch = curl_init("https://tckimlik.nvi.gov.tr/service/kpspublic.asmx");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $soapRequest);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: text/xml; charset=utf-8",
        "SOAPAction: http://tckimlik.nvi.gov.tr/WS/TCKimlikNoDogrula"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return null;
    }

    if (preg_match('/<TCKimlikNoDogrulaResult>(.*?)<\/TCKimlikNoDogrulaResult>/', $response, $matches)) {
        return $matches[1]; // "true" veya "false"
    }
    return null;
}
