<?php
/**
 * Імпорт товарів з Excel (Товари → Імпорт з Excel).
 *
 * Колонки (порядок не важливий, регістр теж):
 *   Артикул | Назва | Категорія | Ціна | Акційна ціна | Вік | Висота | Залишок | Опис | Фото
 *
 * Рядки з однаковою «Назвою», але різним віком/висотою, стають варіаціями одного товару.
 * Повторний імпорт оновлює ціни і залишки за артикулом — дублів не буде.
 */

defined('ABSPATH') || exit;

const SADOK_IMPORT_COLUMNS = [
    'артикул' => 'sku', 'назва' => 'name', 'категорія' => 'category', 'ціна' => 'price',
    'акційна ціна' => 'sale_price', 'вік' => 'vik', 'висота' => 'rozmir', 'залишок' => 'stock',
    'опис' => 'description', 'фото' => 'image',
];

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=product', 'Імпорт з Excel', 'Імпорт з Excel', 'manage_woocommerce', 'sadok-import', 'sadok_import_page');
});

function sadok_import_page(): void
{
    $report = null;
    if (!empty($_FILES['xlsx']['tmp_name']) && check_admin_referer('sadok_import')) {
        try {
            $report = sadok_import_rows(sadok_read_xlsx($_FILES['xlsx']['tmp_name']));
        } catch (Throwable $e) {
            $report = ['error' => $e->getMessage()];
        }
    }
    ?>
    <div class="wrap">
        <h1>Імпорт товарів з Excel</h1>
        <?php if ($report && isset($report['error'])) : ?>
            <div class="notice notice-error"><p><?php echo esc_html($report['error']); ?></p></div>
        <?php elseif ($report) : ?>
            <div class="notice notice-success"><p>
                Створено товарів: <?php echo (int) $report['created']; ?>,
                оновлено: <?php echo (int) $report['updated']; ?>,
                варіацій: <?php echo (int) $report['variations']; ?>.
                <?php if ($report['errors']) : ?><br>Пропущено рядків: <?php echo esc_html(implode('; ', $report['errors'])); ?><?php endif; ?>
            </p></div>
        <?php endif; ?>
        <p>Колонки: <code>Артикул, Назва, Категорія, Ціна, Акційна ціна, Вік, Висота, Залишок, Опис, Фото</code>.
            Рядки з однаковою назвою і різним віком/висотою об'єднуються в один товар з варіаціями.</p>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('sadok_import'); ?>
            <input type="file" name="xlsx" accept=".xlsx" required>
            <button class="button button-primary">Імпортувати</button>
        </form>
    </div>
    <?php
}

/** Атрибут на кшталт pa_vik — створюємо, якщо його ще немає. */
function sadok_ensure_attribute(string $slug, string $label): string
{
    $taxonomy = 'pa_' . $slug;
    if (!taxonomy_exists($taxonomy)) {
        if (!wc_attribute_taxonomy_id_by_name($slug)) {
            wc_create_attribute(['name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order']);
        }
        register_taxonomy($taxonomy, 'product', ['hierarchical' => false]);
    }
    return $taxonomy;
}

/**
 * Латинський slug за офіційною транслітерацією: "2 роки" -> "2-roky".
 * Кириличні slug-и WordPress зберігає як %d1%80..., а фільтри WooCommerce їх обрізають.
 */
function sadok_slug(string $text): string
{
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ie', 'ж' => 'zh',
        'з' => 'z', 'и' => 'y', 'і' => 'i', 'ї' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ю' => 'iu', 'я' => 'ia', "'" => '', '’' => '',
    ];
    return sanitize_title(strtr(mb_strtolower($text), $map));
}

function sadok_term_id(string $taxonomy, string $name): int
{
    $term = get_term_by('name', $name, $taxonomy) ?: wp_insert_term($name, $taxonomy, ['slug' => sadok_slug($name)]);
    return is_array($term) ? (int) $term['term_id'] : (int) $term->term_id;
}

function sadok_category_id(string $path): int
{
    // "Плодові дерева / Яблуні" — вкладені категорії
    $parent = 0;
    foreach (array_map('trim', explode('/', $path)) as $name) {
        $term = term_exists($name, 'product_cat', $parent) ?: wp_insert_term($name, 'product_cat', ['parent' => $parent, 'slug' => sadok_slug($name)]);
        $parent = (int) $term['term_id'];
    }
    return $parent;
}

