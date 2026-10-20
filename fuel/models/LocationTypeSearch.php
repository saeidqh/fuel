<?php

namespace app\modules\fuel\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class LocationTypeSearch extends LocationType
{
    public $lastUser;
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'created_by', 'updated_by'], 'integer'],
            [['name', 'description', 'latitude', 'longitude', 'created_at', 'updated_at', 'lastUser'], 'safe'],
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
        $query = LocationType::find();
        
        $query->andWhere(['is_deleted' => 0]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
                'defaultPageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
                'attributes' => [
                    'id',
                    'name',
                    'description',
                    'latitude',
                    'longitude',
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
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ]);

        $query->andFilterWhere(['like', 'name', $this->name])
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