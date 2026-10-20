<?php

namespace app\modules\fuel\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class RigTypeSearch extends RigType
{
    public $lastUser;
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'created_by', 'updated_by', 'engine_count', 'generator_count', 'location_id', 'build_year'], 'integer'],
            [['name', 'code', 'description', 'rig_document', 'created_at', 'updated_at', 'lastUser'], 'safe'],
            [['initial_fuel_balance', 'initial_water_balance'], 'number'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = RigType::find();
        
        // فقط رکوردهای حذف نشده
        $query->andWhere(['is_deleted' => 0]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
                'attributes' => [
                    'id',
                    'name',
                    'code',
                    'build_year',
                    'description',
                    'engine_count',
                    'generator_count',
                    'location_id',
                    'initial_fuel_balance',
                    'initial_water_balance',
                    'created_at',
                    'updated_at',
                    'created_by',
                    'updated_by',
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // فیلترهای دقیق (عدد مساوی)
        $query->andFilterWhere([
            'id' => $this->id,
            'engine_count' => $this->engine_count,
            'generator_count' => $this->generator_count,
            'location_id' => $this->location_id,
            'build_year' => $this->build_year,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'initial_fuel_balance' => $this->initial_fuel_balance,
            'initial_water_balance' => $this->initial_water_balance,
        ]);

        // فیلترهای متنی (like)
        $query->andFilterWhere(['like', 'name', $this->name])
              ->andFilterWhere(['like', 'code', $this->code])
              ->andFilterWhere(['like', 'description', $this->description])
              ->andFilterWhere(['like', 'created_at', $this->created_at])
              ->andFilterWhere(['like', 'updated_at', $this->updated_at]);

        // جستجو بر اساس نام کاربر (lastUser)
        if (!empty($this->lastUser)) {
            $searchTerm = str_replace('ی', 'ي', $this->lastUser);
            $query->andFilterWhere([
                'or',
                ['like', 'createdByUser.FirstName', $searchTerm],
                ['like', 'createdByUser.LastName', $searchTerm],
                ['like', 'updatedByUser.FirstName', $searchTerm],
                ['like', 'updatedByUser.LastName', $searchTerm],
                ['like', "CONCAT(createdByUser.FirstName, ' ', createdByUser.LastName)", $searchTerm],
                ['like', "CONCAT(updatedByUser.FirstName, ' ', updatedByUser.LastName)", $searchTerm],
            ]);
        }

        return $dataProvider;
    }
}