<?php

namespace app\modules\fuel\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class LocationTypeSearch extends LocationType
{
    public $lastUser;

    /**
     * نام کاربر برای فیلتر (هم ایجادکننده هم آخرین ویرایش‌کننده)
     * @var string
     */
    public $userName;

    public function rules()
    {
        return [
            [['id', 'created_by', 'updated_by'], 'integer'],
            [['name', 'description', 'latitude', 'longitude', 'created_at', 'updated_at', 'lastUser', 'userName'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * ساخت variantهای مختلف یک رشته با در نظر گرفتن ی/ي و ک/ک
     * @param string $search
     * @return array
     */
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
        $query = LocationType::find()->alias('t');
        $query->andWhere(['t.is_deleted' => 0]);

        // join به هر دو رابطه: کاربر ایجادکننده و کاربر آخرین ویرایش‌کننده
        $query->joinWith([
            'createdByUser cu',
            'updatedByUser uu',
        ]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
                'attributes' => [
                    'id', 'name', 'description', 'latitude', 'longitude', 'created_at',
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // فیلترهای ساده
        $query->andFilterWhere(['t.id' => $this->id]);

        $query->andFilterWhere(['like', 't.name', $this->name])
            ->andFilterWhere(['like', 't.description', $this->description])
            ->andFilterWhere(['like', 't.latitude', $this->latitude])
            ->andFilterWhere(['like', 't.longitude', $this->longitude])
            ->andFilterWhere(['like', 't.created_at', $this->created_at]);

        // فیلتر نام کاربر (هم ایجادکننده هم آخرین ویرایش‌کننده)
        // با پشتیبانی از اسم، فامیل، اسم+فامیل با/بدون فاصله، و ی/ي و ک/ک
        if (!empty($this->userName)) {
            $nameVariants = $this->buildPersianVariants($this->userName);

            $nameOr = ['or'];
            foreach ($nameVariants as $v) {
                // کاربر ایجادکننده
                $nameOr[] = ['like', 'cu.FirstName', $v];
                $nameOr[] = ['like', 'cu.LastName', $v];
                $nameOr[] = ['like', "CONCAT(cu.FirstName, ' ', cu.LastName)", $v];
                $nameOr[] = ['like', "CONCAT(cu.FirstName, cu.LastName)", $v];

                // کاربر آخرین ویرایش‌کننده
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