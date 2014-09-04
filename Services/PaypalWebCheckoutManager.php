<?php

/**
 * PaypalWebCheckout for Symfony2
 *
 * This Bundle is part of Symfony2 Payment Suite
 *
 * @author Arkaitz Garro <hola@arkaitzgarro.com>
 *
 * Arkaitz Garro 2014
 */

namespace PaymentSuite\PaypalWebCheckoutBundle\Services;

use PaymentSuite\PaymentCoreBundle\Exception\PaymentException;
use PaymentSuite\PaymentCoreBundle\Exception\PaymentOrderNotFoundException;
use PaymentSuite\PaymentCoreBundle\Services\Interfaces\PaymentBridgeInterface;
use PaymentSuite\PaymentCoreBundle\Services\PaymentEventDispatcher;
use PaymentSuite\PaypalWebCheckoutBundle\PaypalWebCheckoutMethod;
use PaymentSuite\PaypalWebCheckoutBundle\Exception\ParameterNotReceivedException;
use PaymentSuite\PaypalWebCheckoutBundle\Services\Wrapper\PaypalFormTypeWrapper;

/**
 * Paypal Web Checkout manager
 */
class PaypalWebCheckoutManager
{
    /**
     * @var PaymentEventDispatcher
     *
     * Payment event dispatcher
     */
    protected $paymentEventDispatcher;

    /**
     * @var PaymentBridgeInterface
     *
     * Payment bridge interface
     */
    protected $paymentBridge;

    /**
     * @var PaypalWebCheckoutTransactionWrapper $paypalWrapper
     *
     * Paypal Web Checkout wrapper
     */
    protected $paypalWrapper;

    /**
     * @var Array config
     *
     * Paypal Web Checkout configuration
     */
    protected $config;

    /**
     * @var string $locale
     *
     * Locale
     */
    protected $locale;

    /**
     * Construct method for paypal manager
     *
     * @param PaymentEventDispatcher $paymentEventDispatcher Event dispatcher
     * @param PaymentBridgeInterface $paymentBridge          Payment Bridge
     * @param PaypalFormTypeWrapper  $paypalFormTypeWrapper  Paypal Wrapper
     */
    public function __construct(
        PaymentEventDispatcher $paymentEventDispatcher,
        PaymentBridgeInterface $paymentBridge,
        PaypalFormTypeWrapper $paypalFormTypeWrapper
    ) {
        $this->paymentEventDispatcher = $paymentEventDispatcher;
        $this->paymentBridge = $paymentBridge;
        $this->paypalFormTypeWrapper = $paypalFormTypeWrapper;
    }

    /**
     * Set locale
     * @param string $locale Locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * See also PaypalWebCheckout Api Integration : https://developer.paypal.com/docs/integration/web/web-checkout/
     */
    public function processPayment()
    {
        $paypalMethod = new PaypalWebCheckoutMethod();

        /**
         * At this point, order must be created given a cart, and placed in PaymentBridge
         *
         * So, $this->paymentBridge->getOrder() must return an object
         */
        $this
            ->paymentEventDispatcher
            ->notifyPaymentOrderLoad(
                $this->paymentBridge,
                $paypalMethod
            );

        /**
         * Order Not found Exception must be thrown just here
         */
        if (!$this->paymentBridge->getOrder()) {
            throw new PaymentOrderNotFoundException;
        }

        /**
         * Order exists right here
         */
        $this
            ->paymentEventDispatcher
            ->notifyPaymentOrderCreated(
                $this->paymentBridge,
                $paypalMethod
            );

        $formView = $this
            ->paypalFormTypeWrapper
            ->setLocale($this->locale)
            ->buildForm();

        return $formView;
    }

    /**
     *  Process Paypal response
     */
    public function processResult($orderId, array $parameters)
    {
        // Check we receive all needed parameters
        $this->checkResultParameters($parameters);

        // Check if the transaction is successful
        if (!$this->transactionSuccessful($parameters)) {
            /**
             * Payment paid failed
             *
             * Paid process has ended failed
             */
            $this
                ->paymentEventDispatcher
                ->notifyPaymentOrderFail(
                    $this->paymentBridge,
                    $paypalMethod
                );

            throw new PaymentException();
        }

        $paypalMethod = new PaypalWebCheckoutMethod();

        /**
         * Adding transaction information to PaymentMethod
         *
         * This information is only available in PaymentOrderSuccess event
         */
        $paypalMethod
            ->setOrderNumber($orderId);

        /**
         * Payment paid successfully
         *
         * Paid process has ended successfully
         */
        $this
            ->paymentEventDispatcher
            ->notifyPaymentOrderSuccess(
                $this->paymentBridge,
                $paypalMethod
            );

        return $this;
    }

    /**
     * Checks that all the required parameters are received
     *
     * @param array $parameters Parameters
     *
     * @throws \PaymentSuite\PaypalWebCheckoutBundle\Exception\ParameterNotReceivedException
     */
    protected function checkResultParameters(array $parameters)
    {
        $list = array(
            'item_number',
            'payment_status'
        );

        foreach ($list as $item) {
            if (!isset($parameters[$item])) {
                throw new ParameterNotReceivedException($item);
            }
        }
    }

    /**
     * Check if transaction is complete
     *
     * @param array $response Paypal response
     *
     * @return boolean
     */
    public function transactionSuccessful($response)
    {
        /**
         * When a transaction is successful, payment_status has a 'Completed' value
         */
        return strcmp($response['payment_status'], 'Completed') === 0;
    }
}
