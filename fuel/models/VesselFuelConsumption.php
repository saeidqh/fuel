<?php

namespace app\modules\fuel\models;

use Yii;
use yii\db\ActiveRecord;

class VesselFuelConsumption extends ActiveRecord
{
    const STATUS_ON_HIRE = 'on_hire';
    const STATUS_OFF_HIRE = 'off_hire';

    const MAX_AMOUNT = 99999999.99;
    const TIME_ZONE = 'Asia/Tehran';

    const MAX_DAILY_GENERATOR_MINUTES = 1440;

    const MAX_ITEM_ROWS = 14;
    const MAX_TRANSACTION_ROWS = 100;

    public $jalaliDate;
    public $endOfDay = '0';
    public $waterUsed = 0;

    private $_itemInput = [];
    private $_transactionInput = [];
    private $_itemModels = [];
    private $_transactionModels = [];
    private $_submitted = false;

    public static function tableName()
    {
        return 'vessel_fuel_consumption_table_vessel_fuel_consumption';
    }

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => [
                'vesselIdRef',
                'locationIdRef',
                'activityIdRef',
                'jalaliDate',
                'start_time',
                'end_time',
                'endOfDay',
                'status',
                'description',
                'destination_from',
                'destination_to',
                'waterUsed',
            ],
        ];
    }

    public function rules()
    {
        return [
            [
                [
                    'vesselIdRef',
                    'locationIdRef',
                    'activityIdRef',
                    'jalaliDate',
                    'start_time',
                    'endOfDay',
                    'waterUsed',
                ],
                'filter',
                'filter' => [ConsumptionInput::class, 'normalize'],
            ],
            [
                ['endOfDay'],
                'default',
                'value' => '0',
            ],
            [
                ['endOfDay'],
                'in',
                'range' => ['0', '1'],
                'strict' => true,
            ],
            [
                ['end_time'],
                'filter',
                'skipOnEmpty' => false,
                'filter' => function ($value) {
                    return $this->endOfDay === '1'
                        ? '24:00'
                        : ConsumptionInput::normalize($value);
                },
            ],
            [
                ['description', 'destination_from', 'destination_to'],
                'filter',
                'filter' => function ($value) {
                    return is_string($value) ? trim($value) : $value;
                },
            ],
            [
                ['locationIdRef', 'activityIdRef'],
                'default',
                'value' => null,
            ],
            [
                [
                    'vesselIdRef',
                    'jalaliDate',
                    'start_time',
                    'end_time',
                    'status',
                    'waterUsed',
                ],
                'required',
            ],
            [
                ['vesselIdRef', 'locationIdRef', 'activityIdRef'],
                'integer',
                'min' => 1,
            ],
            [
                ['vesselIdRef'],
                'exist',
                'targetClass' => VesselType::class,
                'targetAttribute' => 'id',
            ],
            [
                ['locationIdRef'],
                'exist',
                'targetClass' => LocationType::class,
                'targetAttribute' => 'id',
            ],
            [
                ['activityIdRef'],
                'exist',
                'targetClass' => ActivityType::class,
                'targetAttribute' => 'id',
            ],
            [
                ['jalaliDate'],
                'string',
                'max' => 10,
            ],
            [
                ['start_time'],
                'match',
                'pattern' => '/\A(?:[01]\d|2[0-3]):[0-5]\d\z/',
                'message' => 'ساعت شروع را از 00:00 تا 23:59 وارد کنید.',
            ],
            [
                ['end_time'],
                'match',
                'pattern' => '/\A(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)\z/',
                'message' => 'ساعت پایان معتبر نیست؛ برای نیمه‌‌شب گزینه پایان روز را انتخاب کنید.',
            ],
            [
                ['status'],
                'in',
                'range' => array_keys(self::getStatusOptions()),
            ],
            [
                ['description'],
                'string',
                'max' => 5000,
            ],
            [
                ['destination_from', 'destination_to'],
                'string',
                'max' => 100,
            ],
            [
                ['waterUsed'],
                'number',
                'min' => 0,
                'max' => self::MAX_AMOUNT,
            ],
            [
                ['waterUsed'],
                'match',
                'pattern' => '/\A\d+(?:\.\d{1,2})?\z/',
                'message' => 'مصرف آب را با حداکثر دو رقم اعشار وارد کنید.',
            ],
            [
                ['vesselIdRef'],
                'validateIdentity',
            ],
            [
                ['jalaliDate'],
                'validatePeriod',
                'skipOnEmpty' => false,
            ],
            [
                ['vesselIdRef'],
                'validateOverlap',
            ],
            [
                ['vesselIdRef'],
                'validateDetails',
            ],
            [
                ['vesselIdRef'],
                'validateDailyGeneratorMinutes',
            ],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه گزارش',
            'vesselIdRef' => 'شناور',
            'locationIdRef' => 'لوکیشن',
            'activityIdRef' => 'فعالیت',
            'jalaliDate' => 'تاریخ گزارش',
            'date' => 'تاریخ گزارش',
            'start_time' => 'ساعت شروع',
            'end_time' => 'ساعت پایان',
            'endOfDay' => 'پایان روز ـ ۲۴:۰۰',
            'started_at' => 'شروع گزارش',
            'ended_at' => 'پایان گزارش',
            'status' => 'وضعیت قراردادی',
            'description' => 'توضیحات',
            'destination_from' => 'مبدأ حرکت',
            'destination_to' => 'مقصد حرکت',
            'waterUsed' => 'مصرف آب (لیتر)',
            'operating_hours' => 'جمع کارکرد تجهیزات (تجهیز‌ساعت)',
            'fuel_opening_balance' => 'موجودی اولیه سوخت',
            'fuel_received' => 'سوخت دریافتی',
            'fuel_used' => 'مصرف محاسبه‌شده سوخت',
            'fuel_transferred' => 'سوخت انتقالی',
            'fuel_closing_balance' => 'موجودی نهایی سوخت',
            'water_opening_balance' => 'موجودی اولیه آب',
            'water_received' => 'آب دریافتی',
            'water_used' => 'مصرف آب',
            'water_transferred' => 'آب انتقالی',
            'water_closing_balance' => 'موجودی نهایی آب',
            'fuel_consumption_user_input' => 'مصرف سوخت کل',
            'water_consumption_user_input' => 'مصرف آب',
            'total_ideal_consumption' => 'مصرف استاندارد تجهیزات',
            'consumption_difference' => 'انحراف مصرف',
            'created_at' => 'زمان ثبت',
            'updated_at' => 'زمان ویرایش',
            'created_by' => 'ثبت‌کننده',
        ];
    }

    public function afterFind()
    {
        parent::afterFind();

        $start = $this->parseDateTime($this->started_at);
        $end = $this->parseDateTime($this->ended_at);
        $date = $start ? $start->format('Y-m-d') : $this->date;

        $this->jalaliDate = ConsumptionInput::toJalali($date);

        $this->start_time = $start
            ? $start->format('H:i')
            : substr((string) $this->start_time, 0, 5);

        $this->end_time = $end
            ? $end->format('H:i')
            : substr((string) $this->end_time, 0, 5);

        $this->endOfDay = '0';

        if (
            $start
            && $end
            && $end->format('H:i:s') === '00:00:00'
            && $end->format('Y-m-d')
                === $start->modify('+1 day')->format('Y-m-d')
        ) {
            $this->end_time = '24:00';
            $this->endOfDay = '1';
        } elseif (!$end && $this->end_time === '24:00') {
            $this->endOfDay = '1';
        }

        $this->waterUsed = $this->water_used;
    }

    public function loadSubmission(array $data)
    {
        $this->_submitted = false;
        $this->_itemInput = [];
        $this->_transactionInput = [];
        $this->_itemModels = [];
        $this->_transactionModels = [];

        if (!$this->load($data)) {
            return false;
        }

        $this->_submitted = true;

        $this->_itemInput = array_key_exists('VesselConsumptionItem', $data)
            ? $data['VesselConsumptionItem']
            : [];

        $this->_transactionInput = array_key_exists('VesselTransaction', $data)
            ? $data['VesselTransaction']
            : [];

        return true;
    }

    public function validateIdentity($attribute, $params)
    {
        if (
            !$this->isNewRecord
            && $this->isAttributeChanged('vesselIdRef', false)
        ) {
            $this->addError(
                $attribute,
                'شناور گزارش پس از ثبت قابل تغییر نیست.'
            );
        }
    }

    public function validatePeriod($attribute, $params)
    {
        $this->started_at = null;
        $this->ended_at = null;

        foreach (['jalaliDate', 'start_time', 'end_time', 'endOfDay'] as $field) {
            if ($this->hasErrors($field)) {
                return;
            }
        }

        $date = ConsumptionInput::toGregorian($this->jalaliDate);

        if ($date === null) {
            $this->addError('jalaliDate', 'تاریخ گزارش معتبر نیست.');
            return;
        }

        $zone = new \DateTimeZone(self::TIME_ZONE);

        $start = $this->parseDateTime(
            $date . ' ' . $this->start_time . ':00'
        );

        if (!$start) {
            $this->addError(
                'start_time',
                'زمان شروع برای تاریخ انتخاب‌شده معتبر نیست.'
            );
            return;
        }

        if ($this->end_time === '24:00') {
            $nextDate = $start->modify('+1 day')->format('Y-m-d');
            $end = $this->parseDateTime($nextDate . ' 00:00:00');
        } else {
            if (strcmp($this->end_time, $this->start_time) <= 0) {
                $this->addError(
                    'end_time',
                    'ساعت پایان باید بعد از ساعت شروع باشد.'
                );
                return;
            }

            $end = $this->parseDateTime(
                $date . ' ' . $this->end_time . ':00'
            );
        }

        if (!$end) {
            $this->addError(
                'end_time',
                'زمان پایان برای تاریخ انتخاب‌شده معتبر نیست.'
            );
            return;
        }

        $seconds = $end->getTimestamp() - $start->getTimestamp();

        if ($seconds <= 0 || $seconds > 86400) {
            $this->addError(
                'end_time',
                'مدت گزارش باید بیشتر از صفر و حداکثر ۲۴ ساعت باشد.'
            );
            return;
        }

        if ($end > new \DateTimeImmutable('now', $zone)) {
            $this->addError(
                'end_time',
                'ثبت گزارش برای زمانی که هنوز نگذشته مجاز نیست.'
            );
            return;
        }

        $this->date = $date;
        $this->jalaliDate = ConsumptionInput::toJalali($date);
        $this->endOfDay = $this->end_time === '24:00' ? '1' : '0';
        $this->started_at = $start->format('Y-m-d H:i:s');
        $this->ended_at = $end->format('Y-m-d H:i:s');
    }

    public function validateOverlap($attribute, $params)
    {
        if ($this->hasErrors() || !$this->started_at || !$this->ended_at) {
            return;
        }

        $query = self::find()
            ->where(['vesselIdRef' => $this->vesselIdRef])
            ->andWhere(['<', 'started_at', $this->ended_at])
            ->andWhere(['>', 'ended_at', $this->started_at]);

        if (!$this->isNewRecord) {
            $query->andWhere(['<>', 'id', $this->id]);
        }

        $record = $query->orderBy(['started_at' => SORT_ASC])->one();

        if ($record) {
            $this->addError('start_time', sprintf(
                'این بازه با گزارش شماره %s از %s تا %s تداخل دارد.',
                $record->id,
                $record->started_at,
                $record->ended_at
            ));
        }
    }

    public function validateDetails($attribute, $params)
    {
        $this->_itemModels = [];
        $this->_transactionModels = [];

        if ($this->hasErrors()) {
            return;
        }

        if (!$this->_submitted) {
            $this->addError(
                'details',
                'اطلاعات کامل فرم برای اعتبارسنجی ارسال نشده است.'
            );
            return;
        }

        $this->prepareItems();
        $this->prepareTransactions();

        if (!$this->hasErrors()) {
            $this->calculateTotals();
        }
    }

