<?php

namespace app\modules\fuel\models;

use Yii;
use app\components\jDateTime;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Transaction;

class RigFuelConsumption extends ActiveRecord
{
    const MAX_AMOUNT = 99999999.99;
    const MAX_CENTS = 9999999999;

    const MAX_EQUIPMENT_COUNT = 5;
    const MAX_DAILY_HOURS_CENTS = 2400;

    public $jalaliDate;
    public $endOfDay = '0';

    public $fuelBalanceInput = [
        'enabled' => '0',
        'receive_enabled' => '0',
        'transfer_enabled' => '0',
        'received' => 0,
        'received_from' => '',
        'received_from_desc' => '',
        'transferred' => 0,
        'transferred_to' => '',
        'transferred_to_desc' => '',
    ];

    public $waterBalanceInput = [
        'used' => 0,
        'enabled' => '0',
        'receive_enabled' => '0',
        'transfer_enabled' => '0',
        'received' => 0,
        'received_from' => '',
        'received_from_desc' => '',
        'transferred' => 0,
        'transferred_to' => '',
        'transferred_to_desc' => '',
    ];

    public $auxiliaryEquipment = [
        'cement_unit' => [
            'hours' => 0,
            'consumption_user' => 0,
        ],
        'crane' => [
            'hours' => 0,
            'consumption_user' => 0,
        ],
        'other' => [
            'hours' => 0,
            'consumption_user' => 0,
        ],
    ];

    private $equipmentInput = [
        'engine' => [],
        'generator' => [],
    ];

    private $equipmentRows;
    private $equipmentRigId;

    /**
     * خطاهای ساختار ورودی بر اساس بخش نگهداری می‌شوند.
     * بارگذاری مجدد یک بخش، خطاهای قبلی همان بخش را پاک می‌کند.
     */
    private $inputErrors = [];

    private $balancePlan = [];
    private $summaryReady = false;

    public static function tableName()
    {
        return 'rig_fuel_consumption_table_rig_fuel_consumption';
    }

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => [
                'rigIdRef',
                'locationIdRef',
                'activityIdRef',
                'jalaliDate',
                'start_time',
                'end_time',
                'endOfDay',
                'description',
            ],
        ];
    }

