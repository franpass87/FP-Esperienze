<?php
/**
 * Branding settings view helpers.
 *
 * @package FP\Esperienze\Admin\Settings
 */

namespace FP\Esperienze\Admin\Settings;

use FP\Esperienze\Admin\Settings\Services\BrandingSettingsService;

class BrandingSettingsView
{
    /**
     * @return array<string,string>
     */
    public function getPrimaryFontOptions(): array
    {
        return [
            'inherit' => __('Inherit from theme', 'fp-esperienze'),
            'Arial, sans-serif' => 'Arial',
            'Helvetica, Arial, sans-serif' => 'Helvetica',
            'Georgia, serif' => 'Georgia',
            "'Times New Roman', serif" => __('Times New Roman', 'fp-esperienze'),
            'Verdana, sans-serif' => 'Verdana',
            "'Trebuchet MS', sans-serif" => 'Trebuchet MS',
            "'Courier New', monospace" => 'Courier New',
            "'Open Sans', sans-serif" => __('Open Sans (Google Fonts)', 'fp-esperienze'),
            "'Roboto', sans-serif" => __('Roboto (Google Fonts)', 'fp-esperienze'),
            "'Lato', sans-serif" => __('Lato (Google Fonts)', 'fp-esperienze'),
            "'Montserrat', sans-serif" => __('Montserrat (Google Fonts)', 'fp-esperienze'),
            "'Poppins', sans-serif" => __('Poppins (Google Fonts)', 'fp-esperienze'),
        ];
    }

    /**
     * @return array<string,string>
     */
    public function getHeadingFontOptions(): array
    {
        $options = $this->getPrimaryFontOptions();
        $options["'Playfair Display', serif"] = __('Playfair Display (Google Fonts)', 'fp-esperienze');
        $options["'Merriweather', serif"] = __('Merriweather (Google Fonts)', 'fp-esperienze');

        return $options;
    }

    /**
     * Render a select element for font choices.
     */
    public function renderFontSelect(string $fieldId, string $selectedValue, array $options): string
    {
        $allowed = array_flip(BrandingSettingsService::ALLOWED_FONTS);
        $html = '<select id="' . esc_attr($fieldId) . '" name="' . esc_attr($fieldId) . '" class="regular-text">';

        foreach ($options as $value => $label) {
            if (!isset($allowed[$value])) {
                continue;
            }

            $html .= '<option value="' . esc_attr($value) . '" ' . selected($selectedValue, $value, false) . '>' .
                esc_html($label) . '</option>';
        }

        $html .= '</select>';

        return $html;
    }
}
