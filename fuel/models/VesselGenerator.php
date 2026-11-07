<?php

namespace app\modules\fuel\models;

use yii\db\ActiveRecord;

class VesselGenerator extends ActiveRecord
{
    public static function tableName()
    {
        return 'vessel_table_vessel_generator';
    }

    public function rules()
    {
        return [
            [['gen_type'], 'trim'],
            [['gen_power', 'gen_year', 'gen_type'], 'default', 'value' => null],
            [['vessel_id', 'generator_number'], 'required'],
            [['vessel_id'], 'integer', 'min' => 1],
            [['generator_number'], 'integer', 'min' => 1, 'max' => 5],
            [['gen_year'], 'integer', 'min' => 1, 'max' => 9999],
            [['gen_power'], 'number', 'min' => 0, 'max' => 99999999.99],
            [['gen_power'], 'match', 'pattern' => '/^\d+(?:\.\d{1,2})?$/D', 'message' => 'مقدار را با حداکثر دو رقم اعشار وارد کنید.'],
            [['gen_type'], 'string', 'max' => 50],
            [['is_deleted'], 'default', 'value' => 0],
            [['is_deleted'], 'boolean'],
            [['vessel_id'], 'exist', 'targetClass' => VesselType::class, 'targetAttribute' => 'id'],
            [['generator_number'], 'unique', 'targetAttribute' => ['vessel_id', 'generator_number'], 'message' => 'این شماره ژنراتور برای شناور ثبت شده است.'],
            [['vessel_id', 'generator_number'], 'validateIdentity'],
        ];
    }

    public function validateIdentity($attribute, $params)
    {
        if (!$this->isNewRecord && $this->isAttributeChanged($attribute, false)) {
            $this->addError($attribute, 'شناور و شماره ثابت ژنراتور پس از ثبت قابل تغییر نیستند.');
        }
    }

    public function getVessel()
    {
        return $this->hasOne(VesselType::class, ['id' => 'vessel_id']);
    }

    public function getFuelModes()
    {
        return $this->hasMany(VesselFuelMode::class, ['generator_id' => 'id']);
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه',
            'vessel_id' => 'شناور',
            'generator_number' => 'شماره ژنراتور',
            'gen_power' => 'قدرت ژنراتور (کیلووات)',
            'gen_type' => 'نوع ژنراتور',
            'gen_year' => 'سال ساخت',
            'is_deleted' => 'حذف شده',
        ];
    }
}