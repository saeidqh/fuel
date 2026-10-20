<?php

namespace app\modules\fuel\models;

use yii\db\ActiveRecord;

class RigEngine extends ActiveRecord
{
    public static function tableName()
    {
        return 'rig_engine_table_rig_engine';
    }

    public function rules()
    {
        return [
            [['engine_type'], 'trim'],
            [['engine_type'], 'string', 'max' => 100],

            [['rig_id', 'engine_number'], 'required'],
            [['rig_id'], 'integer', 'min' => 1],
            [
                ['engine_number'],
                'integer',
                'min' => 1,
                'max' => RigType::MAX_EQUIPMENT_COUNT,
            ],

            [['engine_power'], 'default', 'value' => null],

            [
                [
                    'engine_fuel_consumption',
                    'engine_tested_fuel_consumption',
                ],
                'required',
                'message' => '{attribute} الزامی است.',
            ],
            [
                [
                    'engine_power',
                    'engine_fuel_consumption',
                    'engine_tested_fuel_consumption',
                ],
                'number',
                'min' => 0,
                'max' => 99999999.99,
            ],

          
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه',
            'rig_id' => 'شناسه دکل',
            'engine_number' => 'شماره موتور',
            'engine_type' => 'نوع موتور',
            'engine_power' => 'قدرت موتور (اسب بخار)',
            'engine_fuel_consumption' => 'نرخ مصرف ایده‌آل (لیتر/ساعت)',
            'engine_tested_fuel_consumption' => 'نرخ مصرف تست‌شده (لیتر/ساعت)',
            'created_at' => 'زمان ایجاد',
            'updated_at' => 'زمان به‌روزرسانی',
        ];
    }

    public function getRig()
    {
        return $this->hasOne(RigType::class, ['id' => 'rig_id']);
    }
}