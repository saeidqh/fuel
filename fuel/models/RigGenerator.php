<?php

namespace app\modules\fuel\models;

use yii\db\ActiveRecord;

class RigGenerator extends ActiveRecord
{
    public static function tableName()
    {
        return 'rig_generator_table_rig_generator';
    }

    public function rules()
    {
        return [
            [['generator_type'], 'trim'],
            [['generator_type'], 'string', 'max' => 100],

            [['rig_id', 'generator_number'], 'required'],
            [['rig_id'], 'integer', 'min' => 1],
            [
                ['generator_number'],
                'integer',
                'min' => 1,
                'max' => RigType::MAX_EQUIPMENT_COUNT,
            ],

            [['generator_power'], 'default', 'value' => null],

            [
                [
                    'generator_fuel_consumption',
                    'generator_tested_fuel_consumption',
                ],
                'required',
                'message' => '{attribute} الزامی است.',
            ],
            [
                [
                    'generator_power',
                    'generator_fuel_consumption',
                    'generator_tested_fuel_consumption',
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
            'generator_number' => 'شماره ژنراتور',
            'generator_type' => 'نوع ژنراتور',
            'generator_power' => 'قدرت ژنراتور (کیلووات)',
            'generator_fuel_consumption' => 'نرخ مصرف ایده‌آل (لیتر/ساعت)',
            'generator_tested_fuel_consumption' => 'نرخ مصرف تست‌شده (لیتر/ساعت)',
            'created_at' => 'زمان ایجاد',
            'updated_at' => 'زمان به‌روزرسانی',
        ];
    }

    public function getRig()
    {
        return $this->hasOne(RigType::class, ['id' => 'rig_id']);
    }
}