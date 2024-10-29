<?php
/*
Plugin Name: Bank Slip for WooCommerce
Text Domain: bank-slip-for-woocommerce
Description: Generates bank slips for checks, cash or all other payment method.
Author: N.O.U.S. Open Useful and Simple
Author URI: https://apps.avecnous.eu/?mtm_campaign=wp-plugin&mtm_kwd=bank-slip-for-woocommerce&mtm_medium=dashboard&mtm_source=donate
Version: 1.0.2
*/

$WooCommerce_Orders_BankSlip = new WooCommerce_Orders_BankSlip();
class WooCommerce_Orders_BankSlip{
    var $bulk_actions;

    function __construct(){
        add_action( 'admin_footer', array( $this, 'bulk_actions' ), 90 );
        add_action( 'wp_ajax_wc_order_bankslip', array( $this, 'bulk_order_bankslip' ) );
        add_action( 'wc_order_bankslip-wc-order-bankslip-pdf', array( $this, 'generate_bankslip_pdf' ), 10, 4 );
        add_action('manage_shop_order_posts_custom_column', array($this, 'columns_content'), 90, 2);
        add_action('manage_posts_extra_tablenav', array($this, 'restrict_manage_orders'), 90);

        $this->bulk_actions = array('wc-order-bankslip-pdf'=>__('PDF Bank slip', 'bank-slip-for-woocommerce'));
    }

    /**
     * Add actions to menu
     */
    public function bulk_actions() {
        global $post_type;
        if( $post_type != 'shop_order' )
            return;

        wp_enqueue_style( 'wc-order-bankslip', plugins_url('css/bank-slip.css', __FILE__)  );
        // Register the script
        wp_register_script( 'wc-order-bankslip', plugins_url('js/bank-slip.js', __FILE__), array('jquery')  );

        // Localize the script with new data
        $l10n = array(
          'bulk_actions' => apply_filters( 'wc_order_bankslip', $this->bulk_actions ),
          'messages' => array(
                'no_orders' => __('You have to select order(s) first!', 'bank-slip-for-woocommerce'),
          ),
        );
        wp_localize_script( 'wc-order-bankslip', 'wc_order_bankslip', $l10n );

        // Enqueued script with localized data.
        wp_enqueue_script( 'wc-order-bankslip' );
    }

    public function bulk_order_bankslip(){
        $template = filter_input(INPUT_GET, 'document_type');
        $order_ids = explode(',', filter_input(INPUT_GET, 'order_ids'));

        $default_account_no  = get_option('wc_order_bank_slip_last_account_no', 'xxxxx');
        $date = (false != $date = filter_input(INPUT_GET, 'date')) ? sanitize_text_field($date) : date('d/m/Y');
        $piece_no = (false != $piece_no = filter_input(INPUT_GET, 'piece_no')) ? sanitize_text_field($piece_no) : date('Ymd', strtotime($date));
        $account_no = (false != $account_no = filter_input(INPUT_GET, 'account_no')) ? sanitize_text_field($account_no) : $default_account_no;

        update_option('wc_order_bank_slip_last_account_no', $account_no);
        if(!isset($this->bulk_actions[$template])){
            wp_die('Unrecognized action.');
            exit;
        }

        $orders = array();
        foreach ($order_ids as $id) {
            if(is_numeric($id)){
                $orders[$id] = new WC_Order($id);
            }
        }
        do_action( 'wc_order_bankslip-'.$template, $orders, $date, $piece_no, $account_no);

        exit;
    }

