# Image Generator Drivers

This application supports multiple image generation drivers through the `ImageGeneratorInterface`.

## Available Drivers

### 1. KieAiTextToImageDriver (Default)
**Model**: `bytedance/seedream-v4-text-to-image`

Generates images from text prompts only.

**Usage:**
```php
use App\Services\ImageGeneratorManager;

$manager = new ImageGeneratorManager();
$driver = $manager->driver('kie_ai_text_to_image');
$imageUrl = $driver->generate($prompt, $persona);
```

**Configuration:**
```env
SERVICES_IMAGE_GENERATOR_DEFAULT=kie_ai_text_to_image
SERVICES_IMAGE_GENERATOR_DRIVERS_KIE_AI_API_KEY=your_api_key
```

**Features:**
- Pure text-to-image generation
- High-quality, realistic output
- Square HD aspect ratio (2K resolution)
- ~30 second generation time

---

### 2. KieAiEditDriver (Image-to-Image)
**Model**: `bytedance/seedream-v4-edit`

Generates images based on reference images plus text prompts. Perfect for maintaining consistent character appearance across multiple generated images.

**Usage:**
```php
use App\Services\ImageGeneratorManager;

$manager = new ImageGeneratorManager();
$driver = $manager->driver('kie_ai_edit');
$imageUrl = $driver->generate($prompt, $persona);
```

**With custom reference images:**
```php
use App\Services\ImageGenerators\KieAiEditDriver;

$driver = new KieAiEditDriver(
    apiKey: config('services.image_generator.drivers.kie_ai.api_key'),
    referenceImages: [
        'https://example.com/reference1.jpg',
        'https://example.com/reference2.jpg'
    ]
);
$imageUrl = $driver->generate($prompt, $persona);
```

**Configuration:**
```env
SERVICES_IMAGE_GENERATOR_DEFAULT=kie_ai_edit
SERVICES_IMAGE_GENERATOR_DRIVERS_KIE_AI_API_KEY=your_api_key
```

**Features:**
- Image-to-image generation with reference images
- Maintains character consistency
- Up to 10 reference images supported
- Automatically uses persona's `reference_images` media collection
- Falls back to avatar if no reference images found
- Square HD aspect ratio (2K resolution)
- ~30 second generation time

**Reference Image Setup:**
```php
// Upload reference images for a persona
$persona->addMedia($imagePath)
    ->toMediaCollection('reference_images');

// The driver will automatically use these when generating
```

---

### 3. CloudflareFluxDriver (Fallback)
**Model**: `@cf/black-forest-labs/flux-1-schnell`

Fast image generation using Cloudflare Workers AI.

**Usage:**
```php
use App\Services\ImageGeneratorManager;

$manager = new ImageGeneratorManager();
$driver = $manager->driver('cloudflare');
$imageUrl = $driver->generate($prompt, $persona);
```

**Configuration:**
```env
SERVICES_IMAGE_GENERATOR_DEFAULT=cloudflare
CLOUDFLARE_ACCOUNT_ID=your_account_id
CLOUDFLARE_API_TOKEN=your_api_token
```

**Features:**
- Fast generation (~5 seconds)
- Good quality output
- No polling required (direct response)

---

## ImageGeneratorManager

The `ImageGeneratorManager` provides a unified interface to switch between drivers:

```php
namespace App\Services;

class ImageGeneratorManager
{
    public function driver(?string $driverName = null): ImageGeneratorInterface
    {
        $driver = $driverName ?? config('services.image_generator.default', 'cloudflare');

        return match ($driver) {
            'kie_ai_text_to_image' => new KieAiTextToImageDriver(...),
            'kie_ai_edit' => new KieAiEditDriver(...),
            default => new CloudflareFluxDriver(...),
        };
    }
}
```

**Usage in Services:**
```php
use App\Services\ImageGeneratorManager;

// Use default driver
$imageUrl = app(ImageGeneratorManager::class)
    ->driver()
    ->generate($prompt, $persona);

// Use specific driver
$imageUrl = app(ImageGeneratorManager::class)
    ->driver('kie_ai_edit')
    ->generate($prompt, $persona);
```

---

## Driver Comparison

| Feature | KieAiTextToImageDriver | KieAiEditDriver | CloudflareFluxDriver |
|---------|------------------------|-----------------|---------------------|
| **Speed** | ~30s | ~30s | ~5s |
| **Quality** | High | High | Good |
| **Input** | Text only | Text + Images | Text only |
| **Reference Images** | No | Yes (up to 10) | No |
| **Character Consistency** | No | Yes | No |
| **Resolution** | 2K Square HD | 2K Square HD | 1024x1024 |
| **Use Case** | General image gen | Persona-specific images | Quick generations |

---

## When to Use Each Driver

### Use KieAiTextToImageDriver when:
- You need high-quality images from text descriptions only
- Character consistency is not required
- You don't have reference images

### Use KieAiEditDriver when:
- You need consistent character appearance across multiple images
- You have reference images of the character/persona
- You want the persona to appear in different scenarios/outfits
- You need the AI to maintain specific visual features (face, hair, style)

### Use CloudflareFluxDriver when:
- Speed is more important than maximum quality
- You need a quick fallback option
- API quota or costs are a concern

---

## Media Collections

The drivers work with Spatie MediaLibrary collections:

- **`reference_images`**: Input images for KieAiEditDriver (used for character reference)
- **`avatars`**: Persona avatar (fallback reference for KieAiEditDriver)
- **`generated_images`**: Output collection for all generated images

---

## Error Handling

All drivers implement retry logic and comprehensive error logging:

```php
// All errors are logged with context
Log::error('KieAiEditDriver: Generation failed', [
    'taskId' => $taskId,
    'failCode' => $failCode,
    'failMsg' => $failMsg,
]);

// Returns empty string on failure
$imageUrl = $driver->generate($prompt, $persona);
if (empty($imageUrl)) {
    // Handle failure - driver already logged the error
}
```

---

## Configuration Reference

**config/services.php:**
```php
'image_generator' => [
    'default' => env('SERVICES_IMAGE_GENERATOR_DEFAULT', 'cloudflare'),
    'drivers' => [
        'kie_ai' => [
            'api_key' => env('SERVICES_IMAGE_GENERATOR_DRIVERS_KIE_AI_API_KEY'),
        ],
        'cloudflare' => [
            'account_id' => env('SERVICES_IMAGE_GENERATOR_DRIVERS_CLOUDFLARE_ACCOUNT_ID'),
            'api_token' => env('SERVICES_IMAGE_GENERATOR_DRIVERS_CLOUDFLARE_API_TOKEN'),
        ],
    ],
],
```
