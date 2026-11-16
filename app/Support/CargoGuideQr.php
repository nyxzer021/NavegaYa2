<?php

namespace App\Support;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class CargoGuideQr
{
    public static function dataUri(string $contents): string
    {
        $code = new QrCode(
            data: $contents,
            encoding: new Encoding('ISO-8859-1'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 260,
            margin: 8,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(16, 53, 47),
            backgroundColor: new Color(255, 255, 255),
        );

        return 'data:image/png;base64,'.base64_encode((new PngWriter)->write($code)->getString());
    }
}
