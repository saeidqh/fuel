<?php

namespace app\modules\fuel\controllers;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\FileHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;
use app\modules\fuel\models\RigType;
use app\modules\fuel\models\RigTypeSearch;
use app\modules\fuel\models\RigEngine;
use app\modules\fuel\models\RigGenerator;

class RigController extends Controller
{
    public function behaviors()
    {
                return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['list','view','create','update','delete'],
                'rules' => [
                        [
                        'actions' => ['list','view','create','update','delete'],
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
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionList()
    {
        $searchModel = new RigTypeSearch();

        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams
        );

        return $this->render('list', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
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
        $model = new RigType();
        $model->engine_count = 1;
        $model->generator_count = 1;
        $model->initial_fuel_balance = 0;
        $model->initial_water_balance = 0;

        return $this->processForm($model);
    }

    public function actionUpdate($id)
    {
        return $this->processForm($this->findModel($id));
    }

    private function processForm(RigType $model)
    {
        $isNew = $model->isNewRecord;

        if ($model->load(Yii::$app->request->post())) {
            $model->documentFile = UploadedFile::getInstance(
                $model,
                'documentFile'
            );

            if ($this->saveRig($model)) {
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;

                    return [
                        'success' => true,
                        'id' => $model->id,
                        'name' => $model->name,
                    ];
                }

                Yii::$app->session->setFlash(
                    'success',
                    $isNew
                        ? 'دکل با موفقیت ایجاد شد.'
                        : 'دکل با موفقیت به‌روزرسانی شد.'
                );

                return $this->redirect(['list']);
            }
        }

        return $this->renderRigForm($model);
    }

    /**
     * ثبت دکل و تجهیزات در یک تراکنش.
     */
    private function saveRig(RigType $model)
    {
        $db = RigType::getDb();
        $transaction = $db->beginTransaction();

        $isNew = $model->isNewRecord;
        $originalAttributes = $model->getOldAttributes();
        $originalDocument = $model->rig_document;
        $uploadedPath = null;

        try {
            if (!$isNew) {
                // سریالی‌کردن ویرایش‌های هم‌زمان همین دکل.
                $table = $db->quoteTableName(RigType::tableName());

                $lockedRow = $db->createCommand(
                    "SELECT * FROM {$table}
                     WHERE [[id]] = :id AND [[is_deleted]] = 0
                     FOR UPDATE",
                    [':id' => $model->id]
                )->queryOne();

                if ($lockedRow === false) {
                    throw new \RuntimeException(
                        'دکل موجود نیست یا حذف شده است.'
                    );
                }

                // مقایسه موجودی پایه با آخرین مقدار دیتابیس.
                $originalAttributes = $lockedRow;
                $originalDocument = $lockedRow['rig_document'];
                $model->setOldAttributes($lockedRow);

                // فیلدهای مدیریتی از دیتابیس نگه داشته می‌شوند.
                foreach (
                    ['created_by', 'created_at', 'is_deleted', 'rig_document']
                    as $attribute
                ) {
                    $model->$attribute = $lockedRow[$attribute];
                }
            }

            if (!$model->validate()) {
                $transaction->rollBack();
                return false;
            }

            $now = date('Y-m-d H:i:s');

            if ($isNew) {
                $model->created_by = Yii::$app->user->id;
                $model->created_at = $now;
                $model->is_deleted = 0;
            }

            $model->updated_by = Yii::$app->user->id;
            $model->updated_at = $now;

            if ($model->documentFile !== null) {
                $directory = Yii::getAlias('@webroot/uploads/rigs');

                if (!FileHelper::createDirectory($directory, 0755, true)) {
                    throw new \RuntimeException(
                        'ساخت پوشه فایل‌های دکل ناموفق بود.'
                    );
                }

                $fileName = Yii::$app->security->generateRandomString(32)
                    . '.'
                    . strtolower($model->documentFile->extension);

                $uploadedPath = $directory . DIRECTORY_SEPARATOR . $fileName;

                if (!$model->documentFile->saveAs($uploadedPath)) {
                    $model->addError(
                        'documentFile',
                        'ذخیره فایل بارگذاری‌شده ناموفق بود.'
                    );

                    throw new \RuntimeException('ذخیره فایل ناموفق بود.');
                }

                $model->rig_document = '/uploads/rigs/' . $fileName;
            }

            if (!$model->save(false)) {
                throw new \RuntimeException('ذخیره دکل ناموفق بود.');
            }

            $this->syncEquipment($model, 'engine');
            $this->syncEquipment($model, 'generator');

            $transaction->commit();
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            if ($uploadedPath !== null && is_file($uploadedPath)) {
                if (!@unlink($uploadedPath)) {
                    Yii::warning(
                        'پاک‌سازی فایل ناموفق: ' . $uploadedPath,
                        __METHOD__
                    );
                }
            }

            $model->rig_document = $originalDocument;

            if ($isNew) {
                $model->id = null;
                $model->setOldAttributes(null);
            } else {
                $model->setOldAttributes($originalAttributes);
            }

            Yii::error($exception, __METHOD__);

            if (!$model->hasErrors()) {
                $model->addError(
                    'name',
                    'ذخیره اطلاعات ناموفق بود. لطفاً اطلاعات را بررسی کنید.'
                );
            }

            return false;
        }

        return true;
    }

    /**
     * نگاشت ورودی‌های فرم به مدل تجهیزات.
     * نرخ‌ها فقط از فیلدهای مشخص‌شده خوانده می‌شوند.
     */
    private function syncEquipment(RigType $rig, $type)
    {
        $isEngine = $type === 'engine';

        $class = $isEngine ? RigEngine::class : RigGenerator::class;
        $label = $isEngine ? 'موتور' : 'ژنراتور';

        $countAttribute = $type . '_count';
        $numberAttribute = $type . '_number';
        $count = (int) $rig->$countAttribute;

        $posted = Yii::$app->request->post($type, []);

        if (!is_array($posted)) {
            $rig->addError(
                $countAttribute,
                'ساختار اطلاعات ' . $label . ' معتبر نیست.'
            );

            throw new \RuntimeException('ورودی نامعتبر تجهیزات.');
        }

        $existing = [];

        foreach ($class::find()->where(['rig_id' => $rig->id])->all() as $item) {
            $number = (int) $item->$numberAttribute;

            if (isset($existing[$number])) {
                $rig->addError(
                    $countAttribute,
                    'شماره تکراری برای ' . $label
                    . ' در دیتابیس وجود دارد؛ ابتدا داده تکراری اصلاح شود.'
                );

                throw new \RuntimeException('شماره تکراری تجهیزات.');
            }

            $existing[$number] = $item;
        }

        $fieldMap = [
            'type' => $type . '_type',
            'power' => $type . '_power',
            'fuel_consumption' => $type . '_fuel_consumption',
            'tested_fuel_consumption' => $type . '_tested_fuel_consumption',
        ];

        for ($number = 1; $number <= $count; $number++) {
            if (
                !array_key_exists($number, $posted)
                || !is_array($posted[$number])
            ) {
                $rig->addError(
                    $countAttribute,
                    'اطلاعات ' . $label . ' شماره ' . $number . ' ارسال نشده است.'
                );

                throw new \RuntimeException('اطلاعات تجهیزات ناقص است.');
            }

            $item = isset($existing[$number])
                ? $existing[$number]
                : new $class();

            $item->rig_id = $rig->id;
            $item->$numberAttribute = $number;

            foreach ($fieldMap as $input => $attribute) {
                $value = $posted[$number][$input] ?? null;

                if ($value !== null && !is_scalar($value)) {
                    $rig->addError(
                        $countAttribute,
                        'مقدار نامعتبر در ' . $label . ' شماره ' . $number . '.'
                    );

                    throw new \RuntimeException('نوع ورودی تجهیزات نامعتبر است.');
                }

                if (is_string($value)) {
                    $value = trim($value);
                }

                $item->$attribute = $value === '' ? null : $value;
            }

            if (!$item->save()) {
                $this->addEquipmentErrors(
                    $rig,
                    $item,
                    $countAttribute,
                    $label . ' شماره ' . $number
                );

                throw new \RuntimeException('ذخیره تجهیزات ناموفق بود.');
            }

            unset($existing[$number]);
        }

        // فقط تجهیزاتی حذف می‌شوند که دیگر در تعداد انتخاب‌شده نیستند.
        foreach ($existing as $item) {
            if ($item->delete() === false) {
                throw new \RuntimeException('حذف تجهیز اضافی ناموفق بود.');
            }
        }
    }

    private function addEquipmentErrors(
        RigType $model,
        Model $equipment,
        $attribute,
        $label
    ) {
        $errors = $equipment->getErrors();

        if (!$errors) {
            $model->addError(
                $attribute,
                'ذخیره ' . $label . ' ناموفق بود.'
            );

            return;
        }

        foreach ($errors as $messages) {
            foreach ($messages as $message) {
                $model->addError(
                    $attribute,
                    $label . ': ' . $message
                );
            }
        }
    }

    private function renderRigForm(RigType $model)
    {
        $params = [
            'model' => $model,
            'engines' => $model->isNewRecord ? [] : $model->enginesArray,
            'generators' => $model->isNewRecord ? [] : $model->generatorsArray,
            'balancesLocked' => $model->hasConsumptionHistory(),
        ];

        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('_form', $params);
        }

        return $this->render('_form', $params);
    }

    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        $model->is_deleted = 1;
        $model->updated_by = Yii::$app->user->id;
        $model->updated_at = date('Y-m-d H:i:s');

        try {
            $saved = $model->save(false, [
                'is_deleted',
                'updated_by',
                'updated_at',
            ]);
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__);
            $saved = false;
        }

        Yii::$app->session->setFlash(
            $saved ? 'success' : 'error',
            $saved
                ? 'دکل با موفقیت حذف شد.'
                : 'حذف دکل ناموفق بود.'
        );

        return $this->redirect(['list']);
    }

    protected function findModel($id)
    {
        // شرط صریح برای حذف منطقی؛ مستقل از رفتار findOne.
        $model = RigType::find()
            ->andWhere(['id' => $id])
            ->one();

        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException('دکل موردنظر یافت نشد.');
    }
}