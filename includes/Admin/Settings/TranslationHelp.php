<?php
/**
 * Translation Help page.
 *
 * @package FP\Esperienze\Admin\Settings
 */

namespace FP\Esperienze\Admin\Settings;

use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Admin\MenuRegistry;

defined('ABSPATH') || exit;

/**
 * Displays instructions for translating the plugin.
 */
class TranslationHelp {
    /**
     * Constructor.
     */
    public function __construct() {
        MenuRegistry::instance()->registerPage([
            'slug'       => 'fp-esperienze-localization',
            'page_title' => __('Localization Guide', 'fp-esperienze'),
            'menu_title' => __('Localization Guide', 'fp-esperienze'),
            'capability' => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'callback'   => [$this, 'renderPage'],
            'order'      => 160,
            'aliases'    => ['fp-esperienze-translation-help'],
        ]);
    }

    /**
     * Render the help page content.
     */
    public function renderPage(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Localization Guide', 'fp-esperienze'); ?></h1>
            <ol>
                <li>
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            /* translators: %s: WPML translation modes documentation URL */
                            __('Install and activate WPML, then go to <a href="%s" target="_blank">WPML → Settings</a> and choose the <strong>Translate Everything</strong> mode.', 'fp-esperienze'),
                            'https://wpml.org/documentation/getting-started-guide/translation-modes/'
                        )
                    );
                    ?>
                </li>
                <li>
                    <?php esc_html_e('Register dynamic strings with I18nManager::translateString:', 'fp-esperienze'); ?>
                    <pre><code><?php echo esc_html("\\FP\\Esperienze\\Core\\I18nManager::translateString('your-string', 'fp-esperienze');"); ?></code></pre>
                </li>
                <li>
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            /* translators: %s: LibreTranslate URL */
                            __('Set the automatic translator endpoint and API key (e.g., %1$sLibreTranslate%2$s) in the Auto Translation settings page.', 'fp-esperienze'),
                            '<a href="https://libretranslate.com/" target="_blank">',
                            '</a>'
                        )
                    );
                    ?>
                </li>
                <li>
                    <?php esc_html_e('Choose the target languages for automatic translation in the Auto Translation settings.', 'fp-esperienze'); ?>
                </li>
            </ol>
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: %s: WPML docs URL */
                        __('See the %1$sWPML documentation%2$s for further details.', 'fp-esperienze'),
                        '<a href="https://wpml.org/documentation/" target="_blank">',
                        '</a>'
                    )
                );
                ?>
            </p>
            <p><img src="https://wpml.org/wp-content/uploads/2020/08/wpml-translate-everything.png" alt="<?php esc_attr_e('WPML Translate Everything screenshot', 'fp-esperienze'); ?>" style="max-width:100%;height:auto;" /></p>
            <p><a href="https://www.youtube.com/watch?v=6HuLlHotE5w" target="_blank"><?php esc_html_e('Video: WPML Translate Everything overview', 'fp-esperienze'); ?></a></p>
        </div>
        <?php
    }
}
