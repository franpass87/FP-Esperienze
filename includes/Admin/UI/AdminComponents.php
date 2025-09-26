<?php

declare(strict_types=1);

namespace FP\Esperienze\Admin\UI;

use function array_filter;
use function array_unique;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function implode;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function preg_split;
use function sanitize_html_class;
use function sprintf;
use function trim;
use function wp_kses_post;
use function wp_unique_id;

/**
 * Reusable HTML component helpers for FP Esperienze admin screens.
 */
class AdminComponents {

    /**
     * Render a skip link that allows keyboard users to jump to the main content area.
     */
    public static function skipLink(string $target_id = 'fp-admin-main-content', ?string $label = null): void {
        $target = trim($target_id) !== '' ? $target_id : 'fp-admin-main-content';
        $text = $label !== null && $label !== ''
            ? (string) $label
            : esc_html__('Skip to main content', 'fp-esperienze');

        printf(
            '<a class="fp-admin-skip-link" href="#%1$s">%2$s</a>',
            esc_attr($target),
            esc_html($text)
        );
    }

    /**
     * Render a page header containing the primary title, optional description, meta and actions.
     *
     * @param array<string, mixed> $args {
     *     Arguments to control the header output.
     *
     *     @type string                $title        Required page title.
     *     @type string|null           $lead         Optional supporting copy shown below the title.
     *     @type array<int, mixed>     $meta         Inline meta items rendered next to the title.
     *     @type array<int, mixed>     $actions      Action buttons rendered to the right of the header.
     *     @type string|null           $actions_label Accessible label for the actions group.
     * }
     */
    public static function pageHeader(array $args): void {
        $title = isset($args['title']) ? (string) $args['title'] : '';

        if ($title === '') {
            return;
        }

        $lead = isset($args['lead']) ? (string) $args['lead'] : '';
        $meta = isset($args['meta']) && is_array($args['meta']) ? $args['meta'] : [];
        $actions = isset($args['actions']) && is_array($args['actions']) ? $args['actions'] : [];
        $actions_label = isset($args['actions_label']) && $args['actions_label'] !== ''
            ? (string) $args['actions_label']
            : esc_html__('Page actions', 'fp-esperienze');

        $lead_id = $lead !== '' ? wp_unique_id('fp-admin-page-lead-') : null;

        echo '<header class="fp-admin-page__header">';
        echo '<div class="fp-admin-page__heading">';
        printf('<h1 class="fp-admin-page__title">%s</h1>', esc_html($title));

        if ($lead !== '') {
            printf(
                '<p id="%1$s" class="fp-admin-page__lead">%2$s</p>',
                esc_attr((string) $lead_id),
                wp_kses_post($lead)
            );
        }

        if ($meta !== []) {
            echo '<div class="fp-admin-page__meta">';
            foreach ($meta as $item) {
                if (is_array($item)) {
                    $label = isset($item['label']) ? (string) $item['label'] : '';
                    $value = isset($item['value']) ? (string) $item['value'] : '';

                    if ($label === '' && $value === '') {
                        continue;
                    }

                    printf(
                        '<span class="fp-admin-page__meta-item"><span class="fp-admin-helper-text">%s</span> %s</span>',
                        esc_html($label),
                        esc_html($value)
                    );
                    continue;
                }

                if (is_string($item) && trim($item) !== '') {
                    printf('<span class="fp-admin-page__meta-item">%s</span>', esc_html($item));
                }
            }
            echo '</div>';
        }
        echo '</div>';

        if ($actions !== []) {
            $rendered_actions = self::renderItems($actions);

            if ($rendered_actions !== []) {
                printf('<div class="fp-admin-page__actions" role="group" aria-label="%s">', esc_attr($actions_label));
                echo implode('', $rendered_actions);
                echo '</div>';
            }
        }

        echo '</header>';
    }

