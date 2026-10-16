<?php

namespace App\Services;

use App\Enums\MessageType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    /**
     * Stores the file on the private `local` disk under a UUID filename
     * (never the user-supplied original name) and returns a plain attributes
     * array ready for MessageAttachment::create().
     *
     * @return array<string, mixed>
     */
    public function store(UploadedFile $file, string $conversationUuid): array
    {
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension());
        $type = $this->classify($extension, $file->getMimeType());

        $path = $file->storeAs("chat-attachments/{$conversationUuid}", "{$uuid}.{$extension}", 'local');

        $attributes = [
            'uuid' => $uuid,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => $type,
            'thumbnail_path' => null,
            'width' => null,
            'height' => null,
            'duration' => null,
        ];

        if ($type === MessageType::Image) {
            $fullPath = Storage::disk('local')->path($path);
            $dimensions = @getimagesize($fullPath);

            if ($dimensions) {
                $attributes['width'] = $dimensions[0];
                $attributes['height'] = $dimensions[1];
            }

            $attributes['thumbnail_path'] = $this->generateThumbnail($fullPath, $conversationUuid, $uuid);
        }

        return $attributes;
    }

    private function classify(string $extension, ?string $mimeType = null): MessageType
    {
        if ($mimeType && str_starts_with($mimeType, 'audio/')) {
            return MessageType::Audio;
        }

        if ($mimeType && str_starts_with($mimeType, 'video/')) {
            return MessageType::Video;
        }

        if ($mimeType && str_starts_with($mimeType, 'image/')) {
            return MessageType::Image;
        }

        foreach (config('chat.mimes') as $group => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return MessageType::from($group);
            }
        }

        return MessageType::Document;
    }

    /**
     * GD-based resize — no image-processing package is installed, but ext-gd
     * is available. Video poster frames would need ffmpeg, which isn't
     * installed, so this only ever runs for images.
     */
    private function generateThumbnail(string $fullPath, string $conversationUuid, string $uuid): ?string
    {
        $imageInfo = @getimagesize($fullPath);

        if (! $imageInfo) {
            return null;
        }

        [$width, $height, $imageType] = $imageInfo;

        $source = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($fullPath),
            IMAGETYPE_PNG => @imagecreatefrompng($fullPath),
            IMAGETYPE_GIF => @imagecreatefromgif($fullPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : null,
            default => null,
        };

        if (! $source) {
            return null;
        }

        $maxDimension = config('chat.thumbnail.max_dimension', 320);
        $ratio = min(1, $maxDimension / max($width, $height));
        $thumbWidth = max(1, (int) round($width * $ratio));
        $thumbHeight = max(1, (int) round($height * $ratio));

        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

        $thumbnailPath = "chat-attachments/{$conversationUuid}/thumbnails/{$uuid}.jpg";
        $thumbnailFullPath = Storage::disk('local')->path($thumbnailPath);

        if (! is_dir(dirname($thumbnailFullPath))) {
            mkdir(dirname($thumbnailFullPath), 0755, true);
        }

        imagejpeg($thumbnail, $thumbnailFullPath, config('chat.thumbnail.quality', 75));

        imagedestroy($source);
        imagedestroy($thumbnail);

        return $thumbnailPath;
    }
}
