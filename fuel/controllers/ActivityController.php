<?php

namespace app\modules\fuel\controllers;

use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\modules\fuel\models\ActivityType;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\data\ActiveDataProvider;
use Yii;
use app\modules\fuel\models\ActivityTypeSearch;

/**
 * ActivityController implements the CRUD actions for ActivityType model.
 */
class ActivityController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors() {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['list', 'view', 'create', 'update', 'delete'],
                'rules' => [
//                        [
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
     * Lists all ActivityType models.
     * @return mixed
     */
public function actionList()
{
    $dataProvider = new ActiveDataProvider([
        'query' => ActivityType::find(),
        'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
    ]);

    return $this->render('list', [
        'dataProvider' => $dataProvider,
    ]);
}

    /**
     * Displays a single ActivityType model.
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
     * Creates a new ActivityType model.
     * If creation is successful, the browser will be redirected to the 'view' page or return JSON for modal.
     * @return mixed
     */
 public function actionCreate()
{
    $model = new ActivityType();

    if ($model->load(\Yii::$app->request->post())) {
            $model->created_by = \Yii::$app->user->id;
        $model->created_at = date('Y-m-d H:i:s');  // ← زمان دستی
        $model->updated_at = date('Y-m-d H:i:s');  // ← زمان دستی
        if ($model->save()) {
            if (\Yii::$app->request->isAjax) {
                \Yii::$app->response->format = Response::FORMAT_JSON;
                return ['success' => true, 'id' => $model->id, 'name' => $model->name];
            }
            return $this->redirect(['view', 'id' => $model->id]);
        }
    }

    if (\Yii::$app->request->isAjax) {
        return $this->renderAjax('create', [
            'model' => $model,
        ]);
    }

    return $this->render('create', [
        'model' => $model,
    ]);
}

    /**
     * Updates an existing ActivityType model.
     * If update is successful, the browser will be redirected to the 'view' page or return JSON for modal.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
public function actionUpdate($id)
{
    $model = $this->findModel($id);

    if ($model->load(\Yii::$app->request->post())) {
        $model->updated_by = \Yii::$app->user->id;  // ← با \ قبل از Yii
        $model->updated_at = date('Y-m-d H:i:s');
        if ($model->save()) {
            if (\Yii::$app->request->isAjax) {
                \Yii::$app->response->format = Response::FORMAT_JSON;
                return ['success' => true, 'id' => $model->id, 'name' => $model->name];
            }
            return $this->redirect(['view', 'id' => $model->id]);
        }
    }

    if (\Yii::$app->request->isAjax) {
        return $this->renderAjax('update', [
            'model' => $model,
        ]);
    }

    return $this->render('update', [
        'model' => $model,
    ]);
}

    /**
     * Deletes an existing ActivityType model.
     * If deletion is successful, the browser will be redirected to the 'list' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
public function actionDelete($id)
{
    $model = $this->findModel($id);
    
    $model->is_deleted = 1;
    $model->save(false);  // بدون اعتبارسنجی ذخیره کن
    
    \Yii::$app->session->setFlash('success', 'فعالیت با موفقیت حذف شد.');
    
    return $this->redirect(['list']);
}

    /**
     * Finds the ActivityType model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ActivityType the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ActivityType::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('فعالیت مورد نظر یافت نشد.');
    }
}