    /**
     * Render a toolbar shell with start/end groups.
     *
     * @param array<string, mixed> $args {
     *     @type string|null           $title       Optional toolbar title.
     *     @type string|null           $description Optional helper text shown next to the title.
     *     @type array<int, mixed>     $start       Items rendered in the primary (left) group.
     *     @type array<int, mixed>     $end         Items rendered in the trailing (right) group.
     *     @type string|null           $aria_label  Accessible label for the toolbar container.
     *     @type bool                  $sticky      Whether the toolbar should use the sticky modifier.
     * }
     */
    public static function toolbar(array $args = []): void {
        $title = isset($args['title']) ? (string) $args['title'] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $start = isset($args['start']) && is_array($args['start']) ? $args['start'] : [];
        $end = isset($args['end']) && is_array($args['end']) ? $args['end'] : [];
        $aria_label = isset($args['aria_label']) && $args['aria_label'] !== ''
            ? (string) $args['aria_label']
            : esc_html__('Admin actions toolbar', 'fp-esperienze');
        $sticky = ! empty($args['sticky']);

        $classes = ['fp-admin-toolbar'];
        if ($sticky) {
            $classes[] = 'fp-admin-toolbar--sticky';
        }

        printf(
            '<div class="%1$s" role="toolbar" aria-label="%2$s">',
            esc_attr(implode(' ', $classes)),
            esc_attr($aria_label)
        );

        $primary_items = [];
        if ($title !== '') {
            $primary_items[] = sprintf('<h2 class="fp-admin-toolbar__title">%s</h2>', esc_html($title));
        }

        if ($description !== '') {
            $primary_items[] = sprintf('<p class="fp-admin-helper-text">%s</p>', wp_kses_post($description));
        }

        $primary_items = array_merge($primary_items, self::renderItems($start));
        self::renderToolbarGroup($primary_items, 'primary');

        $secondary_items = self::renderItems($end);
        if ($secondary_items !== []) {
            self::renderToolbarGroup($secondary_items, 'secondary');
        }

        echo '</div>';
    }

    /**
     * Open a card container.
     *
     * @param array<string, mixed> $args Optional args (title, meta, muted, classes).
     */
    public static function openCard(array $args = []): void {
        $classes = ['fp-admin-card'];
        if (! empty($args['muted'])) {
            $classes[] = 'fp-admin-card--muted';
        }

        if (! empty($args['class'])) {
            $classes = array_merge($classes, self::normaliseClasses($args['class']));
        }

        printf('<section class="%s">', esc_attr(implode(' ', array_unique($classes))));

        if (! empty($args['title'])) {
            echo '<div class="fp-admin-card__header">';
            printf('<h2 class="fp-admin-card__title">%s</h2>', esc_html((string) $args['title']));

            if (! empty($args['meta']) && is_array($args['meta'])) {
                echo '<div class="fp-admin-card__meta">';
                foreach ($args['meta'] as $meta_item) {
                    if (! is_string($meta_item) || trim($meta_item) === '') {
                        continue;
                    }
                    printf('<span class="fp-admin-page__meta-item">%s</span>', esc_html($meta_item));
                }
                echo '</div>';
            }

            echo '</div>';
        }
    }

    /**
     * Close a previously opened card container.
     */
    public static function closeCard(): void {
        echo '</section>';
    }

