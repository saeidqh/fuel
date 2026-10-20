<?php

namespace app\modules\fuel\models;

use yii\base\Model;

class VesselConsumptionForm extends Model
{
    const MAX_ITEM_ROWS = 14;
    const MAX_TRANSACTION_ROWS = 100;

    /** @var VesselFuelConsumption */
    public $report;

    private $_submitted = false;
    private $_items = [];
    private $_transactions = [];

    public function __construct(
        VesselFuelConsumption $report,
        array $config = []
    ) {
        $this->report = $report;
        parent::__construct($config);
    }

    public function loadSubmission(array $data)
    {
        $this->_submitted = true;
        $this->_items = [];
        $this->_transactions = [];

        $scope = $this->report->formName();

        if (!isset($data[$scope]) || !is_array($data[$scope])) {
            $this->report->addError('details', 'ساختار اطلاعات گزارش معتبر نیست.');
            return false;
        }

        if (
            !isset($data['_items_present'], $data['_transactions_present'])
            || $data['_items_present'] !== '1'
            || $data['_transactions_present'] !== '1'
        ) {
            $this->report->addError('details', 'فرم کامل ارسال نشده است؛ صفحه را دوباره باز کنید.');
            return false;
        }

        $this->_items = isset($data['VesselConsumptionItem']) ? $data['VesselConsumptionItem'] : [];
        $this->_transactions = isset($data['VesselTransaction']) ? $data['VesselTransaction'] : [];

        if (
            !is_array($this->_items)
            || count($this->_items) > self::MAX_ITEM_ROWS
            || !is_array($this->_transactions)
            || count($this->_transactions) > self::MAX_TRANSACTION_ROWS
        ) {
            $this->report->addError('details', 'ساختار یا تعداد ردیف‌های فرم معتبر نیست.');
            return false;
        }

        foreach ($this->_items as $row) {
            if (!is_array($row)) {
                $this->report->addError('details', 'ساختار ردیف مصرف معتبر نیست.');
                return false;
            }
        }

        $transactions = [];
        foreach ($this->_transactions as $row) {
            if (!is_array($row)) {
                $this->report->addError('transactions', 'ساختار ردیف تراکنش معتبر نیست.');
                return false;
            }

            $enabled = isset($row['enabled']) ? $row['enabled'] : '0';
            if (!in_array($enabled, ['0', '1'], true)) {
                $this->report->addError('transactions', 'وضعیت انتخاب تراکنش معتبر نیست.');
                return false;
            }

            if ($enabled === '1') {
                unset($row['enabled']);
                $transactions[] = $row;
            }
        }

        $data['VesselTransaction'] = $transactions;
        return $this->report->loadSubmission($data);
    }

    public function getViewData()
    {
        $data = $this->report->getFormData();
        $data['report'] = $this->report;
        $data['itemRows'] = $this->getItemRows();
        $data['transactionRows'] = $this->getTransactionRows();
        $data['modeOptions'] = VesselConsumptionItem::getModeOptions();

        return $data;
    }

    private function getItemRows()
    {
        $definitions = $this->getEquipmentDefinitions();

        if ($this->_submitted && is_array($this->_items)) {
            $rows = array_values($this->_items);
        } else {
            $rows = [];
            if (!$this->report->isNewRecord) {
                foreach ($this->report->getItems()->all() as $item) {
                    $rows[] = $item->getAttributes([
                        'id',
                        'engine_id',
                        'generator_id',
                        'auxiliary_code',
                        'operation_mode',
                        'operating_minutes',
                        'actual_consumption',
                        'description',
                    ]);
                }
            }

            $present = [];
            foreach ($rows as $row) {
                $present[$this->itemKey($row)] = true;
            }

            foreach ($definitions as $key => $definition) {
                if (!isset($present[$key])) {
                    $rows[] = $definition['input'];
                }
            }
        }

        $result = [];
        foreach (array_slice($rows, 0, self::MAX_ITEM_ROWS) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = $this->itemKey($row);
            $definition = isset($definitions[$key]) ? $definitions[$key] : null;

            $values = [];
            foreach ([
                'id',
                'engine_id',
                'generator_id',
                'auxiliary_code',
                'operation_mode',
                'operating_minutes',
                'actual_consumption',
                'description',
            ] as $field) {
                $values[$field] = $this->displayValue(isset($row[$field]) ? $row[$field] : '');
            }

            $values['index'] = $index;
            $values['title'] = $definition ? $definition['title'] : $this->fallbackTitle($row);
            $values['specification'] = $definition ? $definition['specification'] : 'تجهیز ثبت‌شده در گزارش';
            
            // ۱. دریافت نقشه کامل نرخ‌های حالت کاری (جهت استفاده در گزینه‌های Dropdown)
            $values['mode_rates'] = $definition && isset($definition['mode_rates']) ? $definition['mode_rates'] : [];

            // ۲. دریافت نرخ پیش‌فرض/جاری
            $values['consumption_rate'] = $definition && isset($definition['rate']) ? (float)$definition['rate'] : 0;

            // تفکیک گروه جهت جداسازی در ویو (موتورها/ژنراتورها vs تجهیزات جانبی)
            $values['group'] = (!empty($values['engine_id']) || !empty($values['generator_id'])) ? 'equipment' : 'extra';

            $result[] = $values;
        }

        return $result;
    }

