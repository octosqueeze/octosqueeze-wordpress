<?php
/**
 * OctoSqueeze settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

class OctoSqueeze_Settings {

    public function add_menu() {
        add_options_page(
            __('OctoSqueeze Settings', 'octosqueeze'),
            __('OctoSqueeze', 'octosqueeze'),
            'manage_options',
            'octosqueeze',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('octosqueeze_settings', 'octosqueeze_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);

        // API Section
        add_settings_section(
            'octosqueeze_api',
            __('API Settings', 'octosqueeze'),
            [$this, 'render_api_section'],
            'octosqueeze'
        );

        add_settings_field(
            'api_key',
            __('API Key', 'octosqueeze'),
            [$this, 'render_api_key_field'],
            'octosqueeze',
            'octosqueeze_api'
        );

        // Compression Section
        add_settings_section(
            'octosqueeze_compression',
            __('Compression Settings', 'octosqueeze'),
            [$this, 'render_compression_section'],
            'octosqueeze'
        );

        add_settings_field(
            'mode',
            __('Compression Mode', 'octosqueeze'),
            [$this, 'render_mode_field'],
            'octosqueeze',
            'octosqueeze_compression'
        );

        add_settings_field(
            'formats',
            __('Output Formats', 'octosqueeze'),
            [$this, 'render_formats_field'],
            'octosqueeze',
            'octosqueeze_compression'
        );

        add_settings_field(
            'compress_on_upload',
            __('Auto Compress', 'octosqueeze'),
            [$this, 'render_auto_compress_field'],
            'octosqueeze',
            'octosqueeze_compression'
        );

        add_settings_field(
            'preserve_originals',
            __('Preserve Originals', 'octosqueeze'),
            [$this, 'render_preserve_field'],
            'octosqueeze',
            'octosqueeze_compression'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = [];

        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        $sanitized['mode'] = in_array($input['mode'] ?? '', ['size', 'balanced', 'quality'])
            ? $input['mode']
            : 'balanced';
        $sanitized['formats'] = array_filter(
            (array) ($input['formats'] ?? ['webp']),
            function ($f) {
                return in_array($f, ['webp', 'avif', 'jpeg', 'png']);
            }
        );
        $sanitized['compress_on_upload'] = !empty($input['compress_on_upload']);
        $sanitized['preserve_originals'] = !empty($input['preserve_originals']);
        $sanitized['max_width'] = absint($input['max_width'] ?? 2560);
        $sanitized['max_height'] = absint($input['max_height'] ?? 2560);

        return $sanitized;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="octosqueeze-stats-card">
                <h2><?php _e('Compression Statistics', 'octosqueeze'); ?></h2>
                <div id="octosqueeze-stats">
                    <p><?php _e('Loading...', 'octosqueeze'); ?></p>
                </div>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('octosqueeze_settings');
                do_settings_sections('octosqueeze');
                submit_button(__('Save Settings', 'octosqueeze'));
                ?>
            </form>
        </div>
        <?php
    }

    public function render_api_section() {
        echo '<p>' . sprintf(
            __('Enter your OctoSqueeze API key. <a href="%s" target="_blank">Get your free API key</a>.', 'octosqueeze'),
            'https://octosqueeze.com'
        ) . '</p>';
    }

    public function render_compression_section() {
        echo '<p>' . __('Configure how images are compressed.', 'octosqueeze') . '</p>';
    }

    public function render_api_key_field() {
        $options = get_option('octosqueeze_settings', []);
        $value = $options['api_key'] ?? '';
        ?>
        <input type="password" name="octosqueeze_settings[api_key]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="os_xxxxxxxxxxxx">
        <?php
    }

    public function render_mode_field() {
        $options = get_option('octosqueeze_settings', []);
        $value = $options['mode'] ?? 'balanced';
        $modes = [
            'size' => __('Size (smallest files)', 'octosqueeze'),
            'balanced' => __('Balanced (recommended)', 'octosqueeze'),
            'quality' => __('Quality (best quality)', 'octosqueeze'),
        ];
        ?>
        <select name="octosqueeze_settings[mode]">
            <?php foreach ($modes as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_formats_field() {
        $options = get_option('octosqueeze_settings', []);
        $value = $options['formats'] ?? ['webp'];
        $formats = [
            'webp' => 'WebP',
            'avif' => 'AVIF',
        ];
        ?>
        <fieldset>
            <?php foreach ($formats as $key => $label): ?>
                <label>
                    <input type="checkbox" name="octosqueeze_settings[formats][]"
                           value="<?php echo esc_attr($key); ?>"
                           <?php checked(in_array($key, $value)); ?>>
                    <?php echo esc_html($label); ?>
                </label><br>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">
            <?php _e('Select additional formats to generate. WebP has broad support, AVIF offers better compression but limited support.', 'octosqueeze'); ?>
        </p>
        <?php
    }

    public function render_auto_compress_field() {
        $options = get_option('octosqueeze_settings', []);
        $value = $options['compress_on_upload'] ?? true;
        ?>
        <label>
            <input type="checkbox" name="octosqueeze_settings[compress_on_upload]"
                   value="1" <?php checked($value); ?>>
            <?php _e('Automatically compress images on upload', 'octosqueeze'); ?>
        </label>
        <?php
    }

    public function render_preserve_field() {
        $options = get_option('octosqueeze_settings', []);
        $value = $options['preserve_originals'] ?? true;
        ?>
        <label>
            <input type="checkbox" name="octosqueeze_settings[preserve_originals]"
                   value="1" <?php checked($value); ?>>
            <?php _e('Keep original images (recommended)', 'octosqueeze'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, original images are preserved and WebP/AVIF versions are created alongside them.', 'octosqueeze'); ?>
        </p>
        <?php
    }
}
