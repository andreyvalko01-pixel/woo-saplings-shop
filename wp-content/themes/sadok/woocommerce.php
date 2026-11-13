<?php
/**
 * Обгортка для всіх сторінок WooCommerce (каталог, товар, категорії).
 * woocommerce_content() не викликає хук before_main_content, тому каталог з фільтрами збираємо тут.
 */

get_header();
$is_catalog = is_shop() || is_product_taxonomy();
?>
<div class="container shop-page">
    <?php if ($is_catalog) : ?>
        <?php woocommerce_breadcrumb(); ?>
        <div class="catalog">
            <aside class="catalog__side"><?php sadok_render_filters(); ?></aside>
            <div class="catalog__main"><?php woocommerce_content(); ?></div>
        </div>
    <?php else : ?>
        <?php woocommerce_content(); ?>
    <?php endif; ?>
</div>
<?php
get_footer();