    public function generate_bankslip_pdf($orders, $date='', $piece_no='', $account_no=''){
        $inc_path = plugin_dir_path( __FILE__ );
        require_once $inc_path.'/vendor/autoload.php';
        require_once $inc_path.'/pdf/bank-slip-pdf.php';

        $rows = [];
        $sortby_reference = [];
        $sortby_name = [];
        $sortby_amount = [];
        $total = 0;
        foreach ($orders as $order_id => $order) {
          $order_total = $order->get_total();
          $total += $order_total;
          $name = apply_filters('wc:bankslip:row:name', $order->get_billing_company(). ' ' . $order->get_billing_first_name(). ' ' . $order->get_billing_last_name(), $order);
          $reference = apply_filters('wc:bankslip:row:reference', get_post_meta($order_id, '_transaction_id', true), $order);

          array_push($rows, [
            'name'=>$name,
            'reference'=>$reference.' ',
            'account'=>number_format($order_total, 2, ',', ' ' )
          ]);
          array_push($sortby_name, $name);
          array_push($sortby_reference, $reference);
          array_push($sortby_amount, $order_total);
          update_post_meta($order_id, '_wc_order_bank_slip_date', $date);
          update_post_meta($order_id, '_wc_order_bank_slip_no', $piece_no);
        }

        $bankslip = new PDFBankSlip(
            __('Bank slip', 'bank-slip-for-woocommerce'),
            [
              __('Date:', 'bank-slip-for-woocommerce') => $date,
              __('Piece number:', 'bank-slip-for-woocommerce')=>$piece_no,
              __('Account number:', 'bank-slip-for-woocommerce')=>$account_no,
              __('Number of pieces:', 'bank-slip-for-woocommerce')=>count($orders),
              __('Total amount:', 'bank-slip-for-woocommerce')=>number_format($total, 2, ',', ' ' ),
            ]
        );

        array_multisort($sortby_reference, SORT_ASC, $sortby_name, SORT_ASC, $sortby_amount, SORT_ASC, $rows);

        $i=0;
        foreach ($rows as $row) {
          $i++;
          $bankslip->AddRow(
            $i,
            $row['name'],
            $row['reference'],
            $row['account']
          );
        }
        $bankslip->AddRow();
        $bankslip->AddRow('', __('Total', 'bank-slip-for-woocommerce'), ' ', number_format($total, 2, ',', ' ' ));

        $bankslip->AliasNbPages();
        $bankslip->output('I', 'bankslip.pdf');
    }


    function columns_content($column_name, $post_id) {
        if ($column_name == 'order_status') {
            $bankslip_date = get_post_meta($post_id, '_wc_order_bank_slip_date', true);
            $bankslip_no = get_post_meta($post_id, '_wc_order_bank_slip_no', true);
            if('' !== $bankslip_date || '' !== $bankslip_no){
                echo '<span class="description tips" data-tip="'.esc_attr(sprintf(__('Bank slip: %s @ %s', 'bank-slip-for-woocommerce'), $bankslip_no, $bankslip_date)).'">'.$bankslip_no.'</span>';
            }
        }
    }

    function restrict_manage_orders($value = ''){
        global $woocommerce, $typenow;
        if ('shop_order' != $typenow) {
            return;
        }

        ?>
        <div id="wc-order-bankslip-options">
            <div>
                <label>
                    <?php _e('Date:', 'bank-slip-for-woocommerce'); ?>
                    <input type="text" id="wc-order-bankslip-options-date" value="<?php echo esc_attr(date_i18n(get_option('date_format'))); ?>"/>
                </label>
                <label>
                    <?php _e('Piece number:', 'bank-slip-for-woocommerce'); ?>
                    <input type="text" id="wc-order-bankslip-options-piece_no" value="<?php echo esc_attr(apply_filters('wc:bankslip:piece_no', date('Ymd'))); ?>"/>
                </label>
                <label>
                    <?php _e('Account number:', 'bank-slip-for-woocommerce'); ?>
                    <input type="text" id="wc-order-bankslip-options-account_no" value="<?php echo esc_attr(get_option('wc_order_bank_slip_last_account_no', 'xxxxx')); ?>"/>
                </label>
                <a class="button button-primary" id="wc-order-bankslip-options-btn"><?php _e('Generate bank slip', 'bank-slip-for-woocommerce'); ?></a>
            </div>
        </div>
        <?php
    }
}