public function rules()
    {
        return [
            [['rigIdRef', 'locationIdRef', 'activityIdRef', 'jalaliDate', 'start_time', 'end_time', 'endOfDay'], 'filter', 'filter' => [self::class, 'normalizeInput']],
            [['locationIdRef', 'activityIdRef'], 'default', 'value' => null],
            [['rigIdRef', 'jalaliDate', 'start_time'], 'required', 'message' => '{attribute} الزامی است.'],
            [['rigIdRef', 'locationIdRef', 'activityIdRef'], 'integer', 'min' => 1],
            [['endOfDay'], 'in', 'range' => ['0', '1', 0, 1], 'strict' => true, 'skipOnEmpty' => false],
            [['locationIdRef'], 'exist', 'targetClass' => LocationType::class, 'targetAttribute' => 'id'],
            [['activityIdRef'], 'exist', 'targetClass' => ActivityType::class, 'targetAttribute' => 'id'],
            [['jalaliDate'], 'string', 'max' => 10],
            [['description'], 'string', 'max' => 10000],
            [['rigIdRef'], 'validateReport', 'skipOnEmpty' => false, 'skipOnError' => false],
        ];
    }

    public function attributeLabels()
    {
        return [
            'rigIdRef' => 'دکل',
            'locationIdRef' => 'لوکیشن',
            'activityIdRef' => 'فعالیت',
            'jalaliDate' => 'تاریخ گزارش',
            'start_time' => 'ساعت شروع',
            'end_time' => 'ساعت پایان',
            'endOfDay' => 'پایان روز ـ ۲۴:۰۰',
            'description' => 'توضیحات',
        ];
    }

    public function beforeValidate()
    {
        $this->invalidateCalculation();

        // نرخ و مشخصات پایه در هر اعتبارسنجی دوباره خوانده شوند.
        $this->equipmentRows = null;
        $this->equipmentRigId = null;

        return parent::beforeValidate();
    }

    private function invalidateCalculation()
    {
        $this->summaryReady = false;
        $this->balancePlan = [];
    }

    public static function normalizeInput($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        return trim(strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]));
    }

    public static function activeQuery()
    {
        return self::find()->andWhere(['voided_at' => null]);
    }

    public static function isValidRigId($rigId)
    {
        if (!is_string($rigId) && !is_int($rigId)) {
            return false;
        }

        $rigId = self::normalizeInput($rigId);

        if (!preg_match('/\A[1-9]\d*\z/', (string) $rigId)) {
            return false;
        }

        return RigType::find()
            ->andWhere([
                'id' => $rigId,
                'is_deleted' => 0,
            ])
            ->exists();
    }

    /**
     * ورودی‌های خارج از نام فرم ActiveRecord را بارگذاری می‌کند.
     * بارگذاری فیلدهای اصلی همچنان با load() در کنترلر انجام می‌شود.
     */
    public function loadFormInput(array $post)
    {
        $this->inputErrors = [];
        $this->invalidateCalculation();

        $this->loadEquipmentInput($post);
        $this->loadAuxiliaryEquipmentInput($post);
        $this->loadFuelBalanceInput($post);
        $this->loadWaterBalanceInput($post);
    }

    private function resetInputSection($section)
    {
        unset($this->inputErrors[$section]);
        $this->invalidateCalculation();
    }

    private function addInputError($section, $attribute, $message)
    {
        $this->inputErrors[$section][$attribute][] = $message;
    }

    private function applyInputErrors()
    {
        foreach ($this->inputErrors as $errors) {
            foreach ($errors as $attribute => $messages) {
                foreach ($messages as $message) {
                    $this->addError($attribute, $message);
                }
            }
        }
    }

    public function loadEquipmentInput(array $post)
    {
        $section = 'equipment';
        $this->resetInputSection($section);

        $this->equipmentInput = [
            'engine' => [],
            'generator' => [],
        ];

        $equipment = $post[$section] ?? [];

        if (!is_array($equipment)) {
            $this->addInputError(
                $section,
                'rigIdRef',
                'ساختار اطلاعات تجهیزات معتبر نیست.'
            );
            return;
        }

        foreach ($equipment as $type => $items) {
            if (!in_array($type, ['engine', 'generator'], true)
                || !is_array($items)) {
                $this->addInputError(
                    $section,
                    'rigIdRef',
                    'نوع یا ساختار تجهیزات معتبر نیست.'
                );
                continue;
            }

            foreach ($items as $number => $input) {
                if (!preg_match('/\A[1-5]\z/', (string) $number)
                    || !is_array($input)) {
                    $this->addInputError(
                        $section,
                        'rigIdRef',
                        'شماره یا ساختار اطلاعات تجهیز معتبر نیست.'
                    );
                    continue;
                }

                $this->equipmentInput[$type][(int) $number] = [
                    'hours' => $this->scalarOrNull(
                        $input['hours'] ?? null
                    ),
                    'consumption_user' => $this->scalarOrNull(
                        $input['consumption_user'] ?? null
                    ),
                ];
            }
        }
    }

    public function loadAuxiliaryEquipmentInput(array $post)
    {
        $section = 'auxiliary_equipment';
        $this->resetInputSection($section);

        $input = $post[$section] ?? null;

        if (!is_array($input)) {
            $this->addInputError(
                $section,
                'rigIdRef',
                'اطلاعات تجهیزات جانبی ارسال نشده یا معتبر نیست.'
            );
            $input = [];
        }

        $types = ['cement_unit', 'crane', 'other'];

        foreach ($input as $type => $item) {
            if (!in_array($type, $types, true)) {
                $this->addInputError(
                    $section,
                    'rigIdRef',
                    'نوع تجهیز جانبی معتبر نیست.'
                );
            }
        }

        $this->auxiliaryEquipment = [];

        foreach ($types as $type) {
            $item = $input[$type] ?? null;

            if (!is_array($item)) {
                $this->addInputError(
                    $section,
                    "auxiliary_equipment[{$type}][hours]",
                    'اطلاعات این تجهیز جانبی ارسال نشده یا معتبر نیست.'
                );
                $item = [];
            }

            $this->auxiliaryEquipment[$type] = [
                'hours' => $this->scalarOrNull(
                    $item['hours'] ?? null
                ),
                'consumption_user' => $this->scalarOrNull(
                    $item['consumption_user'] ?? null
                ),
            ];
        }
    }

    public function loadFuelBalanceInput(array $post)
    {
        $this->fuelBalanceInput = $this->readBalanceInput(
            $post,
            'fuel_balance',
            false
        );
    }

    public function loadWaterBalanceInput(array $post)
    {
        $this->waterBalanceInput = $this->readBalanceInput(
            $post,
            'water_balance',
            true
        );
    }

    private function readBalanceInput(array $post, $key, $hasUsed)
    {
        $this->resetInputSection($key);

        $input = $post[$key] ?? null;

        if (!is_array($input)) {
            $this->addInputError(
                $key,
                $key . '[enabled]',
                'اطلاعات تراز ارسال نشده یا معتبر نیست.'
            );
            $input = [];
        }

        $result = [
            'enabled' => $this->scalarOrNull(
                $input['enabled'] ?? null
            ),
            'receive_enabled' => $this->scalarOrNull(
                $input['receive_enabled'] ?? '0'
            ),
            'transfer_enabled' => $this->scalarOrNull(
                $input['transfer_enabled'] ?? '0'
            ),
            'received' => $this->scalarOrNull(
                $input['received'] ?? null
            ),
            'received_from' => $this->scalarOrNull(
                $input['received_from'] ?? ''
            ),
            'received_from_desc' => $this->scalarOrNull(
                $input['received_from_desc'] ?? ''
            ),
            'transferred' => $this->scalarOrNull(
                $input['transferred'] ?? null
            ),
            'transferred_to' => $this->scalarOrNull(
                $input['transferred_to'] ?? ''
            ),
            'transferred_to_desc' => $this->scalarOrNull(
                $input['transferred_to_desc'] ?? ''
            ),
        ];

        if ($hasUsed) {
            $result['used'] = $this->scalarOrNull(
                $input['used'] ?? null
            );
        }

        return $result;
    }

    private function scalarOrNull($value)
    {
        if (!is_string($value)
            && !is_int($value)
            && !is_float($value)) {
            return null;
        }

        return self::normalizeInput($value);
    }

    /**
     * عدد غیرمنفی با حداکثر دو رقم اعشار.
     * جداکننده هزارگان و نمایش علمی عمداً پذیرفته نمی‌شوند.
     */
    private function decimal($value)
    {
        if (!is_string($value)
            && !is_int($value)
            && !is_float($value)) {
            return null;
        }

        $value = (string) self::normalizeInput($value);

        if (!preg_match('/\A\d+(?:\.\d{1,2})?\z/', $value)) {
            return null;
        }

        $number = (float) $value;

        if (!is_finite($number) || $number > self::MAX_AMOUNT) {
            return null;
        }

        return round($number, 2);
    }

    private function validateAmount(
        $attribute,
        $value,
        $label,
        $positive = false
    ) {
        $amount = $this->decimal($value);

        if ($amount === null) {
            $this->addError(
                $attribute,
                $label
                . ' باید عدد غیرمنفی، حداکثر با دو رقم اعشار'
                . ' و در محدوده مجاز باشد.'
            );
            return null;
        }

        if ($positive && $amount <= 0) {
            $this->addError(
                $attribute,
                $label . ' باید بیشتر از صفر باشد.'
            );
            return null;
        }

        return $amount;
    }

    /**
     * برای جمع و تفریق، مقادیر دورقمی به واحد صدم تبدیل می‌شوند.
     * این محدوده به PHP شصت‌وچهاربیتی نیاز دارد.
     */
    private function cents($amount)
    {
        return (int) round((float) $amount * 100);
    }

    public function validateReport($attribute, $params)
    {
        $this->invalidateCalculation();
        $this->applyInputErrors();

//        if (PHP_INT_SIZE < 8) {
//            $this->addError(
//                'rigIdRef',
//                'برای محاسبات دقیق موجودی، اجرای برنامه با PHP شصت‌وچهاربیتی لازم است.'
//            );
//            return;
//        }

        if (!$this->isNewRecord) {
            $this->addError(
                'rigIdRef',
                'ویرایش مستقیم گزارش مجاز نیست؛ سابقه گزارش باید حفظ شود.'
            );
            return;
        }

        if ($this->hasErrors()) {
            return;
        }

        if (!self::isValidRigId($this->rigIdRef)) {
            $this->addError(
                'rigIdRef',
                'دکل انتخاب‌شده معتبر یا فعال نیست.'
            );
            return;
        }

        $this->validateDateAndTime();

        if ($this->hasErrors()) {
            return;
        }

        $this->validateOverlap();

        $this->equipmentRows = null;
        $this->equipmentRigId = null;

        $this->validateAndCalculateEquipment();
        $this->validateAndSyncBalance('fuel');
        $this->validateAndSyncBalance('water');

        if ($this->hasErrors()) {
            return;
        }

        $rig = RigType::find()
            ->andWhere([
                'id' => $this->rigIdRef,
                'is_deleted' => 0,
            ])
            ->one();

        if (!$rig) {
            $this->addError('rigIdRef', 'دکل معتبر نیست.');
            return;
        }

        try {
            $this->balancePlan = $this->buildBalancePlan(
                $rig->initial_fuel_balance,
                $rig->initial_water_balance,
                true
            );

            $this->summaryReady = true;
        } catch (\DomainException $exception) {
            $this->addError(
                'rigIdRef',
                $exception->getMessage()
            );
        }
    }

    private function validateDateAndTime()
    {
        $this->date = null;

        if (!is_string($this->jalaliDate)
            || !preg_match(
                '/\A(1\d{3})\/(\d{1,2})\/(\d{1,2})\z/',
                $this->jalaliDate,
                $matches
            )) {
            $this->addError(
                'jalaliDate',
                'تاریخ را مانند 1405/06/29 وارد کنید.'
            );
            return;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if ($month < 1 || $month > 12
            || $day < 1 || $day > 31) {
            $this->addError(
                'jalaliDate',
                'تاریخ شمسی معتبر نیست.'
            );
            return;
        }

        $calendar = new jDateTime(true, true, 'Asia/Tehran');
        $gregorian = $calendar->toGregorian($year, $month, $day);

        if (!checkdate(
            (int) $gregorian[1],
            (int) $gregorian[2],
            (int) $gregorian[0]
        )) {
            $this->addError('jalaliDate', 'تاریخ معتبر نیست.');
            return;
        }

        $back = $calendar->toJalali(
            $gregorian[0],
            $gregorian[1],
            $gregorian[2]
        );

        if ((int) $back[0] !== $year
            || (int) $back[1] !== $month
            || (int) $back[2] !== $day) {
            $this->addError(
                'jalaliDate',
                'تاریخ شمسی معتبر نیست.'
            );
            return;
        }

        $this->date = sprintf(
            '%04d-%02d-%02d',
            $gregorian[0],
            $gregorian[1],
            $gregorian[2]
        );

        $this->jalaliDate = sprintf(
            '%04d/%02d/%02d',
            $year,
            $month,
            $day
        );

        if (in_array($this->endOfDay, ['1', 1], true)) {
            $this->end_time = '24:00';
        }

        $start = $this->timeMinutes($this->start_time, false);
        $end = $this->timeMinutes($this->end_time, true);

        if ($start === null) {
            $this->addError(
                'start_time',
                'ساعت شروع معتبر نیست؛ مانند 08:30.'
            );
        }

        if ($end === null) {
            $this->addError(
                'end_time',
                'ساعت پایان معتبر نیست؛ مانند 17:30 یا 24:00.'
            );
        }

        if ($start === null || $end === null) {
            return;
        }

        // زمان ذخیره‌شده در مدل با دقت دقیقه یکسان‌سازی می‌شود.
        $this->start_time = substr($this->start_time, 0, 5);
        $this->end_time = substr($this->end_time, 0, 5);
        $this->endOfDay = $end === 1440 ? '1' : '0';

        if ($end <= $start) {
            $this->addError(
                'end_time',
                'پایان باید بعد از شروع باشد؛ گزارش را از مرز روز عبور ندهید.'
            );
            return;
        }

        $timezone = new \DateTimeZone('Asia/Tehran');
        $base = new \DateTimeImmutable(
            $this->date . ' 00:00:00',
            $timezone
        );

        $endDateTime = $base->modify('+' . $end . ' minutes');

        if ($endDateTime > new \DateTimeImmutable('now', $timezone)) {
            $this->addError(
                'end_time',
                'زمان پایان گزارش نمی‌تواند در آینده باشد.'
            );
        }
    }

    /**
     * HH:mm و HH:mm:00 پذیرفته می‌شوند.
     * ثانیه غیرصفر پذیرفته نمی‌شود تا دقت گزارش یک دقیقه باقی بماند.
     */
    private function timeMinutes($time, $allowEndOfDay = true)
    {
        if (!is_string($time)) {
            return null;
        }

        if ($allowEndOfDay
            && ($time === '24:00' || $time === '24:00:00')) {
            return 1440;
        }

        if (!preg_match(
            '/\A([01]\d|2[0-3]):([0-5]\d)(?::00)?\z/',
            $time,
            $matches
        )) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    public function getDurationMinutes()
    {
        $start = $this->timeMinutes($this->start_time, false);
        $end = $this->timeMinutes($this->end_time, true);

        if ($start === null || $end === null) {
            return 0;
        }

        return $end - $start;
    }

    private function validateOverlap()
    {
        $record = self::activeQuery()
            ->andWhere([
                'rigIdRef' => $this->rigIdRef,
                'date' => $this->date,
            ])
            ->andWhere(['<', 'start_time', $this->end_time])
            ->andWhere(['>', 'end_time', $this->start_time])
            ->orderBy([
                'start_time' => SORT_ASC,
                'id' => SORT_ASC,
            ])
            ->one();

        if ($record) {
            $this->addError(
                'start_time',
                sprintf(
                    'بازه با گزارش شماره %s از %s تا %s تداخل دارد.',
                    $record->id,
                    substr($record->start_time, 0, 5),
                    substr($record->end_time, 0, 5)
                )
            );
        }
    }

    /**
     * hours برحسب ساعت اعشاری است، نه دقیقه.
     */
    private function validateWork($prefix, array $input, $label)
    {
        $hours = $this->validateAmount(
            $prefix . '[hours]',
            $input['hours'] ?? null,
            'کارکرد ' . $label
        );

        $actual = $this->validateAmount(
            $prefix . '[consumption_user]',
            $input['consumption_user'] ?? null,
            'مصرف واقعی ' . $label
        );

        if ($hours !== null
            && $this->cents($hours) * 60
                > $this->getDurationMinutes() * 100) {
            $this->addError(
                $prefix . '[hours]',
                'کارکرد تجهیز نمی‌تواند بیشتر از مدت گزارش باشد.'
            );
        }

        if ($hours !== null
            && $actual !== null
            && $hours == 0
            && $actual > 0) {
            $this->addError(
                $prefix . '[consumption_user]',
                'مصرف مثبت با کارکرد صفر مجاز نیست.'
            );
        }

        return [$hours ?? 0, $actual ?? 0];
    }

    /**
     * مجموع کارکرد تمام ژنراتورها با یک کوئری خوانده می‌شود.
     */
    private function getDailyGeneratorHours()
    {
        $select = [];

        for ($number = 1; $number <= self::MAX_EQUIPMENT_COUNT; $number++) {
            $column = 'generator' . $number . '_hours';

            $select[$column] = new Expression(
                'COALESCE(SUM([[' . $column . ']]), 0)'
            );
        }

        return self::activeQuery()
            ->select($select)
            ->andWhere([
                'rigIdRef' => $this->rigIdRef,
                'date' => $this->date,
            ])
            ->asArray()
            ->one() ?: [];
    }

private function validateAndCalculateEquipment()
{
    $totalHours = 0;
    $totalActual = 0;
    $totalIdeal = 0;

    // ۱. صفر کردن اولیه مقادیر موتورها و ژنراتورها
    for ($number = 1; $number <= self::MAX_EQUIPMENT_COUNT; $number++) {
        foreach (['engine', 'generator'] as $type) {
            $this->{$type . $number . '_hours'} = 0;
            $this->{$type . $number . '_consumption_user'} = 0;
            $this->{$type . $number . '_consumption_calc'} = 0;
        }
    }

    $rows = $this->getEquipmentRows();

    // ۲. محاسبه مصرف موتورها و ژنراتورها (ساعت کارکرد × نرخ تست‌شده)
    foreach (['engine' => 'engines', 'generator' => 'generators'] as $type => $group) {
        foreach ($rows[$group] as $row) {
            $number = $row['number'];
            if (!isset($this->equipmentInput[$type][$number])) {
                continue;
            }

            $prefix = "equipment[{$type}][{$number}]";
            $input = $this->equipmentInput[$type][$number];

            list($hours, $userRateInput) = $this->validateWork(
                $prefix,
                $input,
                $row['title']
            );

            $testedRate = $this->decimal($row['tested_rate']);
            $idealRate = $this->decimal($row['rate']);

            // مصرف واقعی این موتور/ژنراتور = ساعت کارکرد × نرخ تست شده
            $actual = round($hours * ($testedRate ?? 0), 2);
            $ideal = round($hours * ($idealRate ?? 0), 2);

            $this->{$type . $number . '_hours'} = $hours;
            $this->{$type . $number . '_consumption_user'} = $actual;
            $this->{$type . $number . '_consumption_calc'} = $ideal;

            $totalHours += $this->cents($hours);
            $totalActual += $this->cents($actual);
            $totalIdeal += $this->cents($ideal);
        }
    }

    // ۳. محاسبه مصرف تجهیزات جانبی (واحد سیمان، جرثقیل و سایر)
    // فرمول: ساعت کارکرد × نرخ مصرف وارد شده توسط کاربر
    $auxiliaryLabels = [
        'cement_unit' => 'واحد سیمان',
        'crane' => 'جرثقیل',
        'other' => 'سایر تجهیزات',
    ];

    $auxiliary = is_array($this->auxiliaryEquipment) ? $this->auxiliaryEquipment : [];

    foreach ($auxiliaryLabels as $type => $label) {
        $input = $auxiliary[$type] ?? [];

        list($hours, $rateInput) = $this->validateWork(
            "auxiliary_equipment[{$type}]",
            $input,
            $label
        );

        // محاسبه دقیق مصرف تجهیز جانبی: ساعت کارکرد × مقدار مصرف/نرخ وارد شده
        $auxActual = round($hours * $rateInput, 2);

        $this->{$type . '_hours'} = $hours;
        $this->{$type . '_consumption_user'} = $auxActual;
        $this->{$type . '_consumption_calc'} = $auxActual;

        // اضافه کردن مستقیم به مجموع کل ساعت‌ها و مجموع کل مصرف واقعی
        $totalHours += $this->cents($hours);
        $totalActual += $this->cents($auxActual);
        $totalIdeal += $this->cents($auxActual);
    }

    if ($totalActual > self::MAX_CENTS || $totalIdeal > self::MAX_CENTS) {
        $this->addError('rigIdRef', 'مجموع مصرف از محدوده مجاز بیشتر است.');
    }

    // ثبت نهایی مقادیر در فیلدهای اصلی گزارش
    $this->operating_hours = $totalHours / 100;
    $this->fuel_consumption_user_input = $totalActual / 100; // مجموع کل مصرف واقعی شامل موتور، ژنراتور، جرثقیل و سیمان
    $this->total_ideal_consumption = $totalIdeal / 100;
    $this->consumption_difference = ($totalActual - $totalIdeal) / 100;
    $this->fuel_used = $totalActual / 100;
}

    private function validateAndSyncBalance($resource)
    {
        $property = $resource . 'BalanceInput';
        $key = $resource . '_balance';
        $label = $resource === 'fuel' ? 'سوخت' : 'آب';

        $input = $this->$property;

        if (!is_array($input)) {
            $this->addError(
                $key . '[enabled]',
                'ساختار اطلاعات تراز معتبر نیست.'
            );
            return;
        }

        $input = array_merge([
            'enabled' => null,
            'receive_enabled' => '0',
            'transfer_enabled' => '0',
            'received' => null,
            'received_from' => '',
            'received_from_desc' => '',
            'transferred' => null,
            'transferred_to' => '',
            'transferred_to_desc' => '',
            'used' => null,
        ], $input);

        foreach (['enabled', 'receive_enabled', 'transfer_enabled'] as $flag) {
            if (!in_array($input[$flag], ['0', '1', 0, 1], true)) {
                $this->addError(
                    $key . '[' . $flag . ']',
                    'وضعیت انتخاب معتبر نیست.'
                );
            }
        }

        $enabled = in_array($input['enabled'], ['1', 1], true);

        $receive = $enabled
            && in_array($input['receive_enabled'], ['1', 1], true);

        $transfer = $enabled
            && in_array($input['transfer_enabled'], ['1', 1], true);

        if ($enabled && !$receive && !$transfer) {
            $this->addError(
                $key . '[enabled]',
                'حداقل یکی از گزینه‌های دریافت یا انتقال را انتخاب کنید.'
            );
        }

        $received = $receive
            ? $this->validateAmount(
                $key . '[received]',
                $input['received'],
                $label . ' دریافتی',
                true
            )
            : 0;

        $transferred = $transfer
            ? $this->validateAmount(
                $key . '[transferred]',
                $input['transferred'],
                $label . ' انتقالی',
                true
            )
            : 0;

        $specifications = [
            ['received_from', $receive, 'مبدأ دریافت', 100, true],
            ['received_from_desc', $receive, 'شرح دریافت', 10000, false],
            ['transferred_to', $transfer, 'مقصد انتقال', 100, true],
            ['transferred_to_desc', $transfer, 'شرح انتقال', 10000, false],
        ];

        foreach ($specifications as $specification) {
            list($field, $active, $title, $max, $required) = $specification;

            if (!$active) {
                $input[$field] = '';
                continue;
            }

            if (!is_string($input[$field])) {
                $this->addError(
                    $key . '[' . $field . ']',
                    $title . ' معتبر نیست.'
                );

                $input[$field] = '';
                continue;
            }

            $input[$field] = trim($input[$field]);

            if ($required && $input[$field] === '') {
                $this->addError(
                    $key . '[' . $field . ']',
                    $title . ' الزامی است.'
                );
            }

            if (mb_strlen($input[$field], 'UTF-8') > $max) {
                $this->addError(
                    $key . '[' . $field . ']',
                    $title . ' بیش از حد طولانی است.'
                );
            }
        }

        $this->{$resource . '_received'} = $received ?? 0;
        $this->{$resource . '_transferred'} = $transferred ?? 0;

        foreach ([
            'received_from',
            'received_from_desc',
            'transferred_to',
            'transferred_to_desc',
        ] as $field) {
            $this->{$resource . '_' . $field} = $input[$field] !== ''
                ? $input[$field]
                : null;
        }

        if ($resource === 'water') {
            $used = $this->validateAmount(
                'water_balance[used]',
                $input['used'],
                'مصرف آب'
            );

            $this->water_used = $used ?? 0;
            $this->water_consumption_user_input = $used ?? 0;
        }

        /*
         * ورودی خام فرم بازنویسی نمی‌شود تا در صورت خطای اعتبارسنجی،
         * مقادیر واردشده کاربر برای نمایش مجدد حفظ شوند.
         * فقط فیلدهای دیتابیس بر اساس گزینه‌های فعال همگام می‌شوند.
         */
    }

private function buildBalancePlan(
    $initialFuel,
    $initialWater,
    $includeCurrent
) {
    $initialFuel = $this->decimal($initialFuel);
    $initialWater = $this->decimal($initialWater);

    if ($initialFuel === null || $initialWater === null) {
        throw new \DomainException('موجودی پایه دکل معتبر نیست.');
    }

    $records = self::activeQuery()
        ->andWhere(['rigIdRef' => $this->rigIdRef])
        ->orderBy([
            'date' => SORT_ASC,
            'start_time' => SORT_ASC,
            'id' => SORT_ASC,
        ])
        ->all();

    if ($includeCurrent) {
        $records[] = $this;
    }

    // استخراج آی‌دی تمام گزارش‌های ثبت‌شده همین دکل
    $ownReportIds = (new \yii\db\Query())
        ->select(['id'])
        ->from(self::tableName())
        ->where(['rigIdRef' => $this->rigIdRef])
        ->column();

    // محاسبه مجموع تراکنش‌های دریافتی/انتقالی مستقل یا متناظر از دکل‌های دیگر
    $extraTransactionsQuery = (new \yii\db\Query())
        ->select([
            'resource_type',
            'transaction_type',
            'SUM(amount) as total_amount'
        ])
        ->from('fuel_transaction_table_fuel_transaction')
        ->where([
            'equipment_type' => FuelTransaction::EQUIPMENT_TYPE_RIG,
            'equipment_id' => $this->rigIdRef,
            'voided_at' => null,
        ]);

    if (!empty($ownReportIds)) {
        // تراکنش‌هایی که مرتبط با گزارش‌های خود این دکل نیستند
        $extraTransactionsQuery->andWhere(['not in', 'consumption_id', $ownReportIds]);
    }

    $extraTransactions = $extraTransactionsQuery
        ->groupBy(['resource_type', 'transaction_type'])
        ->all();

    $extraFuelReceive = 0;
    $extraFuelTransfer = 0;
    $extraWaterReceive = 0;
    $extraWaterTransfer = 0;

    foreach ($extraTransactions as $t) {
        if ($t['resource_type'] === 'fuel') {
            if ($t['transaction_type'] === 'receive') $extraFuelReceive += (float)$t['total_amount'];
            if ($t['transaction_type'] === 'transfer') $extraFuelTransfer += (float)$t['total_amount'];
        } elseif ($t['resource_type'] === 'water') {
            if ($t['transaction_type'] === 'receive') $extraWaterReceive += (float)$t['total_amount'];
            if ($t['transaction_type'] === 'transfer') $extraWaterTransfer += (float)$t['total_amount'];
        }
    }

    // اعمال تراکنش‌های دریافتی/انتقالی متناظر روی موجودی پایه دکل
    $balances = [
        'fuel' => $this->cents($initialFuel) + $this->cents($extraFuelReceive) - $this->cents($extraFuelTransfer),
        'water' => $this->cents($initialWater) + $this->cents($extraWaterReceive) - $this->cents($extraWaterTransfer),
    ];

    $plan = [];
    $previous = null;
    $previousEnd = null;

    foreach ($records as $record) {
        $start = $this->timeMinutes($record->start_time, false);
        $end = $this->timeMinutes($record->end_time, true);

        $values = [];

        foreach (['fuel', 'water'] as $resource) {
            $opening = $balances[$resource];

            $received = $this->decimal($record->{$resource . '_received'});
            $used = $this->decimal($record->{$resource . '_used'});
            $transferred = $this->decimal($record->{$resource . '_transferred'});

            $closing = $opening
                + $this->cents($received)
                - $this->cents($used)
                - $this->cents($transferred);

            $values[$resource . '_opening_balance'] = $opening / 100;
            $values[$resource . '_closing_balance'] = $closing / 100;

            $balances[$resource] = $closing;
        }

        if ($record === $this && $includeCurrent) {
            foreach ($values as $field => $value) {
                $this->$field = $value;
            }
        }

        $previous = $record;
        $previousEnd = $end;
    }

    return $plan;
}

    private function persistBalancePlan(array $plan)
    {
        foreach ($plan as $item) {
            $affected = self::updateAll(
                $item['values'],
                [
                    'id' => $item['id'],
                    'rigIdRef' => $this->rigIdRef,
                    'voided_at' => null,
                ]
            );

            if ($affected !== 1) {
                throw new \RuntimeException(
                    'به‌روزرسانی موجودی گزارش شماره '
                    . $item['id'] . ' ناموفق بود.'
                );
            }
        }
    }

    /**
     * تمام مسیرهای تغییر گزارش/موجودی یک دکل باید همین قفل را رعایت کنند.
     * مناسب MySQL/MariaDB با جداول تراکنشی InnoDB.
     */
    private static function lockRig($rigId)
    {
        $db = self::getDb();
        $table = $db->quoteTableName(RigType::tableName());
        $idColumn = $db->quoteColumnName('id');

        return $db->createCommand(
            "SELECT * FROM {$table} WHERE {$idColumn} = :id FOR UPDATE",
            [':id' => $rigId]
        )->queryOne();
    }

    private static function assertSharedDatabase()
    {
        $db = self::getDb();

        foreach ([
            RigType::class,
            RigEngine::class,
            RigGenerator::class,
            FuelTransaction::class,
        ] as $class) {
            if ($class::getDb() !== $db) {
                throw new \LogicException(
                    'مدل‌های گزارش، تجهیزات و تراکنش'
                    . ' باید از اتصال دیتابیس مشترک استفاده کنند.'
                );
            }
        }
    }

    private function assertIndependentTransaction()
    {
        $transaction = self::getDb()->getTransaction();

        if ($transaction !== null && $transaction->isActive) {
            throw new \LogicException(
                'این عملیات باید تراکنش مستقل داشته باشد.'
            );
        }
    }

    private function reportOperationFailure(
        \Throwable $exception,
        $message,
        $method
    ) {
        $this->invalidateCalculation();

        Yii::error($exception, $method);

        $this->addError(
            'rigIdRef',
            $exception instanceof \DomainException
                ? $exception->getMessage()
                : $message
        );
    }

    public function saveWithFuelTransactions()
    {
        $this->clearErrors();
        $this->invalidateCalculation();

        if (!$this->isNewRecord) {
            $this->addError(
                'rigIdRef',
                'این متد فقط برای ثبت گزارش جدید است.'
            );
            return false;
        }

        if (Yii::$app->user->isGuest) {
            $this->addError(
                'rigIdRef',
                'برای ثبت گزارش وارد سامانه شوید.'
            );
            return false;
        }

        /*
         * خطای برنامه‌نویسی در مدیریت تراکنش پنهان نشود.
         * این بررسی قبل از ورود به تراکنش جدید انجام می‌شود.
         */
        $this->assertIndependentTransaction();

        $transaction = null;
        $attributesBeforeSave = null;
        $oldAttributesBeforeSave = null;

        try {
            self::assertSharedDatabase();

            // اعتبارسنجی اولیه پیش از قفل، زمان نگهداری قفل را کاهش می‌دهد.
            if (!$this->validate()) {
                return false;
            }

            $db = self::getDb();

            $transaction = $db->beginTransaction(
                Transaction::READ_COMMITTED
            );

            $rig = self::lockRig($this->rigIdRef);

            if (!$rig || (int) $rig['is_deleted'] !== 0) {
                throw new \DomainException(
                    'دکل حذف شده یا معتبر نیست.'
                );
            }

            /*
             * بررسی نهایی پس از قفل:
             * تداخل، کارکرد روزانه، نرخ‌ها و زنجیره موجودی
             * بر اساس آخرین داده ثبت‌شده دوباره محاسبه می‌شوند.
             */
            if (!$this->validate()) {
                $transaction->rollBack();
                $this->invalidateCalculation();
                return false;
            }

            $attributesBeforeSave = $this->getAttributes();
            $oldAttributesBeforeSave = $this->getOldAttributes();

            $this->created_by = Yii::$app->user->id;

            $this->voided_at = null;
            $this->voided_by = null;
            $this->void_reason = null;

            if (!$this->save(false)) {
                throw new \RuntimeException(
                    'ذخیره گزارش ناموفق بود.'
                );
            }

            $this->saveResourceTransactions();
            $this->persistBalancePlan($this->balancePlan);

            $transaction->commit();
        } catch (\Throwable $exception) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }

            /*
             * در صورت درج گزارش و شکست مراحل بعدی،
             * مدل دوباره به وضعیت پیش از درج برگردد.
             */
            if ($attributesBeforeSave !== null) {
                $this->setAttributes($attributesBeforeSave, false);
                $this->setOldAttributes($oldAttributesBeforeSave);
            }

            $this->reportOperationFailure(
                $exception,
                'ثبت اطلاعات ناموفق بود؛ هیچ تغییری ذخیره نشد.',
                __METHOD__
            );

            return false;
        }

        // پس از commit عملیات دیتابیسی دیگری انجام نمی‌شود.
        $this->balancePlan = [];

        return true;
    }

