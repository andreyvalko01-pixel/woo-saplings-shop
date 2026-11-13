<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="topbar">
    <div class="container topbar__row">
        <span>🌱 Осінній сезон посадки: відправляємо саджанці з 1 жовтня</span>
        <a href="tel:+380671234567"><?php echo sadok_icon('phone'); ?> 067 123 45 67</a>
    </div>
</div>

<header class="header">
    <div class="container header__row">
        <a class="logo" href="<?php echo esc_url(home_url('/')); ?>">
            <span class="logo__mark"><?php echo sadok_icon('leaf'); ?></span>
            <span><?php bloginfo('name'); ?><small>розсадник з 2009 року</small></span>
        </a>

        <nav class="nav" id="nav">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'fallback_cb'    => function () {
                    // поки меню не налаштоване в адмінці — показуємо категорії магазину
                    echo '<ul><li><a href="' . esc_url(wc_get_page_permalink('shop')) . '">Каталог</a></li>';
                    foreach (get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'number' => 4, 'exclude' => [get_option('default_product_cat')]]) as $cat) {
                        echo '<li><a href="' . esc_url(get_term_link($cat)) . '">' . esc_html($cat->name) . '</a></li>';
                    }
                    echo '</ul>';
                },
            ]);
            ?>
        </nav>

        <form class="search" action="<?php echo esc_url(home_url('/')); ?>">
            <input type="search" name="s" placeholder="Пошук саджанців" value="<?php echo esc_attr(get_search_query()); ?>">
            <input type="hidden" name="post_type" value="product">
        </form>

        <a class="cart-link" href="<?php echo esc_url(wc_get_cart_url()); ?>">
            <?php echo sadok_icon('cart'); ?>
            <span class="cart-count"><?php echo (int) WC()->cart->get_cart_contents_count(); ?></span>
        </a>
        <button class="burger" id="burger" aria-label="Меню"><span></span></button>
    </div>
</header>

<main class="site-main">
