<?php
namespace app\modules\fuel\models;

use yii\db\Expression;

class VesselReportQuery
{
    public function getDashboardData(array $filters)
    {
        return [
            'summary' => $this->getSummary($filters),
            'daily_consumption' => $this->getDailyConsumption($filters),
            'daily_difference' => $this->getDailyDifference($filters),
            'daily_operating_hours' => $this->getDailyOperatingHours($filters),
        ];
    }

    public function getSummary(array $filters)
    {
        $row = $this->createBaseQuery($filters)
            ->select([
                'reports_count' => new Expression('COUNT(*)'),
                'report_days' => new Expression('COUNT(DISTINCT date)'),
                'total_operating_hours' => new Expression('COALESCE(SUM(operating_hours), 0)'),
                'actual_consumption' => new Expression('COALESCE(SUM(fuel_consumption_user_input), 0)'),
                'ideal_consumption' => new Expression('COALESCE(SUM(total_ideal_consumption), 0)'),
                'consumption_difference' => new Expression('COALESCE(SUM(consumption_difference), 0)'),
                'fuel_received' => new Expression('COALESCE(SUM(fuel_received), 0)'),
                'fuel_transferred' => new Expression('COALESCE(SUM(fuel_transferred), 0)'),
                'fuel_used' => new Expression('COALESCE(SUM(fuel_used), 0)'),
            ])
            ->asArray()
            ->one();

        $row = $row ?: [];
        $actual = (float)($row['actual_consumption'] ?? 0);
        $ideal = (float)($row['ideal_consumption'] ?? 0);
        $diff = (float)($row['consumption_difference'] ?? 0);

        $deviationPercent = $ideal > 0 ? ($diff / $ideal) * 100 : 0;

        return [
            'reports_count' => (int)($row['reports_count'] ?? 0),
            'report_days' => (int)($row['report_days'] ?? 0),
            'total_operating_hours' => round((float)($row['total_operating_hours'] ?? 0), 2),
            'actual_consumption' => round($actual, 2),
            'ideal_consumption' => round($ideal, 2),
            'consumption_difference' => round($diff, 2),
            'deviation_percent' => round($deviationPercent, 2),
            'fuel_received' => round((float)($row['fuel_received'] ?? 0), 2),
            'fuel_transferred' => round((float)($row['fuel_transferred'] ?? 0), 2),
            'fuel_used' => round((float)($row['fuel_used'] ?? 0), 2),
        ];
    }

    public function getDailyConsumption(array $filters)
    {
        $rows = $this->createBaseQuery($filters)
            ->select([
                'report_date' => 'date',
                'actual_consumption' => new Expression('COALESCE(SUM(fuel_consumption_user_input), 0)'),
                'ideal_consumption' => new Expression('COALESCE(SUM(total_ideal_consumption), 0)'),
                'consumption_difference' => new Expression('COALESCE(SUM(consumption_difference), 0)'),
            ])
            ->groupBy(['date'])
            ->orderBy(['date' => SORT_ASC])
            ->asArray()
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'report_date' => $row['report_date'],
                'actual_consumption' => round((float)$row['actual_consumption'], 2),
                'ideal_consumption' => round((float)$row['ideal_consumption'], 2),
                'consumption_difference' => round((float)$row['consumption_difference'], 2),
            ];
        }
        return $result;
    }

    public function getDailyDifference(array $filters)
    {
        $rows = $this->createBaseQuery($filters)
            ->select([
                'report_date' => 'date',
                'consumption_difference' => new Expression('COALESCE(SUM(consumption_difference), 0)'),
            ])
            ->groupBy(['date'])
            ->orderBy(['date' => SORT_ASC])
            ->asArray()
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'report_date' => $row['report_date'],
                'consumption_difference' => round((float)$row['consumption_difference'], 2),
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
                'operating_hours' => round((float)$row['operating_hours'], 2),
            ];
        }
        return $result;
    }

    private function createBaseQuery(array $filters)
    {
        $query = VesselFuelConsumption::find();

        if (!empty($filters['vessel_id'])) {
            $query->andWhere(['vesselIdRef' => (int)$filters['vessel_id']]);
        }
        if (!empty($filters['date_from'])) {
            $query->andWhere(['>=', 'date', $filters['date_from']]);
        }
        if (!empty($filters['date_to'])) {
            $query->andWhere(['<=', 'date', $filters['date_to']]);
        }

        return $query;
    }
}