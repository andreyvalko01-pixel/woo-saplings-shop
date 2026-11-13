<?php
/**
 * Каталог: бокова панель фільтрів, розмітка карток, дрібні правки WooCommerce.
 */

defined('ABSPATH') || exit;

/** Атрибути, за якими фільтруємо каталог (WooCommerce сам розуміє ?filter_vik=...&filter_rozmir=...). */
function sadok_filter_attributes(): array
{
    return ['vik' => 'Вік саджанця', 'rozmir' => 'Висота'];
}

function sadok_render_filters(): void
{
    $shop_url = is_product_category() ? get_term_link(get_queried_object()) : wc_get_page_permalink('shop');
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'exclude' => [get_option('default_product_cat')]]);
    ?>
    <form class="filters" method="get" action="<?php echo esc_url($shop_url); ?>">
        <div class="filters__group">
            <p class="filters__title">Категорія</p>
            <?php foreach ($categories as $cat) : ?>
                <a class="filters__link <?php echo is_product_category($cat->slug) ? 'is-active' : ''; ?>" href="<?php echo esc_url(get_term_link($cat)); ?>">
                    <?php echo esc_html($cat->name); ?> <span><?php echo (int) $cat->count; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php foreach (sadok_filter_attributes() as $slug => $title) :
            $terms = get_terms(['taxonomy' => 'pa_' . $slug, 'hide_empty' => true]);
            if (is_wp_error($terms) || !$terms) {
                continue;
            }
            $selected = array_filter(explode(',', sanitize_text_field(wp_unslash($_GET['filter_' . $slug] ?? ''))));
            ?>
            <div class="filters__group">
                <p class="filters__title"><?php echo esc_html($title); ?></p>
                <?php foreach ($terms as $term) : ?>
                    <label class="filters__check">
                        <input type="checkbox" data-filter="<?php echo esc_attr($slug); ?>" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selected, true)); ?>>
                        <?php echo esc_html($term->name); ?>
                    </label>
                <?php endforeach; ?>
                <input type="hidden" name="filter_<?php echo esc_attr($slug); ?>" value="<?php echo esc_attr(implode(',', $selected)); ?>">
            </div>
        <?php endforeach; ?>

        <div class="filters__group">
            <p class="filters__title">Ціна, грн</p>
            <div class="filters__price">
                <input type="number" name="min_price" placeholder="від" value="<?php echo esc_attr($_GET['min_price'] ?? ''); ?>">
                <input type="number" name="max_price" placeholder="до" value="<?php echo esc_attr($_GET['max_price'] ?? ''); ?>">
            </div>
        </div>

        <button class="btn btn--block" type="submit">Показати</button>
        <?php if (!empty($_GET)) : ?>
            <a class="filters__reset" href="<?php echo esc_url($shop_url); ?>">Скинути фільтри</a>
        <?php endif; ?>
    </form>
    <?php
}

// бічна панель WooCommerce не потрібна — у нас своя
remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);

// у картці товару показуємо короткий підпис з "вік / висота"
add_action('woocommerce_after_shop_loop_item_title', function () {
    global $product;
    $parts = [];
    foreach (array_keys(sadok_filter_attributes()) as $slug) {
        $value = $product->get_attribute('pa_' . $slug);
        if ($value) {
            $parts[] = $value;
        }
    }
    if ($parts) {
        echo '<p class="card__meta">' . esc_html(implode(' · ', $parts)) . '</p>';
    }
}, 5);

// замість "Вибрати опції" на варіативних товарах — зрозуміліший текст
add_filter('woocommerce_product_add_to_cart_text', function ($text, $product) {
    return $product->is_type('variable') ? 'Обрати розмір' : 'У кошик';
}, 10, 2);

add_filter('loop_shop_per_page', fn() => 12);

// бейдж "Хіт" для товарів з позначкою "Рекомендований"
add_action('woocommerce_before_shop_loop_item_title', function () {
    global $product;
    if ($product->is_featured()) {
        echo '<span class="badge">Хіт сезону</span>';
    }
}, 9);