    private function getEquipmentDefinitions()
    {
        $definitions = [];
        $equipment = $this->report->getEquipmentRows();
        $vesselId = $this->report->vesselIdRef;

        // ۱. موتورها
        foreach ($equipment['engines'] as $engine) {
            $rates = [];
            if ($vesselId) {
                $modes = VesselFuelMode::find()
                    ->where(['vessel_id' => $vesselId, 'engine_id' => $engine->id, 'is_deleted' => 0])
                    ->all();
                foreach ($modes as $m) {
                    $rates[$m->mode] = (float) $m->tested_consumption;
                }
            }

            $definitions['engine:' . $engine->id] = [
                'title' => 'موتور ' . $engine->engine_number,
                'specification' => $engine->engine_type . ' ـ ' . $engine->engine_power . ' اسب بخار',
                'mode_rates' => $rates,
                'input' => $this->newItem(['engine_id' => $engine->id]),
            ];
        }

        // ۲. ژنراتورها
        foreach ($equipment['generators'] as $generator) {
            $rates = [];
            if ($vesselId) {
                $modes = VesselFuelMode::find()
                    ->where(['vessel_id' => $vesselId, 'generator_id' => $generator->id, 'is_deleted' => 0])
                    ->all();
                foreach ($modes as $m) {
                    $rates[$m->mode] = (float) $m->tested_consumption;
                }
            }

            $definitions['generator:' . $generator->id] = [
                'title' => 'ژنراتور ' . $generator->generator_number,
                'specification' => $generator->gen_type . ' ـ ' . $generator->gen_power . ' کیلووات',
                'mode_rates' => $rates,
                'input' => $this->newItem(['generator_id' => $generator->id]),
            ];
        }

        // ۳. بوستراستر و تجهیزات جانبی (به‌‌صورت پیش‌فرض برای تمام شناورها یا بر اساس نیازمندی شناور)
        if ($vesselId) {
            $vessel = VesselType::findOne(['id' => $vesselId, 'is_deleted' => 0]);
            if ($vessel) {
                $bowThruster = $this->findActiveBowThruster($vessel);
                if ($bowThruster) {
                    $definitions['auxiliary:bow_thruster'] = [
                        'title' => 'بوستراستر (Bow Thruster)',
                        'specification' => $this->bowThrusterSpecification($bowThruster),
                        'input' => $this->newItem(['auxiliary_code' => 'bow_thruster']),
                    ];
                }
            }
        }

        // پشتیبانی از سایر گزینه‌های جانبی
        $auxOptions = VesselConsumptionItem::getAuxiliaryOptions();
        foreach ($auxOptions as $code => $title) {
            if (!isset($definitions['auxiliary:' . $code])) {
                $definitions['auxiliary:' . $code] = [
                    'title' => $title,
                    'specification' => 'تجهیزات جانبی ورودی دستی',
                    'input' => $this->newItem(['auxiliary_code' => $code]),
                ];
            }
        }

        return $definitions;
    }

    private function findActiveBowThruster(VesselType $vessel)
    {
        if ((int) $vessel->is_deleted !== 0 || (int) $vessel->has_bow_thruster !== 1) {
            return null;
        }

        return VesselBowThruster::find()
            ->andWhere(['vessel_id' => $vessel->id, 'is_deleted' => 0])
            ->one();
    }

