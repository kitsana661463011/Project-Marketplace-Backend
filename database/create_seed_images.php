<?php

$dir = __DIR__ . '/../storage/images/';
$sampleImage = $dir . '1785267428_qrcode.png';

if (!file_exists($sampleImage)) {
    // Find any png file > 1000 bytes
    foreach (glob($dir . '*.png') as $f) {
        if (filesize($f) > 1000) {
            $sampleImage = $f;
            break;
        }
    }
}

if (file_exists($sampleImage)) {
    $needed = [
        'slip_booking_01.png',
        'slip_booking_02.png',
        'slip_booking_03.png',
        'slip_booking_04.png',
        'slip_booking_05.png',
        'slip_refund_05.png',
        'slip_refund_01.png',
        'slip_refund_02.png',
        'slip_refund_03.png',
        'slip_refund_04.png',
    ];

    foreach ($needed as $file) {
        $target = $dir . $file;
        if (!file_exists($target) || filesize($target) < 100) {
            copy($sampleImage, $target);
            echo "Copied demo image to: $file\n";
        }
    }
} else {
    echo "Sample image not found.\n";
}
