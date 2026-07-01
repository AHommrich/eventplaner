<?php

namespace App\Services;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * Re-encodes uploaded photos through Intervention/Image so EXIF metadata
 * (including GPS coordinates, device model, original timestamps) is stripped
 * before the file lands on R2.
 *
 * Intervention/Image rebuilds the image from raw pixel data — the resulting
 * JPEG byte stream carries no EXIF, IPTC, or XMP segments. This satisfies the
 * "EXIF stripping" requirement noted as a known gap in
 * docs/legal/sub-processors.md and the privacy policy.
 *
 * Driver selection:
 *  - Imagick when the extension is available (production has it; supports HEIC/HEIF).
 *  - GD as the universal fallback (always present in standard PHP) — handles
 *    JPEG and PNG. HEIC inputs are already converted to JPEG client-side in
 *    the upload flow before they reach the server, so GD is sufficient there.
 *
 * All photo upload paths (organizer Inertia + guest API + event-cover upload)
 * should run through this class so the EXIF-removal guarantee is uniform.
 */
class PhotoSanitizer
{
    /** JPEG quality used when re-encoding. 90 is a good balance for photos. */
    public const JPEG_QUALITY = 90;

    /**
     * Re-encode the file at the given absolute path to a JPEG byte string with
     * EXIF removed. Works for JPEG and PNG everywhere; HEIC/HEIF when Imagick
     * is available.
     */
    public function toJpegWithoutExif(string $absolutePath): string
    {
        $driver = extension_loaded('imagick') ? new ImagickDriver : new GdDriver;
        $manager = new ImageManager($driver);

        return (string) $manager->read($absolutePath)->toJpeg(self::JPEG_QUALITY);
    }
}
