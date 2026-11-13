<?php
/**
 * Головна сторінка магазину.
 */

get_header();

$categories = get_terms([
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'parent'     => 0,
    'exclude'    => [get_option('default_product_cat')],
]);

$bestsellers = wc_get_products(['status' => 'publish', 'featured' => true, 'limit' => 4]);
if (count($bestsellers) < 4) {
    $bestsellers = wc_get_products(['status' => 'publish', 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC']);
}
?>

<section class="hero">
    <div class="container hero__grid">
        <div class="hero__text">
            <p class="eyebrow">Власний розсадник · Умань</p>
            <h1>Саджанці, які приживаються</h1>
            <p class="lead">Яблуні, груші, черешні та ягідні кущі з нашого розсадника. Перевірені сорти для українського клімату, доставка Новою поштою по всій країні.</p>
            <div class="hero__actions">
                <a class="btn" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Перейти до каталогу</a>
                <a class="btn btn--ghost" href="<?php echo esc_url(home_url('/garantiya/')); ?>">Гарантія приживлення</a>
            </div>
        </div>
        <div class="hero__image">
            <img src="https://images.unsplash.com/photo-1622383563227-04401ab4e5ea?w=1100&q=75&auto=format&fit=crop" alt="Посадка саджанця" fetchpriority="high">
        </div>
    </div>
</section>

<section class="container perks">
    <div><?php echo sadok_icon('leaf'); ?><b>Вирощуємо самі</b><span>Без перекупників, кожен сорт підписаний</span></div>
    <div><?php echo sadok_icon('truck'); ?><b>Доставка 1–3 дні</b><span>Нова пошта, корені в зволоженій упаковці</span></div>
    <div><?php echo sadok_icon('shield'); ?><b>Гарантія приживлення</b><span>Не прижився — замінимо навесні</span></div>
</section>

<?php if ($categories) : ?>
<section class="container section">
    <h2 class="section__title">Що садимо цього сезону</h2>
    <div class="categories">
        <?php foreach ($categories as $cat) :
            $thumb_id = get_term_meta($cat->term_id, 'thumbnail_id', true);
            ?>
            <a class="category" href="<?php echo esc_url(get_term_link($cat)); ?>">
                <?php echo $thumb_id ? wp_get_attachment_image($thumb_id, 'medium', false, ['loading' => 'lazy']) : ''; ?>
                <span><?php echo esc_html($cat->name); ?><small><?php echo (int) $cat->count; ?> сортів</small></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="container section">
    <div class="section__head">
        <h2 class="section__title">Хіти продажів</h2>
        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Увесь каталог →</a>
    </div>
    <ul class="products columns-4">
        <?php
        foreach ($bestsellers as $item) {
            $GLOBALS['product'] = $item;
            $post_object = get_post($item->get_id());
            setup_postdata($GLOBALS['post'] = $post_object);
            wc_get_template_part('content', 'product');
        }
        wp_reset_postdata();
        ?>
    </ul>
</section>

<?php get_footer(); ?>
