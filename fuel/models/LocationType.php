<?php

namespace app\modules\fuel\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "location_table_location".
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $created_by
 * @property string $created_at
 * @property string $updated_at
 * @property int|null $updated_by
 * @property int|null $is_deleted
 */
class LocationType extends ActiveRecord
{
    public static function tableName()
    {
        return 'location_table_location';
    }

    public function behaviors()
    {
        return [];
    }

    public function rules()
    {
        return [
            [['name'], 'required', 'message' => 'نام لوکیشن اجباری است.'],
            [['name'], 'string', 'max' => 100, 'tooLong' => 'نام لوکیشن نباید بیشتر از 100 کاراکتر باشد.'],
            [['description'], 'string'],
            [['latitude', 'longitude'], 'number', 'min' => -180, 'max' => 180],
            [['latitude'], 'validateLatitude'],
            [['longitude'], 'validateLongitude'],
            [['created_by', 'updated_by', 'is_deleted'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function validateLatitude($attribute, $params)
    {
        if ($this->latitude !== null && ($this->latitude < -90 || $this->latitude > 90)) {
            $this->addError($attribute, 'عرض جغرافیایی باید بین -90 و 90 درجه باشد.');
        }
    }

    public function validateLongitude($attribute, $params)
    {
        if ($this->longitude !== null && ($this->longitude < -180 || $this->longitude > 180)) {
            $this->addError($attribute, 'طول جغرافیایی باید بین -180 و 180 درجه باشد.');
        }
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه',
            'name' => 'نام لوکیشن',
            'description' => 'توضیحات',
            'latitude' => 'عرض جغرافیایی',
            'longitude' => 'طول جغرافیایی',
            'created_by' => 'ایجاد شده توسط',
            'created_at' => 'زمان ایجاد',
            'updated_at' => 'زمان به‌روزرسانی',
            'updated_by' => 'آخرین ویرایش توسط',
            'is_deleted' => 'حذف شده',
        ];
    }

    public static function getLocationList()
    {
        return self::find()
            ->select(['name', 'id'])
            ->indexBy('id')
            ->column();
    }

    public function getCreatedByUser()
    {
        return $this->hasOne(\app\modules\account\models\User::class, ['UserID' => 'created_by']);
    }

    public function getUpdatedByUser()
    {
        return $this->hasOne(\app\modules\account\models\User::class, ['UserID' => 'updated_by']);
    }

    public function getCreatedAtJalali()
    {
        if (!$this->created_at || $this->created_at == '0000-00-00 00:00:00') {
            return '-';
        }
        $jDate = new \app\components\jDateTime(true, true, 'Asia/Tehran');
        $timestamp = strtotime($this->created_at);
        $jalaliDate = $jDate->toJalali(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp));
        return $jalaliDate[0] . '/' . $jalaliDate[1] . '/' . $jalaliDate[2] . ' ' . date('H:i', $timestamp);
    }

    public function getUpdatedAtJalali()
    {
        if (!$this->updated_at || $this->updated_at == '0000-00-00 00:00:00') {
            return '-';
        }
        $jDate = new \app\components\jDateTime(true, true, 'Asia/Tehran');
        $timestamp = strtotime($this->updated_at);
        $jalaliDate = $jDate->toJalali(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp));
        return $jalaliDate[0] . '/' . $jalaliDate[1] . '/' . $jalaliDate[2] . ' ' . date('H:i', $timestamp);
    }

    public static function calculateDistance($fromLocationId, $toLocationId)
    {
        if (!$fromLocationId || !$toLocationId || $fromLocationId == $toLocationId) {
            return 0.00;
        }
        $from = self::findOne($fromLocationId);
        $to = self::findOne($toLocationId);
        if (!$from || !$to || $from->latitude === null || $from->longitude === null || $to->latitude === null || $to->longitude === null) {
            return 0.00;
        }
        $earthRadiusKm = 6371;
        $latFrom = deg2rad((float)$from->latitude);
        $lonFrom = deg2rad((float)$from->longitude);
        $latTo = deg2rad((float)$to->latitude);
        $lonTo = deg2rad((float)$to->longitude);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        $distanceKm = $angle * $earthRadiusKm;
        $distanceNauticalMiles = $distanceKm / 1.852;
        return round($distanceNauticalMiles, 2);
    }

    public static function getLocationListWithCoords()
    {
        $locations = self::find()->all();
        $list = [];
        $coords = [];
        foreach ($locations as $loc) {
            $list[$loc->id] = $loc->name;
            $coords[$loc->id] = [
                'lat' => (float)$loc->latitude,
                'lng' => (float)$loc->longitude,
            ];
        }
        return ['list' => $list, 'coords' => $coords];
    }

    public static function find()
    {
        return parent::find()->andWhere(['is_deleted' => 0]);
    }
}