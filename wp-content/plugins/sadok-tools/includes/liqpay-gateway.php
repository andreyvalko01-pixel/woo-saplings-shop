<?php
/**
 * Оплата карткою через LiqPay Checkout.
 * Покупець переходить на сторінку LiqPay, після оплати LiqPay шле callback — замовлення стає «В обробці».
 */

defined('ABSPATH') || exit;

class Sadok_LiqPay_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'sadok_liqpay';
        $this->method_title = 'LiqPay';
        $this->method_description = 'Оплата карткою Visa/Mastercard, Apple Pay, Google Pay через LiqPay.';
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_sadok_liqpay', [$this, 'handle_callback']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled'     => ['title' => 'Увімкнути', 'type' => 'checkbox', 'default' => 'no'],
            'title'       => ['title' => 'Назва', 'type' => 'text', 'default' => 'Карткою онлайн (LiqPay)'],
            'description' => ['title' => 'Опис', 'type' => 'textarea', 'default' => 'Visa, Mastercard, Apple Pay, Google Pay.'],
            'public_key'  => ['title' => 'Public key', 'type' => 'text'],
            'private_key' => ['title' => 'Private key', 'type' => 'password'],
            'sandbox'     => ['title' => 'Тестовий режим', 'type' => 'checkbox', 'default' => 'yes'],
        ];
    }

    private function sign(string $data): string
    {
        $key = $this->get_option('private_key');
        return base64_encode(sha1($key . $data . $key, true));
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        $params = [
            'version'     => 3,
            'public_key'  => $this->get_option('public_key'),
            'action'      => 'pay',
            'amount'      => $order->get_total(),
            'currency'    => $order->get_currency(),
            'description' => sprintf('Замовлення №%s', $order->get_order_number()),
            'order_id'    => (string) $order->get_id(),
            'result_url'  => $this->get_return_url($order),
            'server_url'  => WC()->api_request_url('sadok_liqpay'),
            'language'    => 'uk',
        ];
        if ($this->get_option('sandbox') === 'yes') {
            $params['sandbox'] = 1;
        }

        $data = base64_encode(wp_json_encode($params));
        $order->update_status('pending', 'Очікуємо оплату LiqPay');
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => add_query_arg(['data' => $data, 'signature' => $this->sign($data)], 'https://www.liqpay.ua/api/3/checkout'),
        ];
    }

    /** POST від LiqPay на /?wc-api=sadok_liqpay */
    public function handle_callback(): void
    {
        $data = wp_unslash($_POST['data'] ?? '');
        $signature = wp_unslash($_POST['signature'] ?? '');

        if (!$data || !hash_equals($this->sign($data), $signature)) {
            status_header(400);
            exit('bad signature');
        }

        $payload = json_decode(base64_decode($data), true);
        $order = wc_get_order((int) ($payload['order_id'] ?? 0));
        if (!$order) {
            status_header(404);
            exit;
        }

        if (in_array($payload['status'], ['success', 'sandbox'], true) && !$order->is_paid()) {
            $order->payment_complete($payload['payment_id'] ?? '');
            $order->add_order_note('LiqPay: оплату отримано' . ($payload['status'] === 'sandbox' ? ' (тест)' : ''));
        } elseif (in_array($payload['status'], ['failure', 'error'], true)) {
            $order->update_status('failed', 'LiqPay: ' . ($payload['err_description'] ?? 'помилка оплати'));
        }
        exit('ok');
    }
}
