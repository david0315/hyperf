<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Crontab;

class Parser
{
    /**
     *  解析crontab的定时格式，linux只支持到分钟/，这个类支持到秒.
     *
     * @param string $crontabString :
     *        0     1    2    3    4    5
     *        *     *    *    *    *    *
     *        -     -    -    -    -    -
     *        |     |    |    |    |    |
     *        |     |    |    |    |    +----- day of week (0 - 6) (Sunday=0)
     *        |     |    |    |    +----- month (1 - 12)
     *        |     |    |    +------- day of month (1 - 31)
     *        |     |    +--------- hour (0 - 23)
     *        |     +----------- min (0 - 59)
     *        +------------- sec (0-59)
     * @param int $startTime timestamp [default=current timestamp]
     * @throws InvalidArgumentException 错误信息
     * @return int unix timestamp - 下一分钟内执行是否需要执行任务，如果需要，则把需要在那几秒执行返回
     */
    public function parse($crontabString, $startTime = null)
    {
        if (! preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontabString))) {
            if (! preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontabString))) {
                throw new \InvalidArgumentException('Invalid cron string: ' . $crontabString);
            }
        }
        if ($startTime && ! is_numeric($startTime)) {
            throw new \InvalidArgumentException("\$startTime must be a valid unix timestamp ({$startTime} given)");
        }
        $cron = preg_split('/[\\s]+/i', trim($crontabString));
        $start = empty($startTime) ? time() : $startTime;
        if (count($cron) == 6) {
            $date = [
                'second' => $this->parseSegment($cron[0], 0, 59),
                'minutes' => $this->parseSegment($cron[1], 0, 59),
                'hours' => $this->parseSegment($cron[2], 0, 23),
                'day' => $this->parseSegment($cron[3], 1, 31),
                'month' => $this->parseSegment($cron[4], 1, 12),
                'week' => $this->parseSegment($cron[5], 0, 6),
            ];
        } elseif (count($cron) == 5) {
            $date = [
                'second' => [1 => 1],
                'minutes' => $this->parseSegment($cron[0], 0, 59),
                'hours' => $this->parseSegment($cron[1], 0, 23),
                'day' => $this->parseSegment($cron[2], 1, 31),
                'month' => $this->parseSegment($cron[3], 1, 12),
                'week' => $this->parseSegment($cron[4], 0, 6),
            ];
        }
        if (in_array(intval(date('i', $start)), $date['minutes']) && in_array(intval(date('G', $start)), $date['hours']) && in_array(intval(date('j', $start)), $date['day']) && in_array(intval(date('w', $start)), $date['week']) && in_array(intval(date('n', $start)), $date['month'])) {
            return $date['second'];
        }
        return null;
    }

    protected function parseSegment(string $string, int $min, int $max, int $start = null)
    {
        if ($start === null || $start < $min) {
            $start = $min;
        }
        $result = [];
        if ($string === '*') {
            for ($i = $start; $i <= $max; ++$i) {
                $result[] = $i;
            }
        } elseif (strpos($string, ',') !== false) {
            $exploded = explode(',', $string);
            foreach ($exploded as $value) {
                if (! $this->between((int) $value, (int) ($min > $start ? $min : $start), (int) $max)) {
                    continue;
                }
                $result[] = (int) $value;
            }
        } elseif (strpos($string, '/') !== false) {
            $exploded = explode('/', $string);
            if (strpos($exploded[0], '-') !== false) {
                [$nMin, $nMax] = explode('-', $exploded[0]);
                $nMin > $min && $min = $nMin;
                $nMax < $max && $max = $nMax;
            }
            $start > $min && $min = $start;
            for ($i = $start; $i <= $max;) {
                $result[] = $i;
                $i += $exploded[1];
            }
        } elseif ($this->between((int) $string, $min > $start ? $min : $start, $max)) {
            $result[] = (int) $string;
        }
        return $result;
    }

    private function between(int $value, int $min, int $max): bool
    {
        return $value >= $min && $value <= $max;
    }
}
