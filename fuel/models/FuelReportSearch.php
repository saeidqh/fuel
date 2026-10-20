<?php

namespace app\modules\fuel\models;

use yii\base\Model;
use yii\data\ArrayDataProvider;
use app\components\jDateTime;

class FuelReportSearch extends Model
{
    const TYPE_RIG = 'rig';
    const TYPE_VESSEL = 'vessel';

    public $equipment_type;
    public $equipment_id;
    public $date_from;
    public $date_to;

    /**
     * @return array
     */
    public function rules()
    {
        return [
            [['equipment_type', 'equipment_id', 'date_from', 'date_to'],'required','message' => 'فیلد {attribute} الزامی است.',],
            [['equipment_type'],'in','range' => [ self::TYPE_RIG, self::TYPE_VESSEL,],'message' => 'نوع تجهیز معتبر نیست.',],
            [['equipment_id'],'integer','message' => 'شناسه تجهیز باید عدد صحیح باشد.',],
            [['equipment_id'],'compare','compareValue' => 0,'operator' => '>','message' => 'شناسه تجهیز باید بزرگ‌تر از صفر باشد.',],
            [['date_from', 'date_to'],'filter','filter' => function ($value) {return $this->normalizeDigits($value);},],
            [['date_from', 'date_to'], 'validateJalaliDate',],
            [['date_to'],'validateDateRange',],
        ];
    }
    public function attributeLabels()
    {
        return [
            'equipment_type' => 'نوع تجهیز',
            'equipment_id' => 'شناسه تجهیز',
            'date_from' => 'از تاریخ',
            'date_to' => 'تا تاریخ',
        ];
    }
    public function search($params)
    {
        if (!$this->load($params) || !$this->validate()) {
            return $this->emptyDataProvider();
        }

        return $this->getReportQuery()->getDataProvider($this);
    }
    public function getRigDashboardData()
    {
        if (
            $this->equipment_type !== self::TYPE_RIG
            || !$this->validate()
        ) {
            return $this->emptyDashboardData();
        }

        $query = new RigReportQuery();

        return $query->getDashboardData([
            'rig_id' => (int) $this->equipment_id,
            'date_from' => $this->getGregorianDateFrom(),
            'date_to' => $this->getGregorianDateTo(),
        ]);
    }
    public function getVesselDashboardData()
    {
    if (
        $this->equipment_type !== self::TYPE_VESSEL
        || !$this->validate()
    ) {
        return $this->emptyDashboardData();
    }

    $query = new VesselReportQuery();
    return $query->getDashboardData([
        'vessel_id' => (int) $this->equipment_id,
        'date_from' => $this->getGregorianDateFrom(),
        'date_to' => $this->getGregorianDateTo(),
    ]);
}
    public function validateJalaliDate($attribute, $params)
    {
        $value = (string) $this->$attribute;

        if ($value === '') {
            return;
        }

        if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $value)) {
            $this->addError(
                $attribute,
                $this->getAttributeLabel($attribute)
                . ' باید با فرمت 1405/01/01 وارد شود.'
            );

            return;
        }

        if (!jDateTime::mCheckdate($value)) {
            $this->addError(
                $attribute,
                $this->getAttributeLabel($attribute)
                . ' معتبر نیست.'
            );
        }
    }
    public function validateDateRange($attribute, $params)
    {
        if ($this->hasErrors('date_from') || $this->hasErrors('date_to')) {
            return;
        }
        if ($this->date_from > $this->date_to) {
            $this->addError('date_to', 'بازه تاریخی اشتباه است.');
        }
    }

    public function getGregorianDateFrom()
    {
        return $this->jalaliToGregorian($this->date_from);
    }

    public function getGregorianDateTo()
    {
        return $this->jalaliToGregorian($this->date_to);
    }

    private function getReportQuery()
    {
        if ($this->equipment_type === self::TYPE_RIG) {
            return new RigReportQuery();
        }

        return new VesselReportQuery();
    }

    private function jalaliToGregorian($jalaliDate)
    {
        list($year, $month, $day) = explode('/', $jalaliDate);

        $gregorianDate = jDateTime::toGregorian(
            (int) $year,
            (int) $month,
            (int) $day
        );
        return sprintf('%04d-%02d-%02d',$gregorianDate[0],$gregorianDate[1],$gregorianDate[2]);
    }

    private function normalizeDigits($value)
    {
        $value = trim((string) $value);

        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',

            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    private function emptyDataProvider()
    {
        return new ArrayDataProvider([
            'allModels' => [],
            'pagination' => false,
        ]);
    }

    private function emptyDashboardData()
    {
        return [
            'summary' => [
                'reports_count' => 0,
                'report_days' => 0,
                'total_operating_hours' => 0,
                'actual_consumption' => 0,
                'ideal_consumption' => 0,
                'consumption_difference' => 0,
                'deviation_percent' => 0,
                'fuel_received' => 0,
                'fuel_transferred' => 0,
                'fuel_used' => 0,
            ],
            'daily_consumption' => [],
            'daily_difference' => [],
            'daily_operating_hours' => [],
        ];
    }

    public static function equipmentTypeOptions()
    {
        return [
            self::TYPE_RIG => 'دکل',
            self::TYPE_VESSEL => 'شناور',
        ];
    }
}