<?php

namespace App\Support;

use App\Models\Ticket;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

final class BoardingQr
{
    private static function code(Ticket $ticket): QrCode
    {
        return new QrCode(
            data: route('tickets.verify', $ticket->boarding_token),
            encoding: new Encoding('ISO-8859-1'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 260,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(16, 53, 47),
            backgroundColor: new Color(255, 255, 255),
        );
    }

    public static function png(Ticket $ticket): string
    {
        return (new PngWriter)->write(self::code($ticket))->getString();
    }

    public static function svg(Ticket $ticket): string
    {
        return (new SvgWriter)->write(self::code($ticket))->getString();
    }

    public static function dataUri(Ticket $ticket): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($ticket));
    }
}
