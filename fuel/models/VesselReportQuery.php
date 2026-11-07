<?php

namespace app\modules\fuel\models;

use yii\db\Expression;
use yii\db\Query;

/**
 * Query class for vessel fuel-consumption reports.
 *
 * tested  = مصرف تست‌شده = fuel_consumption_user_input (یا جمع actual_consumption آیتم‌ها)
 * ideal   = مصرف ایده‌آل = total_ideal_consumption
 * actual  = مقدار واقعی کاربر = جمع consumption_manual از آیتم‌ها
 */
class VesselReportQuery
{
    const CMP_TESTED_VS_ACTUAL = 'tested_vs_actual';
    const CMP_TESTED_VS_IDEAL = 'tested_vs_ideal';
    const CMP_IDEAL_VS_ACTUAL = 'ideal_vs_actual';

    public function getDashboardData(array $filters)
    {
        $mode = $filters['compare_mode'] ?? self::CMP_TESTED_VS_ACTUAL;

        return [
            'summary' => $this->getSummary($filters, $mode),
            'daily_consumption' => $this->getDailyConsumption($filters, $mode),
            'daily_difference' => $this->getDailyDifference($filters, $mode),
            'daily_operating_hours' => $this->getDailyOperatingHours($filters),
        ];
    }

    public function getSummary(array $filters, $mode = self::CMP_TESTED_VS_ACTUAL)
    {
        $row = $this->createBaseQuery($filters)
            ->select([
                'reports_count' => new Expression('COUNT(*)'),
                'report_days' => new Expression('COUNT(DISTINCT date)'),
                'total_operating_hours' => new Expression('COALESCE(SUM(operating_hours), 0)'),
                'tested_consumption' => new Expression('COALESCE(SUM(fuel_consumption_user_input), 0)'),
                'ideal_consumption' => new Expression('COALESCE(SUM(total_ideal_consumption), 0)'),
                'fuel_received' => new Expression('COALESCE(SUM(fuel_received), 0)'),
                'fuel_transferred' => new Expression('COALESCE(SUM(fuel_transferred), 0)'),
                'fuel_used' => new Expression('COALESCE(SUM(fuel_used), 0)'),
            ])
            ->asArray()
            ->one();

        $row = $row ?: [];

        $tested = (float) ($row['tested_consumption'] ?? 0);
        $ideal = (float) ($row['ideal_consumption'] ?? 0);
        $actual = $this->sumManualForFilters($filters);
        $diff = $this->calcDiff($mode, $tested, $ideal, $actual);
        $deviation = $this->calcDeviation($mode, $tested, $ideal, $actual, $diff);

        return [
            'reports_count' => (int) ($row['reports_count'] ?? 0),
            'report_days' => (int) ($row['report_days'] ?? 0),
            'total_operating_hours' => round((float) ($row['total_operating_hours'] ?? 0), 2),
            'tested_consumption' => round($tested, 2),
            'ideal_consumption' => round($ideal, 2),
            'actual_consumption' => round($actual, 2),
            'legacy_calculated' => round($tested, 2),
            'consumption_difference' => round($diff, 2),
            'deviation_percent' => round($deviation, 2),
            'diff_label' => $this->diffLabel($mode),
            'fuel_received' => round((float) ($row['fuel_received'] ?? 0), 2),
            'fuel_transferred' => round((float) ($row['fuel_transferred'] ?? 0), 2),
            'fuel_used' => round((float) ($row['fuel_used'] ?? 0), 2),
        ];
    }

    public function getDailyConsumption(array $filters, $mode = self::CMP_TESTED_VS_ACTUAL)
    {
        $headerRows = $this->createBaseQuery($filters)
            ->select([
                'report_date' => 'date',
                'tested_consumption' => new Expression('COALESCE(SUM(fuel_consumption_user_input), 0)'),
                'ideal_consumption' => new Expression('COALESCE(SUM(total_ideal_consumption), 0)'),
            ])
            ->groupBy(['date'])
            ->orderBy(['date' => SORT_ASC])
            ->asArray()
            ->all();

        $manualByDate = $this->manualByDate($filters);

        $result = [];
        foreach ($headerRows as $row) {
            $date = $row['report_date'];
            $tested = (float) $row['tested_consumption'];
            $ideal = (float) $row['ideal_consumption'];
            $actual = (float) ($manualByDate[$date] ?? 0);
            $result[] = [
                'report_date' => $date,
                'tested_consumption' => round($tested, 2),
                'ideal_consumption' => round($ideal, 2),
                'actual_consumption' => round($actual, 2),
                'consumption_difference' => round($this->calcDiff($mode, $tested, $ideal, $actual), 2),
            ];
        }

        return $result;
    }