    /**
     * Render an accessible notice block.
     *
     * @param array<string, mixed> $args {
     *     @type string      $message  Main body text of the notice.
     *     @type string|null $title    Optional heading.
     *     @type string      $type     One of info|success|warning|danger.
     *     @type array<int,mixed> $actions Optional follow-up actions.
     *     @type string|null $role     ARIA role override (defaults to status/alert based on type).
     * }
     */
    public static function notice(array $args): void {
        $message = isset($args['message']) ? (string) $args['message'] : '';

        if ($message === '') {
            return;
        }

        $title = isset($args['title']) ? (string) $args['title'] : '';
        $type = isset($args['type']) ? (string) $args['type'] : 'info';
        $actions = isset($args['actions']) && is_array($args['actions']) ? $args['actions'] : [];
        $role = isset($args['role']) ? (string) $args['role'] : '';

        if ($role === '') {
            $role = in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status';
        }

        $classes = ['fp-admin-notice'];
        $variant = in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info';
        $classes[] = 'fp-admin-notice--' . sanitize_html_class($variant);

        $notice_id = wp_unique_id('fp-admin-notice-');
        $title_id = $title !== '' ? $notice_id . '-title' : '';
        $message_id = $notice_id . '-message';
        $live = $role === 'alert' ? 'assertive' : 'polite';

        $attributes = sprintf(
            ' id="%1$s" role="%2$s" aria-live="%3$s" aria-atomic="true" aria-describedby="%4$s"',
            esc_attr($notice_id),
            esc_attr($role),
            esc_attr($live),
            esc_attr($message_id)
        );

        if ($title_id !== '') {
            $attributes .= sprintf(' aria-labelledby="%s"', esc_attr($title_id));
        }

        printf('<div class="%1$s"%2$s>', esc_attr(implode(' ', $classes)), $attributes);

        if ($title !== '') {
            printf('<p id="%1$s" class="fp-admin-notice__title">%2$s</p>', esc_attr($title_id), esc_html($title));
        }

        printf('<div id="%1$s" class="fp-admin-notice__message">%2$s</div>', esc_attr($message_id), wp_kses_post($message));

        $rendered_actions = self::renderItems($actions);
        if ($rendered_actions !== []) {
            echo '<div class="fp-admin-notice__actions">' . implode('', $rendered_actions) . '</div>';
        }

        echo '</div>';
    }

    /**
     * Render a labelled form row with description/error handling.
     *
     * @param array<string, mixed> $args {
     *     @type string      $label       Required label text.
     *     @type string      $for         Associated field ID.
     *     @type bool        $required    Whether to append the required marker.
     *     @type string|null $description Optional helper text.
     *     @type string|null $error       Optional error message.
     *     @type string      $id          Explicit wrapper ID.
     * }
     * @param callable|string|null $control Control markup renderer or pre-rendered HTML.
     */
    public static function formRow(array $args, $control = null): void {
        $label = isset($args['label']) ? (string) $args['label'] : '';
        $for = isset($args['for']) ? (string) $args['for'] : '';

        if ($label === '' || $for === '') {
            return;
        }

        $required = ! empty($args['required']);
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $error = isset($args['error']) ? (string) $args['error'] : '';
        $row_id = isset($args['id']) && $args['id'] !== '' ? (string) $args['id'] : wp_unique_id('fp-admin-form-row-');

        $description_id = $description !== '' ? $row_id . '-description' : '';
        $error_id = $error !== '' ? $row_id . '-error' : '';

        $described_by = array_filter([$description_id, $error_id]);

        echo '<div class="fp-admin-form__row" id="' . esc_attr($row_id) . '">';
        $label_classes = ['fp-admin-form__label'];
        if ($required) {
            $label_classes[] = 'fp-admin-form__label--required';
        }

        printf(
            '<label class="%1$s" for="%2$s">%3$s</label>',
            esc_attr(implode(' ', $label_classes)),
            esc_attr($for),
            esc_html($label)
        );

        if ($description !== '') {
            printf(
                '<p id="%1$s" class="fp-admin-form__description">%2$s</p>',
                esc_attr($description_id),
                wp_kses_post($description)
            );
        }

        $control_markup = self::renderControl($control);
        if ($control_markup !== '') {
            $control_classes = ['fp-admin-form__control'];
            if ($error !== '') {
                $control_classes[] = 'has-error';
            }

            $control_attributes = $described_by !== []
                ? sprintf(' aria-describedby="%s"', esc_attr(implode(' ', $described_by)))
                : '';

            printf(
                '<div class="%1$s"%2$s>%3$s</div>',
                esc_attr(implode(' ', $control_classes)),
                $control_attributes,
                $control_markup
            );
        }

        if ($error !== '') {
            printf(
                '<p id="%1$s" class="fp-admin-form__error" role="alert" aria-live="assertive">%2$s</p>',
                esc_attr($error_id),
                esc_html($error)
            );
        }

        echo '</div>';
    }

