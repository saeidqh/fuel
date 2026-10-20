<?php

namespace app\modules\fuel\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class ActivityTypeSearch extends ActivityType
{
    public $lastUser;  // فیلد مجازی برای جستجوی آخرین کاربر
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'created_by', 'updated_by'], 'integer'],
            [['name', 'description', 'created_at', 'updated_at', 'lastUser'], 'safe'],
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
        $query = ActivityType::find();
        
        // اگر از soft delete استفاده می‌کنی، فقط رکوردهای حذف نشده را نشان بده
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

        // فیلترهای اصلی
        $query->andFilterWhere([
            'id' => $this->id,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ]);

        // فیلترهای like (متن)
        $query->andFilterWhere(['like', 'name', $this->name])
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