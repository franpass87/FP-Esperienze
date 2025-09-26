<?php
/**
 * Branding settings tab service.
 *
 * @package FP\Esperienze\Admin\Settings\Services
 */

namespace FP\Esperienze\Admin\Settings\Services;

class BrandingSettingsService implements SettingsTabServiceInterface
{
    /**
     * @var array<int,string>
     */
    public const ALLOWED_FONTS = [
        'inherit',
        'Arial, sans-serif',
        'Helvetica, Arial, sans-serif',
        'Georgia, serif',
        "'Times New Roman', serif",
        'Verdana, sans-serif',
        "'Trebuchet MS', sans-serif",
        "'Courier New', monospace",
        "'Open Sans', sans-serif",
        "'Roboto', sans-serif",
        "'Lato', sans-serif",
        "'Montserrat', sans-serif",
        "'Poppins', sans-serif",
        "'Playfair Display', serif",
        "'Merriweather', serif",
    ];

    private const DEFAULT_PRIMARY_COLOR   = '#ff6b35';
    private const DEFAULT_SECONDARY_COLOR = '#2c3e50';

    public function handle(array $data): SettingsUpdateResult
    {
        $primaryFont = sanitize_text_field(wp_unslash($data['primary_font'] ?? 'inherit'));
        $headingFont = sanitize_text_field(wp_unslash($data['heading_font'] ?? 'inherit'));
        $primaryColor = sanitize_hex_color(wp_unslash($data['primary_color'] ?? self::DEFAULT_PRIMARY_COLOR))
            ?: self::DEFAULT_PRIMARY_COLOR;
        $secondaryColor = sanitize_hex_color(wp_unslash($data['secondary_color'] ?? self::DEFAULT_SECONDARY_COLOR))
            ?: self::DEFAULT_SECONDARY_COLOR;

        if (!in_array($primaryFont, self::ALLOWED_FONTS, true)) {
            $primaryFont = 'inherit';
        }

        if (!in_array($headingFont, self::ALLOWED_FONTS, true)) {
            $headingFont = 'inherit';
        }

        update_option('fp_esperienze_branding', [
            'primary_font'   => $primaryFont,
            'heading_font'   => $headingFont,
            'primary_color'  => $primaryColor,
            'secondary_color'=> $secondaryColor,
        ]);

        return SettingsUpdateResult::success();
    }
}
