<?php
/**
 * Breadcrumb Helper
 * Responsible for rendering breadcrumb navigation tracks.
 */

class BreadcrumbHelper {
    /**
     * Render breadcrumb navigation
     * @param array $items Array of ['label' => 'Link Text', 'url' => 'page.php'] or just 'Label' for current page
     */
    public static function render($items) {
        if (empty($items)) return '';

        echo '<nav class="breadcrumb" aria-label="Breadcrumb">';

        foreach ($items as $index => $item) {
            $isLast = ($index === count($items) - 1);

            echo '<div class="breadcrumb__item">';

            if ($isLast) {
                if (is_array($item)) {
                    echo '<span class="breadcrumb__current">' . htmlspecialchars($item['label']) . '</span>';
                } else {
                    echo '<span class="breadcrumb__current">' . htmlspecialchars($item) . '</span>';
                }
            } else {
                if (is_array($item)) {
                    echo '<a href="' . htmlspecialchars($item['url']) . '" class="breadcrumb__link">';
                    echo htmlspecialchars($item['label']);
                    echo '</a>';
                }
            }

            echo '</div>';
        }

        echo '</nav>';
    }
}
