<?php
/**
 * Фід товарів у форматі YML для Prom.ua: https://сайт/prom-feed.xml
 * У кабінеті Prom: Товари → Імпорт → За посиланням, оновлення раз на добу.
 */

defined('ABSPATH') || exit;

function sadok_prom_feed_rewrite(): void
{
    add_rewrite_rule('^prom-feed\.xml$', 'index.php?sadok_prom_feed=1', 'top');
}
add_action('init', 'sadok_prom_feed_rewrite');

add_filter('query_vars', function ($vars) {
    $vars[] = 'sadok_prom_feed';
    return $vars;
});

add_action('template_redirect', function () {
    if (!get_query_var('sadok_prom_feed')) {
        return;
    }

    // фід важкий, кешуємо на годину
    $xml = get_transient('sadok_prom_feed');
    if ($xml === false) {
        $xml = sadok_build_prom_feed();
        set_transient('sadok_prom_feed', $xml, HOUR_IN_SECONDS);
    }
    header('Content-Type: application/xml; charset=utf-8');
    echo $xml;
    exit;
});

// після зміни товару фід треба перебудувати
add_action('woocommerce_update_product', fn() => delete_transient('sadok_prom_feed'));

/** createElement() з другим аргументом не екранує "&" (а він є в посиланнях на варіації), тому лише текстові вузли. */
function sadok_xml_child(DOMDocument $doc, DOMNode $parent, string $name, $value): DOMElement
{
    $el = $parent->appendChild($doc->createElement($name));
    $el->appendChild($doc->createTextNode((string) $value));
    return $el;
}

function sadok_build_prom_feed(): string
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;
    $catalog = $doc->appendChild($doc->createElement('yml_catalog'));
    $catalog->setAttribute('date', current_time('Y-m-d H:i'));
    $shop = $catalog->appendChild($doc->createElement('shop'));
    sadok_xml_child($doc, $shop, 'name', get_bloginfo('name'));
    sadok_xml_child($doc, $shop, 'url', home_url('/'));

    $currencies = $shop->appendChild($doc->createElement('currencies'));
    $currency = $currencies->appendChild($doc->createElement('currency'));
    $currency->setAttribute('id', 'UAH');
    $currency->setAttribute('rate', '1');

    $categories = $shop->appendChild($doc->createElement('categories'));
    foreach (get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true]) as $term) {
        $el = $categories->appendChild($doc->createElement('category'));
        $el->appendChild($doc->createTextNode($term->name));
        $el->setAttribute('id', (string) $term->term_id);
        if ($term->parent) {
            $el->setAttribute('parentId', (string) $term->parent);
        }
    }

    $offers = $shop->appendChild($doc->createElement('offers'));
    $products = wc_get_products(['status' => 'publish', 'limit' => -1]);

    foreach ($products as $product) {
        // кожна варіація — окрема пропозиція з group_id батьківського товару
        $items = $product->is_type('variable')
            ? array_filter(array_map('wc_get_product', $product->get_children()))
            : [$product];

        foreach ($items as $item) {
            $offer = $offers->appendChild($doc->createElement('offer'));
            $offer->setAttribute('id', (string) $item->get_id());
            $offer->setAttribute('available', $item->is_in_stock() ? 'true' : 'false');
            if ($item->is_type('variation')) {
                $offer->setAttribute('group_id', (string) $product->get_id());
            }

            $name = $product->get_name();
            if ($item->is_type('variation')) {
                $name .= ' (' . wc_get_formatted_variation($item, true, false) . ')';
            }
            $cat_ids = $product->get_category_ids();

            sadok_xml_child($doc, $offer, 'name', $name);
            sadok_xml_child($doc, $offer, 'price', $item->get_price());
            if ($item->is_on_sale()) {
                sadok_xml_child($doc, $offer, 'oldprice', $item->get_regular_price());
            }
            sadok_xml_child($doc, $offer, 'currencyId', 'UAH');
            sadok_xml_child($doc, $offer, 'categoryId', (string) ($cat_ids[0] ?? 0));
            sadok_xml_child($doc, $offer, 'vendorCode', $item->get_sku());
            sadok_xml_child($doc, $offer, 'url', $item->get_permalink());
            if ($image = wp_get_attachment_url($product->get_image_id())) {
                sadok_xml_child($doc, $offer, 'picture', $image);
            }
            $offer->appendChild($doc->createElement('description'))
                ->appendChild($doc->createCDATASection(wpautop($product->get_description())));
            if ($item->managing_stock()) {
                sadok_xml_child($doc, $offer, 'quantity_in_stock', (string) $item->get_stock_quantity());
            }
        }
    }
    return $doc->saveXML();
}
