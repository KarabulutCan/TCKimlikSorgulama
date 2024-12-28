<?php
// topluSorgu.php

require 'vendor/autoload.php'; // PhpSpreadsheet dahil (composer ile kurulmuş olmalı)


use PhpOffice\PhpSpreadsheet\IOFactory;

// HTML başlık
echo "<!DOCTYPE html><html><head><meta charset='utf-8' /></head><body>";

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    exit("Dosya yüklenirken hata oluştu veya dosya yok!");
}

// 1) Geçici dizinden dosyayı al
$tmpFilePath = $_FILES['excelFile']['tmp_name'];

// 2) PhpSpreadsheet ile oku
$spreadsheet = IOFactory::load($tmpFilePath);
$worksheet = $spreadsheet->getActiveSheet(); 
$highestRow = $worksheet->getHighestRow(); // Kaç satır var

echo "<h1>Toplu Sorgu Sonuçları</h1>";
echo "<table class='result-table'>";
echo "<tr>
        <th>T.C. Kimlik</th>
        <th>Ad</th>
        <th>Soyad</th>
        <th>Doğum Yılı</th>
        <th>Durum</th>
      </tr>";

// 3) Satır satır oku (2'den başlıyoruz, 1. satır başlık)
for ($row = 2; $row <= $highestRow; $row++) {
    $tcNo = $worksheet->getCell("A{$row}")->getValue();      
    $ad = $worksheet->getCell("B{$row}")->getValue();        
    $soyad = $worksheet->getCell("C{$row}")->getValue();     
    $dogumYili = $worksheet->getCell("D{$row}")->getValue(); 

    // 4) Sorgu yap
    $sonuc = tcKimlikSorgula($tcNo, $ad, $soyad, $dogumYili);

    if ($sonuc === "true") {
        $durumYazisi = "<span class='success'>Doğrulandı</span>";
    } elseif ($sonuc === "false") {
        $durumYazisi = "<span class='fail'>Geçersiz</span>";
    } else {
        $durumYazisi = "<span class='fail'>Hata/Boş</span>";
    }

    echo "<tr>
            <td>{$tcNo}</td>
            <td>{$ad}</td>
            <td>{$soyad}</td>
            <td>{$dogumYili}</td>
            <td>{$durumYazisi}</td>
          </tr>";
}

echo "</table>";
echo "</body></html>";


// ------------------------------------------------
// Tekil SOAP sorgu fonksiyonu (dogrula.php'deki mantık)
function tcKimlikSorgula($tcNo, $ad, $soyad, $dogumYili) {
    // Boşlukları temizle
    $tcNo = trim($tcNo);
    $ad = trim($ad);
    $soyad = trim($soyad);
    $dogumYili = trim($dogumYili);

    // SOAP isteği
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

    // cURL
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
        // cURL hatası
        return null;
    }

    // Yanıttaki <TCKimlikNoDogrulaResult> tagını bul
    if (preg_match('/<TCKimlikNoDogrulaResult>(.*?)<\/TCKimlikNoDogrulaResult>/', $response, $m)) {
        return $m[1]; // "true" veya "false"
    }

    return null;
}