private function saveResourceTransactions()
{
    foreach (['fuel', 'water'] as $resource) {
        foreach ([true, false] as $receive) {
            $amountField = $receive ? 'received' : 'transferred';
            $partyField = $receive ? 'received_from' : 'transferred_to';
            $descField = $receive ? 'received_from_desc' : 'transferred_to_desc';

            $amount = $this->{$resource . '_' . $amountField};

            if ((float) $amount <= 0) {
                continue;
            }

            $partyId = $this->{$resource . '_' . $partyField};
            $desc = $this->{$resource . '_' . $descField};

            // تعیین عنوان مبدأ یا مقصد
            $sourceText = $receive ? ($partyId === 'other' ? 'سایر' : 'تجهیز کد ' . $partyId) : null;
            $destinationText = !$receive ? ($partyId === 'other' ? 'سایر' : 'تجهیز کد ' . $partyId) : null;

            // ۱. ثبت تراکنش اصلی برای دکل جاری
            $factory = $receive ? 'createReceive' : 'createTransfer';
            $partyText = $receive ? $sourceText : $destinationText;

            $mainTransaction = FuelTransaction::$factory(
                $this->id,
                FuelTransaction::EQUIPMENT_TYPE_RIG,
                $this->rigIdRef,
                $resource,
                $this->date,
                $amount,
                $partyText,
                $desc
            );

            if (!$mainTransaction->save()) {
                $errors = implode(' - ', array_map(function($e) { return implode(',', $e); }, $mainTransaction->getErrors()));
                throw new \DomainException('خطا در ثبت تراکنش اصلی سوخت/آب: ' . $errors);
            }

            // ۲. ثبت تراکنش متناظر برای دکل/شناور مقابل (با مقداردهی consumption_id به $this->id)
            if ($partyId && $partyId !== 'other' && is_numeric($partyId)) {
                
                // اگر تراکنش جاری «انتقال» باشد، برای مقصد یک «دریافت» ثبت می‌کنیم
                if (!$receive) {
                    $counterpartTransaction = FuelTransaction::createReceive(
                        $this->id, // مقداردهی شناسه گزارش برای رفع خطای الزامی بودن
                        FuelTransaction::EQUIPMENT_TYPE_RIG,
                        (int) $partyId,
                        $resource,
                        $this->date,
                        $amount,
                        'دکل شماره ' . $this->rigIdRef,
                        'انتقال خودکار سیستم از گزارش شماره ' . $this->id . ($desc ? ' - ' . $desc : '')
                    );

                    $counterpartTransaction->related_transfer_id = $mainTransaction->id;

                    if (!$counterpartTransaction->save()) {
                        $errors = implode(' - ', array_map(function($e) { return implode(',', $e); }, $counterpartTransaction->getErrors()));
                        throw new \DomainException('خطا در ثبت تراکنش خودکار مقصد: ' . $errors);
                    }
                }
                // اگر تراکنش جاری «دریافت» از یک دکل دیگر باشد، برای مبدأ یک «انتقال» ثبت می‌کنیم
                else {
                    $counterpartTransaction = FuelTransaction::createTransfer(
                        $this->id, // مقداردهی شناسه گزارش برای رفع خطای الزامی بودن
                        FuelTransaction::EQUIPMENT_TYPE_RIG,
                        (int) $partyId,
                        $resource,
                        $this->date,
                        $amount,
                        'دکل شماره ' . $this->rigIdRef,
                        'دریافت خودکار سیستم طبق گزارش شماره ' . $this->id . ($desc ? ' - ' . $desc : '')
                    );

                    if (!$counterpartTransaction->save()) {
                        $errors = implode(' - ', array_map(function($e) { return implode(',', $e); }, $counterpartTransaction->getErrors()));
                        throw new \DomainException('خطا در ثبت تراکنش خودکار مبدأ: ' . $errors);
                    }

                    // لینک کردن تراکنش متناظر به تراکنش دریافت اصلی
                    $mainTransaction->related_transfer_id = $counterpartTransaction->id;
                    $mainTransaction->save(false, ['related_transfer_id']);
                }
            }
        }
    }
}

    public function voidWithTransactions($reason)
    {
        $this->clearErrors();
        $this->invalidateCalculation();

//        if (PHP_INT_SIZE < 8) {
//            $this->addError(
//                'rigIdRef',
//                'برای محاسبات دقیق موجودی، اجرای برنامه با PHP شصت‌وچهاربیتی لازم است.'
//            );
//            return false;
//        }

        if ($this->isNewRecord || Yii::$app->user->isGuest) {
            $this->addError(
                'rigIdRef',
                'ابطال این گزارش مجاز نیست.'
            );
            return false;
        }

        if (!is_string($reason)) {
            $this->addError(
                'rigIdRef',
                'علت ابطال معتبر نیست.'
            );
            return false;
        }

        $reason = trim($reason);

        if ($reason === ''
            || mb_strlen($reason, 'UTF-8') > 500) {
            $this->addError(
                'rigIdRef',
                'علت ابطال را حداکثر در ۵۰۰ نویسه وارد کنید.'
            );
            return false;
        }

        $this->assertIndependentTransaction();

        $reportId = $this->getOldAttribute('id');
        $rigId = $this->getOldAttribute('rigIdRef');

        $transaction = null;
        $committedAttributes = null;

        try {
            self::assertSharedDatabase();

            $transaction = self::getDb()->beginTransaction(
                Transaction::READ_COMMITTED
            );

            $rig = self::lockRig($rigId);

            if (!$rig) {
                throw new \DomainException('دکل پیدا نشد.');
            }

            /*
             * شناسه دکل نیز بررسی می‌شود تا عملیات روی همان
             * دکلی انجام شود که قفل شده است.
             */
            $record = self::find()
                ->where([
                    'id' => $reportId,
                    'rigIdRef' => $rigId,
                ])
                ->one();

            if (!$record || $record->voided_at !== null) {
                throw new \DomainException(
                    'گزارش پیدا نشد یا قبلاً باطل شده است.'
                );
            }

            $condition = [
                'equipment_type' => FuelTransaction::EQUIPMENT_TYPE_RIG,
                'equipment_id' => $record->rigIdRef,
                'consumption_id' => $record->id,
                'voided_at' => null,
            ];

            $transactions = FuelTransaction::find()
                ->where($condition)
                ->all();

            $transactionIds = [];

            foreach ($transactions as $item) {
                $transactionIds[] = $item->id;

                if ($item->related_transfer_id !== null
                    || $item->counterparty_vessel_id !== null) {
                    throw new \DomainException(
                        'این گزارش تراکنش داخلی مرتبط دارد؛'
                        . ' ابطال آن باید دوطرفه انجام شود.'
                    );
                }
            }

            /*
             * بررسی دریافت‌های وابسته با یک کوئری.
             * برای حفظ رفتار محافظه‌کارانه، وجود سابقه وابسته
             * حتی اگر باطل شده باشد نیز نیازمند بررسی دوطرفه است.
             */
            if ($transactionIds
                && FuelTransaction::find()
                    ->where([
                        'related_transfer_id' => $transactionIds,
                    ])
                    ->exists()) {
                throw new \DomainException(
                    'این گزارش تراکنش داخلی مرتبط دارد؛'
                    . ' ابطال آن باید دوطرفه انجام شود.'
                );
            }

            $timezone = new \DateTimeZone('Asia/Tehran');

            $audit = [
                'voided_at' => (
                    new \DateTimeImmutable('now', $timezone)
                )->format('Y-m-d H:i:s'),
                'voided_by' => Yii::$app->user->id,
                'void_reason' => $reason,
            ];

            $affectedReports = self::updateAll(
                $audit,
                [
                    'id' => $record->id,
                    'rigIdRef' => $record->rigIdRef,
                    'voided_at' => null,
                ]
            );

            if ($affectedReports !== 1) {
                throw new \DomainException(
                    'وضعیت گزارش تغییر کرده است؛ صفحه را تازه‌سازی کنید.'
                );
            }

            $affectedTransactions = FuelTransaction::updateAll(
                $audit,
                $condition
            );

            if ($affectedTransactions !== count($transactions)) {
                throw new \RuntimeException(
                    'وضعیت تراکنش‌های گزارش هنگام ابطال تغییر کرده است.'
                );
            }

            /*
             * گزارش در همین تراکنش باطل شده است؛ activeQuery()
             * آن را از زنجیره موجودی کنار می‌گذارد.
             */
            $plan = $record->buildBalancePlan(
                $rig['initial_fuel_balance'],
                $rig['initial_water_balance'],
                false
            );

            $record->persistBalancePlan($plan);

            $committedAttributes = array_merge(
                $record->getAttributes(),
                $audit
            );

            $transaction->commit();
        } catch (\Throwable $exception) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }

            $this->reportOperationFailure(
                $exception,
                'ابطال ناموفق بود؛ هیچ تغییری ذخیره نشد.',
                __METHOD__
            );

            return false;
        }

        /*
         * پس از commit از refresh() استفاده نمی‌شود.
         * شکست یک کوئری بازخوانی نباید ابطال موفق را ناموفق نشان دهد.
         */
        $this->setAttributes($committedAttributes, false);
        $this->setOldAttributes($committedAttributes);

        return true;
    }

