<?php

namespace app\modules\fuel\models;

use Yii;
use yii\db\ActiveRecord;

class FuelTransaction extends ActiveRecord
{
    const EQUIPMENT_TYPE_RIG = 'rig';
    const EQUIPMENT_TYPE_VESSEL = 'vessel';
    const RESOURCE_TYPE_FUEL = 'fuel';
    const RESOURCE_TYPE_WATER = 'water';
    const TYPE_RECEIVE = 'receive';
    const TYPE_TRANSFER = 'transfer';

    public static function tableName()
    {
        return 'fuel_transaction_table_fuel_transaction';
    }

    public function rules()
    {
        return [
            [['consumption_id', 'equipment_type', 'equipment_id', 'resource_type', 'transaction_type', 'amount', 'transaction_date'], 'required'],
            [['consumption_id', 'equipment_id', 'created_by', 'counterparty_vessel_id', 'related_transfer_id'], 'integer', 'min' => 1],
            [['amount'], 'number', 'min' => 0.01],
            [['equipment_type'], 'in', 'range' => [self::EQUIPMENT_TYPE_RIG, self::EQUIPMENT_TYPE_VESSEL]],
            [['resource_type'], 'in', 'range' => [self::RESOURCE_TYPE_FUEL, self::RESOURCE_TYPE_WATER]],
            [['transaction_type'], 'in', 'range' => [self::TYPE_RECEIVE, self::TYPE_TRANSFER]],
            [['transaction_date'], 'date', 'format' => 'php:Y-m-d'],
            [['occurred_at'], 'date', 'format' => 'php:Y-m-d H:i:s'],
            [['from_source', 'to_destination'], 'string', 'max' => 100],
            [['description'], 'string'],
            [['from_source'], 'required', 'when' => function ($model) {
                return $model->transaction_type === self::TYPE_RECEIVE;
            }, 'whenClient' => false, 'message' => 'مبدأ دریافت الزامی است.'],
            [['to_destination'], 'required', 'when' => function ($model) {
                return $model->transaction_type === self::TYPE_TRANSFER;
            }, 'whenClient' => false, 'message' => 'مقصد انتقال الزامی است.'],
            [['to_destination'], 'validateReceiveDestination'],
            [['from_source'], 'validateTransferSource'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'شناسه تراکنش',
            'consumption_id' => 'رکورد مصرف',
            'equipment_type' => 'نوع تجهیز',
            'equipment_id' => 'تجهیز',
            'resource_type' => 'نوع منبع',
            'transaction_type' => 'نوع تراکنش',
            'amount' => 'مقدار',
            'from_source' => 'مبدأ دریافت',
            'to_destination' => 'مقصد انتقال',
            'transaction_date' => 'تاریخ تراکنش',
            'occurred_at' => 'زمان وقوع تراکنش',
            'description' => 'توضیحات',
            'created_by' => 'ثبت‌کننده',
        ];
    }

    public function validateReceiveDestination($attribute)
    {
        if ($this->transaction_type === self::TYPE_RECEIVE && !empty($this->to_destination)) {
            $this->addError($attribute, 'برای تراکنش دریافت، مقصد انتقال نباید ثبت شود.');
        }
    }

    public function validateTransferSource($attribute)
    {
        if ($this->transaction_type === self::TYPE_TRANSFER && !empty($this->from_source)) {
            $this->addError($attribute, 'برای تراکنش انتقال، مبدأ دریافت نباید ثبت شود.');
        }
    }

    public static function createReceive($consumptionId, $equipmentType, $equipmentId, $resourceType, $date, $amount, $source, $description)
    {
        return self::createTransaction([
            'consumption_id' => $consumptionId,
            'equipment_type' => $equipmentType,
            'equipment_id' => $equipmentId,
            'resource_type' => $resourceType,
            'transaction_type' => self::TYPE_RECEIVE,
            'amount' => $amount,
            'from_source' => $source,
            'to_destination' => null,
            'transaction_date' => $date,
            'occurred_at' => $date . ' 00:00:00',
            'description' => $description,
        ]);
    }

    public static function createTransfer($consumptionId, $equipmentType, $equipmentId, $resourceType, $date, $amount, $destination, $description)
    {
        return self::createTransaction([
            'consumption_id' => $consumptionId,
            'equipment_type' => $equipmentType,
            'equipment_id' => $equipmentId,
            'resource_type' => $resourceType,
            'transaction_type' => self::TYPE_TRANSFER,
            'amount' => $amount,
            'from_source' => null,
            'to_destination' => $destination,
            'transaction_date' => $date,
            'occurred_at' => $date . ' 00:00:00',
            'description' => $description,
        ]);
    }

    private static function createTransaction(array $attributes)
    {
        $transaction = new self();
        $transaction->setAttributes($attributes);
        $transaction->created_by = Yii::$app->user->isGuest ? null : Yii::$app->user->id;

        return $transaction;
    }
}