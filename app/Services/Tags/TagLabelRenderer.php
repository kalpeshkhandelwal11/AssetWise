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
                // Fall back to the scan URL derived from tag_number when qr_payload is missing
                // (mirrors TagService generation) so a tag created outside the batch generator
                // — e.g. a hand-seeded one — can't crash the whole label sheet.
                $payload = $tag->qr_payload ?: url("/scan/{$tag->tag_number}");

                return [
                    'tag'   => $tag,
                    'image' => base64_encode(QrCode::size(150)->generate($payload)),
                    'mime'  => 'image/svg+xml',
                ];
            }

            return [
                'tag'   => $tag,
                'image' => base64_encode($barcodeGenerator->getBarcode($tag->barcode_value ?: $tag->tag_number, $barcodeGenerator::TYPE_CODE_128)),
                'mime'  => 'image/png',
            ];
        });
    }
}