public function getEquipmentRows()
    {
        $rigId = (string) self::normalizeInput($this->rigIdRef);
        if ($this->equipmentRows !== null && $this->equipmentRigId === $rigId) {
            return $this->equipmentRows;
        }

        $this->equipmentRigId = $rigId;
        $this->equipmentRows = ['engines' => [], 'generators' => []];

        if (!self::isValidRigId($rigId)) {
            return $this->equipmentRows;
        }

        $specifications = [
            ['engine', 'engines', RigEngine::class, 'اسب بخار', 'موتور'],
            ['generator', 'generators', RigGenerator::class, 'کیلووات', 'ژنراتور'],
        ];

        foreach ($specifications as $specification) {
            list($type, $group, $class, $unit, $label) = $specification;
            $records = $class::find()->where(['rig_id' => $rigId])->orderBy([$type . '_number' => SORT_ASC])->all();

            foreach ($records as $record) {
                $number = (int) $record->{$type . '_number'};
                $this->equipmentRows[$group][] = [
                    'type' => $type,
                    'number' => $number,
                    'title' => $label . ' ' . $number,
                    'model' => $record->{$type . '_type'} ?: '-',
                    'power' => $record->{$type . '_power'},
                    'power_unit' => $unit,
                    'rate' => $record->{$type . '_fuel_consumption'},
                    'tested_rate' => $record->{$type . '_tested_fuel_consumption'}, // نرخ تست‌شده
                ];
            }
        }

        return $this->equipmentRows;
    }
