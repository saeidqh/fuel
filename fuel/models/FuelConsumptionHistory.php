<?php

namespace app\modules\fuel\models;

use Yii;
use yii\db\Connection;
use yii\db\Query;
use yii\helpers\Json;

final class FuelConsumptionHistory
{
    const TYPE_RIG = 'rig';
    const TYPE_VESSEL = 'vessel';

    const ACTION_UPDATE = 'update';
    const ACTION_VOID = 'void';
    const ACTION_BALANCE_RECALCULATE = 'balance_recalculate';

    const TIME_ZONE = 'Asia/Tehran';

     const HISTORY_TABLE = 'fuel_consumption_history';

    const TRANSACTION_TABLE =
        'fuel_transaction_table_fuel_transaction';

     const ITEM_TABLE = 'vessel_consumption_item';

    const REPORTS = [
        self::TYPE_RIG => [
            'table' => 'rig_fuel_consumption_table_rig_fuel_consumption',
            'equipmentField' => 'rigIdRef',
        ],
        self::TYPE_VESSEL => [
            'table' => 'vessel_fuel_consumption_table_vessel_fuel_consumption',
            'equipmentField' => 'vesselIdRef',
        ],
    ];

    private function __construct()
    {
    }
    public static function snapshot($equipmentType, $reportId)
    {
        $definition = self::reportDefinition($equipmentType);
        $reportId = self::validateId($reportId, 'شناسه گزارش');
        $db = self::db();

        $report = (new Query())
            ->from($definition['table'])
            ->where(['id' => $reportId])
            ->one($db);

        if ($report === false) {
            throw new \RuntimeException(
                'گزارش موردنظر برای ثبت تاریخچه پیدا نشد.'
            );
        }

        $equipmentId = self::validateId(
            $report[$definition['equipmentField']],
            'شناسه دکل یا شناور'
        );

        $items = [];

        if ($equipmentType === self::TYPE_VESSEL) {
            $items = (new Query())
                ->from(self::ITEM_TABLE)
                ->where(['consumption_id' => $reportId])
                ->orderBy(['id' => SORT_ASC])
                ->all($db);
        }

        /*
         * consumption_id بین دکل و شناور مشترک است.
         * هر سه شرط برای جلوگیری از اختلاط گزارش‌ها ضروری‌اند.
         */
        $transactions = (new Query())
            ->from(self::TRANSACTION_TABLE)
            ->where([
                'equipment_type' => $equipmentType,
                'equipment_id' => $equipmentId,
                'consumption_id' => $reportId,
            ])
            ->orderBy(['id' => SORT_ASC])
            ->all($db);

        return [
            'schema_version' => 1,
            'equipment_type' => $equipmentType,
            'equipment_id' => $equipmentId,
            'consumption_id' => $reportId,
            'report' => $report,
            'items' => $items,
            'transactions' => $transactions,
        ];
    }

