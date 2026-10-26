<?php

require_once DIR_SYSTEM . '/library/bluemedia-sdk-php/index.php';

use Psr\Log\LogLevel;

class ControllerExtensionPaymentBluepayment extends Controller
{
    public const STATUS_PENDING = 1;
    public const STATUS_PROCESSING = 2;
    public const STATUS_FAILED = 10;

    private $ext_path;
    private $inputs = [
        'payment_bluepayment_status',
        'payment_bluepayment_test_mode',
        'payment_bluepayment_status_pending',
        'payment_bluepayment_status_success',
        'payment_bluepayment_status_failed',
    ];

    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->load->library('bluepayment/Helper/Logger');
        $this->load->library('bluepayment/Dictionary/BluepaymentDictionary');

        $this->ext_path = $this->BluepaymentDictionary->getExtensionPath();
    }

    public function index()
    {
        $this->load->model('localisation/currency');
        $this->load->model('setting/setting');
        $this->load->language($this->ext_path);
        $this->load->model('localisation/order_status');
        $this->load->model('extension/payment/bluepayment');
        $this->load->library('bluepayment/Validator/AdminFormValidator');

        $this->model_extension_payment_bluepayment->checkUpdate();

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('view/javascript/bluepayment/bluepayment.js');
        $this->document->addStyle('view/stylesheet/bluepayment/bluepayment.css');

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->request->post['payment_bluepayment_currency'] = $this->prepareCurrenciesData(
                $this->request->post['payment_bluepayment_currency']
            );

            $data = $this->AdminFormValidator->validate();

            if (!$this->user->hasPermission('modify', $this->ext_path)) {
                $data['message_warning'] = $this->language->get('error_permission');
            }

            if (empty($data)) {
                $this->model_setting_setting->editSetting('payment_bluepayment', $this->request->post);

                $this->session->data['message_success'] = $this->language->get('text_success');
                $this->Logger->log(
                    LogLevel::INFO,
                    '[BM Bluepayment] Module settings updated with data',
                    [
                        'POST' => json_encode($this->request->post)
                    ]
                );

                $this->response->redirect(
                    $this->url->link($this->ext_path, 'user_token=' . $this->session->data['user_token'], true)
                );
            }
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['download'] = $this->url->link(
            $this->ext_path . '/downloadLog', 'user_token=' . $this->session->data['user_token'], true
        );

        if (isset($this->session->data['message_warning'])) {
            $data['message_warning'] = $this->session->data['message_warning'];

            unset($this->session->data['message_warning']);
        }

        if (isset($this->session->data['message_success'])) {
            $data['message_success'] = $this->session->data['message_success'];

            unset($this->session->data['message_success']);
        }

        $data += $this->Logger->getRecentLog();
        $data += $this->generateBreadcrumbs();
        $data += $this->fillFormFields();
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['currencies'] = $this->model_localisation_currency->getCurrencies();
        $data['log_files'] = $this->Logger->getFormattedFileList();
        $data['refresh_log_uri'] = $this->url->link(
            $this->ext_path  . '/refreshLog', 'user_token=' . $this->session->data['user_token'], true
        );
        $data['info_log_loading'] = $this->language->get('info_log_loading');

        $this->response->setOutput($this->load->view($this->ext_path, $data));
    }

    public function install()
    {
        $this->load->model('extension/payment/bluepayment');
        $this->model_extension_payment_bluepayment->install();
    }

    public function uninstall()
    {
        $this->load->model('extension/payment/bluepayment');
        $this->model_extension_payment_bluepayment->uninstall();
    }

    public function refreshLog(): void
    {
        $this->Logger->refreshLog();
    }

    public function downloadLog(): void
    {
        $this->Logger->download();
    }

    private function prepareCurrenciesData($currencies)
    {
        return array_filter($currencies, function ($currency) {
            return empty($currency['service_id']) === false || empty($currency['shared_key']) === false;
        });
    }

    private function generateBreadcrumbs()
    {
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        ];

        if (!isset($this->request->get['module_id'])) {
            $data['breadcrumbs'][] = [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link($this->ext_path, 'user_token=' . $this->session->data['user_token'], true)
            ];
        } else {
            $data['breadcrumbs'][] = [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link($this->ext_path, 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
            ];
        }

        return $data;
    }

    private function fillFormFields()
    {
        $data = [];

        foreach ($this->inputs as $input) {
            if (isset($this->request->post[$input])) {
                $data[$input] = $this->request->post[$input];
            } else {
                $data[$input] = $this->config->get($input);
            }
        }

        if (isset($this->request->post['payment_bluepayment_currency'])) {
            $currency_settings = $this->request->post['payment_bluepayment_currency'];
        } else {
            $currency_settings = $this->config->get('payment_bluepayment_currency');
        }

        if (!empty($currency_settings)) {
            foreach ($currency_settings as $currency_code => $currency_setting) {
                $data['payment_bluepayment_currency_' . $currency_code . '_service_id'] = $currency_setting['service_id'];
                $data['payment_bluepayment_currency_' . $currency_code . '_shared_key'] = $currency_setting['shared_key'];
            }
        }

        return $data;
    }
}