//    public static function getTargetEquipmentList()
//    {
//        $rigs = RigType::getDropdownList();
//        $vessels = VesselType::getDropdownList(); // فرض بر وجود مدل VesselType
//
//        $list = [];
//        if (!empty($rigs)) {
//            $list['دکل‌ها'] = $rigs;
//        }
//        if (!empty($vessels)) {
//            $list['شناورها'] = $vessels;
//        }
//        $list['other'] = 'سایر (ثبت دستی)';
//
//        return $list;
//    }
    public static function getTargetEquipmentList()
{
    $list = [];
    
    $rigs = RigType::getDropdownList();
    if (!empty($rigs)) {
        $items = [];
        foreach ($rigs as $id => $name) {
            $items['rig:' . $id] = $name;
        }
        $list['دکل‌ها'] = $items;
    }
    
    $vessels = VesselType::getDropdownList();
    if (!empty($vessels)) {
        $items = [];
        foreach ($vessels as $id => $name) {
            $items['vessel:' . $id] = $name;
        }
        $list['شناورها'] = $items;
    }
    
    $list['other'] = 'سایر (ثبت دستی)';
    
    return $list;
}


    public function getEquipmentValue($type, $number, $field)
    {
        if (!in_array($type, ['engine', 'generator'], true)
            || !in_array($field, ['hours', 'consumption_user'], true)
            || (!is_string($number) && !is_int($number))
            || !preg_match('/\A[1-5]\z/', (string) $number)) {
            return 0;
        }

        $number = (int) $number;

        if (isset($this->equipmentInput[$type][$number])
            && array_key_exists(
                $field,
                $this->equipmentInput[$type][$number]
            )) {
            return $this->equipmentInput[$type][$number][$field] ?? 0;
        }

        if (!$this->isNewRecord) {
            $attribute = $type . $number . '_' . $field;

            return $this->getAttribute($attribute) ?? 0;
        }

        return 0;
    }

    public function getEquipmentError($type, $number, $field)
    {
        return $this->getFirstError(
            "equipment[{$type}][{$number}][{$field}]"
        );
    }

    public function getSummaryReady()
    {
        return $this->summaryReady && !$this->hasErrors();
    }

    public function getConsumptionSummary()
    {
        return [
            'operating_hours' => (float) $this->operating_hours,
            'actual_consumption' => (float) $this->fuel_consumption_user_input,
            'ideal_consumption' => (float) $this->total_ideal_consumption,
            'difference' => (float) $this->consumption_difference,
        ];
    }

    public function getFuelBalanceSummary()
    {
        return $this->balanceSummary('fuel');
    }

    public function getWaterBalanceSummary()
    {
        return $this->balanceSummary('water');
    }

    private function balanceSummary($resource)
    {
        $result = [];

        foreach ([
            'opening_balance',
            'received',
            'used',
            'transferred',
            'closing_balance',
        ] as $field) {
            $result[$field] = (float) $this->{$resource . '_' . $field};
        }

        return $result;
    }

    public function getFormData()
    {
        return [
            'rigs' => RigType::getDropdownList(),
            'locations' => LocationType::getLocationList(),
            'activities' => ActivityType::getActivityList(),
        ];
    }

    public function getRig()
    {
        return $this->hasOne(
            RigType::class,
            ['id' => 'rigIdRef']
        );
    }

    public function getLocation()
    {
        return $this->hasOne(
            LocationType::class,
            ['id' => 'locationIdRef']
        );
    }

    public function getActivity()
    {
        return $this->hasOne(
            ActivityType::class,
            ['id' => 'activityIdRef']
        );
    }

    public function getCreatedByUser()
    {
        return $this->hasOne(
            \app\modules\account\models\User::class,
            ['UserID' => 'created_by']
        );
    }
}