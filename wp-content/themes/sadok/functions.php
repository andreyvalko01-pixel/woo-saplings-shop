<?php
/**
 * Тема «Садок»: налаштування, підключення стилів і хуки WooCommerce.
 */

defined('ABSPATH') || exit;

define('SADOK_VERSION', '1.0.0');

require_once __DIR__ . '/inc/product-fields.php';
require_once __DIR__ . '/inc/shop.php';
require_once __DIR__ . '/inc/seo.php';

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('woocommerce', [
        'thumbnail_image_width' => 600,
        'single_image_width'    => 900,
        'product_grid'          => ['default_columns' => 3, 'default_rows' => 4],
    ]);
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');

    register_nav_menus(['primary' => 'Головне меню', 'footer' => 'Меню в підвалі']);
});

add_action('wp_enqueue_scripts', function () {
    $uri = get_template_directory_uri();

    wp_enqueue_style('sadok-fonts', 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Lora:wght@500;600&display=swap', [], null);
    wp_enqueue_style('sadok', $uri . '/assets/css/theme.css', [], SADOK_VERSION);
    wp_enqueue_script('sadok', $uri . '/assets/js/theme.js', [], SADOK_VERSION, ['strategy' => 'defer', 'in_footer' => true]);

    // блокові стилі WordPress тема не використовує — мінус ~100 КБ на кожній сторінці
    if (!is_admin()) {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('global-styles');
        wp_dequeue_style('classic-theme-styles');
    }
}, 20);

// стилі WooCommerce беремо лише базові, решту пишемо самі
add_filter('woocommerce_enqueue_styles', function ($styles) {
    unset($styles['woocommerce-layout'], $styles['woocommerce-smallscreen']);
    return $styles;
});

// прибираємо емодзі-скрипт WordPress, він не потрібен
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

/** Лічильник товарів у кошику оновлюється через AJAX після додавання. */
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    $fragments['span.cart-count'] = '<span class="cart-count">' . (int) WC()->cart->get_cart_contents_count() . '</span>';
    return $fragments;
});

/** Простий helper для іконок у шаблонах. */
function sadok_icon(string $name): string
{
    $icons = [
        'cart'   => '<path d="M3 4h2l2.4 11h11L21 7H6.2"/><circle cx="9" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/>',
        'truck'  => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/>',
        'leaf'   => '<path d="M5 19C5 10 11 5 20 4c0 9-5 15-14 15zM5 19l7-7"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? '') . '</svg>';
}