    public function getDailyDifference(array $filters, $mode = self::CMP_TESTED_VS_ACTUAL)
    {
        $daily = $this->getDailyConsumption($filters, $mode);
        $result = [];
        foreach ($daily as $row) {
            $result[] = [
                'report_date' => $row['report_date'],
                'consumption_difference' => $row['consumption_difference'],
            ];
        }

        return $result;
    }

    public function getDailyOperatingHours(array $filters)
    {
        $rows = $this->createBaseQuery($filters)
            ->select([
                'report_date' => 'date',
                'operating_hours' => new Expression('COALESCE(SUM(operating_hours), 0)'),
            ])
            ->groupBy(['date'])
            ->orderBy(['date' => SORT_ASC])
            ->asArray()
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'report_date' => $row['report_date'],
                'operating_hours' => round((float) $row['operating_hours'], 2),
            ];
        }

        return $result;
    }

    /**
     * جمع consumption_manual آیتم‌ها برای فیلتر فعلی.
     */
    private function sumManualForFilters(array $filters)
    {
        $q = $this->createManualQuery($filters);
        $val = $q->select(['total' => new Expression('COALESCE(SUM(i.consumption_manual), 0)')])
            ->scalar();

        return (float) $val;
    }

    /**
     * جمع manual به تفکیک تاریخ گزارش.
     *
     * @return array [ 'Y-m-d' => float ]
     */
    private function manualByDate(array $filters)
    {
        $rows = $this->createManualQuery($filters)
            ->select([
                'report_date' => 'v.date',
                'total' => new Expression('COALESCE(SUM(i.consumption_manual), 0)'),
            ])
            ->groupBy(['v.date'])
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['report_date']] = (float) $row['total'];
        }

        return $map;
    }

    private function createManualQuery(array $filters)
    {
        $itemTable = VesselConsumptionItem::tableName();
        $headerTable = VesselFuelConsumption::tableName();

        $q = (new Query())
            ->from(['i' => $itemTable])
            ->innerJoin(['v' => $headerTable], 'v.id = i.consumption_id');

        if (!empty($filters['vessel_id'])) {
            $q->andWhere(['v.vesselIdRef' => (int) $filters['vessel_id']]);
        }
        if (!empty($filters['date_from'])) {
            $q->andWhere(['>=', 'v.date', $filters['date_from']]);
        }
        if (!empty($filters['date_to'])) {
            $q->andWhere(['<=', 'v.date', $filters['date_to']]);
        }

        return $q;
    }

    private function createBaseQuery(array $filters)
    {
        $query = VesselFuelConsumption::find();

        if (!empty($filters['vessel_id'])) {
            $query->andWhere(['vesselIdRef' => (int) $filters['vessel_id']]);
        }
        if (!empty($filters['date_from'])) {
            $query->andWhere(['>=', 'date', $filters['date_from']]);
        }
        if (!empty($filters['date_to'])) {
            $query->andWhere(['<=', 'date', $filters['date_to']]);
        }

        return $query;
    }

    private function calcDiff($mode, $tested, $ideal, $actual)
    {
        switch ($mode) {
            case self::CMP_TESTED_VS_IDEAL:
                return $tested - $ideal;
            case self::CMP_IDEAL_VS_ACTUAL:
                return $actual - $ideal;
            case self::CMP_TESTED_VS_ACTUAL:
            default:
                return $actual - $tested;
        }
    }

    private function calcDeviation($mode, $tested, $ideal, $actual, $diff)
    {
        switch ($mode) {
            case self::CMP_TESTED_VS_IDEAL:
                return $ideal > 0 ? ($diff / $ideal) * 100 : 0;
            case self::CMP_IDEAL_VS_ACTUAL:
                return $ideal > 0 ? ($diff / $ideal) * 100 : 0;
            case self::CMP_TESTED_VS_ACTUAL:
            default:
                return $tested > 0 ? ($diff / $tested) * 100 : 0;
        }
    }

    private function diffLabel($mode)
    {
        $map = [
            self::CMP_TESTED_VS_ACTUAL => 'واقعی کاربر − تست‌شده',
            self::CMP_TESTED_VS_IDEAL => 'تست‌شده − ایده‌آل',
            self::CMP_IDEAL_VS_ACTUAL => 'واقعی کاربر − ایده‌آل',
        ];

        return $map[$mode] ?? $map[self::CMP_TESTED_VS_ACTUAL];
    }
}
