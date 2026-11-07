<?php

namespace app\modules\fuel\controllers;

use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\modules\fuel\models\LocationType;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use app\modules\fuel\models\LocationTypeSearch;
use Yii;

/**
 * LocationController implements the CRUD actions for LocationType model.
 */
class LocationController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['list', 'view', 'create', 'update', 'delete'],
                'rules' => [
                    [
                        'actions' => ['list', 'view', 'create', 'update', 'delete'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Lists all LocationType models.
     * @return mixed
     */
    public function actionList()
    {
        $searchModel = new LocationTypeSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('list', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single LocationType model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Creates a new LocationType model.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new LocationType();

        if ($model->load(Yii::$app->request->post())) {
            $model->created_by = Yii::$app->user->id;
            $model->created_at = date('Y-m-d H:i:s');
            $model->updated_at = date('Y-m-d H:i:s');

            if ($model->save()) {
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    return ['success' => true, 'id' => $model->id, 'name' => $model->name];
                }
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('create', ['model' => $model]);
        }

        return $this->render('create', ['model' => $model]);
    }

    /**
     * Updates an existing LocationType model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            $model->updated_by = Yii::$app->user->id;
            $model->updated_at = date('Y-m-d H:i:s');

            if ($model->save()) {
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    return ['success' => true, 'id' => $model->id, 'name' => $model->name];
                }
                return $this->redirect(['list']);
            }
        }

        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('update', ['model' => $model]);
        }

        return $this->render('update', ['model' => $model]);
    }

    /**
     * Deletes an existing LocationType model.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        $model->is_deleted = 1;
        $model->save(false);

        Yii::$app->session->setFlash('success', 'لوکیشن با موفقیت حذف شد.');

        return $this->redirect(['list']);
    }

    /**
     * Finds the LocationType model based on its primary key value.
     * @param integer $id
     * @return LocationType
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        if (($model = LocationType::findOne(['id' => $id, 'is_deleted' => 0])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('لوکیشن مورد نظر یافت نشد.');
    }
}