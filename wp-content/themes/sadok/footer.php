</main>

<footer class="footer">
    <div class="container footer__grid">
        <div>
            <p class="footer__logo"><?php bloginfo('name'); ?></p>
            <p>Вирощуємо плодові саджанці в Черкаській області. Кожен саджанець з бирочкою сорту і гарантією приживлення.</p>
        </div>
        <div>
            <p class="footer__title">Покупцям</p>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Каталог</a>
            <a href="<?php echo esc_url(home_url('/dostavka/')); ?>">Доставка і оплата</a>
            <a href="<?php echo esc_url(home_url('/garantiya/')); ?>">Гарантія приживлення</a>
        </div>
        <div>
            <p class="footer__title">Контакти</p>
            <a href="tel:+380671234567">067 123 45 67</a>
            <a href="mailto:info@sadok.example">info@sadok.example</a>
            <span>Пн–Сб 8:00–18:00</span>
        </div>
    </div>
    <div class="container footer__bottom">© <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?></div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
