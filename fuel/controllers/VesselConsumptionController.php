<?php
namespace app\modules\fuel\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use app\modules\fuel\models\VesselType;
use app\modules\fuel\models\VesselConsumptionForm;
use app\modules\fuel\models\VesselFuelConsumption;

class VesselConsumptionController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['view','create','update','delete','equipment','calculation'],
                'rules' => [
                        [
                        'actions' => ['view','create','update','delete','equipment','calculation'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['GET', 'POST'],
                    'update' => ['GET', 'POST'],
                    'view' => ['GET'],
                    'equipment' => ['GET'],
                    'calculate' => ['POST'],
                ],
            ],
        ];
    }

    public function actionCreate($vesselId = null)
    {
        $report = new VesselFuelConsumption();

        if (!Yii::$app->request->isPost) {
            $report->loadCreateDefaults($vesselId);
        }

        return $this->processForm($report, 'create');
    }

    public function actionUpdate($id)
    {
        return $this->processForm($this->findModel($id), 'update');
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'report' => $this->findModel($id),
        ]);
    }

    public function actionEquipment($vesselId)
    {
        $vessel = $this->findVessel($vesselId);
        $report = new VesselFuelConsumption();
        $report->vesselIdRef = $vessel->id;
        $formModel = new VesselConsumptionForm($report);
        $data = $formModel->getViewData();

        return $this->asJson([
            'status' => $vessel->status,
            'html' => $this->renderPartial('_equipment', [
                'itemRows' => $data['itemRows'],
                'modeOptions' => $data['modeOptions'],
            ]) . $this->renderPartial('_extra', [
                'itemRows' => $data['itemRows'],
            ]),
        ]);
    }

public function actionCalculate($id = null)
{
    $report = $id === null
        ? new VesselFuelConsumption()
        : $this->findModel($id);

    $formModel = new VesselConsumptionForm($report);

    if (!$formModel->loadSubmission(Yii::$app->request->post())
        || !$report->calculatePreview()) {
        $errors = array_values($report->getFirstErrors());

        return $this->asJson([
            'success' => false,
            'errors' => $errors ?: [
                'اطلاعات فرم برای محاسبه کامل نیست.',
            ],
            'summary' => null,
            'fuelSummary' => null,
            'waterSummary' => null,
        ]);
    }

    return $this->asJson([
        'success' => true,
        'errors' => [],
        'summary' => $report->getConsumptionSummary(),
        'fuelSummary' => $report->getFuelBalanceSummary(),
        'waterSummary' => $report->getWaterBalanceSummary(),
    ]);
}
public function actionRate()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $request = Yii::$app->request;
        $vesselId = (int) $request->post('vessel_id');
        $engineId = $request->post('engine_id');
        $generatorId = $request->post('generator_id');
        $mode = $request->post('operation_mode');

        if (!$vesselId || !$mode) {
            return ['success' => false, 'rate' => 0];
        }

        $query = \app\modules\fuel\models\VesselFuelMode::find()
            ->where([
                'vessel_id' => $vesselId,
                'mode' => $mode,
                'is_deleted' => 0,
            ]);

        if (!empty($engineId)) {
            $query->andWhere(['engine_id' => (int) $engineId]);
        } elseif (!empty($generatorId)) {
            $query->andWhere(['generator_id' => (int) $generatorId]);
        } else {
            return ['success' => false, 'rate' => 0];
        }

        $fuelMode = $query->one();

        return [
            'success' => true,
            'rate' => $fuelMode ? (float) $fuelMode->tested_consumption : 0,
        ];
    }

    private function processForm(VesselFuelConsumption $report, $view)
    {
        $formModel = new VesselConsumptionForm($report);

        if (Yii::$app->request->isPost
            && $formModel->loadSubmission(Yii::$app->request->post())
            && $report->saveWithDetails()) {
            Yii::$app->session->setFlash(
                'success',
                'گزارش مصرف شناور با موفقیت ذخیره شد.'
            );

            return $this->redirect(['/fuel/fuel/list']);
        }

        return $this->render($view, $formModel->getViewData());
    }

    private function findModel($id)
    {
        $this->validateId($id);
        $model = VesselFuelConsumption::findOne(['id' => $id]);

        if (!$model) {
            throw new NotFoundHttpException('گزارش مصرف پیدا نشد.');
        }

        return $model;
    }

    private function findVessel($id)
    {
        $this->validateId($id);
        $model = VesselType::findOne([
            'id' => $id,
            'is_deleted' => 0,
        ]);

        if (!$model) {
            throw new NotFoundHttpException('شناور پیدا نشد یا حذف شده است.');
        }

        return $model;
    }

    private function validateId($id)
    {
        if (!is_scalar($id) || !preg_match('/\A[1-9]\d*\z/', (string) $id)) {
            throw new NotFoundHttpException('شناسه معتبر نیست.');
        }
    }
}