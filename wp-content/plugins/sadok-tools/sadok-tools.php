<?php
/**
 * Plugin Name: Садок — інструменти магазину
 * Description: Імпорт товарів з Excel, фід для Prom.ua, оплата LiqPay, відділення Нової пошти в оформленні.
 * Version: 1.0.0
 * Author: Andrii Valko
 * Requires Plugins: woocommerce
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;

define('SADOK_TOOLS_DIR', __DIR__);

require_once __DIR__ . '/includes/xlsx-reader.php';
require_once __DIR__ . '/includes/import.php';
require_once __DIR__ . '/includes/prom-feed.php';
require_once __DIR__ . '/includes/nova-poshta.php';

// платіжний шлюз підключаємо, коли WooCommerce вже завантажив свої класи
add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    require_once __DIR__ . '/includes/liqpay-gateway.php';
    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'Sadok_LiqPay_Gateway';
        return $gateways;
    });
});

register_activation_hook(__FILE__, function () {
    sadok_prom_feed_rewrite();
    flush_rewrite_rules();
});

/** Налаштування плагіна в WooCommerce → Налаштування → Інтеграція не потрібні — тримаємо їх у одному місці. */
add_action('admin_menu', function () {
    add_submenu_page('woocommerce', 'Садок: налаштування', 'Садок: налаштування', 'manage_woocommerce', 'sadok-settings', function () {
        if (isset($_POST['sadok_settings']) && check_admin_referer('sadok_settings')) {
            update_option('sadok_np_api_key', sanitize_text_field(wp_unslash($_POST['np_api_key'] ?? '')));
            echo '<div class="notice notice-success"><p>Збережено.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Садок: налаштування</h1>
            <form method="post">
                <?php wp_nonce_field('sadok_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="np_api_key">API-ключ Нової пошти</label></th>
                        <td>
                            <input type="text" id="np_api_key" name="np_api_key" class="regular-text" value="<?php echo esc_attr(get_option('sadok_np_api_key')); ?>">
                            <p class="description">Особистий кабінет НП → Налаштування → Безпека → API-ключ.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Фід для Prom.ua</th>
                        <td><code><?php echo esc_html(home_url('/prom-feed.xml')); ?></code></td>
                    </tr>
                </table>
                <p><button class="button button-primary" name="sadok_settings" value="1">Зберегти</button></p>
            </form>
        </div>
        <?php
    });
});