public function validateDailyGeneratorMinutes($attribute, $params)
    {
        if ($this->hasErrors() || !$this->_submitted || !$this->started_at) {
            return;
        }

        $start = $this->parseDateTime($this->started_at);

        if (!$start) {
            return;
        }

        $dayStart = $start->setTime(0, 0, 0);
        $nextDayStart = $dayStart->modify('+1 day');
        $currentMinutes = 0;

        foreach ($this->_itemModels as $item) {
            if (!empty($item->generator_id)) {
                $currentMinutes += (int) $item->operating_minutes;
            }
        }

        $reportIds = self::find()
            ->select('id')
            ->where(['vesselIdRef' => $this->vesselIdRef])
            ->andWhere([
                '>=',
                'started_at',
                $dayStart->format('Y-m-d H:i:s'),
            ])
            ->andWhere([
                '<',
                'started_at',
                $nextDayStart->format('Y-m-d H:i:s'),
            ]);

        if (!$this->isNewRecord) {
            $reportIds->andWhere(['<>', 'id', $this->id]);
        }

        // اصلاح شرط کوئری ژنراتور
        $savedMinutes = (int) VesselConsumptionItem::find()
            ->where(['in', 'consumption_id', $reportIds])
            ->andWhere(['and', ['not', ['generator_id' => null]], ['>', 'generator_id', 0]])
            ->sum('operating_minutes');

        $totalMinutes = $savedMinutes + $currentMinutes;

        if ($totalMinutes <= self::MAX_DAILY_GENERATOR_MINUTES) {
            return;
        }

        $this->addError('details', sprintf(
            'مجموع کارکرد ژنراتورهای این شناور در تاریخ %s نباید بیشتر از ۲۴ ساعت (۱۴۴۰ دقیقه) باشد.',
            $this->jalaliDate
        ));
    }

    private function prepareItems()
    {
        if (
            !is_array($this->_itemInput)
            || count($this->_itemInput) > self::MAX_ITEM_ROWS
        ) {
            $this->addError(
                'details',
                'ساختار یا تعداد ردیف‌های تجهیزات معتبر نیست.'
            );
            return;
        }

        $existing = $this->isNewRecord
            ? []
            : $this->getItems()->indexBy('id')->all();

        $identities = [];
        $usedIds = [];

        foreach (array_values($this->_itemInput) as $index => $input) {
            $prefix = 'VesselConsumptionItem[' . $index . ']';

            if (!is_array($input)) {
                $this->addError(
                    'details',
                    'ساختار ردیف تجهیز معتبر نیست.'
                );
                continue;
            }

            $id = isset($input['id']) ? $input['id'] : null;

            if ($id !== null && $id !== '') {
                if (
                    !is_scalar($id)
                    || !ctype_digit((string) $id)
                    || !isset($existing[$id])
                    || isset($usedIds[$id])
                ) {
                    $this->addError(
                        $prefix . '[id]',
                        'شناسه ردیف تجهیز متعلق به این گزارش نیست یا تکرار شده است.'
                    );
                    continue;
                }

                $item = $existing[$id];
                $usedIds[$id] = true;
            } else {
                $item = new VesselConsumptionItem();
            }

            $item->bindReport($this);
            $item->setAttributes($input);

            $this->_itemModels[$index] = $item;

            if (!$item->validate()) {
                $this->copyErrors($item, $prefix);
                continue;
            }

            $key = $item->engine_id !== null
                ? 'engine:' . $item->engine_id
                : ($item->generator_id !== null
                    ? 'generator:' . $item->generator_id
                    : 'auxiliary:' . $item->auxiliary_code);

            if (isset($identities[$key])) {
                $this->addError(
                    'details',
                    'هر تجهیز فقط یک بار در هر گزارش قابل ثبت است.'
                );
            }

            $identities[$key] = true;
        }

        foreach ($existing as $id => $item) {
            if (!isset($usedIds[$id])) {
                $this->addError('details', sprintf(
                    'ردیف ثبت‌شده «%s» با شناسه %s در فرم ارسال نشده است.',
                    $item->getEquipmentTitle(),
                    $id
                ));
            }
        }
    }

