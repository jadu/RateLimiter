<?php

namespace Zodyac;

use Zodyac\Cache\Storage\StorageInterface;

/**
 * Rate limiter. Uses a cache storage to keep a counter.
 */
class RateLimiter
{
    /**
     * Counter storage
     *
     * @var StorageInterface
     */
    protected $cache;

    /**
     * The number of requests to allow in the time period
     *
     * @var int
     */
    protected $limit;

    /**
     * The time period in minutes
     *
     * @var int
     */
    protected $period;

    /**
     * Constructor
     *
     * @param StorageInterface $cache
     * @param int $limit
     * @param int $period
     */
    public function __construct(StorageInterface $cache, $limit, $period)
    {
        $this->cache = $cache;
        $this->limit = $limit;
        $this->period = $period;
    }

    /**
     * Returns the number of requests to allow in the time period.
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Returns the time period in minutes.
     *
     * @return int
     */
    public function getPeriod()
    {
        return $this->period;
    }

    /**
     * Increment the counter.
     *
     * @param array|string $identifiers An array of identifiers or single identifier string
     * @param int $time A unix timestamp or null to use the current time
     */
    public function increment($identifiers, $time = null)
    {
        $this->cache->increment($this->getCacheKey($identifiers, $time), 0, ($this->period + 1) * 60);
    }

    /**
     * Whether the rate limit has been exceeded for the given identifiers.
     *
     * @param array|string $identifiers An array of identifiers or single identifier string
     * @param int $time A unix timestamp or null to use the current time
     * @return bool
     */
    public function exceeded($identifiers, $time = null)
    {
        if ($this->getTotal($identifiers, $time) >= $this->getLimit()) {
            return true;
        }

        return false;
    }

    /**
     * Returns the counter for the current period.
     *
     * @param array|string $identifiers An array of identifiers or single identifier string
     * @param int $time A unix timestamp or null to use the current time
     * @return int
     */
    public function getTotal($identifiers, $time = null)
    {
        $total = 0;
        $results = $this->cache->getMulti($this->getCacheKeysToCheck($identifiers, $time));

        if (count($results) > 0) {
            foreach ($results as $result) {
                if ($result->isHit()) {
                    $total += (int) $result->getValue();
                }
            }
        }

        return $total;
    }

    /**
     * Returns the cache key for the given minute.
     *
     * @param array|string $identifiers An array of identifiers or single identifier string
     * @param int $time A unix timestamp or null to use the current time
     * @return string
     */
    private function getCacheKey($identifiers, $time)
    {
        $time = $this->getTime($time);

        return sprintf('RateLimit:%s:%s', date('YmdHi', $time), sha1(serialize($identifiers)));
    }

    /**
     * Returns the cache keys to check when calculating the counter total for the current period.
     *
     * @param array|string $identifiers An array of identifiers or single identifier string
     * @param int $time A unix timestamp or null to use the current time
     * @return array
     */
    private function getCacheKeysToCheck($identifiers, $time)
    {
        $time = $this->getTime($time);

        $keys = array();
        for ($interval = 0; $interval < $this->getPeriod(); $interval++) {
            $keys[] = $this->getCacheKey($identifiers, $time - ($interval * 60));
        }

        return $keys;
    }

    /**
     * Returns the current time if the given time is null.
     *
     * @codeCoverageIgnore
     */
    private function getTime($time)
    {
        if ($time === null) {
            return time();
        }

        return $time;
    }
}
