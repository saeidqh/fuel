<?php

namespace app\modules\fuel\models;

use yii\base\Model;
use yii\data\ArrayDataProvider;

class FuelConsumptionSearch extends Model
{
    public $id;
    public $date;
    public $type;
    public $equipment_name;
    public $fuel_consumption_user_input;
    public $water_consumption_user_input;
    public $fuel_closing_balance;
    public $water_closing_balance;
    public $operating_hours;
    public $status;
    public $location_name;
    public $activity_name;
    public $userName;
    public $created_at;

    public function rules()
    {
        return [
            [['id', 'date', 'type', 'equipment_name', 'fuel_consumption_user_input',
              'water_consumption_user_input', 'fuel_closing_balance', 'water_closing_balance',
              'operating_hours', 'status', 'location_name', 'activity_name', 'userName', 'created_at'], 'safe'],
            [['id', 'date', 'type', 'equipment_name', 'status', 'location_name', 'activity_name', 'userName', 'created_at'], 'trim'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'                            => 'شناسه گزارش',
            'date'                          => 'تاریخ گزارش',
            'type'                          => 'نوع تجهیز',
            'equipment_name'                => 'نام تجهیز',
            'fuel_consumption_user_input'   => 'مصرف واقعی سوخت',
            'water_consumption_user_input'  => 'مصرف واقعی آب',
            'fuel_closing_balance'          => 'موجودی نهایی سوخت',
            'water_closing_balance'         => 'موجودی نهایی آب',
            'operating_hours'               => 'کارکرد',
            'status'                        => 'وضعیت قراردادی',
            'location_name'                 => 'لوکیشن',
            'activity_name'                 => 'فعالیت',
            'userName'                      => 'کاربر ایجادکننده',
            'created_at'                    => 'تاریخ ایجاد',
        ];
    }

    public function search(array $params)
    {
        $this->load($params);

        $rigQuery = RigFuelConsumption::find()
            ->alias('rfc')
            ->with(['rig', 'location', 'activity', 'createdByUser'])
            ->leftJoin(['cu'  => \app\modules\account\models\User::tableName()], 'cu.UserID = rfc.created_by')
            ->leftJoin(['loc' => LocationType::tableName()], 'loc.id = rfc.locationIdRef')
            ->leftJoin(['act' => ActivityType::tableName()], 'act.id = rfc.activityIdRef')
            ->leftJoin(['rig' => RigType::tableName()], 'rig.id = rfc.rigIdRef');

        $vesselQuery = VesselFuelConsumption::find()
            ->alias('vfc')
            ->with(['vessel', 'location', 'activity', 'createdByUser'])
            ->leftJoin(['cu'  => \app\modules\account\models\User::tableName()], 'cu.UserID = vfc.created_by')
            ->leftJoin(['loc' => LocationType::tableName()], 'loc.id = vfc.locationIdRef')
            ->leftJoin(['act' => ActivityType::tableName()], 'act.id = vfc.activityIdRef')
            ->leftJoin(['ves' => VesselType::tableName()], 'ves.id = vfc.vesselIdRef');

        if ($this->validate()) {
            $this->applyFilters($rigQuery, 'rfc', 'rig');
            $this->applyFilters($vesselQuery, 'vfc', 'ves');
        }

        $allRecords = [];

        foreach ($rigQuery->all() as $record) {
            $allRecords[] = [
                'key'              => 'rig-' . $record->id,
                'type'             => 'rig',
                'data'             => $record,
                'createdTimestamp' => $this->toTimestamp($record->created_at),
            ];
        }

        foreach ($vesselQuery->all() as $record) {
            $allRecords[] = [
                'key'              => 'vessel-' . $record->id,
                'type'             => 'vessel',
                'data'             => $record,
                'createdTimestamp' => $this->toTimestamp($record->created_at),
            ];
        }

        usort($allRecords, static function ($a, $b) {
            $comparison = $b['createdTimestamp'] <=> $a['createdTimestamp'];
            if ($comparison !== 0) {
                return $comparison;
            }
            $comparison = (int) $b['data']->id <=> (int) $a['data']->id;
            return $comparison !== 0 ? $comparison : strcmp($a['type'], $b['type']);
        });

        return new ArrayDataProvider([
            'allModels'  => $allRecords,
            'key'        => 'key',
            'sort'       => false,
            'pagination' => ['pageSize' => 50],
        ]);
    }

    private function applyFilters($query, $mainAlias, $equipmentAlias)
    {
        // شناسه گزارش: R-5 یا V-3
        if (!empty($this->id)) {
            $idStr = trim((string) $this->id);
            if (preg_match('/^[RVrv]-?(\d+)$/D', $idStr, $m)) {
                $prefix = strtoupper($idStr[0]);
                if ($prefix === 'R' && $mainAlias === 'rfc') {
                    $query->andWhere(["$mainAlias.id" => (int) $m[1]]);
                } elseif ($prefix === 'V' && $mainAlias === 'vfc') {
                    $query->andWhere(["$mainAlias.id" => (int) $m[1]]);
                } else {
                    $query->andWhere('0=1');
                }
            } else {
                $query->andWhere(['like', "$mainAlias.id", $idStr]);
            }
        }

        // نوع تجهیز
        if (!empty($this->type)) {
            if ($this->type === 'rig' && $mainAlias !== 'rfc') {
                $query->andWhere('0=1');
            } elseif ($this->type === 'vessel' && $mainAlias !== 'vfc') {
                $query->andWhere('0=1');
            }
        }

        // تاریخ گزارش
        if (!empty($this->date)) {
            $query->andWhere(['like', "$mainAlias.date", $this->date]);
        }

        // نام تجهیز
        if (!empty($this->equipment_name)) {
            $query->andWhere(['like', "$equipmentAlias.name", $this->equipment_name]);
        }

        // اعداد
        foreach ([
            'fuel_consumption_user_input',
            'water_consumption_user_input',
            'fuel_closing_balance',
            'water_closing_balance',
            'operating_hours',
        ] as $col) {
            if ($this->$col !== null && $this->$col !== '') {
                $query->andWhere(["$mainAlias.$col" => $this->$col]);
            }
        }

        // وضعیت (فقط شناور)
        if (!empty($this->status)) {
            if ($mainAlias === 'vfc') {
                $query->andWhere(["$mainAlias.status" => $this->status]);
            } else {
                $query->andWhere('0=1');
            }
        }

        // لوکیشن
        if (!empty($this->location_name)) {
            $query->andWhere(['like', 'loc.name', $this->location_name]);
        }

        // فعالیت
        if (!empty($this->activity_name)) {
            $query->andWhere(['like', 'act.name', $this->activity_name]);
        }

        // نام کاربر (با ی/ي و ک/ک)
        if (!empty($this->userName)) {
            $variants = $this->buildPersianVariants($this->userName);
            $query->andWhere($this->buildUserNameCondition('cu', $variants));
        }

        // تاریخ ایجاد
        if (!empty($this->created_at)) {
            $query->andWhere(['like', "$mainAlias.created_at", $this->created_at]);
        }
    }

    private function buildPersianVariants($search)
    {
        $search = trim((string) $search);
        if ($search === '') {
            return [];
        }
        $variants = [
            $search,
            str_replace('ی', 'ي', $search),
            str_replace('ي', 'ی', $search),
            str_replace('ک', 'ك', $search),
            str_replace('ك', 'ک', $search),
            str_replace(['ی', 'ک'], ['ي', 'ك'], $search),
            str_replace(['ي', 'ك'], ['ی', 'ک'], $search),
        ];
        return array_values(array_unique($variants));
    }

    private function buildUserNameCondition($alias, array $variants)
    {
        $or = ['or'];
        foreach ($variants as $v) {
            $or[] = ['like', "$alias.FirstName", $v];
            $or[] = ['like', "$alias.LastName", $v];
            $or[] = ['like', "CONCAT($alias.FirstName, ' ', $alias.LastName)", $v];
            $or[] = ['like', "CONCAT($alias.FirstName, $alias.LastName)", $v];
        }
        return $or;
    }

    private function toTimestamp($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }
        return is_numeric($value) ? (int) $value : (strtotime($value) ?: 0);
    }
}