    /**
     * Render a tab navigation list.
     *
     * @param array<int, array<string, mixed>> $tabs Tab definitions.
     * @param array<string, mixed>              $args Optional container args.
     */
    public static function tabNav(array $tabs, array $args = []): void {
        if ($tabs === []) {
            return;
        }

        $label = isset($args['label']) && $args['label'] !== '' ? (string) $args['label'] : esc_html__('Secondary navigation', 'fp-esperienze');
        $nav_id = isset($args['id']) && $args['id'] !== '' ? (string) $args['id'] : wp_unique_id('fp-admin-tab-nav-');

        printf('<nav id="%1$s" class="fp-admin-tab-nav" aria-label="%2$s">', esc_attr($nav_id), esc_attr($label));
        echo '<ul class="fp-admin-tab-nav__list">';

        foreach ($tabs as $tab) {
            $label_text = isset($tab['label']) ? (string) $tab['label'] : '';
            $href = isset($tab['href']) ? (string) $tab['href'] : '#';

            if ($label_text === '') {
                continue;
            }

            $is_current = ! empty($tab['current']);
            $link_classes = ['fp-admin-tab-nav__link'];
            if (! empty($tab['class'])) {
                $link_classes[] = sanitize_html_class((string) $tab['class']);
            }

            $attrs = '';
            $attr_map = isset($tab['attrs']) && is_array($tab['attrs']) ? $tab['attrs'] : [];
            $attr_map['href'] = $href;

            if ($is_current) {
                $attr_map['aria-current'] = 'page';
            }

            if (! empty($tab['target'])) {
                $attr_map['target'] = (string) $tab['target'];
            }

            if (! empty($tab['rel'])) {
                $attr_map['rel'] = (string) $tab['rel'];
            }

            foreach ($attr_map as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                if ($value === true) {
                    $attrs .= sprintf(' %s', esc_attr((string) $key));
                } else {
                    $attrs .= sprintf(' %s="%s"', esc_attr((string) $key), esc_attr((string) $value));
                }
            }

            echo '<li class="fp-admin-tab-nav__item">';
            printf('<a class="%1$s"%2$s>', esc_attr(implode(' ', $link_classes)), $attrs);
            echo esc_html($label_text);

            if (isset($tab['count']) && $tab['count'] !== '') {
                printf('<span class="fp-admin-badge">%s</span>', esc_html((string) $tab['count']));
            }

            if (isset($tab['after']) && is_string($tab['after']) && trim($tab['after']) !== '') {
                echo wp_kses_post($tab['after']);
            }

            echo '</a>';
            echo '</li>';
        }

        echo '</ul>';
        echo '</nav>';
    }

    /**
     * Render items for components that accept string/array/callable definitions.
     *
     * @param array<int, mixed> $items Items to render.
     *
     * @return array<int, string>
     */
    private static function renderItems(array $items): array {
        $output = [];

        foreach ($items as $item) {
            if (is_callable($item)) {
                $output[] = self::captureCallable($item);
                continue;
            }

            if (is_string($item)) {
                $trimmed = trim($item);
                if ($trimmed !== '') {
                    $output[] = $trimmed;
                }
                continue;
            }

            if (is_array($item)) {
                $rendered = self::renderActionItem($item);
                if ($rendered !== '') {
                    $output[] = $rendered;
                }
            }
        }

        return array_filter($output, static fn($fragment) => $fragment !== '');
    }

