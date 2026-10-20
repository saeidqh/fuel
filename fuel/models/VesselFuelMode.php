<?php

namespace app\modules\fuel\models;

use yii\db\ActiveRecord;

class VesselFuelMode extends ActiveRecord
{
    const MODE_TIE_UP = 'tie_up';
    const MODE_ANCHORAGE = 'anchorage';
    const MODE_STANDBY = 'standby';
    const MODE_OPERATION = 'operation';

    public static function tableName()
    {
        return 'vessel_table_vessel_fuel_mode';
    }

public function rules()
{
    return [
        [['engine_id', 'generator_id'], 'default', 'value' => null],
        [['vessel_id', 'mode'], 'required'],
        [['vessel_id'], 'integer', 'min' => 1],
        [['engine_id', 'generator_id'], 'integer'],  // ✅ بدون min
        [['engine_number', 'generator_number'], 'integer'],  // ✅ اضافه شد
        [['mode'], 'in', 'range' => array_keys(self::getModeOptions())],
        [['ideal_consumption', 'tested_consumption'], 'default', 'value' => null],
        [['ideal_consumption', 'tested_consumption'], 'number', 'min' => 0, 'max' => 99999999.99],
        [['ideal_consumption', 'tested_consumption'], 'match', 'pattern' => '/^\d+(?:\.\d{1,2})?$/D', 'message' => 'نرخ را با حداکثر دو رقم اعشار وارد کنید.'],
        [['is_deleted'], 'default', 'value' => 0],
        [['is_deleted'], 'boolean'],
        [['vessel_id'], 'exist', 'targetClass' => VesselType::class, 'targetAttribute' => 'id'],
        [['engine_id', 'generator_id'], 'validateEquipment', 'skipOnEmpty' => false],  // ✅ هر دو
        [['engine_id'], 'unique', 'targetAttribute' => ['engine_id', 'mode'], 'message' => 'نرخ این حالت برای موتور قبلاً ثبت شده است؛ همان رکورد را ویرایش کنید.'],
        [['generator_id'], 'unique', 'targetAttribute' => ['generator_id', 'mode'], 'message' => 'نرخ این حالت برای ژنراتور قبلاً ثبت شده است؛ همان رکورد را ویرایش کنید.'],
        [['vessel_id', 'engine_id', 'generator_id', 'mode'], 'validateIdentity', 'skipOnEmpty' => false],
    ];
}

    public function validateEquipment($attribute, $params)
    {
        if ($this->hasErrors('vessel_id') || $this->hasErrors('engine_id') || $this->hasErrors('generator_id')) {
            return;
        }

        $hasEngine = $this->engine_id !== null;
        $hasGenerator = $this->generator_id !== null;

        if ($hasEngine === $hasGenerator) {
            $this->addError('engine_id', 'دقیقاً یک موتور یا یک ژنراتور انتخاب کنید.');
            return;
        }

        $field = $hasEngine ? 'engine_id' : 'generator_id';
        $class = $hasEngine ? VesselEngine::class : VesselGenerator::class;
        $equipment = $class::find()->where([
            'id' => $this->$field,
            'vessel_id' => $this->vessel_id,
        ])->one();

        if (!$equipment) {
            $this->addError($field, 'تجهیز انتخاب‌شده متعلق به این شناور نیست.');
            return;
        }

        if (!(bool) $this->is_deleted && (bool) $equipment->is_deleted) {
            $this->addError($field, 'برای تجهیز حذف‌شده نمی‌توان نرخ فعال ثبت کرد.');
        }
    }

    public function validateIdentity($attribute, $params)
    {
        if (!$this->isNewRecord && $this->isAttributeChanged($attribute, false)) {
            $this->addError($attribute, 'شناور، تجهیز و حالت عملیاتی رکورد نرخ پس از ثبت قابل تغییر نیستند.');
        }
    }

    public function getVessel()
    {
        return $this->hasOne(VesselType::class, ['id' => 'vessel_id']);
    }

    public function getEngine()
    {
        return $this->hasOne(VesselEngine::class, ['id' => 'engine_id']);
    }

    public function getGenerator()
    {
        return $this->hasOne(VesselGenerator::class, ['id' => 'generator_id']);
    }

    public static function getModeOptions()
    {
        return [
            self::MODE_TIE_UP => 'کنار اسکله (Tie Up)',
            self::MODE_ANCHORAGE => 'لنگرگاه (Anchorage)',
            self::MODE_STANDBY => 'آماده‌باش (Standby)',
            self::MODE_OPERATION => 'عملیات (Operation)',
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه',
            'vessel_id' => 'شناور',
            'engine_id' => 'موتور',
            'generator_id' => 'ژنراتور',
            'mode' => 'حالت عملیاتی',
            'ideal_consumption' => 'مصرف سوخت ایده‌آل (لیتر/ساعت)',
            'tested_consumption' => 'مصرف سوخت تست‌شده (لیتر/ساعت)',
            'is_deleted' => 'حذف شده',
            'created_at' => 'زمان ایجاد',
            'created_by' => 'ایجادکننده',
            'updated_at' => 'زمان ویرایش',
            'updated_by' => 'ویرایش‌کننده',
        ];
    }
}