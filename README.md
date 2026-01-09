# OctoSqueeze for WordPress

Automatic image compression and WebP/AVIF conversion for WordPress.

## Features

- Automatic compression on upload
- WebP and AVIF format generation
- Manual compression from Media Library
- Background processing queue
- Compression statistics dashboard
- Preserves original images (optional)

## Requirements

- WordPress 6.0+
- PHP 8.0+
- OctoSqueeze API key ([Get one free](https://octosqueeze.com))

## Installation

### From WordPress Admin

1. Download the plugin zip file
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload and activate

### Manual Installation

1. Upload the `octosqueeze` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu
3. Go to **Settings > OctoSqueeze** to configure

## Configuration

1. Get your API key from [octosqueeze.com](https://octosqueeze.com)
2. Go to **Settings > OctoSqueeze**
3. Enter your API key
4. Configure compression settings

### Settings

| Setting | Description |
|---------|-------------|
| **API Key** | Your OctoSqueeze API key |
| **Compression Mode** | Size (smallest), Balanced (recommended), Quality (best quality) |
| **Output Formats** | WebP, AVIF - formats to generate in addition to original |
| **Auto Compress** | Automatically compress images on upload |
| **Preserve Originals** | Keep original images alongside compressed versions |

## Usage

### Automatic Compression

Once configured, images are automatically compressed when uploaded. The plugin:

1. Queues the image for compression
2. Sends it to OctoSqueeze API in the background
3. Downloads and saves WebP/AVIF versions
4. Updates the Media Library with compression stats

### Manual Compression

For images uploaded before installing OctoSqueeze:

1. Go to **Media > Library**
2. Switch to List view
3. Click **Compress** button next to any image

### Viewing Statistics

Go to **Settings > OctoSqueeze** to see:

- Total images compressed
- Total space saved
- Pending images in queue
- Average compression percentage

## How It Works

1. **Upload**: When you upload an image, it's added to the compression queue
2. **Process**: A background task (runs every 5 minutes) sends queued images to OctoSqueeze
3. **Compress**: OctoSqueeze compresses the image and generates WebP/AVIF versions
4. **Save**: Compressed images are saved to your uploads folder
5. **Serve**: WordPress automatically serves the best format based on browser support

## Serving WebP/AVIF Images

OctoSqueeze creates `.webp` and `.avif` versions alongside your original images. To serve them:

### Option 1: Using .htaccess (Apache)

Add to your `.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Serve WebP if available and browser supports it
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteCond %{REQUEST_FILENAME} \.(jpe?g|png)$
    RewriteCond %{REQUEST_FILENAME}\.webp -f
    RewriteRule ^(.+)\.(jpe?g|png)$ $1.$2.webp [T=image/webp,E=accept:1,L]
</IfModule>
```

### Option 2: Using a Caching Plugin

Many caching plugins like WP Rocket, LiteSpeed Cache, or W3 Total Cache support WebP serving automatically.

## Troubleshooting

### Images Not Compressing

1. Check your API key is correct in Settings
2. Verify your site can make outbound HTTPS requests
3. Check the WordPress error log for messages

### Queue Not Processing

The background queue runs every 5 minutes via WP-Cron. If images are stuck:

1. Ensure WP-Cron is working (visit your site or set up a real cron job)
2. Check for PHP errors in your error log

## Support

- Documentation: [octosqueeze.com/docs](https://octosqueeze.com/docs)
- Issues: [GitHub Issues](https://github.com/octosqueeze/octosqueeze-wordpress/issues)

## License

GPL v2 or later