function sadok_attach_image(string $url, int $product_id): int
{
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // одне фото не завантажуємо двічі при повторному імпорті
    $existing = get_posts(['post_type' => 'attachment', 'meta_key' => '_sadok_source', 'meta_value' => $url, 'fields' => 'ids', 'numberposts' => 1]);
    if ($existing) {
        return $existing[0];
    }
    $id = media_sideload_image($url, $product_id, null, 'id');
    if (is_wp_error($id)) {
        return 0;
    }
    update_post_meta($id, '_sadok_source', $url);
    return $id;
}

function sadok_import_rows(array $rows): array
{
    $header = array_map(fn($h) => mb_strtolower(trim($h)), array_shift($rows));
    $map = [];
    foreach ($header as $i => $title) {
        if (isset(SADOK_IMPORT_COLUMNS[$title])) {
            $map[SADOK_IMPORT_COLUMNS[$title]] = $i;
        }
    }
    foreach (['sku', 'name', 'price'] as $required) {
        if (!isset($map[$required])) {
            throw new RuntimeException('Немає обов\'язкової колонки: ' . array_search($required, SADOK_IMPORT_COLUMNS, true));
        }
    }

    $report = ['created' => 0, 'updated' => 0, 'variations' => 0, 'errors' => []];
    $groups = [];
    foreach ($rows as $n => $row) {
        $item = [];
        foreach ($map as $field => $i) {
            $item[$field] = $row[$i] ?? '';
        }
        if (!$item['sku'] || !$item['name'] || !is_numeric(str_replace(',', '.', $item['price']))) {
            $report['errors'][] = 'рядок ' . ($n + 2);
            continue;
        }
        $groups[$item['name']][] = $item;
    }

    $vik = sadok_ensure_attribute('vik', 'Вік');
    $rozmir = sadok_ensure_attribute('rozmir', 'Висота');

    foreach ($groups as $name => $items) {
        $first = $items[0];
        $is_variable = count($items) > 1;
        $parent_sku = $is_variable ? preg_replace('/-[^-]+$/', '', $first['sku']) : $first['sku'];

        $product_id = wc_get_product_id_by_sku($parent_sku);
        $product = $product_id ? wc_get_product($product_id) : ($is_variable ? new WC_Product_Variable() : new WC_Product_Simple());
        $report[$product_id ? 'updated' : 'created']++;

        $product->set_name($name);
        $product->set_sku($parent_sku);
        if (!empty($first['description'])) {
            $product->set_description($first['description']);
            $product->set_short_description(wp_trim_words($first['description'], 25));
        }
        if (!empty($first['category'])) {
            $product->set_category_ids([sadok_category_id($first['category'])]);
        }

        // атрибути: збираємо всі значення віку/висоти з рядків групи
        $attributes = [];
        foreach ([$vik => 'vik', $rozmir => 'rozmir'] as $taxonomy => $field) {
            $values = array_unique(array_filter(array_column($items, $field)));
            if (!$values) {
                continue;
            }
            $attr = new WC_Product_Attribute();
            $attr->set_id(wc_attribute_taxonomy_id_by_name($field));
            $attr->set_name($taxonomy);
            $attr->set_options(array_map(fn($v) => sadok_term_id($taxonomy, $v), $values));
            $attr->set_visible(true);
            $attr->set_variation($is_variable);
            $attributes[] = $attr;
        }
        $product->set_attributes($attributes);

        if (!$is_variable) {
            sadok_apply_prices($product, $first);
        }
        $product_id = $product->save();

        if (!empty($first['image']) && !$product->get_image_id()) {
            $product->set_image_id(sadok_attach_image($first['image'], $product_id));
            $product->save();
        }

        if ($is_variable) {
            foreach ($items as $item) {
                $variation_id = wc_get_product_id_by_sku($item['sku']);
                $variation = $variation_id ? wc_get_product($variation_id) : new WC_Product_Variation();
                $variation->set_parent_id($product_id);
                $variation->set_sku($item['sku']);
                $variation->set_attributes(array_filter([
                    $vik    => $item['vik'] ? get_term_by('name', $item['vik'], $vik)->slug : null,
                    $rozmir => $item['rozmir'] ? get_term_by('name', $item['rozmir'], $rozmir)->slug : null,
                ]));
                sadok_apply_prices($variation, $item);
                $variation->save();
                $report['variations']++;
            }
            WC_Product_Variable::sync($product_id);
        }
    }
    return $report;
}

function sadok_apply_prices(WC_Product $product, array $item): void
{
    $product->set_regular_price((string) (float) str_replace(',', '.', $item['price']));
    $sale = str_replace(',', '.', $item['sale_price'] ?? '');
    $product->set_sale_price(is_numeric($sale) && $sale > 0 ? (string) (float) $sale : '');
    if (isset($item['stock']) && $item['stock'] !== '') {
        $product->set_manage_stock(true);
        $product->set_stock_quantity((int) $item['stock']);
    }
}
