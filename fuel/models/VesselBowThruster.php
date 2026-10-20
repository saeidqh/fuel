<?php

namespace app\modules\fuel\models;

use yii\db\ActiveRecord;
use yii\db\Expression;

class VesselBowThruster extends ActiveRecord
{
    const MAX_VALUE = 99999999.99;

    public static function tableName()
    {
        return 'vessel_table_vessel_bow_thruster';
    }

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => [
                '!vessel_id',
                'power_kw',
               // 'nominal_consumption',
                'engine_type',
                'manufacture_year',
                '!is_deleted',
                '!created_by',
                '!updated_by',
            ],
        ];
    }

public function rules()
{
    return [
        [['power_kw', 'engine_type', 'manufacture_year'], 'filter', 'filter' => function ($value) { return is_string($value) ? trim($value) : $value; }],
        [['power_kw', 'nominal_consumption', 'engine_type', 'manufacture_year'], 'default', 'value' => null],
        [['is_deleted'], 'default', 'value' => 0],
        [['vessel_id'], 'required'],
        [['vessel_id', 'created_by', 'updated_by'], 'integer', 'min' => 1],
        [['power_kw', 'nominal_consumption'], 'number', 'min' => 0, 'max' => self::MAX_VALUE],
        [['power_kw', 'nominal_consumption'], 'match', 'pattern' => '/\A\d+(?:\.\d{1,2})?\z/', 'message' => 'مقدار را با حداکثر دو رقم اعشار وارد کنید.'],
        [['engine_type'], 'string', 'max' => 50],
        [['manufacture_year'], 'integer', 'min' => 1, 'max' => 9999],
        [['is_deleted'], 'in', 'range' => [0, 1]],
        [['vessel_id'], 'unique', 'message' => 'برای این شناور قبلاً مشخصات بوتراستر ثبت شده است.'],
        [['vessel_id'], 'exist', 'targetClass' => VesselType::class, 'targetAttribute' => ['vessel_id' => 'id'], 'message' => 'شناور مرتبط معتبر یا فعال نیست.'],
    ];
}

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->updated_at = new Expression('CURRENT_TIMESTAMP');

        return true;
    }

    public function getVessel()
    {
        return $this->hasOne(
            VesselType::class,
            ['id' => 'vessel_id']
        );
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه',
            'vessel_id' => 'شناور',
            'power_kw' => 'قدرت بوتراستر (کیلووات)',
            'nominal_consumption' => 'مصرف نامی بوتراستر (لیتر/ساعت)',
            'engine_type' => 'نوع موتور بوتراستر',
            'manufacture_year' => 'سال ساخت',
            'is_deleted' => 'غیرفعال',
            'created_at' => 'زمان ایجاد',
            'created_by' => 'ایجادکننده',
            'updated_at' => 'زمان ویرایش',
            'updated_by' => 'ویرایش‌کننده',
        ];
    }
}