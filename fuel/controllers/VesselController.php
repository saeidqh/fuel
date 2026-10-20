<?php
namespace app\modules\fuel\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;
use app\modules\fuel\models\VesselType;

class VesselController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['view','create','update','delete','save'],
                'rules' => [
                        [
                        'actions' => ['view','create','update','delete','save'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'list' => ['GET'],
                    'view' => ['GET'],
                    'create' => ['GET'],
                    'update' => ['GET'],
                    'save' => ['POST'],
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionList()
    {
        return $this->render('list', [
            'dataProvider' => new ActiveDataProvider([
                'query' => VesselType::find(),
                'pagination' => ['pageSize' => 20],
                'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
            ]),
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionCreate()
    {
        $model = new VesselType();
        $model->loadDefaultValues();
        $model->engine_count = 0;
        $model->generator_count = 0;

        return $this->renderAjax('_form', $model->getFormData());
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        return $this->renderAjax('_form', $model->getFormData());
    }

    public function actionSave($id = null)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $model = $id === null ? new VesselType() : $this->findModel($id);
        $data = Yii::$app->request->post();

        if (!$model->load($data)) {
            return [
                'success' => false,
                'message' => 'داده‌های ارسالی معتبر نیستند.',
                'errors' => [],
            ];
        }

        $model->image_file = UploadedFile::getInstance($model, 'image_file');

        if (!$model->saveWithDetails($data)) {
            return [
                'success' => false,
                'message' => 'ذخیره انجام نشد؛ خطاهای فرم را بررسی کنید.',
                'errors' => $model->getErrors(),
            ];
        }

        return [
            'success' => true,
            'message' => 'شناور با موفقیت ذخیره شد.',
            'redirect' => Url::to(['list']),
        ];
    }

    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        if ($model->archive()) {
            Yii::$app->session->setFlash('success', 'شناور با موفقیت حذف شد.');
        } else {
            Yii::$app->session->setFlash('error', 'حذف شناور انجام نشد.');
        }

        return $this->redirect(['list']);
    }

    protected function findModel($id)
    {
        if (!is_scalar($id) || !preg_match('/^[1-9]\d*$/D', (string) $id)) {
            throw new NotFoundHttpException('شناور مورد نظر یافت نشد.');
        }

        $model = VesselType::find()
            ->andWhere([VesselType::tableName() . '.id' => $id])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('شناور مورد نظر یافت نشد.');
        }

        return $model;
    }
}