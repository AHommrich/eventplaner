<?php

namespace App\Services;

use RuntimeException;

/**
 * Re-encodes uploaded photos so EXIF metadata (including GPS coordinates,
 * device model, original timestamps, IPTC, XMP) is stripped before the file
 * leaves the app server and lands on Hetzner Object Storage.
 *
 * Implementation talks to Imagick / GD directly rather than going through
 * Intervention/Image: Intervention's decoder chain attaches EXIF to the image
 * object and can carry it into the output stream depending on segment order,
 * defeating the whole point of a sanitizer. Doing the strip explicitly gives
 * a hard guarantee that matches the "EXIF stripping" claim in the privacy
 * policy and docs/legal/sub-processors.md.
 *
 * Driver selection:
 *  - Imagick when the extension is loaded (production has it; also supports
 *    HEIC/HEIF). `stripImage()` removes EXIF, IPTC, XMP and all other profiles.
 *  - GD as the universal fallback. GD's `imagejpeg()` writes a fresh JPEG
 *    with only a JFIF APP0 header and its own `gd-jpeg` COM comment — it has
 *    no mechanism to embed EXIF, IPTC or XMP.
 *
 * All photo upload paths (organizer Inertia + guest API + event-cover +
 * photo-game submissions) route through this class so the EXIF-removal
 * guarantee is uniform.
 */
class PhotoSanitizer
{
    /** JPEG quality used when re-encoding. 90 is a good balance for photos. */
    public const JPEG_QUALITY = 90;

    /**
     * Re-encode the file at the given absolute path to a JPEG byte string with
     * all metadata removed. Works for JPEG and PNG everywhere; HEIC/HEIF when
     * Imagick is available.
     */
    public function toJpegWithoutExif(string $absolutePath): string
    {
        return extension_loaded('imagick')
            ? $this->sanitizeWithImagick($absolutePath)
            : $this->sanitizeWithGd($absolutePath);
    }

    private function sanitizeWithImagick(string $absolutePath): string
    {
        $imagick = new \Imagick($absolutePath);
        $imagick->stripImage();
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(self::JPEG_QUALITY);

        $blob = $imagick->getImageBlob();
        $imagick->clear();

        return $blob;
    }

    private function sanitizeWithGd(string $absolutePath): string
    {
        $raw = @file_get_contents($absolutePath);
        if ($raw === false) {
            throw new RuntimeException("PhotoSanitizer: cannot read {$absolutePath}");
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            throw new RuntimeException('PhotoSanitizer: GD cannot decode the given image');
        }

        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);
        $blob = ob_get_clean();
        imagedestroy($image);

        if ($blob === false || $blob === '') {
            throw new RuntimeException('PhotoSanitizer: GD failed to encode JPEG output');
        }

        return $blob;
    }
}
