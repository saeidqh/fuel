<?php

namespace app\modules\fuel\controllers;

use app\modules\fuel\models\RigFuelConsumption;
use app\modules\fuel\models\VesselFuelConsumption;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;

class FuelController extends Controller
{
    public function behaviors()
    {
                return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['list'],
                'rules' => [
                        [
                        'actions' => ['list'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
//================
////                        [
//                        'actions' => ['view', 'start-call-chat', 'update', 'close', 'auto-close'],
//                        'allow' => true,
//                        'roles' => ['@'],
//                        'matchCallback' => function ($rule, $action, $exteraData = []) {
//                            return \app\access\AccessControl::isAccess([
//                                        'or',
//                                            ['formAction', 'aid' => 446],
//                                            ['obs', 'check' => 'isCandoAdmin']
//                            ]);
//                        },
//                    ],
//=======================                
//        return [
//            'access' => [
//                'class' => AccessControl::class,
//                'rules' => [
//                    [
//                        'allow' => true,
//                        'roles' => ['@'],
//                    ],
//                ],
//            ],
//            'verbs' => [
//                'class' => VerbFilter::class,
//                'actions' => [
//                    'list' => ['GET'],
//                ],
//            ],
//        ];
    }

public function actionList()
{
    $rigConsumptionProvider = new ActiveDataProvider([
        'query' => RigFuelConsumption::find()->with([
            'rig',
            'location',
            'activity',
            'createdByUser',
        ]),
        'pagination' => false,
    ]);

    $vesselConsumptionProvider = new ActiveDataProvider([
        'query' => VesselFuelConsumption::find()->with([
            'vessel',
            'location',
            'activity',
            'createdByUser',
        ]),
        'pagination' => false,
    ]);

    return $this->render('list', [
        'rigConsumptionProvider' => $rigConsumptionProvider,
        'vesselConsumptionProvider' => $vesselConsumptionProvider,
    ]);
}
}