    private function bowThrusterSpecification(VesselBowThruster $bowThruster)
    {
        $parts = [];
        $engineType = trim((string) $bowThruster->engine_type);
        if ($engineType !== '') {
            $parts[] = 'نوع موتور: ' . $engineType;
        }
        if ($bowThruster->power_kw !== null && $bowThruster->power_kw !== '') {
            $parts[] = 'قدرت: ' . $bowThruster->power_kw . ' کیلووات';
        }
        if ($bowThruster->manufacture_year !== null && $bowThruster->manufacture_year !== '') {
            $parts[] = 'سال ساخت: ' . $bowThruster->manufacture_year;
        }

        return !empty($parts) ? implode(' ـ ', $parts) : 'تجهیز جانبی شناور';
    }

    private function newItem(array $identity)
    {
        return array_merge([
            'id' => '',
            'engine_id' => '',
            'generator_id' => '',
            'auxiliary_code' => '',
            'operation_mode' => '',
            'operating_minutes' => '0',
            'actual_consumption' => '',
            'description' => '',
        ], $identity);
    }

    private function getTransactionRows()
    {
        if ($this->_submitted && is_array($this->_transactions)) {
            $rows = array_values($this->_transactions);
        } else {
            $rows = [];
            if (!$this->report->isNewRecord) {
                foreach ($this->report->getTransactions()->all() as $transaction) {
                    $row = $transaction->getAttributes([
                        'id',
                        'resource_type',
                        'transaction_type',
                        'amount',
                        'from_source',
                        'to_destination',
                        'description',
                    ]);
                    $row['enabled'] = '1';
                    $rows[] = $row;
                }
            }

            $present = [];
            foreach ($rows as $row) {
                $present[$row['resource_type'] . ':' . $row['transaction_type']] = true;
            }

            foreach (['fuel', 'water'] as $resource) {
                foreach (['receive', 'transfer'] as $type) {
                    if (!isset($present[$resource . ':' . $type])) {
                        $rows[] = [
                            'id' => '',
                            'enabled' => '0',
                            'resource_type' => $resource,
                            'transaction_type' => $type,
                            'amount' => '',
                            'from_source' => '',
                            'to_destination' => '',
                            'description' => '',
                        ];
                    }
                }
            }
        }

        $result = [];
        foreach (array_slice($rows, 0, self::MAX_TRANSACTION_ROWS) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $values = [];
            foreach ([
                'id',
                'enabled',
                'resource_type',
                'transaction_type',
                'amount',
                'from_source',
                'to_destination',
                'description',
            ] as $field) {
                $values[$field] = $this->displayValue(isset($row[$field]) ? $row[$field] : '');
            }

            $isReceive = $values['transaction_type'] === 'receive';
            $values['index'] = $index;
            $values['title'] = ($isReceive ? 'دریافت ' : 'انتقال ') . ($values['resource_type'] === 'water' ? 'آب' : 'سوخت');
            $values['partyField'] = $isReceive ? 'from_source' : 'to_destination';
            $values['partyLabel'] = $isReceive ? 'مبدأ دریافت' : 'مقصد انتقال';

            $result[] = $values;
        }

        return $result;
    }

    private function itemKey(array $row)
    {
        if (isset($row['engine_id']) && is_scalar($row['engine_id']) && $row['engine_id'] !== '') {
            return 'engine:' . $row['engine_id'];
        }
        if (isset($row['generator_id']) && is_scalar($row['generator_id']) && $row['generator_id'] !== '') {
            return 'generator:' . $row['generator_id'];
        }
        return 'auxiliary:' . $this->displayValue(isset($row['auxiliary_code']) ? $row['auxiliary_code'] : '');
    }

    private function fallbackTitle(array $row)
    {
        if (!empty($row['auxiliary_code']) && is_string($row['auxiliary_code'])) {
            $options = VesselConsumptionItem::getAuxiliaryOptions();
            if (isset($options[$row['auxiliary_code']])) {
                return $options[$row['auxiliary_code']];
            }
        }
        return 'تجهیز گزارش';
    }

    private function displayValue($value)
    {
        return $value === null || is_scalar($value) ? (string) $value : 'ورودی نامعتبر';
    }
}