    /**
     * ثبت نسخه قبل و بعد از تغییر.
     *
     * نسخه بعد مستقیماً از دیتابیس خوانده می‌شود، نه فرم کاربر.
     *
     * این متد باید بعد از ذخیره تغییرات و قبل از commit فراخوانی شود.
     * در صورت شکست، exception باید به لایه مدیریت تراکنش برسد
     * تا کل عملیات rollback شود.
     *
     * @param string $equipmentType
     * @param int|string $reportId
     * @param string $action
     * @param array $before خروجی snapshot قبل از تغییر
     * @param string|null $reason
     * @return bool آیا سابقه جدید ثبت شد؟
     */
    public static function record(
        $equipmentType,
        $reportId,
        $action,
        array $before,
        $reason = null
    ) {
        self::reportDefinition($equipmentType);
        $reportId = self::validateId($reportId, 'شناسه گزارش');

        if (!is_string($action)
            || !array_key_exists($action, self::getActionOptions())) {
            throw new \InvalidArgumentException(
                'نوع عملیات تاریخچه معتبر نیست.'
            );
        }

        $reason = self::normalizeReason($reason);

        if ($action === self::ACTION_VOID && $reason === null) {
            throw new \InvalidArgumentException(
                'برای ابطال گزارش، ثبت علت الزامی است.'
            );
        }

        $db = self::db();
        $transaction = $db->getTransaction();

        if ($transaction === null || !$transaction->isActive) {
            throw new \LogicException(
                'ثبت تاریخچه باید داخل تراکنش فعال دیتابیس انجام شود.'
            );
        }

        $actorId = self::currentUserId();

        self::validateSnapshot(
            $before,
            $equipmentType,
            $reportId
        );

        $after = self::snapshot($equipmentType, $reportId);

        /*
         * تغییر دکل یا شناور گزارش در مسیر ویرایش مجاز نیست.
         * این کنترل جایگزین اعتبارسنجی مدل مصرف نیست.
         */
        if ((string) $before['equipment_id']
            !== (string) $after['equipment_id']) {
            throw new \LogicException(
                'دکل یا شناور گزارش در حین عملیات تغییر کرده است.'
            );
        }

        /*
         * اگر هیچ داده‌ای تغییر نکرده، سابقه تکراری ثبت نکن.
         * مقایسه شامل گزارش، آیتم‌ها و تراکنش‌ها است.
         */
        if ($before === $after) {
            return false;
        }

        $createdAt = new \DateTimeImmutable(
            'now',
            new \DateTimeZone(self::TIME_ZONE)
        );

        $affectedRows = $db->createCommand()
            ->insert(self::HISTORY_TABLE, [
                'equipment_type' => $equipmentType,
                'equipment_id' => $after['equipment_id'],
                'consumption_id' => $reportId,
                'action' => $action,
                'reason' => $reason,
                'before_data' => Json::encode(
                    $before,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                'after_data' => Json::encode(
                    $after,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                'created_by' => $actorId,
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
            ])
            ->execute();

        if ($affectedRows !== 1) {
            throw new \RuntimeException(
                'ثبت تاریخچه انجام نشد؛ عملیات باید برگشت داده شود.'
            );
        }

        return true;
    }

    /**
     * کوئری تاریخچه یک گزارش برای استفاده در صفحه نمایش.
     *
     * بررسی مجوز مشاهده گزارش، وظیفه کنترلر است.
     * JSONها در این مرحله decode نمی‌شوند.
     *
     * @return Query
     */
    public static function queryForReport($equipmentType, $reportId)
    {
        self::reportDefinition($equipmentType);
        $reportId = self::validateId($reportId, 'شناسه گزارش');

        return (new Query())
            ->from(self::HISTORY_TABLE)
            ->where([
                'equipment_type' => $equipmentType,
                'consumption_id' => $reportId,
            ])
            ->orderBy(['id' => SORT_DESC]);
    }

    public static function getActionOptions()
    {
        return [
            self::ACTION_UPDATE => 'ویرایش گزارش',
            self::ACTION_VOID => 'ابطال گزارش',
            self::ACTION_BALANCE_RECALCULATE => 'بازمحاسبه موجودی',
        ];
    }

    private static function validateSnapshot(
        array $snapshot,
        $equipmentType,
        $reportId
    ) {
        foreach ([
            'schema_version',
            'equipment_type',
            'equipment_id',
            'consumption_id',
            'report',
            'items',
            'transactions',
        ] as $key) {
            if (!array_key_exists($key, $snapshot)) {
                throw new \InvalidArgumentException(
                    'ساختار نسخه قبلی گزارش کامل نیست.'
                );
            }
        }

        if ($snapshot['schema_version'] !== 1
            || $snapshot['equipment_type'] !== $equipmentType
            || (string) $snapshot['consumption_id'] !== (string) $reportId
            || !is_array($snapshot['report'])
            || !is_array($snapshot['items'])
            || !is_array($snapshot['transactions'])) {
            throw new \InvalidArgumentException(
                'نسخه قبلی متعلق به این گزارش نیست یا ساختار معتبری ندارد.'
            );
        }

        $definition = self::reportDefinition($equipmentType);
        $report = $snapshot['report'];
        $equipmentField = $definition['equipmentField'];

        if (!isset($report['id'], $report[$equipmentField])
            || (string) $report['id'] !== (string) $reportId
            || (string) $report[$equipmentField]
                !== (string) $snapshot['equipment_id']) {
            throw new \InvalidArgumentException(
                'هویت گزارش در نسخه قبلی معتبر نیست.'
            );
        }
    }

    private static function reportDefinition($equipmentType)
    {
        if (!is_string($equipmentType)
            || !isset(self::REPORTS[$equipmentType])) {
            throw new \InvalidArgumentException(
                'نوع گزارش باید دکل یا شناور باشد.'
            );
        }

        return self::REPORTS[$equipmentType];
    }

    /**
     * شناسه‌های جداول اصلی در ساختار فعلی INT signed هستند.
     * پیش از تبدیل به int، محدوده رشته بررسی می‌شود.
     */
    private static function validateId($value, $label)
    {
        if ((!is_int($value) && !is_string($value))
            || !preg_match('/\A[1-9]\d*\z/', (string) $value)) {
            throw new \InvalidArgumentException(
                $label . ' معتبر نیست.'
            );
        }

        $value = (string) $value;

        if (strlen($value) > 10
            || (strlen($value) === 10
                && strcmp($value, '2147483647') > 0)) {
            throw new \InvalidArgumentException(
                $label . ' خارج از محدوده مجاز است.'
            );
        }

        return (int) $value;
    }

    private static function normalizeReason($reason)
    {
        if ($reason === null) {
            return null;
        }

        if (!is_string($reason)) {
            throw new \InvalidArgumentException(
                'علت عملیات باید متن باشد.'
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            return null;
        }

        if (mb_strlen($reason, 'UTF-8') > 500) {
            throw new \InvalidArgumentException(
                'علت عملیات نمی‌تواند بیشتر از ۵۰۰ نویسه باشد.'
            );
        }

        return $reason;
    }

    private static function currentUserId()
    {
        if (!Yii::$app->has('user') || Yii::$app->user->isGuest) {
            throw new \LogicException(
                'برای ثبت تاریخچه، کاربر باید وارد سامانه شده باشد.'
            );
        }

        return self::validateId(
            Yii::$app->user->id,
            'شناسه کاربر'
        );
    }

    private static function db(): Connection
    {
        return Yii::$app->db;
    }
}