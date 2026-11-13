<?php
/**
 * Доставка Новою поштою: замість "адреси" — місто і відділення зі списку.
 * Список відділень підтягується з API НП (ключ — WooCommerce → Садок: налаштування).
 */

defined('ABSPATH') || exit;

function sadok_np_request(string $model, string $method, array $props): array
{
    $key = get_option('sadok_np_api_key');
    if (!$key) {
        return [];
    }
    $cache_key = 'sadok_np_' . md5($model . $method . wp_json_encode($props));
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $response = wp_remote_post('https://api.novaposhta.ua/v2.0/json/', [
        'timeout' => 15,
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode(['apiKey' => $key, 'modelName' => $model, 'calledMethod' => $method, 'methodProperties' => $props]),
    ]);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $data = !empty($body['success']) ? $body['data'] : [];

    // довідник відділень змінюється рідко
    set_transient($cache_key, $data, DAY_IN_SECONDS);
    return $data;
}

// поля оформлення: ховаємо зайве, додаємо відділення
add_filter('woocommerce_checkout_fields', function ($fields) {
    unset($fields['billing']['billing_company'], $fields['billing']['billing_address_2'], $fields['billing']['billing_postcode'], $fields['billing']['billing_state']);

    $fields['billing']['billing_city']['label'] = 'Місто';
    $fields['billing']['billing_city']['priority'] = 60;
    $fields['billing']['billing_address_1']['label'] = 'Відділення або поштомат Нової пошти';
    $fields['billing']['billing_address_1']['placeholder'] = 'Почніть вводити місто вище';
    $fields['billing']['billing_address_1']['priority'] = 70;
    $fields['billing']['billing_phone']['priority'] = 25;
    return $fields;
});

// AJAX: відділення за назвою міста
add_action('wp_ajax_sadok_np_warehouses', 'sadok_np_warehouses_ajax');
add_action('wp_ajax_nopriv_sadok_np_warehouses', 'sadok_np_warehouses_ajax');

function sadok_np_warehouses_ajax(): void
{
    $city = sanitize_text_field(wp_unslash($_GET['city'] ?? ''));
    if (mb_strlen($city) < 2) {
        wp_send_json([]);
    }
    $warehouses = sadok_np_request('AddressGeneral', 'getWarehouses', ['CityName' => $city, 'Limit' => '300']);
    wp_send_json(array_map(fn($w) => $w['Description'], $warehouses));
}

// на сторінці оформлення перетворюємо поле адреси на список відділень
add_action('wp_footer', function () {
    if (!is_checkout() || !get_option('sadok_np_api_key')) {
        return;
    }
    ?>
    <datalist id="np-warehouses"></datalist>
    <script>
      (() => {
        const city = document.getElementById('billing_city');
        const address = document.getElementById('billing_address_1');
        const list = document.getElementById('np-warehouses');
        if (!city || !address) return;
        address.setAttribute('list', 'np-warehouses');

        let timer;
        city.addEventListener('input', () => {
          clearTimeout(timer);
          timer = setTimeout(async () => {
            const url = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=sadok_np_warehouses&city=' + encodeURIComponent(city.value);
            const items = await (await fetch(url)).json();
            list.innerHTML = items.map((w) => `<option value="${w.replace(/"/g, '&quot;')}">`).join('');
          }, 400);
        });
      })();
    </script>
    <?php
});
