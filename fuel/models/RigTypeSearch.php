<?php

namespace app\modules\fuel\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class RigTypeSearch extends RigType
{
    public $lastUser;
    public $location_name;
    public $userName;

    public function rules()
    {
        return [
            [['id', 'created_by', 'updated_by', 'engine_count', 'generator_count', 'location_id', 'build_year'], 'integer'],
            [['name', 'code', 'description', 'rig_document', 'created_at', 'updated_at', 'lastUser', 'location_name', 'userName'], 'safe'],
            [['initial_fuel_balance', 'initial_water_balance'], 'number'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
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

    public function search($params)
    {
        $rigTable  = RigType::tableName();
        $locTable  = LocationType::tableName();
        $userTable = \app\modules\account\models\User::tableName();

        // از parent::find() استفاده می‌کنیم تا is_deleted خودکارِ RigType::find() اضافه نشه
        $query = parent::find()->alias('t');

        // join دستی به‌جای joinWith، تا هیچ شرط خودکاری (is_deleted) اضافه نشه
        $query->leftJoin(['loc' => $locTable], "loc.id = t.location_id")
              ->leftJoin(['cu' => $userTable], "cu.UserID = t.created_by")
              ->leftJoin(['uu' => $userTable], "uu.UserID = t.updated_by");

        // is_deleted فقط با alias صریح t
        $query->where(['t.is_deleted' => 0]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
                'attributes' => [
                    'id', 'name', 'code', 'engine_count', 'generator_count',
                    'location_id', 'initial_fuel_balance', 'initial_water_balance', 'created_at',
                    'location_name' => [
                        'asc'  => ['loc.name' => SORT_ASC],
                        'desc' => ['loc.name' => SORT_DESC],
                    ],
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            't.id' => $this->id,
            't.engine_count' => $this->engine_count,
            't.generator_count' => $this->generator_count,
            't.location_id' => $this->location_id,
            't.initial_fuel_balance' => $this->initial_fuel_balance,
            't.initial_water_balance' => $this->initial_water_balance,
        ]);

        $query->andFilterWhere(['like', 't.name', $this->name])
            ->andFilterWhere(['like', 't.code', $this->code])
            ->andFilterWhere(['like', 't.created_at', $this->created_at])
            ->andFilterWhere(['like', 'loc.name', $this->location_name]);

        if (!empty($this->userName)) {
            $nameVariants = $this->buildPersianVariants($this->userName);

            $nameOr = ['or'];
            foreach ($nameVariants as $v) {
                $nameOr[] = ['like', 'cu.FirstName', $v];
                $nameOr[] = ['like', 'cu.LastName', $v];
                $nameOr[] = ['like', "CONCAT(cu.FirstName, ' ', cu.LastName)", $v];
                $nameOr[] = ['like', "CONCAT(cu.FirstName, cu.LastName)", $v];

                $nameOr[] = ['like', 'uu.FirstName', $v];
                $nameOr[] = ['like', 'uu.LastName', $v];
                $nameOr[] = ['like', "CONCAT(uu.FirstName, ' ', uu.LastName)", $v];
                $nameOr[] = ['like', "CONCAT(uu.FirstName, uu.LastName)", $v];
            }

            $query->andWhere($nameOr);
        }

        return $dataProvider;
    }
}