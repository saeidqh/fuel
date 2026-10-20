<?php

namespace app\modules\fuel\controllers;

use Yii;
use app\modules\fuel\models\RigFuelConsumption;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class RigConsumptionController extends Controller
{
    /**
     * مجوز ابطال گزارش مصرف دکل.
     *
     * این مجوز باید در سیستم دسترسی برنامه تعریف و
     * به کاربران مجاز اختصاص داده شود.
     */
    const PERMISSION_VOID = 'fuel.rigConsumption.void';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => [
                            'create',
                            'equipment',
                            'calculate',
                            'view',
                        ],
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['void'],
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return Yii::$app->user->can(
                                self::PERMISSION_VOID
                            );
                        },
                    ],
                ],
            ],

            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['GET', 'POST'],
                    'equipment' => ['GET'],
                    'calculate' => ['POST'],
                    'view' => ['GET'],
                    'void' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * بارگذاری فیلدهای اصلی گزارش و ورودی‌های مستقل فرم.
     *
     * فیلدهای محاسباتی از درخواست کاربر بارگذاری نمی‌شوند؛
     * سناریوی مدل فقط فیلدهای مجاز را می‌پذیرد.
     */
    private function loadReport(RigFuelConsumption $model)
    {
        $post = Yii::$app->request->post();
        $formName = $model->formName();

        if (!isset($post[$formName])
            || !is_array($post[$formName])) {
            return false;
        }

        if (!$model->load($post)) {
            return false;
        }

        $model->loadFormInput($post);

        return true;
    }

    /**
     * ثبت گزارش جدید مصرف دکل.
     *
     * مدیریت تراکنش دیتابیس در مدل انجام می‌شود؛
     * در کنترلر تراکنش دیگری باز نمی‌کنیم.
     */
    public function actionCreate()
    {
        $model = new RigFuelConsumption();

        if (Yii::$app->request->isPost) {
            if (!$this->loadReport($model)) {
                throw new BadRequestHttpException(
                    'ساختار اطلاعات فرم معتبر نیست.'
                );
            }

            if ($model->saveWithFuelTransactions()) {
                Yii::$app->session->setFlash(
                    'success',
                    'گزارش مصرف دکل و تراکنش‌های سوخت و آب با موفقیت ثبت شد.'
                );

                return $this->redirectToList();
            }

            /*
             * در صورت خطا، همان مدل دوباره نمایش داده می‌شود
             * تا ورودی‌های کاربر و پیام‌های اعتبارسنجی حفظ شوند.
             */
        }

        return $this->render('create', [
            'model' => $model,
            'formData' => $model->getFormData(),
            'equipmentRows' => $model->getEquipmentRows(),
            'summary' => $model->getConsumptionSummary(),
            'fuelSummary' => $model->getFuelBalanceSummary(),
            'waterSummary' => $model->getWaterBalanceSummary(),
        ]);
    }

    /**
     * دریافت بخش تجهیزات دکل انتخاب‌شده.
     *
     * خروجی HTML برای جایگزینی بخش تجهیزات در فرم است.
     */
    public function actionEquipment($rigId = null)
    {
        $rigId = $this->normalizeId($rigId, 'شناسه دکل');

        if (!RigFuelConsumption::isValidRigId($rigId)) {
            throw new BadRequestHttpException(
                'دکل انتخاب‌شده معتبر یا فعال نیست.'
            );
        }

        $model = new RigFuelConsumption();
        $model->rigIdRef = $rigId;

        return $this->renderAjax('_equipment', [
            'model' => $model,
            'equipmentRows' => $model->getEquipmentRows(),
        ]);
    }

    /**
     * پیش‌نمایش محاسبات بدون ذخیره اطلاعات.
     *
     * هنگام ثبت نهایی، مدل محاسبات و اعتبارسنجی را
     * پس از گرفتن قفل دکل دوباره انجام می‌دهد.
     */
    public function actionCalculate()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $model = new RigFuelConsumption();

        if (!$this->loadReport($model)) {
            throw new BadRequestHttpException(
                'ساختار اطلاعات فرم معتبر نیست.'
            );
        }

        $valid = $model->validate();

        return [
            'valid' => $valid,
            'errors' => $model->getErrors(),
            'summary' => $valid
                ? $model->getConsumptionSummary()
                : null,
            'fuel' => $valid
                ? $model->getFuelBalanceSummary()
                : null,
            'water' => $valid
                ? $model->getWaterBalanceSummary()
                : null,
        ];
    }

    /**
     * نمایش گزارش ثبت‌شده، شامل گزارش‌های باطل‌شده.
     *
     * مقادیر مصرف از خود گزارش خوانده می‌شوند؛
     * محاسبات تاریخی با نرخ فعلی تجهیزات بازسازی نمی‌شوند.
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        return $this->render('view', [
            'model' => $model,
            'summary' => $model->getConsumptionSummary(),
            'fuelSummary' => $model->getFuelBalanceSummary(),
            'waterSummary' => $model->getWaterBalanceSummary(),
            'canVoid' => $model->voided_at === null
                && Yii::$app->user->can(self::PERMISSION_VOID),
        ]);
    }

    /**
     * ابطال گزارش با حفظ سابقه.
     *
     * ورودی POST:
     * reason = علت ابطال
     *
     * اعتبارسنجی علت، بررسی وابستگی تراکنش‌ها،
     * ابطال و بازمحاسبه موجودی در مدل انجام می‌شود.
     */
    public function actionVoid($id)
    {
        $model = $this->findModel($id);

        $reason = Yii::$app->request->post('reason');

        if ($model->voidWithTransactions($reason)) {
            Yii::$app->session->setFlash(
                'success',
                'گزارش با حفظ سابقه باطل شد و موجودی گزارش‌های فعال بازمحاسبه شد.'
            );

            return $this->redirectToList();
        }

        $messages = array_values($model->getFirstErrors());
        $messages = array_unique($messages);

        Yii::$app->session->setFlash(
            'error',
            $messages
                ? implode(' — ', $messages)
                : 'ابطال گزارش انجام نشد.'
        );

        return $this->redirectToList();
    }

    /**
     * دریافت گزارش بدون حذف گزارش‌های باطل‌شده از نتیجه.
     */
    protected function findModel($id)
    {
        $id = $this->normalizeId($id, 'شناسه گزارش');

        $model = RigFuelConsumption::find()
            ->where(['id' => $id])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException(
                'گزارش مصرف دکل پیدا نشد.'
            );
        }

        return $model;
    }

    /**
     * شناسه باید عدد صحیح مثبت باشد.
     *
     * تبدیل به int انجام نمی‌شود تا شناسه بزرگ
     * پیش از ارسال به دیتابیس دچار سرریز نشود.
     */
    private function normalizeId($value, $label)
    {
        if (!is_string($value) && !is_int($value)) {
            throw new BadRequestHttpException(
                $label . ' معتبر نیست.'
            );
        }

        $value = (string) RigFuelConsumption::normalizeInput($value);

        if (!preg_match('/\A[1-9]\d*\z/', $value)) {
            throw new BadRequestHttpException(
                $label . ' معتبر نیست.'
            );
        }

        return $value;
    }

    private function redirectToList()
    {
        return $this->redirect(['/fuel/fuel/list']);
    }
}