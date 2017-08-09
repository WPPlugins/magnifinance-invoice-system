<?php
/*
  Plugin Name: MagniFinance Invoice System
  Plugin URI: http://www.webds.pt
  Description: MagniFinance Invoice System enables you to create simplified and normal invoices using MagniFinance with WooCommerce
  Version: 1.0
  Author: WebDS
  Author URI: http://www.webds.pt
  License: GPLv2
 */

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && !class_exists('whmcsis')) {


    class woocommerce_magnifinance {

        function __construct() {
            global $magnifinance;

            if (!class_exists("MAGNIFINANCE_WEB")) {
                require_once('lib/magnifinance.class.php');

                $magnifinance = new MAGNIFINANCE_WEB();
            }

            $this->load_plugin_textdomain();

            $this->loginemail = get_option('wc_mf_loginemail');
            $this->logintoken = get_option('wc_mf_api_logintoken');

            add_action('admin_init', array(&$this, 'settings_init'));
            add_action('admin_menu', array(&$this, 'menu'));


            add_action('woocommerce_order_status_on-hold', array($this, 'on_hold'));
            add_action('woocommerce_order_status_pending', array($this, 'on_hold'));
            add_action('woocommerce_order_status_processing', array($this, 'process'));
            //add_action('woocommerce_order_status_completed',array(&$this,'process'));

            add_action('woocommerce_order_actions', array($this, 'my_woocommerce_order_actions'), 10, 1);
            add_action('woocommerce_order_action_create_mf', array($this, 'create_mf'), 10, 1);
            add_action('woocommerce_order_action_update_mf', array($this, 'update_mf'), 10, 1);
        }

        function load_plugin_textdomain() {
            load_plugin_textdomain('wc_magnifinance', false, plugin_basename(dirname(__FILE__)) . "/languages");
        }

        function my_woocommerce_order_actions($actions) {
            $actions['create_mf'] = "Criar Factura (MagniFinance)";
            $actions['update_mf'] = "Actualizar (MagniFinance)";
            return $actions;
        }

        function create_mf($order) {
            // Do something here with the WooCommerce $order object
            $this->process($order->id);
        }

        function update_mf($order) {
            // Do something here with the WooCommerce $order object
            $this->on_hold($order->id);
        }

        function menu() {
            add_submenu_page('woocommerce', __('MagniFinance', 'wc_magnifinance'), __('MagniFinance', 'wc_magnifinance'), 'manage_woocommerce', 'woocommerce_magnifinance', array(&$this, 'options_page'));
        }

        function settings_init() {

            wp_enqueue_style('woocommerce_webdsmf_css', plugin_dir_url(__FILE__) . '/assets/css/webdsmf.css');
            wp_enqueue_script('woocommerce_webdsmf_js', plugin_dir_url(__FILE__) . '/assets/js/webdsmf.js', array('jquery'), null, true);

            $settings = array();
            $settings[] = $this->formMagnifinance();

            foreach ($settings as $sections => $section) {
                add_settings_section($section['name'], $section['title'], array(&$this, $section['name']), $section['page']);
                foreach ($section['settings'] as $setting => $option) {
                    add_settings_field($option['name'], $option['title'], array(&$this, $option['name']), $section['page'], $section['name']);
                    register_setting($section['page'], $option['name']);
                    $this->$option['name'] = get_option($option['name']);
                }
            }
        }

        function formMagnifinance() {
            return array(
                'name' => 'wc_mf_settings',
                'title' => __('Configuração de MagniFinance para WooCommerce', 'wc_magnifinance'),
                'page' => 'woocommerce_magnifinance',
                'settings' => array(
                    array(
                        'name' => 'wc_mf_loginemail',
                        'title' => __('Email', 'wc_magnifinance'),
                    ),
                    array(
                        'name' => 'wc_mf_api_logintoken',
                        'title' => __('API Token', 'wc_magnifinance'),
                    ),
                    array(
                        'name' => 'wc_mf_vat',
                        'title' => __('IVA', 'wc_magnifinance'),
                    ),
                    array(
                        'name' => 'wc_mf_create_invoice',
                        'title' => __('Criar Factura', 'wc_magnifinance'),
                    ),
                    array(
                        'name' => 'wc_mf_send_invoice',
                        'title' => __('Enviar Factura', 'wc_magnifinance'),
                    ),
                    array(
                        'name' => 'wc_mf_create_simplified_invoice',
                        'title' => __('Facturas Simplificadas', 'wc_magnifinance'),
                    )
                    ,
                    array(
                        'name' => 'wc_mf_create_paid_invoice',
                        'title' => __('Marcar como pago/fechado', 'wc_magnifinance'),
                    )
                ),
            );
        }

        function wc_mf_settings() {
            echo '<p>' . __('Por favor preencha os campos abaixo.<br> O MagniFinance para WooCommerce cria as facturas quando o estado da encomenda é actualizado para processando.', 'wc_magnifinance') . '</p>';
        }

        function wc_mf_settings_license_register() {
            echo '<p>' . __('Registe-se para obter uma licença válida para activar o Plugin', 'wc_magnifinance') . '</p>';
        }

        function wc_mf_settings_license_activate() {
            echo '<p>' . __('Insira uma licença válida de forma a activar o Plugin', 'wc_magnifinance') . '</p>';
        }

        function wc_mf_license_key() {
            echo '<input type="text" name="wc_mf_license_key" id="wc_mf_license_key" value="' . get_option('wc_mf_license_key') . '" />';
            echo ' <label for="wc_mf_license_key">A licença enviada pela WebDS.</label>';
        }

        function wc_mf_license_name() {
            echo '<input type="text" name="wc_mf_license_name" id="wc_mf_license_name" value="' . get_option('wc_mf_license_name') . '"/>';
            echo ' <label for="wc_mf_license_name"></label>';
        }

        function wc_mf_license_lastname() {
            echo '<input type="text" name="wc_mf_license_lastname" id="wc_mf_license_lastname" value="' . get_option('wc_mf_license_lastname') . '"/>';
            echo ' <label for="wc_mf_license_lastname"></label>';
        }

        function wc_mf_license_email() {
            echo '<input type="email" name="wc_mf_license_email" id="wc_mf_license_email" value="' . get_option('wc_mf_license_email') . '"/>';
            echo ' <label for="wc_mf_license_email">(Obrigatório)</label>';
        }

        function wc_mf_license_company_name() {
            echo '<input type="text" name="wc_mf_license_company_name" id="wc_mf_license_company_name" value="' . get_option('wc_mf_license_company_name') . '"/>';
            echo ' <label for="wc_mf_license_company_name"></label>';
        }

        function wc_mf_loginemail() {
            echo '<input type="text" name="wc_mf_loginemail" id="wc_mf_loginemail" value="' . get_option('wc_mf_loginemail') . '" />';
            echo ' <label for="wc_mf_loginemail">Email usado para autenticar-se no MagniFinance</label>';
        }

        function wc_mf_api_logintoken() {
            echo '<input type="password" name="wc_mf_api_logintoken" id="wc_mf_api_logintoken" value="' . get_option('wc_mf_api_logintoken') . '" />';
            echo '<label for="wc_mf_api_logintoken">No MagniFinance ir a Config >> Separador Detalhes. No fundo encontra o campo API Token.</label>';
        }

        function wc_mf_vat() {
            echo '<input type="number" name="wc_mf_vat" id="wc_mf_vat" value="' . get_option('wc_mf_vat') . '" />%';
            echo '<label for="wc_mf_loginemail">IVA a aplicar por defeito, ex: 23</label>';
        }

        function wc_mf_create_invoice() {
            $checked = (get_option('wc_mf_create_invoice') == 1) ? 'checked="checked"' : '';
            echo '<input type="hidden" name="wc_mf_create_invoice" value="0" />';
            echo '<input type="checkbox" name="wc_mf_create_invoice" id="wc_mf_create_invoice" value="1" ' . $checked . ' />';
            echo ' <label for="wc_mf_create_invoice">Criar factura para novas encomendas, em modo de rascunho (<i>recomendado</i>).</label>';
        }

        function wc_mf_send_invoice() {
            $checked = (get_option('wc_mf_send_invoice') == 1) ? 'checked="checked"' : '';
            echo '<input type="hidden" name="wc_mf_send_invoice" value="0" />';
            echo '<input type="checkbox" name="wc_mf_send_invoice" id="wc_mf_send_invoice" value="1" ' . $checked . ' />';
            echo ' <label for="wc_mf_send_invoice">Enviar email para o cliente com a factura em anexo (<i>recomendado</i>).</label>';
        }

        function wc_mf_create_simplified_invoice() {
            $checked = (get_option('wc_mf_create_simplified_invoice') == 1) ? 'checked="checked"' : '';
            echo '<input type="hidden" name="wc_mf_create_simplified_invoice" value="0" />';
            echo '<input type="checkbox" name="wc_mf_create_simplified_invoice" id="wc_mf_create_simplified_invoice" value="1" ' . $checked . ' />';
            echo ' <label for="wc_mf_create_simplified_invoice">Se activo todas as facturas serão geradas como factura Simplificada. Apenas disponível para Portugal.</label>';
        }

        function wc_mf_create_paid_invoice() {
            $checked = (get_option('wc_mf_create_paid_invoice') == 1) ? 'checked="checked"' : '';
            echo '<input type="hidden" name="wc_mf_create_paid_invoice" value="0" />';
            echo '<input type="checkbox" name="wc_mf_create_paid_invoice" id="wc_mf_create_paid_invoice" value="1" ' . $checked . ' />';
            echo ' <label for="wc_mf_create_paid_invoice">Se activada irá marcar as Facturas como Pagas/Fechadas quando a encomenda transitar para o estado <strong>Em processamento</strong>.</label>';
        }

        function options_page() {
            ?>
            <div class="wrap woocommerce">
                <form method="post" id="mainform" action="options.php">
                    <div class="icon32 icon32-woocommerce-settings" id="icon-woocommerce"><br /></div>
                    <a  class="webds_mf_logo" href="http://www.webds.pt" target="_blank"><img src="http://www.webds.pt/webds_logomail.png" alt="WebDS" /></a>
                    <center>
                        <h2><?php _e('MagniFinance para WooCommerce', 'wc_magnifinance'); ?></h2>
                        <small>by WebDS</small>
                    </center>
                    <?php settings_fields('woocommerce_magnifinance'); ?>
                    <?php do_settings_sections('woocommerce_magnifinance'); ?>
                    <p class="submit"><input type="submit" class="button-primary" value="Guardar" /></p>
                </form>
                <div class="webds_mf_footer">
                    Uma empresa<br/>
                    <a href="https://www.webhs.pt"><img src="https://www.webhs.pt/wp-content/uploads/2014/02/logo.png" alt="WebHS" /></a>
                </div>
            </div>
            <?php
        }

        function on_hold($order_id) {
            global $magnifinance;
            if (get_option('wc_mf_create_invoice') == 0)
                return;


            $order = new WC_Order($order_id);

            $invoice = $magnifinance->prepInvoice($order, $order_id);


            $result = $magnifinance->Invoice_Create($invoice);
            if (!empty($result->InvoiceIdOut)) {

                $order->add_order_note(__('Factura gerada em MagniFinance como rascunho', 'wc_magnifinance') . ' #' . $result->InvoiceIdOut);

                add_post_meta($order_id, 'wc_mf_inv_num', $result->InvoiceIdOut, true);
                add_post_meta($order_id, 'wc_mf_inv_fdn_num', $result->FiscalDocumentNumber, true);
            } else {
                $order->add_order_note(__('MagniFinance Factura erro na API:', 'wc_magnifinance') . ': ' . $result->ErrorMessage);
            }
        }

        function process($order_id) {
            global $magnifinance;
            if (get_option('wc_mf_create_invoice') == 0)
                return;

            $order = new WC_Order($order_id);

            $close = true;
            $close_text = 'Closed';
            if (get_option('wc_mf_create_paid_invoice') == 0) {
                $close = false;
                $close_text = 'Draft';
            }

            $invoice = $magnifinance->prepInvoice($order, $order_id, $close);

            $result = $magnifinance->Invoice_Create($invoice);

            if (!empty($result->InvoiceIdOut)) {

                $order->add_order_note(__('Invoice updated in MagniFinance to ' . $close_text, 'wc_magnifinance') . ' #' . $result->InvoiceIdOut);

                add_post_meta($order_id, 'wc_mf_inv_num', $result->InvoiceIdOut, true);
                add_post_meta($order_id, 'wc_mf_inv_fdn_num', $result->FiscalDocumentNumber, true);
            } else {
                $order->add_order_note(__('MagniFinance Factura erro na API:', 'wc_magnifinance') . ': ' . $result->ErrorMessage);
            }
        }

    }

    add_filter('woocommerce_billing_fields', 'woocommerce_nif_billing_fields', 10, 2);
    add_filter('woocommerce_admin_billing_fields', 'woocommerce_nif_admin_billing_fields');
    add_filter('woocommerce_found_customer_details', 'woocommerce_nif_found_customer_details', 10, 3);
    add_action('woocommerce_order_details_after_customer_details', 'woocommerce_nif_order_details_after_customer_details');
    add_filter('woocommerce_email_customer_details_fields', 'woocommerce_nif_email_customer_details_fields', 10, 3);
    add_action('wp_footer', 'wordimpress_custom_checkout_field', 100);

    function wordimpress_custom_checkout_field() {
        if (is_checkout()) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function () {
                    jQuery('input#billing_nifcheck[type="checkbox"]').css({
                        "margin-left": 0,
                        "position": "relative"
                    }).removeClass('form-control');
                    jQuery('p#billing_nif_field').hide();
                    jQuery('input#billing_nifcheck[type="checkbox"]').on('click', function () {
                        jQuery('p#billing_nif_field').slideToggle();
                    });
                });
            </script>
            <?php
        }
    }

    ############NIF################
    //Languages

    function woocommerce_nif_billing_fields($fields, $country) {
        //var_dump($country);
        if ($country == 'PT') {
            $fields['billing_nifcheck'] = array(
                'type' => 'checkbox',
                'label' => __('Factura com contribuinte?', 'wc_magnifinance'),
                'class' => array('form-row-wide'), //Should be an option
                'required' => false, //Should be an option
                'clear' => true, //Should be an option
            );
            $fields['billing_nif'] = array(
                'type' => 'text',
                'label' => __('NIF / NIPC', 'wc_magnifinance'),
                'placeholder' => _x('Número de Identificação Fiscal Português', 'placeholder', 'wc_magnifinance'),
                'class' => array('form-row-first'), //Should be an option
                'required' => false, //Should be an option
                'clear' => true, //Should be an option
            );
        }
        return $fields;
    }

    function woocommerce_nif_admin_billing_fields($billing_fields) {
        //var_dump($billing_fields);
        global $post;
        if ($post->post_type == 'shop_order') {
            $order = new WC_Order($post->ID);
            $countries = new WC_Countries();
            //Costumer is portuguese or it's a new order ?
            if ($order->billing_country == 'PT' || ($order->billing_country == '' && $countries->get_base_country() == 'PT')) {
                $billing_fields['nif'] = array(
                    'label' => __('NIF / NIPC', 'wc_magnifinance'),
                );
            }
        }
        return $billing_fields;
    }

    function woocommerce_nif_found_customer_details($customer_data, $user_id, $type_to_load) {
        if ($type_to_load == 'billing') {
            if (isset($customer_data['billing_country']) && $customer_data['billing_country'] == 'PT') {
                $customer_data['billing_nif'] = get_user_meta($user_id, $type_to_load . '_nif', true);
            }
        }
        return $customer_data;
    }

    function woocommerce_nif_order_details_after_customer_details($order) {
        if ($order->billing_country == 'PT' && $order->billing_nif) {
            ?>
            <tr>
                <th><?php _e('NIF / NIPC', 'wc_magnifinance'); ?>:</th>
                <td><?php echo esc_html($order->billing_nif); ?></td>
            </tr>
            <?php
        }
    }

    function woocommerce_nif_email_customer_details_fields($fields, $sent_to_admin, $order) {
        if ($order->billing_nif) {
            $fields['billing_nif'] = array(
                'label' => __('NIF / NIPC', 'wc_magnifinance'),
                'value' => wptexturize($order->billing_nif)
            );
        }
        return $fields;
    }

    function start_magnifinance() {
        global $wc_magnifinance;

        if (!isset($wc_magnifinance)) {
            $wc_magnifinance = new woocommerce_magnifinance();
        }

        return $wc_magnifinance;
    }

    start_magnifinance();
}