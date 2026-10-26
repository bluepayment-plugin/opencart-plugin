<?php

namespace BluePayment\Provider;

require_once DIR_SYSTEM . '/library/bluepayment/ValueObject/ServiceCredentials.php';

use Registry;
use bluepayment\ValueObject\ServiceCredentials;

final class ServiceCredentialsProvider
{
    private $registry;
    private $current_currency;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->current_currency = $this->registry->get('session')->data['currency'];

        $this->registry->get('load')->model('setting/setting');
    }

    public function currencyServiceExists(string $currency = ''): bool
    {
        $currency = $currency !== '' ? $currency : $this->current_currency;

        return isset($this->getAllServiceCredentials()->{$currency});
    }

    public function getCurrencyServiceCredentials(string $currency = ''): ServiceCredentials
    {
        $service_credentials = $this->getAllServiceCredentials();
        $currency = $currency !== '' ? $currency : $this->current_currency;
        $credentials = $service_credentials->{$currency} ?? null;

        if (!($credentials instanceof \stdClass)) {
            throw new \InvalidArgumentException('Payment service is not configured for this currency.');
        }

        $serviceId = (int) ($credentials->service_id ?? 0);
        $sharedKey = $credentials->shared_key ?? '';

        if ($serviceId <= 0 || !is_string($sharedKey) || trim($sharedKey) === '') {
            throw new \InvalidArgumentException('Payment service credentials are incomplete.');
        }

        $this->current_currency = $currency;

        return new ServiceCredentials($serviceId, trim($sharedKey));
    }

    public function getCurrentCurrency(): string
    {
        return $this->current_currency;
    }

    private function getAllServiceCredentials(): \stdClass
    {
        $value = $this->registry->get('model_setting_setting')->getSettingValue('payment_bluepayment_currency');
        $credentials = is_string($value) ? json_decode($value) : null;

        return $credentials instanceof \stdClass ? $credentials : new \stdClass();
    }
}