    /**
     * Render a control block from either callable or string.
     *
     * @param callable|string|null $control Control renderer.
     */
    private static function renderControl($control): string {
        if ($control === null) {
            return '';
        }

        if (is_callable($control)) {
            return self::captureCallable($control);
        }

        if (is_string($control)) {
            $trimmed = trim($control);

            return $trimmed !== '' ? $trimmed : '';
        }

        return '';
    }

    /**
     * Render an action array into HTML markup.
     *
     * @param array<string, mixed> $action Action definition.
     */
    private static function renderActionItem(array $action): string {
        $label = isset($action['label']) ? (string) $action['label'] : '';

        if ($label === '') {
            return '';
        }

        $tag = isset($action['tag']) ? (string) $action['tag'] : '';
        if (! in_array($tag, ['a', 'button'], true)) {
            $tag = isset($action['url']) ? 'a' : 'button';
        }

        $variant = isset($action['variant']) ? (string) $action['variant'] : 'secondary';
        $classes = ['button'];

        if ($variant === 'primary') {
            $classes[] = 'button-primary';
        } elseif ($variant === 'link') {
            $classes = [];
        } else {
            $classes[] = 'button-secondary';
        }

        if (! empty($action['class'])) {
            $classes = array_merge($classes, self::normaliseClasses($action['class']));
        }

        $attributes = isset($action['attrs']) && is_array($action['attrs']) ? $action['attrs'] : [];

        if ($tag === 'a') {
            $attributes['href'] = isset($action['url']) ? (string) $action['url'] : '#';
        } else {
            $attributes['type'] = isset($action['type']) ? (string) $action['type'] : 'button';
        }

        if (! empty($action['target'])) {
            $attributes['target'] = (string) $action['target'];
        }

        if (! empty($action['rel'])) {
            $attributes['rel'] = (string) $action['rel'];
        }

        if (! empty($action['disabled'])) {
            $attributes['disabled'] = 'disabled';
        }

        $attr_string = '';
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($value === true) {
                $attr_string .= sprintf(' %s', esc_attr((string) $key));
            } else {
                $attr_string .= sprintf(' %s="%s"', esc_attr((string) $key), esc_attr((string) $value));
            }
        }

        $class_attr = $classes !== [] ? sprintf(' class="%s"', esc_attr(implode(' ', array_unique($classes)))) : '';

        return sprintf('<%1$s%2$s%3$s>%4$s</%1$s>', esc_attr($tag), $class_attr, $attr_string, esc_html($label));
    }

    /**
     * Render a toolbar group wrapper when items exist.
     *
     * @param array<int, string> $items   Markup fragments.
     * @param string             $context Contextual modifier suffix.
     */
    private static function renderToolbarGroup(array $items, string $context): void {
        if ($items === []) {
            return;
        }

        $classes = array_merge(
            ['fp-admin-toolbar__group'],
            self::normaliseClasses('fp-admin-toolbar__group--' . $context)
        );
        printf('<div class="%s">', esc_attr(implode(' ', array_unique($classes))));
        echo implode('', $items);
        echo '</div>';
    }

    /**
     * Normalise custom class values into individual safe class tokens.
     *
     * @param string|array<int, string> $classes Raw class definition.
     *
     * @return array<int, string>
     */
    private static function normaliseClasses($classes): array {
        if (is_string($classes)) {
            $classes = preg_split('/\s+/', trim($classes)) ?: [];
        }

        if (! is_array($classes)) {
            return [];
        }

        $tokens = [];
        foreach ($classes as $class) {
            $class = trim((string) $class);

            if ($class === '') {
                continue;
            }

            $tokens[] = sanitize_html_class($class);
        }

        return array_unique($tokens);
    }

    /**
     * Capture the output of a callable into a string.
     *
     * @param callable $callable Callable to execute.
     */
    private static function captureCallable(callable $callable): string {
        ob_start();
        $callable();

        return trim((string) ob_get_clean());
    }
}

