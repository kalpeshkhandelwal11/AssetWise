<?php

namespace App\Services\Tags;

use App\Models\Tag;
use Illuminate\Support\Collection;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Renders one base64-encoded image per tag — QR or barcode, per each tag's own
 * snapshotted code_type — for embedding into the printable PDF and Word label sheets.
 *
 * QR codes render as SVG, not PNG: bacon/bacon-qr-code (simplesoftwareio/simple-qrcode's
 * backend) only ships Svg/Eps/Imagick image back ends — there is no GD-based PNG option —
 * and `imagick` is deliberately not in this project's required PHP extension list
 * (CLAUDE.md's Local Development Setup section). Barcodes render as PNG via GD, which
 * picqer/php-barcode-generator uses by default and which is a required extension.
 */
class TagLabelRenderer
{
    /**
     * @param  Collection<int, Tag>  $tags
     * @return Collection<int, array{tag: Tag, image: string, mime: string}>
     */
    public function render(Collection $tags): Collection
    {
        $barcodeGenerator = new BarcodeGeneratorPNG();

        return $tags->map(function (Tag $tag) use ($barcodeGenerator) {
            if ($tag->code_type === 'qr') {
                return [
                    'tag'   => $tag,
                    'image' => base64_encode(QrCode::size(150)->generate($tag->qr_payload)),
                    'mime'  => 'image/svg+xml',
                ];
            }

            return [
                'tag'   => $tag,
                'image' => base64_encode($barcodeGenerator->getBarcode($tag->barcode_value, $barcodeGenerator::TYPE_CODE_128)),
                'mime'  => 'image/png',
            ];
        });
    }
}
