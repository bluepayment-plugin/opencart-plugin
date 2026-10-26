<?php

require_once DIR_SYSTEM . '/library/bluemedia-sdk-php/index.php';
require_once __DIR__ . '/bluepayment_base.php';

use BlueMedia\OnlinePayments\Action\ITN\Transformer;
use Psr\Log\LogLevel;
use BlueMedia\OnlinePayments\Gateway;

class ControllerExtensionPaymentBluepayment extends ControllerExtensionPaymentBluepaymentBase
{
    public function processCheckout(): void
    {
        if (!isset($this->session->data['order_id']) || !$this->ServiceCredentialsProvider->currencyServiceExists()) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('checkout/order');
        $this->load->library('bluepayment/Builder/TransactionBuilder');

        $orderInfo = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        try {
            if ($orderInfo === false) {
                throw new Exception(sprintf('Order not found %s', $this->session->data['order_id']));
            }

            $credentials = $this->ServiceCredentialsProvider->getCurrencyServiceCredentials();

            $gateway = new Gateway(
                $credentials->getServiceId(),
                $credentials->getSharedKey(),
                $this->getGatewayMode()
            );

            /** @var \BlueMedia\OnlinePayments\Model\TransactionInit $transactionData */
            $transactionData = $this->TransactionBuilder->build(
                $orderInfo,
                $credentials->getServiceId(),
                $this->request->post
            );

            $this->model_checkout_order->addOrderHistory(
                $this->session->data['order_id'],
                $this->config->get('payment_bluepayment_status_pending'),
                $this->language->get('text_history_payment_pending')
            );

            $this->Logger->log(
                LogLevel::INFO,
                'Data in ' . __METHOD__,
                [
                    'Transation Data' => json_encode($transactionData->toArray()),
                ]
            );

            $result = $gateway->doInitTransaction($transactionData);

            $redirectUrl = property_exists($result, 'redirecturl') ? (string)$result->redirecturl : null;

            $response = [
                'redirect' => $redirectUrl,
                'order_id' => $this->session->data['order_id'],
            ];

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($response));
        } catch (Throwable $e) {
            $this->Logger->log(
                LogLevel::WARNING,
                'Exception in ' . __METHOD__,
                [
                    'Order ID' => $this->session->data['order_id'],
                    'data' => $e
                ]
            );

            $this->response->redirect($this->url->link('checkout/failure', '', true));
        }
    }

    public function paymentReturn(): void
    {
        $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    private function addLangContents(): void
    {
        $this->view_data['text_button_checkout'] = $this->language->get('text_button_checkout');
        $this->view_data['text_information_redirect'] = $this->language->get('text_information_redirect');
        $this->view_data['text_information_payment_regulations'] = $this->language->get('text_information_payment_regulations');
    }

    private function getGatewayMode(): string
    {
        return (int) $this->config->get('payment_bluepayment_test_mode') === 1
            ? Gateway::MODE_SANDBOX
            : Gateway::MODE_LIVE;
    }

    public function processItn()
    {
        $this->load->library('bluepayment/Builder/ItnDataBuilder');
        $this->load->library('bluepayment/Service/Itn/Result/Result');
        $this->load->library('bluepayment/Service/Itn/Itn');

        $orderId = null;

        try {
            $transactionConfirmed = true;

            $transaction = Gateway::doItnIn();
            if ($transaction->getCurrency() === '' || !$this->ServiceCredentialsProvider->currencyServiceExists($transaction->getCurrency())) {
                throw new \InvalidArgumentException('ITN currency is not configured.');
            }

            $service_credentials = $this->ServiceCredentialsProvider->getCurrencyServiceCredentials($transaction->getCurrency());

            $gateway = new Gateway(
                $service_credentials->getServiceId(),
                $service_credentials->getSharedKey(),
                $this->getGatewayMode()
            );

            $orderId = $this->ParamSuffixer->removeSuffix($transaction->getOrderId());
            $order = $this->model_checkout_order->getOrder($orderId);

            $dataToHash = $this->ItnDataBuilder->build(Gateway::getItnInXml());
            $generatedOrderHash = Gateway::generateHash($dataToHash);

            if ($generatedOrderHash !== $transaction->getHash()) {
                $transactionConfirmed = false;

                $this->Logger->log(
                    LogLevel::WARNING,
                    'Hash mismatch',
                    [
                        'Order ID' => $orderId,
                        'Generated hash' => $generatedOrderHash,
                        'Received hash' => $transaction->getHash(),
                        'Transaction data' => $dataToHash
                    ]
                );
            }

            if ($transactionConfirmed) {
                $this->load->library('bluepayment/Service/Itn/Result/Success');
                $this->load->library('bluepayment/Service/Itn/Result/Failure');
                $this->load->library('bluepayment/Service/Itn/Result/Pending');

                $this->Itn->addResult($this->Failure);
                $this->Itn->addResult($this->Success);
                $this->Itn->addResult($this->Pending);
                $this->Itn->handle($transaction->getPaymentStatus(), (int) $orderId, (int) $order['order_status_id']);
            }

            $response = $gateway->doItnInResponse($transaction, $transactionConfirmed);

            $this->Logger->log(
                LogLevel::INFO,
                'ITN response in ' . __METHOD__,
                [
                    'Order ID' => $orderId,
                    'ITN Data' => json_encode(Transformer::modelToArray($transaction)),
                    'Response' => json_encode($response)
                ]
            );

            $this->response->setOutput($response);
        } catch (Throwable $e) {
            $this->Logger->log(
                LogLevel::WARNING,
                'Exception in ' . __METHOD__,
                [
                    'Order ID' => $orderId,
                    'data' => $e
                ]
            );
            exit;
        }
    }
}
