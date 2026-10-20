<?php

namespace app\modules\fuel\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class VesselType extends ActiveRecord
{
    const STATUS_ON_HIRE = 'on_hire';
    const STATUS_OFF_HIRE = 'off_hire';

    const MAX_EQUIPMENT = 5;

    public $image_file;

    public static function tableName()
    {
        return 'vessel_table_vessel';
    }

    public function rules()
    {
        return [
            [['name', 'code', 'description'], 'trim'],
            [['name', 'status'], 'required'],
            [['name'], 'string', 'max' => 100],
            [['code'], 'string', 'max' => 50],
            [['description'], 'string'],
            [['status'], 'in', 'range' => array_keys(self::getStatusOptions())],
            [['engine_count', 'generator_count'], 'default', 'value' => 0],
            [['engine_count', 'generator_count'], 'integer', 'min' => 0, 'max' => self::MAX_EQUIPMENT],
            [['has_bow_thruster'], 'default', 'value' => 0],
            [['has_bow_thruster'], 'in', 'range' => [0, 1, '0', '1'], 'strict' => true, 'message' => 'وضعیت وجود بوتراستر معتبر نیست.'],
            [['initial_fuel_balance', 'initial_water_balance'], 'default', 'value' => 0],
            [['vessel_speed'], 'default', 'value' => null],
            [['vessel_speed', 'initial_fuel_balance', 'initial_water_balance'], 'number', 'min' => 0, 'max' => 99999999.99],
            [['vessel_speed', 'initial_fuel_balance', 'initial_water_balance'], 'match', 'pattern' => '/^\d+(?:\.\d{1,2})?$/D', 'message' => 'مقدار را با حداکثر دو رقم اعشار وارد کنید.'],
            [['image_file'], 'image', 'extensions' => ['jpg', 'jpeg', 'png', 'gif'], 'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'], 'maxSize' => 5 * 1024 * 1024, 'skipOnEmpty' => true],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه',
            'name' => 'نام شناور',
            'code' => 'کد شناور',
            'engine_count' => 'تعداد موتورها',
            'generator_count' => 'تعداد ژنراتورها',
            'has_bow_thruster' => 'بوتراستر',
            'vessel_speed' => 'سرعت شناور (نات)',
            'status' => 'وضعیت',
            'initial_fuel_balance' => 'موجودی اولیه سوخت (لیتر)',
            'initial_water_balance' => 'موجودی اولیه آب (لیتر)',
            'description' => 'توضیحات',
            'image_file' => 'تصویر شناور',
            'vessel_image' => 'تصویر شناور',
            'is_deleted' => 'حذف شده',
            'created_by' => 'ایجادکننده',
            'created_at' => 'زمان ایجاد',
            'updated_by' => 'ویرایش‌کننده',
            'updated_at' => 'زمان ویرایش',
        ];
    }

    public static function find()
    {
        return parent::find()->andWhere([self::tableName() . '.is_deleted' => 0]);
    }

    public function getEngines()
    {
        return $this->hasMany(VesselEngine::class, ['vessel_id' => 'id'])
            ->andOnCondition([VesselEngine::tableName() . '.is_deleted' => 0])
            ->orderBy(['engine_number' => SORT_ASC]);
    }

    public function getGenerators()
    {
        return $this->hasMany(VesselGenerator::class, ['vessel_id' => 'id'])
            ->andOnCondition([VesselGenerator::tableName() . '.is_deleted' => 0])
            ->orderBy(['generator_number' => SORT_ASC]);
    }

    public function getBowThruster()
    {
        return $this->hasOne(VesselBowThruster::class, ['vessel_id' => 'id'])
            ->andOnCondition([VesselBowThruster::tableName() . '.is_deleted' => 0]);
    }

    public function getFuelConsumptionModes()
    {
        return $this->hasMany(VesselFuelMode::class, ['vessel_id' => 'id'])
            ->andOnCondition([VesselFuelMode::tableName() . '.is_deleted' => 0]);
    }

    public function getCreatedByUser()
    {
        return $this->hasOne(\app\modules\account\models\User::class, ['UserID' => 'created_by']);
    }

    public function getFormData()
    {
        $engines = [];
        $generators = [];
        $modes = [];
        $bowThruster = [];

        if (!$this->isNewRecord) {
            foreach ($this->engines as $engine) {
                $engines[(int) $engine->engine_number] = $engine->getAttributes([
                    'id', 'engine_number', 'engine_power', 'engine_type', 'engine_year',
                ]);
            }

            foreach ($this->generators as $generator) {
                $generators[(int) $generator->generator_number] = $generator->getAttributes([
                    'id', 'generator_number', 'gen_power', 'gen_type', 'gen_year',
                ]);
            }

            $engineNumbers = ArrayHelper::map($engines, 'id', 'engine_number');
            $generatorNumbers = ArrayHelper::map($generators, 'id', 'generator_number');

            foreach ($this->fuelConsumptionModes as $mode) {
                if ($mode->engine_id !== null && isset($engineNumbers[$mode->engine_id])) {
                    $key = 'engine_' . $engineNumbers[$mode->engine_id];
                } elseif ($mode->generator_id !== null && isset($generatorNumbers[$mode->generator_id])) {
                    $key = 'generator_' . $generatorNumbers[$mode->generator_id];
                } else {
                    continue;
                }

                $modes[$mode->mode][$key] = [
                    'ideal' => $mode->ideal_consumption,
                    'tested' => $mode->tested_consumption,
                ];
            }

            $record = VesselBowThruster::find()->where(['vessel_id' => $this->id, 'is_deleted' => 0])->one();

            if ($record !== null) {
                $bowThruster = $record->getAttributes([
                    'id', 'power_kw', 'engine_type', 'manufacture_year',
                ]);
            }
        }

        return [
            'model' => $this,
            'existingEngines' => $engines,
            'existingGenerators' => $generators,
            'existingModes' => $modes,
            'existingBowThruster' => $bowThruster,
        ];
    }

    public function saveWithDetails(array $data)
    {
        if (!$this->validate()) {
            return false;
        }

        $attributes = $this->getAttributes();
        $oldAttributes = $this->getOldAttributes();

        $transaction = null;
        $uploadedPath = null;

        try {
            $enginesData = $this->readEquipmentData($data, 'VesselEngine', 'engine_number', (int) $this->engine_count);
            $generatorsData = $this->readEquipmentData($data, 'VesselGenerator', 'generator_number', (int) $this->generator_count);
            $bowThrusterData = $this->readBowThrusterData($data);
            $modesData = $this->readModeData($data);

            $transaction = static::getDb()->beginTransaction();

            if (!$this->isNewRecord) {
                $lockedRow = $this->lockRecord();
                $this->assertInitialBalancesUnchanged($lockedRow);
            }

            $userId = $this->currentUserId();

            if ($this->isNewRecord) {
                $this->created_by = $userId;
                $this->is_deleted = 0;
            }

            $this->has_bow_thruster = (int) $this->has_bow_thruster;
            $this->updated_by = $userId;

            if (!$this->save(false)) {
                $this->fail('ذخیره اطلاعات اصلی شناور انجام نشد.');
            }

            $engines = $this->syncEquipment(
                VesselEngine::class,
                'engine_number',
                ['engine_power', 'engine_type', 'engine_year'],
                $enginesData,
                'موتور'
            );

            $generators = $this->syncEquipment(
                VesselGenerator::class,
                'generator_number',
                ['gen_power', 'gen_type', 'gen_year'],
                $generatorsData,
                'ژنراتور'
            );

            $bowThruster = $this->syncBowThruster($bowThrusterData);
            $this->syncFuelModes($modesData, $engines, $generators);
            $this->saveImage($uploadedPath);

            $transaction->commit();

            $this->populateRelation('engines', array_values($engines));
            $this->populateRelation('generators', array_values($generators));
            $this->populateRelation('bowThruster', $bowThruster);
            unset($this->fuelConsumptionModes);

            return true;

        } catch (\Throwable $e) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }

            if ($uploadedPath !== null && is_file($uploadedPath) && !@unlink($uploadedPath)) {
                Yii::warning('Unable to remove vessel upload: ' . $uploadedPath, __METHOD__);
            }

            $this->setAttributes($attributes, false);
            $this->setOldAttributes($oldAttributes);

            if (!$e instanceof \DomainException) {
                Yii::error($e, __METHOD__);
                $this->addError('details', 'ذخیره اطلاعات انجام نشد. دوباره تلاش کنید.');
            }

            return false;
        }
    }

    private function readEquipmentData(array $data, $key, $numberField, $count)
    {
        $rows = array_key_exists($key, $data) ? $data[$key] : [];

        if (!is_array($rows) || count($rows) !== $count) {
            $this->fail('تعداد ردیف‌های تجهیزات با تعداد انتخاب‌شده مطابقت ندارد.');
        }

        foreach ($rows as $number => $row) {
            if (!preg_match('/^[1-5]$/D', (string) $number) || (int) $number > $count || !is_array($row)) {
                $this->fail('ساختار اطلاعات تجهیزات معتبر نیست.');
            }

            if (!isset($row[$numberField]) || !is_scalar($row[$numberField]) || (string) $row[$numberField] !== (string) $number) {
                $this->fail('شماره تجهیز معتبر نیست.');
            }
        }

        for ($number = 1; $number <= $count; $number++) {
            if (!isset($rows[$number])) {
                $this->fail('اطلاعات یکی از تجهیزات ارسال نشده است.');
            }
        }

        ksort($rows);
        return $rows;
    }

    private function readBowThrusterData(array $data)
    {
        if ((int) $this->has_bow_thruster !== 1) {
            return null;
        }

        if (!array_key_exists('VesselBowThruster', $data) || !is_array($data['VesselBowThruster'])) {
            $this->fail('مشخصات بوتراستر ارسال نشده یا ساختار آن معتبر نیست.', 'has_bow_thruster');
        }

        $row = $data['VesselBowThruster'];
        $allowedFields = ['id', 'power_kw', 'engine_type', 'manufacture_year'];

        if (array_diff(array_keys($row), $allowedFields)) {
            $this->fail('فیلدهای ارسالی بوتراستر معتبر نیستند.', 'has_bow_thruster');
        }

        foreach ($row as $value) {
            if ($value !== null && !is_scalar($value)) {
                $this->fail('ساختار مقادیر بوتراستر معتبر نیست.', 'has_bow_thruster');
            }
        }

        return $row;
    }

    private function syncBowThruster($row)
    {
        $record = VesselBowThruster::find()->where(['vessel_id' => $this->id])->one();

        if ($row === null) {
            if ($record !== null && !(bool) $record->is_deleted) {
                $record->is_deleted = 1;
                $record->updated_by = $this->currentUserId();

                if (!$record->save(false, ['is_deleted', 'updated_by', 'updated_at'])) {
                    $this->fail('غیرفعال‌سازی بوتراستر انجام نشد.');
                }
            }

            return null;
        }

        if ($record === null) {
            $record = new VesselBowThruster();
        }

        if (array_key_exists('id', $row) && $row['id'] !== '' && $row['id'] !== null) {
            if (!preg_match('/^[1-9]\d*$/D', (string) $row['id']) || $record->isNewRecord || (string) $record->id !== (string) $row['id']) {
                $this->fail('شناسه بوتراستر معتبر نیست.', 'has_bow_thruster');
            }
        }

        if ($record->isNewRecord) {
            $record->vessel_id = $this->id;
            $record->created_by = $this->currentUserId();
        }

        foreach (['power_kw', 'engine_type', 'manufacture_year'] as $field) {
            $record->$field = array_key_exists($field, $row) ? $row[$field] : null;
        }

        $record->is_deleted = 0;
        $record->updated_by = $this->currentUserId();

        $this->saveDetail($record, 'بوتراستر');

        return $record;
    }

    private function readModeData(array $data)
    {
        $rows = array_key_exists('VesselFuelConsumptionMode', $data) ? $data['VesselFuelConsumptionMode'] : [];
        $expected = [];

        if (!is_array($rows)) {
            $this->fail('ساختار نرخ‌های مصرف معتبر نیست.');
        }

        for ($number = 1; $number <= (int) $this->engine_count; $number++) {
            $expected[] = 'engine_' . $number;
        }

        for ($number = 1; $number <= (int) $this->generator_count; $number++) {
            $expected[] = 'generator_' . $number;
        }

        $validModes = array_keys(VesselFuelMode::getModeOptions());

        if (array_diff(array_keys($rows), $validModes)) {
            $this->fail('حالت عملیاتی معتبر نیست.');
        }

        if (!$expected) {
            if ($rows) {
                $this->fail('برای شناور بدون تجهیزات نباید نرخ تجهیز ارسال شود.');
            }

            return [];
        }

        foreach ($validModes as $mode) {
            if (!isset($rows[$mode]) || !is_array($rows[$mode])) {
                $this->fail('اطلاعات حالت‌های عملیاتی کامل نیست.');
            }

            $keys = array_keys($rows[$mode]);

            if (array_diff($expected, $keys) || array_diff($keys, $expected)) {
                $this->fail('نرخ‌های مصرف با تجهیزات انتخاب‌شده مطابقت ندارند.');
            }

            foreach ($rows[$mode] as $item) {
                if (!is_array($item)) {
                    $this->fail('ساختار نرخ مصرف معتبر نیست.');
                }
                foreach (['ideal', 'tested'] as $field) {
                    $val = array_key_exists($field, $item) ? $item[$field] : null;
                    if ($val !== null && !is_scalar($val)) {
                        $this->fail('مقدار نرخ مصرف معتبر نیست.');
                    }
                }
            }
        }

        return $rows;
    }

    private function syncEquipment($class, $numberField, array $fields, array $rows, $label)
    {
        $records = $class::find()->where(['vessel_id' => $this->id])->indexBy($numberField)->all();
        $active = [];

        foreach ($rows as $number => $row) {
            $record = isset($records[$number]) ? $records[$number] : new $class();

            if (array_key_exists('id', $row) && $row['id'] !== '' && $row['id'] !== null) {
                if (!is_scalar($row['id']) || !preg_match('/^[1-9]\d*$/D', (string) $row['id']) || $record->isNewRecord || (string) $record->id !== (string) $row['id']) {
                    $this->fail('شناسه ' . $label . ' شماره ' . $number . ' معتبر نیست.');
                }
            }

            if ($record->isNewRecord) {
                $record->vessel_id = $this->id;
                $record->$numberField = $number;
                $record->created_by = $this->currentUserId();
            }

            foreach ($fields as $field) {
                $value = array_key_exists($field, $row) ? $row[$field] : null;

                if ($value !== null && !is_scalar($value)) {
                    $this->fail('اطلاعات ' . $label . ' شماره ' . $number . ' معتبر نیست.');
                }

                $record->$field = $value;
            }

            $record->is_deleted = 0;
            $this->saveDetail($record, $label . ' شماره ' . $number);
            $active[(int) $number] = $record;
        }

        foreach ($records as $number => $record) {
            if (!isset($active[$number]) && !(bool) $record->is_deleted) {
                $record->is_deleted = 1;
                $this->saveDetail($record, $label . ' شماره ' . $number);
            }
        }

        return $active;
    }

    private function syncFuelModes(array $rows, array $engines, array $generators)
    {
        $records = VesselFuelMode::find()->where(['vessel_id' => $this->id])->all();
        $indexed = [];
        $used = [];

        foreach ($records as $record) {
            $type = $record->engine_id !== null ? 'engine' : 'generator';
            $id = $record->engine_id !== null ? $record->engine_id : $record->generator_id;
            $indexed[$type . ':' . $id . ':' . $record->mode] = $record;
        }

        foreach ($rows as $mode => $items) {
            foreach ($items as $key => $values) {
                $ideal = isset($values['ideal']) && $values['ideal'] !== '' ? $values['ideal'] : null;
                $tested = isset($values['tested']) && $values['tested'] !== '' ? $values['tested'] : null;

                if ($ideal === null && $tested === null) {
                    continue;
                }

                list($type, $number) = explode('_', $key, 2);

                $equipment = $type === 'engine' ? $engines[(int) $number] : $generators[(int) $number];
                $index = $type . ':' . $equipment->id . ':' . $mode;

                $record = isset($indexed[$index]) ? $indexed[$index] : new VesselFuelMode();

                if ($record->isNewRecord) {
                    $record->vessel_id = $this->id;
                    $record->mode = $mode;
                    $record->engine_id = $type === 'engine' ? $equipment->id : null;
                    $record->generator_id = $type === 'generator' ? $equipment->id : null;
                    $record->created_by = $this->currentUserId();
                }

                $record->engine_number = $type === 'engine' ? (int) $number : null;
                $record->generator_number = $type === 'generator' ? (int) $number : null;
                $record->ideal_consumption = $ideal;
                $record->tested_consumption = $tested;
                $record->is_deleted = 0;
                $record->updated_by = $this->currentUserId();

                $label = ($type === 'engine' ? 'موتور ' : 'ژنراتور ') . $number;
                $this->saveDetail($record, 'نرخ ' . $label . ' در حالت ' . $mode);

                $used[$record->id] = true;
            }
        }

        foreach ($records as $record) {
            if (!isset($used[$record->id]) && !(bool) $record->is_deleted) {
                $record->is_deleted = 1;
                $record->updated_by = $this->currentUserId();
                $this->saveDetail($record, 'غیرفعال‌‌سازی نرخ مصرف');
            }
        }
    }

    private function saveDetail(ActiveRecord $record, $label)
    {
        if (!$record->save()) {
            $messages = array_values($record->getFirstErrors());
            $this->fail($label . ': ' . ($messages ? implode(' ـ ', $messages) : 'ذخیره انجام نشد.'));
        }
    }

    private function saveImage(&$uploadedPath)
    {
        if (!$this->image_file instanceof UploadedFile) {
            return;
        }

        $directory = Yii::getAlias('@webroot/uploads/vessels');

        if (!FileHelper::createDirectory($directory, 0755)) {
            $this->fail('ایجاد پوشه تصاویر امکان‌پذیر نیست.', 'image_file');
        }

        $name = Yii::$app->security->generateRandomString(32) . '.' . strtolower($this->image_file->extension);
        $uploadedPath = $directory . DIRECTORY_SEPARATOR . $name;

        if (!$this->image_file->saveAs($uploadedPath, false)) {
            $this->fail('ذخیره تصویر انجام نشد.', 'image_file');
        }

        $this->vessel_image = rtrim(Yii::getAlias('@web'), '/') . '/uploads/vessels/' . $name;

        if (!$this->save(false, ['vessel_image'])) {
            $this->fail('ذخیره مسیر تصویر انجام نشد.', 'image_file');
        }
    }

    private function lockRecord()
    {
        $db = static::getDb();
        $table = $db->quoteTableName(static::tableName());

        $row = $db->createCommand(
            'SELECT [[id]], [[is_deleted]], [[initial_fuel_balance]], [[initial_water_balance]] FROM ' . $table . ' WHERE [[id]] = :id FOR UPDATE',
            [':id' => $this->id]
        )->queryOne();

        if (!$row || (bool) $row['is_deleted']) {
            $this->fail('شناور حذف شده یا دیگر در دسترس نیست.');
        }

        return $row;
    }

    private function assertInitialBalancesUnchanged(array $lockedRow)
    {
        $fields = ['initial_fuel_balance' => 'سوخت', 'initial_water_balance' => 'آب'];
        $changed = [];

        foreach ($fields as $field => $label) {
            $submitted = number_format((float) $this->$field, 2, '.', '');
            $stored = number_format((float) $lockedRow[$field], 2, '.', '');

            if ($submitted !== $stored) {
                $changed[$field] = $label;
            }
        }

        if (!$changed) {
            return;
        }

        $db = static::getDb();
        $table = $db->quoteTableName(VesselFuelConsumption::tableName());

        $reportId = $db->createCommand(
            'SELECT [[id]] FROM ' . $table . ' WHERE [[vesselIdRef]] = :vesselId LIMIT 1 FOR UPDATE',
            [':vesselId' => $this->id]
        )->queryScalar();

        if ($reportId === false) {
            return;
        }

        foreach ($changed as $field => $label) {
            $this->addError($field, 'به دلیل وجود گزارش مصرف، تغییر موجودی پایه ' . $label . ' مجاز نیست.');
        }

        throw new \DomainException('تغییر موجودی پایه شناور دارای گزارش مصرف مجاز نیست.');
    }

    private function currentUserId()
    {
        return Yii::$app->user->isGuest ? null : Yii::$app->user->id;
    }

    private function fail($message, $attribute = 'details')
    {
        $this->addError($attribute, $message);
        throw new \DomainException($message);
    }

    public function archive()
    {
        if ($this->isNewRecord) {
            return false;
        }

        $transaction = null;
        $attributes = $this->getAttributes();
        $oldAttributes = $this->getOldAttributes();

        try {
            $transaction = static::getDb()->beginTransaction();
            $this->lockRecord();

            $this->is_deleted = 1;
            $this->updated_by = $this->currentUserId();

            if (!$this->save(false, ['is_deleted', 'updated_by'])) {
                $this->fail('حذف شناور انجام نشد.');
            }

            $transaction->commit();
            return true;
        } catch (\Throwable $e) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }

            $this->setAttributes($attributes, false);
            $this->setOldAttributes($oldAttributes);

            if (!$e instanceof \DomainException) {
                Yii::error($e, __METHOD__);
                $this->addError('details', 'حذف شناور انجام نشد.');
            }

            return false;
        }
    }

    public function getCurrentFuelBalance()
    {
        return $this->getCurrentBalance('fuel_closing_balance', 'initial_fuel_balance');
    }

    public function getCurrentWaterBalance()
    {
        return $this->getCurrentBalance('water_closing_balance', 'initial_water_balance');
    }

    private function getCurrentBalance($column, $initialColumn)
    {
        if ($this->isNewRecord) {
            return $this->$initialColumn;
        }

        $value = VesselFuelConsumption::find()
            ->select($column)
            ->where(['vesselIdRef' => $this->id])
            ->orderBy(['started_at' => SORT_DESC, 'id' => SORT_DESC])
            ->scalar();

        return $value === false || $value === null ? $this->$initialColumn : $value;
    }

    public function getCreatedAtJalali()
    {
        return $this->formatJalali($this->created_at);
    }

    public function getUpdatedAtJalali()
    {
        return $this->formatJalali($this->updated_at);
    }

    private function formatJalali($value)
    {
        if (!$value || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return '-';
        }

        $date = new \app\components\jDateTime(true, true, 'Asia/Tehran');
        $parts = $date->toJalali(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp));

        return sprintf('%04d/%02d/%02d %s', $parts[0], $parts[1], $parts[2], date('H:i', $timestamp));
    }

    public static function getStatusOptions()
    {
        return [
            self::STATUS_ON_HIRE => 'On Hire',
            self::STATUS_OFF_HIRE => 'Off Hire',
        ];
    }

    public static function getBowThrusterOptions()
    {
        return [
            0 => 'ندارد',
            1 => 'دارد',
        ];
    }

    public static function getDropdownList()
    {
        return ArrayHelper::map(
            self::find()->orderBy(['name' => SORT_ASC])->all(),
            'id',
            function ($model) {
                return $model->code !== null && $model->code !== ''
                    ? $model->name . ' (' . $model->code . ')'
                    : $model->name;
            }
        );
    }
}