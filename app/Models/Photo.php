<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    protected $fillable = ['event_id', 'album_id', 'guest_id', 'uploaded_by', 'uploader_user_id', 'uploader_role', 'url', 'r2_key', 'description'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function album()
    {
        return $this->belongsTo(PhotoAlbum::class, 'album_id');
    }

    public function gameAssignment()
    {
        return $this->hasOne(PhotoGameAssignment::class, 'photo_id');
    }

    /**
     * Object key of this photo's blob on the "s3" disk. Legacy rows (pre-r2_key
     * uploads) have no r2_key, so the key is derived from the URL — but only when
     * the URL points at this disk's own public host. Rows pointing elsewhere
     * (another bucket, picsum demo data) yield null and are never touched.
     */
    public function storageKey(): ?string
    {
        return $this->r2_key ?: self::keyFromDiskUrl($this->url);
    }

    public static function keyFromDiskUrl(?string $url): ?string
    {
        $base = rtrim((string) config('filesystems.disks.s3.url'), '/');

        if (! $url || $base === '' || ! str_starts_with($url, $base.'/')) {
            return null;
        }

        $key = rawurldecode(strtok(substr($url, strlen($base) + 1), '?'));

        return $key === '' ? null : $key;
    }
}