private function prepareTransactions()
{
    if (
        !is_array($this->_transactionInput)
        || count($this->_transactionInput) > self::MAX_TRANSACTION_ROWS
    ) {
        $this->addError(
            'transactions',
            'ساختار یا تعداد تراکنش‌ها معتبر نیست.'
        );
        return;
    }

    $existing = $this->isNewRecord
        ? []
        : $this->getTransactions()->indexBy('id')->all();

    $usedIds = [];

    foreach (array_values($this->_transactionInput) as $index => $input) {
        $prefix = 'VesselTransaction[' . $index . ']';

        if (!is_array($input)) {
            $this->addError(
                'transactions',
                'ساختار تراکنش معتبر نیست.'
            );
            continue;
        }

        $id = isset($input['id']) ? $input['id'] : null;

        if ($id !== null && $id !== '') {
            if (
                !is_scalar($id)
                || !ctype_digit((string) $id)
                || !isset($existing[$id])
                || isset($usedIds[$id])
            ) {
                $this->addError(
                    $prefix,
                    'شناسه تراکنش معتبر نیست یا تکرار شده است.'
                );
                continue;
            }

            $model = $existing[$id];
            $usedIds[$id] = true;
        } else {
            $model = new FuelTransaction();
        }

        // ─────────────────────────────────────────────
        // ۱. ذخیره فیلدهای متنی (بدون تغییر)
        // ─────────────────────────────────────────────
        foreach ([
            'resource_type',
            'transaction_type',
            'from_source',
            'to_destination',
            'description',
        ] as $field) {
            $value = isset($input[$field]) ? $input[$field] : null;

            if ($value !== null && !is_string($value)) {
                $this->addError(
                    $prefix . '[' . $field . ']',
                    'مقدار ارسالی معتبر نیست.'
                );
                continue 2;
            }

            $model->$field = $value === null ? null : trim($value);
        }

        // ─────────────────────────────────────────────
        // ۲. اعتبارسنجی مقدار (بدون تغییر)
        // ─────────────────────────────────────────────
        $rawAmount = isset($input['amount']) ? $input['amount'] : null;

        $amount = is_scalar($rawAmount)
            ? ConsumptionInput::decimal($rawAmount)
            : null;

        if (
            $amount === null
            || !is_numeric($amount)
            || !is_finite((float) $amount)
            || (float) $amount <= 0
            || (float) $amount > self::MAX_AMOUNT
        ) {
            $this->addError(
                $prefix . '[amount]',
                'مقدار تراکنش باید عدد مثبت با حداکثر دو رقم اعشار باشد.'
            );
            continue;
        }

        // ─────────────────────────────────────────────
        // ۳. اعتبارسنجی زمان تراکنش (بدون تغییر)
        // ─────────────────────────────────────────────
        $occurredAt = isset($input['occurred_at'])
            ? ConsumptionInput::normalize($input['occurred_at'])
            : $this->ended_at;

        $time = $this->parseDateTime($occurredAt);

        if (
            !$time
            || $occurredAt < $this->started_at
            || $occurredAt > $this->ended_at
        ) {
            $this->addError(
                $prefix . '[occurred_at]',
                'زمان تراکنش باید داخل بازه گزارش باشد.'
            );
            continue;
        }

        // ─────────────────────────────────────────────
        // ۴. تعیین فیلد طرف مقابل بر اساس نوع تراکنش
        // ─────────────────────────────────────────────
        // receive  → مبدأ از from_source
        // transfer → مقصد از to_destination
        $partyField = $model->transaction_type === FuelTransaction::TYPE_RECEIVE
            ? 'from_source'
            : 'to_destination';

        $partyValue = isset($model->$partyField)
            ? trim((string) $model->$partyField)
            : '';

        // ─────────────────────────────────────────────
        // ۵. تفکیک پیشوند (rig: / vessel: / other / متن آزاد)
        // ─────────────────────────────────────────────
        $partyType = null;   // 'rig' | 'vessel' | null
        $partyId   = null;   // عدد صحیح یا null

        if ($partyValue !== '' && $partyValue !== 'other') {
            if (strpos($partyValue, ':') !== false) {
                list($rawType, $rawId) = explode(':', $partyValue, 2);
                $rawType = trim($rawType);
                $rawId   = trim($rawId);

                if (
                    in_array($rawType, ['rig', 'vessel'], true)
                    && ctype_digit($rawId)
                    && (int) $rawId > 0
                ) {
                    $partyType = $rawType;
                    $partyId   = (int) $rawId;
                } else {
                    $this->addError(
                        $prefix . '[' . $partyField . ']',
                        'طرف مقابل انتخاب‌شده معتبر نیست.'
                    );
                    continue;
                }
            }
            // اگر پیشوند ندارد، متن آزاد است (سایر) → partyType = null
        }

        // ─────────────────────────────────────────────
        // ۶. مقداردهی به مدل
        // ─────────────────────────────────────────────
        $model->amount = $amount;
        $model->consumption_id = $this->id;
        $model->equipment_type = FuelTransaction::EQUIPMENT_TYPE_VESSEL;
        $model->equipment_id = $this->vesselIdRef;
        $model->transaction_date = substr($occurredAt, 0, 10);
        $model->occurred_at = $occurredAt;

        // ⭐ طرف مقابل را ذخیره کن
        if ($partyType === 'vessel') {
            $model->counterparty_vessel_id = $partyId;
        } else {
            $model->counterparty_vessel_id = null;
        }

        // ⭐ اگر ستون counterparty_rig_id را به جدول اضافه کرده‌اید:
        // (در غیر این صورت این خطا خطا می‌دهد و باید کامنت شود)
        if (property_exists($model, 'counterparty_rig_id')) {
            if ($partyType === 'rig') {
                $model->counterparty_rig_id = $partyId;
            } else {
                $model->counterparty_rig_id = null;
            }
        }

        // related_transfer_id در saveTransactions پر می‌شود
        $model->related_transfer_id = null;

        if ($model->isNewRecord) {
            $model->created_by = $this->currentUserId();
        }

        // ─────────────────────────────────────────────
        // ۷. اعتبارسنجی نهایی مدل
        // ─────────────────────────────────────────────
        $fields = [
            'equipment_type',
            'equipment_id',
            'resource_type',
            'transaction_type',
            'amount',
            'from_source',
            'to_destination',
            'transaction_date',
            'description',
            'created_by',
        ];

        if (!$model->validate($fields)) {
            $this->copyErrors($model, $prefix);
            continue;
        }

        $this->_transactionModels[$index] = $model;
    }
}

    private function calculateTotals()
    {
        $minutes = 0;
        $totalConsumption = 0;

        foreach ($this->_itemModels as $item) {
            $minutes += (int) $item->operating_minutes;
            // مجموع مصرف شامل مصرف اتوماتیک موتورها و مصرف دستی تجهیزات جانبی است
            $totalConsumption += (float) $item->actual_consumption;
        }

        $this->operating_hours = $this->money($minutes / 60);
        $this->fuel_used = $this->money($totalConsumption);
        $this->fuel_consumption_user_input = $this->fuel_used;
        $this->total_ideal_consumption = $this->fuel_used;

        $this->water_used = $this->money($this->waterUsed);
        $this->water_consumption_user_input = $this->water_used;

        $this->consumption_difference = '0.00';

        foreach (['fuel', 'water'] as $resource) {
            $this->{$resource . '_received'} = '0.00';
            $this->{$resource . '_transferred'} = '0.00';

            foreach ([
                'received_from',
                'received_from_desc',
                'transferred_to',
                'transferred_to_desc',
            ] as $field) {
                $this->{$resource . '_' . $field} = null;
            }
        }

        foreach ($this->_transactionModels as $transaction) {
            $field = $transaction->resource_type . '_'
                . (
                    $transaction->transaction_type === FuelTransaction::TYPE_RECEIVE
                    ? 'received'
                    : 'transferred'
                );

            $this->$field = $this->money(
                (float) $this->$field + (float) $transaction->amount
            );
        }

        $this->fuel_balance_difference = '0.00';
        $this->water_balance_difference = '0.00';
    }

    public function saveWithDetails()
    {
        $attributes = $this->getAttributes();
        $oldAttributes = $this->getOldAttributes();
        $transaction = null;

        try {
            $transaction = static::getDb()->beginTransaction();

            $vessel = $this->lockVessel();

            $this->assertSupportedLedger();

            if (!$this->validate()) {
                throw new \DomainException('validation');
            }

            if ($this->isNewRecord) {
                $this->created_by = $this->currentUserId();
            }

            if (!$this->save(false)) {
                $this->fail('ذخیره گزارش مصرف انجام نشد.');
            }

            $this->saveItems();
            $this->saveTransactions();
            $this->recalculateBalances($vessel);

            $transaction->commit();
        } catch (\Throwable $exception) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }

            $this->setAttributes($attributes, false);
            $this->setOldAttributes($oldAttributes);

            $this->_itemModels = [];
            $this->_transactionModels = [];

            if (!($exception instanceof \DomainException)) {
                Yii::error($exception, __METHOD__);

                $this->addError(
                    'details',
                    'ذخیره انجام نشد؛ هیچ‌‌کدام از تغییرات ثبت نشد.'
                );
            } elseif (!$this->hasErrors()) {
                $this->addError(
                    'details',
                    'ذخیره گزارش به دلیل نامعتبر بودن اطلاعات انجام نشد.'
                );
            }

            return false;
        }

        $this->_submitted = false;
        $this->_itemInput = [];
        $this->_transactionInput = [];
        $this->_itemModels = [];
        $this->_transactionModels = [];

        unset($this->items, $this->transactions);

        try {
            $this->refresh();
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__ . ':refresh');
        }

        return true;
    }

    private function lockVessel()
    {
        $id = ConsumptionInput::normalize($this->vesselIdRef);

        if (!$id) {
            $this->fail('شناور انتخاب‌شده معتبر نیست.', 'vesselIdRef');
        }

        $row = static::getDb()->createCommand(
            'SELECT [[id]], [[is_deleted]], [[initial_fuel_balance]], [[initial_water_balance]]
             FROM {{vessel_table_vessel}} WHERE [[id]] = :id FOR UPDATE',
            [':id' => $id]
        )->queryOne();

        if (!$row || (bool) $row['is_deleted']) {
            $this->fail('شناور معتبر یا فعال نیست.', 'vesselIdRef');
        }

        return $row;
    }

