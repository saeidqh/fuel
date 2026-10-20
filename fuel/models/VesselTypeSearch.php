<?php

namespace app\modules\fuel\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class VesselTypeSearch extends VesselType
{
    public $lastUser;
    
    public function rules()
    {
        return [
            [['id', 'created_by', 'updated_by', 'engine_count'], 'integer'],
            [['name', 'code', 'status', 'description', 'created_at', 'updated_at', 'lastUser'], 'safe'],
            [['initial_fuel_balance', 'initial_water_balance', 'vessel_speed', 'bow_thruster_fuel'], 'number'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = VesselType::find();
        $query->andWhere(['is_deleted' => 0]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
                'attributes' => [
                    'id',
                    'name',
                    'code',
                    'status',
                    'description',
                    'engine_count',
                    'vessel_speed',
                    'bow_thruster_fuel',
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

        $query->andFilterWhere([
            'id' => $this->id,
            'engine_count' => $this->engine_count,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'initial_fuel_balance' => $this->initial_fuel_balance,
            'initial_water_balance' => $this->initial_water_balance,
            'vessel_speed' => $this->vessel_speed,
            'bow_thruster_fuel' => $this->bow_thruster_fuel,
        ]);

        $query->andFilterWhere(['like', 'name', $this->name])
              ->andFilterWhere(['like', 'code', $this->code])
              ->andFilterWhere(['like', 'status', $this->status])
              ->andFilterWhere(['like', 'description', $this->description])
              ->andFilterWhere(['like', 'created_at', $this->created_at])
              ->andFilterWhere(['like', 'updated_at', $this->updated_at]);

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