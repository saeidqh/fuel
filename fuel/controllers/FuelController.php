<?php

namespace app\modules\fuel\controllers;

use app\modules\fuel\models\FuelConsumptionSearch;
use yii\filters\AccessControl;
use yii\web\Controller;
use Yii;

class FuelController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['list'],
                'rules' => [
                    ['actions' => ['list'], 'allow' => true, 'roles' => ['@']],
                ],
            ],
        ];
    }

    public function actionList()
    {
        $searchModel = new FuelConsumptionSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('list', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }
}