//    private function assertSupportedLedger()
//    {
//        $unsupported = FuelTransaction::find()
//            ->where([
//                'equipment_type' => FuelTransaction::EQUIPMENT_TYPE_VESSEL,
//                'equipment_id' => $this->vesselIdRef,
//            ])
//            ->andWhere([
//                'or',
//                ['consumption_id' => null],
//                ['not', ['counterparty_vessel_id' => null]],
//                ['not', ['related_transfer_id' => null]],
//            ])
//            ->exists();
//
//        if ($unsupported) {
//            $this->fail('این شناور تراکنش مستقل یا انتقال داخلی دارد.');
//        }
//    }
    
    private function assertSupportedLedger()
{
    // فقط رکوردهای بدون consumption_id را بررسی کن
    // (رکوردهایی که مستقل ثبت شده‌اند و به گزارش وابسته نیستند)
    $unsupported = FuelTransaction::find()
        ->where([
            'equipment_type' => FuelTransaction::EQUIPMENT_TYPE_VESSEL,
            'equipment_id' => $this->vesselIdRef,
            'consumption_id' => null,   // فقط مستقل‌ها
        ])
        ->exists();

    if ($unsupported) {
        $this->fail('این شناور تراکنش مستقل دارد و قابل ویرایش نیست.');
    }
}

    private function saveItems()
    {
        foreach ($this->_itemModels as $item) {
            $item->bindReport($this);

            if (!$item->save(false)) {
                $this->copyErrors($item, 'details');
                $this->fail('ذخیره جزئیات مصرف تجهیز انجام نشد.');
            }
        }
    }

