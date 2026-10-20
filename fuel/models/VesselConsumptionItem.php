<?php

namespace app\modules\fuel\models;

use yii\db\ActiveRecord;

class VesselConsumptionItem extends ActiveRecord
{
    const AUX_OTHER = 'other';
    const AUX_BOW_THRUSTER = 'bow_thruster';

    const MAX_CONSUMPTION = 99999999.99;
    const MAX_RATE = 99999999.9999;

    /** @var VesselFuelConsumption|null */
    private $_report;

    public static function tableName()
    {
        return 'vessel_consumption_item';
    }

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => [
                '!consumption_id',
                'engine_id',
                'generator_id',
                'auxiliary_code',
                'operation_mode',
                'operating_minutes',
                'actual_consumption',
                'ideal_consumption',
                'consumption_rate',
                'description',
            ],
        ];
    }

    public function rules()
    {
        return [
            [['engine_id', 'generator_id', 'operating_minutes'], 'filter', 'filter' => [ConsumptionInput::class, 'normalize']],
            [['auxiliary_code', 'operation_mode', 'description'], 'filter', 'filter' => function ($value) { return is_string($value) ? trim($value) : $value; }],
            [['engine_id', 'generator_id', 'auxiliary_code', 'operation_mode'], 'default', 'value' => null],
            [['operating_minutes'], 'required'],
            [['consumption_id', 'engine_id', 'generator_id'], 'integer', 'min' => 1],
            [['operating_minutes'], 'integer', 'min' => 0, 'max' => 1440],
            [['actual_consumption', 'ideal_consumption', 'consumption_rate'], 'number', 'min' => 0, 'max' => self::MAX_CONSUMPTION],
            [['auxiliary_code'], 'in', 'range' => array_keys(self::getAuxiliaryOptions())],
            [['operation_mode'], 'in', 
    'range' => array_keys(VesselFuelMode::getModeOptions()), 
    'skipOnEmpty' => true,
    'when' => function($model) {
        return !$model->isAuxiliary();
    }
],
            [['description'], 'string', 'max' => 2000],
            [['engine_id'], 'validateEquipment', 'skipOnEmpty' => false],
            [['operation_mode'], 'validateMode', 'skipOnEmpty' => false],
            [['operating_minutes'], 'validateDuration'],
            [['engine_id'], 'unique', 'targetAttribute' => ['consumption_id', 'engine_id'], 'message' => 'این موتور در گزارش تکرار شده است.'],
            [['generator_id'], 'unique', 'targetAttribute' => ['consumption_id', 'generator_id'], 'message' => 'این ژنراتور در گزارش تکرار شده است.'],
            [['auxiliary_code'], 'unique', 'targetAttribute' => ['consumption_id', 'auxiliary_code'], 'message' => 'این تجهیز جانبی در گزارش تکرار شده است.'],
        ];
    }

    public function bindReport(VesselFuelConsumption $report)
    {
        $this->_report = $report;
        $this->consumption_id = $report->id;

        return $this;
    }

    /**
     * تشخیص اینکه آیا این ردیف متعلق به تجهیزات جانبی است یا خیر
     */
    public function isAuxiliary()
    {
        return !empty($this->auxiliary_code) || (empty($this->engine_id) && empty($this->generator_id));
    }

    public function validateEquipment($attribute, $params)
    {
        $identityFields = [
            'consumption_id',
            'engine_id',
            'generator_id',
            'auxiliary_code',
        ];

        foreach ($identityFields as $field) {
            if ($this->hasErrors($field)) {
                return;
            }
        }

        $count = (int) ($this->engine_id !== null)
            + (int) ($this->generator_id !== null)
            + (int) ($this->auxiliary_code !== null);

        if ($count !== 1) {
            $this->addError(
                'engine_id',
                'دقیقاً یک موتور، ژنراتور یا تجهیز جانبی مشخص کنید.'
            );

            return;
        }

        if (!$this->isNewRecord) {
            foreach ($identityFields as $field) {
                if ($this->isAttributeChanged($field, false)) {
                    $this->addError(
                        $field,
                        'گزارش و هویت تجهیز پس از ثبت قابل تغییر نیستند.'
                    );
                }
            }

            if ($this->hasErrors()) {
                return;
            }
        }

        $report = $this->resolveReport();

        if (
            !$report
            || !is_scalar($report->vesselIdRef)
            || !ctype_digit((string) $report->vesselIdRef)
            || (int) $report->vesselIdRef < 1
        ) {
            $this->addError(
                'consumption_id',
                'شناور گزارش مشخص نشده است.'
            );

            return;
        }

        $vessel = $this->findVessel();

        if (!$vessel || (int) $vessel->is_deleted !== 0) {
            $this->addError(
                'consumption_id',
                'شناور گزارش معتبر یا فعال نیست.'
            );

            return;
        }

        if ($this->auxiliary_code !== null) {
            if (
                $this->auxiliary_code === self::AUX_BOW_THRUSTER
                && $this->isNewRecord
            ) {
                if ((int) $vessel->has_bow_thruster !== 1) {
                    $this->addError(
                        'auxiliary_code',
                        'برای این شناور وجود بوستراستر ثبت نشده است.'
                    );

                    return;
                }

                if ($this->findActiveBowThruster($vessel) === null) {
                    $this->addError(
                        'auxiliary_code',
                        'مشخصات فعال بوستراستر برای این شناور ثبت نشده است.'
                    );
                }
            }

            return;
        }

        $equipment = $this->findEquipment();

        $field = $this->engine_id !== null
            ? 'engine_id'
            : 'generator_id';

        if (!$equipment) {
            $this->addError(
                $field,
                'تجهیز انتخاب‌شده متعلق به شناور گزارش نیست.'
            );

            return;
        }

        if (
            (bool) $equipment->is_deleted
            && (
                $this->isNewRecord
                || $this->isAttributeChanged('operation_mode', false)
            )
        ) {
            $this->addError(
                $field,
                'برای تجهیز حذف‌شده، ثبت جدید یا تغییر حالت عملیاتی مجاز نیست.'
            );
        }
    }

