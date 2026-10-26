<?php

namespace BluePayment\Validator;

final class AdminFormValidator
{
    private $language;
    private $request;

    public function __construct($registry)
    {
        $this->language = $registry->get('language');
        $this->request = $registry->get('request');
    }

    public function validate(): array
    {
        $data = [];
        $pending_status = isset($this->request->post['payment_bluepayment_status_pending']) ? $this->request->post['payment_bluepayment_status_pending'] : null;
        $success_status = isset($this->request->post['payment_bluepayment_status_success']) ? $this->request->post['payment_bluepayment_status_success'] : null;
        $failed_status = isset($this->request->post['payment_bluepayment_status_failed']) ? $this->request->post['payment_bluepayment_status_failed'] : null;

        if (is_null($this->request->post['payment_bluepayment_status'])) {
            $data['error_payment_bluepayment_status'] = $this->language->get('error_empty_status');
        }

        if (is_null($this->request->post['payment_bluepayment_test_mode'])) {
            $data['error_payment_bluepayment_test_mode'] = $this->language->get('error_empty_test_mode');
        }

        if (filter_var($failed_status, FILTER_VALIDATE_INT) === false) {
            $data['error_payment_bluepayment_status_failed'] = $this->language->get('payment_status_not_defined');
        }

        if (filter_var($success_status, FILTER_VALIDATE_INT) === false) {
            $data['error_payment_bluepayment_status_success'] = $this->language->get('payment_status_not_defined');
        }

        if (filter_var($pending_status, FILTER_VALIDATE_INT) === false) {
            $data['error_payment_bluepayment_status_pending'] = $this->language->get('payment_status_not_defined');
        }

        if (empty($this->request->post['payment_bluepayment_currency'])) {
            $data['message_warning'] = $this->language->get('at_least_one_currency_settings_required');
        } else {
            $data += $this->validateBluepaymentCurrencies();
        }

        return $data;
    }

    private function validateBluepaymentCurrencies(): array
    {
        $data = [];

        foreach ($this->request->post['payment_bluepayment_currency'] as $currency) {
            if (empty($currency['service_id']) || !isset($currency['shared_key']) || !is_string($currency['shared_key']) || trim($currency['shared_key']) === '') {
                $data['message_warning'][] = sprintf($this->language->get('currency_settings_both_values_required'), $currency['custom_name']);
            } elseif (filter_var($currency['service_id'], FILTER_VALIDATE_INT) === false) {
                $data['message_warning'][] = sprintf($this->language->get('currency_settings_service_id_integer_required'), $currency['custom_name']);
            }
        }

        return $data;
    }
}