private function saveTransactions()
{
    $keep = [];

    foreach ($this->_transactionModels as $model) {
        $model->consumption_id = $this->id;

        if (!$model->save()) {
            $this->copyErrors($model, 'transactions');
            $this->fail('ذخیره جزئیات دریافت یا انتقال انجام نشد.');
        }

        $keep[] = $model->id;

        // ⭐ ساخت رکورد دوطرفه
        $this->createCounterpartyRecord($model, $keep);
    }

    // حذف رکوردهای قدیمی
    $condition = [
        'consumption_id' => $this->id,
        'equipment_type' => FuelTransaction::EQUIPMENT_TYPE_VESSEL,
        'equipment_id' => $this->vesselIdRef,
    ];

    if ($keep) {
        $condition = ['and', $condition, ['not in', 'id', $keep]];
    }

    FuelTransaction::deleteAll($condition);
}

/**
 * ساخت رکورد دوطرفه برای طرف مقابل
 */
private function createCounterpartyRecord(FuelTransaction $main, array &$keep)
{
    // استخراج طرف مقابل از from_source یا to_destination
    $partyField = $main->transaction_type === FuelTransaction::TYPE_RECEIVE
        ? 'from_source'
        : 'to_destination';
    
    $partyValue = (string) $main->$partyField;
    
    // اگر طرف مقابل "other" یا متن آزاد است، رکورد دوطرفه نمی‌سازیم
    if ($partyValue === '' || $partyValue === 'other' || strpos($partyValue, ':') === false) {
        return;
    }
    
    list($partyType, $partyId) = explode(':', $partyValue, 2);
    $partyId = (int) $partyId;
    
    if (!in_array($partyType, ['rig', 'vessel'], true) || $partyId < 1) {
        return;
    }
    
    // آیا رکورد دوطرفه قبلاً وجود دارد؟ (حالت ویرایش)
    $reverse = FuelTransaction::findOne([
        'related_transfer_id' => $main->id,
        'equipment_type' => $partyType,
        'equipment_id' => $partyId,
    ]);
    
    if (!$reverse) {
        $reverse = new FuelTransaction();
    }
    
    $reverse->consumption_id   = $this->id;
    $reverse->equipment_type   = $partyType;
    $reverse->equipment_id     = $partyId;
    $reverse->resource_type    = $main->resource_type;
    $reverse->transaction_type = $main->transaction_type === FuelTransaction::TYPE_RECEIVE
        ? FuelTransaction::TYPE_TRANSFER
        : FuelTransaction::TYPE_RECEIVE;
    $reverse->amount           = $main->amount;
    $reverse->transaction_date = $main->transaction_date;
    $reverse->occurred_at      = $main->occurred_at;
    $reverse->description      = 'رکورد خودکار سیستم مرتبط با تراکنش #' . $main->id;
    
    // فیلد مبدأ/مقصد رکورد معکوس
    if ($reverse->transaction_type === FuelTransaction::TYPE_TRANSFER) {
        // دکل/شناور در حال انتقال (خروج)
        $reverse->to_destination = 'vessel:' . $this->vesselIdRef;
        $reverse->from_source = null;
    } else {
        // دکل/شناور در حال دریافت (ورود)
        $reverse->from_source = 'vessel:' . $this->vesselIdRef;
        $reverse->to_destination = null;
    }
    
    $reverse->counterparty_vessel_id = $this->vesselIdRef;
    $reverse->related_transfer_id = $main->id;
    $reverse->created_by = $this->currentUserId();
    
    if (!$reverse->save(false)) {
        throw new \DomainException('ذخیره رکورد دوطرفه انجام نشد.');
    }
    
    // لینک دوطرفه: به‌روزرسانی رکورد اصلی
    $main->related_transfer_id = $reverse->id;
    $main->save(false);
    
    $keep[] = $reverse->id;
}

    private function recalculateBalances(array $vessel)
    {
        $plan = $this->buildBalancePlan($vessel);

        foreach ($plan as $row) {
            static::getDb()->createCommand()->update(
                static::tableName(),
                $row['balances'],
                [
                    'id' => $row['id'],
                    'vesselIdRef' => $this->vesselIdRef,
                ]
            )->execute();
        }
    }

    private function buildBalancePlan(array $vessel)
    {
        $balances = [];

        foreach (['fuel', 'water'] as $resource) {
            $field = 'initial_' . $resource . '_balance';

            $value = ConsumptionInput::decimal(
                isset($vessel[$field]) ? $vessel[$field] : null
            );

            if ($value === null || (float) $value < 0) {
                $this->fail('موجودی پایه معتبر نیست.', 'vesselIdRef');
            }

            $balances[$resource] = (float) $value;
        }

        $query = self::find()
            ->select([
                'id',
                'started_at',
                'ended_at',
                'fuel_received',
                'fuel_used',
                'fuel_transferred',
                'water_received',
                'water_used',
                'water_transferred',
            ])
            ->where(['vesselIdRef' => $this->vesselIdRef]);

        if (!$this->isNewRecord) {
            $query->andWhere(['<>', 'id', $this->id]);
        }

        $records = $query->asArray()->all();

        foreach ($records as &$record) {
            $record['_current'] = false;
        }

        unset($record);

        $records[] = [
            'id' => $this->isNewRecord ? null : $this->id,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'fuel_received' => $this->fuel_received,
            'fuel_used' => $this->fuel_used,
            'fuel_transferred' => $this->fuel_transferred,
            'water_received' => $this->water_received,
            'water_used' => $this->water_used,
            'water_transferred' => $this->water_transferred,
            '_current' => true,
        ];

        usort($records, function ($first, $second) {
            return strcmp((string) $first['started_at'], (string) $second['started_at']);
        });

        $plan = [];
        $currentBalances = [];

        foreach ($records as $record) {
            $fields = [];

            foreach (['fuel', 'water'] as $resource) {
                $amounts = [
                    'received' => (float) $record[$resource . '_received'],
                    'used' => (float) $record[$resource . '_used'],
                    'transferred' => (float) $record[$resource . '_transferred'],
                ];

                $opening = $balances[$resource];
                $closing = round($opening + $amounts['received'] - $amounts['used'] - $amounts['transferred'], 2);

                $fields[$resource . '_opening_balance'] = $this->money($opening);
                $fields[$resource . '_closing_balance'] = $this->money($closing);

                $balances[$resource] = $closing;
            }

            if ($record['_current']) {
                $currentBalances = $fields;
            }

            $plan[] = [
                'id' => $record['id'],
                'balances' => $fields,
            ];
        }

        foreach ($currentBalances as $field => $value) {
            $this->$field = $value;
        }

        return $plan;
    }

    public function calculatePreview()
    {
        if (!$this->validate()) {
            return false;
        }

        try {
            $vessel = VesselType::find()
                ->select(['id', 'initial_fuel_balance', 'initial_water_balance'])
                ->where(['id' => $this->vesselIdRef, 'is_deleted' => 0])
                ->asArray()
                ->one();

            if (!$vessel) {
                $this->fail('شناور پیدا نشد.', 'vesselIdRef');
            }

            $this->assertSupportedLedger();
            $this->buildBalancePlan($vessel);

            return true;
        } catch (\DomainException $exception) {
            return false;
        }
    }

    public function getItemModels()
    {
        if ($this->_submitted) {
            return $this->_itemModels;
        }

        return $this->isNewRecord ? [] : $this->getItems()->all();
    }

    public function getTransactionModels()
    {
        if ($this->_submitted) {
            return $this->_transactionModels;
        }

        return $this->isNewRecord ? [] : $this->getTransactions()->all();
    }

    public function getEquipmentRows()
    {
        $rows = ['engines' => [], 'generators' => []];
        $vesselId = ConsumptionInput::normalize($this->vesselIdRef);

        if (!$vesselId) {
            return $rows;
        }

        $rows['engines'] = VesselEngine::find()
            ->where(['vessel_id' => $vesselId, 'is_deleted' => 0])
            ->orderBy(['engine_number' => SORT_ASC])
            ->all();

        $rows['generators'] = VesselGenerator::find()
            ->where(['vessel_id' => $vesselId, 'is_deleted' => 0])
            ->orderBy(['generator_number' => SORT_ASC])
            ->all();

        return $rows;
    }

    public function getFormData()
    {
        return [
            'vessels' => VesselType::getDropdownList(),
            'locations' => LocationType::getLocationList(),
            'activities' => ActivityType::getActivityList(),
        ];
    }

    public function getDurationMinutes()
    {
        $start = $this->parseDateTime($this->started_at);
        $end = $this->parseDateTime($this->ended_at);

        return $start && $end
            ? (int) (($end->getTimestamp() - $start->getTimestamp()) / 60)
            : 0;
    }

    public function getConsumptionSummary()
    {
        return [
            'duration_minutes' => $this->getDurationMinutes(),
            'equipment_hours' => $this->operating_hours,
            'actual_consumption' => $this->fuel_used,
            'ideal_consumption' => $this->total_ideal_consumption,
            'difference' => $this->consumption_difference,
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
            $result[$field] = $this->{$resource . '_' . $field};
        }

        return $result;
    }

    private function parseDateTime($value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            new \DateTimeZone(self::TIME_ZONE)
        );

        return $date && $date->format('Y-m-d H:i:s') === $value
            ? $date
            : null;
    }

    private function money($value)
    {
        return number_format(
            round((float) $value, 2, PHP_ROUND_HALF_UP),
            2,
            '.',
            ''
        );
    }

    private function currentUserId()
    {
        return Yii::$app->user->isGuest ? null : Yii::$app->user->id;
    }

    private function copyErrors($model, $prefix)
    {
        foreach ($model->getErrors() as $attribute => $messages) {
            foreach ($messages as $message) {
                $this->addError(
                    $prefix . '[' . $attribute . ']',
                    $message
                );
            }
        }
    }

    private function fail($message, $attribute = 'details')
    {
        $this->addError($attribute, $message);
        throw new \DomainException($message);
    }

    public function getItems()
    {
        return $this->hasMany(
            VesselConsumptionItem::class,
            ['consumption_id' => 'id']
        )->orderBy(['id' => SORT_ASC]);
    }

    public function getTransactions()
    {
        return $this->hasMany(
            FuelTransaction::class,
            ['consumption_id' => 'id']
        )
            ->andWhere([
                'equipment_type' => FuelTransaction::EQUIPMENT_TYPE_VESSEL,
                'equipment_id' => $this->vesselIdRef,
            ])
            ->orderBy([
                'occurred_at' => SORT_ASC,
                'id' => SORT_ASC,
            ]);
    }

    public static function getStatusOptions()
    {
        return VesselType::getStatusOptions();
    }

    public function loadCreateDefaults($vesselId = null)
    {
        $this->loadDefaultValues();

        if ($vesselId !== null && $vesselId !== '') {
            $vessel = VesselType::findOne([
                'id' => $vesselId,
                'is_deleted' => 0,
            ]);

            if ($vessel) {
                $this->vesselIdRef = $vessel->id;
                $this->status = $vessel->status;
            }
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TIME_ZONE));

        $this->jalaliDate = ConsumptionInput::toJalali($now->format('Y-m-d'));
        $this->start_time = '';
        $this->end_time = '';
        $this->endOfDay = '0';
        $this->waterUsed = 0;
    }
/**
 * شناور مربوط به گزارش مصرف.
 *
 * vessel_table_vessel.id
 *     ← vessel_fuel_consumption_table_vessel_fuel_consumption.vesselIdRef
 */
    public function getVessel()
    {
        return $this->hasOne(
            \app\modules\fuel\models\VesselType::class,
            ['id' => 'vesselIdRef']
        );
    }
    public function getLocation()
    {
        return $this->hasOne(
            \app\modules\fuel\models\LocationType::class,
            ['id' => 'locationIdRef']
        );
    }
    public function getActivity()
    {
        return $this->hasOne(
            \app\modules\fuel\models\ActivityType::class,
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