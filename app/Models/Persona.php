<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Persona extends Model implements HasMedia
{
    use InteractsWithMedia, HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'about_description',
        'system_prompt',
        'appearance_description',
        'physical_traits',
        'gender',
        'wake_time',
        'sleep_time',
        'voice_frequency',
        'image_frequency',
        'is_active',
        'telegram_bot_token',
        'telegram_bot_username',
    ];

    protected $casts = [
        'wake_time' => 'string',
        'sleep_time' => 'string',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memoryTags(): HasMany
    {
        return $this->hasMany(MemoryTag::class);
    }

    public function eventSchedules(): HasMany
    {
        return $this->hasMany(EventSchedule::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function wardrobeItems(): HasMany
    {
        return $this->hasMany(WardrobeItem::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('reference_image')
            ->useDisk('public')
            ->singleFile();

        $this->addMediaCollection('avatar')
            ->useDisk('public')
            ->singleFile();

        $this->addMediaCollection('generated_images')
            ->useDisk('public');

        $this->addMediaCollection('voice_notes')
            ->useDisk('public');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(300)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('large')
            ->width(1200)
            ->height(900)
            ->sharpen(10)
            ->nonQueued();
    }

    /**
     * Get publicly accessible URL for media (for external APIs)
     *
     * In development: Uses PUBLIC_MEDIA_URL (ngrok tunnel)
     * In production: Uses PUBLIC_MEDIA_URL (actual domain/CDN)
     */
    public function getPublicMediaUrl(string $collection = 'avatar'): ?string
    {
        $media = $this->getMedia($collection)->first();

        if (!$media) {
            return null;
        }

        $publicBaseUrl = config('app.public_media_url');

        // If PUBLIC_MEDIA_URL is configured, use it instead of local URL
        if ($publicBaseUrl) {
            // Get the path relative to the public disk (e.g., "88/file.png")
            // Media is stored in storage/app/public/{id}/{filename}
            $relativePath = $media->id . '/' . $media->file_name;
            return rtrim($publicBaseUrl, '/') . '/storage/' . $relativePath;
        }

        // Fallback to local URL (only works in same network)
        return $media->getUrl();
    }
}
