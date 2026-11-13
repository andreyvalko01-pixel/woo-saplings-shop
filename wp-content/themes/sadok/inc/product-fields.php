<?php
/**
 * Додаткові поля саджанця: морозостійкість, початок плодоношення, запилення, термін дозрівання.
 * Власник заповнює їх у звичній вкладці «Загальне» картки товару — без зайвих плагінів.
 */

defined('ABSPATH') || exit;

function sadok_product_fields(): array
{
    return [
        '_sadok_frost'      => ['label' => 'Морозостійкість', 'placeholder' => 'до −30 °C'],
        '_sadok_fruiting'   => ['label' => 'Початок плодоношення', 'placeholder' => 'на 3–4 рік'],
        '_sadok_ripening'   => ['label' => 'Термін дозрівання', 'placeholder' => 'кінець серпня'],
        '_sadok_pollination' => ['label' => 'Запилення', 'placeholder' => 'самоплідний'],
        '_sadok_root'       => ['label' => 'Коренева система', 'placeholder' => 'закрита (контейнер 5 л)'],
    ];
}

add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';
    foreach (sadok_product_fields() as $key => $field) {
        woocommerce_wp_text_input([
            'id'          => $key,
            'label'       => $field['label'],
            'placeholder' => $field['placeholder'],
        ]);
    }
    echo '</div>';
});

add_action('woocommerce_admin_process_product_object', function (WC_Product $product) {
    foreach (array_keys(sadok_product_fields()) as $key) {
        if (isset($_POST[$key])) {
            $product->update_meta_data($key, sanitize_text_field(wp_unslash($_POST[$key])));
        }
    }
});

/** Таблиця характеристик на сторінці товару, під кнопкою «Купити». */
add_action('woocommerce_single_product_summary', function () {
    global $product;
    $rows = [];
    foreach (sadok_product_fields() as $key => $field) {
        $value = $product->get_meta($key);
        if ($value) {
            $rows[] = sprintf('<div><dt>%s</dt><dd>%s</dd></div>', esc_html($field['label']), esc_html($value));
        }
    }
    if ($rows) {
        echo '<dl class="specs">' . implode('', $rows) . '</dl>';
    }
}, 35);
