<?php

namespace Odiseo\SyliusReportPlugin\DataFetcher;

use DateInterval;
use DateTime;
use Exception;
use InvalidArgumentException;

/**
 * Abstract class to provide time periods logic.
 *
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 * @author Diego D'amico <diego@odiseo.com.ar>
 */
abstract class TimePeriodDataFetcher extends BaseDataFetcher
{
    const PERIOD_DAY = 'day';
    const PERIOD_MONTH = 'month';
    const PERIOD_YEAR = 'year';

    /**
     * @return array
     */
    public static function getPeriodChoices()
    {
        return [
            'odiseo_sylius_report.ui.daily' => self::PERIOD_DAY,
            'odiseo_sylius_report.ui.monthly' => self::PERIOD_MONTH,
            'odiseo_sylius_report.ui.yearly' => self::PERIOD_YEAR,
        ];
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function fetch(array $configuration): Data
    {
        $data = new Data();

        /** @var DateTime $endDate */
        $endDate = isset($configuration['timePeriod']['end']) ? $configuration['timePeriod']['end'] : null;

        //There is added 23 hours 59 minutes 59 seconds to the end date to provide records for whole end date
        $configuration['timePeriod']['end'] = $endDate !== null ? $endDate->add(new DateInterval('PT23H59M59S')) : null;

        switch ($configuration['timePeriod']['period']) {
            case self::PERIOD_DAY:
                $this->setExtraConfiguration($configuration, 'P1D', '%a', 'Y-m-d', ['date']);
                break;
            case self::PERIOD_MONTH:
                $this->setExtraConfiguration($configuration, 'P1M', '%m', 'F Y', ['month', 'year']);
                break;
            case self::PERIOD_YEAR:
                $this->setExtraConfiguration($configuration, 'P1Y', '%y', 'Y', ['year']);
                break;
            default:
                throw new InvalidArgumentException('Wrong data fetcher period');
        }

        $rawData = $this->getData($configuration);

        if (empty($rawData)) {
            return $data;
        }

        $labelsAux = array_keys($rawData[0]);
        $labels = [];
        foreach ($labelsAux as $label) {
            if (!in_array($label, ['MonthDate', 'YearDate', 'DateDate'])) {
                $labels[] = $label;
            }
        }
        $data->setLabels($labels);

        $fetched = [];

        if ($configuration['empty_records']) {
            $fetched = $this->fillEmptyRecords($fetched, $configuration);
        }
        foreach ($rawData as $row) {
            $rowFetched = [];
            foreach ($labels as $i => $label) {
                if ($i === 0) {
                    $date = new DateTime($row[$labels[0]]);
                    $rowFetched[] = $date->format($configuration['timePeriod']['presentationFormat']);
                } else {
                    $rowFetched[] = $row[$labels[$i]];
                }
            }
            $fetched[] = $rowFetched;
        }

        $data->setData($fetched);

        $labels = [];
        foreach ($labelsAux as $label) {
            if (!in_array($label, ['MonthDate', 'YearDate', 'DateDate'])) {
                $labels[] = preg_replace('/(?!^)[A-Z]{2,}(?=[A-Z][a-z])|[A-Z][a-z]/', ' $0', (string)$label);
            }
        }
        $data->setLabels($labels);

        return $data;
    }

    /**
     * @param array  $configuration
     * @param string $interval
     * @param string $periodFormat
     * @param string $presentationFormat
     * @param array  $groupBy
     */
    protected function setExtraConfiguration(
        array &$configuration,
        $interval,
        $periodFormat,
        $presentationFormat,
        array $groupBy
    ) {
        $configuration['timePeriod']['interval'] = $interval;
        $configuration['timePeriod']['periodFormat'] = $periodFormat;
        $configuration['timePeriod']['presentationFormat'] = $presentationFormat;
        $configuration['groupBy'] = $groupBy;
        $configuration['empty_records'] = false;
    }

    /**
     * @param array $fetched
     * @param array $configuration
     *
     * @return array
     */
    private function fillEmptyRecords(array $fetched, array $configuration)
    {
        /** @var DateTime $startDate */
        $startDate = $configuration['start'];
        /** @var DateTime $startDate */
        $endDate = $configuration['end'];

        try {
            $dateInterval = new DateInterval($configuration['interval']);
        } catch (Exception $e) {
            return $fetched;
        }

        $numberOfPeriods = $startDate->diff($endDate);
        $formattedNumberOfPeriods = $numberOfPeriods->format($configuration['periodFormat']);

        for ($i = 0; $i <= $formattedNumberOfPeriods; ++$i) {
            $fetched[$startDate->format($configuration['presentationFormat'])] = 0;
            $startDate = $startDate->add($dateInterval);
        }

        return $fetched;
    }
}
