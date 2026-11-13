<?php
/**
 * SEO: розмітка Schema.org для магазину і хлібних крихт, meta description.
 * Розмітку Product для сторінок товарів WooCommerce генерує сам.
 */

defined('ABSPATH') || exit;

add_action('wp_head', function () {
    $data = [
        '@context'  => 'https://schema.org',
        '@type'     => 'Store',
        'name'      => get_bloginfo('name'),
        'url'       => home_url('/'),
        'telephone' => '+380671234567',
        'address'   => [
            '@type'           => 'PostalAddress',
            'addressLocality' => 'Умань',
            'addressRegion'   => 'Черкаська область',
            'addressCountry'  => 'UA',
        ],
        'openingHours' => 'Mo-Sa 08:00-18:00',
    ];
    if (is_front_page()) {
        printf('<script type="application/ld+json">%s</script>' . "\n", wp_json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    // опис для пошуковиків: короткий опис товару або категорії
    $description = '';
    if (is_product()) {
        $description = wp_strip_all_tags(get_the_excerpt());
    } elseif (is_product_category()) {
        $description = wp_strip_all_tags(term_description());
    } elseif (is_front_page()) {
        $description = 'Плодові саджанці з власного розсадника: яблуні, груші, черешні, ягідні кущі. Доставка Новою поштою по Україні.';
    }
    if ($description) {
        printf('<meta name="description" content="%s">' . "\n", esc_attr(wp_trim_words($description, 30, '…')));
    }
}, 5);

// хлібні крихти WooCommerce у вигляді розмітки BreadcrumbList
add_filter('woocommerce_breadcrumb_defaults', function ($defaults) {
    $defaults['delimiter'] = '<span class="sep">/</span>';
    $defaults['home'] = 'Головна';
    return $defaults;
});
