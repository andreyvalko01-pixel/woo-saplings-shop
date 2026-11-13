<?php
get_header();
?>
<div class="container page">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class('entry'); ?>>
                <h1><?php the_title(); ?></h1>
                <div class="entry__content"><?php the_content(); ?></div>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p>Нічого не знайдено.</p>
    <?php endif; ?>
</div>
<?php
get_footer();
