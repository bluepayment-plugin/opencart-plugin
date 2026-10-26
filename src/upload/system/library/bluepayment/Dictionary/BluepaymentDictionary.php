<?php

namespace BluePayment\Dictionary;

final class BluepaymentDictionary
{
    public const EXTENSION_VERSION = '1.0.10';
    private const EXTENSION_NAME = 'bluepayment';
    private const EXTENSION_PATH = 'extension/payment/' . self::EXTENSION_NAME;
    private const START_TRANSACTION_URI = 'index.php?route=extension/payment/bluepayment/processCheckout';

    public function getExtensionVersion(): string
    {
        return self::EXTENSION_VERSION;
    }

    public function getExtensionName(): string
    {
        return self::EXTENSION_NAME;
    }

    public function getExtensionPath(): string
    {
        return self::EXTENSION_PATH;
    }

    public function getStartTransactionUri(): string
    {
        return self::START_TRANSACTION_URI;
    }
}
