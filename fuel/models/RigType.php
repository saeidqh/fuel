<?php

namespace app\modules\fuel\models;

use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class RigType extends ActiveRecord
{
    const MAX_EQUIPMENT_COUNT = 5;

    /**
     * فایل ورودی فرم؛ در دیتابیس ذخیره نمی‌شود.
     *
     * @var \yii\web\UploadedFile|null
     */
    public $documentFile;

    public static function tableName()
    {
        return 'rig_table_rig';
    }

    public function rules()
    {
        return [
            [['name', 'code'], 'trim'],

            [['name'], 'required', 'message' => 'نام دکل اجباری است.'],
            [['name'], 'string', 'max' => 100],
            [['code'], 'string', 'max' => 50],
            [['description'], 'string'],

            [['build_year', 'location_id'], 'default', 'value' => null],
            [['build_year'], 'integer', 'min' => 1300, 'max' => 1430],

            [['engine_count', 'generator_count'], 'default', 'value' => 1],
            [
                ['engine_count', 'generator_count'],
                'integer',
                'min' => 0,
                'max' => self::MAX_EQUIPMENT_COUNT,
            ],

            [['location_id'], 'integer', 'min' => 1],
            [
                ['location_id'],
                'exist',
                'targetClass' => LocationType::class,
                'targetAttribute' => 'id',
                'message' => 'لوکیشن انتخاب‌شده معتبر نیست.',
            ],

            [
                ['initial_fuel_balance', 'initial_water_balance'],
                'default',
                'value' => 0,
            ],
            [
                ['initial_fuel_balance', 'initial_water_balance'],
                'number',
                'min' => 0,
                'max' => 99999999.99,
            ],
            [
                ['initial_fuel_balance', 'initial_water_balance'],
                'validateInitialBalance',
                'skipOnError' => true,
            ],

            [
                ['documentFile'],
                'file',
                'skipOnEmpty' => true,
                'extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                'checkExtensionByMimeType' => true,
                'maxSize' => 10 * 1024 * 1024,
                'tooBig' => 'حجم فایل نباید بیشتر از ۱۰ مگابایت باشد.',
                'wrongExtension' => 'فرمت فایل مجاز نیست.',
            ],

        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه',
            'name' => 'نام دکل',
            'code' => 'کد دکل',
            'build_year' => 'سال ساخت دکل',
            'engine_count' => 'تعداد موتورها',
            'generator_count' => 'تعداد موتور اضطراری',
            'rig_document' => 'فایل مشخصات دکل',
            'documentFile' => 'فایل مشخصات دکل',
            'location_id' => 'لوکیشن',
            'description' => 'توضیحات',
            'initial_fuel_balance' => 'موجودی اولیه سوخت (لیتر)',
            'initial_water_balance' => 'موجودی اولیه آب (لیتر)',
            'created_by' => 'ایجادشده توسط',
            'created_at' => 'زمان ایجاد',
            'updated_at' => 'زمان به‌روزرسانی',
            'updated_by' => 'آخرین ویرایش توسط',
            'is_deleted' => 'حذف‌شده',
        ];
    }

    /**
     * حتی گزارش ابطال‌شده نیز سابقه محسوب می‌شود.
     * این بررسی فعلاً فقط مربوط به جدول گزارش مصرف است.
     * پس از اضافه شدن جابه‌جایی مستقل، سابقه آن نیز بررسی خواهد شد.
     */
    public function hasConsumptionHistory()
    {
        if ($this->isNewRecord) {
            return false;
        }

        return (new \yii\db\Query())
            ->from(RigFuelConsumption::tableName())
            ->where(['rigIdRef' => $this->id])
            ->exists(static::getDb());
    }

    public function validateInitialBalance($attribute, $params)
    {
        if ($this->isNewRecord) {
            return;
        }

        $oldValue = (float) $this->getOldAttribute($attribute);
        $newValue = (float) $this->$attribute;

        // مقایسه بر مبنای دقت ستون دیتابیس.
        if (round($oldValue, 2) === round($newValue, 2)) {
            return;
        }

        if ($this->hasConsumptionHistory()) {
            $this->addError(
                $attribute,
                'پس از ثبت گزارش مصرف، تغییر مستقیم موجودی اولیه مجاز نیست.'
            );
        }
    }

    public function getConsumptions()
    {
        return $this->hasMany(
            RigFuelConsumption::class,
            ['rigIdRef' => 'id']
        );
    }

    /**
     * آخرین گزارش معتبر بر اساس زمان عملیات، نه زمان ثبت در سیستم.
     */
    public function getLatestConsumption()
    {
        return $this->hasOne(
            RigFuelConsumption::class,
            ['rigIdRef' => 'id']
        )
            ->andWhere(['voided_at' => null])
            ->orderBy([
                'date' => SORT_DESC,
                'end_time' => SORT_DESC,
                'start_time' => SORT_DESC,
                'id' => SORT_DESC,
            ]);
    }

    /**
     * فعلاً موجودی بر اساس گزارش‌های مصرف فعلی است.
     * منطق انتقال مستقل در مرحله موجودی جایگزین خواهد شد.
     */
public function getCurrentFuelBalance()
{
    $latest = $this->latestConsumption;
    $baseBalance = (float) ($latest !== null ? $latest->fuel_closing_balance : $this->initial_fuel_balance);

    // اگر گزارشی وجود دارد، تراکنش‌های بعد از تاریخ آخرین گزارش را لحاظ کن
    $lastDate = $latest !== null ? $latest->date : '1970-01-01';
    $lastId = $latest !== null ? $latest->id : 0;

    // مجموع دریافت‌های متناظر مستقل که بعد از آخرین گزارش انجام شده‌اند
    $pendingReceives = (float) (new \yii\db\Query())
        ->from('fuel_transaction_table_fuel_transaction')
        ->where([
            'equipment_type' => 'rig',
            'equipment_id' => $this->id,
            'resource_type' => 'fuel',
            'transaction_type' => 'receive',
            'voided_at' => null,
        ])
        ->andWhere(['>', 'transaction_date', $lastDate])
        ->orWhere([
            'and',
            ['equipment_type' => 'rig', 'equipment_id' => $this->id, 'resource_type' => 'fuel', 'transaction_type' => 'receive', 'voided_at' => null],
            ['transaction_date' => $lastDate],
            ['>', 'id', $lastId]
        ])
        ->sum('amount') ?: 0;

    return $baseBalance + $pendingReceives;
}

    public function getCurrentWaterBalance()
    {
        $latest = $this->latestConsumption;

        return (float) (
            $latest !== null
                ? $latest->water_closing_balance
                : $this->initial_water_balance
        );
    }

    public function getCreatedByUser()
    {
        return $this->hasOne(
            \app\modules\account\models\User::class,
            ['UserID' => 'created_by']
        );
    }

    public function getUpdatedByUser()
    {
        return $this->hasOne(
            \app\modules\account\models\User::class,
            ['UserID' => 'updated_by']
        );
    }

    public function getCreatedAtJalali()
    {
        return $this->formatJalaliDateTime($this->created_at);
    }

    public function getUpdatedAtJalali()
    {
        return $this->formatJalaliDateTime($this->updated_at);
    }

    private function formatJalaliDateTime($value)
    {
        if (!$value || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        try {
            $date = new \DateTimeImmutable(
                $value,
                new \DateTimeZone(\Yii::$app->timeZone)
            );

            $date = $date->setTimezone(
                new \DateTimeZone('Asia/Tehran')
            );

            $converter = new \app\components\jDateTime(
                true,
                true,
                'Asia/Tehran'
            );

            $jalali = $converter->toJalali(
                (int) $date->format('Y'),
                (int) $date->format('m'),
                (int) $date->format('d')
            );

            return sprintf(
                '%04d/%02d/%02d %s',
                $jalali[0],
                $jalali[1],
                $jalali[2],
                $date->format('H:i')
            );
        } catch (\Exception $exception) {
            return '-';
        }
    }

    public function getEngines()
    {
        return $this->hasMany(RigEngine::class, ['rig_id' => 'id'])
            ->orderBy(['engine_number' => SORT_ASC]);
    }

    public function getGenerators()
    {
        return $this->hasMany(RigGenerator::class, ['rig_id' => 'id'])
            ->orderBy(['generator_number' => SORT_ASC]);
    }

    public function getEnginesArray()
    {
        return ArrayHelper::index($this->engines, 'engine_number');
    }

    public function getGeneratorsArray()
    {
        return ArrayHelper::index($this->generators, 'generator_number');
    }

    public function getLocation()
    {
        return $this->hasOne(LocationType::class, ['id' => 'location_id']);
    }

    public static function getDropdownList()
    {
        $rows = static::find()
            ->select(['id', 'name', 'code'])
            ->orderBy(['name' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        return ArrayHelper::map($rows, 'id', static function ($row) {
            return $row['code'] !== null && $row['code'] !== ''
                ? $row['name'] . ' (' . $row['code'] . ')'
                : $row['name'];
        });
    }

    public static function find()
    {
        return parent::find()->andWhere(['is_deleted' => 0]);
    }
}