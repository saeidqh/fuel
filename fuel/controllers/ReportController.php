<?php

namespace app\modules\fuel\controllers;

use app\modules\fuel\models\FuelReportSearch;
use Yii;
use yii\web\Controller;

class ReportController extends Controller
{
public function actionList()
{
    $searchModel = new FuelReportSearch();
    $searchModel->load(\Yii::$app->request->get());
    $reportData = [];
    if (
        $searchModel->equipment_id
        && $searchModel->date_from
        && $searchModel->date_to
    ) {
        if ($searchModel->equipment_type === FuelReportSearch::TYPE_RIG) {
            $reportData = $searchModel->getRigDashboardData();
        } elseif ($searchModel->equipment_type === FuelReportSearch::TYPE_VESSEL) {
            $reportData = $searchModel->getVesselDashboardData();
        }
    }
    return $this->render('list', [
        'searchModel' => $searchModel,
        'reportData' => $reportData,
    ]);
}
}