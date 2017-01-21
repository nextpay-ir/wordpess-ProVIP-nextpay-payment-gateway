<?php

/**
 * Plugin Name: NextPay Gateway For Pro-VIP
 * Created by NextPay.ir
 * author: Nextpay Company
 * ID: @nextpay
 * Date: 09/22/2016
 * Time: 5:05 PM
 * Website: NextPay.ir
 * Email: info@nextpay.ir
 * @copyright 2016
 * @package NextPay_Gateway
 * @version 1.0
 * Description: This plugin lets you use NextPay gateway in pro-vip wp plugin.
 * Plugin URI: http://www.nextpay.ir
 * Author URI: http://www.nextpay.ir
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html.
 */
defined('ABSPATH') or exit;

if (!function_exists('init_Nextpay_gateway_pv_class')) {
    add_action('plugins_loaded', 'init_Nextpay_gateway_pv_class');

    function init_Nextpay_gateway_pv_class()
    {
        add_filter('pro_vip_currencies_list', 'currencies_check');

        function currencies_check($list)
        {
            if (!in_array('IRT', $list)) {
                $list['IRT'] = [
                    'name'   => 'تومان ایران',
                    'symbol' => 'تومان',
                ];
            }

            if (!in_array('IRR', $list)) {
                $list['IRR'] = [
                    'name'   => 'ریال ایران',
                    'symbol' => 'ریال',
                ];
            }

            return $list;
        }

        if (class_exists('Pro_VIP_Payment_Gateway') && !class_exists('Pro_VIP_Nextpay_Gateway')) {
            class Pro_VIP_Nextpay_Gateway extends Pro_VIP_Payment_Gateway
            {
                public $id = 'Nextpay',
                        $settings = [],
                        $frontendLabel = 'نکست پی',
                        $adminLabel = 'نکست پی';

                public function __construct()
                {
                    parent::__construct();
                }

                /**
                 * @param Pro_VIP_Payment $payment
                 */
                public function beforePayment(Pro_VIP_Payment $payment)
                {
                    $Api_key = $this->settings['api_key']; //Required
                    $Amount = intval($payment->price); // Required
                    $orderId = $payment->paymentId; // Required
                    $Description = 'پرداخت فاکتور به شماره ی'.$orderId; // Required
                    $CallbackURL = $this->getReturnUrl(); // add_query_arg('order', $orderId, $this->getReturnUrl());
                    //$currency = $order->get_order_currency();

                    if (pvGetOption('currency') === 'IRR') {
                        $Amount /= 10;
                    }

                    include_once 'nextpay_payment.php';

                    $nextpay = new Nextpay_Payment(array(
                        "api_key"=>$Api_key,
                        "order_id"=>$orderId,
                        "amount"=>$Amount,
                        "callback_uri"=>$CallbackURL));

                    $res = $nextpay->token();

                    if (intval($res->code) == -1) {
                        $payment->key = $orderId;
                        $payment->user = get_current_user_id();
                        $payment->save();

                        $nextpay->send($res->trans_id);
                    } else {
                        pvAddNotice('خطا در هنگام اتصال به نکست پی.');
                        return;
                    }
                }

                public function afterPayment()
                {
                    if (isset($_POST['order_id'])) {
                        $orderId = $_POST['order_id'];
                    } else {
                        $orderId = 0;
                    }

                    if ($orderId) {
                        $payment = new Pro_VIP_Payment($orderId);
                        $Api_key = $this->settings['api_key']; //Required
                        $amount = intval($payment->price); //  - ریال به مبلغ Required
                        $trans_id = isset($_POST['trans_id'])?$_POST['trans_id'] : false ;
                        $order_id = isset($_POST['order_id'])?$_POST['order_id'] : false ;

                        if (pvGetOption('currency') === 'IRR') {
                            $amount /= 10;
                        }

                        if ($trans_id) {

                            include_once "nextpay_payment.php";

                            $parameters = array
                            (
                                'api_key'	=> $Api_key,
                                'trans_id' 	=> $trans_id,
                                'order_id' 	=> $order_id,
                                'amount'	=> $amount,
                            );

                            $nextpay = new Nextpay_Payment();
                            $Result = intval($nextpay->verify_request($parameters));

                            if ($Result == 0) {
                                pvAddNotice('پرداخت شما با موفقیت انجام شد. کد پیگیری: '.$trans_id, 'success');
                                $payment->status = 'publish';
                                $payment->save();

                                $this->paymentComplete($payment);
                            } else {
                                pvAddNotice('به نظر می رسد عملیات پرداخت توسط شما لغو گردیده، اگر چنین نیست مجددا اقدام به پرداخت فاکتور نمایید.');
                                $this->paymentFailed($payment);

                                return false;
                            }
                        } else {
                            pvAddNotice('به نظر می رسد عملیات پرداخت توسط شما لغو گردیده، اگر چنین نیست مجددا اقدام به پرداخت فاکتور نمایید.');
                            $this->paymentFailed($payment);

                            return false;
                        }
                    }
                }

                public function adminSettings(PV_Framework_Form_Builder $form)
                {
                    $form->textfield('api_key')->label('کلید API');
                }
            }

            Pro_VIP_Payment_Gateway::registerGateway('Pro_VIP_Nextpay_Gateway');
        }
    }
}