public function validateMode($attribute, $params)
{
    foreach (['engine_id', 'generator_id', 'auxiliary_code'] as $field) {
        if ($this->hasErrors($field)) {
            return;
        }
    }

    // تجهیزات جانبی مبنای نرخ/حالت کاری ندارند
    if ($this->isAuxiliary()) {
        return;
    }

    // موتور و ژنراتور باید operation_mode داشته باشن
    if (
        ($this->engine_id !== null || $this->generator_id !== null)
        && $this->operation_mode === null
    ) {
        $this->addError(
            $attribute,
            'حالت عملیاتی موتور یا ژنراتور را انتخاب کنید.'
        );
    }
}

    public function validateDuration($attribute, $params)
    {
        $report = $this->resolveReport();

        if (!$report) {
            $this->addError(
                $attribute,
                'گزارش مرتبط مشخص نشده است.'
            );

            return;
        }

        $start = $this->parseDateTime($report->started_at);
        $end = $this->parseDateTime($report->ended_at);

        if (!$start || !$end || $end <= $start) {
            $this->addError(
                $attribute,
                'ابتدا بازه زمانی معتبر گزارش را مشخص کنید.'
            );

            return;
        }

        $duration = $end->getTimestamp() - $start->getTimestamp();

        if ((int) $this->operating_minutes * 60 > $duration) {
            $this->addError(
                $attribute,
                'کارکرد تجهیز نمی‌تواند بیشتر از مدت گزارش باشد.'
            );
        }
    }

    public function afterValidate()
    {
        if (!$this->hasErrors()) {
            $this->calculateConsumption();
        }

        parent::afterValidate();
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if (!$this->consumption_id) {
            $this->addError(
                'consumption_id',
                'ابتدا باید گزارش اصلی ذخیره شود.'
            );

            return false;
        }

        if ($this->hasErrors()) {
            return false;
        }

        return true;
    }

    private function calculateConsumption()
    {
        // تجهیزات جانبی: مصرف کاملاً دستی توسط کاربر وارد می‌شود و نیازی به فرمول/نرخ ندارد
        if ($this->isAuxiliary()) {
            $hours = (int) $this->operating_minutes / 60;
            $actual = (float) $this->actual_consumption;
            
            if ($hours > 0 && $actual > 0) {
                $this->consumption_rate = number_format($actual / $hours, 4, '.', '');
            } else {
                $this->consumption_rate = '0.0000';
            }
            
            $this->ideal_consumption = number_format($actual, 2, '.', '');
            $this->actual_consumption = number_format($actual, 2, '.', '');
            return;
        }

        // موتورها و ژنراتورها: محاسبه بر اساس نرخ تست‌شده
        $rate = $this->resolveRate();
        
        if ($rate !== null && is_numeric($rate)) {
            $rateFloat = (float) $rate;
            $hours = (int) $this->operating_minutes / 60;
            
            $calculatedConsumption = round($hours * $rateFloat, 2);
            
            $this->consumption_rate = number_format($rateFloat, 4, '.', '');
            $this->ideal_consumption = number_format($calculatedConsumption, 2, '.', '');
            $this->actual_consumption = number_format($calculatedConsumption, 2, '.', '');
        } else {
            $this->consumption_rate = '0.0000';
            $this->ideal_consumption = '0.00';
            $this->actual_consumption = '0.00';
        }
    }

    private function resolveRate()
    {
        if ($this->isAuxiliary()) {
            return null;
        }

        $report = $this->resolveReport();
        if (!$report || !$this->operation_mode) {
            return null;
        }

        $query = VesselFuelMode::find()
            ->where([
                'vessel_id' => $report->vesselIdRef,
                'mode' => $this->operation_mode,
                'is_deleted' => 0,
            ]);

        if ($this->engine_id !== null) {
            $query->andWhere(['engine_id' => $this->engine_id]);
        }

        if ($this->generator_id !== null) {
            $query->andWhere(['generator_id' => $this->generator_id]);
        }

        $mode = $query->one();

        return $mode ? (float) $mode->tested_consumption : null;
    }

    private function findActiveBowThruster(VesselType $vessel)
    {
        if (
            (int) $vessel->is_deleted !== 0
            || (int) $vessel->has_bow_thruster !== 1
        ) {
            return null;
        }

        return VesselBowThruster::find()
            ->where([
                'vessel_id' => $vessel->id,
                'is_deleted' => 0,
            ])
            ->one();
    }

    private function findEquipment()
    {
        $report = $this->resolveReport();

        if (!$report || $this->isAuxiliary()) {
            return null;
        }

        $class = $this->engine_id !== null
            ? VesselEngine::class
            : VesselGenerator::class;

        $id = $this->engine_id !== null
            ? $this->engine_id
            : $this->generator_id;

        return $class::find()
            ->where([
                'id' => $id,
                'vessel_id' => $report->vesselIdRef,
            ])
            ->one();
    }

    private function findVessel()
    {
        $report = $this->resolveReport();
        return $report ? VesselType::findOne($report->vesselIdRef) : null;
    }

    private function resolveReport()
    {
        if ($this->_report !== null) {
            return $this->_report;
        }

        return $this->consumption_id ? $this->getReport()->one() : null;
    }

    private function parseDateTime($value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            new \DateTimeZone('Asia/Tehran')
        );

        return $date && $date->format('Y-m-d H:i:s') === $value
            ? $date
            : null;
    }

    public function getReport()
    {
        return $this->hasOne(
            VesselFuelConsumption::class,
            ['id' => 'consumption_id']
        );
    }

    public function getEngine()
    {
        return $this->hasOne(
            VesselEngine::class,
            ['id' => 'engine_id']
        );
    }

    public function getGenerator()
    {
        return $this->hasOne(
            VesselGenerator::class,
            ['id' => 'generator_id']
        );
    }

    public function getConsumptionDifference()
    {
        return 0.00;
    }

    public function getEquipmentTitle()
    {
        if ($this->auxiliary_code !== null) {
            $options = self::getAuxiliaryOptions();

            return isset($options[$this->auxiliary_code])
                ? $options[$this->auxiliary_code]
                : 'تجهیز جانبی';
        }

        if ($this->engine_id !== null) {
            return $this->engine
                ? 'موتور ' . $this->engine->engine_number
                : 'موتور';
        }

        return $this->generator
            ? 'ژنراتور ' . $this->generator->generator_number
            : 'ژنراتور';
    }

    public static function getModeOptions()
    {
        return VesselFuelMode::getModeOptions();
    }

    public static function getAuxiliaryOptions()
    {
        return [
            self::AUX_BOW_THRUSTER => 'بوستراستر',
            self::AUX_OTHER => 'سایر تجهیزات',
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه',
            'consumption_id' => 'گزارش مصرف',
            'engine_id' => 'موتور',
            'generator_id' => 'ژنراتور',
            'auxiliary_code' => 'تجهیز جانبی',
            'operation_mode' => 'مبنای نرخ مصرف',
            'operating_minutes' => 'کارکرد (دقیقه)',
            'consumption_rate' => 'نرخ مصرف (لیتر/ساعت)',
            'actual_consumption' => 'مصرف محاسبه‌شده (لیتر)',
            'ideal_consumption' => 'مصرف استاندارد (لیتر)',
            'description' => 'توضیحات',
            'created_at' => 'زمان ایجاد',
            'updated_at' => 'زمان ویرایش',
        ];
    }
}