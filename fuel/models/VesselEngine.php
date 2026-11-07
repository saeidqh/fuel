<?php

namespace app\modules\fuel\models;

use yii\db\ActiveRecord;

class VesselEngine extends ActiveRecord
{
    public static function tableName()
    {
        return 'vessel_table_vessel_engine';
    }

    public function rules()
    {
        return [
            [['engine_type'], 'trim'],
            [['engine_power', 'engine_year', 'engine_type'], 'default', 'value' => null],
            [['vessel_id', 'engine_number'], 'required'],
            [['vessel_id'], 'integer', 'min' => 1],
            [['engine_number'], 'integer', 'min' => 1, 'max' => 5],
            [['engine_year'], 'integer', 'min' => 1, 'max' => 9999],
            [['engine_power'], 'number', 'min' => 0, 'max' => 99999999.99],
            [['engine_power'], 'match', 'pattern' => '/^\d+(?:\.\d{1,2})?$/D', 'message' => 'مقدار را با حداکثر دو رقم اعشار وارد کنید.'],
            [['engine_type'], 'string', 'max' => 50],
            [['is_deleted'], 'default', 'value' => 0],
            [['is_deleted'], 'boolean'],
            [['vessel_id'], 'exist', 'targetClass' => VesselType::class, 'targetAttribute' => 'id'],
            [['engine_number'], 'unique', 'targetAttribute' => ['vessel_id', 'engine_number'], 'message' => 'این شماره موتور برای شناور ثبت شده است.'],
            [['vessel_id', 'engine_number'], 'validateIdentity'],
        ];
    }

    public function validateIdentity($attribute, $params)
    {
        if (!$this->isNewRecord && $this->isAttributeChanged($attribute, false)) {
            $this->addError($attribute, 'شناور و شماره ثابت موتور پس از ثبت قابل تغییر نیستند.');
        }
    }

    public function getVessel()
    {
        return $this->hasOne(VesselType::class, ['id' => 'vessel_id']);
    }

    public function getFuelModes()
    {
        return $this->hasMany(VesselFuelMode::class, ['engine_id' => 'id']);
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه',
            'vessel_id' => 'شناور',
            'engine_number' => 'شماره موتور',
            'engine_power' => 'قدرت موتور',
            'engine_type' => 'نوع موتور',
            'engine_year' => 'سال ساخت',
            'is_deleted' => 'حذف شده',
        ];
    }
}